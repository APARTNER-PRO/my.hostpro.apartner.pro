<?php

class Database
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance) return self::$instance;

        $cfg    = require __DIR__ . '/../config/config.php';
        $driver = $cfg['db_driver'] ?? 'sqlite';

        $pdo = match ($driver) {
            'mariadb', 'mysql' => self::connectMariaDB($cfg),
            default            => self::connectSQLite($cfg),
        };

        $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,   false);

        self::migrate($pdo, $driver);
        self::$instance = $pdo;
        return $pdo;
    }

    // ── SQLite ────────────────────────────────────────────────────────────────
    private static function connectSQLite(array $cfg): PDO
    {
        $path = $cfg['db_path'];
        $dir  = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $pdo = new PDO("sqlite:$path");
        $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
        return $pdo;
    }

    // ── MariaDB / MySQL ───────────────────────────────────────────────────────
    private static function connectMariaDB(array $cfg): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['db_host'],
            $cfg['db_port'],
            $cfg['db_name'],
            $cfg['db_charset']
        );

        return new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
            PDO::MYSQL_ATTR_INIT_COMMAND     => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::MYSQL_ATTR_FOUND_ROWS       => true,
            PDO::ATTR_TIMEOUT                => 5,
        ]);
    }

    // ── Migrations (dialect-aware) ────────────────────────────────────────────
    private static function migrate(PDO $pdo, string $driver): void
    {
        $isMariaDB = in_array($driver, ['mariadb', 'mysql'], true);

        if ($isMariaDB) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    email      VARCHAR(255)    NOT NULL,
                    password   VARCHAR(255)    NOT NULL,
                    role       ENUM('admin','client') NOT NULL DEFAULT 'client',
                    name       VARCHAR(255)    DEFAULT NULL,
                    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS paddle_cache (
                    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    email      VARCHAR(255)    NOT NULL,
                    raw_json   MEDIUMTEXT      NOT NULL,
                    fetched_at INT UNSIGNED    NOT NULL,
                    INDEX idx_email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS event_logs (
                    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    event      VARCHAR(100)    NOT NULL,
                    email      VARCHAR(255)    DEFAULT NULL,
                    level      ENUM('info','warning','error') NOT NULL DEFAULT 'info',
                    message    TEXT            NOT NULL,
                    context    JSON            DEFAULT NULL,
                    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_email (email),
                    INDEX idx_event (event),
                    INDEX idx_level (level),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS webhook_log (
                    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    event_type   VARCHAR(100)  NOT NULL,
                    paddle_id    VARCHAR(100)  DEFAULT NULL,
                    email        VARCHAR(255)  DEFAULT NULL,
                    status       ENUM('ok','error','ignored') NOT NULL DEFAULT 'ok',
                    payload      MEDIUMTEXT    NOT NULL,
                    error        TEXT          DEFAULT NULL,
                    processed_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_event_type (event_type),
                    INDEX idx_email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tickets (
                    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    user_id    INT UNSIGNED    NOT NULL,
                    subject    VARCHAR(255)    NOT NULL,
                    status     ENUM('open','pending','replied','closed') NOT NULL DEFAULT 'open',
                    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS invoices (
                    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    user_id    INT UNSIGNED    NOT NULL,
                    amount     DECIMAL(10,2)   NOT NULL,
                    currency   VARCHAR(3)      NOT NULL DEFAULT 'EUR',
                    status     ENUM('unpaid','paid','cancelled','refunded') NOT NULL DEFAULT 'unpaid',
                    due_date   DATE            NOT NULL,
                    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS ticket_messages (
                    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    ticket_id   INT UNSIGNED    NOT NULL,
                    sender_id   INT UNSIGNED    NOT NULL,
                    sender_role ENUM('admin','client') NOT NULL,
                    message     TEXT            NOT NULL,
                    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS settings (
                    `key`   VARCHAR(255) NOT NULL PRIMARY KEY,
                    `value` TEXT NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $pdo->exec("INSERT IGNORE INTO settings (`key`, `value`) VALUES ('payment_methods', '{\"paddle\":true,\"monobank\":false,\"wayforpay\":false}')");

        } else {
            // SQLite
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    email      TEXT    UNIQUE NOT NULL,
                    password   TEXT    NOT NULL,
                    role       TEXT    NOT NULL DEFAULT 'client',
                    name       TEXT,
                    created_at TEXT    DEFAULT (datetime('now')),
                    updated_at TEXT    DEFAULT (datetime('now'))
                );
                CREATE TABLE IF NOT EXISTS paddle_cache (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    email      TEXT    NOT NULL,
                    raw_json   TEXT    NOT NULL,
                    fetched_at INTEGER NOT NULL
                );
                CREATE TABLE IF NOT EXISTS event_logs (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    event      TEXT    NOT NULL,
                    email      TEXT    DEFAULT NULL,
                    level      TEXT    NOT NULL DEFAULT 'info',
                    message    TEXT    NOT NULL,
                    context    TEXT    DEFAULT NULL,
                    created_at TEXT    DEFAULT (datetime('now'))
                );
                CREATE TABLE IF NOT EXISTS webhook_log (
                    id           INTEGER PRIMARY KEY AUTOINCREMENT,
                    event_type   TEXT    NOT NULL,
                    paddle_id    TEXT    DEFAULT NULL,
                    email        TEXT    DEFAULT NULL,
                    status       TEXT    NOT NULL DEFAULT 'ok',
                    payload      TEXT    NOT NULL,
                    error        TEXT    DEFAULT NULL,
                    processed_at TEXT    DEFAULT (datetime('now'))
                );
                CREATE TABLE IF NOT EXISTS tickets (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id    INTEGER NOT NULL,
                    subject    TEXT    NOT NULL,
                    status     TEXT    NOT NULL DEFAULT 'open',
                    created_at TEXT    DEFAULT (datetime('now')),
                    updated_at TEXT    DEFAULT (datetime('now')),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                );
                CREATE TABLE IF NOT EXISTS invoices (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id    INTEGER NOT NULL,
                    amount     REAL    NOT NULL,
                    currency   TEXT    NOT NULL DEFAULT 'EUR',
                    status     TEXT    NOT NULL DEFAULT 'unpaid',
                    due_date   TEXT    NOT NULL,
                    created_at TEXT    DEFAULT (datetime('now')),
                    updated_at TEXT    DEFAULT (datetime('now')),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                );
                CREATE TABLE IF NOT EXISTS ticket_messages (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    ticket_id   INTEGER NOT NULL,
                    sender_id   INTEGER NOT NULL,
                    sender_role TEXT    NOT NULL,
                    message     TEXT    NOT NULL,
                    created_at  TEXT    DEFAULT (datetime('now')),
                    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
                );
                CREATE TABLE IF NOT EXISTS settings (
                    key   TEXT NOT NULL PRIMARY KEY,
                    value TEXT NOT NULL
                );
                INSERT OR IGNORE INTO settings (key, value) VALUES ('payment_methods', '{\"paddle\":true,\"monobank\":false,\"wayforpay\":false}');
            ");
        }
    }
}
