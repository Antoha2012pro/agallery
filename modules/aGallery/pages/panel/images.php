<?php
if (!defined('ROOT_PATH')) {
    die('Direct access not permitted');
}

const PAGE = 'agallery_images';

require_once ROOT_PATH . '/core/templates/backend_init.php';
require_once ROOT_PATH . '/modules/aGallery/classes/aGallery.php';

$language = new Language();

if (!$user->isLoggedIn() || !$user->hasPermission('agallery.manage')) {
    Redirect::to(URL::build('/panel'));
}

$cats = aGallery::getCategories();

if (Input::exists()) {
    if (!Token::check(Input::get('token'))) {
        Session::flash('agallery_error', $language->get('agallery', 'err_csrf'));
        Redirect::to(URL::build('/panel/agallery/images'));
    }

    $action = (string)Input::get('action');
    $imageId = (int)Input::get('image_id');

    if ($action === 'update') {
        $categoryId = (int)Input::get('category');
        $title = trim((string)Input::get('title'));
        $desc = trim((string)Input::get('description'));

        if ($title === '' || mb_strlen($title) > 128) {
            Session::flash('agallery_error', $language->get('agallery', 'err_title'));
            Redirect::to(URL::build('/panel/agallery/images', 'id=' . $imageId));
        }

        aGallery::updateApproved($imageId, $categoryId, $title, $desc);
        aGallery::insertAudit('edit', $imageId, (int)$user->data()->id, null);

        Session::flash('agallery_success', $language->get('agallery', 'images_saved'));
        Redirect::to(URL::build('/panel/agallery/images', 'id=' . $imageId));
    }

    if ($action === 'delete') {
        aGallery::deleteImage($imageId);
        aGallery::insertAudit('delete', $imageId, (int)$user->data()->id, null);

        Session::flash('agallery_success', $language->get('agallery', 'images_deleted'));
        Redirect::to(URL::build('/panel/agallery/images'));
    }
}

// list approved
$perPage = 20;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $perPage;

$row = DB::getInstance()->query("SELECT COUNT(*) AS cnt FROM agallery_images WHERE status='approved'")->first();
$total = (int)($row->cnt ?? 0);

$items = DB::getInstance()->query("
    SELECT i.*, c.name AS category_name
    FROM agallery_images i
    JOIN agallery_categories c ON c.id=i.category_id
    WHERE i.status='approved'
    ORDER BY i.created_at DESC
    LIMIT $perPage OFFSET $offset
")->results();

$totalPages = (int)max(1, ceil($total / $perPage));

$focusId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$focus = $focusId ? aGallery::getImage($focusId) : null;

$smarty->assign([
    'TOKEN' => Token::get(),
    'TITLE' => 'aGallery! - ' . $language->get('agallery', 'staffcp_images'),
    'ITEMS' => $items,
    'CATS' => $cats,
    'FOCUS' => $focus,
    'PAGE_NO' => $page,
    'TOTAL_PAGES' => $totalPages,
    'SUCCESS' => Session::exists('agallery_success') ? Session::flash('agallery_success') : null,
    'ERROR' => Session::exists('agallery_error') ? Session::flash('agallery_error') : null,
    'L' => [
        'images' => $language->get('agallery', 'staffcp_images'),
        'save' => $language->get('agallery', 'save'),
        'delete' => $language->get('agallery', 'cat_delete'),
        'confirm' => $language->get('agallery', 'confirm'),
        'title' => $language->get('agallery', 'field_title'),
        'description' => $language->get('agallery', 'field_description'),
        'category' => $language->get('agallery', 'field_category'),
    ],
]);

$template->displayTemplate('agallery/images.tpl');
