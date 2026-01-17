<?php
if (!defined('ROOT_PATH')) {
    die('ROOT_PATH not defined');
}

require_once(ROOT_PATH . '/modules/aGallery/includes/autoload.php');

define('PAGE', 'panel');
define('PARENT_PAGE', 'agallery');
define('PANEL_PAGE', 'agallery_settings');

$db = DB::getInstance();
$language = aGallery_Compat::getLanguageFromGlobals();

aGallery_Compat::handlePanelPageLoadOrDeny($user, 'agallery.manage');

$settings = new aGallery_Settings($db);

$errors = [];
$success = null;

if (Input::exists()) {
    $token = Input::get('token');
    if (!aGallery_Compat::tokenCheck($token)) {
        $errors[] = aGallery_Compat::t($language, 'err_csrf', [], 'CSRF токен неверный.');
    } else {
        $maxMb = max(1, (int)Input::get('max_upload_mb'));
        $maxW  = max(1, (int)Input::get('max_width'));
        $maxH  = max(1, (int)Input::get('max_height'));
        $exts  = aGallery_Util::cleanText(Input::get('allowed_extensions'));
        $qJ    = min(100, max(1, (int)Input::get('image_quality_jpeg')));
        $qW    = min(100, max(1, (int)Input::get('image_quality_webp')));
        $thumbW= max(64, (int)Input::get('thumb_width'));
        $conv  = isset($_POST['allow_convert']) ? '1' : '0';

        $settings->set('max_upload_mb', (string)$maxMb);
        $settings->set('max_width', (string)$maxW);
        $settings->set('max_height', (string)$maxH);
        $settings->set('allowed_extensions', $exts);
        $settings->set('image_quality_jpeg', (string)$qJ);
        $settings->set('image_quality_webp', (string)$qW);
        $settings->set('thumb_width', (string)$thumbW);
        $settings->set('allow_convert', $conv);

        aGallery_Audit::add($db, 'settings_update', null, aGallery_Compat::userId($user));
        $success = aGallery_Compat::t($language, 'saved', [], 'Сохранено.');
    }
}

$base = aGallery_Storage::baseDirFs();
$ym = date('Y') . '/' . date('m');
$monthDir = $base . '/' . $ym;
$thumbDir = $monthDir . '/thumbs';

$health = [
    'base' => ['path' => $base, 'exists' => is_dir($base), 'writable' => is_writable($base)],
    'month' => ['path' => $monthDir, 'exists' => is_dir($monthDir), 'writable' => is_dir($monthDir) ? is_writable($monthDir) : false],
    'thumbs' => ['path' => $thumbDir, 'exists' => is_dir($thumbDir), 'writable' => is_dir($thumbDir) ? is_writable($thumbDir) : false],
];

$php = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
];

$maxMb = $settings->getInt('max_upload_mb', 50);
$warn = [];
if (aGallery_Util::iniBytes((string)$php['upload_max_filesize']) < $maxMb * 1024 * 1024) $warn[] = 'upload_max_filesize';
if (aGallery_Util::iniBytes((string)$php['post_max_size']) < $maxMb * 1024 * 1024) $warn[] = 'post_max_size';

$smarty->assign([
    'AGALLERY_TITLE' => aGallery_Compat::t($language, 'staff_settings', [], 'Settings'),
    'AGALLERY_TOKEN' => aGallery_Compat::tokenGet(),
    'AGALLERY_ERRORS' => $errors,
    'AGALLERY_SUCCESS' => $success,
    'AGALLERY_SETTINGS' => [
        'max_upload_mb' => $settings->get('max_upload_mb','50'),
        'max_width' => $settings->get('max_width','1920'),
        'max_height' => $settings->get('max_height','1080'),
        'allowed_extensions' => $settings->get('allowed_extensions','png,jpg,jpeg,webp'),
        'image_quality_jpeg' => $settings->get('image_quality_jpeg','82'),
        'image_quality_webp' => $settings->get('image_quality_webp','80'),
        'thumb_width' => $settings->get('thumb_width','480'),
        'allow_convert' => $settings->get('allow_convert','0'),
    ],
    'AGALLERY_HEALTH' => $health,
    'AGALLERY_PHP_LIMITS' => $php,
    'AGALLERY_PHP_WARN' => $warn,
]);

$smarty->display('aGallery/settings.tpl');
