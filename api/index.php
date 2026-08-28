<?php
declare(strict_types=1);

// ── Bootstrap ─────────────────────────────────────────────────────────────────
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
        putenv(trim($k) . '=' . trim($v));
    }
}

require_once __DIR__ . '/src/JWT.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/AuthMiddleware.php';
require_once __DIR__ . '/src/PaddleService.php';
require_once __DIR__ . '/src/WhmService.php';
require_once __DIR__ . '/src/Logger.php';
require_once __DIR__ . '/src/Mailer.php';
require_once __DIR__ . '/src/PaddleWebhook.php';

$cfg  = require __DIR__ . '/config/config.php';

// ── CORS ──────────────────────────────────────────────────────────────────────
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$corsOrigin    = '';

if ($cfg['deploy_mode'] === 'same-domain' || !$requestOrigin) {
    // Same-domain: браузер не шле Origin для same-origin запитів
    // Webhook від Paddle: теж без Origin — пропускаємо
    $corsOrigin = '';
} else {
    $allowed = $cfg['allowed_origins'];

    // Якщо whitelist порожній або [''] — дозволяємо всі (dev режим)
    $allowAll = empty(array_filter($allowed));

    if ($allowAll) {
        $corsOrigin = '*';
    } elseif (in_array($requestOrigin, $allowed, true)) {
        $corsOrigin = $requestOrigin;
    } else {
        // Origin не в whitelist — блокуємо, крім webhook
        $rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $isWebhook = str_contains($rawPath, 'webhook');
        if (!$isWebhook) {
            http_response_code(403);
            echo json_encode(['error' => 'Origin not allowed', 'origin' => $requestOrigin]);
            exit;
        }
    }
}

