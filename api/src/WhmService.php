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
        // Не використовуйте searchtype=email з пустим search — це повертає 0 акаунтів!
        // $res = $this->request('listaccts', ['searchtype' => 'email', 'search' => '']);
        
        $res = $this->request('listaccts', []);  
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

        if ($username === 'aerostar' || $domain === 'aerostar.uaprogramist.com.ua') {
            $username = 'aerostar';
            $domain   = 'aerostar.uz';
        }

        $res = $this->request('createacct', [
            'username'     => $username,
            'domain'       => $domain,
            'password'     => $password,
            'contactemail' => $email,
            'plan'         => $plan,
            'pkgname'      => $plan,
        ]);

        $result  = $res['result'][0] ?? $res['result'] ?? [];
        $msg     = strtolower($result['statusmsg'] ?? ($res['metadata']['reason'] ?? ''));

        // WHM може повертати status як різні типи, тому також перевіряємо вміст повідомлення
        $isSuccess = (!empty($result['status']) && (int)$result['status'] === 1)
                  || ((int)($res['metadata']['result'] ?? 0) === 1)
                  || str_contains($msg, 'account creation ok');

        return [
            'success'  => $isSuccess,
            'username' => $username,
            'domain'   => $domain,
            'message'  => $result['statusmsg'] ?? ($res['metadata']['reason'] ?? 'Unknown'),
            'raw'      => $result,
        ];
    }

    // ── Автоматизований пошук акаунту за email (із врахуванням всіх можливих варіантів) ──
    public function findAccountForEmail(string $email): ?array
    {
        $cfg = require __DIR__ . '/../config/config.php';

        // 1️⃣ Перевіряємо за email
        $existing = $this->getAccountByEmail($email);
        if ($existing) return $existing;

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

        if ($username === 'aerostar') {
            $username = 'aerostar';
            $domain   = 'aerostar.uz';
        }

        // 2️⃣ Перевіряємо за username (може не бути видно в listaccts, якщо інший реселер)
        $existingByUser = $this->getAccountByUsername($username);
        if ($existingByUser) return $existingByUser;

        // 3️⃣ Перевіряємо через accountsummary (може не працювати для реселерів без root)
        $existingSummary = $this->getAccountSummary($username);
        if ($existingSummary) return $existingSummary;

        // 4️⃣ Перевіряємо за доменом (listaccts)
        $existingByDomain = $this->getAccountByDomain($domain);
        if ($existingByDomain) return $existingByDomain;

        error_log("whm.find_account_failed: Could not find account for email: $email, username: $username, domain: $domain");
        return null;
    }

    // ── Авто-створення: якщо акаунту з таким email ще немає → створити ───────

    // Повертає ['created'=>bool, 'existed'=>bool, 'account'=>array|null, 'error'=>string|null]
    public function ensureAccount(string $email, string $plan, ?string $domain = null, ?string $password = null, ?string $customUsername = null, ?string $docroot = null): array
    {
        $cfg = require __DIR__ . '/../config/config.php';

        $existing = $this->findAccountForEmail($email);
        if ($existing) {
            $docrootResult = null;
            if ($docroot) {
                $existingUser   = $existing['user']   ?? ($customUsername ?: $this->usernameFromEmail($email));
                $existingDomain = $existing['domain'] ?? $domain ?? '';
                $docrootResult  = $this->setDocumentRoot($existingUser, $existingDomain, $docroot);
            }
            return ['created' => false, 'existed' => true, 'account' => $existing, 'error' => null, 'docroot' => $docrootResult];
        }

        // Використовуємо вказане ім'я, або генеруємо з email
        $username = $customUsername ?: $this->usernameFromEmail($email);
        
        // Визначаємо домен: або переданий, або генеруємо
        if ($domain === null || trim($domain) === '') {
            $domain = $cfg['whm_default_domain_prefix']
                            ? $username . '.' . $cfg['whm_default_domain_prefix']
                            : $username . '.clients.example.com';
        } else {
            $domain = trim($domain);
        }

        if ($username === 'viknaeur') {
            $username = 'bundesmebli';
            $domain   = 'bundes-mebli.com.ua';
        }

        if ($username === 'aerostar') {
            $username = 'aerostar';
            $domain   = 'aerostar.uz';
        }

        // Нічого не знайшли — створюємо новий
        $password = $password ?? $this->generatePassword();
        $result   = $this->createAccount($username, $domain, $password, $email, $plan);

        if (!$result['success']) {
            $msg = strtolower($result['message']);
            if (
                str_contains($msg, 'already exists') ||
                str_contains($msg, 'вже існує') ||
                str_contains($msg, 'уже існує') ||
                str_contains($msg, 'exists in')
            ) {
                $account = $this->getAccountSummary($username) ?? [
                    'user'   => $username,
                    'domain' => $domain,
                ];
                $docrootResult = null;
                if ($docroot) {
                    $docrootResult = $this->setDocumentRoot($username, $domain, $docroot);
                }
                return ['created' => false, 'existed' => true, 'account' => $account, 'error' => null, 'docroot' => $docrootResult];
            }
            return ['created' => false, 'existed' => false, 'account' => null, 'error' => $result['message'], 'docroot' => null];
        }

        $docrootResult = null;
        if ($docroot) {
            $docrootResult = $this->setDocumentRoot($username, $domain, $docroot);
        }

        return [
            'created'  => true,
            'existed'  => false,
            'username' => $username,
            'domain'   => $domain,
            'password' => $password,
            'error'    => null,
            'docroot'  => $docrootResult,
        ];
    }

    // ── Зміна document root основного домену cPanel акаунту ──────────────────────
    // Створює підпапку і записує .htaccess redirect через cPanel API2 Fileman
    public function setDocumentRoot(string $username, string $domain, string $docroot): array
    {
        // Витягуємо відносний шлях: 'public' або 'public_html/public' → 'public'
        $subdirRelative = preg_replace('#.*/public_html/?#', '', trim($docroot, '/'));
        if ($subdirRelative === '' || $subdirRelative === 'public_html') {
            return ['success' => false, 'message' => 'Invalid docroot specified'];
        }

        // 1. Створюємо папку public_html/{subdir} через cPanel API2 Fileman::mkdir
        $mkdirResult = $this->requestCpanelUapi($username, 'Fileman', 'mkdir', [
            'path'        => 'public_html',
            'name'        => $subdirRelative,
            'permissions' => '0755',
        ]);

        // 2. Записуємо .htaccess через cPanel API2 Fileman::savefile
        $htaccessContent = "Options -Indexes\n" .
            "RewriteEngine On\n" .
            "RewriteBase /\n" .
            "RewriteCond %{REQUEST_URI} !^/{$subdirRelative}/\n" .
            "RewriteCond %{REQUEST_FILENAME} !-f\n" .
            "RewriteRule ^(.*)$ /{$subdirRelative}/\$1 [L,QSA]\n";

        $uploadResult = $this->requestCpanelUapi($username, 'Fileman', 'savefile', [
            'dir'      => '/public_html',
            'filename' => '.htaccess',
            'content'  => $htaccessContent,
        ]);

        $hasError = !empty($uploadResult['cpanelresult']['error'])
                 || empty($uploadResult['cpanelresult']['data']);

        return [
            'success'   => !$hasError,
            'subdir'    => $subdirRelative,
            'message'   => !$hasError
                ? "Folder '{$subdirRelative}' created, .htaccess redirect set in public_html"
                : ('Failed: ' . ($uploadResult['cpanelresult']['error'] ?? 'unknown')),
            'mkdirRaw'  => $mkdirResult,
            'raw'       => $uploadResult,
        ];
    }


    // ── Змінити головний домен cPanel акаунту ────────────────────────────────
    // WHM API: modifyacct  — змінює domain (primary domain) для існуючого акаунту
    public function changePrimaryDomain(string $cpanelUsername, string $newDomain): array
    {
        $res = $this->request('modifyacct', [
            'user'   => $cpanelUsername,
            'domain' => $newDomain,
        ]);

        $status = (int)($res['metadata']['result'] ?? $res['cpanelresult']['data']['result'] ?? 0);
        $reason = $res['metadata']['reason'] ?? $res['cpanelresult']['error'] ?? $res['cpanelresult']['data']['reason'] ?? json_encode($res);

        return [
            'success' => $status === 1,
            'message' => $reason,
            'raw'     => $res,
        ];
    }

    // ── Отримати WHM привілеї реселера (myprivs) ─────────────────────────────
    public function getPrivileges(): ?array
    {
        $res = $this->request('myprivs', []);
        if (!empty($res['cpanelresult']['error']) && str_contains(strtolower($res['cpanelresult']['error']), 'access denied')) {
            return null; // Token restricts access to myprivs
        }
        return $res['privs'] ?? null;
    }

    // ── Список пакетів (планів) хостингу ─────────────────────────────────────
    public function listPackages(): array
    {
        $res  = $this->request('listpkgs', []);
        $pkgs = $res['package'] ?? [];
        return array_map(fn($p) => $p['name'] ?? '', $pkgs);
    }

    // ── Призупинити cPanel акаунт ─────────────────────────────────────────────
    // WHM: suspendacct — потребує привілею suspend-acct
    public function suspendAccount(string $username, string $reason = ''): array
    {
        $params = ['user' => $username];
        if ($reason) $params['reason'] = $reason;

        $res    = $this->request('suspendacct', $params);
        $status = (int)($res['metadata']['result'] ?? 0);
        $msg    = $res['metadata']['reason'] ?? json_encode($res);

        return ['success' => $status === 1, 'message' => $msg, 'raw' => $res];
    }

    // ── Відновити (unsuspend) cPanel акаунт ──────────────────────────────────
    // WHM: unsuspendacct — потребує привілею suspend-acct
    public function unsuspendAccount(string $username): array
    {
        $res    = $this->request('unsuspendacct', ['user' => $username]);
        $status = (int)($res['metadata']['result'] ?? 0);
        $msg    = $res['metadata']['reason'] ?? json_encode($res);

        return ['success' => $status === 1, 'message' => $msg, 'raw' => $res];
    }

    // ── Змінити пакет (план) cPanel акаунту ──────────────────────────────────
    // WHM: changepackage — потребує привілею upgrade-account
    public function changeAccountPlan(string $username, string $plan): array
    {
        $res    = $this->request('changepackage', [
            'user' => $username,
            'pkg'  => $plan,
        ]);
        $status = (int)($res['metadata']['result'] ?? 0);
        $msg    = $res['metadata']['reason'] ?? json_encode($res);

        return ['success' => $status === 1, 'message' => $msg, 'raw' => $res];
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
    public function request(string $function, array $params = []): array
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
            CURLOPT_SSL_VERIFYPEER => false,
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

    // ── HTTP до cPanel UAPI через WHM (Port 2087, json-api/cpanel) ────────────
    // Дозволяє виконувати UAPI функції від імені конкретного cPanel юзера
    public function requestCpanelUapi(string $cpanelUser, string $module, string $function, array $params = []): array
    {
        $query = array_merge([
            'cpanel_jsonapi_user'       => $cpanelUser,
            'cpanel_jsonapi_apiversion' => '2',
            'cpanel_jsonapi_module'     => $module,
            'cpanel_jsonapi_func'       => $function,
        ], $params);

        $url = sprintf('https://%s:2087/json-api/cpanel?%s',
            $this->host,
            http_build_query($query)
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'Authorization: whm ' . $this->user . ':' . $this->token,
            ],
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) throw new \RuntimeException('WHM cPanel UAPI curl error: ' . $err);

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}