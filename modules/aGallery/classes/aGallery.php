<?php
if (!defined('ROOT_PATH')) {
    die('Direct access not permitted');
}

class aGallery {

    public static function settings(): array {
        $rows = DB::getInstance()->query("SELECT `key`,`value` FROM agallery_settings")->results();
        $out = [];
        foreach ($rows as $r) {
            $out[$r->key] = (string)$r->value;
        }
        return $out;
    }

    public static function getSetting(string $key, $default = null) {
        $row = DB::getInstance()->query("SELECT `value` FROM agallery_settings WHERE `key` = ?", [$key])->first();
        return $row ? (string)$row->value : $default;
    }

    public static function setSetting(string $key, string $value): void {
        DB::getInstance()->query("
            INSERT INTO agallery_settings (`key`,`value`) VALUES (?,?)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
        ", [$key, $value]);
    }

    public static function slugifyUsername(string $username): string {
        $u = mb_strtolower($username, 'UTF-8');
        // transliteration is not guaranteed on all servers; keep safe replacement:
        $u = preg_replace('/[^a-z0-9_-]+/i', '_', $u);
        $u = trim($u, '_');
        if ($u === '') {
            $u = 'user';
        }
        return substr($u, 0, 32);
    }

    public static function userGroupIds(int $userId): array {
        $rows = DB::getInstance()->query("SELECT group_id FROM nl2_users_groups WHERE user_id = ?", [$userId])->results();
        return array_map(static fn($r) => (int)$r->group_id, $rows);
    }

    public static function allowedByGroups(?string $json, ?int $userId): bool {
        if ($json === null || trim($json) === '') {
            return true; // empty => everyone
        }

        $allowed = json_decode($json, true);
        if (!is_array($allowed) || count($allowed) === 0) {
            return true;
        }

        if ($userId === null) {
            return false; // guest cannot match any group
        }

        $userGroups = self::userGroupIds($userId);
        foreach ($allowed as $gid) {
            if (in_array((int)$gid, $userGroups, true)) {
                return true;
            }
        }
        return false;
    }

    public static function getCategories(): array {
        return DB::getInstance()->query("SELECT * FROM agallery_categories ORDER BY sort_order ASC, id ASC")->results();
    }

    public static function getCategory(int $id) {
        return DB::getInstance()->query("SELECT * FROM agallery_categories WHERE id = ?", [$id])->first();
    }

    public static function canViewCategory($cat, ?int $userId): bool {
        return self::allowedByGroups($cat->view_groups ?? null, $userId);
    }

    public static function canUploadCategory($cat, ?int $userId): bool {
        return self::allowedByGroups($cat->upload_groups ?? null, $userId);
    }

    public static function insertAudit(string $action, ?int $imageId, ?int $actorId, ?string $details = null): void {
        DB::getInstance()->query("
            INSERT INTO agallery_audit_log (action,image_id,actor_id,details,created_at)
            VALUES (?,?,?,?,?)
        ", [$action, $imageId, $actorId, $details, time()]);
    }

    public static function createImage(array $data): int {
        DB::getInstance()->query("
            INSERT INTO agallery_images
              (category_id,user_id,title,description,file_path,thumb_path,mime,ext,width,height,file_size,status,created_at,updated_at)
            VALUES
              (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ", [
            $data['category_id'],
            $data['user_id'],
            $data['title'],
            $data['description'],
            $data['file_path'],
            $data['thumb_path'],
            $data['mime'],
            $data['ext'],
            $data['width'],
            $data['height'],
            $data['file_size'],
            $data['status'],
            time(),
            time(),
        ]);

        $id = (int)DB::getInstance()->lastId();
        self::insertAudit('create', $id, (int)$data['user_id'], 'status=' . $data['status']);
        return $id;
    }

    public static function getImage(int $id) {
        return DB::getInstance()->query("SELECT * FROM agallery_images WHERE id = ?", [$id])->first();
    }

    public static function listApprovedImages(array $categoryIds, int $limit, int $offset): array {
        if (count($categoryIds) === 0) {
            return [];
        }
        $in = implode(',', array_fill(0, count($categoryIds), '?'));

        return DB::getInstance()->query("
            SELECT i.*, c.name AS category_name
            FROM agallery_images i
            JOIN agallery_categories c ON c.id = i.category_id
            WHERE i.status = 'approved'
              AND i.category_id IN ($in)
            ORDER BY i.created_at DESC
            LIMIT $limit OFFSET $offset
        ", $categoryIds)->results();
    }

    public static function countApprovedImages(array $categoryIds): int {
        if (count($categoryIds) === 0) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($categoryIds), '?'));

        $row = DB::getInstance()->query("
            SELECT COUNT(*) AS cnt
            FROM agallery_images
            WHERE status = 'approved'
              AND category_id IN ($in)
        ", $categoryIds)->first();

        return (int)($row->cnt ?? 0);
    }

    public static function listModeration(string $status, int $limit, int $offset): array {
        $allowed = ['pending','approved','declined'];
        if (!in_array($status, $allowed, true)) {
            $status = 'pending';
        }

        return DB::getInstance()->query("
            SELECT i.*, c.name AS category_name
            FROM agallery_images i
            JOIN agallery_categories c ON c.id = i.category_id
            WHERE i.status = ?
            ORDER BY i.created_at DESC
            LIMIT $limit OFFSET $offset
        ", [$status])->results();
    }

    public static function countModeration(string $status): int {
        $allowed = ['pending','approved','declined'];
        if (!in_array($status, $allowed, true)) {
            $status = 'pending';
        }
        $row = DB::getInstance()->query("SELECT COUNT(*) AS cnt FROM agallery_images WHERE status = ?", [$status])->first();
        return (int)($row->cnt ?? 0);
    }

    public static function approve(int $imageId, int $moderatorId): void {
        DB::getInstance()->query("
            UPDATE agallery_images
            SET status='approved', moderated_by=?, moderated_at=?, updated_at=?
            WHERE id=?
        ", [$moderatorId, time(), time(), $imageId]);

        self::insertAudit('approve', $imageId, $moderatorId, null);
    }

    public static function decline(int $imageId, int $moderatorId, string $reason): void {
        DB::getInstance()->query("
            UPDATE agallery_images
            SET status='declined', decline_reason=?, moderated_by=?, moderated_at=?, updated_at=?
            WHERE id=?
        ", [$reason, $moderatorId, time(), time(), $imageId]);

        self::insertAudit('decline', $imageId, $moderatorId, null);
    }

    public static function updateApproved(int $imageId, int $categoryId, string $title, string $description): void {
        DB::getInstance()->query("
            UPDATE agallery_images
            SET category_id=?, title=?, description=?, updated_at=?
            WHERE id=? AND status='approved'
        ", [$categoryId, $title, $description, time(), $imageId]);
    }

    public static function deleteImage(int $imageId): void {
        $img = self::getImage($imageId);
        if (!$img) {
            return;
        }

        // Delete files
        $paths = [
            ROOT_PATH . '/' . ltrim($img->file_path, '/'),
            ROOT_PATH . '/' . ltrim($img->thumb_path, '/'),
        ];

        foreach ($paths as $p) {
            if (is_file($p)) {
                @unlink($p);
            }
        }

        DB::getInstance()->query("DELETE FROM agallery_images WHERE id=?", [$imageId]);
    }

    /**
     * Find user IDs that have a permission node.
     * We try group permissions LIKE first, then fallback to checking users.
     */
    public static function usersWithPermission(string $permission): array {
        $users = [];

        // 1) Try group permissions text match
        $groups = DB::getInstance()->query("SELECT id FROM nl2_groups WHERE deleted=0 AND permissions LIKE ?", ['%' . $permission . '%'])->results();
        if (count($groups)) {
            $gids = array_map(static fn($r) => (int)$r->id, $groups);
            $in = implode(',', array_fill(0, count($gids), '?'));
            $rows = DB::getInstance()->query("SELECT DISTINCT user_id FROM nl2_users_groups WHERE group_id IN ($in)", $gids)->results();
            foreach ($rows as $r) {
                $users[] = (int)$r->user_id;
            }
            return array_values(array_unique($users));
        }

        // 2) Fallback: check users in nl2_users_groups
        $rows = DB::getInstance()->query("SELECT DISTINCT user_id FROM nl2_users_groups")->results();
        foreach ($rows as $r) {
            $uid = (int)$r->user_id;
            $u = new User($uid);
            if ($u->exists() && $u->hasPermission($permission)) {
                $users[] = $uid;
            }
        }

        return array_values(array_unique($users));
    }
}
