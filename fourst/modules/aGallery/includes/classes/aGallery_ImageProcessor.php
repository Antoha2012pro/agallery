<?php

class aGallery_ImageProcessor {

    public static function canDecode(string $mime): bool {
        // decode support (Imagick supports more, GD limited)
        if (extension_loaded('imagick')) return true;

        switch ($mime) {
            case 'image/jpeg':
                return function_exists('imagecreatefromjpeg');
            case 'image/png':
                return function_exists('imagecreatefrompng');
            case 'image/webp':
                return function_exists('imagecreatefromwebp');
            case 'image/gif':
                return function_exists('imagecreatefromgif');
            default:
                return false;
        }
    }

    public static function canEncode(string $mime): bool {
        if (extension_loaded('imagick')) return true;

        switch ($mime) {
            case 'image/jpeg':
                return function_exists('imagejpeg');
            case 'image/png':
                return function_exists('imagepng');
            case 'image/webp':
                return function_exists('imagewebp');
            case 'image/gif':
                // GD can encode GIF, but animation is lost anyway.
                return function_exists('imagegif');
            default:
                return false;
        }
    }

    public static function isAnimatedGif(string $file): bool {
        $fh = @fopen($file, 'rb');
        if (!$fh) return false;
        $count = 0;
        while (!feof($fh) && $count < 2) {
            $chunk = fread($fh, 1024 * 100);
            if ($chunk === false) break;
            $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00\x2C#s', $chunk, $m);
        }
        fclose($fh);
        return $count > 1;
    }

