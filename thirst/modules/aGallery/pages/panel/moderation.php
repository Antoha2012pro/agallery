<?php
require_once ROOT_PATH . '/modules/aGallery/includes/autoload.php';

aGallery_Compat::requireBackendInit();
global $user;

if (!$user->isLoggedIn()) aGallery_Compat::redirect(URL::build('/login'));
if (method_exists($user, 'handlePanelPageLoad')) $user->handlePanelPageLoad('agallery.moderate');
if (!$user->hasPermission('agallery.moderate')) aGallery_Compat::redirect(URL::build('/panel'));

$status = $_GET['status'] ?? 'pending';
if (!in_array($status, ['pending','approved','declined'], true)) $status = 'pending';

$err = '';
$ok = '';

$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;

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
            if ($action === 'approve') {
                aGallery::updateImage($id, [
                    'status' => 'approved',
                    'decline_reason' => null,
                    'moderated_by' => (int)$user->data()->id,
                    'moderated_at' => aGallery_Utils::now(),
                ]);
                aGallery::addAudit('approve', $id, (int)$user->data()->id);
                aGallery::notifyAuthorApproved($id, (int)$img->user_id, (int)$user->data()->id);
                $ok = aGallery_Compat::lang('approved', 'panel');
            } elseif ($action === 'decline') {
                $reason = aGallery_Utils::cleanText($_POST['reason'] ?? '', 255, false);
                if ($reason === '') {
                    $err = aGallery_Compat::lang('decline_reason_required', 'errors');
                } else {
                    aGallery::updateImage($id, [
                        'status' => 'declined',
                        'decline_reason' => $reason,
                        'moderated_by' => (int)$user->data()->id,
                        'moderated_at' => aGallery_Utils::now(),
                    ]);
                    aGallery::addAudit('decline', $id, (int)$user->data()->id, ['reason' => $reason]);
                    aGallery::notifyAuthorDeclined($id, (int)$img->user_id, (int)$user->data()->id, $reason);
                    $ok = aGallery_Compat::lang('declined', 'panel');
                }
            }
        }
    }
}

// Load list
$db = DB::getInstance();
$list = [];
try {
    $q = $db->query("SELECT i.*, c.name category_name
                     FROM `" . $db->getPrefix() . "agallery_images` i
                     JOIN `" . $db->getPrefix() . "agallery_categories` c ON c.id=i.category_id
                     WHERE i.status='" . $db->escape($status) . "'
                     ORDER BY i.created_at DESC
                     LIMIT 100");
    $list = $q && !$q->error() ? $q->results() : [];
} catch (Throwable $e) {}

// Quick view
$view = null;
if ($viewId > 0) $view = aGallery::getImage($viewId);

echo '<div class="container">';
echo '<h2>' . aGallery_Utils::h(aGallery_Compat::lang('moderation', 'panel')) . '</h2>';
echo '<p>'
   . '<a href="' . aGallery_Utils::h(URL::build('/panel/agallery/moderation', 'status=pending')) . '">pending</a> | '
   . '<a href="' . aGallery_Utils::h(URL::build('/panel/agallery/moderation', 'status=approved')) . '">approved</a> | '
   . '<a href="' . aGallery_Utils::h(URL::build('/panel/agallery/moderation', 'status=declined')) . '">declined</a>'
   . '</p>';

if ($ok) echo '<div class="alert alert-success">' . aGallery_Utils::h($ok) . '</div>';
if ($err) echo '<div class="alert alert-danger">' . aGallery_Utils::h($err) . '</div>';

if ($view) {
    echo '<div style="border:1px solid #ddd;padding:12px;margin-bottom:12px">';
    echo '<h3>#' . (int)$view->id . ' — ' . aGallery_Utils::h($view->title) . '</h3>';
    echo '<p>' . aGallery_Utils::h((string)$view->description) . '</p>';
    echo '<p><img src="' . aGallery_Utils::h(URL::build('/' . ltrim($view->thumb_path, '/'))) . '" style="max-width:320px"></p>';
    echo '<form method="post" style="display:flex;gap:8px;align-items:flex-end">';
    echo aGallery_Compat::tokenField();
    echo '<input type="hidden" name="id" value="' . (int)$view->id . '">';
    echo '<button class="btn btn-success" name="action" value="approve" type="submit">' . aGallery_Utils::h(aGallery_Compat::lang('approve', 'panel')) . '</button>';
    echo '<div style="flex:1">';
    echo '<label>' . aGallery_Utils::h(aGallery_Compat::lang('decline_reason', 'panel')) . '</label>';
    echo '<input name="reason" class="form-control" placeholder="' . aGallery_Utils::h(aGallery_Compat::lang('decline_reason_placeholder', 'panel')) . '">';
    echo '</div>';
    echo '<button class="btn btn-danger" name="action" value="decline" type="submit">' . aGallery_Utils::h(aGallery_Compat::lang('decline', 'panel')) . '</button>';
    echo '</form>';
    echo '</div>';
}

echo '<table class="table table-striped" style="width:100%">';
echo '<thead><tr><th>ID</th><th>Thumb</th><th>Title</th><th>Category</th><th>Status</th><th>Created</th><th></th></tr></thead><tbody>';
foreach ($list as $i) {
    echo '<tr>';
    echo '<td>#' . (int)$i->id . '</td>';
    echo '<td><img src="' . aGallery_Utils::h(URL::build('/' . ltrim($i->thumb_path, '/'))) . '" style="max-width:80px"></td>';
    echo '<td>' . aGallery_Utils::h($i->title) . '</td>';
    echo '<td>' . aGallery_Utils::h($i->category_name) . '</td>';
    echo '<td>' . aGallery_Utils::h($i->status) . '</td>';
    echo '<td>' . aGallery_Utils::h($i->created_at) . '</td>';
    echo '<td><a class="btn btn-sm btn-primary" href="' . aGallery_Utils::h(URL::build('/panel/agallery/moderation', 'status=' . $status . '&view=' . (int)$i->id)) . '">' . aGallery_Utils::h(aGallery_Compat::lang('view', 'panel')) . '</a></td>';
    echo '</tr>';
}
echo '</tbody></table>';
echo '</div>';
