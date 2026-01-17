<?php
if (!defined('ROOT_PATH')) {
    die('ROOT_PATH not defined');
}

require_once(ROOT_PATH . '/modules/aGallery/includes/autoload.php');

$db = DB::getInstance();
$language = aGallery_Compat::getLanguageFromGlobals();
$settings = new aGallery_Settings($db);

if (!aGallery_Compat::isLoggedIn($user)) {
    aGallery_Compat::flash('agallery_error', aGallery_Compat::t($language, 'err_login_required', [], 'Нужно войти.'));
    aGallery_Compat::redirect(aGallery_Compat::url('/gallery'));
}

if (!aGallery_Compat::hasPerm($user, 'agallery.upload')) {
    aGallery_Compat::flash('agallery_error', aGallery_Compat::t($language, 'err_no_upload_perm', [], 'Нет прав на загрузку.'));
    aGallery_Compat::redirect(aGallery_Compat::url('/gallery'));
}

$token = $_POST['token'] ?? null;
if (!aGallery_Compat::tokenCheck($token)) {
    aGallery_Compat::flash('agallery_error', aGallery_Compat::t($language, 'err_csrf', [], 'CSRF токен неверный.'));
    aGallery_Compat::redirect(aGallery_Compat::url('/gallery'));
}

$catId = (int)($_POST['category'] ?? 0);
$title = aGallery_Util::cleanText($_POST['title'] ?? '');
$desc  = aGallery_Util::cleanText($_POST['description'] ?? '');

$titleMax = $settings->getInt('title_max_len', 64);
$descMax = $settings->getInt('desc_max_len', 500);

if ($catId <= 0) {
    aGallery_Compat::flash('agallery_error', aGallery_Compat::t($language, 'err_category_required', [], 'Выберите категорию.'));
    aGallery_Compat::redirect(aGallery_Compat::url('/gallery'));
}

if ($title === '' || mb_strlen($title) > $titleMax) {
    aGallery_Compat::flash('agallery_error', aGallery_Compat::t($language, 'err_title_invalid', [], 'Некорректный заголовок.'));
    aGallery_Compat::redirect(aGallery_Compat::url('/gallery'));
}

if ($desc !== '' && mb_strlen($desc) > $descMax) {
    aGallery_Compat::flash('agallery_error', aGallery_Compat::t($language, 'err_desc_too_long', [], 'Описание слишком длинное.'));
    aGallery_Compat::redirect(aGallery_Compat::url('/gallery'));
}

$cat = aGallery_Categories::get($db, $catId);
if (!$cat || !aGallery_Categories::canUploadToCategory($db, $user, $cat)) {
    aGallery_Compat::flash('agallery_error', aGallery_Compat::t($language, 'err_no_category_upload_access', [], 'Нет доступа к загрузке в эту категорию.'));
    aGallery_Compat::redirect(aGallery_Compat::url('/gallery'));
}

if (!isset($_FILES['file'])) {
    aGallery_Compat::flash('agallery_error', aGallery_Compat::t($language, 'err_file_required', [], 'Файл обязателен.'));
    aGallery_Compat::redirect(aGallery_Compat::url('/gallery'));
}

$f = $_FILES['file'];

if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    $errKey = 'err_upload_generic';
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) $errKey = 'err_upload_too_large_server';
    aGallery_Compat::flash('agallery_error', aGallery_Compat::t($language, $errKey, [], 'Ошибка загрузки файла.'));
    aGallery_Compat::redirect(aGallery_Compat::url('/gallery'));
}

$maxBytes = $settings->maxUploadBytes();
$size = (int)($f['size'] ?? 0);
if ($size <= 0 || $size > $maxBytes) {
    aGallery_Compat::flash('agallery_error', aGallery_Compat::t($language, 'err_upload_too_large', ['max' => (string)($settings->getInt('max_upload_mb',50))], 'Файл слишком большой.'));
    aGallery_Compat::redirect(aGallery_Compat::url('/gallery'));
}

$tmp = $f['tmp_name'];
$info = @getimagesize($tmp);
if (!$info || !isset($info['mime'])) {
    aGallery_Compat::flash('agallery_error', aGallery_Compat::t($language, 'err_not_image', [], 'Файл не является изображением.'));
    aGallery_Compat::redirect(aGallery_Compat::url('/gallery'));
}

$mime = (string)$info['mime'];

$allowed = array_map('trim', explode(',', (string)$settings->get('allowed_extensions', 'png,jpg,jpeg,webp')));
$allowConvert = $settings->getBool('allow_convert', false);

