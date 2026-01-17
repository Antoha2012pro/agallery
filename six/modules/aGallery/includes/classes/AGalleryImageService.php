<?php

class AGalleryImageService {

    public static function handleUpload(User $user): array {
        $language = new Language('language', 'English'); // placeholder, реальный объект приходит извне, но здесь возвращаем только данные.
        $errors = [];

        if (!isset($_POST['category_id'])) {
            $errors[] = 'missing_category';
        }
        if (!isset($_POST['title']) || !strlen(trim($_POST['title']))) {
            $errors[] = 'missing_title';
        }

        if (!isset($_FILES['image']) || !$_FILES['image']['tmp_name']) {
            $errors[] = 'missing_file';
        }

        if (count($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $category_id = (int)$_POST['category_id'];
        $category = AGalleryCategoryService::getById($category_id);
        if (!$category) {
            return ['success' => false, 'errors' => ['invalid_category']];
        }

        if (!AGalleryCategoryService::canUploadToCategory($user, $category)) {
            return ['success' => false, 'errors' => ['no_upload_permission']];
        }

        $settings = AGallerySettings::all();
        $maxMb = (int)$settings['max_upload_mb'];
        $maxBytes = $maxMb * 1024 * 1024;
        $file = $_FILES['image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'errors' => ['upload_error']];
        }
        if ($file['size'] > $maxBytes) {
            return ['success' => false, 'errors' => ['file_too_large']];
        }

        // Проверка реального изображения.
        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            return ['success' => false, 'errors' => ['not_image']];
        }
        $mime = $info['mime'];
        $width = (int)$info[0];
        $height = (int)$info[1];

        $allowedExt = array_map('trim', explode(',', (string)$settings['allowed_extensions']));
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            return ['success' => false, 'errors' => ['extension_not_allowed']];
        }

        $allow_convert = (int)$settings['allow_convert'] === 1;

        // Папки.
        $baseDir = ROOT_PATH . '/uploads/agallery';
        $year = date('Y');
        $month = date('m');

        $targetDir = $baseDir . '/' . $year . '/' . $month;
        $thumbDir = $targetDir . '/thumbs';

        if (!file_exists($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        if (!file_exists($thumbDir)) {
            @mkdir($thumbDir, 0755, true);
        }

        if (!is_writable($targetDir) || !is_writable($thumbDir)) {
            return ['success' => false, 'errors' => ['upload_dir_not_writable']];
        }

        // Подготовка имени файла.
        $user_id = (int)$user->data()->id;
        $username = $user->data()->username;
        $username_slug = strtolower(preg_replace('/[^a-z0-9_-]+/i', '_', $username));
        $timestamp = time();

        // Вставка placeholder записи для получения id.
        $now = time();
        DB::getInstance()->insert('agallery_images', [
            'category_id' => $category_id,
            'user_id' => $user_id,
            'title' => trim($_POST['title']),
            'description' => isset($_POST['description']) ? trim($_POST['description']) : null,
            'file_path' => '',
            'thumb_path' => '',
            'mime' => $mime,
            'ext' => $ext,
            'width' => $width,
            'height' => $height,
            'file_size' => $file['size'],
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $image_id = (int)DB::getInstance()->lastId();

        $baseName = 'gallery_u' . $user_id . '_' . $username_slug . '_' . $image_id . '_' . $timestamp;
        $normalizedExt = $ext;
        $thumbExt = $ext;

        // Обработка изображения.
        $maxWidth = (int)$settings['max_width'];
        $maxHeight = (int)$settings['max_height'];
        $thumbWidth = (int)$settings['thumb_width'];

        $normalizedFile = $targetDir . '/' . $baseName . '.' . $normalizedExt;
        $thumbFile = $thumbDir . '/' . $baseName . '.' . $thumbExt;

        $result = self::processImage($file['tmp_name'], $normalizedFile, $thumbFile, $mime, $ext, $maxWidth, $maxHeight, $thumbWidth, $settings, $allow_convert);
        if (!$result['success']) {
            // Rollback placeholder.
            DB::getInstance()->delete('agallery_images', ['id', '=', $image_id]);
            return ['success' => false, 'errors' => $result['errors']];
        }

        // Обновление путей.
        DB::getInstance()->update('agallery_images', $image_id, [
            'file_path' => str_replace(ROOT_PATH, '', $normalizedFile),
            'thumb_path' => str_replace(ROOT_PATH, '', $thumbFile),
            'width' => $result['width'],
            'height' => $result['height'],
            'file_size' => $result['file_size'],
        ]);

        return ['success' => true, 'image_id' => $image_id];
    }

    private static function processImage(
        string $tmpFile,
        string $normalizedFile,
        string $thumbFile,
        string $mime,
        string $ext,
        int $maxWidth,
        int $maxHeight,
        int $thumbWidth,
        array $settings,
        bool $allow_convert
    ): array {

        $useImagick = class_exists('Imagick');
        try {
            if ($useImagick) {
                $image = new Imagick($tmpFile);
                if ($image->getImageHeight() <= 0 || $image->getImageWidth() <= 0) {
                    return ['success' => false, 'errors' => ['invalid_image_size']];
                }

                // Downscale.
                $w = $image->getImageWidth();
                $h = $image->getImageHeight();

                $ratio = min($maxWidth / $w, $maxHeight / $h, 1.0);
                if ($ratio < 1.0) {
                    $image->resizeImage((int)($w * $ratio), (int)($h * $ratio), Imagick::FILTER_LANCZOS, 1);
                }

                // Strip metadata.
                $image->stripImage();

                // Decide format.
                $format = strtolower($image->getImageFormat());
                if (!self::canEncode($format) && $allow_convert) {
                    $format = 'jpeg';
                } elseif (!self::canEncode($format)) {
                    return ['success' => false, 'errors' => ['format_not_supported']];
                }

                if ($format === 'jpeg' || $format === 'jpg') {
                    $image->setImageCompression(Imagick::COMPRESSION_JPEG);
                    $image->setImageCompressionQuality((int)$settings['image_quality_jpeg']);
                } elseif ($format === 'webp') {
                    $image->setImageCompressionQuality((int)$settings['image_quality_webp']);
                }

                $normalizedFile = self::adjustExtensionForFormat($normalizedFile, $format);
                $thumbFile = self::adjustExtensionForFormat($thumbFile, $format);

                $image->writeImage($normalizedFile);

                // Thumbnail.
                $thumb = clone $image;
                $thumb->thumbnailImage($thumbWidth, 0);
                $thumb->writeImage($thumbFile);

                $width = $image->getImageWidth();
                $height = $image->getImageHeight();
                $size = filesize($normalizedFile);

                return [
                    'success' => true,
                    'width' => $width,
                    'height' => $height,
                    'file_size' => $size,
                ];

            } else {
                // GD fallback.
                $gd = self::createGdImage($tmpFile, $mime);
                if (!$gd) {
                    return ['success' => false, 'errors' => ['format_not_supported']];
                }
                $w = imagesx($gd);
                $h = imagesy($gd);

                $ratio = min($maxWidth / $w, $maxHeight / $h, 1.0);
                $newW = (int)($w * $ratio);
                $newH = (int)($h * $ratio);
                $resized = imagecreatetruecolor($newW, $newH);
                imagecopyresampled($resized, $gd, 0, 0, 0, 0, $newW, $newH, $w, $h);

                // Save normalized.
                $ok = self::saveGdImage($resized, $normalizedFile, $mime, $settings, $allow_convert);
                if (!$ok) {
                    return ['success' => false, 'errors' => ['format_not_supported']];
                }

                // Thumbnail.
                $thumbH = (int)($newH * ($thumbWidth / $newW));
                $thumbRes = imagecreatetruecolor($thumbWidth, $thumbH);
                imagecopyresampled($thumbRes, $resized, 0, 0, 0, 0, $thumbWidth, $thumbH, $newW, $newH);
                self::saveGdImage($thumbRes, $thumbFile, $mime, $settings, $allow_convert);

                $width = $newW;
                $height = $newH;
                $size = filesize($normalizedFile);

                return [
                    'success' => true,
                    'width' => $width,
                    'height' => $height,
                    'file_size' => $size,
                ];
            }

        } catch (Exception $e) {
            return ['success' => false, 'errors' => ['processing_failed']];
        }
    }

    private static function canEncode(string $format): bool {
        $format = strtolower($format);
        switch ($format) {
            case 'jpeg':
            case 'jpg':
                return function_exists('imagejpeg');
            case 'png':
                return function_exists('imagepng');
            case 'webp':
                return function_exists('imagewebp');
            default:
                return false;
        }
    }

    private static function adjustExtensionForFormat(string $filename, string $format): string {
        $format = strtolower($format);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $base = substr($filename, 0, -strlen($ext) - 1);
        if ($format === 'jpeg' || $format === 'jpg') {
            return $base . '.jpg';
        }
        if ($format === 'png') {
            return $base . '.png';
        }
        if ($format === 'webp') {
            return $base . '.webp';
        }
        return $filename;
    }

    private static function createGdImage(string $file, string $mime) {
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                return @imagecreatefromjpeg($file);
            case 'image/png':
                return @imagecreatefrompng($file);
            case 'image/webp':
                return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false;
            default:
                return false;
        }
    }

    private static function saveGdImage($image, string $file, string $mime, array $settings, bool $allow_convert): bool {
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                if (!function_exists('imagejpeg')) return false;
                return imagejpeg($image, $file, (int)$settings['image_quality_jpeg']);
            case 'image/png':
                if (!function_exists('imagepng')) return false;
                return imagepng($image, $file);
            case 'image/webp':
                if (!function_exists('imagewebp')) {
                    if ($allow_convert && function_exists('imagejpeg')) {
                        return imagejpeg($image, preg_replace('/\.webp$/i', '.jpg', $file), (int)$settings['image_quality_jpeg']);
                    }
                    return false;
                }
                return imagewebp($image, $file, (int)$settings['image_quality_webp']);
            default:
                if ($allow_convert && function_exists('imagejpeg')) {
                    return imagejpeg($image, preg_replace('/\.[a-z0-9]+$/i', '.jpg', $file), (int)$settings['image_quality_jpeg']);
                }
                return false;
        }
    }
}
