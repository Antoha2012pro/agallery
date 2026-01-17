<?php
if (!defined('ROOT_PATH')) {
    die('ROOT_PATH not defined');
}

require_once(ROOT_PATH . '/modules/aGallery/includes/autoload.php');

define('PAGE', 'panel');
define('PARENT_PAGE', 'agallery');
define('PANEL_PAGE', 'agallery_categories');

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

        if ($action === 'create' || $action === 'update') {
            $id = (int)Input::get('id');
            $name = aGallery_Util::cleanText(Input::get('name'));
            $desc = aGallery_Util::cleanText(Input::get('description'));
            $sort = (int)Input::get('sort_order');

            $viewGroups = (array)($_POST['view_groups'] ?? []);
            $uploadGroups = (array)($_POST['upload_groups'] ?? []);

            $viewGroups = array_map('intval', $viewGroups);
            $uploadGroups = array_map('intval', $uploadGroups);

            if ($name === '' || mb_strlen($name) > 64) {
                $errors[] = aGallery_Compat::t($language, 'err_name_invalid', [], 'Некорректное имя.');
            } else {
                if ($action === 'create') {
                    aGallery_Categories::create($db, $name, $desc, $sort, $viewGroups, $uploadGroups);
                    aGallery_Audit::add($db, 'category_create', null, aGallery_Compat::userId($user), ['name' => $name]);
                    $success = aGallery_Compat::t($language, 'saved', [], 'Сохранено.');
                } else {
                    aGallery_Categories::update($db, $id, $name, $desc, $sort, $viewGroups, $uploadGroups);
                    aGallery_Audit::add($db, 'category_update', null, aGallery_Compat::userId($user), ['id' => $id]);
                    $success = aGallery_Compat::t($language, 'saved', [], 'Сохранено.');
                }
            }
        }

        if ($action === 'delete') {
            $id = (int)Input::get('id');
            aGallery_Categories::delete($db, $id);
            aGallery_Audit::add($db, 'category_delete', null, aGallery_Compat::userId($user), ['id' => $id]);
            $success = aGallery_Compat::t($language, 'deleted', [], 'Удалено.');
        }
    }
}

$cats = aGallery_Categories::all($db);
$groups = aGallery_Categories::groupsList($db);

$smarty->assign([
    'AGALLERY_TITLE' => aGallery_Compat::t($language, 'staff_categories', [], 'Categories'),
    'AGALLERY_TOKEN' => aGallery_Compat::tokenGet(),
    'AGALLERY_ERRORS' => $errors,
    'AGALLERY_SUCCESS' => $success,
    'AGALLERY_CATS' => $cats,
    'AGALLERY_GROUPS' => $groups,
]);

$smarty->display('aGallery/categories.tpl');
