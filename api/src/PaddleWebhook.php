<?php

class PaddleWebhook
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = require __DIR__ . '/../config/config.php';
    }

    // ── Головний обробник ─────────────────────────────────────────────────────
    public function handle(string $rawBody, string $signature): array
    {
        // 1. Верифікуємо підпис
        if (!$this->verifySignature($rawBody, $signature)) {
            Logger::warning('webhook.signature', 'Invalid Paddle signature', null, [
                'signature' => substr($signature, 0, 32) . '...',
            ]);
            return ['status' => 'error', 'message' => 'Invalid signature'];
        }

        $payload = json_decode($rawBody, true);
        if (!$payload || !isset($payload['event_type'])) {
            return ['status' => 'error', 'message' => 'Invalid payload'];
        }

        $eventType = $payload['event_type'];
        $data      = $payload['data'] ?? [];

        // Email клієнта — в різних подіях по-різному
        $email = $this->extractEmail($payload);

        Logger::info('webhook.received', "Paddle event: {$eventType}", $email, [
            'event_type' => $eventType,
            'paddle_id'  => $data['id'] ?? null,
        ]);

        try {
            $result = match (true) {
                // Підписка активована або поновлена
                in_array($eventType, ['subscription.activated', 'subscription.renewed'], true)
                    => $this->onSubscriptionActivated($data, $email),

                // Підписка скасована
                $eventType === 'subscription.canceled'
                    => $this->onSubscriptionCanceled($data, $email),

                // Прострочений платіж
                $eventType === 'subscription.past_due'
                    => $this->onSubscriptionPastDue($data, $email),

                // Підписка призупинена
                $eventType === 'subscription.paused'
                    => $this->onSubscriptionPaused($data, $email),

                // Транзакція завершена (перший платіж)
                $eventType === 'transaction.completed'
                    => $this->onTransactionCompleted($data, $email),

                default => $this->onIgnored($eventType, $email),
            };

            Logger::webhook($eventType, 'ok', $rawBody, $email, $data['id'] ?? null);
            return $result;

        } catch (\Throwable $e) {
            Logger::error('webhook.error', $e->getMessage(), $email, ['event_type' => $eventType]);
            Logger::webhook($eventType, 'error', $rawBody, $email, $data['id'] ?? null, $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    // ── subscription.activated / subscription.renewed ─────────────────────────
    private function onSubscriptionActivated(array $data, ?string $email): array
    {
        if (!$email) return ['status' => 'ignored', 'reason' => 'no email'];

        $item      = $data['items'][0] ?? [];
        $planName  = $item['product']['name'] ?? $item['price']['description'] ?? 'Plan';
        $expiresAt = $data['current_billing_period']['ends_at'] ?? $data['next_billed_at'] ?? '';
        $amount    = '';
        if (isset($item['price']['unit_price']['amount'], $item['price']['unit_price']['currency_code'])) {
            $amount = number_format((int)$item['price']['unit_price']['amount'] / 100, 2)
                    . ' ' . strtoupper($item['price']['unit_price']['currency_code']);
        }

        // Провізіонуємо WHM якщо ще немає
        $whmResult = null;
        if (!empty($this->cfg['whm_token'])) {
            $whm       = new WhmService();
            $whmResult = $whm->ensureAccount($email, $this->cfg['whm_plan']);

            if ($whmResult['created'] ?? false) {
                // Надсилаємо cPanel credentials
                $mailer = new Mailer();
                $mailer->sendCpanelCredentials(
                    $email,
                    $email,
                    $whmResult['username'],
                    $whmResult['password'],
                    $whmResult['domain'],
                    $this->cfg['whm_plan']
                );
                Logger::info('whm.created', "WHM account created via webhook", $email, $whmResult);
            }
        }

        // Email клієнту про активацію
        $mailer = new Mailer();
        $mailer->sendSubscriptionActivated($email, $planName, $expiresAt);

        // Email адміну
        $mailer->notifyAdminNewSubscription($email, $planName, $amount);

        Logger::info('subscription.activated', "Subscription activated: {$planName}", $email, [
            'plan'    => $planName,
            'expires' => $expiresAt,
            'whm'     => $whmResult,
        ]);

        return ['status' => 'ok', 'action' => 'subscription_activated', 'whm' => $whmResult];
    }

    // ── subscription.canceled ─────────────────────────────────────────────────
    private function onSubscriptionCanceled(array $data, ?string $email): array
    {
        if (!$email) return ['status' => 'ignored', 'reason' => 'no email'];

        $item     = $data['items'][0] ?? [];
        $planName = $item['product']['name'] ?? 'Plan';

        $mailer = new Mailer();
        $mailer->sendSubscriptionCanceled($email, $planName);

        Logger::info('subscription.canceled', "Subscription canceled: {$planName}", $email);

        // WHM акаунт НЕ видаляємо автоматично — тільки логуємо
        // Адмін вирішує вручну через панель
        Logger::warning('whm.pending_suspend', "Subscription canceled — WHM account may need suspension", $email);

        return ['status' => 'ok', 'action' => 'subscription_canceled'];
    }

    // ── subscription.past_due ─────────────────────────────────────────────────
    private function onSubscriptionPastDue(array $data, ?string $email): array
    {
        if (!$email) return ['status' => 'ignored', 'reason' => 'no email'];

        $item      = $data['items'][0] ?? [];
        $planName  = $item['product']['name'] ?? 'Plan';
        $updateUrl = $data['management_urls']['update_payment_method'] ?? '';

        $mailer = new Mailer();
        $mailer->sendPaymentPastDue($email, $planName, $updateUrl);

        Logger::warning('subscription.past_due', "Payment past due: {$planName}", $email, [
            'update_url' => $updateUrl,
        ]);

        return ['status' => 'ok', 'action' => 'payment_past_due'];
    }

    // ── subscription.paused ───────────────────────────────────────────────────
    private function onSubscriptionPaused(array $data, ?string $email): array
    {
        if (!$email) return ['status' => 'ignored', 'reason' => 'no email'];
        Logger::warning('subscription.paused', 'Subscription paused', $email);
        return ['status' => 'ok', 'action' => 'subscription_paused'];
    }

    // ── transaction.completed ─────────────────────────────────────────────────
    private function onTransactionCompleted(array $data, ?string $email): array
    {
        if (!$email) return ['status' => 'ignored', 'reason' => 'no email'];
        Logger::info('transaction.completed', 'Transaction completed', $email, [
            'id'     => $data['id'] ?? null,
            'status' => $data['status'] ?? null,
        ]);
        return ['status' => 'ok', 'action' => 'transaction_logged'];
    }

    // ── ignored ───────────────────────────────────────────────────────────────
    private function onIgnored(string $eventType, ?string $email): array
    {
        Logger::info('webhook.ignored', "Unhandled event: {$eventType}", $email);
        return ['status' => 'ignored', 'event_type' => $eventType];
    }

    // ── Витягти email з різних типів подій ────────────────────────────────────
    private function extractEmail(array $payload): ?string
    {
        $data = $payload['data'] ?? [];

        // subscription.* — customer.email
        if (!empty($data['customer']['email'])) return strtolower(trim($data['customer']['email']));

        // transaction.* — customer або billing_details
        if (!empty($data['billing_details']['email'])) return strtolower(trim($data['billing_details']['email']));

        // Іноді email в address
        if (!empty($data['address']['email'])) return strtolower(trim($data['address']['email']));

        return null;
    }

    // ── Верифікація підпису (Paddle Billing v2) ────────────────────────────────
    // https://developer.paddle.com/webhooks/signature-verification
    private function verifySignature(string $rawBody, string $signatureHeader): bool
    {
        $secret = trim($this->cfg['paddle_webhook_secret'] ?? '');

        // Якщо секрет не налаштовано — пропускаємо (dev mode)
        if (!$secret) {
            Logger::warning('webhook.no_secret', 'PADDLE_WEBHOOK_SECRET not set — skipping signature check');
            return true;
        }

        // Формат: ts=1234567890;h1=abc...
        $parts = [];
        foreach (explode(';', $signatureHeader) as $part) {
            [$k, $v] = explode('=', $part, 2) + ['', ''];
            $parts[$k] = $v;
        }

        $ts        = $parts['ts'] ?? '';
        $signature = $parts['h1'] ?? '';
        if (!$ts || !$signature) return false;

        $expected = hash_hmac('sha256', "{$ts}:{$rawBody}", $secret);
        return hash_equals($expected, $signature);
    }
}
