<?php

class aGallery_Compat {

    public static function getLanguageFromGlobals() {
        // Typical NamelessMC pages have $language global
        if (isset($GLOBALS['language'])) {
            return $GLOBALS['language'];
        }
        return null;
    }

    public static function t($language, string $key, array $vars = [], string $fallback = ''): string {
        // Try: $language->get('agallery', 'key')
        try {
            if ($language && method_exists($language, 'get')) {
                // Some installs accept ($module, $key, $vars)
                return (string)$language->get('agallery', $key, $vars);
            }
        } catch (Throwable $e) {
            // ignore
        }
        return $fallback !== '' ? $fallback : $key;
    }

    public static function url(string $path, string $params = ''): string {
        try {
            if (class_exists('URL') && method_exists('URL', 'build')) {
                return URL::build($path, $params);
            }
        } catch (Throwable $e) {
            // ignore
        }
        // Fallback
        return $params ? ($path . '?' . ltrim($params, '?')) : $path;
    }

    public static function redirect(string $url): void {
        try {
            if (class_exists('Redirect') && method_exists('Redirect', 'to')) {
                Redirect::to($url);
                return;
            }
        } catch (Throwable $e) {
            // ignore
        }
        header('Location: ' . $url);
        exit;
    }

    public static function flash(string $key, ?string $value = null): ?string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        if ($value !== null) {
            $_SESSION[$key] = $value;
            return null;
        }

        if (isset($_SESSION[$key])) {
            $v = (string)$_SESSION[$key];
            unset($_SESSION[$key]);
            return $v;
        }

