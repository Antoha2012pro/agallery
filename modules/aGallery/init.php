<?php
/**
 * aGallery module init file
 */

if (!defined('ROOT_PATH')) {
    die('Direct access not permitted');
}

require_once ROOT_PATH . '/modules/aGallery/module.php';

// Register module instance (Module base class handles registration internally).
new aGallery_Module();
