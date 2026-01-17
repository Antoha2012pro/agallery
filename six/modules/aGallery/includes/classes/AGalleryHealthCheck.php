<?php

class AGalleryHealthCheck {

    public static function getPathsInfo(): array {
        $base = ROOT_PATH . '/uploads/agallery';
        $year = date('Y');
        $month = date('m');

        $paths = [
            'base' => $base,
            'ym' => $base . '/' . $year . '/' . $month,
            'thumbs' => $base . '/' . $year . '/' . $month . '/thumbs',
        ];

        $info = [];
        foreach ($paths as $key => $path) {
            $exists = file_exists($path);
            if (!$exists) {
                $created = @mkdir($path, 0755, true);
                $exists = $created || file_exists($path);
            }
            $writable = $exists ? is_writable($path) : false;
            $info[$key] = [
                'path' => $path,
                'exists' => $exists,
                'writable' => $writable,
            ];
        }

        return $info;
    }

    public static function getPhpLimits(): array {
        return [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
        ];
    }

    public static function compareWithModuleLimit(): array {
        $maxUploadMb = (int)AGallerySettings::get('max_upload_mb', 50);
        $phpUpload = self::toMb(ini_get('upload_max_filesize'));
        $phpPost = self::toMb(ini_get('post_max_size'));
        $phpMemory = self::toMb(ini_get('memory_limit'));

        $warnings = [];

        if ($phpUpload > 0 && $phpUpload < $maxUploadMb) {
            $warnings[] = 'upload_max_filesize';
        }
        if ($phpPost > 0 && $phpPost < $maxUploadMb) {
            $warnings[] = 'post_max_size';
        }
        if ($phpMemory > 0 && $phpMemory < $maxUploadMb) {
            $warnings[] = 'memory_limit';
        }

        return [
            'module_limit' => $maxUploadMb,
            'php_upload' => $phpUpload,
            'php_post' => $phpPost,
            'php_memory' => $phpMemory,
            'warnings' => $warnings,
        ];
    }

    private static function toMb(string $val): int {
        $val = trim($val);
        $last = strtolower(substr($val, -1));
        $num = (int)$val;
        switch ($last) {
            case 'g':
                $num *= 1024;
                break;
            case 'k':
                $num = (int)($num / 1024);
                break;
        }
        return $num;
    }
}
