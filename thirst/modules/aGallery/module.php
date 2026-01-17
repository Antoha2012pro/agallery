<?php
/**
 * aGallery module for NamelessMC v2.2.4
 */

if (!defined('ROOT_PATH')) {
    die('ROOT_PATH not defined');
}

require_once(ROOT_PATH . '/modules/aGallery/includes/autoload.php');

class aGallery_Module extends Module {

    public function __construct() {
        // В NamelessMC: (module, name, author, module_version, nameless_version, load_before, load_after)
        parent::__construct(
            $this,
            'aGallery',
            'ORBitium',
            '1.0.0',
            '2.2.4',
            [],
            ['Core']
        );
    }

    public function onInstall() {
        try {
            if (class_exists('DB')) {
                $db = DB::getInstance();

                aGallery_Settings::install($db);
                aGallery_Categories::install($db);
                aGallery_Images::install($db);
                aGallery_Audit::install($db);
            }

            aGallery_Storage::ensureBaseDirs();
        } catch (Throwable $e) {
            error_log('[aGallery] onInstall crash: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    public function onUninstall() {
        // Данные не трогаем намеренно.
    }

    public function onEnable() {
        try {
            aGallery_Storage::ensureBaseDirs();
        } catch (Throwable $e) {
            error_log('[aGallery] onEnable crash: ' . $e->getMessage());
        }
    }

    public function onDisable() {
        // No-op
    }

    private function _fallbackNavLabel($language): string {
        $label = '';

        try {
            if (is_object($language) && method_exists($language, 'get')) {
                $label = (string) $language->get('agallery', 'nav_gallery');
            }
        } catch (Throwable $e) {
            // ignore
        }

        // Если ключ не найден — у тебя показывалось "agallery/nav_gallery"
        if ($label === '' || $label === 'agallery/nav_gallery' || (function_exists('str_contains') && str_contains($label, 'Term '))) {
            $label = 'Галлерея';
        }

        return $label;
    }

    public function onPageLoad(User $user, Pages $pages, Cache $cache, $smarty, array $navs, Widgets $widgets, TemplateBase $template) {
        try {
            $language = aGallery_Compat::getLanguageFromGlobals();

            // 1) Регистрируем страницы
            // Pages::add(string $module, string $url, string $file, string $name='', bool $widgets=false) 
            $pages->add('aGallery', 'gallery', 'pages/gallery.php', 'agallery', true);
            $pages->add('aGallery', 'gallery/upload', 'pages/upload.php', 'agallery_upload', false);
            $pages->add('aGallery', 'gallery/view', 'pages/view.php', 'agallery_view', true);

            // StaffCP (Panel)
            $pages->add('aGallery', 'panel/agallery/categories', 'pages/panel/categories.php', 'agallery_categories', false);
            $pages->add('aGallery', 'panel/agallery/moderation', 'pages/panel/moderation.php', 'agallery_moderation', false);
            $pages->add('aGallery', 'panel/agallery/images', 'pages/panel/images.php', 'agallery_images', false);
            $pages->add('aGallery', 'panel/agallery/settings', 'pages/panel/settings.php', 'agallery_settings', false);

            // 2) Права (если твой Compat это умеет) — оборачиваем, чтобы не роняло сайт
            try {
                aGallery_Compat::registerPermissions($language);
            } catch (Throwable $e) {
                error_log('[aGallery] registerPermissions crash: ' . $e->getMessage());
            }

            // 3) Навигация
            // В NamelessMC модульной доке: $navs[0]=top navbar, $navs[1]=user dropdown, $navs[2]=StaffCP sidebar :contentReference[oaicite:2]{index=2}
            $label = $this->_fallbackNavLabel($language);

            // Frontend TOP
            if (isset($navs[0]) && $navs[0] instanceof Navigation) {
                $link = class_exists('URL') ? URL::build('/gallery') : '/gallery';
                $navs[0]->add('agallery', $label, $link, 'top', null, 20, '');
            }

            // StaffCP sidebar (чтобы пункты были в панели)
            if (isset($navs[2]) && $navs[2] instanceof Navigation) {
                $settings_link = class_exists('URL') ? URL::build('/panel/agallery/settings') : '/panel/agallery/settings';
                $navs[2]->add('agallery', $label, $settings_link, 'top', null, 50, '');

                $cats_link = class_exists('URL') ? URL::build('/panel/agallery/categories') : '/panel/agallery/categories';
                $navs[2]->add('agallery_categories', $label . ' - Categories', $cats_link, 'agallery', null, 51, '');

                $mod_link = class_exists('URL') ? URL::build('/panel/agallery/moderation') : '/panel/agallery/moderation';
                $navs[2]->add('agallery_moderation', $label . ' - Moderation', $mod_link, 'agallery', null, 52, '');
            }

            // 4) uploads/ структура
            aGallery_Storage::ensureBaseDirs();

        } catch (Throwable $e) {
            error_log('[aGallery] onPageLoad crash: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    public function getDebugInfo(): array {
        return [
            'module' => 'aGallery',
            'version' => '1.0.0',
            'uploads_base' => class_exists('aGallery_Storage') ? aGallery_Storage::baseDirFs() : 'n/a',
            'php_gd' => extension_loaded('gd') ? 'yes' : 'no',
            'php_imagick' => extension_loaded('imagick') ? 'yes' : 'no',
        ];
    }
}
