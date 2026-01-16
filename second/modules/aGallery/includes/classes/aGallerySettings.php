<?php

class aGallerySettings {

    private aGalleryRepository $repo;

    public function __construct(aGalleryRepository $repo) {
        $this->repo = $repo;
    }

    public function get(string $name, $default = null) {
        $row = $this->repo->db()->query("SELECT value FROM `{$this->repo->t('agallery_settings')}` WHERE name = ?", [$name])->first();
        if (!$row) return $default;

        $val = $row->value;

        // attempt json decode
        $decoded = json_decode($val, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $val;
    }

    public function set(string $name, $value): void {
        $stored = is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE);

        $exists = $this->repo->db()->query("SELECT name FROM `{$this->repo->t('agallery_settings')}` WHERE name = ?", [$name])->first();
        if ($exists) {
            $this->repo->db()->query(
                "UPDATE `{$this->repo->t('agallery_settings')}` SET value = ?, updated_at = ? WHERE name = ?",
                [$stored, $this->repo->now(), $name]
            );
        } else {
            $this->repo->db()->insert($this->repo->t('agallery_settings'), [
                'name' => $name,
                'value' => $stored,
                'updated_at' => $this->repo->now(),
            ]);
        }
    }

    public function ensureDefaults(): void {
        $defaults = [
            'max_upload_mb' => 50,
            'max_width' => 1920,
            'max_height' => 1080,
            'allowed_extensions' => ['png','jpg','jpeg','webp','gif'],
            'image_quality_jpeg' => 82,
            'image_quality_webp' => 80,
            'thumb_width' => 480,
            'allow_conversion' => 0,
            'convert_to' => 'jpg', // jpg|webp
        ];

        foreach ($defaults as $k => $v) {
            $exists = $this->repo->db()->query("SELECT name FROM `{$this->repo->t('agallery_settings')}` WHERE name = ?", [$k])->first();
            if (!$exists) $this->set($k, $v);
        }
    }
}