<?php

class Mailer
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = require __DIR__ . '/../config/config.php';
    }

    // ── Клієнту: новий cPanel акаунт ─────────────────────────────────────────
    public function sendCpanelCredentials(
        string $toEmail,
        string $toName,
        string $cpanelUser,
        string $cpanelPass,
        string $domain,
        string $plan
    ): bool {
        $host     = $this->cfg['whm_host'];
        $cpanelUrl= "https://{$domain}:2083";
        $webmail  = "https://{$domain}:2096";

        $subject = 'Ваш хостинг акаунт створено — ' . $domain;

        $body = $this->template('cpanel_created', [
            'name'        => $toName ?: $toEmail,
            'domain'      => $domain,
            'cpanel_url'  => $cpanelUrl,
            'webmail_url' => $webmail,
            'username'    => $cpanelUser,
            'password'    => $cpanelPass,
            'plan'        => $plan,
            'server'      => $host,
            'support'     => $this->cfg['mail_from'] ?? $this->cfg['admin_email'],
        ]);

        return $this->send($toEmail, $subject, $body);
    }

    // ── Клієнту: підписка активована ─────────────────────────────────────────
    public function sendSubscriptionActivated(string $toEmail, string $planName, string $expiresAt): bool
    {
        $subject = 'Підписка активована — ' . $planName;
        $body    = $this->template('subscription_activated', [
            'email'    => $toEmail,
            'plan'     => $planName,
            'expires'  => $expiresAt ? date('d.m.Y', strtotime($expiresAt)) : '—',
            'portal'   => $this->cfg['billing_url'] ?? '',
            'support'  => $this->cfg['admin_email'],
        ]);
        return $this->send($toEmail, $subject, $body);
    }

    // ── Клієнту: підписка скасована ──────────────────────────────────────────
    public function sendSubscriptionCanceled(string $toEmail, string $planName): bool
    {
        $subject = 'Підписку скасовано — ' . $planName;
        $body    = $this->template('subscription_canceled', [
            'email'   => $toEmail,
            'plan'    => $planName,
            'portal'  => $this->cfg['billing_url'] ?? '',
            'support' => $this->cfg['admin_email'],
        ]);
        return $this->send($toEmail, $subject, $body);
    }

    // ── Клієнту: прострочений платіж ─────────────────────────────────────────
    public function sendPaymentPastDue(string $toEmail, string $planName, string $updateUrl): bool
    {
        $subject = '⚠️ Проблема з оплатою — ' . $planName;
        $body    = $this->template('payment_past_due', [
            'email'      => $toEmail,
            'plan'       => $planName,
            'update_url' => $updateUrl,
            'support'    => $this->cfg['admin_email'],
        ]);
        return $this->send($toEmail, $subject, $body);
    }

    // ── Адміну: нова підписка ─────────────────────────────────────────────────
    public function notifyAdminNewSubscription(string $clientEmail, string $planName, string $amount): bool
    {
        $subject = '🎉 Нова підписка: ' . $clientEmail;
        $body    = $this->template('admin_new_subscription', [
            'client'  => $clientEmail,
            'plan'    => $planName,
            'amount'  => $amount,
            'time'    => date('d.m.Y H:i'),
            'portal'  => $this->cfg['billing_url'] ?? '',
        ]);
        return $this->send($this->cfg['admin_email'], $subject, $body);
    }

    // ── Надіслати листа ───────────────────────────────────────────────────────
    private function send(string $to, string $subject, string $html): bool
    {
        $from     = $this->cfg['mail_from']      ?? ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'example.com'));
        $fromName = $this->cfg['mail_from_name'] ?? 'Billing Portal';

        // SMTP через PHPMailer якщо налаштовано, інакше mail()
        if (!empty($this->cfg['smtp_host'])) {
            return $this->sendSmtp($to, $subject, $html, $from, $fromName);
        }

        // Fallback: PHP mail()
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
        $headers .= "Reply-To: {$from}\r\n";
        $headers .= "X-Mailer: BillingPortal/1.0\r\n";

        $result = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers);

        Logger::info(
            'email.sent',
            $result ? "Email sent to {$to}: {$subject}" : "Email FAILED to {$to}: {$subject}",
            $to,
            ['subject' => $subject, 'success' => $result]
        );

        return $result;
    }

    // ── SMTP (без зовнішніх бібліотек, через fsockopen) ───────────────────────
    private function sendSmtp(string $to, string $subject, string $html, string $from, string $fromName): bool
    {
        $cfg  = $this->cfg;
        $host = $cfg['smtp_host'];
        $port = (int)($cfg['smtp_port'] ?? 587);
        $user = $cfg['smtp_user'] ?? '';
        $pass = $cfg['smtp_pass'] ?? '';

        try {
            $boundary = md5(uniqid());
            $message  = "Content-Type: text/html; charset=UTF-8\r\n\r\n" . $html;

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ]);

            $prefix = ($port === 465) ? 'ssl://' : '';
            $sock   = stream_socket_client("{$prefix}{$host}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
            if (!$sock) throw new \RuntimeException("SMTP connect failed: $errstr ($errno)");

            $read = fn() => fgets($sock, 512);
            $write= fn($cmd) => fputs($sock, $cmd . "\r\n");

            $read(); // 220 greeting
            $write("EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $resp = '';
            while ($line = fgets($sock, 512)) {
                $resp .= $line;
                if ($line[3] === ' ') break;
            }

            // STARTTLS for port 587
            if ($port === 587 && str_contains($resp, 'STARTTLS')) {
                $write('STARTTLS');
                $read();
                stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $write("EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
                while ($line = fgets($sock, 512)) { if ($line[3] === ' ') break; }
            }

            if ($user) {
                $write('AUTH LOGIN');
                $read();
                $write(base64_encode($user));
                $read();
                $write(base64_encode($pass));
                $read();
            }

            $write("MAIL FROM:<{$from}>");           $read();
            $write("RCPT TO:<{$to}>");               $read();
            $write('DATA');                           $read();

            $msg  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
            $msg .= "To: {$to}\r\n";
            $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $msg .= "MIME-Version: 1.0\r\n";
            $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
            $msg .= "\r\n" . $html . "\r\n.";

            $write($msg);  $read();
            $write('QUIT'); fclose($sock);

            Logger::info('email.smtp', "SMTP sent to {$to}: {$subject}", $to);
            return true;

        } catch (\Throwable $e) {
            Logger::error('email.smtp', 'SMTP error: ' . $e->getMessage(), $to, ['subject' => $subject]);
            return false;
        }
    }

    // ── HTML Templates ────────────────────────────────────────────────────────
    private function template(string $name, array $vars): string
    {
        $content = match($name) {
            'cpanel_created' => "
                <h2 style='color:#22d3a0'>✅ Ваш хостинг акаунт створено!</h2>
                <p>Вітаємо, <strong>{name}</strong>! Ваш хостинг готовий до роботи.</p>
                <table style='border-collapse:collapse;width:100%;margin:20px 0'>
                    <tr><td style='padding:8px 12px;background:#1a1e26;color:#5a6478;width:160px'>Домен</td>
                        <td style='padding:8px 12px;font-family:monospace'>{domain}</td></tr>
                    <tr><td style='padding:8px 12px;background:#1a1e26;color:#5a6478'>Логін cPanel</td>
                        <td style='padding:8px 12px;font-family:monospace'>{username}</td></tr>
                    <tr><td style='padding:8px 12px;background:#1a1e26;color:#5a6478'>Пароль</td>
                        <td style='padding:8px 12px;font-family:monospace'>{password}</td></tr>
                    <tr><td style='padding:8px 12px;background:#1a1e26;color:#5a6478'>Тариф</td>
                        <td style='padding:8px 12px'>{plan}</td></tr>
                </table>
                <p><a href='{cpanel_url}' style='background:#4f8ef7;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block;margin:8px 4px'>Відкрити cPanel</a>
                <a href='{webmail_url}' style='background:#2a3040;color:#e8eaf0;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block;margin:8px 4px'>Webmail</a></p>
                <p style='color:#5a6478;font-size:13px'>⚠️ Збережіть пароль у безпечному місці. З питань — {support}</p>",

            'subscription_activated' => "
                <h2 style='color:#22d3a0'>🎉 Підписка активована!</h2>
                <p>Тариф <strong>{plan}</strong> активовано для <strong>{email}</strong>.</p>
                <p>Дійсна до: <strong>{expires}</strong></p>
                <p><a href='{portal}' style='background:#4f8ef7;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block'>Відкрити кабінет</a></p>
                <p style='color:#5a6478;font-size:13px'>Підтримка: {support}</p>",

            'subscription_canceled' => "
                <h2 style='color:#f75a5a'>Підписку скасовано</h2>
                <p>Тариф <strong>{plan}</strong> для <strong>{email}</strong> було скасовано.</p>
                <p>Ваш хостинг залишається активним до кінця оплаченого періоду.</p>
                <p><a href='{portal}' style='background:#4f8ef7;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block'>Поновити підписку</a></p>
                <p style='color:#5a6478;font-size:13px'>Питання? {support}</p>",

            'payment_past_due' => "
                <h2 style='color:#f5c842'>⚠️ Проблема з оплатою</h2>
                <p>Не вдалося списати кошти за тариф <strong>{plan}</strong>.</p>
                <p>Будь ласка, оновіть платіжні дані щоб уникнути блокування хостингу.</p>
                <p><a href='{update_url}' style='background:#f75a5a;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block'>Оновити картку</a></p>
                <p style='color:#5a6478;font-size:13px'>Підтримка: {support}</p>",

            'admin_new_subscription' => "
                <h2 style='color:#4f8ef7'>🎉 Нова підписка</h2>
                <table style='border-collapse:collapse;width:100%;margin:20px 0'>
                    <tr><td style='padding:8px 12px;background:#1a1e26;color:#5a6478;width:120px'>Клієнт</td>
                        <td style='padding:8px 12px'>{client}</td></tr>
                    <tr><td style='padding:8px 12px;background:#1a1e26;color:#5a6478'>Тариф</td>
                        <td style='padding:8px 12px'>{plan}</td></tr>
                    <tr><td style='padding:8px 12px;background:#1a1e26;color:#5a6478'>Сума</td>
                        <td style='padding:8px 12px'>{amount}</td></tr>
                    <tr><td style='padding:8px 12px;background:#1a1e26;color:#5a6478'>Час</td>
                        <td style='padding:8px 12px'>{time}</td></tr>
                </table>
                <p><a href='{portal}' style='background:#4f8ef7;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block'>Адмін-панель</a></p>",

            default => "<p>{message}</p>",
        };

        // Wrap у базовий layout
        $html = $this->baseLayout($content);

        // Replace placeholders
        foreach ($vars as $k => $v) {
            $html = str_replace('{' . $k . '}', htmlspecialchars((string)$v, ENT_QUOTES), $html);
        }
        return $html;
    }

    private function baseLayout(string $content): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'>
        <style>
            body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
                 background:#0a0c0f;color:#e8eaf0;margin:0;padding:0}
            .wrap{max-width:600px;margin:40px auto;background:#111418;
                  border:1px solid #1e2329;border-radius:16px;overflow:hidden}
            .header{background:linear-gradient(135deg,#4f8ef7,#7c5df7);padding:28px 36px}
            .header h1{margin:0;font-size:20px;color:#fff}
            .body{padding:36px}
            .footer{padding:20px 36px;border-top:1px solid #1e2329;
                    font-size:12px;color:#5a6478;text-align:center}
            h2{margin-top:0}
            a{color:#4f8ef7}
        </style></head><body>
        <div class='wrap'>
            <div class='header'><h1>● Billing Portal</h1></div>
            <div class='body'>{$content}</div>
            <div class='footer'>programist.com.ua · Автоматичне повідомлення</div>
        </div></body></html>";
    }
}