$extMap = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

$ext = $extMap[$mime] ?? '';
if ($ext === '') {
    aGallery_Compat::flash('agallery_error', aGallery_Compat::t($language, 'err_format_not_supported', [], 'Формат не поддерживается.'));
    aGallery_Compat::redirect(aGallery_Compat::url('/gallery'));
}

if (!in_array($ext, $allowed, true)) {
    // if allow_convert we may accept decode-only formats, but here ext already in map
    aGallery_Compat::flash('agallery_error', aGallery_Compat::t($language, 'err_ext_not_allowed', [], 'Расширение не разрешено.'));
    aGallery_Compat::redirect(aGallery_Compat::url('/gallery'));
}

if (!aGallery_ImageProcessor::canDecode($mime)) {
    aGallery_Compat::flash('agallery_error', aGallery_Compat::t($language, 'err_cannot_decode', [], 'Этот формат нельзя декодировать на сервере.'));
    aGallery_Compat::redirect(aGallery_Compat::url('/gallery'));
}

if (!aGallery_ImageProcessor::canEncode($mime) && !$allowConvert) {
    aGallery_Compat::flash('agallery_error', aGallery_Compat::t($language, 'err_cannot_encode', [], 'Этот формат нельзя безопасно пересохранить.'));
    aGallery_Compat::redirect(aGallery_Compat::url('/gallery'));
}

// Create pending record first
$uid = aGallery_Compat::userId($user);
$imageId = aGallery_Images::createPending($db, $catId, $uid, $title, $desc);
if ($imageId <= 0) {
    aGallery_Compat::flash('agallery_error', aGallery_Compat::t($language, 'err_db', [], 'Ошибка БД.'));
    aGallery_Compat::redirect(aGallery_Compat::url('/gallery'));
}

// Build file name & dirs
$ym = date('Y') . '/' . date('m');
[$dirFs, $thumbDirFs] = aGallery_Storage::monthDirs($ym);

$usernameSlug = aGallery_Util::slugUsername(aGallery_Compat::username($user));
$stamp = time();

$baseName = "gallery_u{$uid}_{$usernameSlug}_{$imageId}_{$stamp}";
$outFs = $dirFs . '/' . $baseName . '.' . $ext;
$thumbFs = $thumbDirFs . '/' . $baseName . '.' . $ext;

try {
    [$finalMime, $finalExt, $w, $h, $finalSize] = aGallery_ImageProcessor::process(
        $settings,
        $tmp,
        $mime,
        $ext,
        $outFs,
        $thumbFs
    );

    $fileRel = aGallery_Storage::relPathFromFs($outFs);
    $thumbRel = aGallery_Storage::relPathFromFs($thumbFs);

    aGallery_Images::attachFiles($db, $imageId, $fileRel, $thumbRel, $finalMime, $finalExt, (int)$w, (int)$h, (int)$finalSize);

    aGallery_Audit::add($db, 'upload_pending', $imageId, $uid, [
        'mime' => $finalMime,
        'ext' => $finalExt,
        'size' => $finalSize
    ]);

    // Notify moderators
    $mods = aGallery_Compat::getUserIdsWithPermission($db, 'agallery.moderate');
    $panelUrl = aGallery_Compat::url('/panel/agallery/moderation', 'id=' . $imageId);

    $nTitle = aGallery_Compat::t($language, 'notif_new_pending_title', [], 'Новая заявка в галерею');
    $nBody = aGallery_Compat::t($language, 'notif_new_pending_body', [], 'Открыть заявку в StaffCP.');

    aGallery_Compat::notifyMany($db, $uid, $mods, $nTitle, $nBody, $panelUrl);

    aGallery_Compat::flash('agallery_success', aGallery_Compat::t($language, 'upload_success', [], 'Отправлено на рассмотрение.'));
} catch (Throwable $e) {
    // Clean up
    aGallery_Audit::add($db, 'upload_failed', $imageId, $uid, ['error' => $e->getMessage()]);
    // mark declined with reason for traceability
    aGallery_Images::decline($db, $imageId, 0, 'processing_failed');
    if (is_file($outFs)) @unlink($outFs);
    if (is_file($thumbFs)) @unlink($thumbFs);

    $msgKey = ($e->getMessage() === 'animated_gif_not_allowed') ? 'err_animated_gif' : 'err_processing_failed';
    aGallery_Compat::flash('agallery_error', aGallery_Compat::t($language, $msgKey, [], 'Ошибка обработки изображения.'));
}

aGallery_Compat::redirect(aGallery_Compat::url('/gallery'));
