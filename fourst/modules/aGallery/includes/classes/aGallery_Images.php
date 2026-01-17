<?php

class aGallery_Images {

    public static function install(DB $db): void {
        $t = aGallery_Compat::table('agallery_images');
        $db->query("CREATE TABLE IF NOT EXISTS `{$t}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `category_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(128) NOT NULL,
            `description` VARCHAR(600) NULL,
            `file_path` VARCHAR(255) NULL,
            `thumb_path` VARCHAR(255) NULL,
            `mime` VARCHAR(64) NULL,
            `ext` VARCHAR(12) NULL,
            `width` INT NULL,
            `height` INT NULL,
            `file_size` BIGINT NULL,
            `status` ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
            `decline_reason` VARCHAR(255) NULL,
            `moderated_by` INT UNSIGNED NULL,
            `moderated_at` DATETIME NULL,
            `created_at` DATETIME NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_category` (`category_id`),
            INDEX `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", []);
    }

    public static function createPending(DB $db, int $categoryId, int $userId, string $title, string $desc): int {
        $t = aGallery_Compat::table('agallery_images');
        $db->query("INSERT INTO `{$t}` (category_id, user_id, title, description, status, created_at, updated_at)
                    VALUES (?,?,?,?, 'pending', ?, ?)", [
            $categoryId,
            $userId,
            $title,
            $desc !== '' ? $desc : null,
            aGallery_Util::now(),
            aGallery_Util::now(),
        ]);

        $idRow = $db->query("SELECT LAST_INSERT_ID() AS id", [])->results();
        return (int)($idRow[0]->id ?? 0);
    }

    public static function attachFiles(DB $db, int $id, string $fileRel, string $thumbRel, string $mime, string $ext, int $w, int $h, int $size): void {
        $t = aGallery_Compat::table('agallery_images');
        $db->query("UPDATE `{$t}` SET file_path=?, thumb_path=?, mime=?, ext=?, width=?, height=?, file_size=?, updated_at=? WHERE id=?",
            [$fileRel, $thumbRel, $mime, $ext, $w, $h, $size, aGallery_Util::now(), $id]
        );
    }

    public static function get(DB $db, int $id) {
        $t = aGallery_Compat::table('agallery_images');
        $rows = $db->query("SELECT * FROM `{$t}` WHERE id=? LIMIT 1", [$id])->results();
        return $rows[0] ?? null;
    }

    public static function listByStatus(DB $db, string $status, int $limit, int $offset): array {
        $t = aGallery_Compat::table('agallery_images');
        return $db->query("SELECT * FROM `{$t}` WHERE status=? ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}", [$status])->results();
    }

    public static function countByStatus(DB $db, string $status): int {
        $t = aGallery_Compat::table('agallery_images');
        $r = $db->query("SELECT COUNT(*) AS c FROM `{$t}` WHERE status=?", [$status])->results();
        return (int)($r[0]->c ?? 0);
    }

    public static function listApprovedForUser(DB $db, $user, int $limit, int $offset): array {
        $ids = aGallery_Categories::viewableCategoryIds($db, $user);
        if (!count($ids)) return [];

        $t = aGallery_Compat::table('agallery_images');
        $in = implode(',', array_map('intval', $ids));
        return $db->query(
            "SELECT * FROM `{$t}`
             WHERE status='approved' AND category_id IN ({$in})
             ORDER BY created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            []
        )->results();
    }

    public static function countApprovedForUser(DB $db, $user): int {
        $ids = aGallery_Categories::viewableCategoryIds($db, $user);
        if (!count($ids)) return 0;

        $t = aGallery_Compat::table('agallery_images');
        $in = implode(',', array_map('intval', $ids));
        $r = $db->query("SELECT COUNT(*) AS c FROM `{$t}` WHERE status='approved' AND category_id IN ({$in})", [])->results();
        return (int)($r[0]->c ?? 0);
    }

    public static function approve(DB $db, int $id, int $moderatorId): void {
        $t = aGallery_Compat::table('agallery_images');
        $db->query("UPDATE `{$t}` SET status='approved', decline_reason=NULL, moderated_by=?, moderated_at=?, updated_at=? WHERE id=?",
            [$moderatorId, aGallery_Util::now(), aGallery_Util::now(), $id]
        );
    }

    public static function decline(DB $db, int $id, int $moderatorId, string $reason): void {
        $t = aGallery_Compat::table('agallery_images');
        $db->query("UPDATE `{$t}` SET status='declined', decline_reason=?, moderated_by=?, moderated_at=?, updated_at=? WHERE id=?",
            [$reason, $moderatorId, aGallery_Util::now(), aGallery_Util::now(), $id]
        );
    }

    public static function updateMeta(DB $db, int $id, int $categoryId, string $title, string $desc): void {
        $t = aGallery_Compat::table('agallery_images');
        $db->query("UPDATE `{$t}` SET category_id=?, title=?, description=?, updated_at=? WHERE id=?",
            [$categoryId, $title, $desc !== '' ? $desc : null, aGallery_Util::now(), $id]
        );
    }

    public static function delete(DB $db, int $id): void {
        $img = self::get($db, $id);
        if ($img) {
            $file = ROOT_PATH . ($img->file_path ?? '');
            $thumb = ROOT_PATH . ($img->thumb_path ?? '');
            if (is_file($file)) @unlink($file);
            if (is_file($thumb)) @unlink($thumb);
        }

        $t = aGallery_Compat::table('agallery_images');
        $db->query("DELETE FROM `{$t}` WHERE id=?", [$id]);
    }
}
