<?php

class aGallery_ImageProcessor {

    public static function supportedEncoders(): array {
        $enc = [
            'jpg' => function_exists('imagejpeg'),
            'jpeg' => function_exists('imagejpeg'),
            'png' => function_exists('imagepng'),
            'webp' => function_exists('imagewebp'),
            'gif' => function_exists('imagegif'),
        ];

        $imagick = extension_loaded('imagick') && class_exists('Imagick');
        return [
            'imagick' => $imagick,
            'gd' => extension_loaded('gd'),
            'gd_enc' => $enc,
        ];
    }

    public static function handleUpload(User $user, array $file, array $settings): array {
        $maxBytes = (int)$settings['max_upload_mb'] * 1024 * 1024;

        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException(aGallery_Compat::lang('upload_invalid', 'errors'));
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException(aGallery_Compat::lang('upload_too_large_server', 'errors'));
            case UPLOAD_ERR_PARTIAL:
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException(aGallery_Compat::lang('upload_failed', 'errors'));
            default:
                throw new RuntimeException(aGallery_Compat::lang('upload_failed', 'errors'));
        }

        if ((int)$file['size'] <= 0 || (int)$file['size'] > $maxBytes) {
            throw new RuntimeException(aGallery_Compat::lang('upload_too_large', 'errors', ['max' => (string)$settings['max_upload_mb']]));
        }

        $tmp = $file['tmp_name'];
        if (!is_file($tmp)) {
            throw new RuntimeException(aGallery_Compat::lang('upload_failed', 'errors'));
        }

        // Verify actual image (do not trust extension)
        $imgInfo = @getimagesize($tmp);
        if ($imgInfo === false || !isset($imgInfo['mime'])) {
            throw new RuntimeException(aGallery_Compat::lang('not_an_image', 'errors'));
        }
        $mime = (string)$imgInfo['mime'];

        // Allow list by real mime + re-encode capability
        $allowed = array_map('trim', explode(',', (string)$settings['allowed_extensions']));
        $allowed = array_values(array_filter($allowed));
        $allowConvert = (int)$settings['allow_convert'] === 1;

