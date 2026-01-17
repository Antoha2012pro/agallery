<?php

class AGallerySettings {

    private const CACHE_NAME = 'agallery_settings';

    public static function installDefaults(): void {
        $defaults = [
            'max_upload_mb' => 50,
            'max_width' => 1920,
            'max_height' => 1080,
            'allowed_extensions' => 'png,jpg,jpeg,webp',
            'image_quality_jpeg' => 82,
            'image_quality_webp' => 80,
            'thumb_width' => 480,
            'allow_convert' => 1,
        ];

        $cache = new Cache();
        $cache->setCache(self::CACHE_NAME);
        foreach ($defaults as $key => $value) {
            $cache->store($key, $value);
        }
    }

    public static function removeAll(): void {
        $cache = new Cache();
        $cache->setCache(self::CACHE_NAME);
        $cache->clearCache();
    }

    public static function get(string $key, $default = null) {
        $cache = new Cache();
        $cache->setCache(self::CACHE_NAME);
        if ($cache->isCached($key)) {
            return $cache->retrieve($key);
        }
        return $default;
    }

    public static function set(string $key, $value): void {
        $cache = new Cache();
        $cache->setCache(self::CACHE_NAME);
        $cache->store($key, $value);
    }

    public static function all(): array {
        $keys = [
            'max_upload_mb',
            'max_width',
            'max_height',
            'allowed_extensions',
            'image_quality_jpeg',
            'image_quality_webp',
            'thumb_width',
            'allow_convert',
        ];
        $result = [];
        foreach ($keys as $k) {
            $result[$k] = self::get($k);
        }
        return $result;
    }
}
