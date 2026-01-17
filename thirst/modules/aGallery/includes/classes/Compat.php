<?php

class aGallery_Compat {

    public static function lang(string $term, string $section = 'general', array $vars = []): string {
        global $language;
        if (isset($language) && $language) {
            return $language->get('aGallery', $term, $section, $vars);
        }
        // Fallback: term as-is
        return $term;
    }

    public static function tokenField(): string {
        $token = '';
        if (class_exists('Token') && method_exists('Token', 'get')) {
            $token = Token::get();
        }
        return '<input type="hidden" name="token" value="' . aGallery_Utils::h($token) . '">';
    }

    public static function checkToken(?string $token): bool {
        if (!class_exists('Token') || !method_exists('Token', 'check')) {
            return true; // fallback: do not block if Token missing (should not happen)
        }
        return Token::check((string)$token);
    }

    public static function flash(string $key, string $value): void {
        if (class_exists('Session') && method_exists('Session', 'flash')) {
            Session::flash($key, $value);
        }
    }

    public static function getFlash(string $key): ?string {
        if (class_exists('Session') && method_exists('Session', 'exists') && method_exists('Session', 'flash')) {
            if (Session::exists($key)) {
                return Session::flash($key);
            }
        }
        return null;
    }

    public static function redirect(string $path): void {
        if (class_exists('Redirect') && method_exists('Redirect', 'to')) {
            Redirect::to($path);
            exit;
        }
        header('Location: ' . $path);
        exit;
    }

    public static function requireFrontendInit(): void {
        $candidates = [
            ROOT_PATH . '/core/templates/frontend_init.php',
            ROOT_PATH . '/core/templates/portal_init.php',
        ];
        foreach ($candidates as $f) {
            if (is_file($f)) {
                require_once $f;
                return;
            }
        }
        // Fallback: nothing
    }

    public static function requireBackendInit(): void {
        $candidates = [
            ROOT_PATH . '/core/templates/backend_init.php',
            ROOT_PATH . '/panel/backend_init.php',
            ROOT_PATH . '/core/templates/panel_init.php',
        ];
        foreach ($candidates as $f) {
            if (is_file($f)) {
                require_once $f;
                return;
            }
        }
        // Fallback: nothing
    }

    public static function displaySmartyOrEcho($smarty, ?string $tplRelPath, string $fallbackHtml): void {
        // Best effort: try Smarty display if possible.
        if ($smarty && method_exists($smarty, 'display') && $tplRelPath) {
            try {
                $smarty->display($tplRelPath);
                return;
            } catch (Throwable $e) {
                // ignore and fallback
            }
        }
        echo $fallbackHtml;
    }

    /**
     * Register custom notification types for preferences page.
     * Notification::addType(type,value,defaultPreferences) :contentReference[oaicite:4]{index=4}
     */
    public static function registerNotificationTypes(): void {
        if (!class_exists('Notification') || !method_exists('Notification', 'addType') || !method_exists('Notification', 'getTypes')) {
            return;
        }

        try {
            $types = Notification::getTypes();
            if (!isset($types['agallery_moderation'])) {
                Notification::addType('agallery_moderation', self::lang('notif_type_moderation', 'notifications'));
            }
            if (!isset($types['agallery_result'])) {
                Notification::addType('agallery_result', self::lang('notif_type_result', 'notifications'));
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    /**
     * Send via Notification (preferred), fallback to Alert::send.
     * Notification::__construct(type,title,content,recipients,authorId,contentCallback,skipPurify,alertUrl) + ->send() :contentReference[oaicite:5]{index=5}
     */
    public static function notify(string $type, $title, $content, $recipients, int $authorId, ?string $alertUrl = null, $contentCallback = null): void {
        if (class_exists('Notification')) {
            try {
                $n = new Notification($type, $title, $content, $recipients, $authorId, $contentCallback, false, $alertUrl);
                $n->send();
                return;
            } catch (Throwable $e) {
                // fallback below
            }
        }

        // Fallback: Alert::send(userId, title, content, url) 
        if (class_exists('Alert') && method_exists('Alert', 'send')) {
            $ids = is_array($recipients) ? $recipients : [$recipients];
            foreach ($ids as $uid) {
                try {
                    Alert::send((int)$uid, (string)$title, (string)$content, $alertUrl ?? '');
                } catch (Throwable $e) {
                    // ignore
                }
            }
        }
    }
}
