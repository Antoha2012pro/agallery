<?php

class aGallery {

    private static function db(): DB {
        return DB::getInstance();
    }

    public static function installOrUpdateSchema(): void {
        $db = self::db();

        // Create tables using DB::createTable (name without prefix) if available.
        if (method_exists($db, 'createTable')) {
            $db->createTable('agallery_categories', "
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(64) NOT NULL,
                `description` varchar(255) DEFAULT NULL,
                `sort_order` int(11) NOT NULL DEFAULT 0,
                `view_groups` text DEFAULT NULL,
                `upload_groups` text DEFAULT NULL,
                `created_at` datetime NOT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `sort_order` (`sort_order`)
            ");

            $db->createTable('agallery_images', "
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `category_id` int(11) NOT NULL,
                `user_id` int(11) NOT NULL,
                `title` varchar(80) NOT NULL,
                `description` varchar(600) DEFAULT NULL,
                `file_path` varchar(255) NOT NULL,
                `thumb_path` varchar(255) NOT NULL,
                `mime` varchar(64) NOT NULL,
                `ext` varchar(10) NOT NULL,
                `width` int(11) NOT NULL,
                `height` int(11) NOT NULL,
                `file_size` bigint(20) NOT NULL,
                `status` enum('pending','approved','declined') NOT NULL DEFAULT 'pending',
                `decline_reason` varchar(255) DEFAULT NULL,
                `moderated_by` int(11) DEFAULT NULL,
                `moderated_at` datetime DEFAULT NULL,
                `created_at` datetime NOT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `status` (`status`),
                KEY `category_id` (`category_id`),
                KEY `created_at` (`created_at`)
            ");

            $db->createTable('agallery_audit_log', "
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `action` varchar(32) NOT NULL,
                `image_id` int(11) DEFAULT NULL,
                `actor_id` int(11) DEFAULT NULL,
                `details` text DEFAULT NULL,
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `action` (`action`),
                KEY `image_id` (`image_id`),
                KEY `created_at` (`created_at`)
            ");

            $db->createTable('agallery_settings', "
                `key` varchar(64) NOT NULL,
                `value` text NOT NULL,
                PRIMARY KEY (`key`)
            ");
        } else {
            // Fallback: raw queries (rare)
            $db->query("CREATE TABLE IF NOT EXISTS `agallery_settings` (`key` varchar(64) NOT NULL, `value` text NOT NULL, PRIMARY KEY (`key`))");
        }

        // Defaults
        self::setSettingIfMissing('max_upload_mb', '50');
        self::setSettingIfMissing('max_width', '1920');
        self::setSettingIfMissing('max_height', '1080');
        self::setSettingIfMissing('allowed_extensions', 'png,jpg,jpeg,webp,gif');
        self::setSettingIfMissing('image_quality_jpeg', '82');
        self::setSettingIfMissing('image_quality_webp', '80');
        self::setSettingIfMissing('thumb_width', '480');
        self::setSettingIfMissing('allow_convert', '0');
        self::setSettingIfMissing('per_page', '24');
    }

    public static function ensureUploadDirs(): array {
        $warnings = [];

        $base = ROOT_PATH . '/uploads/agallery';
        $year = date('Y');
        $month = date('m');

        $paths = [
            $base,
            "$base/$year",
            "$base/$year/$month",
            "$base/$year/$month/thumbs",
        ];

        foreach ($paths as $p) {
            if (!aGallery_Utils::ensureDir($p)) {
                $warnings[] = $p;
            }
        }

        // Protection files in uploads (allowed)
        aGallery_Utils::safeWriteFile($base . '/index.html', '<!-- aGallery -->');
        aGallery_Utils::safeWriteFile($base . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar)$\">\n  Deny from all\n</FilesMatch>\n");

        return $warnings;
    }

    private static function setSettingIfMissing(string $key, string $value): void {
        $db = self::db();
        $existing = $db->get('agallery_settings', ['key', '=', $key]);
        if ($existing && $existing->count() > 0) {
            return;
        }
        $db->insert('agallery_settings', [
            'key' => $key,
            'value' => $value,
        ]);
    }

    public static function getSetting(string $key, $default = null) {
        $db = self::db();
        $row = $db->get('agallery_settings', ['key', '=', $key]);
        if ($row && $row->count() > 0) {
            return $row->first()->value;
        }
        return $default;
    }

    public static function setSetting(string $key, string $value): void {
        $db = self::db();
        $row = $db->get('agallery_settings', ['key', '=', $key]);
        if ($row && $row->count() > 0) {
            $db->update('agallery_settings', $key, ['value' => $value], 'key');
        } else {
            $db->insert('agallery_settings', ['key' => $key, 'value' => $value]);
        }
    }

    public static function getCategories(): array {
        $db = self::db();
        $q = $db->query("SELECT * FROM `" . $db->getPrefix() . "agallery_categories` ORDER BY `sort_order` ASC, `id` ASC");
        // If getPrefix() doesn't exist, fallback to get()
        if (!$q || $q->error()) {
            $rows = $db->get('agallery_categories', ['id', '<>', 0]);
            return $rows ? $rows->results() : [];
        }
        return $q->results();
    }

    public static function getCategory(int $id) {
        $db = self::db();
        $row = $db->get('agallery_categories', ['id', '=', $id]);
        if ($row && $row->count() > 0) return $row->first();
        return null;
    }

    public static function createCategory(string $name, ?string $desc, int $sort, array $viewGroups, array $uploadGroups): void {
        self::db()->insert('agallery_categories', [
            'name' => $name,
            'description' => $desc,
            'sort_order' => $sort,
            'view_groups' => aGallery_Utils::jsonFromArray($viewGroups),
            'upload_groups' => aGallery_Utils::jsonFromArray($uploadGroups),
            'created_at' => aGallery_Utils::now(),
            'updated_at' => aGallery_Utils::now(),
        ]);
    }

    public static function updateCategory(int $id, string $name, ?string $desc, int $sort, array $viewGroups, array $uploadGroups): void {
        self::db()->update('agallery_categories', $id, [
            'name' => $name,
            'description' => $desc,
            'sort_order' => $sort,
            'view_groups' => aGallery_Utils::jsonFromArray($viewGroups),
            'upload_groups' => aGallery_Utils::jsonFromArray($uploadGroups),
            'updated_at' => aGallery_Utils::now(),
        ]);
    }

    public static function deleteCategory(int $id): bool {
        // block delete if images exist
        $db = self::db();
        $count = $db->get('agallery_images', ['category_id', '=', $id]);
        if ($count && $count->count() > 0) return false;
        $db->delete('agallery_categories', ['id', '=', $id]);
        return true;
    }

    public static function userGroupIds(User $user): array {
        // User::getAllGroupIds exists :contentReference[oaicite:7]{index=7}
        if (method_exists($user, 'getAllGroupIds')) {
            $ids = $user->getAllGroupIds();
            if (is_array($ids)) return array_values(array_map('intval', $ids));
        }
        return [];
    }

    public static function canViewCategory(User $user, $category): bool {
        $view = aGallery_Utils::arrayFromJson($category->view_groups ?? null);
        if (count($view) === 0) return true; // доступ всем
        $userGroups = self::userGroupIds($user);
        return aGallery_Utils::intersect($userGroups, $view);
    }

    public static function canUploadToCategory(User $user, $category): bool {
        if (!$user->isLoggedIn()) return false;
        if (!method_exists($user, 'hasPermission') || !$user->hasPermission('agallery.upload')) {
            return false;
        }
        $upload = aGallery_Utils::arrayFromJson($category->upload_groups ?? null);
        if (count($upload) === 0) return false; // строго: должен входить в список upload-групп
        $userGroups = self::userGroupIds($user);
        return aGallery_Utils::intersect($userGroups, $upload);
    }

    public static function addAudit(string $action, ?int $imageId, ?int $actorId, array $details = []): void {
        self::db()->insert('agallery_audit_log', [
            'action' => $action,
            'image_id' => $imageId,
            'actor_id' => $actorId,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'created_at' => aGallery_Utils::now(),
        ]);
    }

    public static function createPendingImage(int $categoryId, int $userId, string $title, ?string $desc, array $fileMeta): int {
        self::db()->insert('agallery_images', [
            'category_id' => $categoryId,
            'user_id' => $userId,
            'title' => $title,
            'description' => $desc,
            'file_path' => $fileMeta['file_path'],
            'thumb_path' => $fileMeta['thumb_path'],
            'mime' => $fileMeta['mime'],
            'ext' => $fileMeta['ext'],
            'width' => $fileMeta['width'],
            'height' => $fileMeta['height'],
            'file_size' => $fileMeta['file_size'],
            'status' => 'pending',
            'created_at' => aGallery_Utils::now(),
            'updated_at' => aGallery_Utils::now(),
        ]);

        $id = (int)self::db()->lastId();
        self::addAudit('upload_pending', $id, $userId, ['category_id' => $categoryId]);
        return $id;
    }

    public static function getImage(int $id) {
        $row = self::db()->get('agallery_images', ['id', '=', $id]);
        if ($row && $row->count() > 0) return $row->first();
        return null;
    }

    public static function updateImage(int $id, array $data): void {
        $data['updated_at'] = aGallery_Utils::now();
        self::db()->update('agallery_images', $id, $data);
    }

    public static function deleteImage(int $id): bool {
        $img = self::getImage($id);
        if (!$img) return false;

        $abs1 = ROOT_PATH . '/' . ltrim($img->file_path, '/');
        $abs2 = ROOT_PATH . '/' . ltrim($img->thumb_path, '/');
        $ok = true;

        if (is_file($abs1) && !@unlink($abs1)) $ok = false;
        if (is_file($abs2) && !@unlink($abs2)) $ok = false;

        self::db()->delete('agallery_images', ['id', '=', $id]);
        self::addAudit('delete', $id, null, ['file_deleted' => $ok]);
        return $ok;
    }

    public static function getModeratorsUserIds(): array {
        // Safe compatibility: iterate all users and check hasPermission('agallery.moderate') :contentReference[oaicite:8]{index=8}
        $db = self::db();
        $ids = [];

        $users = $db->get('users', ['id', '<>', 0]);
        if (!$users) return [];

        foreach ($users->results() as $u) {
            $uid = (int)$u->id;
            try {
                $usr = new User($uid);
                if ($usr->hasPermission('agallery.moderate')) {
                    $ids[] = $uid;
                }
            } catch (Throwable $e) {
                // ignore
            }
        }

        return array_values(array_unique($ids));
    }

    public static function notifyModeratorsNewPending(int $imageId, int $authorId): void {
        $mods = self::getModeratorsUserIds();
        if (!count($mods)) return;

        $title = new LanguageKey('aGallery', 'notif_moderation_title', 'notifications');
        $content = new LanguageKey('aGallery', 'notif_moderation_content', 'notifications');
        $url = URL::build('/panel/agallery/moderation', 'view=' . $imageId);

        aGallery_Compat::notify('agallery_moderation', $title, $content, $mods, $authorId, $url, function($recipientId) use ($imageId) {
            // optional per-recipient content (not used when alertUrl is set)
            return null;
        });

        self::addAudit('notify_moderators', $imageId, $authorId, ['moderators' => $mods]);
    }

    public static function notifyAuthorApproved(int $imageId, int $authorId, int $moderatorId): void {
        $title = new LanguageKey('aGallery', 'notif_approved_title', 'notifications');
        $content = new LanguageKey('aGallery', 'notif_approved_content', 'notifications');
        $url = URL::build('/gallery');

        aGallery_Compat::notify('agallery_result', $title, $content, [$authorId], $moderatorId, $url);
        self::addAudit('notify_author_approved', $imageId, $moderatorId, ['author' => $authorId]);
    }

    public static function notifyAuthorDeclined(int $imageId, int $authorId, int $moderatorId, string $reason): void {
        $title = new LanguageKey('aGallery', 'notif_declined_title', 'notifications');
        $content = new LanguageKey('aGallery', 'notif_declined_content', 'notifications');

        aGallery_Compat::notify('agallery_result', $title, $content, [$authorId], $moderatorId, null, function($recipientId) use ($reason) {
            // Replace placeholder manually if LanguageKey not expanded by core
            return str_replace('{reason}', $reason, aGallery_Compat::lang('notif_declined_content', 'notifications', ['reason' => $reason]));
        });

        self::addAudit('notify_author_declined', $imageId, $moderatorId, ['author' => $authorId, 'reason' => $reason]);
    }
}