        $mapMimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];

        $srcExt = $mapMimeToExt[$mime] ?? null;
        if (!$srcExt) {
            throw new RuntimeException(aGallery_Compat::lang('format_not_supported', 'errors'));
        }

        if (!in_array($srcExt, $allowed, true)) {
            throw new RuntimeException(aGallery_Compat::lang('format_not_allowed', 'errors'));
        }

        // Animated GIF handling
        if ($srcExt === 'gif') {
            $animated = aGallery_Utils::isAnimatedGif($tmp);
            if ($animated && !$allowConvert) {
                throw new RuntimeException(aGallery_Compat::lang('gif_animated_rejected', 'errors'));
            }
        }

        // Prepare dirs
        $year = date('Y');
        $month = date('m');

        $baseRel = "uploads/agallery/$year/$month";
        $thumbRel = "$baseRel/thumbs";
        $baseAbs = ROOT_PATH . "/$baseRel";
        $thumbAbs = ROOT_PATH . "/$thumbRel";

        aGallery_Utils::ensureDir($baseAbs);
        aGallery_Utils::ensureDir($thumbAbs);

        if (!is_writable($baseAbs) || !is_writable($thumbAbs)) {
            throw new RuntimeException(aGallery_Compat::lang('uploads_not_writable', 'errors'));
        }

        $userId = (int)$user->data()->id;
        $username = (string)$user->data()->username;
        $slug = aGallery_Utils::slugUsername($username);
        $rand = bin2hex(random_bytes(4));
        $ts = time();

        // Decide output format (keep same if encoder exists; else convert if allowed)
        $enc = self::supportedEncoders();

        $targetExt = $srcExt;
        if ($enc['imagick']) {
            // Imagick can write many formats, but we still obey allowed list + explicit ext
            if (!in_array($targetExt, ['jpg','png','webp','gif'], true)) $targetExt = 'jpg';
            if ($srcExt === 'gif' && $allowConvert) {
                // convert to webp by default
                $targetExt = function_exists('imagewebp') ? 'webp' : 'jpg';
            }
        } else {
            // GD
            if (empty($enc['gd_enc'][$targetExt])) {
                if (!$allowConvert) {
                    throw new RuntimeException(aGallery_Compat::lang('format_not_supported', 'errors'));
                }
                $targetExt = function_exists('imagewebp') ? 'webp' : 'jpg';
            }
            if ($srcExt === 'gif' && aGallery_Utils::isAnimatedGif($tmp)) {
                // convert animated gif -> static image only when allowed
                $targetExt = function_exists('imagewebp') ? 'webp' : 'jpg';
            }
        }

        $baseName = "gallery_u{$userId}_{$slug}_{$rand}_{$ts}";
        $outRel = "$baseRel/{$baseName}.{$targetExt}";
        $thumbOutRel = "$thumbRel/{$baseName}.{$targetExt}";
        $outAbs = ROOT_PATH . "/$outRel";
        $thumbOutAbs = ROOT_PATH . "/$thumbOutRel";

        $maxW = (int)$settings['max_width'];
        $maxH = (int)$settings['max_height'];
        $thumbW = (int)$settings['thumb_width'];
        $qJpg = (int)$settings['image_quality_jpeg'];
        $qWebp = (int)$settings['image_quality_webp'];

        // Process and save
        $result = self::processAndSave($tmp, $srcExt, $targetExt, $outAbs, $thumbOutAbs, $maxW, $maxH, $thumbW, $qJpg, $qWebp);

        return [
            'file_path' => $outRel,
            'thumb_path' => $thumbOutRel,
            'mime' => $result['mime'],
            'ext' => $targetExt,
            'width' => $result['width'],
            'height' => $result['height'],
            'file_size' => filesize($outAbs) ?: (int)$file['size'],
        ];
    }

    private static function processAndSave(
        string $srcAbs,
        string $srcExt,
        string $dstExt,
        string $dstAbs,
        string $thumbAbs,
        int $maxW,
        int $maxH,
        int $thumbW,
        int $qJpg,
        int $qWebp
    ): array {
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            return self::processImagick($srcAbs, $dstExt, $dstAbs, $thumbAbs, $maxW, $maxH, $thumbW, $qJpg, $qWebp);
        }

        return self::processGd($srcAbs, $srcExt, $dstExt, $dstAbs, $thumbAbs, $maxW, $maxH, $thumbW, $qJpg, $qWebp);
    }

    private static function processImagick(
        string $srcAbs,
        string $dstExt,
        string $dstAbs,
        string $thumbAbs,
        int $maxW,
        int $maxH,
        int $thumbW,
        int $qJpg,
        int $qWebp
    ): array {
        $im = new Imagick();
        $im->readImage($srcAbs);

        // If animated (GIF), take first frame (static)
        if ($im->getNumberImages() > 1) {
            $im = $im->coalesceImages();
            $im->setIteratorIndex(0);
            $first = $im->getImage();
            $im->clear();
            $im = new Imagick();
            $im->readImageBlob($first);
        }

        // Auto-orient + strip metadata
        if (method_exists($im, 'autoOrient')) {
            $im->autoOrient();
        }
        $im->stripImage();

        $w = $im->getImageWidth();
        $h = $im->getImageHeight();

        // Downscale keeping aspect
        $ratio = min($maxW / max($w, 1), $maxH / max($h, 1), 1.0);
        if ($ratio < 1.0) {
            $nw = (int)floor($w * $ratio);
            $nh = (int)floor($h * $ratio);
            $im->resizeImage($nw, $nh, Imagick::FILTER_LANCZOS, 1);
        }

        // Save main
        self::imagickWrite($im, $dstExt, $dstAbs, $qJpg, $qWebp);

        // Thumb
        $thumb = clone $im;
        $tw = $thumb->getImageWidth();
        $th = $thumb->getImageHeight();
        $tr = min($thumbW / max($tw, 1), 1.0);
        if ($tr < 1.0) {
            $thumb->resizeImage((int)floor($tw * $tr), (int)floor($th * $tr), Imagick::FILTER_LANCZOS, 1);
        }
        self::imagickWrite($thumb, $dstExt, $thumbAbs, $qJpg, $qWebp);

        $outInfo = @getimagesize($dstAbs);
        return [
            'mime' => $outInfo['mime'] ?? 'image/' . ($dstExt === 'jpg' ? 'jpeg' : $dstExt),
            'width' => $outInfo[0] ?? $im->getImageWidth(),
            'height' => $outInfo[1] ?? $im->getImageHeight(),
        ];
    }

    private static function imagickWrite(Imagick $im, string $ext, string $abs, int $qJpg, int $qWebp): void {
        $fmt = $ext === 'jpg' ? 'jpeg' : $ext;
        $im->setImageFormat($fmt);

        if ($fmt === 'jpeg') {
            $im->setImageCompression(Imagick::COMPRESSION_JPEG);
            $im->setImageCompressionQuality($qJpg);
        } elseif ($fmt === 'webp') {
            $im->setImageCompressionQuality($qWebp);
        } elseif ($fmt === 'png') {
            if (method_exists($im, 'setOption')) {
                $im->setOption('png:compression-level', '9');
            }
        }

        $im->writeImage($abs);
    }

    private static function processGd(
        string $srcAbs,
        string $srcExt,
        string $dstExt,
        string $dstAbs,
        string $thumbAbs,
        int $maxW,
        int $maxH,
        int $thumbW,
        int $qJpg,
        int $qWebp
    ): array {
        switch ($srcExt) {
            case 'jpg':
            case 'jpeg':
                $img = @imagecreatefromjpeg($srcAbs);
                break;
            case 'png':
                $img = @imagecreatefrompng($srcAbs);
                break;
            case 'webp':
                if (!function_exists('imagecreatefromwebp')) throw new RuntimeException(aGallery_Compat::lang('format_not_supported', 'errors'));
                $img = @imagecreatefromwebp($srcAbs);
                break;
            case 'gif':
                $animated = aGallery_Utils::isAnimatedGif($srcAbs);
                // for animated gif here we already decided we will convert static frame (first frame)
                $img = @imagecreatefromgif($srcAbs);
                break;
            default:
                throw new RuntimeException(aGallery_Compat::lang('format_not_supported', 'errors'));
        }

        if (!$img) {
            throw new RuntimeException(aGallery_Compat::lang('upload_failed', 'errors'));
        }

        $w = imagesx($img);
        $h = imagesy($img);

        $ratio = min($maxW / max($w, 1), $maxH / max($h, 1), 1.0);
        $out = $img;
        if ($ratio < 1.0) {
            $nw = (int)floor($w * $ratio);
            $nh = (int)floor($h * $ratio);
            $out = imagescale($img, $nw, $nh, IMG_BICUBIC);
            imagedestroy($img);
        }

        self::gdWrite($out, $dstExt, $dstAbs, $qJpg, $qWebp);

        // Thumb
        $tw = imagesx($out);
        $th = imagesy($out);
        $tr = min($thumbW / max($tw, 1), 1.0);
        $thumb = $out;
        if ($tr < 1.0) {
            $thumb = imagescale($out, (int)floor($tw * $tr), (int)floor($th * $tr), IMG_BICUBIC);
        }
        self::gdWrite($thumb, $dstExt, $thumbAbs, $qJpg, $qWebp);

        if ($thumb !== $out) imagedestroy($thumb);
        imagedestroy($out);

        $outInfo = @getimagesize($dstAbs);
        return [
            'mime' => $outInfo['mime'] ?? 'image/' . ($dstExt === 'jpg' ? 'jpeg' : $dstExt),
            'width' => $outInfo[0] ?? $w,
            'height' => $outInfo[1] ?? $h,
        ];
    }

    private static function gdWrite($img, string $ext, string $abs, int $qJpg, int $qWebp): void {
        if ($ext === 'jpg' || $ext === 'jpeg') {
            imagejpeg($img, $abs, $qJpg);
            return;
        }
        if ($ext === 'png') {
            // compression 0..9
            imagepng($img, $abs, 9);
            return;
        }
        if ($ext === 'webp') {
            if (!function_exists('imagewebp')) {
                throw new RuntimeException(aGallery_Compat::lang('format_not_supported', 'errors'));
            }
            imagewebp($img, $abs, $qWebp);
            return;
        }
        if ($ext === 'gif') {
            imagegif($img, $abs);
            return;
        }
        throw new RuntimeException(aGallery_Compat::lang('format_not_supported', 'errors'));
    }
}
