<?php

class aGallery_Settings {

    private DB $_db;
    private array $_cache = [];

    public function __construct(DB $db) {
        $this->_db = $db;
        $this->load();
    }

    public static function install(DB $db): void {
        $t = aGallery_Compat::table('agallery_settings');

        $db->query("CREATE TABLE IF NOT EXISTS `{$t}` (
            `key` VARCHAR(64) NOT NULL PRIMARY KEY,
            `value` TEXT NULL,
            `updated_at` DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", []);

        $defaults = [
            'max_upload_mb' => '50',
            'max_width' => '1920',
            'max_height' => '1080',
            'allowed_extensions' => 'png,jpg,jpeg,webp',
            'image_quality_jpeg' => '82',
            'image_quality_webp' => '80',
            'thumb_width' => '480',
            'allow_convert' => '0',
            'title_max_len' => '64',
            'desc_max_len' => '500',
            'page_limit' => '24',
        ];

        foreach ($defaults as $k => $v) {
            $db->query("INSERT IGNORE INTO `{$t}` (`key`,`value`,`updated_at`) VALUES (?,?,?)", [$k, $v, aGallery_Util::now()]);
        }
    }

    private function load(): void {
        $t = aGallery_Compat::table('agallery_settings');
        try {
            $rows = $this->_db->query("SELECT `key`,`value` FROM `{$t}`", [])->results();
            foreach ($rows as $r) {
                $k = (string)($r->key ?? '');
                $v = (string)($r->value ?? '');
                if ($k !== '') $this->_cache[$k] = $v;
            }
        } catch (Throwable $e) {
            $this->_cache = [];
        }
    }

    public function get(string $key, ?string $default = null): ?string {
        return $this->_cache[$key] ?? $default;
    }

    public function getInt(string $key, int $default): int {
        $v = $this->get($key, (string)$default);
        return (int)$v;
    }

    public function getBool(string $key, bool $default): bool {
        $v = $this->get($key, $default ? '1' : '0');
        return $v === '1' || strtolower($v) === 'true';
    }

    public function set(string $key, string $value): void {
        $t = aGallery_Compat::table('agallery_settings');
        $this->_db->query("INSERT INTO `{$t}` (`key`,`value`,`updated_at`) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), `updated_at`=VALUES(`updated_at`)", [$key, $value, aGallery_Util::now()]);
        $this->_cache[$key] = $value;
    }

    public function maxUploadBytes(): int {
        return $this->getInt('max_upload_mb', 50) * 1024 * 1024;
    }
}
