<?php

class aGallery_Storage {

    public static function baseDirFs(): string {
        return ROOT_PATH . '/uploads/agallery';
    }

    public static function ensureBaseDirs(): void {
        $base = self::baseDirFs();
        self::ensureDir($base);
        self::writeIndexHtml($base);

        // Create year/month lazily on upload
    }

    public static function ensureDir(string $path): void {
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
    }

    public static function writeIndexHtml(string $dir): void {
        $f = rtrim($dir, '/') . '/index.html';
        if (!file_exists($f)) {
            @file_put_contents($f, "<!doctype html><html><head><meta charset=\"utf-8\"></head><body></body></html>");
        }
    }

    public static function monthDirs(string $ym): array {
        // $ym: YYYY/MM
        $base = self::baseDirFs();
        $dir = $base . '/' . $ym;
        $thumbs = $dir . '/thumbs';

        self::ensureDir($dir);
        self::ensureDir($thumbs);
        self::writeIndexHtml($dir);
        self::writeIndexHtml($thumbs);

        return [$dir, $thumbs];
    }

    public static function relPathFromFs(string $fsPath): string {
        // convert to web path (/uploads/...)
        $root = rtrim(ROOT_PATH, '/');
        $rel = str_replace($root, '', $fsPath);
        return $rel;
    }
}
