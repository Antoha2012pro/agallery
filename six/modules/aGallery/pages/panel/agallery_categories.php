<?php

if (!defined('ROOT_PATH')) {
    die();
}

if (!$user->isLoggedIn() || !$user->hasPermission('agallery.manage')) {
    Redirect::to(URL::build('/login'));
}

$page = 'panel';
$timeago = new TimeAgo(TIMEZONE);
$ag_title = $language->get('agallery', 'panel_categories_title');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AGalleryCompat::checkToken('agallery_categories', $language->get('general', 'invalid_token'));

    if (isset($_POST['create'])) {
        AGalleryCategoryService::create([
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'sort_order' => (int)$_POST['sort_order'],
            'view_groups' => json_encode($_POST['view_groups'] ?? []),
            'upload_groups' => json_encode($_POST['upload_groups'] ?? []),
        ]);
        Session::flash('agallery_categories', $language->get('agallery', 'category_created'));
        Redirect::to(URL::build('/panel/agallery/categories'));
    } elseif (isset($_POST['update']) && isset($_POST['id'])) {
        AGalleryCategoryService::update((int)$_POST['id'], [
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'sort_order' => (int)$_POST['sort_order'],
            'view_groups' => json_encode($_POST['view_groups'] ?? []),
            'upload_groups' => json_encode($_POST['upload_groups'] ?? []),
        ]);
        Session::flash('agallery_categories', $language->get('agallery', 'category_updated'));
        Redirect::to(URL::build('/panel/agallery/categories'));
    } elseif (isset($_POST['delete']) && isset($_POST['id'])) {
        AGalleryCategoryService::delete((int)$_POST['id']);
        Session::flash('agallery_categories', $language->get('agallery', 'category_deleted'));
        Redirect::to(URL::build('/panel/agallery/categories'));
    }
}

$categories = AGalleryCategoryService::getAll();

// TODO: load groups from Group model; compat: assume Group::all() exists
$groups = [];
if (class_exists('Group') && method_exists('Group', 'all')) {
    $groups = Group::all();
}

$smarty->assign([
    'PANEL_TITLE' => $ag_title,
    'AGALLERY_CATEGORIES' => $categories,
    'AGALLERY_GROUPS' => $groups,
    'AGALLERY_TOKEN_INPUT' => AGalleryCompat::generateTokenInput('agallery_categories'),
]);

$template->onPageLoad();
require_once(ROOT_PATH . '/core/templates/panel_init.php');

$smarty->display('aGallery/panel_categories.tpl');
