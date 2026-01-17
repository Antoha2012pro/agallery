<?php

class aGallery_Categories {

    public static function install(DB $db): void {
        $t = aGallery_Compat::table('agallery_categories');
        $db->query("CREATE TABLE IF NOT EXISTS `{$t}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(64) NOT NULL,
            `description` VARCHAR(255) NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `view_groups` TEXT NULL,
            `upload_groups` TEXT NULL,
            `created_at` DATETIME NULL,
            `updated_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            INDEX (`sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", []);
    }

    public static function all(DB $db): array {
        $t = aGallery_Compat::table('agallery_categories');
        return $db->query("SELECT * FROM `{$t}` ORDER BY sort_order ASC, id ASC", [])->results();
    }

    public static function get(DB $db, int $id) {
        $t = aGallery_Compat::table('agallery_categories');
        $r = $db->query("SELECT * FROM `{$t}` WHERE id = ? LIMIT 1", [$id])->results();
        return $r[0] ?? null;
    }

    public static function create(DB $db, string $name, string $desc, int $sort, array $viewGroups, array $uploadGroups): void {
        $t = aGallery_Compat::table('agallery_categories');
        $db->query("INSERT INTO `{$t}` (name, description, sort_order, view_groups, upload_groups, created_at, updated_at)
                    VALUES (?,?,?,?,?,?,?)", [
            $name,
            $desc !== '' ? $desc : null,
            $sort,
            aGallery_Util::intArrayToJson($viewGroups),
            aGallery_Util::intArrayToJson($uploadGroups),
            aGallery_Util::now(),
            aGallery_Util::now(),
        ]);
    }

    public static function update(DB $db, int $id, string $name, string $desc, int $sort, array $viewGroups, array $uploadGroups): void {
        $t = aGallery_Compat::table('agallery_categories');
        $db->query("UPDATE `{$t}` SET name=?, description=?, sort_order=?, view_groups=?, upload_groups=?, updated_at=? WHERE id=?",
            [
                $name,
                $desc !== '' ? $desc : null,
                $sort,
                aGallery_Util::intArrayToJson($viewGroups),
                aGallery_Util::intArrayToJson($uploadGroups),
                aGallery_Util::now(),
                $id
            ]
        );
    }

    public static function delete(DB $db, int $id): void {
        $t = aGallery_Compat::table('agallery_categories');
        $db->query("DELETE FROM `{$t}` WHERE id=?", [$id]);
    }

    public static function canViewCategory(DB $db, $user, $catRow): bool {
        if (!$catRow) return false;
        $json = $catRow->view_groups ?? null;
        $viewGroups = aGallery_Util::parseIntArrayFromJson($json);
        if (!count($viewGroups)) return true; // empty => everyone
        $userGroups = aGallery_Compat::getUserGroupIds($user, $db);
        return aGallery_Util::intersects($viewGroups, $userGroups);
    }

    public static function canUploadToCategory(DB $db, $user, $catRow): bool {
        if (!$catRow) return false;

        if (!aGallery_Compat::hasPerm($user, 'agallery.upload')) {
            return false;
        }

        $json = $catRow->upload_groups ?? null;
        $uploadGroups = aGallery_Util::parseIntArrayFromJson($json);
        if (!count($uploadGroups)) {
            // If empty -> nobody (safer) OR everyone? Requirement: upload must be in list upload-groups category.
            // So empty list => nobody.
            return false;
        }

        $userGroups = aGallery_Compat::getUserGroupIds($user, $db);
        return aGallery_Util::intersects($uploadGroups, $userGroups);
    }

    public static function uploadableForUser(DB $db, $user): array {
        $cats = self::all($db);
        $out = [];
        foreach ($cats as $c) {
            if (self::canUploadToCategory($db, $user, $c)) {
                $out[] = $c;
            }
        }
        return $out;
    }

    public static function viewableCategoryIds(DB $db, $user): array {
        $cats = self::all($db);
        $ids = [];
        foreach ($cats as $c) {
            if (self::canViewCategory($db, $user, $c)) {
                $ids[] = (int)$c->id;
            }
        }
        return $ids;
    }

    public static function groupsList(DB $db): array {
        // Returns [ [id, name], ... ]
        $t = aGallery_Compat::table('groups');
        try {
            $rows = $db->query("SELECT id, name FROM `{$t}` ORDER BY id ASC", [])->results();
            $out = [];
            foreach ($rows as $r) {
                $out[] = ['id' => (int)$r->id, 'name' => (string)$r->name];
            }
            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }
}
