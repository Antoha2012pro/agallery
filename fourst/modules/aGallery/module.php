<?php
// /modules/aGallery/module.php

class aGallery_Module extends Module {

    private ?Language $_language = null;
    private mixed $_queries = null;

    public function __construct(?Language $language = null, mixed $queries = null) {
        $name = 'aGallery!';
        $author = 'Orbitium';
        $module_version = '1.0.0';
        $nameless_version = '2.2.0';

        parent::__construct($this, $name, $author, $module_version, $nameless_version);

        // Сохраняем ссылки (если передали)
        $this->_language = $language;
        $this->_queries = $queries;
    }

    // В NamelessMC 2.2.x эти методы обязательны (и важна совместимость сигнатуры/return type).
    public function onInstall(): mixed {
        return null;
    }

    public function onUninstall(): mixed {
        return null;
    }

    public function onEnable(): mixed {
        return null;
    }

    public function onDisable(): mixed {
        return null;
    }

    public function onPageLoad(
        User $user,
        Pages $pages,
        Cache $cache,
        FakeSmarty|Smarty|null $smarty,
        array $navs,
        Widgets $widgets,
        TemplateBase|null $template
    ): mixed {
        // Достаём Language максимально безопасно
        $language = $this->_language ?? ($GLOBALS['language'] ?? null);

        // 1) Регистрируем страницы (URL лучше с ведущим '/')
        // Сигнатура Pages::add: add($module, $url, $file, $name = '', $widgets = false) :contentReference[oaicite:3]{index=3}
        try {
            $pages->add('aGallery', '/gallery', 'modules/aGallery/pages/gallery.php', 'agallery', true);
            $pages->add('aGallery', '/gallery/upload', 'modules/aGallery/pages/upload.php', 'agallery_upload', false);
            $pages->add('aGallery', '/panel/agallery', 'modules/aGallery/pages/panel/index.php', 'agallery_panel', false);
        } catch (Throwable $e) {
            error_log('[aGallery] pages->add failed: ' . $e->getMessage());
        }

        // 2) Добавляем пункт в NAV (frontend top) + StaffCP sidebar
        // Navigation::add($name, $title, $link, $location='top', $target=null, $order=10, $icon='') :contentReference[oaicite:4]{index=4}
        $title = $language ? $language->get('agallery', 'gallery') : 'Gallery';
        $icon = 'fa-solid fa-images';

        try {
            if (isset($navs[0]) && $navs[0] instanceof Navigation) {
                $navs[0]->add('agallery', $title, URL::build('/gallery'), 'top', null, 10, $icon);
            }
        } catch (Throwable $e) {
            error_log('[aGallery] navs[0] add failed: ' . $e->getMessage());
        }

        try {
            if (defined('BACK_END') && isset($navs[2]) && $navs[2] instanceof Navigation) {
                $navs[2]->add('agallery', $title, URL::build('/panel/agallery'), 'panel', null, 10, $icon);
            }
        } catch (Throwable $e) {
            error_log('[aGallery] navs[2] add failed: ' . $e->getMessage());
        }

        return null;
    }

    public function getDebugInfo(): array {
        return [
            'module' => 'aGallery',
            'version' => '1.0.0',
        ];
    }
}
