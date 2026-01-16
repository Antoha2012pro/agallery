<?php
if (!defined('ROOT_PATH')) die('Direct access not permitted');

if (!$user->isLoggedIn()) { Redirect::to(URL::build('/login')); die(); }
if (!($user->hasPermission('agallery.moderate') || $user->hasPermission('agallery.manage'))) { require_once(ROOT_PATH . '/403.php'); die(); }

$repo = new aGalleryRepository();

$errors = [];
$success = null;

$status = $_GET['status'] ?? 'pending';
if (!in_array($status, ['pending','approved','declined'], true)) $status = 'pending';

$focus_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Token::check($_POST['token'] ?? '')) $errors[] = $language->get('agallery', 'csrf_invalid');

    $image_id = isset($_POST['image_id']) ? (int)$_POST['image_id'] : 0;
    $img = $image_id ? DB::getInstance()->query("SELECT * FROM `{$repo->t('agallery_images')}` WHERE id=?", [$image_id])->first() : null;
    if (!$img) $errors[] = $language->get('agallery', 'image_not_found');

    if (!count($errors)) {
        $moderator_id = (int)$user->data()->id;
        $now = $repo->now();

        if (isset($_POST['approve'])) {
            DB::getInstance()->query(
                "UPDATE `{$repo->t('agallery_images')}`
                 SET status='approved', decline_reason=NULL, moderated_by=?, moderated_at=?, updated_at=?
                 WHERE id=?",
                [$moderator_id, $now, $now, $image_id]
            );
            $repo->log($moderator_id, $image_id, 'approve', []);

            // Notify author
            $title = $language->get('agallery', 'alert_user_approved_title');
            $body = str_replace('{ID}', (string)$image_id, $language->get('agallery', 'alert_user_approved_body'));
            Alert::send((int)$img->user_id, $title, $body, URL::build('/gallery'));

            $success = $language->get('agallery', 'approved');
        }

        if (isset($_POST['decline'])) {
            $reason = trim($_POST['decline_reason'] ?? '');
            if ($reason === '') $errors[] = $language->get('agallery', 'decline_reason_required');

            if (!count($errors)) {
                DB::getInstance()->query(
                    "UPDATE `{$repo->t('agallery_images')}`
                     SET status='declined', decline_reason=?, moderated_by=?, moderated_at=?, updated_at=?
                     WHERE id=?",
                    [$reason, $moderator_id, $now, $now, $image_id]
                );
                $repo->log($moderator_id, $image_id, 'decline', ['reason' => $reason]);

                $title = $language->get('agallery', 'alert_user_declined_title');
                $body = str_replace(
                    ['{ID}','{REASON}'],
                    [(string)$image_id, $reason],
                    $language->get('agallery', 'alert_user_declined_body')
                );
                Alert::send((int)$img->user_id, $title, $body, URL::build('/gallery'));

                $success = $language->get('agallery', 'declined');
            }
        }
    }
}

$per_page = 30;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $per_page;

$total_row = DB::getInstance()->query(
    "SELECT COUNT(*) AS c FROM `{$repo->t('agallery_images')}` WHERE status=?",
    [$status]
)->first();
$total = $total_row ? (int)$total_row->c : 0;
$total_pages = (int) ceil($total / $per_page);

$list = DB::getInstance()->query(
    "SELECT i.*, u.username, c.name AS category_name
     FROM `{$repo->t('agallery_images')}` i
     LEFT JOIN `{$repo->t('users')}` u ON u.id = i.user_id
     LEFT JOIN `{$repo->t('agallery_categories')}` c ON c.id = i.category_id
     WHERE i.status=?
     ORDER BY i.created_at DESC
     LIMIT ? OFFSET ?",
    [$status, $per_page, $offset]
)->results() ?: [];

$focus = null;
if ($focus_id > 0) {
    $focus = DB::getInstance()->query(
        "SELECT i.*, u.username, c.name AS category_name
         FROM `{$repo->t('agallery_images')}` i
         LEFT JOIN `{$repo->t('users')}` u ON u.id = i.user_id
         LEFT JOIN `{$repo->t('agallery_categories')}` c ON c.id = i.category_id
         WHERE i.id=?",
        [$focus_id]
    )->first();
}

$page_title = $language->get('agallery', 'staffcp_moderation');

require_once(ROOT_PATH . '/core/templates/panel_header.php');
require_once(ROOT_PATH . '/core/templates/panel_navbar.php');

$smarty->assign([
    'TITLE' => $page_title,
    'TABS_TEMPLATE' => 'aGallery/header_tabs.tpl',
    'ACTIVE_TAB' => 'moderation',

    'TOKEN' => Token::get(),
    'ERRORS' => $errors,
    'SUCCESS' => $success,

    'STATUS' => $status,
    'LIST' => $list,
    'FOCUS' => $focus,

    'PAGE' => $page,
    'TOTAL_PAGES' => $total_pages,

    'URL_BASE' => URL::build('/panel/agallery/moderation'),
]);

$template->displayTemplate('aGallery/moderation.tpl', $smarty);

require_once(ROOT_PATH . '/core/templates/panel_footer.php');