<?php
if (!defined('ROOT_PATH')) {
    die('Direct access not permitted');
}

const PAGE = 'agallery_moderation';

require_once ROOT_PATH . '/core/templates/backend_init.php';
require_once ROOT_PATH . '/modules/aGallery/classes/aGallery.php';

$language = new Language();

if (!$user->isLoggedIn() || !$user->hasPermission('agallery.moderate')) {
    Redirect::to(URL::build('/panel'));
}

$status = (string)($_GET['status'] ?? 'pending');
$allowed = ['pending','approved','declined'];
if (!in_array($status, $allowed, true)) $status = 'pending';

$focusId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (Input::exists()) {
    if (!Token::check(Input::get('token'))) {
        Session::flash('agallery_error', $language->get('agallery', 'err_csrf'));
        Redirect::to(URL::build('/panel/agallery/moderation', 'status=' . $status));
    }

    $action = (string)Input::get('action');
    $imageId = (int)Input::get('image_id');

    $img = aGallery::getImage($imageId);
    if (!$img) {
        Session::flash('agallery_error', $language->get('agallery', 'err_not_found'));
        Redirect::to(URL::build('/panel/agallery/moderation', 'status=' . $status));
    }

    if ($action === 'approve') {
        aGallery::approve($imageId, (int)$user->data()->id);

        // Notify author
        $authorUrl = URL::build('/gallery');
        Alert::send(
            (int)$img->user_id,
            $language->get('agallery', 'alert_approved_title'),
            str_replace('{id}', (string)$imageId, $language->get('agallery', 'alert_approved_content')),
            $authorUrl
        );

        Session::flash('agallery_success', $language->get('agallery', 'moderation_saved'));
        Redirect::to(URL::build('/panel/agallery/moderation', 'status=' . $status));
    }

    if ($action === 'decline') {
        $reason = trim((string)Input::get('reason'));
        if ($reason === '') {
            Session::flash('agallery_error', $language->get('agallery', 'err_reason_required'));
            Redirect::to(URL::build('/panel/agallery/moderation', 'status=' . $status . '&id=' . $imageId));
        }

        aGallery::decline($imageId, (int)$user->data()->id, $reason);

        // Notify author with reason
        $authorUrl = URL::build('/gallery');
        $content = str_replace(
            ['{id}', '{reason}'],
            [(string)$imageId, htmlspecialchars($reason, ENT_QUOTES, 'UTF-8')],
            $language->get('agallery', 'alert_declined_content')
        );

        Alert::send(
            (int)$img->user_id,
            $language->get('agallery', 'alert_declined_title'),
            $content,
            $authorUrl,
            true // skipPurify because we already escaped
        );

        Session::flash('agallery_success', $language->get('agallery', 'moderation_saved'));
        Redirect::to(URL::build('/panel/agallery/moderation', 'status=' . $status));
    }
}

$perPage = 15;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $perPage;
$total = aGallery::countModeration($status);
$items = aGallery::listModeration($status, $perPage, $offset);
$totalPages = (int)max(1, ceil($total / $perPage));

$focus = null;
if ($focusId) {
    $focus = aGallery::getImage($focusId);
}

$smarty->assign([
    'TOKEN' => Token::get(),
    'TITLE' => 'aGallery! - ' . $language->get('agallery', 'staffcp_moderation'),
    'STATUS' => $status,
    'ITEMS' => $items,
    'FOCUS' => $focus,
    'PAGE_NO' => $page,
    'TOTAL_PAGES' => $totalPages,
    'SUCCESS' => Session::exists('agallery_success') ? Session::flash('agallery_success') : null,
    'ERROR' => Session::exists('agallery_error') ? Session::flash('agallery_error') : null,
    'L' => [
        'moderation' => $language->get('agallery', 'staffcp_moderation'),
        'pending' => $language->get('agallery', 'status_pending'),
        'approved' => $language->get('agallery', 'status_approved'),
        'declined' => $language->get('agallery', 'status_declined'),
        'approve' => $language->get('agallery', 'approve'),
        'decline' => $language->get('agallery', 'decline'),
        'reason' => $language->get('agallery', 'decline_reason'),
        'confirm' => $language->get('agallery', 'confirm'),
    ],
]);

$template->displayTemplate('agallery/moderation.tpl');
