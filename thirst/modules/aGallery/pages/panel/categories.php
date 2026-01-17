<?php
require_once ROOT_PATH . '/modules/aGallery/includes/autoload.php';

aGallery_Compat::requireBackendInit();
global $user;

if (!$user->isLoggedIn()) aGallery_Compat::redirect(URL::build('/login'));
if (method_exists($user, 'handlePanelPageLoad')) $user->handlePanelPageLoad('agallery.manage');
if (!$user->hasPermission('agallery.manage')) aGallery_Compat::redirect(URL::build('/panel'));

$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!aGallery_Compat::checkToken($_POST['token'] ?? null)) {
        $err = aGallery_Compat::lang('invalid_token', 'errors');
    } else {
        $action = $_POST['action'] ?? '';
        $name = aGallery_Utils::cleanText($_POST['name'] ?? '', 64, false);
        $desc = aGallery_Utils::cleanText($_POST['description'] ?? '', 255, false);
        if ($desc === '') $desc = null;
        $sort = (int)($_POST['sort_order'] ?? 0);

        $viewGroups = isset($_POST['view_groups']) && is_array($_POST['view_groups']) ? array_map('intval', $_POST['view_groups']) : [];
        $uploadGroups = isset($_POST['upload_groups']) && is_array($_POST['upload_groups']) ? array_map('intval', $_POST['upload_groups']) : [];

        try {
            if ($action === 'create') {
                if ($name === '') throw new RuntimeException(aGallery_Compat::lang('name_required', 'errors'));
                aGallery::createCategory($name, $desc, $sort, $viewGroups, $uploadGroups);
                $ok = aGallery_Compat::lang('category_created', 'panel');
            }
            if ($action === 'update') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new RuntimeException(aGallery_Compat::lang('category_invalid', 'errors'));
                if ($name === '') throw new RuntimeException(aGallery_Compat::lang('name_required', 'errors'));
                aGallery::updateCategory($id, $name, $desc, $sort, $viewGroups, $uploadGroups);
                $ok = aGallery_Compat::lang('category_updated', 'panel');
            }
            if ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new RuntimeException(aGallery_Compat::lang('category_invalid', 'errors'));
                if (!aGallery::deleteCategory($id)) {
                    throw new RuntimeException(aGallery_Compat::lang('category_delete_blocked', 'panel'));
                }
                $ok = aGallery_Compat::lang('category_deleted', 'panel');
            }
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
    }
}

// Load groups list (best effort)
$groups = [];
try {
    $g = DB::getInstance()->get('groups', ['id', '<>', 0]);
    $groups = $g ? $g->results() : [];
} catch (Throwable $e) {}

$cats = aGallery::getCategories();

echo '<div class="container">';
echo '<h2>' . aGallery_Utils::h(aGallery_Compat::lang('categories', 'panel')) . '</h2>';
if ($ok) echo '<div class="alert alert-success">' . aGallery_Utils::h($ok) . '</div>';
if ($err) echo '<div class="alert alert-danger">' . aGallery_Utils::h($err) . '</div>';

echo '<h3>' . aGallery_Utils::h(aGallery_Compat::lang('category_create', 'panel')) . '</h3>';
echo '<form method="post">';
echo aGallery_Compat::tokenField();
echo '<input type="hidden" name="action" value="create">';
echo '<div><label>' . aGallery_Utils::h(aGallery_Compat::lang('name', 'panel')) . '</label><input name="name" class="form-control"></div>';
echo '<div><label>' . aGallery_Utils::h(aGallery_Compat::lang('description', 'panel')) . '</label><input name="description" class="form-control"></div>';
echo '<div><label>' . aGallery_Utils::h(aGallery_Compat::lang('sort_order', 'panel')) . '</label><input name="sort_order" class="form-control" value="0"></div>';

echo '<div><label>' . aGallery_Utils::h(aGallery_Compat::lang('view_groups', 'panel')) . '</label><select name="view_groups[]" multiple class="form-control">';
foreach ($groups as $gr) echo '<option value="' . (int)$gr->id . '">' . aGallery_Utils::h($gr->name) . '</option>';
echo '</select><small>' . aGallery_Utils::h(aGallery_Compat::lang('view_groups_hint', 'panel')) . '</small></div>';

echo '<div><label>' . aGallery_Utils::h(aGallery_Compat::lang('upload_groups', 'panel')) . '</label><select name="upload_groups[]" multiple class="form-control">';
foreach ($groups as $gr) echo '<option value="' . (int)$gr->id . '">' . aGallery_Utils::h($gr->name) . '</option>';
echo '</select><small>' . aGallery_Utils::h(aGallery_Compat::lang('upload_groups_hint', 'panel')) . '</small></div>';

echo '<button class="btn btn-primary" type="submit">' . aGallery_Utils::h(aGallery_Compat::lang('create', 'panel')) . '</button>';
echo '</form>';

echo '<hr><h3>' . aGallery_Utils::h(aGallery_Compat::lang('existing_categories', 'panel')) . '</h3>';

foreach ($cats as $c) {
    $vg = aGallery_Utils::arrayFromJson($c->view_groups ?? null);
    $ug = aGallery_Utils::arrayFromJson($c->upload_groups ?? null);

    echo '<form method="post" style="border:1px solid #ddd;padding:12px;margin-bottom:12px">';
    echo aGallery_Compat::tokenField();
    echo '<input type="hidden" name="id" value="' . (int)$c->id . '">';
    echo '<div><strong>#' . (int)$c->id . '</strong></div>';
    echo '<div><label>' . aGallery_Utils::h(aGallery_Compat::lang('name', 'panel')) . '</label><input name="name" class="form-control" value="' . aGallery_Utils::h($c->name) . '"></div>';
    echo '<div><label>' . aGallery_Utils::h(aGallery_Compat::lang('description', 'panel')) . '</label><input name="description" class="form-control" value="' . aGallery_Utils::h((string)$c->description) . '"></div>';
    echo '<div><label>' . aGallery_Utils::h(aGallery_Compat::lang('sort_order', 'panel')) . '</label><input name="sort_order" class="form-control" value="' . (int)$c->sort_order . '"></div>';

    echo '<div><label>' . aGallery_Utils::h(aGallery_Compat::lang('view_groups', 'panel')) . '</label><select name="view_groups[]" multiple class="form-control">';
    foreach ($groups as $gr) {
        $sel = in_array((int)$gr->id, $vg, true) ? ' selected' : '';
        echo '<option value="' . (int)$gr->id . '"' . $sel . '>' . aGallery_Utils::h($gr->name) . '</option>';
    }
    echo '</select></div>';

    echo '<div><label>' . aGallery_Utils::h(aGallery_Compat::lang('upload_groups', 'panel')) . '</label><select name="upload_groups[]" multiple class="form-control">';
    foreach ($groups as $gr) {
        $sel = in_array((int)$gr->id, $ug, true) ? ' selected' : '';
        echo '<option value="' . (int)$gr->id . '"' . $sel . '>' . aGallery_Utils::h($gr->name) . '</option>';
    }
    echo '</select></div>';

    echo '<button class="btn btn-success" type="submit" name="action" value="update">' . aGallery_Utils::h(aGallery_Compat::lang('save', 'panel')) . '</button> ';
    echo '<button class="btn btn-danger" type="submit" name="action" value="delete" onclick="return confirm(\'' . aGallery_Utils::h(aGallery_Compat::lang('confirm_delete', 'panel')) . '\')">' . aGallery_Utils::h(aGallery_Compat::lang('delete', 'panel')) . '</button>';
    echo '</form>';
}

echo '</div>';
