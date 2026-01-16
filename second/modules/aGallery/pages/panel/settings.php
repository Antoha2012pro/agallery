<?php
if (!defined('ROOT_PATH')) die('Direct access not permitted');

if (!$user->isLoggedIn()) { Redirect::to(URL::build('/login')); die(); }
if (!$user->hasPermission('agallery.manage')) { require_once(ROOT_PATH . '/403.php'); die(); }

$repo = new aGalleryRepository();
$settings = new aGallerySettings($repo);
$settings->ensureDefaults();

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Token::check($_POST['token'] ?? '')) $errors[] = $language->get('agallery', 'csrf_invalid');

    if (!count($errors)) {
        $max_upload_mb = max(1, (int)($_POST['max_upload_mb'] ?? 50));
        $max_w = max(1, (int)($_POST['max_width'] ?? 1920));
        $max_h = max(1, (int)($_POST['max_height'] ?? 1080));

        $allowed_extensions = trim($_POST['allowed_extensions'] ?? 'png,jpg,jpeg,webp,gif');
        $allowed_extensions = array_values(array_filter(array_map('trim', explode(',', strtolower($allowed_extensions)))));

        $q_jpg = (int)($_POST['image_quality_jpeg'] ?? 82);
        $q_webp = (int)($_POST['image_quality_webp'] ?? 80);
        $thumb_w = max(64, (int)($_POST['thumb_width'] ?? 480));

        $allow_conversion = isset($_POST['allow_conversion']) ? 1 : 0;
        $convert_to = ($_POST['convert_to'] ?? 'jpg') === 'webp' ? 'webp' : 'jpg';

        $settings->set('max_upload_mb', $max_upload_mb);
        $settings->set('max_width', $max_w);
        $settings->set('max_height', $max_h);
        $settings->set('allowed_extensions', $allowed_extensions);
        $settings->set('image_quality_jpeg', max(1, min(100, $q_jpg)));
        $settings->set('image_quality_webp', max(1, min(100, $q_webp)));
        $settings->set('thumb_width', $thumb_w);
        $settings->set('allow_conversion', $allow_conversion);
        $settings->set('convert_to', $convert_to);

        $repo->log((int)$user->data()->id, null, 'settings_update', []);
        $success = $language->get('agallery', 'saved');
    }
}

$data = [
    'max_upload_mb' => $settings->get('max_upload_mb', 50),
    'max_width' => $settings->get('max_width', 1920),
    'max_height' => $settings->get('max_height', 1080),
    'allowed_extensions' => $settings->get('allowed_extensions', ['png','jpg','jpeg','webp','gif']),
    'image_quality_jpeg' => $settings->get('image_quality_jpeg', 82),
    'image_quality_webp' => $settings->get('image_quality_webp', 80),
    'thumb_width' => $settings->get('thumb_width', 480),
    'allow_conversion' => (int)$settings->get('allow_conversion', 0),
    'convert_to' => (string)$settings->get('convert_to', 'jpg'),
];

$page_title = $language->get('agallery', 'staffcp_settings');

require_once(ROOT_PATH . '/core/templates/panel_header.php');
require_once(ROOT_PATH . '/core/templates/panel_navbar.php');

$smarty->assign([
    'TITLE' => $page_title,
    'TABS_TEMPLATE' => 'aGallery/header_tabs.tpl',
    'ACTIVE_TAB' => 'settings',

    'TOKEN' => Token::get(),
    'ERRORS' => $errors,
    'SUCCESS' => $success,
    'DATA' => $data,
]);

$template->displayTemplate('aGallery/settings.tpl', $smarty);

require_once(ROOT_PATH . '/core/templates/panel_footer.php');