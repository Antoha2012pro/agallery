<?php

class aGalleryImageProcessor {

    private aGalleryRepository $repo;
    private aGallerySettings $settings;

    public function __construct(aGalleryRepository $repo, aGallerySettings $settings) {
        $this->repo = $repo;
        $this->settings = $settings;
    }

    public function supportsMime(string $mime): bool {
        // Strict: only formats we can decode+encode with current runtime
        if (extension_loaded('imagick')) {
            // We still validate by actually trying to read later.
            return in_array($mime, ['image/png','image/jpeg','image/webp','image/gif'], true);
        }

        // GD
        if ($mime === 'image/jpeg') return function_exists('imagecreatefromjpeg') && function_exists('imagejpeg');
        if ($mime === 'image/png')  return function_exists('imagecreatefrompng') && function_exists('imagepng');
        if ($mime === 'image/webp') return function_exists('imagecreatefromwebp') && function_exists('imagewebp');
        if ($mime === 'image/gif')  return function_exists('imagecreatefromgif') && function_exists('imagegif');

        return false;
    }

    public function normalizeUsernameSlug(string $username): string {
        $s = strtolower($username);
        // Keep only a-z0-9_- ; replace others with _
        $s = preg_replace('/[^a-z0-9_-]+/i', '_', $s);
        $s = trim($s, '_');
        if ($s === '') $s = 'user';
        return $s;
    }

    public function buildBaseFilename(int $user_id, string $username, int $image_id): string {
        $slug = $this->normalizeUsernameSlug($username);
        $ts = $this->repo->now();
        $rand = bin2hex(random_bytes(4));
        return "gallery_u{$user_id}_{$slug}_{$image_id}_{$ts}_{$rand}";
    }

    public function ensureDirs(string $rel_base_dir): void {
        $abs = ROOT_PATH . '/' . $rel_base_dir;
        if (!is_dir($abs)) mkdir($abs, 0755, true);

        $protect = $abs . '/.htaccess';
        if (!file_exists($protect)) {
            @file_put_contents($protect, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|phar)$\">\n  Deny from all\n</FilesMatch>\n");
        }

