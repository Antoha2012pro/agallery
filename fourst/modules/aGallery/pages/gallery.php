<?php
if (!defined('ROOT_PATH')) {
    die('ROOT_PATH not defined');
}

require_once(ROOT_PATH . '/modules/aGallery/includes/autoload.php');

define('PAGE', 'gallery');

$db = DB::getInstance();
$language = aGallery_Compat::getLanguageFromGlobals();

$settings = new aGallery_Settings($db);

$success = aGallery_Compat::flash('agallery_success');
$error = aGallery_Compat::flash('agallery_error');

$canUpload = aGallery_Compat::isLoggedIn($user) && aGallery_Compat::hasPerm($user, 'agallery.upload');

$uploadCats = $canUpload ? aGallery_Categories::uploadableForUser($db, $user) : [];

$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$limit = max(1, $settings->getInt('page_limit', 24));
$offset = ($page - 1) * $limit;

$total = aGallery_Images::countApprovedForUser($db, $user);
$pages = max(1, (int)ceil($total / $limit));

$images = aGallery_Images::listApprovedForUser($db, $user, $limit, $offset);

$smarty->assign([
    'AGALLERY_TITLE' => aGallery_Compat::t($language, 'gallery_title', [], 'Галлерея'),
    'AGALLERY_CAN_UPLOAD' => $canUpload,
    'AGALLERY_UPLOAD_CATS' => $uploadCats,
    'AGALLERY_TOKEN' => aGallery_Compat::tokenGet(),
    'AGALLERY_SUCCESS' => $success,
    'AGALLERY_ERROR' => $error,
    'AGALLERY_IMAGES' => $images,
    'AGALLERY_PAGE' => $page,
    'AGALLERY_PAGES' => $pages,
    'AGALLERY_TOTAL' => $total,
    'AGALLERY_URL_BASE' => aGallery_Compat::url('/gallery'),
    'AGALLERY_UPLOAD_URL' => aGallery_Compat::url('/gallery/upload'),
    'AGALLERY_VIEW_URL' => aGallery_Compat::url('/gallery/view'),
]);

$smarty->display('aGallery/gallery.tpl');
