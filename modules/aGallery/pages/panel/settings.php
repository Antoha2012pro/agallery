<?php
if (!defined('ROOT_PATH')) {
    die('Direct access not permitted');
}

const PAGE = 'agallery_settings';

require_once ROOT_PATH . '/core/templates/backend_init.php';
require_once ROOT_PATH . '/modules/aGallery/classes/aGallery.php';

$language = new Language();

if (!$user->isLoggedIn() || !$user->hasPermission('agallery.manage')) {
    Redirect::to(URL::build('/panel'));
}

if (Input::exists()) {
    if (!Token::check(Input::get('token'))) {
        Session::flash('agallery_error', $language->get('agallery', 'err_csrf'));
        Redirect::to(URL::build('/panel/agallery/settings'));
    }

    $maxMb = max(1, (int)Input::get('max_upload_mb'));
    $maxW = max(1, (int)Input::get('max_width'));
    $maxH = max(1, (int)Input::get('max_height'));
    $ext = trim((string)Input::get('allowed_extensions'));
    $qJ = min(100, max(1, (int)Input::get('image_quality_jpeg')));
    $qW = min(100, max(1, (int)Input::get('image_quality_webp')));
    $thumbW = max(50, (int)Input::get('thumb_width'));
    $toJpg = Input::get('convert_to_jpg') ? '1' : '0';
    $toWebp = Input::get('convert_to_webp') ? '1' : '0';

    aGallery::setSetting('max_upload_mb', (string)$maxMb);
    aGallery::setSetting('max_width', (string)$maxW);
    aGallery::setSetting('max_height', (string)$maxH);
    aGallery::setSetting('allowed_extensions', $ext);
    aGallery::setSetting('image_quality_jpeg', (string)$qJ);
    aGallery::setSetting('image_quality_webp', (string)$qW);
    aGallery::setSetting('thumb_width', (string)$thumbW);
    aGallery::setSetting('convert_to_jpg', $toJpg);
    aGallery::setSetting('convert_to_webp', $toWebp);

    Session::flash('agallery_success', $language->get('agallery', 'settings_saved'));
    Redirect::to(URL::build('/panel/agallery/settings'));
}

$settings = aGallery::settings();

$smarty->assign([
    'TOKEN' => Token::get(),
    'TITLE' => 'aGallery! - ' . $language->get('agallery', 'staffcp_settings'),
    'S' => $settings,
    'SUCCESS' => Session::exists('agallery_success') ? Session::flash('agallery_success') : null,
    'ERROR' => Session::exists('agallery_error') ? Session::flash('agallery_error') : null,
    'L' => [
        'settings' => $language->get('agallery', 'staffcp_settings'),
        'save' => $language->get('agallery', 'save'),
        'max_upload_mb' => $language->get('agallery', 'set_max_upload_mb'),
        'max_width' => $language->get('agallery', 'set_max_width'),
        'max_height' => $language->get('agallery', 'set_max_height'),
        'allowed_extensions' => $language->get('agallery', 'set_allowed_extensions'),
        'jpeg_q' => $language->get('agallery', 'set_jpeg_quality'),
        'webp_q' => $language->get('agallery', 'set_webp_quality'),
        'thumb_w' => $language->get('agallery', 'set_thumb_width'),
        'to_jpg' => $language->get('agallery', 'set_convert_to_jpg'),
        'to_webp' => $language->get('agallery', 'set_convert_to_webp'),
    ],
]);

$template->displayTemplate('agallery/settings.tpl');
