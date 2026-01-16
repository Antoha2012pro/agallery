<?php

class aGalleryRepository {

    public function prefix(): string {
        return defined('TABLE_PREFIX') ? TABLE_PREFIX : 'nl2_';
    }

    public function t(string $table): string {
        // table without prefix -> with prefix
        return $this->prefix() . $table;
    }

    public function db(): DB {
        return DB::getInstance();
    }

    public function now(): int {
        return (int) date('U');
    }

    public function installSchema(): void {
        $p = $this->prefix();

        // categories
        $this->db()->query("
            CREATE TABLE IF NOT EXISTS `{$p}agallery_categories` (
              `id` INT(11) NOT NULL AUTO_INCREMENT,
              `name` VARCHAR(64) NOT NULL,
              `description` TEXT NULL,
              `sort_order` INT(11) NOT NULL DEFAULT 0,
              `view_groups` TEXT NOT NULL,
              `upload_groups` TEXT NOT NULL,
              `created_at` INT(11) NOT NULL,
              `updated_at` INT(11) NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // images
        $this->db()->query("
            CREATE TABLE IF NOT EXISTS `{$p}agallery_images` (
              `id` INT(11) NOT NULL AUTO_INCREMENT,
              `category_id` INT(11) NOT NULL,
              `user_id` INT(11) NOT NULL,
              `title` VARCHAR(128) NOT NULL,
              `description` TEXT NULL,
              `file_path` VARCHAR(255) NOT NULL,
              `thumb_path` VARCHAR(255) NOT NULL,
              `mime` VARCHAR(64) NOT NULL,
              `ext` VARCHAR(16) NOT NULL,
              `width` INT(11) NOT NULL,
              `height` INT(11) NOT NULL,
              `file_size` BIGINT NOT NULL,
              `status` VARCHAR(16) NOT NULL,
              `decline_reason` TEXT NULL,
              `moderated_by` INT(11) NULL,
              `moderated_at` INT(11) NULL,
              `created_at` INT(11) NOT NULL,
              `updated_at` INT(11) NOT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_status` (`status`),
              KEY `idx_category` (`category_id`),
              KEY `idx_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // audit log
        $this->db()->query("
            CREATE TABLE IF NOT EXISTS `{$p}agallery_audit_log` (
              `id` INT(11) NOT NULL AUTO_INCREMENT,
              `action` VARCHAR(32) NOT NULL,
              `image_id` INT(11) NULL,
              `actor_id` INT(11) NULL,
              `details` TEXT NULL,
              `created_at` INT(11) NOT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_image` (`image_id`),
              KEY `idx_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // settings
        $this->db()->query("
            CREATE TABLE IF NOT EXISTS `{$p}agallery_settings` (
              `name` VARCHAR(64) NOT NULL,
              `value` TEXT NOT NULL,
              `updated_at` INT(11) NOT NULL,
              PRIMARY KEY (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    public function log(?int $actor_id, ?int $image_id, string $action, array $details = []): void {
        $this->db()->insert($this->t('agallery_audit_log'), [
            'action' => $action,
            'image_id' => $image_id,
            'actor_id' => $actor_id,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'created_at' => $this->now(),
        ]);
    }

    // ----- Groups / permissions helpers -----

    public function allGroups(): array {
        $res = $this->db()->query("SELECT id, name FROM `{$this->t('groups')}` WHERE deleted = 0 ORDER BY `order` ASC")->results();
        return $res ?: [];
    }

    public function userGroupIds(int $user_id): array {
        $now = $this->now();
        $rows = $this->db()->query(
            "SELECT group_id FROM `{$this->t('users_groups')}` WHERE user_id = ? AND (expire = 0 OR expire > ?)",
            [$user_id, $now]
        )->results();

        $out = [];
        foreach ($rows ?: [] as $r) {
            $out[] = (int) $r->group_id;
        }
        return array_values(array_unique($out));
    }

    public function categoryById(int $id) {
        return $this->db()->query("SELECT * FROM `{$this->t('agallery_categories')}` WHERE id = ?", [$id])->first();
    }

    public function categoriesAll(): array {
        return $this->db()->query("SELECT * FROM `{$this->t('agallery_categories')}` ORDER BY sort_order ASC, id ASC")->results() ?: [];
    }

    public function categoryGroups(string $json_or_text): array {
        $arr = json_decode($json_or_text, true);
        if (!is_array($arr)) return [];
        return array_map('intval', $arr);
    }

    public function userInAllowedGroups(array $user_groups, array $allowed_groups): bool {
        // Fail closed: if no allowed groups, deny.
        if (!count($allowed_groups)) return false;
        return (bool) array_intersect($user_groups, $allowed_groups);
    }

    public function canViewCategory(int $user_id, $category): bool {
        $user_groups = $this->userGroupIds($user_id);
        $view_groups = $this->categoryGroups($category->view_groups);
        return $this->userInAllowedGroups($user_groups, $view_groups);
    }

    public function canUploadCategory(int $user_id, $category): bool {
        $user_groups = $this->userGroupIds($user_id);
        $upload_groups = $this->categoryGroups($category->upload_groups);
        return $this->userInAllowedGroups($user_groups, $upload_groups);
    }

    public function categoriesViewable(int $user_id): array {
        $cats = $this->categoriesAll();
        $out = [];
        foreach ($cats as $c) {
            if ($this->canViewCategory($user_id, $c)) $out[] = $c;
        }
        return $out;
    }

    public function categoriesUploadable(int $user_id): array {
        $cats = $this->categoriesAll();
        $out = [];
        foreach ($cats as $c) {
            if ($this->canUploadCategory($user_id, $c)) $out[] = $c;
        }
        return $out;
    }

    // ----- Moderators list -----

    public function moderatorUserIds(): array {
        // Robust approach: search substring in groups.permissions field
        $groups = $this->db()->query(
            "SELECT id FROM `{$this->t('groups')}` WHERE deleted = 0 AND permissions LIKE ?",
            ['%agallery.moderate%']
        )->results();

        if (!$groups) return [];

        $group_ids = array_map(fn($g) => (int)$g->id, $groups);
        $placeholders = implode(',', array_fill(0, count($group_ids), '?'));

        $now = $this->now();
        $rows = $this->db()->query(
            "SELECT DISTINCT user_id FROM `{$this->t('users_groups')}` WHERE group_id IN ($placeholders) AND (expire = 0 OR expire > ?)",
            array_merge($group_ids, [$now])
        )->results();

        $out = [];
        foreach ($rows ?: [] as $r) $out[] = (int)$r->user_id;
        return array_values(array_unique($out));
    }
}