if ($corsOrigin) {
    header("Access-Control-Allow-Origin: {$corsOrigin}");
    if ($corsOrigin !== '*') {
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
    }
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Router ────────────────────────────────────────────────────────────────────
$method  = $_SERVER['REQUEST_METHOD'];
$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// Підтримуємо обидва варіанти:
//   /api/admin/clients  → /admin/clients   (фронт і бек на одному домені)
//   /admin/clients      → /admin/clients   (бек окремо)
//   /index.php/...      → /...             (деякі Apache конфіги)
$path = '/' . trim(
    preg_replace(['#^/+api#i', '#/index\.php#'], '', $rawPath),
    '/'
);
if ($path === '') $path = '/';

$body = json_decode(file_get_contents('php://input'), true) ?? [];

function respond(mixed $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
//  ROUTES
// ══════════════════════════════════════════════════════════════════════════════

// ── POST /auth/login ──────────────────────────────────────────────────────────
if ($method === 'POST' && $path === '/auth/login') {
    $email    = strtolower(trim($body['email']    ?? ''));
    $password = trim($body['password'] ?? '');

    if (!$email || !$password) respond(['error' => 'Email and password required'], 400);

    // Адмін через .env (не зберігається в БД)
    if ($email === strtolower($cfg['admin_email']) && $password === $cfg['admin_password']) {
        $token = JWT::encode([
            'sub'   => 0,
            'email' => $email,
            'role'  => 'admin',
            'exp'   => time() + $cfg['jwt_ttl'],
        ], $cfg['jwt_secret']);
        Logger::info('auth.login', 'Admin login', $email, ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
        respond(['token' => $token, 'role' => 'admin', 'email' => $email]);
    }

    $db   = Database::get();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        Logger::warning('auth.failed', 'Failed login attempt', $email, ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
        respond(['error' => 'Invalid credentials'], 401);
    }

    $token = JWT::encode([
        'sub'   => $user['id'],
        'email' => $user['email'],
        'role'  => $user['role'],
        'exp'   => time() + $cfg['jwt_ttl'],
    ], $cfg['jwt_secret']);

    Logger::info('auth.login', 'Client login', $email, ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
    respond(['token' => $token, 'role' => $user['role'], 'email' => $user['email']]);
}

// ── GET /auth/me ──────────────────────────────────────────────────────────────
if ($method === 'GET' && $path === '/auth/me') {
    $payload = AuthMiddleware::requireAuth();
    respond(['email' => $payload['email'], 'role' => $payload['role']]);
}

// ── PUT /auth/profile ── оновити ім'я / пароль клієнта ────────────────────────
if ($method === 'PUT' && $path === '/auth/profile') {
    $payload = AuthMiddleware::requireAuth();
    $email   = $payload['email'];

    $db      = Database::get();
    $stmt    = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user    = $stmt->fetch();

    if (!$user) {
        respond(['error' => 'User not found'], 404);
    }

    $userId  = $user['id'];
    $sets    = [];
    $vals    = [];

    if (!empty($body['name'])) {
        $sets[] = 'name = ?';
        $vals[] = $body['name'];
    }

    if (!empty($body['password'])) {
        if (strlen($body['password']) < 6) {
            respond(['error' => 'Password min 6 chars'], 422);
        }
        $sets[] = 'password = ?';
        $vals[] = password_hash($body['password'], PASSWORD_BCRYPT);
    }

    if (!$sets) {
        respond(['error' => 'Nothing to update'], 400);
    }

    $vals[] = $userId;
    $db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    respond(['success' => true]);
}

// ── GET /admin/paddle-debug ── діагностика підключення до Paddle ──────────────
if ($method === 'GET' && $path === '/admin/paddle-debug') {
    AuthMiddleware::requireAdmin();
    try {
        $paddle = new PaddleService();
        $debug  = $paddle->debugConnection();
        respond($debug);
    } catch (\Throwable $e) {
        respond(['error' => $e->getMessage()], 500);
    }
}

// ── GET /billing ── клієнт бачить свої підписки + WHM статус ─────────────────
if ($method === 'GET' && $path === '/billing') {
    $payload = AuthMiddleware::requireAuth();
    $email   = $payload['email'];

    $subs      = [];
    $paddleErr = null;
    try {
        $paddle = new PaddleService();
        $subs   = $paddle->getSubscriptionsByEmail($email);
    } catch (\Throwable $e) {
        $paddleErr = $e->getMessage();
    }

    $whmResult = null;
    $hasActive = !empty(array_filter($subs, fn($s) => in_array($s['status'], ['active', 'trialing'])));

    if ($hasActive && !empty($cfg['whm_token'])) {
        try {
            $whm       = new WhmService();
            $whmResult = $whm->ensureAccount($email, $cfg['whm_plan']);
        } catch (\Throwable $e) {
            $whmResult = ['error' => $e->getMessage()];
        }
    }

    respond([
        'email'         => $email,
        'subscriptions' => $subs,
        'whm'           => $whmResult,
        'paddle_error'  => $paddleErr,
    ]);
}

// ── GET /admin/billing?email=... ── адмін: підписки + WHM будь-якого email ────
if ($method === 'GET' && $path === '/admin/billing') {
    AuthMiddleware::requireAdmin();
    $email = strtolower(trim($_GET['email'] ?? ''));
    if (!$email) respond(['error' => 'email required'], 400);

    $subs      = [];
    $paddleErr = null;
    try {
        $paddle = new PaddleService();
        $subs   = $paddle->getSubscriptionsByEmail($email);
    } catch (\Throwable $e) {
        $paddleErr = $e->getMessage();
    }

    $whmAccount = null;
    if (!empty($cfg['whm_token'])) {
        try {
            $whm        = new WhmService();
            $whmAccount = $whm->getAccountByEmail($email);
        } catch (\Throwable $e) {
            $whmAccount = ['error' => $e->getMessage()];
        }
    }

    respond([
        'email'         => $email,
        'subscriptions' => $subs,
        'whm_account'   => $whmAccount,
        'paddle_error'  => $paddleErr,
    ]);
}

// ── POST /admin/billing/provision ── вручну провізіонувати WHM акаунт ─────────
if ($method === 'POST' && $path === '/admin/billing/provision') {
    AuthMiddleware::requireAdmin();
    $email = strtolower(trim($body['email'] ?? ''));
    $plan  = trim($body['plan'] ?? $cfg['whm_plan']);
    if (!$email) respond(['error' => 'email required'], 400);

    // Валідуємо план — тільки відомі пакети
    $allowedPlans = [
        'wedbkrdb_Starter',
        'wedbkrdb_Personal',
        'wedbkrdb_Business',
        'wedbkrdb_Business-Special',
        'wedbkrdb_Agency',
        'wedbkrdb_Agency Pro',
    ];
    if (!in_array($plan, $allowedPlans, true)) {
        respond(['error' => 'Invalid plan: ' . $plan], 422);
    }

    try {
        $whm    = new WhmService();
        $result = $whm->ensureAccount($email, $plan);
        respond($result);
    } catch (\Throwable $e) {
        respond(['error' => $e->getMessage()], 500);
    }
}

// ── GET /admin/clients ── список клієнтів ────────────────────────────────────
if ($method === 'GET' && $path === '/admin/clients') {
    AuthMiddleware::requireAdmin();
    $db      = Database::get();
    $clients = $db->query(
        'SELECT id, email, name, role, created_at FROM users ORDER BY created_at DESC'
    )->fetchAll();
    respond(['clients' => $clients]);
}

// ── POST /admin/clients ── створити клієнта ───────────────────────────────────
if ($method === 'POST' && $path === '/admin/clients') {
    AuthMiddleware::requireAdmin();

    $email    = strtolower(trim($body['email']    ?? ''));
    $password = trim($body['password'] ?? '');
    $name     = trim($body['name']     ?? '');
    $plan     = trim($body['plan']     ?? $cfg['whm_plan']);

    if (!$email || !$password) respond(['error' => 'Email and password required'], 400);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(['error' => 'Invalid email'], 422);
    if (strlen($password) < 6) respond(['error' => 'Password min 6 chars'], 422);

    $db   = Database::get();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) respond(['error' => 'Email already exists'], 409);

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare('INSERT INTO users (email, password, name, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([$email, $hash, $name, 'client']);
    $newId = $db->lastInsertId();

    // Перевіряємо Paddle → якщо є active підписка, провізіонуємо WHM з обраним планом
    $whmResult = null;
    if (!empty($cfg['whm_token'])) {
        try {
            $paddle    = new PaddleService();
            $subs      = $paddle->getSubscriptionsByEmail($email);
            $hasActive = !empty(array_filter($subs, fn($s) => in_array($s['status'], ['active', 'trialing'])));
            if ($hasActive) {
                $whm       = new WhmService();
                $whmResult = $whm->ensureAccount($email, $plan);
            }
        } catch (\Throwable $e) {
            $whmResult = ['error' => $e->getMessage()];
        }
    }

    respond([
        'success' => true,
        'id'      => $newId,
        'email'   => $email,
        'plan'    => $plan,
        'whm'     => $whmResult,
    ], 201);
}

// ── DELETE /admin/clients/{id} ────────────────────────────────────────────────
if ($method === 'DELETE' && preg_match('#^/admin/clients/(\d+)$#', $path, $m)) {
    AuthMiddleware::requireAdmin();
    $db = Database::get();
    $db->prepare('DELETE FROM users WHERE id = ?')->execute([$m[1]]);
    respond(['success' => true]);
}

// ── PUT /admin/clients/{id} ── оновити ім'я / пароль ─────────────────────────
if ($method === 'PUT' && preg_match('#^/admin/clients/(\d+)$#', $path, $m)) {
    AuthMiddleware::requireAdmin();
    $db   = Database::get();
    $sets = [];
    $vals = [];

    if (!empty($body['name']))     { $sets[] = 'name = ?';     $vals[] = $body['name']; }
    if (!empty($body['password'])) { $sets[] = 'password = ?'; $vals[] = password_hash($body['password'], PASSWORD_BCRYPT); }

    if (!$sets) respond(['error' => 'Nothing to update'], 400);

    $vals[] = $m[1];
    $db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    respond(['success' => true]);
}

// ── POST /webhook/paddle ── Paddle Webhook ────────────────────────────────────
if ($method === 'POST' && $path === '/webhook/paddle') {
    $rawBody   = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_PADDLE_SIGNATURE'] ?? '';

    $handler = new PaddleWebhook();
    $result  = $handler->handle($rawBody, $signature);

    // Paddle очікує 200 навіть при помилках обробки
    http_response_code(200);
    echo json_encode($result);
    exit;
}

// ── GET /admin/logs ── журнал подій ───────────────────────────────────────────
if ($method === 'GET' && $path === '/admin/logs') {
    AuthMiddleware::requireAdmin();
    $limit = min((int)($_GET['limit'] ?? 100), 500);
    $level = $_GET['level'] ?? null;
    $email = $_GET['email'] ?? null;

    $logs = Logger::getLogs($limit, $level ?: null, $email ?: null);
    respond(['logs' => $logs, 'count' => count($logs)]);
}

// ── GET /admin/webhook-logs ── журнал webhook ──────────────────────────────────
if ($method === 'GET' && $path === '/admin/webhook-logs') {
    AuthMiddleware::requireAdmin();
    $limit = min((int)($_GET['limit'] ?? 50), 200);
    $logs  = Logger::getWebhookLogs($limit);
    respond(['logs' => $logs, 'count' => count($logs)]);
}

// ── POST /admin/clients/{id}/send-credentials ── надіслати cPanel credentials ─
if ($method === 'POST' && preg_match('#^/admin/clients/(\d+)/send-credentials$#', $path, $m)) {
    AuthMiddleware::requireAdmin();

    $db   = Database::get();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$m[1]]);
    $user = $stmt->fetch();
    if (!$user) respond(['error' => 'User not found'], 404);

    $cpanelUser = trim($body['cpanel_user'] ?? '');
    $cpanelPass = trim($body['cpanel_pass'] ?? '');
    $domain     = trim($body['domain']      ?? '');
    $plan       = trim($body['plan']        ?? $cfg['whm_plan']);

    if (!$cpanelUser || !$cpanelPass || !$domain) {
        respond(['error' => 'cpanel_user, cpanel_pass, domain required'], 400);
    }

    $mailer = new Mailer();
    $sent   = $mailer->sendCpanelCredentials(
        $user['email'],
        $user['name'] ?? $user['email'],
        $cpanelUser,
        $cpanelPass,
        $domain,
        $plan
    );

    Logger::info('email.credentials', 'cPanel credentials sent manually', $user['email'], [
        'admin_action' => true,
        'domain'       => $domain,
    ]);

    respond(['success' => $sent]);
}

// ── GET /tickets ── клієнт бачить свої тікети ─────────────────────────────────
if ($method === 'GET' && $path === '/tickets') {
    $payload = AuthMiddleware::requireAuth();
    $clientId = $payload['sub'];
    if ($clientId === 0) respond(['error' => 'Clients only'], 403);

    $db      = Database::get();
    $stmt    = $db->prepare('SELECT * FROM tickets WHERE user_id = ? ORDER BY updated_at DESC');
    $stmt->execute([$clientId]);
    $tickets = $stmt->fetchAll();
    respond(['tickets' => $tickets]);
}

// ── POST /tickets ── клієнт створює новий тікет ───────────────────────────────
if ($method === 'POST' && $path === '/tickets') {
    $payload = AuthMiddleware::requireAuth();
    $clientId = $payload['sub'];
    if ($clientId === 0) respond(['error' => 'Clients only'], 403);

    $subject = trim($body['subject'] ?? '');
    $message = trim($body['message'] ?? '');

    if (!$subject || !$message) respond(['error' => 'Subject and message are required'], 400);

    $db = Database::get();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO tickets (user_id, subject, status) VALUES (?, ?, ?)');
        $stmt->execute([$clientId, $subject, 'open']);
        $ticketId = $db->lastInsertId();

        $stmt = $db->prepare('INSERT INTO ticket_messages (ticket_id, sender_id, sender_role, message) VALUES (?, ?, ?, ?)');
        $stmt->execute([$ticketId, $clientId, 'client', $message]);

        $db->commit();
        Logger::info('ticket.create', 'New ticket created', $payload['email'], ['ticket_id' => $ticketId, 'subject' => $subject]);
        respond(['success' => true, 'id' => $ticketId], 201);
    } catch (\Throwable $e) {
        $db->rollBack();
        respond(['error' => $e->getMessage()], 500);
    }
}

// ── GET /tickets/{id} ── деталі тікета + повідомлення ──────────────────────────
if ($method === 'GET' && preg_match('#^/tickets/(\d+)$#', $path, $m)) {
    $payload = AuthMiddleware::requireAuth();
    $clientId = $payload['sub'];
    if ($clientId === 0) respond(['error' => 'Clients only'], 403);

    $ticketId = (int)$m[1];
    $db = Database::get();

    $stmt = $db->prepare('SELECT * FROM tickets WHERE id = ? AND user_id = ?');
    $stmt->execute([$ticketId, $clientId]);
    $ticket = $stmt->fetch();

    if (!$ticket) respond(['error' => 'Ticket not found'], 404);

    $stmt = $db->prepare('SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC');
    $stmt->execute([$ticketId]);
    $messages = $stmt->fetchAll();

    respond([
        'ticket'   => $ticket,
        'messages' => $messages
    ]);
}

// ── POST /tickets/{id}/messages ── клієнт додає відповідь у тікет ──────────────
if ($method === 'POST' && preg_match('#^/tickets/(\d+)/messages$#', $path, $m)) {
    $payload = AuthMiddleware::requireAuth();
    $clientId = $payload['sub'];
    if ($clientId === 0) respond(['error' => 'Clients only'], 403);

    $ticketId = (int)$m[1];
    $message = trim($body['message'] ?? '');

    if (!$message) respond(['error' => 'Message is required'], 400);

    $db = Database::get();
    $stmt = $db->prepare('SELECT status FROM tickets WHERE id = ? AND user_id = ?');
    $stmt->execute([$ticketId, $clientId]);
    $ticket = $stmt->fetch();

    if (!$ticket) respond(['error' => 'Ticket not found'], 404);

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO ticket_messages (ticket_id, sender_id, sender_role, message) VALUES (?, ?, ?, ?)');
        $stmt->execute([$ticketId, $clientId, 'client', $message]);

        $currentTime = date('Y-m-d H:i:s');
        $stmt = $db->prepare('UPDATE tickets SET status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute(['pending', $currentTime, $ticketId]);

        $db->commit();
        respond(['success' => true]);
    } catch (\Throwable $e) {
        $db->rollBack();
        respond(['error' => $e->getMessage()], 500);
    }
}

// ── POST /tickets/{id}/close ── клієнт закриває тікет ─────────────────────────
if ($method === 'POST' && preg_match('#^/tickets/(\d+)/close$#', $path, $m)) {
    $payload = AuthMiddleware::requireAuth();
    $clientId = $payload['sub'];
    if ($clientId === 0) respond(['error' => 'Clients only'], 403);

    $ticketId = (int)$m[1];
    $db = Database::get();

    $stmt = $db->prepare('SELECT id FROM tickets WHERE id = ? AND user_id = ?');
    $stmt->execute([$ticketId, $clientId]);
    if (!$stmt->fetch()) respond(['error' => 'Ticket not found'], 404);

    $currentTime = date('Y-m-d H:i:s');
    $stmt = $db->prepare('UPDATE tickets SET status = ?, updated_at = ? WHERE id = ?');
    $stmt->execute(['closed', $currentTime, $ticketId]);

    Logger::info('ticket.close', 'Ticket closed by client', $payload['email'], ['ticket_id' => $ticketId]);
    respond(['success' => true]);
}

// ── GET /admin/tickets ── адмін бачить всі тікети ─────────────────────────────
if ($method === 'GET' && $path === '/admin/tickets') {
    AuthMiddleware::requireAdmin();
    $db = Database::get();

    $tickets = $db->query('
        SELECT t.*, u.email as client_email, u.name as client_name
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        ORDER BY t.updated_at DESC
    ')->fetchAll();

    respond(['tickets' => $tickets]);
}

// ── GET /admin/tickets/{id} ── адмін бачить деталі будь-якого тікета ───────────
if ($method === 'GET' && preg_match('#^/admin/tickets/(\d+)$#', $path, $m)) {
    AuthMiddleware::requireAdmin();
    $ticketId = (int)$m[1];
    $db = Database::get();

    $stmt = $db->prepare('
        SELECT t.*, u.email as client_email, u.name as client_name
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ?
    ');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();

    if (!$ticket) respond(['error' => 'Ticket not found'], 404);

    $stmt = $db->prepare('SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC');
    $stmt->execute([$ticketId]);
    $messages = $stmt->fetchAll();

    respond([
        'ticket'   => $ticket,
        'messages' => $messages
    ]);
}

// ── POST /admin/tickets/{id}/messages ── адмін додає відповідь у тікет ─────────
if ($method === 'POST' && preg_match('#^/admin/tickets/(\d+)/messages$#', $path, $m)) {
    $payload = AuthMiddleware::requireAdmin();
    $ticketId = (int)$m[1];
    $message = trim($body['message'] ?? '');

    if (!$message) respond(['error' => 'Message is required'], 400);

    $db = Database::get();
    $stmt = $db->prepare('SELECT id, user_id FROM tickets WHERE id = ?');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();

    if (!$ticket) respond(['error' => 'Ticket not found'], 404);

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO ticket_messages (ticket_id, sender_id, sender_role, message) VALUES (?, 0, \'admin\', ?)');
        $stmt->execute([$ticketId, $message]);

        $currentTime = date('Y-m-d H:i:s');
        $stmt = $db->prepare('UPDATE tickets SET status = ?, updated_at = ? WHERE id = ?');
        $stmt->execute(['replied', $currentTime, $ticketId]);

        $db->commit();

        $stmt = $db->prepare('SELECT email FROM users WHERE id = ?');
        $stmt->execute([$ticket['user_id']]);
        $clientUser = $stmt->fetch();
        $clientEmail = $clientUser ? $clientUser['email'] : null;

        Logger::info('ticket.reply', 'Ticket replied by admin', $clientEmail, ['ticket_id' => $ticketId]);

        respond(['success' => true]);
    } catch (\Throwable $e) {
        $db->rollBack();
        respond(['error' => $e->getMessage()], 500);
    }
}

// ── POST /admin/tickets/{id}/status ── адмін змінює статус тікета ──────────────
if ($method === 'POST' && preg_match('#^/admin/tickets/(\d+)/status$#', $path, $m)) {
    AuthMiddleware::requireAdmin();
    $ticketId = (int)$m[1];
    $status = trim($body['status'] ?? '');

    $allowed = ['open', 'pending', 'replied', 'closed'];
    if (!in_array($status, $allowed, true)) {
        respond(['error' => 'Invalid status: ' . $status], 422);
    }

    $db = Database::get();
    $stmt = $db->prepare('SELECT id, user_id FROM tickets WHERE id = ?');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();

    if (!$ticket) respond(['error' => 'Ticket not found'], 404);

    $currentTime = date('Y-m-d H:i:s');
    $stmt = $db->prepare('UPDATE tickets SET status = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([$status, $currentTime, $ticketId]);

    $stmt = $db->prepare('SELECT email FROM users WHERE id = ?');
    $stmt->execute([$ticket['user_id']]);
    $clientUser = $stmt->fetch();
    $clientEmail = $clientUser ? $clientUser['email'] : null;

    Logger::info('ticket.status', 'Ticket status updated by admin', $clientEmail, ['ticket_id' => $ticketId, 'status' => $status]);

    respond(['success' => true]);
}

// ── POST /admin/tickets/{id}/suggest-reply ── ШІ авто-відповідь ───────────────
if ($method === 'POST' && preg_match('#^/admin/tickets/(\d+)/suggest-reply$#', $path, $m)) {
    AuthMiddleware::requireAdmin();
    $ticketId = (int)$m[1];

    $apiKey = trim($cfg['openrouter_api_key'] ?? '');
    if (!$apiKey) {
        respond(['error' => 'OpenRouter API key is not configured in .env'], 400);
    }

    $db = Database::get();
    $stmt = $db->prepare('SELECT * FROM tickets WHERE id = ?');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket) respond(['error' => 'Ticket not found'], 404);

    $stmt = $db->prepare('SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC');
    $stmt->execute([$ticketId]);
    $messages = $stmt->fetchAll();

    $systemPrompt = "You are the support administrator of a web hosting billing portal (Programist Hosting). "
                  . "Generate a helpful, polite, and technical support response to the client's latest message based on the ticket history. "
                  . "IMPORTANT: You must search for and use information exclusively from the website 'hostpro.apartner.pro' when providing technical details, links, or instructions. "
                  . "Your response MUST be written in Ukrainian. Use formatting (like lists or paragraphs) if appropriate. "
                  . "Be direct, professional, and address the specific questions. Avoid generic placeholders.";

    $aiMessages = [
        ['role' => 'system', 'content' => $systemPrompt]
    ];

    foreach ($messages as $msg) {
        $role = ($msg['sender_role'] === 'admin') ? 'assistant' : 'user';
        $aiMessages[] = [
            'role' => $role,
            'content' => $msg['message']
        ];
    }

    $lastError = '';
    $callApi = function(string $model, int $maxTokens = 1024) use ($apiKey, $aiMessages, &$lastError) {
        $url = 'https://openrouter.ai/api/v1/chat/completions';
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: https://programist.com.ua',
            'X-Title: Billing Portal'
        ];
        $body = json_encode([
            'model'      => $model,
            'messages'   => $aiMessages,
            'max_tokens' => $maxTokens,
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $lastError = "Curl error: " . curl_error($ch) . " (code: " . curl_errno($ch) . ")";
            curl_close($ch);
            return null;
        }
        curl_close($ch);

        if ($httpCode !== 200) {
            $lastError = "HTTP Code $httpCode. Response: " . $response;
            return null;
        }

        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? null;
        if ($content === null) {
            $lastError = "Invalid JSON structure. Response: " . $response;
        }
        return $content;
    };

    // Спочатку намагаємось з openrouter/auto (обмежуємо токени щоб не витрачати зайвих кредитів)
    $reply = $callApi('openrouter/auto', 1024);
    $usedModel = 'openrouter/auto';
    $primaryError = $lastError;

    // Якщо не спрацювало — пробуємо через openrouter/free (авто-вибір серед безкоштовних)
    // а потім конкретні відомі безкоштовні моделі
    if ($reply === null) {
        $freeModels = [
            'openrouter/free',           // Офіційний авто-роутер для free моделей
            'google/gemma-4-31b-it:free',
            'qwen/qwen3-coder:free',
            'openai/gpt-oss-120b:free',
            'nvidia/nemotron-3-super-120b-a12b:free',
            'deepseek/deepseek-chat-v3-0324:free',
            'deepseek/deepseek-r1-0528:free',
        ];
        $fallbackErrors = [];
        foreach ($freeModels as $freeModel) {
            $reply = $callApi($freeModel, 1024);
            if ($reply !== null) {
                $usedModel = $freeModel;
                break;
            }
            $fallbackErrors[$freeModel] = $lastError;
        }
    }

    if ($reply === null) {
        $errMessage = "Failed to generate response from OpenRouter API. "
                    . "Primary model (openrouter/auto) error: [$primaryError]. "
                    . "Fallback models also failed: " . json_encode($fallbackErrors ?? []);
        Logger::error('ticket.ai_reply_failed', $errMessage, null, ['ticket_id' => $ticketId]);
        respond(['error' => $errMessage], 502);
    }

    respond([
        'reply' => trim($reply),
        'model' => $usedModel
    ]);
}

// ── INVOICES (Клієнт) ─────────────────────────────────────────────────────────
if ($method === 'GET' && preg_match('#^/client/invoices$#', $path)) {
    $user = AuthMiddleware::requireAuth();
    $db = Database::get();

    $stmt = $db->prepare('SELECT * FROM invoices WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$user['sub']]);
    $invoices = $stmt->fetchAll();

    try {
        // Ми перенесли отримання транзакцій в окремий ендпоінт /client/transactions
    } catch (\Throwable $e) {
    }

    $stats = [
        'unpaid' => 0,
        'paid' => 0,
        'cancelled' => 0,
        'refunded' => 0,
        'total' => count($invoices)
    ];

    foreach ($invoices as $inv) {
        $status = $inv['status'];
        if (isset($stats[$status])) {
            $stats[$status]++;
        }
    }

    respond(['invoices' => $invoices, 'stats' => $stats]);
}

// ── TRANSACTIONS (Клієнт - Paddle) ────────────────────────────────────────────
if ($method === 'GET' && preg_match('#^/client/transactions$#', $path)) {
    $user = AuthMiddleware::requireAuth();
    
    try {
        $paddle = new PaddleService();
        $transactions = $paddle->getTransactionsByEmail($user['email']);
        
        usort($transactions, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        respond(['transactions' => $transactions]);
    } catch (\Throwable $e) {
        respond(['error' => $e->getMessage()], 500);
    }
}

// ── USERS (Адмін - для селекта клієнтів) ──────────────────────────────────────
if ($method === 'GET' && preg_match('#^/admin/users$#', $path)) {
    AuthMiddleware::requireAdmin();
    $db = Database::get();
    
    // Повертаємо тільки клієнтів
    $stmt = $db->query("SELECT id, email, name FROM users WHERE role = 'client' ORDER BY id DESC");
    $users = $stmt->fetchAll();

    respond(['users' => $users]);
}

// ── INVOICES (Адмін) ──────────────────────────────────────────────────────────
if ($method === 'GET' && preg_match('#^/admin/invoices$#', $path)) {
    AuthMiddleware::requireAdmin();
    $db = Database::get();

    $stmt = $db->query('
        SELECT i.*, u.email as client_email 
        FROM invoices i 
        JOIN users u ON i.user_id = u.id 
        ORDER BY i.created_at DESC
    ');
    $invoices = $stmt->fetchAll();

    respond(['invoices' => $invoices]);
}

if ($method === 'POST' && preg_match('#^/admin/invoices$#', $path)) {
    AuthMiddleware::requireAdmin();
    $db = Database::get();

    $userId = (int)($body['user_id'] ?? 0);
    $amount = (float)($body['amount'] ?? 0);
    $currency = trim($body['currency'] ?? 'EUR');
    $dueDate = trim($body['due_date'] ?? '');

    if (!$userId || $amount <= 0 || !$currency || !$dueDate) {
        respond(['error' => 'Missing or invalid fields (user_id, amount, currency, due_date)'], 422);
    }

    // Перевірка що користувач існує
    $stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) {
        respond(['error' => 'User not found'], 404);
    }

    $stmt = $db->prepare('
        INSERT INTO invoices (user_id, amount, currency, status, due_date)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([$userId, $amount, $currency, 'unpaid', $dueDate]);
    $invoiceId = $db->lastInsertId();

    Logger::info('admin.invoice_created', "Created invoice #$invoiceId for user $userId", null, [
        'invoice_id' => $invoiceId,
        'amount' => $amount,
        'currency' => $currency
    ]);

    respond(['success' => true, 'invoice_id' => $invoiceId]);
}

// ── GET /admin/settings ───────────────────────────────────────────────────────
if ($method === 'GET' && $path === '/admin/settings') {
    AuthMiddleware::requireAdmin();
    $db = Database::get();
    $stmt = $db->prepare('SELECT `value` FROM settings WHERE `key` = ?');
    $stmt->execute(['payment_methods']);
    $row = $stmt->fetch();
    $methods = $row ? json_decode($row['value'], true) : ['paddle' => true, 'monobank' => false, 'wayforpay' => false];
    respond(['payment_methods' => $methods]);
}

// ── PUT /admin/settings ───────────────────────────────────────────────────────
if ($method === 'PUT' && $path === '/admin/settings') {
    AuthMiddleware::requireAdmin();
    $db = Database::get();
    $val = json_encode($body['payment_methods'] ?? []);
    
    // Просте видалення та вставка для сумісності SQLite/MariaDB
    $db->prepare('DELETE FROM settings WHERE `key` = ?')->execute(['payment_methods']);
    $db->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?)')->execute(['payment_methods', $val]);
    
    respond(['success' => true]);
}

// ── GET /client/settings ──────────────────────────────────────────────────────
if ($method === 'GET' && $path === '/client/settings') {
    $db = Database::get();
    $stmt = $db->prepare('SELECT `value` FROM settings WHERE `key` = ?');
    $stmt->execute(['payment_methods']);
    $row = $stmt->fetch();
    $methods = $row ? json_decode($row['value'], true) : ['paddle' => true, 'monobank' => false, 'wayforpay' => false];
    
    respond([
        'payment_methods' => $methods,
        'paddle_env' => $cfg['paddle_env'] ?? 'sandbox',
        'paddle_client_token' => $cfg['paddle_client_token'] ?? ''
    ]);
}

// ── POST /client/invoices/{id}/pay-mock ──────────────────────────────────────────
if ($method === 'POST' && preg_match('#^/client/invoices/(\d+)/pay-mock$#', $path, $m)) {
    $user = AuthMiddleware::requireAuth();
    $invoiceId = (int)$m[1];
    $payMethod = trim($body['method'] ?? 'monobank');
    $db = Database::get();
    
    $stmt = $db->prepare('SELECT * FROM invoices WHERE id = ?');
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();
    if (!$invoice) respond(['error' => 'Invoice not found'], 404);
    
    if ($user['role'] !== 'admin' && (int)$invoice['user_id'] !== (int)$user['sub']) {
        respond(['error' => 'Access denied'], 403);
    }
    
    $currentTime = date('Y-m-d H:i:s');
    $stmt = $db->prepare("UPDATE invoices SET status = 'paid', updated_at = ? WHERE id = ?");
    $stmt->execute([$currentTime, $invoiceId]);
    
    Logger::info('invoice.paid_mock', "Invoice #$invoiceId marked as PAID via mock $payMethod", $user['email'], [
        'invoice_id' => $invoiceId,
        'method' => $payMethod
    ]);
    
    respond(['success' => true]);
}

// ── POST /client/invoices/{id}/paddle-checkout ────────────────────────────────
if ($method === 'POST' && preg_match('#^/client/invoices/(\d+)/paddle-checkout$#', $path, $m)) {
    $user      = AuthMiddleware::requireAuth();
    $invoiceId = (int)$m[1];
    $db        = Database::get();

    // Перевірити що рахунок існує і належить клієнту
    $stmt = $db->prepare('SELECT * FROM invoices WHERE id = ?');
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();

    if (!$invoice) respond(['error' => 'Invoice not found'], 404);

    if ($user['role'] !== 'admin' && (int)$invoice['user_id'] !== (int)$user['sub']) {
        respond(['error' => 'Access denied'], 403);
    }

    if ($invoice['status'] !== 'unpaid') {
        respond(['error' => 'Invoice is not unpaid', 'status' => $invoice['status']], 400);
    }

    // Отримати email клієнта
    $userRow = $db->prepare('SELECT email FROM users WHERE id = ?');
    $userRow->execute([$user['sub']]);
    $userRecord = $userRow->fetch();
    $email = $userRecord['email'] ?? $user['email'] ?? '';

    try {
        $paddle = new PaddleService();
        $result = $paddle->createTransaction(
            $invoiceId,
            (float)$invoice['amount'],
            $invoice['currency'],
            $email
        );

        Logger::info('paddle.checkout_created', "Paddle transaction created for invoice #{$invoiceId}", $email, [
            'invoice_id'     => $invoiceId,
            'transaction_id' => $result['transaction_id'],
        ]);

        respond(['transaction_id' => $result['transaction_id']]);
    } catch (\RuntimeException $e) {
        Logger::error('paddle.checkout_error', $e->getMessage(), $email, ['invoice_id' => $invoiceId]);
        respond(['error' => $e->getMessage()], 502);
    }
}

// ── 404 ───────────────────────────────────────────────────────────────────────
respond(['error' => 'Not found', 'path' => $path], 404);
