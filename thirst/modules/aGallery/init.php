<?php
/**
 * aGallery init.php (safe)
 * NamelessMC v2.2.4
 */

if (!defined('ROOT_PATH')) {
    return;
}

try {
    $module_file = ROOT_PATH . '/modules/aGallery/module.php';
    if (!is_file($module_file)) {
        error_log('[aGallery] module.php not found: ' . $module_file);
        return;
    }

    require_once $module_file;

    if (class_exists('aGallery_Module', false)) {
        $module = new aGallery_Module();
    } else {
        error_log('[aGallery] class aGallery_Module not found after require_once module.php');
        return;
    }
} catch (\Throwable $e) {
    error_log('[aGallery] init.php crash: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    return;
}
