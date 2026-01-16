<?php
if (!defined('ROOT_PATH')) {
    die('Direct access not permitted');
}

const PAGE = 'agallery';

require_once ROOT_PATH . '/core/templates/frontend_init.php';
require_once ROOT_PATH . '/modules/aGallery/classes/aGallery.php';
require_once ROOT_PATH . '/modules/aGallery/classes/ImageProcessor.php';

$language = new Language();

// Handle upload POST (from modal).
if (Input::exists()) {
    if (!$user->isLoggedIn()) {
        Redirect::to(URL::build('/login'));
    }

    if (!$user->hasPermission('agallery.upload')) {
        Session::flash('agallery_error', $language->get('agallery', 'err_no_permission'));
        Redirect::to(URL::build('/gallery'));
    }

    if (!Token::check(Input::get('token'))) {
        Session::flash('agallery_error', $language->get('agallery', 'err_csrf'));
        Redirect::to(URL::build('/gallery'));
    }

    $categoryId = (int)Input::get('category');
    $title = trim((string)Input::get('title'));
    $description = trim((string)Input::get('description'));

    $cat = aGallery::getCategory($categoryId);
    if (!$cat) {
        Session::flash('agallery_error', $language->get('agallery', 'err_category'));
        Redirect::to(URL::build('/gallery'));
    }

    if (!aGallery::canUploadCategory($cat, (int)$user->data()->id)) {
        Session::flash('agallery_error', $language->get('agallery', 'err_category_upload'));
        Redirect::to(URL::build('/gallery'));
    }

    // Basic validation
    if ($title === '' || mb_strlen($title) > 128) {
        Session::flash('agallery_error', $language->get('agallery', 'err_title'));
        Redirect::to(URL::build('/gallery'));
    }
    if (mb_strlen($description) > 2000) {
        Session::flash('agallery_error', $language->get('agallery', 'err_description'));
        Redirect::to(URL::build('/gallery'));
    }

    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        Session::flash('agallery_error', $language->get('agallery', 'err_file_missing'));
        Redirect::to(URL::build('/gallery'));
    }

    $file = $_FILES['file'];

    if (!empty($file['error'])) {
        Session::flash('agallery_error', $language->get('agallery', 'err_file_upload'));
        Redirect::to(URL::build('/gallery'));
    }

    $settings = aGallery::settings();
    $maxMb = (int)($settings['max_upload_mb'] ?? '50');
    $maxBytes = $maxMb * 1024 * 1024;

    if ((int)$file['size'] > $maxBytes) {
        Session::flash('agallery_error', str_replace('{max}', (string)$maxMb, $language->get('agallery', 'err_file_too_large')));
        Redirect::to(URL::build('/gallery'));
    }

    $tmpPath = $file['tmp_name'];
    if (!is_uploaded_file($tmpPath)) {
        Session::flash('agallery_error', $language->get('agallery', 'err_file_upload'));
        Redirect::to(URL::build('/gallery'));
    }

    $info = @getimagesize($tmpPath);
    if (!$info || !isset($info[0], $info[1], $info[2])) {
        Session::flash('agallery_error', $language->get('agallery', 'err_invalid_image'));
        Redirect::to(URL::build('/gallery'));
    }

    $srcW = (int)$info[0];
    $srcH = (int)$info[1];
    $imageType = (int)$info[2];
    $mime = image_type_to_mime_type($imageType);

    // Allowed extensions from settings, but we only accept types we can decode+save.
    $allowedExt = array_map('trim', explode(',', (string)($settings['allowed_extensions'] ?? 'png,jpg,jpeg,webp,gif')));
    $allowedExt = array_filter($allowedExt, static fn($e) => $e !== '');

    $typeToExt = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
    ];
    if (!isset($typeToExt[$imageType])) {
        Session::flash('agallery_error', $language->get('agallery', 'err_unsupported_type'));
        Redirect::to(URL::build('/gallery'));
    }

    $srcExt = $typeToExt[$imageType];

    // If admin removed ext from allowed list => reject.
    if (!in_array($srcExt, $allowedExt, true) && !in_array(($srcExt === 'jpg' ? 'jpeg' : $srcExt), $allowedExt, true)) {
        Session::flash('agallery_error', $language->get('agallery', 'err_unsupported_type'));
        Redirect::to(URL::build('/gallery'));
    }

    $im = ImageProcessor::detectAndLoad($tmpPath, $imageType);
    if (!$im) {
        Session::flash('agallery_error', $language->get('agallery', 'err_unsupported_type'));
        Redirect::to(URL::build('/gallery'));
    }

    // Normalize / resize to max WxH
    $maxW = (int)($settings['max_width'] ?? '1920');
    $maxH = (int)($settings['max_height'] ?? '1080');

    [$norm, $normW, $normH] = ImageProcessor::resampleToFit($im, $srcW, $srcH, $maxW, $maxH);

    // Decide output format
    $convertToJpg = (int)($settings['convert_to_jpg'] ?? '0') === 1;
    $convertToWebp = (int)($settings['convert_to_webp'] ?? '0') === 1;

    $outExt = $srcExt;
    if ($convertToWebp && ImageProcessor::gdSupportsWebp()) {
        $outExt = 'webp';
        $mime = 'image/webp';
    } elseif ($convertToJpg && ImageProcessor::gdSupportsJpeg()) {
        $outExt = 'jpg';
        $mime = 'image/jpeg';
    } else {
        // keep source ext if possible; if it's webp but server doesn't support saving webp => fallback jpg
        if ($outExt === 'webp' && !ImageProcessor::gdSupportsWebp()) {
            if (!ImageProcessor::gdSupportsJpeg()) {
                Session::flash('agallery_error', $language->get('agallery', 'err_unsupported_type'));
                Redirect::to(URL::build('/gallery'));
            }
            $outExt = 'jpg';
            $mime = 'image/jpeg';
        }
    }

    $jpegQ = (int)($settings['image_quality_jpeg'] ?? '82');
    $webpQ = (int)($settings['image_quality_webp'] ?? '80');
    $thumbW = (int)($settings['thumb_width'] ?? '480');

    // Build paths
    $uid = (int)$user->data()->id;
    $uname = (string)$user->data()->username;
    $slug = aGallery::slugifyUsername($uname);
    $ts = time();
    $rand = bin2hex(random_bytes(4));

    // Create DB record first to get ID for filename pattern
    // We'll store placeholder paths, then update after save.
    $imageId = aGallery::createImage([
        'category_id' => $categoryId,
        'user_id' => $uid,
        'title' => $title,
        'description' => $description,
        'file_path' => 'uploads/agallery/tmp',
        'thumb_path' => 'uploads/agallery/tmp',
        'mime' => $mime,
        'ext' => $outExt,
        'width' => $normW,
        'height' => $normH,
        'file_size' => 0,
        'status' => 'pending',
    ]);

    $baseDir = ROOT_PATH . '/uploads/agallery/' . date('Y') . '/' . date('m') . '/';
    $origDir = $baseDir . 'original/';
    $thumbDir = $baseDir . 'thumbs/';

    if (!is_dir($origDir)) @mkdir($origDir, 0755, true);
    if (!is_dir($thumbDir)) @mkdir($thumbDir, 0755, true);

    $baseName = "gallery_u{$uid}_{$slug}_{$imageId}_{$ts}_{$rand}";
    $relOrig = 'uploads/agallery/' . date('Y') . '/' . date('m') . '/original/' . $baseName . '.' . $outExt;
    $relThumb = 'uploads/agallery/' . date('Y') . '/' . date('m') . '/thumbs/' . $baseName . '.' . $outExt;

    $absOrig = ROOT_PATH . '/' . $relOrig;
    $absThumb = ROOT_PATH . '/' . $relThumb;

    // Save normalized
    if (!ImageProcessor::save($norm, $absOrig, $outExt, $jpegQ, $webpQ)) {
        Session::flash('agallery_error', $language->get('agallery', 'err_save_failed'));
        Redirect::to(URL::build('/gallery'));
    }

    // Thumbnail
    [$thumbIm, $tW, $tH] = ImageProcessor::resampleWidth($norm, $normW, $normH, $thumbW);
    if (!ImageProcessor::save($thumbIm, $absThumb, $outExt, $jpegQ, $webpQ)) {
        @unlink($absOrig);
        Session::flash('agallery_error', $language->get('agallery', 'err_save_failed'));
        Redirect::to(URL::build('/gallery'));
    }

    $fileSize = (int)@filesize($absOrig);

    DB::getInstance()->query("
        UPDATE agallery_images
        SET file_path=?, thumb_path=?, file_size=?, updated_at=?
        WHERE id=?
    ", [$relOrig, $relThumb, $fileSize, time(), $imageId]);

    aGallery::insertAudit('upload_pending', $imageId, $uid, "cat={$categoryId}");

    // Notify moderators (ALERT with link)
    $modUsers = aGallery::usersWithPermission('agallery.moderate');
    $url = URL::build('/panel/agallery/moderation', 'id=' . $imageId);

    foreach ($modUsers as $mid) {
        Alert::send(
            (int)$mid,
            $language->get('agallery', 'alert_new_title'),
            str_replace('{id}', (string)$imageId, $language->get('agallery', 'alert_new_content')),
            $url
        );
    }

    Session::flash('agallery_success', $language->get('agallery', 'upload_submitted'));
    Redirect::to(URL::build('/gallery'));
}

