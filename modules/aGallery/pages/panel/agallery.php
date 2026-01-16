<?php
if (!defined('ROOT_PATH')) {
    die('Direct access not permitted');
}

require_once ROOT_PATH . '/core/templates/backend_init.php';

if (!$user->isLoggedIn()) {
    Redirect::to(URL::build('/login'));
}

if ($user->hasPermission('agallery.manage')) {
    Redirect::to(URL::build('/panel/agallery/categories'));
}

if ($user->hasPermission('agallery.moderate')) {
    Redirect::to(URL::build('/panel/agallery/moderation'));
}

Redirect::to(URL::build('/panel'));
