<?php

/**
 * Compat слой для интеграции с NamelessMC:
 * - CSRF Token
 * - Alerts
 * - Notification type
 * - Permission checks
 *
 * Все сомнительные места обёрнуты в проверки наличия классов/методов.
 */
class AGalleryCompat {

    public const NOTIFICATION_TYPE = 'agallery_moderation';

    /**
     * Проверка CSRF токена.
     */
    public static function checkToken(string $name, string $errorMessage): void {
        if (class_exists('Token') && method_exists('Token', 'check')) {
            if (!Token::check($name)) {
                die($errorMessage);
            }
        }
    }

    /**
     * Генерация CSRF hidden поля.
     */
    public static function generateTokenInput(string $name = 'token'): string {
        if (class_exists('Token') && method_exists('Token', 'get')) {
            $token = Token::get($name);
            return '<input type="hidden" name="' . Output::getClean($name) . '" value="' . Output::getClean($token) . '">';
        }
        return '';
    }

    /**
     * Регистрация типа уведомлений для Notification.
     */
    public static function registerNotificationType(): void {
        if (class_exists('Notification') && method_exists('Notification', 'addType') && class_exists('Module')) {
            $moduleId = Module::getIdFromName('aGallery');
            Notification::addType(
                self::NOTIFICATION_TYPE,
                'aGallery moderation',
                $moduleId,
                [
                    'alert' => true,
                    'email' => false,
                ]
            );
        }
    }

    /**
     * Отправка уведомления модераторам о новой заявке.
     *
     * @param int   $authorId
     * @param int[] $recipientIds
     * @param string $title
     * @param string $content
     */
    public static function sendModerationNotification(int $authorId, array $recipientIds, string $title, string $content): void {
        if (!class_exists('Notification')) {
            return;
        }

        try {
            $callback = static function (int $recipient, string $t, string $c, bool $skipPurify) {
                return $c;
            };

            $notification = new Notification(
                self::NOTIFICATION_TYPE,
                $title,
                $content,
                $recipientIds,
                $authorId,
                $callback,
                false
            );
            $notification->send();
        } catch (Exception $e) {
            // Fail silently.
        }
    }

    /**
     * Отправка Alert пользователю.
     */
    public static function sendAlert(int $userId, string $title, string $content, bool $skipPurify = false): void {
        if (class_exists('Alert') && method_exists('Alert', 'send')) {
            Alert::send($userId, $title, $content, null, $skipPurify);
        }
    }

    /**
     * Выбрать ID пользователей, у которых есть указанное право.
     * Здесь предполагается, что:
     * - Есть класс User с методом hasPermission
     * - DB::getInstance()->query(.. nl2_users ..)
     *
     * Точку нужно проверить по месту.
     */
    public static function getUsersWithPermission(string $permission): array {
        $ids = [];
        try {
            $db = DB::getInstance();
            $users = $db->query('SELECT id FROM nl2_users', [])->results();
            foreach ($users as $u) {
                $user = new User($u->id);
                if ($user->hasPermission($permission)) {
                    $ids[] = (int)$u->id;
                }
            }
        } catch (Exception $e) {
            // Fail silently.
        }
        return $ids;
    }
}
