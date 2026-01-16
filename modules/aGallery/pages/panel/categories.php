<?php
if (!defined('ROOT_PATH')) {
    die('Direct access not permitted');
}

const PAGE = 'agallery_categories';

require_once ROOT_PATH . '/core/templates/backend_init.php';
require_once ROOT_PATH . '/modules/aGallery/classes/aGallery.php';

$language = new Language();

if (!$user->isLoggedIn() || !$user->hasPermission('agallery.manage')) {
    Redirect::to(URL::build('/panel'));
}

$groups = DB::getInstance()->query("SELECT id,name FROM nl2_groups WHERE deleted=0 ORDER BY `order` ASC")->results();

if (Input::exists()) {
    if (!Token::check(Input::get('token'))) {
        Session::flash('agallery_error', $language->get('agallery', 'err_csrf'));
        Redirect::to(URL::build('/panel/agallery/categories'));
    }

    $action = (string)Input::get('action');

    if ($action === 'create' || $action === 'update') {
        $id = (int)Input::get('id');
        $name = trim((string)Input::get('name'));
        $desc = trim((string)Input::get('description'));
        $sort = (int)Input::get('sort_order');

        $viewGroups = Input::get('view_groups');
        $uploadGroups = Input::get('upload_groups');
        if (!is_array($viewGroups)) $viewGroups = [];
        if (!is_array($uploadGroups)) $uploadGroups = [];

        $viewGroups = array_values(array_map('intval', $viewGroups));
        $uploadGroups = array_values(array_map('intval', $uploadGroups));

        if ($name === '' || mb_strlen($name) > 64) {
            Session::flash('agallery_error', $language->get('agallery', 'err_cat_name'));
            Redirect::to(URL::build('/panel/agallery/categories'));
        }

        $now = time();

        if ($action === 'create') {
            DB::getInstance()->query("
                INSERT INTO agallery_categories (name,description,sort_order,view_groups,upload_groups,created_at,updated_at)
                VALUES (?,?,?,?,?,?,?)
            ", [$name, $desc, $sort, json_encode($viewGroups), json_encode($uploadGroups), $now, $now]);

            Session::flash('agallery_success', $language->get('agallery', 'cat_saved'));
        } else {
            DB::getInstance()->query("
                UPDATE agallery_categories
                SET name=?, description=?, sort_order=?, view_groups=?, upload_groups=?, updated_at=?
                WHERE id=?
            ", [$name, $desc, $sort, json_encode($viewGroups), json_encode($uploadGroups), $now, $id]);

            Session::flash('agallery_success', $language->get('agallery', 'cat_saved'));
        }

        Redirect::to(URL::build('/panel/agallery/categories'));
    }

    if ($action === 'delete') {
        $id = (int)Input::get('id');
        DB::getInstance()->query("DELETE FROM agallery_categories WHERE id=?", [$id]);
        Session::flash('agallery_success', $language->get('agallery', 'cat_deleted'));
        Redirect::to(URL::build('/panel/agallery/categories'));
    }
}

$cats = aGallery::getCategories();

$smarty->assign([
    'TOKEN' => Token::get(),
    'CATS' => $cats,
    'GROUPS' => $groups,
    'TITLE' => 'aGallery! - ' . $language->get('agallery', 'staffcp_categories'),
    'SUCCESS' => Session::exists('agallery_success') ? Session::flash('agallery_success') : null,
    'ERROR' => Session::exists('agallery_error') ? Session::flash('agallery_error') : null,
    'L' => [
        'categories' => $language->get('agallery', 'staffcp_categories'),
        'add' => $language->get('agallery', 'cat_add'),
        'edit' => $language->get('agallery', 'cat_edit'),
        'delete' => $language->get('agallery', 'cat_delete'),
        'name' => $language->get('agallery', 'cat_name'),
        'description' => $language->get('agallery', 'cat_description'),
        'sort' => $language->get('agallery', 'cat_sort'),
        'view_groups' => $language->get('agallery', 'cat_view_groups'),
        'upload_groups' => $language->get('agallery', 'cat_upload_groups'),
        'save' => $language->get('agallery', 'save'),
        'confirm' => $language->get('agallery', 'confirm'),
    ],
]);

$template->displayTemplate('agallery/categories.tpl');
