<?php
if (!$user->hasPermission('agallery.manage')) {
    Redirect::to(URL::build('/panel'));
    die();
}

$ag_language = AGallery::getLanguage();
$writable_check = [];
$paths = ['uploads/agallery'];
foreach($paths as $p) {
    $writable_check[$p] = is_writable(ROOT_PATH . '/' . $p);
}

$php_limits = [
    'upload_max' => ini_get('upload_max_filesize'),
    'post_max' => ini_get('post_max_size'),
    'memory' => ini_get('memory_limit')
];

$smarty->assign([
    'HEALTH_CHECK' => $ag_language->get('agallery', 'health_check'),
    'WRITABLE_PATHS' => $writable_check,
    'PHP_LIMITS' => $php_limits
]);
// Дальнейший рендер panel/agallery/settings.tpl