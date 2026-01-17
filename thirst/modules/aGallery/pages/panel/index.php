<?php
require_once ROOT_PATH . '/modules/aGallery/includes/autoload.php';

aGallery_Compat::requireBackendInit();
global $user, $language;

if (!$user->isLoggedIn()) {
    aGallery_Compat::redirect(URL::build('/login'));
}
if (method_exists($user, 'handlePanelPageLoad')) {
    // allow viewing StaffCP, but module pages require manage
    $user->handlePanelPageLoad('agallery.manage');
} else {
    if (!$user->hasPermission('agallery.manage')) {
        aGallery_Compat::redirect(URL::build('/panel'));
    }
}

$title = aGallery_Compat::lang('panel_title', 'panel');

echo '<div class="container"><div class="row"><div class="col-md-12">';
echo '<h2>' . aGallery_Utils::h($title) . '</h2>';
echo '<ul>';
echo '<li><a href="' . aGallery_Utils::h(URL::build('/panel/agallery/categories')) . '">' . aGallery_Utils::h(aGallery_Compat::lang('categories', 'panel')) . '</a></li>';
echo '<li><a href="' . aGallery_Utils::h(URL::build('/panel/agallery/moderation')) . '">' . aGallery_Utils::h(aGallery_Compat::lang('moderation', 'panel')) . '</a></li>';
echo '<li><a href="' . aGallery_Utils::h(URL::build('/panel/agallery/images')) . '">' . aGallery_Utils::h(aGallery_Compat::lang('images', 'panel')) . '</a></li>';
echo '<li><a href="' . aGallery_Utils::h(URL::build('/panel/agallery/settings')) . '">' . aGallery_Utils::h(aGallery_Compat::lang('settings', 'panel')) . '</a></li>';
echo '</ul>';
echo '</div></div></div>';
