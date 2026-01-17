<?php
class AGallery {
    public static function getLanguage() {
        return new Language(ROOT_PATH . '/modules/aGallery/language', LANGUAGE);
    }

    public static function processImage($file, $config) {
        $info = getimagesize($file['tmp_name']);
        if (!$info) return ['error' => 'Invalid image content'];
        
        $mime = $info['mime'];
        $width = $info[0];
        $height = $info[1];

        // Resource loading based on MIME
        switch ($mime) {
            case 'image/jpeg': $img = imagecreatefromjpeg($file['tmp_name']); break;
            case 'image/png':  $img = imagecreatefrompng($file['tmp_name']); break;
            case 'image/webp': $img = imagecreatefromwebp($file['tmp_name']); break;
            default: return ['error' => 'Unsupported format'];
        }

        // Downscale
        if ($width > $config['max_w'] || $height > $config['max_h']) {
            $ratio = min($config['max_w'] / $width, $config['max_h'] / $height);
            $new_w = (int)($width * $ratio);
            $new_h = (int)($height * $ratio);
            $img = imagescale($img, $new_w, $new_h);
        }

        // Paths: uploads/agallery/2026/01/
        $y = date('Y'); $m = date('m');
        $base_dir = "uploads/agallery/$y/$m";
        if (!is_dir(ROOT_PATH . '/' . $base_dir . '/thumbs')) {
            mkdir(ROOT_PATH . '/' . $base_dir . '/thumbs', 0755, true);
        }

        $filename = 'gallery_u' . $config['user_id'] . '_' . time() . '_' . bin2hex(random_bytes(4));
        $ext = ($mime == 'image/jpeg') ? '.jpg' : (($mime == 'image/webp') ? '.webp' : '.png');
        
        $full_path = $base_dir . '/' . $filename . $ext;
        $thumb_path = $base_dir . '/thumbs/' . $filename . $ext;

        // Save Main
        if ($mime == 'image/jpeg') imagejpeg($img, ROOT_PATH . '/' . $full_path, 82);
        elseif ($mime == 'image/webp') imagewebp($img, ROOT_PATH . '/' . $full_path, 80);
        else imagepng($img, ROOT_PATH . '/' . $full_path);

        // Thumbnail
        $thumb = imagescale($img, 480);
        if ($mime == 'image/jpeg') imagejpeg($thumb, ROOT_PATH . '/' . $thumb_path, 75);
        elseif ($mime == 'image/webp') imagewebp($thumb, ROOT_PATH . '/' . $thumb_path, 75);
        else imagepng($thumb, ROOT_PATH . '/' . $thumb_path);

        imagedestroy($img);
        imagedestroy($thumb);

        return [
            'path' => '/' . $full_path,
            'thumb' => '/' . $thumb_path,
            'mime' => $mime
        ];
    }
    
    public static function sendNotification($user_id, $title, $content, $url) {
        // Notification compatibility layer
        if (class_exists('Alert')) {
            Alert::create($user_id, 'agallery_mod', ['title' => $title, 'content' => $content], ['title' => $title, 'content' => $content], $url);
        }
    }
}