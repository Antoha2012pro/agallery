<?php

class aGallery_Audit {

    public static function install(DB $db): void {
        $t = aGallery_Compat::table('agallery_audit_log');
        $db->query("CREATE TABLE IF NOT EXISTS `{$t}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `action` VARCHAR(64) NOT NULL,
            `image_id` INT UNSIGNED NULL,
            `actor_id` INT UNSIGNED NULL,
            `details` TEXT NULL,
            `created_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            INDEX (`action`),
            INDEX (`image_id`),
            INDEX (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", []);
    }

    public static function add(DB $db, string $action, ?int $imageId, ?int $actorId, array $details = []): void {
        $t = aGallery_Compat::table('agallery_audit_log');
        $db->query("INSERT INTO `{$t}` (action, image_id, actor_id, details, created_at) VALUES (?,?,?,?,?)", [
            $action,
            $imageId,
            $actorId,
            json_encode($details, JSON_UNESCAPED_UNICODE),
            aGallery_Util::now(),
        ]);
    }
}
