<?php
if (!defined('ROOT_PATH')) die('Direct access not permitted');

if (!$user->isLoggedIn()) { Redirect::to(URL::build('/login')); die(); }
if (!$user->hasPermission('agallery.manage')) { require_once(ROOT_PATH . '/403.php'); die(); }

$repo = new aGalleryRepository();
$settings = new aGallerySettings($repo);
$processor = new aGalleryImageProcessor($repo, $settings);

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Token::check($_POST['token'] ?? '')) $errors[] = $language->get('agallery', 'csrf_invalid');

    $image_id = isset($_POST['image_id']) ? (int)$_POST['image_id'] : 0;
    $img = $image_id ? DB::getInstance()->query("SELECT * FROM `{$repo->t('agallery_images')}` WHERE id=?", [$image_id])->first() : null;
    if (!$img) $errors[] = $language->get('agallery', 'image_not_found');

    if (!count($errors)) {
        $now = $repo->now();

        if (isset($_POST['update'])) {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;

            if ($title === '' || mb_strlen($title) > 128) $errors[] = $language->get('agallery', 'title_invalid');
            if (mb_strlen($description) > 2000) $errors[] = $language->get('agallery', 'description_too_long');

            $cat = $repo->categoryById($category_id);
            if (!$cat) $errors[] = $language->get('agallery', 'category_invalid');

            if (!count($errors)) {
                DB::getInstance()->query(
                    "UPDATE `{$repo->t('agallery_images')}`
                     SET title=?, description=?, category_id=?, updated_at=?
                     WHERE id=?",
                    [$title, $description, $category_id, $now, $image_id]
                );
                $repo->log((int)$user->data()->id, $image_id, 'image_edit', []);
                $success = $language->get('agallery', 'saved');
            }
        }

        if (isset($_POST['delete'])) {
            // delete files
            $abs_file = ROOT_PATH . '/' . $img->file_path;
            $abs_thumb = ROOT_PATH . '/' . $img->thumb_path;
            if (is_file($abs_file)) @unlink($abs_file);
            if (is_file($abs_thumb)) @unlink($abs_thumb);

            DB::getInstance()->query("DELETE FROM `{$repo->t('agallery_images')}` WHERE id=?", [$image_id]);
            $repo->log((int)$user->data()->id, $image_id, 'image_delete', []);
            $success = $language->get('agallery', 'deleted');
        }

        if (isset($_POST['recompress'])) {
            // Re-save normalized image and regenerate thumb using current settings.
            // Uses existing file as input.
            $abs_file = ROOT_PATH . '/' . $img->file_path;

            if (!is_file($abs_file)) {
                $errors[] = $language->get('agallery', 'file_missing');
            } else {
                $info = @getimagesize($abs_file);
                if ($info === false) {
                    $errors[] = $language->get('agallery', 'not_image');
                } else {
                    $mime = $info['mime'] ?? $img->mime;
                    if (!$processor->supportsMime($mime)) {
                        $errors[] = $language->get('agallery', 'format_not_supported');
                    } else {
                        // Re-process into same paths (overwrite)
                        try {
                            // Create temp copy so processor can read safely
                            $tmp = tempnam(sys_get_temp_dir(), 'agallery_');
                            copy($abs_file, $tmp);

                            // Overwrite using current settings
                            // NOTE: keep ext/mime as stored
                            $year = date('Y', (int)$img->created_at);
                            $month = date('m', (int)$img->created_at);

                            // We rebuild paths from stored ones and just overwrite them via GD/Imagick directly:
                            // easiest: call internal processing using output paths.
                            // To keep code small, we just run processor->processUpload into same folders with new random id and then replace.
                            // But requirement says "опционально, если реализуемо без риска" — поэтому делаем безопасно:
                            // 1) generate new processed file
                            $out = $processor->processUpload($tmp, $mime, (string)$img->ext, (int)$img->user_id, 'user', (int)$img->id);

                            // 2) swap paths in DB
                            DB::getInstance()->query(
                                "UPDATE `{$repo->t('agallery_images')}` SET file_path=?, thumb_path=?, mime=?, ext=?, width=?, height=?, file_size=?, updated_at=? WHERE id=?",
                                [$out['file_path'], $out['thumb_path'], $out['mime'], $out['ext'], (int)$out['width'], (int)$out['height'], (int)$out['file_size'], $repo->now(), $image_id]
                            );

                            @unlink($tmp);
                            $repo->log((int)$user->data()->id, $image_id, 'image_recompress', []);
                            $success = $language->get('agallery', 'recompressed');
                        } catch (Throwable $e) {
                            $errors[] = $language->get('agallery', 'processing_failed');
                        }
                    }
                }
            }
        }
    }
}

$cats = $repo->categoriesAll();

$per_page = 30;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $per_page;

$total_row = DB::getInstance()->query(
    "SELECT COUNT(*) AS c FROM `{$repo->t('agallery_images')}` WHERE status='approved'"
)->first();
$total = $total_row ? (int)$total_row->c : 0;
$total_pages = (int) ceil($total / $per_page);

$list = DB::getInstance()->query(
    "SELECT i.*, u.username, c.name AS category_name
     FROM `{$repo->t('agallery_images')}` i
     LEFT JOIN `{$repo->t('users')}` u ON u.id = i.user_id
     LEFT JOIN `{$repo->t('agallery_categories')}` c ON c.id = i.category_id
     WHERE i.status='approved'
     ORDER BY i.created_at DESC
     LIMIT ? OFFSET ?",
    [$per_page, $offset]
)->results() ?: [];

$page_title = $language->get('agallery', 'staffcp_images');

require_once(ROOT_PATH . '/core/templates/panel_header.php');
require_once(ROOT_PATH . '/core/templates/panel_navbar.php');

$smarty->assign([
    'TITLE' => $page_title,
    'TABS_TEMPLATE' => 'aGallery/header_tabs.tpl',
    'ACTIVE_TAB' => 'images',

    'TOKEN' => Token::get(),
    'ERRORS' => $errors,
    'SUCCESS' => $success,

    'LIST' => $list,
    'CATEGORIES' => $cats,

    'PAGE' => $page,
    'TOTAL_PAGES' => $total_pages,
    'URL_BASE' => URL::build('/panel/agallery/images'),
]);

$template->displayTemplate('aGallery/images.tpl', $smarty);

require_once(ROOT_PATH . '/core/templates/panel_footer.php');