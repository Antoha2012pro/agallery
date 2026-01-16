<?php
if (!defined('ROOT_PATH')) die('Direct access not permitted');

define('PAGE', 'gallery');
$page_title = $language->get('agallery', 'gallery_title');

$repo = new aGalleryRepository();
$settings = new aGallerySettings($repo);

$can_upload = $user->isLoggedIn() && $user->hasPermission('agallery.upload');

$user_id = $user->isLoggedIn() ? (int)$user->data()->id : 0;
$viewable_categories = $user->isLoggedIn() ? $repo->categoriesViewable($user_id) : [];
$uploadable_categories = $user->isLoggedIn() ? $repo->categoriesUploadable($user_id) : [];

$per_page = 24;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $per_page;

// build allowed category ids
$allowed_cat_ids = array_map(fn($c) => (int)$c->id, $viewable_categories);
$images = [];
$total = 0;

if (count($allowed_cat_ids)) {
    $ph = implode(',', array_fill(0, count($allowed_cat_ids), '?'));

    $total_row = DB::getInstance()->query(
        "SELECT COUNT(*) AS c FROM `{$repo->t('agallery_images')}` WHERE status = 'approved' AND category_id IN ($ph)",
        $allowed_cat_ids
    )->first();

    $total = $total_row ? (int)$total_row->c : 0;

    $params = array_merge($allowed_cat_ids, [$per_page, $offset]);
    $images = DB::getInstance()->query(
        "SELECT i.*, u.username, c.name AS category_name
         FROM `{$repo->t('agallery_images')}` i
         LEFT JOIN `{$repo->t('users')}` u ON u.id = i.user_id
         LEFT JOIN `{$repo->t('agallery_categories')}` c ON c.id = i.category_id
         WHERE i.status = 'approved' AND i.category_id IN ($ph)
         ORDER BY i.created_at DESC
         LIMIT ? OFFSET ?",
        $params
    )->results() ?: [];
}

$total_pages = (int) ceil($total / $per_page);

$smarty->assign([
    'GALLERY_TITLE' => $language->get('agallery', 'gallery_title'),
    'UPLOAD_BUTTON' => $language->get('agallery', 'upload_button'),
    'UPLOAD_MODAL_TITLE' => $language->get('agallery', 'upload_modal_title'),
    'FIELD_CATEGORY' => $language->get('agallery', 'field_category'),
    'FIELD_TITLE' => $language->get('agallery', 'field_title'),
    'FIELD_DESCRIPTION' => $language->get('agallery', 'field_description'),
    'FIELD_FILE' => $language->get('agallery', 'field_file'),
    'SUBMIT_FOR_REVIEW' => $language->get('agallery', 'submit_for_review'),

    'CAN_UPLOAD' => $can_upload,
    'TOKEN' => Token::get(),
    'UPLOAD_URL' => URL::build('/gallery/upload'),

    'IMAGES' => $images,
    'PAGE' => $page,
    'TOTAL_PAGES' => $total_pages,
    'PAGINATION_BASE' => URL::build('/gallery'),

    'UPLOADABLE_CATEGORIES' => $uploadable_categories,
]);

require_once(ROOT_PATH . '/core/templates/header.php');
require_once(ROOT_PATH . '/core/templates/navbar.php');
$template->displayTemplate('aGallery/gallery.tpl', $smarty);
require_once(ROOT_PATH . '/core/templates/footer.php');