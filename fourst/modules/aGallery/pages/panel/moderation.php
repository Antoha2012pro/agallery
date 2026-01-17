<?php
if (!defined('ROOT_PATH')) {
    die('ROOT_PATH not defined');
}

require_once(ROOT_PATH . '/modules/aGallery/includes/autoload.php');

define('PAGE', 'panel');
define('PARENT_PAGE', 'agallery');
define('PANEL_PAGE', 'agallery_moderation');

$db = DB::getInstance();
$language = aGallery_Compat::getLanguageFromGlobals();

aGallery_Compat::handlePanelPageLoadOrDeny($user, 'agallery.moderate');

$settings = new aGallery_Settings($db);

$errors = [];
$success = null;

$status = isset($_GET['status']) ? (string)$_GET['status'] : 'pending';
if (!in_array($status, ['pending','approved','declined'], true)) $status = 'pending';

$focusId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (Input::exists()) {
    $token = Input::get('token');
    if (!aGallery_Compat::tokenCheck($token)) {
        $errors[] = aGallery_Compat::t($language, 'err_csrf', [], 'CSRF токен неверный.');
    } else {
        $action = (string)Input::get('action');
        $id = (int)Input::get('id');
        $moderatorId = aGallery_Compat::userId($user);

        $img = aGallery_Images::get($db, $id);
        if (!$img) {
            $errors[] = aGallery_Compat::t($language, 'err_not_found', [], 'Не найдено.');
        } else {
            if ($action === 'approve') {
                aGallery_Images::approve($db, $id, $moderatorId);
                aGallery_Audit::add($db, 'approve', $id, $moderatorId);

                // notify author
                $authorId = (int)$img->user_id;
                $title = aGallery_Compat::t($language, 'notif_approved_title', [], 'Заявка принята');
                $body = aGallery_Compat::t($language, 'notif_approved_body', [], 'Ваше изображение опубликовано.');
                $url = aGallery_Compat::url('/gallery/view', 'id=' . $id);
                aGallery_Compat::notifyMany($db, $moderatorId, [$authorId], $title, $body, $url);

                $success = aGallery_Compat::t($language, 'approved', [], 'Approved');
            }

            if ($action === 'decline') {
                $reason = aGallery_Util::cleanText($_POST['reason'] ?? '');
                if ($reason === '' || mb_strlen($reason) > 255) {
                    $errors[] = aGallery_Compat::t($language, 'err_decline_reason_required', [], 'Причина обязательна.');
                } else {
                    aGallery_Images::decline($db, $id, $moderatorId, $reason);
                    aGallery_Audit::add($db, 'decline', $id, $moderatorId, ['reason' => $reason]);

                    // notify author
                    $authorId = (int)$img->user_id;
                    $title = aGallery_Compat::t($language, 'notif_declined_title', [], 'Заявка отклонена');
                    $body = aGallery_Compat::t($language, 'notif_declined_body', ['reason' => $reason], 'Причина: ' . $reason);
                    $url = aGallery_Compat::url('/gallery');
                    aGallery_Compat::notifyMany($db, $moderatorId, [$authorId], $title, $body, $url);

                    $success = aGallery_Compat::t($language, 'declined', [], 'Declined');
                }
            }
        }
    }
}

$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$limit = 25;
$offset = ($page - 1) * $limit;

$total = aGallery_Images::countByStatus($db, $status);
$pages = max(1, (int)ceil($total / $limit));

$list = aGallery_Images::listByStatus($db, $status, $limit, $offset);

$smarty->assign([
    'AGALLERY_TITLE' => aGallery_Compat::t($language, 'staff_moderation', [], 'Moderation'),
    'AGALLERY_TOKEN' => aGallery_Compat::tokenGet(),
    'AGALLERY_ERRORS' => $errors,
    'AGALLERY_SUCCESS' => $success,
    'AGALLERY_STATUS' => $status,
    'AGALLERY_LIST' => $list,
    'AGALLERY_PAGE' => $page,
    'AGALLERY_PAGES' => $pages,
    'AGALLERY_FOCUS_ID' => $focusId,
]);

$smarty->display('aGallery/moderation.tpl');
