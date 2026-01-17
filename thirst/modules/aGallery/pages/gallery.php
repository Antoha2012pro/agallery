<?php
require_once ROOT_PATH . '/modules/aGallery/includes/autoload.php';

aGallery::ensureUploadDirs();
aGallery_Compat::requireFrontendInit();

global $user, $smarty, $language;

// Handle upload POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agallery_upload'])) {
    if (!$user->isLoggedIn()) {
        aGallery_Compat::flash('error', aGallery_Compat::lang('login_required', 'errors'));
        aGallery_Compat::redirect(URL::build('/login'));
    }

    if (!$user->hasPermission('agallery.upload')) {
        aGallery_Compat::flash('error', aGallery_Compat::lang('no_permission_upload', 'errors'));
        aGallery_Compat::redirect(URL::build('/gallery'));
    }

    if (!aGallery_Compat::checkToken($_POST['token'] ?? null)) {
        aGallery_Compat::flash('error', aGallery_Compat::lang('invalid_token', 'errors'));
        aGallery_Compat::redirect(URL::build('/gallery'));
    }

    $categoryId = (int)($_POST['category'] ?? 0);
    $title = aGallery_Utils::cleanText($_POST['title'] ?? '', 80, false);
    $desc = aGallery_Utils::cleanText($_POST['description'] ?? '', 600, true);
    if ($desc === '') $desc = null;

    if ($categoryId <= 0) {
        aGallery_Compat::flash('error', aGallery_Compat::lang('category_required', 'errors'));
        aGallery_Compat::redirect(URL::build('/gallery'));
    }
    if ($title === '') {
        aGallery_Compat::flash('error', aGallery_Compat::lang('title_required', 'errors'));
        aGallery_Compat::redirect(URL::build('/gallery'));
    }

    $cat = aGallery::getCategory($categoryId);
    if (!$cat) {
        aGallery_Compat::flash('error', aGallery_Compat::lang('category_invalid', 'errors'));
        aGallery_Compat::redirect(URL::build('/gallery'));
    }

    if (!aGallery::canUploadToCategory($user, $cat)) {
        aGallery_Compat::flash('error', aGallery_Compat::lang('category_upload_denied', 'errors'));
        aGallery_Compat::redirect(URL::build('/gallery'));
    }

    // Settings
    $settings = [
        'max_upload_mb' => (int)aGallery::getSetting('max_upload_mb', 50),
        'max_width' => (int)aGallery::getSetting('max_width', 1920),
        'max_height' => (int)aGallery::getSetting('max_height', 1080),
        'allowed_extensions' => (string)aGallery::getSetting('allowed_extensions', 'png,jpg,jpeg,webp,gif'),
        'image_quality_jpeg' => (int)aGallery::getSetting('image_quality_jpeg', 82),
        'image_quality_webp' => (int)aGallery::getSetting('image_quality_webp', 80),
        'thumb_width' => (int)aGallery::getSetting('thumb_width', 480),
        'allow_convert' => (int)aGallery::getSetting('allow_convert', 0),
    ];

    try {
        if (!isset($_FILES['file'])) {
            throw new RuntimeException(aGallery_Compat::lang('file_required', 'errors'));
        }
        $meta = aGallery_ImageProcessor::handleUpload($user, $_FILES['file'], $settings);

        $imageId = aGallery::createPendingImage(
            $categoryId,
            (int)$user->data()->id,
            $title,
            $desc,
            $meta
        );

        // Notify moderators
        aGallery::notifyModeratorsNewPending($imageId, (int)$user->data()->id);

        aGallery_Compat::flash('success', aGallery_Compat::lang('upload_submitted', 'general'));
        aGallery_Compat::redirect(URL::build('/gallery'));

    } catch (Throwable $e) {
        aGallery_Compat::flash('error', $e->getMessage());
        aGallery_Compat::redirect(URL::build('/gallery'));
    }
}

// Render page
$pageTitle = aGallery_Compat::lang('gallery', 'general');
$success = aGallery_Compat::getFlash('success');
$error = aGallery_Compat::getFlash('error');

// Categories and upload categories
$catsAll = aGallery::getCategories();
$catsViewable = [];
$catsUploadable = [];
foreach ($catsAll as $c) {
    if (aGallery::canViewCategory($user, $c)) $catsViewable[] = $c;
    if (aGallery::canUploadToCategory($user, $c)) $catsUploadable[] = $c;
}

// Pagination and images list (approved only and only viewable categories)
$perPage = (int)aGallery::getSetting('per_page', 24);
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $perPage;

$catIds = array_map(static fn($c) => (int)$c->id, $catsViewable);
if (!count($catIds)) {
    $images = [];
    $total = 0;
} else {
    $db = DB::getInstance();
    $in = implode(',', array_map('intval', $catIds));

    $cnt = $db->query("SELECT COUNT(*) c FROM `" . $db->getPrefix() . "agallery_images` WHERE `status`='approved' AND `category_id` IN ($in)");
    if (!$cnt || $cnt->error()) {
        // fallback without prefix
        $total = 0;
        $images = [];
    } else {
        $total = (int)$cnt->first()->c;
        $q = $db->query("SELECT i.*, c.name category_name
                         FROM `" . $db->getPrefix() . "agallery_images` i
                         JOIN `" . $db->getPrefix() . "agallery_categories` c ON c.id=i.category_id
                         WHERE i.`status`='approved' AND i.`category_id` IN ($in)
                         ORDER BY i.`created_at` DESC
                         LIMIT " . (int)$perPage . " OFFSET " . (int)$offset);
        $images = $q && !$q->error() ? $q->results() : [];
    }
}

$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
$pages = [];
for ($i = 1; $i <= max(1, $totalPages); $i++) {
    $pages[] = [
        'n' => $i,
        'active' => $i === $page,
        'url' => URL::build('/gallery', 'p=' . $i),
    ];
}

$canUpload = $user->isLoggedIn() && $user->hasPermission('agallery.upload') && count($catsUploadable) > 0;

$items = [];
foreach ($images as $img) {
    $items[] = [
        'id' => (int)$img->id,
        'title' => $img->title,
        'description' => $img->description,
        'thumb_url' => URL::build('/' . ltrim($img->thumb_path, '/')),
        'file_url' => URL::build('/' . ltrim($img->file_path, '/')),
        'category' => $img->category_name,
        'created_at' => $img->created_at,
        'mime' => $img->mime,
        'w' => (int)$img->width,
        'h' => (int)$img->height,
    ];
}

$smarty->assign([
    'AGALLERY_TITLE' => $pageTitle,
    'AGALLERY_SUCCESS' => $success,
    'AGALLERY_ERROR' => $error,
    'AGALLERY_CAN_UPLOAD' => $canUpload,
    'AGALLERY_UPLOAD_CATEGORIES' => array_map(static function($c) {
        return ['id' => (int)$c->id, 'name' => $c->name];
    }, $catsUploadable),
    'AGALLERY_IMAGES' => $items,
    'AGALLERY_PAGES' => $pages,
    'AGALLERY_TOKEN_FIELD' => aGallery_Compat::tokenField(),
    'AGALLERY_MAX_MB' => (int)aGallery::getSetting('max_upload_mb', 50),
]);

// Template
$templatePath = ROOT_PATH . '/custom/templates/DefaultRevamp/aGallery/gallery.tpl';
if (is_file($templatePath)) {
    $smarty->display($templatePath);
} else {
    echo '<h2>' . aGallery_Utils::h($pageTitle) . '</h2>';
    echo '<p>Template not found: ' . aGallery_Utils::h($templatePath) . '</p>';
}
