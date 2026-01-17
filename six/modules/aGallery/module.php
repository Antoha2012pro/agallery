<?php

require_once __DIR__ . '/includes/classes/AGallerySettings.php';
require_once __DIR__ . '/includes/classes/AGalleryCompat.php';
require_once __DIR__ . '/includes/classes/AGalleryImageService.php';
require_once __DIR__ . '/includes/classes/AGalleryCategoryService.php';
require_once __DIR__ . '/includes/classes/AGalleryHealthCheck.php';

/**
 * aGallery! module.
 */
class AGalleryModule extends Module {

    public function __construct() {
        $name = 'aGallery';
        $author = 'Custom';
        $version = '1.0.0';
        $nameless_version = '2.2.0';

        // load_before/load_after — необязательные массивы, оставляем по умолчанию. [web:3]
        parent::__construct($this, $name, $author, $version, $nameless_version);
    }

    public function onInstall() {
        $db = DB::getInstance();

        $db->createTable('agallery_categories', '
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(128) NOT NULL,
            `description` TEXT NULL,
            `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
            `view_groups` TEXT NULL,
            `upload_groups` TEXT NULL,
            `created_at` INT UNSIGNED NOT NULL,
            `updated_at` INT UNSIGNED NOT NULL,
            INDEX (`sort_order`)
        ');

        $db->createTable('agallery_images', '
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `category_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            `file_path` VARCHAR(255) NOT NULL,
            `thumb_path` VARCHAR(255) NOT NULL,
            `mime` VARCHAR(64) NOT NULL,
            `ext` VARCHAR(16) NOT NULL,
            `width` INT UNSIGNED NOT NULL,
            `height` INT UNSIGNED NOT NULL,
            `file_size` INT UNSIGNED NOT NULL,
            `status` ENUM("pending","approved","declined") NOT NULL DEFAULT "pending",
            `decline_reason` TEXT NULL,
            `moderated_by` INT UNSIGNED NULL,
            `moderated_at` INT UNSIGNED NULL,
            `created_at` INT UNSIGNED NOT NULL,
            `updated_at` INT UNSIGNED NOT NULL,
            INDEX (`status`),
            INDEX (`category_id`),
            INDEX (`created_at`)
        ');

        $db->createTable('agallery_audit_log', '
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `action` VARCHAR(64) NOT NULL,
            `image_id` INT UNSIGNED NOT NULL,
            `actor_id` INT UNSIGNED NOT NULL,
            `details` TEXT NULL,
            `created_at` INT UNSIGNED NOT NULL,
            INDEX (`image_id`),
            INDEX (`created_at`)
        ');

        AGallerySettings::installDefaults();
    }

    public function onUninstall() {
        $db = DB::getInstance();
        $db->dropTable('agallery_categories');
        $db->dropTable('agallery_images');
        $db->dropTable('agallery_audit_log');
        AGallerySettings::removeAll();
    }

    public function onEnable() {
        AGalleryCompat::registerNotificationType();
    }

    public function onDisable() {
        // no-op
    }

    /**
     * Handle page loading for this module. [web:3]
     *
     * @param User        $user
     * @param Pages       $pages
     * @param Cache       $cache
     * @param FakeSmarty|Smarty $smarty
     * @param Navigation[] $navs
     * @param Widgets     $widgets
     * @param TemplateBase $template
     */
public function onPageLoad($user, $pages, $cache, $smarty, $navs, $widgets, $template) {

    global $language;

    // 1) Права
    if (class_exists('PermissionHandler') && isset($language) && $language instanceof Language) {
        PermissionHandler::registerPermissions('aGallery', [
            'agallery.upload'   => $language->get('agallery', 'perm_upload'),
            'agallery.moderate' => $language->get('agallery', 'perm_moderate'),
            'agallery.manage'   => $language->get('agallery', 'perm_manage'),
        ]);
    }

    // 2) Публичная страница /gallery + staff‑страницы
    if ($pages instanceof Pages) {
        // /gallery (frontend)
        $pages->add(
            'aGallery',              // имя модуля = папка modules/aGallery
            '/gallery',             // URL
            'pages/gallery.php'     // относительный файл
        );

        // StaffCP
        $pages->add(
            'aGallery',
            '/panel/agallery/categories',
            'pages/panel/agallery_categories.php'
        );
        $pages->add(
            'aGallery',
            '/panel/agallery/moderation',
            'pages/panel/agallery_moderation.php'
        );
        $pages->add(
            'aGallery',
            '/panel/agallery/images',
            'pages/panel/agallery_images.php'
        );
        $pages->add(
            'aGallery',
            '/panel/agallery/settings',
            'pages/panel/agallery_settings.php'
        );
    }

    // 3) Навбар (top)
    if (is_array($navs) && isset($navs[0]) && $navs[0] instanceof Navigation && isset($language) && $language instanceof Language) {
        $navs[0]->add(
            'agallery_nav',
            $language->get('agallery', 'nav_gallery'),
            URL::build('/gallery'),
            'top',
            null,
            40
        );
    }
}


    public function getDebugInfo(): array {
        return [];
    }
}
