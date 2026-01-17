<?php

class aGallery_Utils {

    public static function now(): string {
        return date('Y-m-d H:i:s');
    }

    public static function parseBytes(string $val): int {
        $val = trim($val);
        if ($val === '') return 0;
        $last = strtolower($val[strlen($val) - 1]);
        $num = (int)$val;
        switch ($last) {
            case 'g': $num *= 1024;
            case 'm': $num *= 1024;
            case 'k': $num *= 1024;
        }
        return $num;
    }

    public static function slugUsername(string $username): string {
        $u = strtolower($username);
        $u = preg_replace('/[^a-z0-9_-]+/i', '_', $u);
        $u = trim($u, '_');
        if ($u === '') $u = 'user';
        return substr($u, 0, 32);
    }

    public static function cleanText(?string $text, int $maxLen, bool $allowNewLines = true): string {
        $text = (string)$text;
        $text = str_replace("\0", '', $text);
        $text = trim($text);
        $text = strip_tags($text);
        if (!$allowNewLines) {
            $text = str_replace(["\r", "\n"], ' ', $text);
        } else {
            $text = str_replace(["\r\n", "\r"], "\n", $text);
        }
        if (mb_strlen($text) > $maxLen) {
            $text = mb_substr($text, 0, $maxLen);
        }
        return $text;
    }

    public static function isAnimatedGif(string $path): bool {
        // Simple heuristic: multiple graphic control extensions
        $fh = @fopen($path, 'rb');
        if (!$fh) return false;
        $count = 0;
        while (!feof($fh) && $count < 2) {
            $chunk = fread($fh, 1024 * 64);
            if ($chunk === false) break;
            $count += substr_count($chunk, "\x21\xF9\x04");
        }
        fclose($fh);
        return $count > 1;
    }

    public static function ensureDir(string $absPath): bool {
        if (is_dir($absPath)) {
            return true;
        }
        return @mkdir($absPath, 0755, true);
    }

    public static function safeWriteFile(string $absPath, string $contents): void {
        if (!is_file($absPath)) {
            @file_put_contents($absPath, $contents);
        }
    }

    public static function arrayFromJson(?string $json): array {
        if ($json === null || $json === '') return [];
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) return [];
        return array_values(array_filter(array_map('intval', $decoded), static fn($v) => $v > 0));
    }

    public static function jsonFromArray(array $ids): string {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($v) => $v > 0)));
        return json_encode($ids, JSON_UNESCAPED_UNICODE);
    }

    public static function intersect(array $a, array $b): bool {
        $set = array_flip($a);
        foreach ($b as $x) {
            if (isset($set[$x])) return true;
        }
        return false;
    }

    public static function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
