<?php

class WhmService
{
    private string $host;
    private string $token;
    private string $user;  // WHM reseller username

    public function __construct()
    {
        $cfg        = require __DIR__ . '/../config/config.php';
        $this->host = $cfg['whm_host'];
        $this->token= $cfg['whm_token'];
        $this->user = $cfg['whm_user'];
    }

    // ── Перевірити чи існує cPanel акаунт за email ────────────────────────────
    public function accountExistsByEmail(string $email): bool
    {
        $accounts = $this->listAccounts();
        foreach ($accounts as $acc) {
            if (strtolower($acc['email'] ?? '') === strtolower($email)) return true;
        }

        // var_dump($accounts); // debug
        return false;
    }

    // ── Отримати акаунт за email ──────────────────────────────────────────────
    public function getAccountByEmail(string $email): ?array
    {
        $accounts = $this->listAccounts();
        foreach ($accounts as $acc) {
            if (strtolower($acc['email'] ?? '') === strtolower($email)) return $acc;
        }
        return null;
    }

    // ── Отримати акаунт за cPanel username ─────────────────────────────────
    public function getAccountByUsername(string $username): ?array
    {
        $accounts = $this->listAccounts();
        foreach ($accounts as $acc) {
            if (strtolower($acc['user'] ?? '') === strtolower($username)) return $acc;
        }
        return null;
    }

    // ── Отримати акаунт за доменом ────────────────────────────────────────
    public function getAccountByDomain(string $domain): ?array
    {
        $accounts = $this->listAccounts();
        foreach ($accounts as $acc) {
            if (strtolower($acc['domain'] ?? '') === strtolower($domain)) return $acc;
        }
        return null;
    }

    // ── Отримати акаунт через accountsummary (працює для будь-якого акаунту, ────
    // незалежно від того чиї він реселер) ──────────────────────────────
    public function getAccountSummary(string $username): ?array
    {
        $res  = $this->request('accountsummary', ['user' => $username]);
        $acct = $res['acct'][0] ?? null;
        return $acct ?: null;
    }

    // ── Список усіх акаунтів (без фільтрів — отримуємо всі) ────────────────
    public function listAccounts(): array
    {
        $res = $this->request('listaccts', []);  // без searchtype — повертає всі акаунти
        return $res['acct'] ?? [];
    }

    // ── Створити cPanel акаунт ────────────────────────────────────────────────
    public function createAccount(
        string $username,
        string $domain,
        string $password,
        string $email,
        string $plan
    ): array {
        // username: макс 8 символів, тільки [a-z0-9_]
        $username = $this->sanitizeUsername($username);

        // Спеціальний випадок: viknaeur → bundesmebli / bundes-mebli.com.ua
        if ($username === 'viknaeur' || $domain === 'bundesmebli.uaprogramist.com.ua') {
            $username = 'bundesmebli';
            $domain   = 'bundes-mebli.com.ua';
        }

        $res = $this->request('createacct', [
            'username'     => $username,
            'domain'       => $domain,
            'password'     => $password,
            'contactemail' => $email,
            'plan'         => $plan,
            'pkgname'      => $plan,
        ]);

        $result = $res['result'][0] ?? $res['result'] ?? [];

        return [
            'success'  => !empty($result['status']) && (int)$result['status'] === 1,
            'username' => $username,
            'domain'   => $domain,
            'message'  => $result['statusmsg'] ?? ($res['metadata']['reason'] ?? 'Unknown'),
            'raw'      => $result,
        ];
    }

    // ── Авто-створення: якщо акаунту з таким email ще немає → створити ───────
    // Повертає ['created'=>bool, 'existed'=>bool, 'account'=>array|null, 'error'=>string|null]
    public function ensureAccount(string $email, string $plan): array
    {
        $cfg = require __DIR__ . '/../config/config.php';

        // 1️⃣ Перевіряємо за email
        $existing = $this->getAccountByEmail($email);
        if ($existing) {
            return ['created' => false, 'existed' => true, 'account' => $existing, 'error' => null];
        }

        // Генеруємо username і домен з email
        $username = $this->usernameFromEmail($email);
        $domain   = $cfg['whm_default_domain_prefix']
                        ? $username . '.' . $cfg['whm_default_domain_prefix']
                        : $username . '.clients.example.com';

        // Спеціальний випадок: viknaeur → завжди bundes-mebli.com.ua
        if ($username === 'viknaeur') {
            $username = 'bundesmebli';
            $domain   = 'bundes-mebli.com.ua';
        }

        // 2️⃣ Перевіряємо за username (акаунт може бути зареєстрований з іншим email)
        $existingByUser = $this->getAccountByUsername($username);
        if ($existingByUser) {
            return ['created' => false, 'existed' => true, 'account' => $existingByUser, 'error' => null];
        }

        // 3️⃣ Перевіряємо за доменом (на випадок фіксованих доменів)
        $existingByDomain = $this->getAccountByDomain($domain);
        if ($existingByDomain) {
            return ['created' => false, 'existed' => true, 'account' => $existingByDomain, 'error' => null];
        }

        // Нічого не знайшли — створюємо новий
        $password = $this->generatePassword();
        $result   = $this->createAccount($username, $domain, $password, $email, $plan);

        if (!$result['success']) {
            // Якщо WHM каже "вже існує" — акаунт є, просто не наш реселер.
            // Не намагаємось шукати — повертаємо existed=true з відомими даними.
            $msg = strtolower($result['message']);
            if (
                str_contains($msg, 'already exists') ||
                str_contains($msg, 'вже існує') ||
                str_contains($msg, 'exists in')
            ) {
                // Пробуємо отримати деталі через accountsummary (може не спрацювати)
                $account = $this->getAccountSummary($username) ?? [
                    'user'   => $username,
                    'domain' => $domain,
                ];
                return ['created' => false, 'existed' => true, 'account' => $account, 'error' => null];
            }
            return ['created' => false, 'existed' => false, 'account' => null, 'error' => $result['message']];
        }


        return [
            'created'  => true,
            'existed'  => false,
            'username' => $username,
            'domain'   => $domain,
            'password' => $password,
            'error'    => null,
        ];
    }

    // ── SSO: створити одноразову сесію для входу в cPanel без пароля ──────────
    // Повертає URL для редиректу або кидає RuntimeException
    public function createUserSession(string $cpanelUsername): string
    {
        $res = $this->request('create_user_session', [
            'user'    => $cpanelUsername,
            'service' => 'cpaneld',
        ]);

        $url = $res['data']['url'] ?? null;
        if (!$url) {
            $reason = $res['metadata']['reason'] ?? json_encode($res);
            throw new \RuntimeException('WHM SSO failed: ' . $reason);
        }

        return $url;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private function sanitizeUsername(string $raw): string
    {
        $clean = preg_replace('/[^a-z0-9_]/', '', strtolower($raw));
        return substr($clean, 0, 8) ?: 'user' . rand(1000, 9999);
    }

    private function usernameFromEmail(string $email): string
    {
        $local = explode('@', $email)[0];
        return $this->sanitizeUsername($local);
    }

    private function generatePassword(int $length = 16): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
        $pass  = '';
        for ($i = 0; $i < $length; $i++) {
            $pass .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $pass;
    }

    // ── HTTP до WHM JSON API ───────────────────────────────────────────────────
    private function request(string $function, array $params = []): array
    {
        $url = sprintf('https://%s:2087/json-api/%s?api.version=1&%s',
            $this->host,
            $function,
            http_build_query($params)
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: whm ' . $this->user . ':' . $this->token,
            ],
        ]);

        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) throw new \RuntimeException('WHM curl error: ' . $err);

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}