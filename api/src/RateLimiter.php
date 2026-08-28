<?php

/**
 * RateLimiter — захист від брутфорсу при авторизації.
 *
 * Логіка:
 *  - Зберігає спроби у таблиці `login_attempts` (ip + email).
 *  - Після MAX_ATTEMPTS невдалих спроб за WINDOW_SECONDS — блокує на LOCKOUT_SECONDS.
 *  - Успішний вхід скидає лічильник для цього IP/email.
 */
class RateLimiter
{
    // ── Конфіг ────────────────────────────────────────────────────────────────
    private const MAX_ATTEMPTS     = 5;    // макс. невдалих спроб
    private const WINDOW_SECONDS   = 900;  // вікно спостереження: 15 хв
    private const LOCKOUT_SECONDS  = 900;  // блокування: 15 хв

    // ── Перевірити, чи IP/email заблокований ──────────────────────────────────
    /**
     * @return array{blocked: bool, attempts: int, retry_after: int|null}
     */
    public static function check(string $ip, string $email): array
    {
        self::ensureTable();
        $db  = Database::get();
        $now = time();
        $win = $now - self::WINDOW_SECONDS;

        // Кількість невдалих спроб за IP або email в межах вікна
        $stmt = $db->prepare("
            SELECT COUNT(*) AS cnt, MAX(attempted_at) AS last_at
            FROM login_attempts
            WHERE (ip = ? OR email = ?)
              AND attempted_at > ?
        ");
        $stmt->execute([$ip, $email, date('Y-m-d H:i:s', $win)]);
        $row = $stmt->fetch();

        $attempts = (int)($row['cnt'] ?? 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            $lastAt     = strtotime($row['last_at'] ?? 'now');
            $retryAfter = ($lastAt + self::LOCKOUT_SECONDS) - $now;
            $retryAfter = max(0, $retryAfter);

            return [
                'blocked'     => true,
                'attempts'    => $attempts,
                'retry_after' => $retryAfter,
            ];
        }

        return [
            'blocked'     => false,
            'attempts'    => $attempts,
            'retry_after' => null,
        ];
    }

    // ── Записати невдалу спробу ───────────────────────────────────────────────
    public static function recordFailure(string $ip, string $email): int
    {
        self::ensureTable();
        $db   = Database::get();
        $stmt = $db->prepare("
            INSERT INTO login_attempts (ip, email, attempted_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$ip, $email]);

        // Повернути поточну кількість спроб
        $win  = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
        $cnt  = $db->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE (ip = ? OR email = ?) AND attempted_at > ?
        ");
        $cnt->execute([$ip, $email, $win]);
        return (int)$cnt->fetchColumn();
    }

    // ── Скинути лічильник після успішного входу ───────────────────────────────
    public static function reset(string $ip, string $email): void
    {
        self::ensureTable();
        $db   = Database::get();
        $stmt = $db->prepare("
            DELETE FROM login_attempts
            WHERE ip = ? OR email = ?
        ");
        $stmt->execute([$ip, $email]);
    }

    // ── Автоматично створити таблицю, якщо не існує ───────────────────────────
    private static function ensureTable(): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        $db = Database::get();
        $db->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ip           VARCHAR(45)  NOT NULL,
                email        VARCHAR(255) NOT NULL,
                attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ip_time    (ip, attempted_at),
                INDEX idx_email_time (email, attempted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Видалити старі записи (старші за 24 год) — щоб таблиця не росла
        $db->exec("
            DELETE FROM login_attempts
            WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
    }
}
