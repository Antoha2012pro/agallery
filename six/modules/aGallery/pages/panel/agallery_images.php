<?php

if (!defined('ROOT_PATH')) {
    die();
}

if (!$user->isLoggedIn() || !$user->hasPermission('agallery.manage')) {
    Redirect::to(URL::build('/login'));
}

// Handle edit/delete/recompress.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AGalleryCompat::checkToken('agallery_images', $language->get('general', 'invalid_token'));

    $db = DB::getInstance();
    $image_id = isset($_POST['image_id']) ? (int)$_POST['image_id'] : 0;
    $image = $db->query('SELECT * FROM agallery_images WHERE id = ?', [$image_id])->first();
    if ($image) {
        if (isset($_POST['save'])) {
            $db->update('agallery_images', $image_id, [
                'title' => $_POST['title'],
                'description' => $_POST['description'],
                'category_id' => (int)$_POST['category_id'],
                'updated_at' => time(),
            ]);
            Session::flash('agallery_images', $language->get('agallery', 'image_updated'));
        } elseif (isset($_POST['delete'])) {
            // Delete files.
            if ($image->file_path) {
                @unlink(ROOT_PATH . $image->file_path);
            }
            if ($image->thumb_path) {
                @unlink(ROOT_PATH . $image->thumb_path);
            }
            $db->delete('agallery_images', ['id', '=', $image_id]);
            Session::flash('agallery_images', $language->get('agallery', 'image_deleted'));
        }
    }

    Redirect::to(URL::build('/panel/agallery/images'));
}

$db = DB::getInstance();
$images = $db->query(
    'SELECT i.*, u.username, c.name AS category_name
     FROM agallery_images i
     JOIN nl2_users u ON u.id = i.user_id
     LEFT JOIN agallery_categories c ON c.id = i.category_id
     WHERE i.status = "approved"
     ORDER BY i.created_at DESC',
    []
)->results();

$categories = AGalleryCategoryService::getAll();

$smarty->assign([
    'PANEL_TITLE' => $language->get('agallery', 'panel_images_title'),
    'AGALLERY_IMAGES' => $images,
    'AGALLERY_CATEGORIES' => $categories,
    'AGALLERY_TOKEN_INPUT' => AGalleryCompat::generateTokenInput('agallery_images'),
]);

$template->onPageLoad();
require_once(ROOT_PATH . '/core/templates/panel_init.php');

$smarty->display('aGallery/panel_images.tpl');
