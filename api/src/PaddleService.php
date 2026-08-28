<?php

class PaddleService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $cfg = require __DIR__ . '/../config/config.php';

        // trim() критично — ключ з .env може мати \n або пробіли
        $this->apiKey  = trim($cfg['paddle_api_key']);
        $this->baseUrl = rtrim(trim($cfg['paddle_api_url']), '/');
    }

    // ── Створення транзакції для оплати рахунку ───────────────────────────────
    /**
     * Створює Paddle transaction для одноразового платежу.
     *
     * @param int    $invoiceId     ID рахунку в нашій БД
     * @param float  $amount        Сума (напр. 99.99)
     * @param string $currency      ISO код валюти (напр. "USD", "UAH")
     * @param string $customerEmail Email клієнта
     * @return array { transaction_id: string }
     * @throws \RuntimeException
     */
    public function createTransaction(
        int    $invoiceId,
        float  $amount,
        string $currency,
        string $customerEmail
    ): array {
        // Paddle зберігає суми в найменших одиницях (cents)
        // UAH, USD — 2 знаки після коми
        $amountInCents = (string)(int)round($amount * 100);

        $body = [
            'items' => [[
                'quantity' => 1,
                'price' => [
                    'name'        => "Invoice #{$invoiceId}",
                    'description' => "Payment for invoice #{$invoiceId}",
                    'unit_price'  => [
                        'amount'        => $amountInCents,
                        'currency_code' => strtoupper($currency),
                    ],
                    'product' => [
                        'name'         => "Invoice Payment",
                        'tax_category' => 'standard',
                    ]
                ],
            ]],
            'custom_data' => [
                // Custom data values in Paddle must be strings
                'invoice_id' => (string)$invoiceId,
            ]
        ];

        $raw = $this->requestRaw('POST', '/transactions', $body);

        if ($raw['curl_err']) {
            throw new \RuntimeException('Paddle cURL error: ' . $raw['curl_err']);
        }

        $resp = json_decode($raw['body'], true);

        if ($raw['http_code'] !== 201 || !isset($resp['data']['id'])) {
            $errDetail = $resp['error']['detail'] ?? ($resp['error']['code'] ?? $raw['body']);
            
            // Extract validation errors if any
            if (!empty($resp['error']['errors'])) {
                $valErrors = [];
                foreach ($resp['error']['errors'] as $e) {
                    $valErrors[] = ($e['field'] ?? 'unknown') . ': ' . ($e['message'] ?? 'invalid');
                }
                $errDetail .= ' | Validation: ' . implode(', ', $valErrors);
            }
            
            throw new \RuntimeException("Paddle transaction error [{$raw['http_code']}]: {$errDetail}");
        }

        return [
            'transaction_id' => $resp['data']['id'],
        ];
    }

    public function getSubscriptionsByEmail(string $email): array
    {
        // Paddle Billing v2 — шукаємо customer за email
        $customers = $this->request('GET', '/customers', ['email' => $email, 'per_page' => 10]);

        // Якщо помилка аутентифікації або інша — кидаємо щоб вище спіймали
        if (isset($customers['error'])) {
            $code   = $customers['error']['code']   ?? 'unknown';
            $detail = $customers['error']['detail']  ?? 'Paddle API error';
            throw new \RuntimeException("Paddle API error [{$code}]: {$detail}");
        }

        if (!empty($customers['data'])) {
            $customerId = $customers['data'][0]['id'];
            $subs = $this->request('GET', '/subscriptions', [
                'customer_id' => $customerId,
                'per_page'    => 50,
            ]);

            if (isset($subs['error'])) {
                throw new \RuntimeException('Paddle subscriptions error: ' . ($subs['error']['detail'] ?? 'unknown'));
            }

            $result = [];
            foreach ($subs['data'] ?? [] as $sub) {
                $result[] = $this->normalizeBillingV2($sub);
            }
            return $result;
        }

        // Fallback: Classic v1
        return $this->classicSubscriptionsByEmail($email);
    }

    public function getTransactionsByEmail(string $email): array
    {
        $customers = $this->request('GET', '/customers', ['email' => $email, 'per_page' => 10]);

        if (empty($customers['data'])) {
            return [];
        }

        $customerId = $customers['data'][0]['id'];
        $txns = $this->request('GET', '/transactions', [
            'customer_id' => $customerId,
            'per_page'    => 50,
        ]);

        if (empty($txns['data'])) {
            return [];
        }

        $result = [];
        foreach ($txns['data'] as $txn) {
            $statusMap = [
                'completed' => 'paid',
                'billed'    => 'unpaid',
                'past_due'  => 'unpaid',
                'canceled'  => 'cancelled',
            ];
            $status = $statusMap[$txn['status']] ?? 'paid';
            $amount = (float)($txn['details']['totals']['total'] ?? 0) / 100;

            $result[] = [
                'id'         => $txn['id'],
                'created_at' => $txn['created_at'],
                'due_date'   => $txn['billed_at'] ?? $txn['created_at'],
                'amount'     => $amount,
                'currency'   => $txn['currency_code'] ?? 'USD',
                'status'     => $status,
                'is_paddle'  => true,
            ];
        }
        return $result;
    }

    // ── Debug: повертає сирий стан підключення до Paddle ─────────────────────
    public function debugConnection(): array
    {
        $keyLen    = strlen($this->apiKey);
        $keyPrefix = $keyLen > 8 ? substr($this->apiKey, 0, 4) . '...' . substr($this->apiKey, -4) : '(short)';
        $hasSpaces = $this->apiKey !== trim($this->apiKey);
        $hasNewline= str_contains($this->apiKey, "\n") || str_contains($this->apiKey, "\r");

        $raw = $this->requestRaw('GET', '/customers', ['per_page' => 1]);

        return [
            'key_length'   => $keyLen,
            'key_preview'  => $keyPrefix,
            'has_spaces'   => $hasSpaces,
            'has_newline'  => $hasNewline,
            'api_url'      => $this->baseUrl,
            'http_code'    => $raw['http_code'],
            'response'     => $raw['body'],
        ];
    }

    // ── Paddle Billing v2 normalizer ──────────────────────────────────────────
    private function normalizeBillingV2(array $sub): array
    {
        $item    = $sub['items'][0] ?? [];
        $price   = $item['price']   ?? [];
        $product = $item['product'] ?? [];

        $nextBilling = $sub['next_billed_at'] ?? null;
        $endsAt      = $sub['current_billing_period']['ends_at']    ?? $nextBilling;
        $startsAt    = $sub['current_billing_period']['starts_at']  ?? $sub['started_at'] ?? null;

        $amount = null;
        if (isset($price['unit_price']['amount'], $price['unit_price']['currency_code'])) {
            $amount = number_format((int)$price['unit_price']['amount'] / 100, 2) . ' '
                    . strtoupper($price['unit_price']['currency_code']);
        }

        return [
            'id'           => $sub['id']     ?? null,
            'status'       => $sub['status'] ?? 'unknown',
            'plan_name'    => $product['name'] ?? $price['description'] ?? 'Plan',
            'plan_id'      => $price['id']     ?? null,
            'amount'       => $amount,
            'interval'     => $price['billing_cycle']['interval'] ?? null,
            'started_at'   => $startsAt,
            'expires_at'   => $endsAt,
            'next_payment' => $nextBilling,
            'cancel_url'   => $sub['management_urls']['cancel']                ?? null,
            'update_url'   => $sub['management_urls']['update_payment_method'] ?? null,
            'api_version'  => 'v2',
        ];
    }

    // ── Paddle Classic v1 ─────────────────────────────────────────────────────
    private function classicSubscriptionsByEmail(string $email): array
    {
        $vendorId = trim($_ENV['PADDLE_VENDOR_ID'] ?? '');
        if (!$vendorId) return []; // Classic не налаштовано

        $url  = 'https://vendors.paddle.com/api/2.0/subscription/users';
        $body = http_build_query([
            'vendor_id'        => $vendorId,
            'vendor_auth_code' => $this->apiKey,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($raw, true);
        if (empty($data['success']) || empty($data['response'])) return [];

        $result = [];
        foreach ($data['response'] as $sub) {
            if (strtolower($sub['user_email'] ?? '') !== strtolower($email)) continue;
            $result[] = $this->normalizeClassicV1($sub);
        }
        return $result;
    }

    private function normalizeClassicV1(array $sub): array
    {
        return [
            'id'           => (string)($sub['subscription_id'] ?? ''),
            'status'       => $sub['state'] ?? 'unknown',
            'plan_name'    => $sub['plan_name'] ?? 'Plan',
            'plan_id'      => (string)($sub['plan_id'] ?? ''),
            'amount'       => ($sub['last_payment']['amount'] ?? '') . ' ' . strtoupper($sub['last_payment']['currency'] ?? ''),
            'interval'     => null,
            'started_at'   => $sub['signup_date']        ?? null,
            'expires_at'   => $sub['next_payment']['date'] ?? null,
            'next_payment' => $sub['next_payment']['date'] ?? null,
            'cancel_url'   => $sub['cancel_url']  ?? null,
            'update_url'   => $sub['update_url']  ?? null,
            'api_version'  => 'v1',
        ];
    }

    // ── HTTP helpers ──────────────────────────────────────────────────────────
    private function request(string $method, string $path, array $params = []): array
    {
        $raw = $this->requestRaw($method, $path, $params);
        $decoded = json_decode($raw['body'], true);
        return is_array($decoded) ? $decoded : [];
    }

    private function requestRaw(string $method, string $path, array $params = []): array
    {
        $url = $this->baseUrl . $path;
        if ($method === 'GET' && $params) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Paddle-Version: 1',
            ],
        ]);

        if ($method !== 'GET' && $params) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        return [
            'http_code' => $code,
            'body'      => $body ?: '',
            'curl_err'  => $err,
        ];
    }
}

