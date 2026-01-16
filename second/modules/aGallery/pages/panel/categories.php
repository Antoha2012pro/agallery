<?php
if (!defined('ROOT_PATH')) die('Direct access not permitted');

if (!$user->isLoggedIn()) { Redirect::to(URL::build('/login')); die(); }
if (!$user->hasPermission('agallery.manage')) { require_once(ROOT_PATH . '/403.php'); die(); }

$repo = new aGalleryRepository();
$groups = $repo->allGroups();

$errors = [];
$success = null;

$action = $_GET['action'] ?? '';
$edit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Token::check($_POST['token'] ?? '')) $errors[] = $language->get('agallery', 'csrf_invalid');

    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $sort = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

    $view_groups = isset($_POST['view_groups']) && is_array($_POST['view_groups']) ? array_map('intval', $_POST['view_groups']) : [];
    $upload_groups = isset($_POST['upload_groups']) && is_array($_POST['upload_groups']) ? array_map('intval', $_POST['upload_groups']) : [];

    if ($name === '' || mb_strlen($name) > 64) $errors[] = $language->get('agallery', 'category_name_invalid');
    if (!count($view_groups)) $errors[] = $language->get('agallery', 'category_view_groups_required');
    if (!count($upload_groups)) $errors[] = $language->get('agallery', 'category_upload_groups_required');

    if (!count($errors)) {
        $now = $repo->now();

        if (isset($_POST['create'])) {
            DB::getInstance()->insert($repo->t('agallery_categories'), [
                'name' => $name,
                'description' => $desc,
                'sort_order' => $sort,
                'view_groups' => json_encode(array_values(array_unique($view_groups))),
                'upload_groups' => json_encode(array_values(array_unique($upload_groups))),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $repo->log((int)$user->data()->id, null, 'category_create', ['name' => $name]);
            $success = $language->get('agallery', 'saved');
        }

        if (isset($_POST['update']) && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            DB::getInstance()->query(
                "UPDATE `{$repo->t('agallery_categories')}`
                 SET name=?, description=?, sort_order=?, view_groups=?, upload_groups=?, updated_at=?
                 WHERE id=?",
                [
                    $name, $desc, $sort,
                    json_encode(array_values(array_unique($view_groups))),
                    json_encode(array_values(array_unique($upload_groups))),
                    $now, $id
                ]
            );
            $repo->log((int)$user->data()->id, null, 'category_update', ['id' => $id]);
            $success = $language->get('agallery', 'saved');
        }

        if (isset($_POST['delete']) && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            DB::getInstance()->query("DELETE FROM `{$repo->t('agallery_categories')}` WHERE id=?", [$id]);
            $repo->log((int)$user->data()->id, null, 'category_delete', ['id' => $id]);
            $success = $language->get('agallery', 'deleted');
        }
    }
}

$cats = $repo->categoriesAll();
$edit = null;
if ($action === 'edit' && $edit_id > 0) {
    $edit = $repo->categoryById($edit_id);
}

$page_title = $language->get('agallery', 'staffcp_categories');

require_once(ROOT_PATH . '/core/templates/panel_header.php');
require_once(ROOT_PATH . '/core/templates/panel_navbar.php');

$smarty->assign([
    'TITLE' => $page_title,
    'TABS_TEMPLATE' => 'aGallery/header_tabs.tpl',
    'ACTIVE_TAB' => 'categories',

    'TOKEN' => Token::get(),
    'ERRORS' => $errors,
    'SUCCESS' => $success,

    'CATEGORIES' => $cats,
    'GROUPS' => $groups,

    'EDIT' => $edit,
    'EDIT_VIEW_GROUPS' => $edit ? (json_decode($edit->view_groups, true) ?: []) : [],
    'EDIT_UPLOAD_GROUPS' => $edit ? (json_decode($edit->upload_groups, true) ?: []) : [],
]);

$template->displayTemplate('aGallery/categories.tpl', $smarty);

require_once(ROOT_PATH . '/core/templates/panel_footer.php');