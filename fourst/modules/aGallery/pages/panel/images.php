<?php
if (!defined('ROOT_PATH')) {
    die('ROOT_PATH not defined');
}

require_once(ROOT_PATH . '/modules/aGallery/includes/autoload.php');

define('PAGE', 'panel');
define('PARENT_PAGE', 'agallery');
define('PANEL_PAGE', 'agallery_images');

$db = DB::getInstance();
$language = aGallery_Compat::getLanguageFromGlobals();

aGallery_Compat::handlePanelPageLoadOrDeny($user, 'agallery.manage');

$errors = [];
$success = null;

if (Input::exists()) {
    $token = Input::get('token');
    if (!aGallery_Compat::tokenCheck($token)) {
        $errors[] = aGallery_Compat::t($language, 'err_csrf', [], 'CSRF токен неверный.');
    } else {
        $action = (string)Input::get('action');

        if ($action === 'edit') {
            $id = (int)Input::get('id');
            $catId = (int)Input::get('category');
            $title = aGallery_Util::cleanText(Input::get('title'));
            $desc = aGallery_Util::cleanText(Input::get('description'));

            if ($id <= 0 || $catId <= 0 || $title === '') {
                $errors[] = aGallery_Compat::t($language, 'err_invalid', [], 'Некорректные данные.');
            } else {
                aGallery_Images::updateMeta($db, $id, $catId, $title, $desc);
                aGallery_Audit::add($db, 'image_edit', $id, aGallery_Compat::userId($user));
                $success = aGallery_Compat::t($language, 'saved', [], 'Сохранено.');
            }
        }

        if ($action === 'delete') {
            $id = (int)Input::get('id');
            if ($id > 0) {
                aGallery_Images::delete($db, $id);
                aGallery_Audit::add($db, 'image_delete', $id, aGallery_Compat::userId($user));
                $success = aGallery_Compat::t($language, 'deleted', [], 'Удалено.');
            }
        }
    }
}

$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$limit = 25;
$offset = ($page - 1) * $limit;

$total = aGallery_Images::countByStatus($db, 'approved');
$pages = max(1, (int)ceil($total / $limit));

$list = aGallery_Images::listByStatus($db, 'approved', $limit, $offset);
$cats = aGallery_Categories::all($db);

$smarty->assign([
    'AGALLERY_TITLE' => aGallery_Compat::t($language, 'staff_images', [], 'Images'),
    'AGALLERY_TOKEN' => aGallery_Compat::tokenGet(),
    'AGALLERY_ERRORS' => $errors,
    'AGALLERY_SUCCESS' => $success,
    'AGALLERY_LIST' => $list,
    'AGALLERY_CATS' => $cats,
    'AGALLERY_PAGE' => $page,
    'AGALLERY_PAGES' => $pages,
]);

$smarty->display('aGallery/images.tpl');
