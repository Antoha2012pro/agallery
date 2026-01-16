<?php
if (!defined('ROOT_PATH')) {
    die('Direct access not permitted');
}

class ImageProcessor {

    public static function gdSupportsWebp(): bool {
        return function_exists('imagecreatefromwebp') && function_exists('imagewebp');
    }

    public static function gdSupportsJpeg(): bool {
        return function_exists('imagecreatefromjpeg') && function_exists('imagejpeg');
    }

    public static function gdSupportsPng(): bool {
        return function_exists('imagecreatefrompng') && function_exists('imagepng');
    }

    public static function gdSupportsGif(): bool {
        return function_exists('imagecreatefromgif') && function_exists('imagegif');
    }

    public static function detectAndLoad(string $path, int $imageType) {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                if (!self::gdSupportsJpeg()) return null;
                return imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                if (!self::gdSupportsPng()) return null;
                $im = imagecreatefrompng($path);
                if ($im) {
                    imagealphablending($im, true);
                    imagesavealpha($im, true);
                }
                return $im;
            case IMAGETYPE_WEBP:
                if (!self::gdSupportsWebp()) return null;
                return imagecreatefromwebp($path);
            case IMAGETYPE_GIF:
                if (!self::gdSupportsGif()) return null;
                return imagecreatefromgif($path);
            default:
                return null;
        }
    }

    public static function save($im, string $destPath, string $ext, int $jpegQuality, int $webpQuality): bool {
        $ext = strtolower($ext);

        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                if (!self::gdSupportsJpeg()) return false;
                return imagejpeg($im, $destPath, $jpegQuality);
            case 'png':
                if (!self::gdSupportsPng()) return false;
                // 0..9 (9 = max compression)
                return imagepng($im, $destPath, 6);
            case 'webp':
                if (!self::gdSupportsWebp()) return false;
                return imagewebp($im, $destPath, $webpQuality);
            case 'gif':
                if (!self::gdSupportsGif()) return false;
                // NOTE: will be single frame (animation lost)
                return imagegif($im, $destPath);
            default:
                return false;
        }
    }

    public static function resampleToFit($src, int $srcW, int $srcH, int $maxW, int $maxH) {
        $scale = min($maxW / $srcW, $maxH / $srcH, 1);
        $dstW = (int)floor($srcW * $scale);
        $dstH = (int)floor($srcH * $scale);

        $dst = imagecreatetruecolor($dstW, $dstH);

        // Preserve alpha for PNG/WebP
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        return [$dst, $dstW, $dstH];
    }

    public static function resampleWidth($src, int $srcW, int $srcH, int $targetW) {
        $scale = min($targetW / $srcW, 1);
        $dstW = (int)floor($srcW * $scale);
        $dstH = (int)floor($srcH * $scale);

        $dst = imagecreatetruecolor($dstW, $dstH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        return [$dst, $dstW, $dstH];
    }
}
