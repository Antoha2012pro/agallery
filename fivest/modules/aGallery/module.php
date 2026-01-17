<?php
class AGallery_Module extends Module {
    private $_language, $_queries, $_navigation, $_cache;

    public function __construct($language, $pages, $queries, $navigation, $cache) {
        $this->_language = $language;
        $this->_queries = $queries;
        $this->_navigation = $navigation;
        $this->_cache = $cache;

        $name = 'aGallery';
        $author = 'Gemini';
        $module_version = '1.0.0';
        $nameless_version = '2.2.4';

        parent::__construct($this, $name, $author, $module_version, $nameless_version);

        // Register Pages
        $pages->add('aGallery', '/gallery', 'pages/gallery.php', 'gallery', true);
        $pages->add('aGallery', '/panel/agallery/categories', 'pages/panel/categories.php');
        $pages->add('aGallery', '/panel/agallery/moderation', 'pages/panel/moderation.php');
        $pages->add('aGallery', '/panel/agallery/images', 'pages/panel/images.php');
        $pages->add('aGallery', '/panel/agallery/settings', 'pages/panel/settings.php');
    }

    public function onInstall() {
        $queries = $this->_queries;
        try {
            $queries->createTable("agallery_categories", " `id` int(11) NOT NULL AUTO_INCREMENT, `name` varchar(64) NOT NULL, `description` text, `sort_order` int(11) NOT NULL DEFAULT '0', `view_groups` text, `upload_groups` text, `created_at` int(11) NOT NULL, PRIMARY KEY (`id`)", "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $queries->createTable("agallery_images", " `id` int(11) NOT NULL AUTO_INCREMENT, `category_id` int(11) NOT NULL, `user_id` int(11) NOT NULL, `title` varchar(128) NOT NULL, `description` text, `file_path` varchar(255) NOT NULL, `thumb_path` varchar(255) NOT NULL, `mime` varchar(32) NOT NULL, `status` enum('pending','approved','declined') NOT NULL DEFAULT 'pending', `decline_reason` text, `moderated_by` int(11) DEFAULT NULL, `created_at` int(11) NOT NULL, PRIMARY KEY (`id`), KEY `status` (`status`), KEY `category_id` (`category_id`)", "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Default settings
            $queries->create('settings', ['name' => 'agallery_max_mb', 'value' => '50']);
            $queries->create('settings', ['name' => 'agallery_max_width', 'value' => '1920']);
            $queries->create('settings', ['name' => 'agallery_max_height', 'value' => '1080']);
        } catch (Exception $e) { /* Handle existing table */ }
    }

    public function onEnable() {
        PermissionHandler::registerPermissions('aGallery', [
            'agallery.upload' => $this->_language->get('admin', 'ability_to_upload_to_gallery'),
            'agallery.moderate' => $this->_language->get('admin', 'ability_to_moderate_gallery'),
            'agallery.manage' => $this->_language->get('admin', 'ability_to_manage_gallery')
        ]);
    }

    public function onDisable() {}
    public function onUninstall() {}

    public function onPageLoad($user, $pages, $cache, $smarty, $navs) {
        $language = new Language(ROOT_PATH . '/modules/aGallery/language', LANGUAGE);
        $this->_navigation->add('agallery', $language->get('agallery', 'gallery'), URL::build('/gallery'), 'top', null, 10);
        
        if (defined('BACK_END')) {
            if ($user->hasPermission('agallery.manage') || $user->hasPermission('agallery.moderate')) {
                $navs[2]->add('agallery_divider', mb_strtoupper($language->get('agallery', 'gallery')), 'divider', 'top', null, 50);
                $navs[2]->add('agallery_mod', $language->get('agallery', 'moderation'), URL::build('/panel/agallery/moderation'), 'top', null, 51);
                if ($user->hasPermission('agallery.manage')) {
                    $navs[2]->add('agallery_cats', $language->get('agallery', 'categories'), URL::build('/panel/agallery/categories'), 'top', null, 52);
                    $navs[2]->add('agallery_imgs', $language->get('agallery', 'images'), URL::build('/panel/agallery/images'), 'top', null, 53);
                    $navs[2]->add('agallery_settings', $language->get('agallery', 'settings'), URL::build('/panel/agallery/settings'), 'top', null, 54);
                }
            }
        }
    }
}