// Build categories user can view
$cats = aGallery::getCategories();
$viewerId = $user->isLoggedIn() ? (int)$user->data()->id : null;

$viewableCatIds = [];
foreach ($cats as $c) {
    if (aGallery::canViewCategory($c, $viewerId)) {
        $viewableCatIds[] = (int)$c->id;
    }
}

$perPage = 12;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $perPage;

$total = aGallery::countApprovedImages($viewableCatIds);
$images = aGallery::listApprovedImages($viewableCatIds, $perPage, $offset);

$totalPages = (int)max(1, ceil($total / $perPage));

$canUpload = $user->isLoggedIn() && $user->hasPermission('agallery.upload');

// Categories allowed for upload (modal select)
$uploadCats = [];
if ($canUpload) {
    foreach ($cats as $c) {
        if (aGallery::canUploadCategory($c, (int)$user->data()->id)) {
            $uploadCats[] = $c;
        }
    }
}

$smarty->assign([
    'AGALLERY_TITLE' => $language->get('agallery', 'gallery_title'),
    'AGALLERY_UPLOAD' => $language->get('agallery', 'upload_button'),
    'CAN_UPLOAD' => $canUpload,
    'UPLOAD_CATS' => $uploadCats,
    'IMAGES' => $images,
    'PAGE' => $page,
    'TOTAL_PAGES' => $totalPages,
    'TOKEN' => Token::get(),
    'SUCCESS' => Session::exists('agallery_success') ? Session::flash('agallery_success') : null,
    'ERROR' => Session::exists('agallery_error') ? Session::flash('agallery_error') : null,
]);

$template->displayTemplate('agallery/gallery.tpl');
