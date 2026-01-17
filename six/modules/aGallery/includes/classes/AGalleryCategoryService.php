<?php

class AGalleryCategoryService {

    public static function getAllForView(User $user): array {
        $db = DB::getInstance();
        $categories = $db->query('SELECT * FROM agallery_categories ORDER BY sort_order ASC, name ASC', [])->results();

        $result = [];
        foreach ($categories as $cat) {
            if (self::canViewCategory($user, $cat)) {
                $result[] = $cat;
            }
        }

        return $result;
    }

    public static function getAll(): array {
        return DB::getInstance()->query('SELECT * FROM agallery_categories ORDER BY sort_order ASC, name ASC', [])->results();
    }

    public static function getById(int $id) {
        return DB::getInstance()->query('SELECT * FROM agallery_categories WHERE id = ?', [$id])->first();
    }

    public static function create(array $data): int {
        $now = time();
        DB::getInstance()->insert('agallery_categories', [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'view_groups' => $data['view_groups'] ?? null,
            'upload_groups' => $data['upload_groups'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return DB::getInstance()->lastId();
    }

    public static function update(int $id, array $data): void {
        $now = time();
        DB::getInstance()->update('agallery_categories', $id, [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'view_groups' => $data['view_groups'] ?? null,
            'upload_groups' => $data['upload_groups'] ?? null,
            'updated_at' => $now,
        ]);
    }

    public static function delete(int $id): void {
        DB::getInstance()->delete('agallery_categories', ['id', '=', $id]);
    }

    public static function canViewCategory(User $user, $category): bool {
        $json = $category->view_groups ?? null;
        if ($json === null || $json === '') {
            return true;
        }
        $groups = json_decode($json, true);
        if (!is_array($groups) || !count($groups)) {
            return true;
        }

        foreach ($user->getAllGroups() as $group) {
            if (in_array((int)$group->id, $groups, true)) {
                return true;
            }
        }

        return false;
    }

    public static function canUploadToCategory(User $user, $category): bool {
        // Requires permission agallery.upload + membership in upload_groups.
        if (!$user->hasPermission('agallery.upload')) {
            return false;
        }
        $json = $category->upload_groups ?? null;
        if ($json === null || $json === '') {
            return false;
        }
        $groups = json_decode($json, true);
        if (!is_array($groups) || !count($groups)) {
            return false;
        }
        foreach ($user->getAllGroups() as $group) {
            if (in_array((int)$group->id, $groups, true)) {
                return true;
            }
        }
        return false;
    }
}
