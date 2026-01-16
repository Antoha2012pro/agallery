<?php
/**
 * aGallery module main class.
 */

if (!defined('ROOT_PATH')) {
    die('Direct access not permitted');
}

class aGallery_Module extends Module {

    public function __construct() {
        parent::__construct(
            $this,
            'aGallery!',      // UI name (allowed to include "!")
            'ORBitium',       // Author (change if you want)
            '1.0.0',          // Module version
            '2.2.4',          // Supported NamelessMC version
            [],               // load_before
            []                // load_after
        );
    }

    public function onInstall(): void {
        // Tables (minimal + settings table).
        DB::getInstance()->query("
            CREATE TABLE IF NOT EXISTS agallery_categories (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT,
              name VARCHAR(64) NOT NULL,
              description TEXT NULL,
              sort_order INT NOT NULL DEFAULT 0,
              view_groups TEXT NULL,
              upload_groups TEXT NULL,
              created_at INT NOT NULL,
              updated_at INT NOT NULL,
              PRIMARY KEY (id),
              KEY idx_sort_order (sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        DB::getInstance()->query("
            CREATE TABLE IF NOT EXISTS agallery_images (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT,
              category_id INT UNSIGNED NOT NULL,
              user_id INT UNSIGNED NOT NULL,
              title VARCHAR(128) NOT NULL,
              description TEXT NULL,
              file_path VARCHAR(255) NOT NULL,
              thumb_path VARCHAR(255) NOT NULL,
              mime VARCHAR(64) NOT NULL,
              ext VARCHAR(16) NOT NULL,
              width INT UNSIGNED NOT NULL,
              height INT UNSIGNED NOT NULL,
              file_size BIGINT UNSIGNED NOT NULL,
              status ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
              decline_reason TEXT NULL,
              moderated_by INT UNSIGNED NULL,
              moderated_at INT NULL,
              created_at INT NOT NULL,
              updated_at INT NOT NULL,
              PRIMARY KEY (id),
              KEY idx_status (status),
              KEY idx_category (category_id),
              KEY idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        DB::getInstance()->query("
            CREATE TABLE IF NOT EXISTS agallery_audit_log (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT,
              action VARCHAR(64) NOT NULL,
              image_id INT UNSIGNED NULL,
              actor_id INT UNSIGNED NULL,
              details TEXT NULL,
              created_at INT NOT NULL,
              PRIMARY KEY (id),
              KEY idx_image (image_id),
              KEY idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        DB::getInstance()->query("
            CREATE TABLE IF NOT EXISTS agallery_settings (
              `key` VARCHAR(64) NOT NULL,
              `value` TEXT NULL,
              PRIMARY KEY (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Defaults
        $defaults = [
            'max_upload_mb' => '50',
            'max_width' => '1920',
            'max_height' => '1080',
            'allowed_extensions' => 'png,jpg,jpeg,webp,gif',
            'image_quality_jpeg' => '82',
            'image_quality_webp' => '80',
            'thumb_width' => '480',
            'convert_to_jpg' => '0',
            'convert_to_webp' => '0',
        ];

        foreach ($defaults as $k => $v) {
            DB::getInstance()->query(
                "INSERT IGNORE INTO agallery_settings (`key`,`value`) VALUES (?,?)",
                [$k, $v]
            );
        }
    }

    public function onUninstall(): void {
        // Intentionally do NOT drop tables automatically.
        // (Safer for production: admin can drop manually.)
    }

    public function onEnable(): void {
        // no-op
    }

    public function onDisable(): void {
        // no-op
    }

    public function onPageLoad(User $user, Pages $pages, Cache $cache, $smarty, array $navs, Widgets $widgets, TemplateBase $template): void {
        $language = new Language();

        // Permissions (so they appear in StaffCP -> Permissions).
        // NOTE: PermissionHandler API can vary between minor versions; if you get a fatal error here,
        // paste your core/classes/Core/PermissionHandler.php and I'll adjust to exact signature.
        if (class_exists('PermissionHandler') && method_exists('PermissionHandler', 'registerPermissions')) {
            PermissionHandler::registerPermissions('aGallery', [
                'agallery.upload' => $language->get('agallery', 'perm_upload'),
                'agallery.moderate' => $language->get('agallery', 'perm_moderate'),
                'agallery.manage' => $language->get('agallery', 'perm_manage'),
            ]);
        }

        // Frontend page
        // NOTE: If Pages::add signature differs, paste core/classes/Core/Pages.php.
        $pages->add('aGallery', '/gallery', 'modules/aGallery/pages/gallery.php');

        // StaffCP pages
        $pages->add('aGallery', '/panel/agallery', 'modules/aGallery/pages/panel/agallery.php');
        $pages->add('aGallery', '/panel/agallery/categories', 'modules/aGallery/pages/panel/categories.php');
        $pages->add('aGallery', '/panel/agallery/moderation', 'modules/aGallery/pages/panel/moderation.php');
        $pages->add('aGallery', '/panel/agallery/images', 'modules/aGallery/pages/panel/images.php');
        $pages->add('aGallery', '/panel/agallery/settings', 'modules/aGallery/pages/panel/settings.php');

        // Front nav item (top navbar)
        // Navigation::add signature confirmed in phpdoc. :contentReference[oaicite:2]{index=2}
        if (isset($navs[0]) && $navs[0] instanceof Navigation) {
            $navs[0]->add(
                'agallery',
                $language->get('agallery', 'nav_gallery'),
                URL::build('/gallery'),
                'top',
                null,
                10,
                'images'
            );
        }

        // StaffCP nav (panel navbar)
        // We assume $navs[2] is panel nav (common in v2). If yours differs, tell me what keys/indexes exist.
        if (isset($navs[2]) && $navs[2] instanceof Navigation && $user->isLoggedIn() && $user->hasPermission('agallery.manage')) {
            $navs[2]->addDropdown('agallery', 'aGallery!', 'top', 50, 'images');
            $navs[2]->addItemToDropdown('agallery', 'agallery_categories', $language->get('agallery', 'staffcp_categories'), URL::build('/panel/agallery/categories'));
            $navs[2]->addItemToDropdown('agallery', 'agallery_moderation', $language->get('agallery', 'staffcp_moderation'), URL::build('/panel/agallery/moderation'));
            $navs[2]->addItemToDropdown('agallery', 'agallery_images', $language->get('agallery', 'staffcp_images'), URL::build('/panel/agallery/images'));
            $navs[2]->addItemToDropdown('agallery', 'agallery_settings', $language->get('agallery', 'staffcp_settings'), URL::build('/panel/agallery/settings'));
        } elseif (isset($navs[2]) && $navs[2] instanceof Navigation && $user->isLoggedIn() && $user->hasPermission('agallery.moderate')) {
            $navs[2]->add('agallery_moderation', 'aGallery! - ' . $language->get('agallery', 'staffcp_moderation'), URL::build('/panel/agallery/moderation'), 'top', null, 50, 'images');
        }
    }
}