        $index = $abs . '/index.html';
        if (!file_exists($index)) {
            @file_put_contents($index, "<!doctype html><meta charset=\"utf-8\"><title>Forbidden</title>");
        }
    }

    public function processUpload(
        string $tmp_path,
        string $mime,
        string $src_ext,
        int $user_id,
        string $username,
        int $image_id
    ): array {
        $max_w = (int)$this->settings->get('max_width', 1920);
        $max_h = (int)$this->settings->get('max_height', 1080);
        $q_jpg = (int)$this->settings->get('image_quality_jpeg', 82);
        $q_webp = (int)$this->settings->get('image_quality_webp', 80);
        $thumb_w = (int)$this->settings->get('thumb_width', 480);

        $allow_conversion = (int)$this->settings->get('allow_conversion', 0) === 1;
        $convert_to = (string)$this->settings->get('convert_to', 'jpg'); // jpg|webp

        $year = date('Y');
        $month = date('m');

        $base_dir = "uploads/agallery/{$year}/{$month}";
        $norm_dir = "{$base_dir}/normalized";
        $thumb_dir = "{$base_dir}/thumbs";

        $this->ensureDirs("uploads/agallery");
        $this->ensureDirs($base_dir);
        $this->ensureDirs($norm_dir);
        $this->ensureDirs($thumb_dir);

        $base_name = $this->buildBaseFilename($user_id, $username, $image_id);

        $target_ext = strtolower($src_ext);
        if (!in_array($target_ext, ['png','jpg','jpeg','webp','gif'], true)) {
            $target_ext = 'jpg';
        }

        // Optional conversion if enabled and if runtime supports it
        if ($allow_conversion) {
            if ($convert_to === 'webp' && (extension_loaded('imagick') || (function_exists('imagewebp') && function_exists('imagecreatefromwebp')))) {
                $target_ext = 'webp';
                $mime = 'image/webp';
            } else {
                $target_ext = 'jpg';
                $mime = 'image/jpeg';
            }
        }

        $norm_rel = "{$norm_dir}/{$base_name}.{$target_ext}";
        $thumb_rel = "{$thumb_dir}/{$base_name}.{$target_ext}";

        $norm_abs = ROOT_PATH . '/' . $norm_rel;
        $thumb_abs = ROOT_PATH . '/' . $thumb_rel;

        $result = extension_loaded('imagick')
            ? $this->processWithImagick($tmp_path, $mime, $norm_abs, $thumb_abs, $max_w, $max_h, $thumb_w, $q_jpg, $q_webp, $target_ext)
            : $this->processWithGd($tmp_path, $mime, $norm_abs, $thumb_abs, $max_w, $max_h, $thumb_w, $q_jpg, $q_webp, $target_ext);

        // Return relative paths and final dimensions
        return [
            'file_path' => $norm_rel,
            'thumb_path' => $thumb_rel,
            'width' => $result['width'],
            'height' => $result['height'],
            'mime' => $result['mime'],
            'ext' => $result['ext'],
            'file_size' => filesize($norm_abs),
        ];
    }

    private function processWithImagick(
        string $tmp, string $mime,
        string $out_abs, string $thumb_abs,
        int $max_w, int $max_h, int $thumb_w,
        int $q_jpg, int $q_webp, string $target_ext
    ): array {
        try {
            $img = new Imagick($tmp);

            // If animated GIF, keep first frame (explicit behavior).
            if ($mime === 'image/gif' && $img->getNumberImages() > 1) {
                $img->setIteratorIndex(0);
                $img = $img->getImage();
            }

            // Strip metadata
            if (method_exists($img, 'stripImage')) $img->stripImage();

            $w = $img->getImageWidth();
            $h = $img->getImageHeight();

            // Downscale
            $scale = min($max_w / max(1,$w), $max_h / max(1,$h), 1.0);
            if ($scale < 1.0) {
                $nw = (int) floor($w * $scale);
                $nh = (int) floor($h * $scale);
                $img->resizeImage($nw, $nh, Imagick::FILTER_LANCZOS, 1);
                $w = $nw; $h = $nh;
            }

            $format = $target_ext === 'jpg' ? 'jpeg' : $target_ext;
            $img->setImageFormat($format);

            if ($format === 'jpeg') $img->setImageCompressionQuality($q_jpg);
            if ($format === 'webp') $img->setImageCompressionQuality($q_webp);

            $img->writeImage($out_abs);

            // Thumb
            $thumb = new Imagick($out_abs);
            $tw = $thumb->getImageWidth();
            $th = $thumb->getImageHeight();
            if ($tw > $thumb_w) {
                $thumb->resizeImage($thumb_w, (int)floor($th * ($thumb_w / $tw)), Imagick::FILTER_LANCZOS, 1);
            }
            if (method_exists($thumb, 'stripImage')) $thumb->stripImage();
            if ($format === 'jpeg') $thumb->setImageCompressionQuality($q_jpg);
            if ($format === 'webp') $thumb->setImageCompressionQuality($q_webp);
            $thumb->writeImage($thumb_abs);

            return [
                'width' => $w,
                'height' => $h,
                'mime' => ($format === 'jpeg') ? 'image/jpeg' : 'image/' . $format,
                'ext' => ($format === 'jpeg') ? 'jpg' : $format,
            ];
        } catch (Exception $e) {
            throw new RuntimeException('Imagick failed: ' . $e->getMessage());
        }
    }

    private function processWithGd(
        string $tmp, string $mime,
        string $out_abs, string $thumb_abs,
        int $max_w, int $max_h, int $thumb_w,
        int $q_jpg, int $q_webp, string $target_ext
    ): array {
        $src = $this->gdLoad($tmp, $mime);
        if (!$src) throw new RuntimeException('GD cannot decode image');

        $w = imagesx($src);
        $h = imagesy($src);

        $scale = min($max_w / max(1,$w), $max_h / max(1,$h), 1.0);
        $nw = (int) floor($w * $scale);
        $nh = (int) floor($h * $scale);

        $dst = imagecreatetruecolor($nw, $nh);

        // Preserve alpha for PNG/WebP
        if (in_array($target_ext, ['png','webp'], true)) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
        }

        imagecopyresampled($dst, $src, 0,0,0,0, $nw,$nh, $w,$h);

        $this->gdSave($dst, $out_abs, $target_ext, $q_jpg, $q_webp);

        // Thumb
        $thumbScale = min($thumb_w / max(1,$nw), 1.0);
        $tw = (int) floor($nw * $thumbScale);
        $th = (int) floor($nh * $thumbScale);

        $timg = imagecreatetruecolor($tw, $th);
        if (in_array($target_ext, ['png','webp'], true)) {
            imagealphablending($timg, false);
            imagesavealpha($timg, true);
            $transparent = imagecolorallocatealpha($timg, 0, 0, 0, 127);
            imagefilledrectangle($timg, 0, 0, $tw, $th, $transparent);
        }
        imagecopyresampled($timg, $dst, 0,0,0,0, $tw,$th, $nw,$nh);

        $this->gdSave($timg, $thumb_abs, $target_ext, $q_jpg, $q_webp);

        imagedestroy($src);
        imagedestroy($dst);
        imagedestroy($timg);

        return [
            'width' => $nw,
            'height' => $nh,
            'mime' => $this->mimeFromExt($target_ext),
            'ext' => ($target_ext === 'jpeg') ? 'jpg' : $target_ext,
        ];
    }

    private function gdLoad(string $tmp, string $mime) {
        if ($mime === 'image/jpeg') return @imagecreatefromjpeg($tmp);
        if ($mime === 'image/png') return @imagecreatefrompng($tmp);
        if ($mime === 'image/webp') return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false;
        if ($mime === 'image/gif') return @imagecreatefromgif($tmp);
        return false;
    }

    private function gdSave($img, string $out, string $ext, int $q_jpg, int $q_webp): void {
        $ext = strtolower($ext);
        if ($ext === 'jpeg') $ext = 'jpg';

        if ($ext === 'jpg') {
            imagejpeg($img, $out, max(1, min(100, $q_jpg)));
            return;
        }
        if ($ext === 'png') {
            // PNG compression: 0 (none) .. 9 (max)
            imagepng($img, $out, 6);
            return;
        }
        if ($ext === 'webp') {
            if (!function_exists('imagewebp')) throw new RuntimeException('GD webp encoder missing');
            imagewebp($img, $out, max(1, min(100, $q_webp)));
            return;
        }
        if ($ext === 'gif') {
            imagegif($img, $out);
            return;
        }

        // fallback
        imagejpeg($img, $out, max(1, min(100, $q_jpg)));
    }

    private function mimeFromExt(string $ext): string {
        $ext = strtolower($ext);
        if ($ext === 'jpg' || $ext === 'jpeg') return 'image/jpeg';
        if ($ext === 'png') return 'image/png';
        if ($ext === 'webp') return 'image/webp';
        if ($ext === 'gif') return 'image/gif';
        return 'application/octet-stream';
    }
}