<?php

return [
    // =========================================================================
    // URLs
    // =========================================================================

    // URL фронтенду — для CORS whitelist і посилань в email
    // Приклад: https://billing.vercel.app  або  https://programist.com.ua
    'frontend_url' => rtrim($_ENV['FRONTEND_URL'] ?? '', '/'),

    // URL бекенду — для посилань (webhook, email тощо)
    // Приклад: https://programist.com.ua  або  http://localhost:8000
    'backend_url'  => rtrim($_ENV['BACKEND_URL'] ?? '', '/'),

    // Режим деплою: 'same-domain' | 'split'
    //   same-domain — фронт і бек на одному домені/папці
    //   split       — фронт на Vercel, бек на окремому домені
    'deploy_mode'  => $_ENV['DEPLOY_MODE'] ?? 'split',

    // =========================================================================
    // CORS
    // =========================================================================
    // Автоматично дозволяємо FRONTEND_URL.
    // EXTRA_ORIGINS — додаткові через кому (localhost для розробки тощо)
    // При DEPLOY_MODE=same-domain CORS не потрібен — запити з того ж домену
    'allowed_origins' => array_unique(array_filter(array_merge(
        [rtrim($_ENV['FRONTEND_URL'] ?? '', '/')],
        array_map('trim', explode(',', $_ENV['EXTRA_ORIGINS'] ?? ''))
    ))),

    // =========================================================================
    // JWT
    // =========================================================================
    'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'CHANGE_ME_32CHARS_RANDOM_STRING!!',
    'jwt_ttl'    => (int)($_ENV['JWT_TTL'] ?? 86400),

    // =========================================================================
    // Paddle
    // =========================================================================
    'paddle_api_key'        => trim($_ENV['PADDLE_API_KEY'] ?? ''),
    'paddle_client_token'   => trim($_ENV['PADDLE_CLIENT_TOKEN'] ?? ''),
    'paddle_env'            => $_ENV['PADDLE_ENV'] ?? 'sandbox',
    'paddle_api_url'        => (($_ENV['PADDLE_ENV'] ?? '') === 'production')
                                    ? 'https://api.paddle.com'
                                    : 'https://sandbox-api.paddle.com',
    'paddle_vendor_id'      => $_ENV['PADDLE_VENDOR_ID']      ?? '',
    'paddle_webhook_secret' => $_ENV['PADDLE_WEBHOOK_SECRET'] ?? '',

    // =========================================================================
    // Database  (DB_DRIVER=sqlite|mariadb)
    // =========================================================================
    'db_driver'  => $_ENV['DB_DRIVER'] ?? 'sqlite',
    'db_path'    => $_ENV['DB_PATH']   ?? __DIR__ . '/../database/billing.sqlite',
    'db_host'    => $_ENV['DB_HOST']   ?? '127.0.0.1',
    'db_port'    => $_ENV['DB_PORT']   ?? '3306',
    'db_name'    => $_ENV['DB_NAME']   ?? 'billing',
    'db_user'    => $_ENV['DB_USER']   ?? 'billing_user',
    'db_pass'    => $_ENV['DB_PASS']   ?? '',
    'db_charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',

    // =========================================================================
    // Email / SMTP
    // =========================================================================
    'mail_from'      => $_ENV['MAIL_FROM']      ?? ('noreply@' . parse_url($_ENV['FRONTEND_URL'] ?? 'example.com', PHP_URL_HOST)),
    'mail_from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Billing Portal',
    'billing_url'    => rtrim($_ENV['FRONTEND_URL'] ?? '', '/'),

    'smtp_host' => $_ENV['SMTP_HOST'] ?? '',
    'smtp_port' => $_ENV['SMTP_PORT'] ?? '587',
    'smtp_user' => $_ENV['SMTP_USER'] ?? '',
    'smtp_pass' => $_ENV['SMTP_PASS'] ?? '',

    // =========================================================================
    // WHM / cPanel
    // =========================================================================
    'whm_host'                  => $_ENV['WHM_HOST']          ?? 'rocket-cms3.hostsila.org',
    'whm_user'                  => $_ENV['WHM_USER']          ?? 'wedbkrdb',
    'whm_token'                 => $_ENV['WHM_TOKEN']         ?? '',
    'whm_plan'                  => $_ENV['WHM_PLAN']          ?? 'wedbkrdb_Personal',
    'whm_default_domain_prefix' => $_ENV['WHM_DOMAIN_PREFIX'] ?? '',

    // =========================================================================
    // Admin & AI
    // =========================================================================
    'openrouter_api_key' => $_ENV['OPENROUTER_API_KEY'] ?? '',
    'admin_email'    => $_ENV['ADMIN_EMAIL']    ?? 'admin@example.com',
    'admin_password' => $_ENV['ADMIN_PASSWORD'] ?? 'change_me_in_env',
];
