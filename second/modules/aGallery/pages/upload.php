<?php
if (!defined('ROOT_PATH')) die('Direct access not permitted');

header('Content-Type: application/json; charset=utf-8');

if (!$user->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
    exit;
}

if (!$user->hasPermission('agallery.upload')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'no_permission']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

if (!Token::check($_POST['token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'csrf']);
    exit;
}

$repo = new aGalleryRepository();
$settings = new aGallerySettings($repo);

$category_id = isset($_POST['category']) ? (int)$_POST['category'] : 0;
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($category_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'category_required']);
    exit;
}

$cat = $repo->categoryById($category_id);
if (!$cat) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'category_invalid']);
    exit;
}

$user_id = (int)$user->data()->id;
if (!$repo->canUploadCategory($user_id, $cat)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'category_no_upload']);
    exit;
}

if ($title === '' || mb_strlen($title) > 128) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'title_invalid']);
    exit;
}
if (mb_strlen($description) > 2000) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'description_too_long']);
    exit;
}

if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'file_required']);
    exit;
}

$max_mb = (int)$settings->get('max_upload_mb', 50);
$max_bytes = $max_mb * 1024 * 1024;

if ((int)$_FILES['file']['size'] <= 0 || (int)$_FILES['file']['size'] > $max_bytes) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'file_too_large']);
    exit;
}

$tmp = $_FILES['file']['tmp_name'];

// Real image check
$info = @getimagesize($tmp);
if ($info === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'not_image']);
    exit;
}

$mime = $info['mime'] ?? '';
if ($mime === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'mime_unknown']);
    exit;
}

// extension check based on filename (secondary)
$orig_name = $_FILES['file']['name'] ?? '';
$ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

// Allowed extensions setting is "declared", but we accept only if реально обработаем.
$allowed = (array)$settings->get('allowed_extensions', ['png','jpg','jpeg','webp','gif']);
if (!in_array($ext, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ext_not_allowed']);
    exit;
}

$processor = new aGalleryImageProcessor($repo, $settings);

if (!$processor->supportsMime($mime)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'format_not_supported']);
    exit;
}

// Create pending DB row first, to get image_id (for filename)
$now = $repo->now();
DB::getInstance()->insert($repo->t('agallery_images'), [
    'category_id' => $category_id,
    'user_id' => $user_id,
    'title' => $title,
    'description' => $description,
    'file_path' => '', // fill after processing
    'thumb_path' => '',
    'mime' => $mime,
    'ext' => $ext,
    'width' => (int)$info[0],
    'height' => (int)$info[1],
    'file_size' => (int)$_FILES['file']['size'],
    'status' => 'pending',
    'decline_reason' => null,
    'moderated_by' => null,
    'moderated_at' => null,
    'created_at' => $now,
    'updated_at' => $now,
]);

$image_id = (int) DB::getInstance()->lastId();

try {
    $out = $processor->processUpload($tmp, $mime, $ext, $user_id, (string)$user->data()->username, $image_id);

    DB::getInstance()->query(
        "UPDATE `{$repo->t('agallery_images')}`
         SET file_path = ?, thumb_path = ?, mime = ?, ext = ?, width = ?, height = ?, file_size = ?, updated_at = ?
         WHERE id = ?",
        [
            $out['file_path'], $out['thumb_path'], $out['mime'], $out['ext'],
            (int)$out['width'], (int)$out['height'], (int)$out['file_size'], $repo->now(),
            $image_id
        ]
    );

    $repo->log($user_id, $image_id, 'upload_pending', [
        'category_id' => $category_id,
        'mime' => $out['mime'],
        'ext' => $out['ext'],
        'w' => $out['width'],
        'h' => $out['height'],
    ]);

    // Notify moderators (Alert)
    $mods = $repo->moderatorUserIds();
    $mods = array_values(array_diff($mods, [$user_id])); // don't notify uploader as moderator

    $staff_url = URL::build('/panel/agallery/moderation', 'id=' . $image_id);
    $alert_title = $language->get('agallery', 'alert_mod_new_title');
    $alert_body = str_replace('{ID}', (string)$image_id, $language->get('agallery', 'alert_mod_new_body'));

    foreach ($mods as $mid) {
        Alert::send((int)$mid, $alert_title, $alert_body, $staff_url);
    }

    echo json_encode(['ok' => true, 'message' => $language->get('agallery', 'upload_success')]);
} catch (Throwable $e) {
    // Mark declined with reason (internal error)
    DB::getInstance()->query(
        "UPDATE `{$repo->t('agallery_images')}` SET status='declined', decline_reason=?, updated_at=? WHERE id=?",
        ['Internal processing error', $repo->now(), $image_id]
    );
    $repo->log($user_id, $image_id, 'upload_failed', ['error' => $e->getMessage()]);

    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'processing_failed']);
}
exit;