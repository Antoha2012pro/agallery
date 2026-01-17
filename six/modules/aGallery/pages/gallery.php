<?php
// /gallery frontend page.

if (!defined('ROOT_PATH')) {
    die();
}

$smarty->assign([
    'AGALLERY_TITLE' => $language->get('agallery', 'page_title'),
]);

$categories = AGalleryCategoryService::getAllForView($user);

// Pagination params.
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$perPage = 24;
$offset = ($page - 1) * $perPage;

// Images (approved only).
$db = DB::getInstance();
$images = $db->query(
    'SELECT i.*, u.username FROM agallery_images i JOIN nl2_users u ON u.id = i.user_id WHERE i.status = "approved" ORDER BY i.created_at DESC LIMIT ? OFFSET ?',
    [$perPage, $offset]
)->results();

$total = $db->query('SELECT COUNT(*) AS c FROM agallery_images WHERE status = "approved"', [])->first()->c;
$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

// Upload button visibility.
$canUploadAny = false;
foreach ($categories as $cat) {
    if (AGalleryCategoryService::canUploadToCategory($user, $cat)) {
        $canUploadAny = true;
        break;
    }
}

$smarty->assign([
    'AGALLERY_CATEGORIES' => $categories,
    'AGALLERY_IMAGES' => $images,
    'AGALLERY_CAN_UPLOAD' => $canUploadAny,
    'AGALLERY_CURRENT_PAGE' => $page,
    'AGALLERY_TOTAL_PAGES' => $totalPages,
    'AGALLERY_UPLOAD_LABEL' => $language->get('agallery', 'button_upload'),
    'AGALLERY_GALLERY_LABEL' => $language->get('agallery', 'page_title'),
]);

// Handle upload POST (modal).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agallery_upload'])) {
    AGalleryCompat::checkToken('agallery_upload', $language->get('general', 'invalid_token'));

    $result = AGalleryImageService::handleUpload($user);
    if ($result['success']) {
        Session::flash('agallery_success', $language->get('agallery', 'upload_submitted'));
        // Notify moderators.
        $moderators = AGalleryCompat::getUsersWithPermission('agallery.moderate');
        if (count($moderators)) {
            $title = $language->get('agallery', 'notif_new_upload_title');
            $content = $language->get('agallery', 'notif_new_upload_content');
            AGalleryCompat::sendModerationNotification(
                (int)$user->data()->id,
                $moderators,
                $title,
                $content
            );
        }
        Redirect::to(URL::build('/gallery'));
    } else {
        $smarty->assign('AGALLERY_ERRORS', $result['errors']);
    }
}

$template->onPageLoad();
require_once(ROOT_PATH . '/core/templates/frontend_init.php');

$smarty->display('aGallery/gallery.tpl');
