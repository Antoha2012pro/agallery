<?php
require_once ROOT_PATH . '/modules/aGallery/includes/autoload.php';

aGallery_Compat::requireBackendInit();
global $user;

if (!$user->isLoggedIn()) aGallery_Compat::redirect(URL::build('/login'));
if (method_exists($user, 'handlePanelPageLoad')) $user->handlePanelPageLoad('agallery.manage');
if (!$user->hasPermission('agallery.manage')) aGallery_Compat::redirect(URL::build('/panel'));

$err = '';
$ok = '';

$db = DB::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!aGallery_Compat::checkToken($_POST['token'] ?? null)) {
        $err = aGallery_Compat::lang('invalid_token', 'errors');
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        $img = aGallery::getImage($id);
        if (!$img) {
            $err = aGallery_Compat::lang('image_not_found', 'errors');
        } else {
            if ($action === 'update') {
                $title = aGallery_Utils::cleanText($_POST['title'] ?? '', 80, false);
                $desc  = aGallery_Utils::cleanText($_POST['description'] ?? '', 600, true);
                if ($desc === '') $desc = null;
                $catId = (int)($_POST['category_id'] ?? 0);
                if ($title === '') $err = aGallery_Compat::lang('title_required', 'errors');
                else if ($catId <= 0 || !aGallery::getCategory($catId)) $err = aGallery_Compat::lang('category_invalid', 'errors');
                else {
                    aGallery::updateImage($id, [
                        'title' => $title,
                        'description' => $desc,
                        'category_id' => $catId,
                    ]);
                    aGallery::addAudit('edit', $id, (int)$user->data()->id);
                    $ok = aGallery_Compat::lang('saved', 'panel');
                }
            } elseif ($action === 'delete') {
                $okDel = aGallery::deleteImage($id);
                $ok = $okDel ? aGallery_Compat::lang('deleted', 'panel') : aGallery_Compat::lang('deleted_with_warnings', 'panel');
            }
        }
    }
}

$cats = aGallery::getCategories();

$list = [];
try {
    $q = $db->query("SELECT i.*, c.name category_name
                     FROM `" . $db->getPrefix() . "agallery_images` i
                     JOIN `" . $db->getPrefix() . "agallery_categories` c ON c.id=i.category_id
                     WHERE i.status='approved'
                     ORDER BY i.created_at DESC
                     LIMIT 200");
    $list = $q && !$q->error() ? $q->results() : [];
} catch (Throwable $e) {}

echo '<div class="container">';
echo '<h2>' . aGallery_Utils::h(aGallery_Compat::lang('images', 'panel')) . '</h2>';
if ($ok) echo '<div class="alert alert-success">' . aGallery_Utils::h($ok) . '</div>';
if ($err) echo '<div class="alert alert-danger">' . aGallery_Utils::h($err) . '</div>';

foreach ($list as $i) {
    echo '<form method="post" style="border:1px solid #ddd;padding:12px;margin-bottom:12px">';
    echo aGallery_Compat::tokenField();
    echo '<input type="hidden" name="id" value="' . (int)$i->id . '">';
    echo '<div style="display:flex;gap:12px;align-items:flex-start">';
    echo '<img src="' . aGallery_Utils::h(URL::build('/' . ltrim($i->thumb_path, '/'))) . '" style="max-width:120px">';
    echo '<div style="flex:1">';
    echo '<div><strong>#' . (int)$i->id . '</strong></div>';
    echo '<div><label>' . aGallery_Utils::h(aGallery_Compat::lang('title', 'panel')) . '</label><input name="title" class="form-control" value="' . aGallery_Utils::h($i->title) . '"></div>';
    echo '<div><label>' . aGallery_Utils::h(aGallery_Compat::lang('description', 'panel')) . '</label><textarea name="description" class="form-control" rows="3">' . aGallery_Utils::h((string)$i->description) . '</textarea></div>';
    echo '<div><label>' . aGallery_Utils::h(aGallery_Compat::lang('category', 'panel')) . '</label><select name="category_id" class="form-control">';
    foreach ($cats as $c) {
        $sel = ((int)$c->id === (int)$i->category_id) ? ' selected' : '';
        echo '<option value="' . (int)$c->id . '"' . $sel . '>' . aGallery_Utils::h($c->name) . '</option>';
    }
    echo '</select></div>';
    echo '<div style="margin-top:8px">';
    echo '<button class="btn btn-success" name="action" value="update" type="submit">' . aGallery_Utils::h(aGallery_Compat::lang('save', 'panel')) . '</button> ';
    echo '<button class="btn btn-danger" name="action" value="delete" type="submit" onclick="return confirm(\'' . aGallery_Utils::h(aGallery_Compat::lang('confirm_delete', 'panel')) . '\')">' . aGallery_Utils::h(aGallery_Compat::lang('delete', 'panel')) . '</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</form>';
}

echo '</div>';