        return null;
    }

    public static function tokenGet(): string {
        try {
            if (class_exists('Token') && method_exists('Token', 'get')) {
                return (string)Token::get();
            }
        } catch (Throwable $e) {
            // ignore
        }
        // Fallback: generate random (but CSRF would be weak) - better than fatal
        return bin2hex(random_bytes(16));
    }

    public static function tokenCheck(?string $token = null): bool {
        try {
            if (class_exists('Token') && method_exists('Token', 'check')) {
                return (bool)Token::check($token);
            }
        } catch (Throwable $e) {
            return false;
        }
        return false;
    }

    public static function userId($user): int {
        try {
            if ($user && method_exists($user, 'data') && is_object($user->data()) && isset($user->data()->id)) {
                return (int)$user->data()->id;
            }
            if ($user && method_exists($user, 'getId')) {
                return (int)$user->getId();
            }
        } catch (Throwable $e) {
            // ignore
        }
        return 0;
    }

    public static function username($user): string {
        try {
            if ($user && method_exists($user, 'data') && is_object($user->data()) && isset($user->data()->username)) {
                return (string)$user->data()->username;
            }
            if ($user && method_exists($user, 'getDisplayname')) {
                return (string)$user->getDisplayname();
            }
        } catch (Throwable $e) {
            // ignore
        }
        return 'user';
    }

    public static function isLoggedIn($user): bool {
        try {
            if ($user && method_exists($user, 'isLoggedIn')) {
                return (bool)$user->isLoggedIn();
            }
            if ($user && method_exists($user, 'data')) {
                return self::userId($user) > 0;
            }
        } catch (Throwable $e) {
            // ignore
        }
        return false;
    }

    public static function hasPerm($user, string $perm): bool {
        try {
            if ($user && method_exists($user, 'hasPermission')) {
                return (bool)$user->hasPermission($perm);
            }
        } catch (Throwable $e) {
            // ignore
        }
        return false;
    }

    public static function handlePanelPageLoadOrDeny($user, string $perm): void {
        // Standard mechanism
        try {
            if ($user && method_exists($user, 'handlePanelPageLoad')) {
                $ok = (bool)$user->handlePanelPageLoad($perm);
                if (!$ok) {
                    self::redirect(self::url('/panel'));
                }
                return;
            }
        } catch (Throwable $e) {
            // fallback below
        }

        if (!self::hasPerm($user, $perm)) {
            self::redirect(self::url('/panel'));
        }
    }

    public static function registerPermissions($language): void {
        // PermissionHandler expected in NamelessMC core
        if (!class_exists('PermissionHandler')) {
            return;
        }

        $perms = [
            'agallery.upload' => self::t($language, 'perm_upload', [], 'Upload to gallery'),
            'agallery.moderate' => self::t($language, 'perm_moderate', [], 'Moderate gallery uploads'),
            'agallery.manage' => self::t($language, 'perm_manage', [], 'Manage gallery'),
        ];

        try {
            if (method_exists('PermissionHandler', 'registerPermissions')) {
                PermissionHandler::registerPermissions('aGallery', $perms);
                return;
            }
        } catch (Throwable $e) {
            // ignore
        }

        // Fallback: try singular
        try {
            if (method_exists('PermissionHandler', 'registerPermission')) {
                foreach ($perms as $k => $v) {
                    PermissionHandler::registerPermission('aGallery', $k, $v);
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    public static function addTopNavItem($language, array $navs): void {
        $title = self::t($language, 'nav_gallery', [], 'Галлерея');
        $link = self::url('/gallery');

        foreach ($navs as $nav) {
            try {
                if ($nav && is_object($nav) && method_exists($nav, 'add')) {
                    // Navigation::add(name, title, link, location='top', target=null, order=10)
                    $nav->add('gallery', $title, $link, 'top', null, 10);
                    return;
                }
            } catch (Throwable $e) {
                // continue
            }
        }
    }

    public static function getDbPrefix(): string {
        try {
            if (class_exists('Config') && method_exists('Config', 'get')) {
                $p = (string)Config::get('mysql/prefix');
                return $p;
            }
        } catch (Throwable $e) {
            // ignore
        }

        // Some DB implementations expose prefix
        try {
            $db = DB::getInstance();
            if (method_exists($db, 'getPrefix')) {
                return (string)$db->getPrefix();
            }
        } catch (Throwable $e) {
            // ignore
        }

        return '';
    }

    public static function table(string $name): string {
        $prefix = self::getDbPrefix();
        return $prefix . $name;
    }

    public static function getUserGroupIds($user, DB $db): array {
        $uid = self::userId($user);
        if ($uid <= 0) return [];

        // Try built-in methods first
        try {
            if ($user && method_exists($user, 'getAllGroups')) {
                $groups = $user->getAllGroups();
                if (is_array($groups)) {
                    $ids = [];
                    foreach ($groups as $g) {
                        if (is_object($g) && isset($g->id)) $ids[] = (int)$g->id;
                        if (is_array($g) && isset($g['id'])) $ids[] = (int)$g['id'];
                    }
                    return array_values(array_unique($ids));
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        // Fallback DB query
        try {
            $t = self::table('users_groups');
            $res = $db->query("SELECT group_id FROM `{$t}` WHERE user_id = ?", [$uid])->results();
            $ids = [];
            foreach ($res as $row) {
                if (isset($row->group_id)) $ids[] = (int)$row->group_id;
            }
            return array_values(array_unique($ids));
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function getUserIdsWithPermission(DB $db, string $permission): array {
        // Try fast path: join groups_permissions + users_groups
        try {
            $tUG = self::table('users_groups');
            $tGP = self::table('groups_permissions');

            // common columns: group_id, permission, value
            $rows = $db->query(
                "SELECT DISTINCT ug.user_id
                 FROM `{$tUG}` ug
                 INNER JOIN `{$tGP}` gp ON gp.group_id = ug.group_id
                 WHERE gp.permission = ? AND (gp.value = 1 OR gp.value = '1' OR gp.value = 'true')",
                [$permission]
            )->results();

            $ids = [];
            foreach ($rows as $r) {
                if (isset($r->user_id)) $ids[] = (int)$r->user_id;
            }
            return array_values(array_unique($ids));
        } catch (Throwable $e) {
            // fallback below
        }

        // Slow fallback: scan all users and check hasPermission via User class (may be heavy)
        $ids = [];
        try {
            $tU = self::table('users');
            $rows = $db->query("SELECT id FROM `{$tU}`", [])->results();

            foreach ($rows as $r) {
                $id = (int)($r->id ?? 0);
                if ($id <= 0) continue;

                try {
                    $u = new User($id);
                    if ($u && method_exists($u, 'hasPermission') && $u->hasPermission($permission)) {
                        $ids[] = $id;
                    }
                } catch (Throwable $e2) {
                    // ignore
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        return array_values(array_unique($ids));
    }

    public static function notifyMany(DB $db, int $authorId, array $recipientIds, string $title, string $content, ?string $alertUrl = null): void {
        $recipientIds = array_values(array_unique(array_filter($recipientIds, fn($x) => (int)$x > 0)));
        if (!count($recipientIds)) return;

        // Preferred: Notification (per your critical requirement)
        try {
            if (class_exists('Notification')) {
                // __construct(string $type, string|LanguageKey $title, string|LanguageKey $content,
                // int|int[] $recipients, int $authorId, callable|null $contentCallback=null, bool $skipPurify=false, string|null $alertUrl=null)
                $n = new Notification('alert', $title, $content, $recipientIds, $authorId, null, false, $alertUrl);
                if (method_exists($n, 'send')) {
                    $n->send();
                    return;
                }
            }
        } catch (Throwable $e) {
            // fallback below
        }

        // Fallback: Alert::send
        try {
            if (class_exists('Alert') && method_exists('Alert', 'send')) {
                foreach ($recipientIds as $uid) {
                    Alert::send((int)$uid, $title, $content, $alertUrl ?? '', false);
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
    }
}
