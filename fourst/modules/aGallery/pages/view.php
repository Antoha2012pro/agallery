<?php
if (!defined('ROOT_PATH')) {
    die('ROOT_PATH not defined');
}

require_once(ROOT_PATH . '/modules/aGallery/includes/autoload.php');

define('PAGE', 'gallery');

$db = DB::getInstance();
$language = aGallery_Compat::getLanguageFromGlobals();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$img = $id > 0 ? aGallery_Images::get($db, $id) : null;

if (!$img || ($img->status ?? '') !== 'approved') {
    aGallery_Compat::flash('agallery_error', aGallery_Compat::t($language, 'err_not_found', [], 'Не найдено.'));
    aGallery_Compat::redirect(aGallery_Compat::url('/gallery'));
}

$cat = aGallery_Categories::get($db, (int)$img->category_id);
if (!$cat || !aGallery_Categories::canViewCategory($db, $user, $cat)) {
    aGallery_Compat::flash('agallery_error', aGallery_Compat::t($language, 'err_no_view_access', [], 'Нет доступа.'));
    aGallery_Compat::redirect(aGallery_Compat::url('/gallery'));
}

$smarty->assign([
    'AGALLERY_TITLE' => aGallery_Compat::t($language, 'gallery_title', [], 'Галлерея'),
    'AGALLERY_IMG' => $img,
    'AGALLERY_BACK_URL' => aGallery_Compat::url('/gallery'),
]);

$smarty->display('aGallery/view.tpl');
