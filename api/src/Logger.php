<?php

class Logger
{
    // ── Рівні ─────────────────────────────────────────────────────────────────
    public static function info(string $event, string $message, ?string $email = null, array $context = []): void
    {
        self::write('info', $event, $message, $email, $context);
    }

    public static function warning(string $event, string $message, ?string $email = null, array $context = []): void
    {
        self::write('warning', $event, $message, $email, $context);
    }

    public static function error(string $event, string $message, ?string $email = null, array $context = []): void
    {
        self::write('error', $event, $message, $email, $context);
    }

    // ── Webhook лог ───────────────────────────────────────────────────────────
    public static function webhook(
        string  $eventType,
        string  $status,       // ok | error | ignored
        string  $payload,
        ?string $email     = null,
        ?string $paddleId  = null,
        ?string $error     = null
    ): void {
        try {
            $db   = Database::get();
            $stmt = $db->prepare("
                INSERT INTO webhook_log (event_type, paddle_id, email, status, payload, error)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$eventType, $paddleId, $email, $status, $payload, $error]);
        } catch (\Throwable $e) {
            // Не ломаємо основний потік якщо лог не записався
            error_log('[Logger::webhook] ' . $e->getMessage());
        }
    }

    // ── Отримати логи (для адмін-панелі) ─────────────────────────────────────
    public static function getLogs(int $limit = 100, ?string $level = null, ?string $email = null): array
    {
        $db     = Database::get();
        $where  = [];
        $params = [];

        if ($level) { $where[] = 'level = ?'; $params[] = $level; }
        if ($email) { $where[] = 'email = ?'; $params[] = $email; }

        $sql = 'SELECT * FROM event_logs'
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY created_at DESC LIMIT ?';
        $params[] = $limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getWebhookLogs(int $limit = 50): array
    {
        $db   = Database::get();
        $stmt = $db->prepare('SELECT * FROM webhook_log ORDER BY processed_at DESC LIMIT ?');
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    // ── Internal ──────────────────────────────────────────────────────────────
    private static function write(string $level, string $event, string $message, ?string $email, array $context): void
    {
        try {
            $db   = Database::get();
            $stmt = $db->prepare("
                INSERT INTO event_logs (event, email, level, message, context)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $event,
                $email,
                $level,
                $message,
                $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            error_log('[Logger] ' . $e->getMessage());
        }
    }
}
