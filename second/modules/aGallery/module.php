<?php

require_once ROOT_PATH . '/modules/aGallery/includes/classes/aGalleryRepository.php';
require_once ROOT_PATH . '/modules/aGallery/includes/classes/aGalleryImageProcessor.php';
require_once ROOT_PATH . '/modules/aGallery/includes/classes/aGallerySettings.php';

class aGallery_Module extends Module {

    public function __construct() {
        $name = 'aGallery!';
        $author = 'Orbitium';
        $module_version = '1.0.0';
        $nameless_version = '2.2.4';

        parent::__construct($this, $name, $author, $module_version, $nameless_version);
    }

    public function onInstall() {
        $repo = new aGalleryRepository();
        $repo->installSchema();

        $settings = new aGallerySettings($repo);
        $settings->ensureDefaults();
    }

    public function onUninstall() {
        // Intentionally do nothing (safe uninstall would require explicit confirmation).
    }

    public function onEnable() {}
    public function onDisable() {}

    public function onPageLoad($user, $pages, $cache, $smarty, $navs, $widgets, $template) {
        // Load language file
        $language = $GLOBALS['language'] ?? null;

        // Register permissions (StaffCP -> Permissions)
        // Official doc: PermissionHandler::registerPermissions(section, array(permission => title)) :contentReference[oaicite:2]{index=2}
        PermissionHandler::registerPermissions(
            'aGallery!',
            [
                'agallery.upload'   => $language ? $language->get('agallery', 'perm_upload') : 'aGallery: Upload',
                'agallery.moderate' => $language ? $language->get('agallery', 'perm_moderate') : 'aGallery: Moderate',
                'agallery.manage'   => $language ? $language->get('agallery', 'perm_manage') : 'aGallery: Manage',
            ]
        );

        // Register pages
        // Official doc: Pages::add(moduleName, url, file, name?, widgets?) :contentReference[oaicite:3]{index=3}
        $pages->add('aGallery', '/gallery', 'pages/gallery.php', 'aGallery', true);
        $pages->add('aGallery', '/gallery/upload', 'pages/upload.php', 'aGallery Upload', false);

        // StaffCP pages
        $pages->add('aGallery', '/panel/agallery', 'pages/panel/index.php', 'aGallery Panel', false);
        $pages->add('aGallery', '/panel/agallery/categories', 'pages/panel/categories.php', 'aGallery Categories', false);
        $pages->add('aGallery', '/panel/agallery/moderation', 'pages/panel/moderation.php', 'aGallery Moderation', false);
        $pages->add('aGallery', '/panel/agallery/images', 'pages/panel/images.php', 'aGallery Images', false);
        $pages->add('aGallery', '/panel/agallery/settings', 'pages/panel/settings.php', 'aGallery Settings', false);

        // Add navigation items
        // Official doc: $navs[0]=top frontend, $navs[2]=StaffCP sidebar :contentReference[oaicite:4]{index=4}
        if ($language) {
            $navs[0]->add('agallery', $language->get('agallery', 'nav_gallery'), URL::build('/gallery'), 'top');
        } else {
            $navs[0]->add('agallery', 'Gallery', URL::build('/gallery'), 'top');
        }

        // StaffCP nav: show if user can manage or moderate
        if ($user->isLoggedIn() && ($user->hasPermission('agallery.manage') || $user->hasPermission('agallery.moderate'))) {
            $title = $language ? $language->get('agallery', 'staffcp_title') : 'aGallery!';
            $navs[2]->add('agallery', $title, URL::build('/panel/agallery'));
        }
    }

    public function getDebugInfo(): array {
        return [
            'name' => 'aGallery!',
            'version' => '1.0.0',
            'nameless' => '2.2.4',
        ];
    }
}