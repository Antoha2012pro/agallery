<?php
/**
 * Simple autoloader for aGallery classes
 */
spl_autoload_register(static function (string $class) {
    if (strpos($class, 'aGallery') !== 0) {
        return;
    }

    $base = __DIR__ . '/classes/';
    $map = [
        'aGallery' => 'aGallery.php',
        'aGallery_Compat' => 'Compat.php',
        'aGallery_ImageProcessor' => 'ImageProcessor.php',
        'aGallery_Utils' => 'Utils.php',
    ];

    if (isset($map[$class])) {
        $path = $base . $map[$class];
        if (is_file($path)) {
            require_once $path;
        }
    }
});
