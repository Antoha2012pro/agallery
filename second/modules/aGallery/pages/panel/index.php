<?php
if (!defined('ROOT_PATH')) die('Direct access not permitted');

if (!$user->isLoggedIn()) {
    Redirect::to(URL::build('/login'));
    die();
}

if (!($user->hasPermission('agallery.manage') || $user->hasPermission('agallery.moderate'))) {
    require_once(ROOT_PATH . '/403.php');
    die();
}

Redirect::to(URL::build('/panel/agallery/moderation'));
die();