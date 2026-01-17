<?php
require_once ROOT_PATH . '/modules/aGallery/includes/autoload.php';

aGallery_Compat::requireBackendInit();
global $user;

if (!$user->isLoggedIn()) aGallery_Compat::redirect(URL::build('/login'));
if (method_exists($user, 'handlePanelPageLoad')) $user->handlePanelPageLoad('agallery.manage');
if (!$user->hasPermission('agallery.manage')) aGallery_Compat::redirect(URL::build('/panel'));

$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!aGallery_Compat::checkToken($_POST['token'] ?? null)) {
        $err = aGallery_Compat::lang('invalid_token', 'errors');
    } else {
        try {
            $maxMb = max(1, (int)($_POST['max_upload_mb'] ?? 50));
            $maxW  = max(64, (int)($_POST['max_width'] ?? 1920));
            $maxH  = max(64, (int)($_POST['max_height'] ?? 1080));
            $allowed = aGallery_Utils::cleanText($_POST['allowed_extensions'] ?? 'png,jpg,jpeg,webp,gif', 120, false);
            $qJ = min(100, max(1, (int)($_POST['image_quality_jpeg'] ?? 82)));
            $qW = min(100, max(1, (int)($_POST['image_quality_webp'] ?? 80)));
            $tW = min(2000, max(64, (int)($_POST['thumb_width'] ?? 480)));
            $allowConvert = isset($_POST['allow_convert']) ? '1' : '0';

            aGallery::setSetting('max_upload_mb', (string)$maxMb);
            aGallery::setSetting('max_width', (string)$maxW);
            aGallery::setSetting('max_height', (string)$maxH);
            aGallery::setSetting('allowed_extensions', $allowed);
            aGallery::setSetting('image_quality_jpeg', (string)$qJ);
            aGallery::setSetting('image_quality_webp', (string)$qW);
            aGallery::setSetting('thumb_width', (string)$tW);
            aGallery::setSetting('allow_convert', $allowConvert);

            $ok = aGallery_Compat::lang('saved', 'panel');
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
    }
}

// Health check
$pathsBad = aGallery::ensureUploadDirs();
$base = ROOT_PATH . '/uploads/agallery';
$wBase = is_writable($base);
$phpUpload = ini_get('upload_max_filesize');
$phpPost = ini_get('post_max_size');
$phpMem = ini_get('memory_limit');

$maxMb = (int)aGallery::getSetting('max_upload_mb', 50);
$warnLimits = [];
if (aGallery_Utils::parseBytes($phpUpload) > 0 && aGallery_Utils::parseBytes($phpUpload) < $maxMb * 1024 * 1024) $warnLimits[] = 'upload_max_filesize';
if (aGallery_Utils::parseBytes($phpPost) > 0 && aGallery_Utils::parseBytes($phpPost) < $maxMb * 1024 * 1024) $warnLimits[] = 'post_max_size';

echo '<div class="container">';
echo '<h2>' . aGallery_Utils::h(aGallery_Compat::lang('settings', 'panel')) . '</h2>';
if ($ok) echo '<div class="alert alert-success">' . aGallery_Utils::h($ok) . '</div>';
if ($err) echo '<div class="alert alert-danger">' . aGallery_Utils::h($err) . '</div>';

echo '<h3>' . aGallery_Utils::h(aGallery_Compat::lang('health_check', 'panel')) . '</h3>';
echo '<ul>';
echo '<li>uploads/agallery writable: <strong>' . ($wBase ? 'OK' : 'NO') . '</strong> (' . aGallery_Utils::h($base) . ')</li>';
if (count($pathsBad)) {
    echo '<li><strong>' . aGallery_Utils::h(aGallery_Compat::lang('not_writable_paths', 'panel')) . '</strong><ul>';
    foreach ($pathsBad as $p) echo '<li>' . aGallery_Utils::h($p) . '</li>';
    echo '</ul></li>';
}
echo '<li>PHP upload_max_filesize: <code>' . aGallery_Utils::h((string)$phpUpload) . '</code></li>';
echo '<li>PHP post_max_size: <code>' . aGallery_Utils::h((string)$phpPost) . '</code></li>';
echo '<li>PHP memory_limit: <code>' . aGallery_Utils::h((string)$phpMem) . '</code></li>';
if (count($warnLimits)) {
    echo '<li style="color:#b00"><strong>' . aGallery_Utils::h(aGallery_Compat::lang('php_limits_too_low', 'panel')) . '</strong>: ' . aGallery_Utils::h(implode(', ', $warnLimits)) . '</li>';
}
echo '</ul>';

echo '<hr><form method="post">';
echo aGallery_Compat::tokenField();

echo '<div><label>max_upload_mb</label><input class="form-control" name="max_upload_mb" value="' . (int)aGallery::getSetting('max_upload_mb', 50) . '"></div>';
echo '<div><label>max_width</label><input class="form-control" name="max_width" value="' . (int)aGallery::getSetting('max_width', 1920) . '"></div>';
echo '<div><label>max_height</label><input class="form-control" name="max_height" value="' . (int)aGallery::getSetting('max_height', 1080) . '"></div>';
echo '<div><label>allowed_extensions</label><input class="form-control" name="allowed_extensions" value="' . aGallery_Utils::h((string)aGallery::getSetting('allowed_extensions', 'png,jpg,jpeg,webp,gif')) . '"></div>';
echo '<div><label>image_quality_jpeg</label><input class="form-control" name="image_quality_jpeg" value="' . (int)aGallery::getSetting('image_quality_jpeg', 82) . '"></div>';
echo '<div><label>image_quality_webp</label><input class="form-control" name="image_quality_webp" value="' . (int)aGallery::getSetting('image_quality_webp', 80) . '"></div>';
echo '<div><label>thumb_width</label><input class="form-control" name="thumb_width" value="' . (int)aGallery::getSetting('thumb_width', 480) . '"></div>';
$allow = (int)aGallery::getSetting('allow_convert', 0) === 1 ? ' checked' : '';
echo '<div><label><input type="checkbox" name="allow_convert" value="1"' . $allow . '> allow_convert</label></div>';

echo '<button class="btn btn-primary" type="submit">' . aGallery_Utils::h(aGallery_Compat::lang('save', 'panel')) . '</button>';
echo '</form>';

echo '<hr><p><strong>' . aGallery_Utils::h(aGallery_Compat::lang('server_hint', 'panel')) . '</strong><br>';
echo aGallery_Utils::h(aGallery_Compat::lang('server_hint_text', 'panel')) . '</p>';

echo '</div>';
