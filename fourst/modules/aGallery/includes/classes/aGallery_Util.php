<?php

class aGallery_Util {

    public static function cleanText(?string $s): string {
        $s = (string)$s;
        try {
            if (class_exists('Output') && method_exists('Output', 'getClean')) {
                $s = (string)Output::getClean($s);
            } else {
                $s = strip_tags($s);
                $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        } catch (Throwable $e) {
            $s = strip_tags($s);
        }
        return trim($s);
    }

    public static function slugUsername(string $username): string {
        $u = strtolower($username);
        $u = preg_replace('/[^a-z0-9_-]+/i', '_', $u);
        $u = preg_replace('/_+/', '_', $u);
        $u = trim($u, '_');
        if ($u === '') $u = 'user';
        return $u;
    }

    public static function parseIntArrayFromJson(?string $json): array {
        if ($json === null || trim($json) === '') return [];
        $v = json_decode($json, true);
        if (!is_array($v)) return [];
        $out = [];
        foreach ($v as $x) {
            $i = (int)$x;
            if ($i > 0) $out[] = $i;
        }
        return array_values(array_unique($out));
    }

    public static function intArrayToJson(array $arr): string {
        $out = [];
        foreach ($arr as $x) {
            $i = (int)$x;
            if ($i > 0) $out[] = $i;
        }
        $out = array_values(array_unique($out));
        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    public static function intersects(array $a, array $b): bool {
        if (!count($a) || !count($b)) return false;
        $set = array_flip($a);
        foreach ($b as $x) {
            $x = (int)$x;
            if ($x > 0 && isset($set[$x])) return true;
        }
        return false;
    }

    public static function iniBytes(string $val): int {
        $val = trim($val);
        if ($val === '') return 0;
        $last = strtolower($val[strlen($val)-1]);
        $num = (int)$val;
        switch ($last) {
            case 'g': return $num * 1024 * 1024 * 1024;
            case 'm': return $num * 1024 * 1024;
            case 'k': return $num * 1024;
            default: return (int)$val;
        }
    }

    public static function formatBytes(int $bytes): string {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }

    public static function now(): string {
        return date('Y-m-d H:i:s');
    }
}
