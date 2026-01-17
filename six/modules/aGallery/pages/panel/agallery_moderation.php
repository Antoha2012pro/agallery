<?php

if (!defined('ROOT_PATH')) {
    die();
}

if (!$user->isLoggedIn() || !$user->hasPermission('agallery.moderate')) {
    Redirect::to(URL::build('/login'));
}

$status = isset($_GET['status']) ? $_GET['status'] : 'pending';
if (!in_array($status, ['pending', 'approved', 'declined'], true)) {
    $status = 'pending';
}

// Actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AGalleryCompat::checkToken('agallery_moderation', $language->get('general', 'invalid_token'));

    $image_id = isset($_POST['image_id']) ? (int)$_POST['image_id'] : 0;
    $db = DB::getInstance();
    $image = $db->query('SELECT * FROM agallery_images WHERE id = ?', [$image_id])->first();
    if ($image) {
        if (isset($_POST['approve'])) {
            $db->update('agallery_images', $image_id, [
                'status' => 'approved',
                'moderated_by' => (int)$user->data()->id,
                'moderated_at' => time(),
            ]);

            $db->insert('agallery_audit_log', [
                'action' => 'approve',
                'image_id' => $image_id,
                'actor_id' => (int)$user->data()->id,
                'details' => '',
                'created_at' => time(),
            ]);

            // Alert author.
            AGalleryCompat::sendAlert(
                (int)$image->user_id,
                $language->get('agallery', 'alert_approved_title'),
                $language->get('agallery', 'alert_approved_content')
            );

        } elseif (isset($_POST['decline']) && isset($_POST['decline_reason'])) {
            $reason = trim($_POST['decline_reason']);
            if ($reason === '') {
                Session::flash('agallery_moderation_error', $language->get('agallery', 'decline_reason_required'));
            } else {
                $db->update('agallery_images', $image_id, [
                    'status' => 'declined',
                    'decline_reason' => $reason,
                    'moderated_by' => (int)$user->data()->id,
                    'moderated_at' => time(),
                ]);

                $db->insert('agallery_audit_log', [
                    'action' => 'decline',
                    'image_id' => $image_id,
                    'actor_id' => (int)$user->data()->id,
                    'details' => $reason,
                    'created_at' => time(),
                ]);

                AGalleryCompat::sendAlert(
                    (int)$image->user_id,
                    $language->get('agallery', 'alert_declined_title'),
                    $language->get('agallery', 'alert_declined_content') . ' ' . $reason
                );
            }
        }
    }

    Redirect::to(URL::build('/panel/agallery/moderation', 'status=' . urlencode($status)));
}

$db = DB::getInstance();
$images = $db->query(
    'SELECT i.*, u.username, c.name AS category_name
     FROM agallery_images i
     JOIN nl2_users u ON u.id = i.user_id
     LEFT JOIN agallery_categories c ON c.id = i.category_id
     WHERE i.status = ?
     ORDER BY i.created_at DESC',
    [$status]
)->results();

$smarty->assign([
    'PANEL_TITLE' => $language->get('agallery', 'panel_moderation_title'),
    'AGALLERY_STATUS' => $status,
    'AGALLERY_IMAGES' => $images,
    'AGALLERY_TOKEN_INPUT' => AGalleryCompat::generateTokenInput('agallery_moderation'),
]);

$template->onPageLoad();
require_once(ROOT_PATH . '/core/templates/panel_init.php');

$smarty->display('aGallery/panel_moderation.tpl');
