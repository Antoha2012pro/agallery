<?php
// /modules/aGallery/init.php

if (!defined('ROOT_PATH')) {
    die('ROOT_PATH not defined');
}

try {
    $module_file = ROOT_PATH . '/modules/aGallery/module.php';

    if (!file_exists($module_file)) {
        error_log('[aGallery] module.php not found: ' . $module_file);
        return;
    }

    require_once $module_file;

    if (!class_exists('aGallery_Module')) {
        error_log('[aGallery] class aGallery_Module not found after requiring module.php');
        return;
    }

    // $language и $queries обычно доступны глобально в Nameless init-контексте
    $module = new aGallery_Module($language ?? null, $queries ?? null);

} catch (Throwable $e) {
    error_log('[aGallery] init.php crash: ' . $e->getMessage());
}