    /**
     * Process upload and save normalized + thumb.
     * Returns array: [mime, ext, width, height, file_size]
     */
    public static function process(
        aGallery_Settings $settings,
        string $tmpFile,
        string $srcMime,
        string $srcExt,
        string $outFs,
        string $thumbFs
    ): array {
        $maxW = $settings->getInt('max_width', 1920);
        $maxH = $settings->getInt('max_height', 1080);
        $thumbW = $settings->getInt('thumb_width', 480);

        $allowConvert = $settings->getBool('allow_convert', false);
        $qJpeg = $settings->getInt('image_quality_jpeg', 82);
        $qWebp = $settings->getInt('image_quality_webp', 80);

        // GIF animation rule
        if ($srcMime === 'image/gif' && self::isAnimatedGif($tmpFile)) {
            if (!$allowConvert) {
                throw new Exception('animated_gif_not_allowed');
            }
            // convert to webp if possible else jpeg
            if (self::canEncode('image/webp')) {
                $srcMime = 'image/webp';
                $srcExt = 'webp';
                $outFs = preg_replace('/\.[a-z0-9]+$/i', '.webp', $outFs);
                $thumbFs = preg_replace('/\.[a-z0-9]+$/i', '.webp', $thumbFs);
            } else {
                $srcMime = 'image/jpeg';
                $srcExt = 'jpg';
                $outFs = preg_replace('/\.[a-z0-9]+$/i', '.jpg', $outFs);
                $thumbFs = preg_replace('/\.[a-z0-9]+$/i', '.jpg', $thumbFs);
            }
        }

        if (extension_loaded('imagick')) {
            $im = new Imagick();
            $im->readImage($tmpFile);

            // Strip metadata
            $im->stripImage();

            // Handle orientation for jpeg if present
            try {
                if (method_exists($im, 'autoOrient')) {
                    $im->autoOrient();
                } elseif (method_exists($im, 'setImageOrientation')) {
                    $im->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
                }
            } catch (Throwable $e) {
                // ignore
            }

            $w = $im->getImageWidth();
            $h = $im->getImageHeight();

            $scale = min($maxW / max(1, $w), $maxH / max(1, $h), 1);
            if ($scale < 1) {
                $nw = (int)floor($w * $scale);
                $nh = (int)floor($h * $scale);
                $im->resizeImage($nw, $nh, Imagick::FILTER_LANCZOS, 1, true);
            }

            // Save normalized
            self::imagickWrite($im, $srcMime, $outFs, $qJpeg, $qWebp);

            // Thumb
            $tw = $im->getImageWidth();
            $th = $im->getImageHeight();
            $ts = min($thumbW / max(1, $tw), 1);
            $thumb = clone $im;
            if ($ts < 1) {
                $thumb->resizeImage((int)floor($tw * $ts), (int)floor($th * $ts), Imagick::FILTER_LANCZOS, 1, true);
            }
            self::imagickWrite($thumb, $srcMime, $thumbFs, $qJpeg, $qWebp);

            $finalW = $im->getImageWidth();
            $finalH = $im->getImageHeight();
            $size = (int)@filesize($outFs);

            $im->clear(); $im->destroy();
            $thumb->clear(); $thumb->destroy();

            return [$srcMime, $srcExt, $finalW, $finalH, $size];
        }

        // GD fallback
        $img = self::gdRead($tmpFile, $srcMime);
        if (!$img) throw new Exception('gd_decode_failed');

        $w = imagesx($img);
        $h = imagesy($img);

        $scale = min($maxW / max(1, $w), $maxH / max(1, $h), 1);
        $norm = $img;
        if ($scale < 1) {
            $nw = (int)floor($w * $scale);
            $nh = (int)floor($h * $scale);
            $norm = imagecreatetruecolor($nw, $nh);
            imagealphablending($norm, false);
            imagesavealpha($norm, true);
            imagecopyresampled($norm, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        }

        self::gdWrite($norm, $srcMime, $outFs, $qJpeg, $qWebp);

        // thumb
        $tw = imagesx($norm);
        $th = imagesy($norm);
        $ts = min($thumbW / max(1, $tw), 1);
        $thumb = $norm;
        if ($ts < 1) {
            $nw = (int)floor($tw * $ts);
            $nh = (int)floor($th * $ts);
            $thumb = imagecreatetruecolor($nw, $nh);
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            imagecopyresampled($thumb, $norm, 0, 0, 0, 0, $nw, $nh, $tw, $th);
        }
        self::gdWrite($thumb, $srcMime, $thumbFs, $qJpeg, $qWebp);

        $finalW = imagesx($norm);
        $finalH = imagesy($norm);
        $size = (int)@filesize($outFs);

        if ($norm !== $img) imagedestroy($norm);
        if ($thumb !== $norm) imagedestroy($thumb);
        imagedestroy($img);

        return [$srcMime, $srcExt, $finalW, $finalH, $size];
    }

    private static function imagickWrite(Imagick $im, string $mime, string $path, int $qJpeg, int $qWebp): void {
        if ($mime === 'image/jpeg') {
            $im->setImageFormat('jpeg');
            $im->setImageCompressionQuality($qJpeg);
        } elseif ($mime === 'image/webp') {
            $im->setImageFormat('webp');
            $im->setImageCompressionQuality($qWebp);
        } elseif ($mime === 'image/png') {
            $im->setImageFormat('png');
        } elseif ($mime === 'image/gif') {
            $im->setImageFormat('gif');
        } else {
            // default safe
            $im->setImageFormat('jpeg');
            $im->setImageCompressionQuality($qJpeg);
        }
        $im->writeImage($path);
    }

    private static function gdRead(string $tmp, string $mime) {
        switch ($mime) {
            case 'image/jpeg': return @imagecreatefromjpeg($tmp);
            case 'image/png':  return @imagecreatefrompng($tmp);
            case 'image/webp': return @imagecreatefromwebp($tmp);
            case 'image/gif':  return @imagecreatefromgif($tmp);
        }
        return null;
    }

    private static function gdWrite($img, string $mime, string $path, int $qJpeg, int $qWebp): void {
        switch ($mime) {
            case 'image/jpeg':
                @imagejpeg($img, $path, $qJpeg);
                return;
            case 'image/png':
                @imagepng($img, $path, 6);
                return;
            case 'image/webp':
                @imagewebp($img, $path, $qWebp);
                return;
            case 'image/gif':
                @imagegif($img, $path);
                return;
        }
        @imagejpeg($img, $path, $qJpeg);
    }
}
