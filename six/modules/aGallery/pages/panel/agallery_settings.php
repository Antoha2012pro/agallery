<?php

if (!defined('ROOT_PATH')) {
    die();
}

if (!$user->isLoggedIn() || !$user->hasPermission('agallery.manage')) {
    Redirect::to(URL::build('/login'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    AGalleryCompat::checkToken('agallery_settings', $language->get('general', 'invalid_token'));

    AGallerySettings::set('max_upload_mb', (int)$_POST['max_upload_mb']);
    AGallerySettings::set('max_width', (int)$_POST['max_width']);
    AGallerySettings::set('max_height', (int)$_POST['max_height']);
    AGallerySettings::set('allowed_extensions', $_POST['allowed_extensions']);
    AGallerySettings::set('image_quality_jpeg', (int)$_POST['image_quality_jpeg']);
    AGallerySettings::set('image_quality_webp', (int)$_POST['image_quality_webp']);
    AGallerySettings::set('thumb_width', (int)$_POST['thumb_width']);
    AGallerySettings::set('allow_convert', isset($_POST['allow_convert']) ? 1 : 0);

    Session::flash('agallery_settings', $language->get('agallery', 'settings_saved'));
    Redirect::to(URL::build('/panel/agallery/settings'));
}

$settings = AGallerySettings::all();
$pathsInfo = AGalleryHealthCheck::getPathsInfo();
$limits = AGalleryHealthCheck::getPhpLimits();
$compare = AGalleryHealthCheck::compareWithModuleLimit();

$smarty->assign([
    'PANEL_TITLE' => $language->get('agallery', 'panel_settings_title'),
    'AGALLERY_SETTINGS' => $settings,
    'AGALLERY_PATHS' => $pathsInfo,
    'AGALLERY_LIMITS' => $limits,
    'AGALLERY_LIMIT_COMPARE' => $compare,
    'AGALLERY_TOKEN_INPUT' => AGalleryCompat::generateTokenInput('agallery_settings'),
]);

$template->onPageLoad();
require_once(ROOT_PATH . '/core/templates/panel_init.php');

$smarty->display('aGallery/panel_settings.tpl');
