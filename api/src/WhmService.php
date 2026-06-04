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

    // ── Список усіх акаунтів ──────────────────────────────────────────────────
    public function listAccounts(): array
    {
        $res = $this->request('listaccts', ['searchtype' => 'email', 'search' => '']);
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

        // Перевіряємо чи вже є
        $existing = $this->getAccountByEmail($email);
        if ($existing) {
            return [
                'created'  => false,
                'existed'  => true,
                'account'  => $existing,
                'error'    => null,
            ];
        }

        // Генеруємо username з email
        $username = $this->usernameFromEmail($email);
        $domain   = $cfg['whm_default_domain_prefix']
                        ? $username . '.' . $cfg['whm_default_domain_prefix']
                        : $username . '.clients.example.com';
        $password = $this->generatePassword();

        $result = $this->createAccount($username, $domain, $password, $email, $plan);

        if (!$result['success']) {
            return [
                'created' => false,
                'existed' => false,
                'account' => null,
                'error'   => $result['message'],
            ];
        }

        return [
            'created'  => true,
            'existed'  => false,
            'username' => $username,
            'domain'   => $domain,
            'password' => $password, // повертаємо тільки при створенні
            'error'    => null,
        ];
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
