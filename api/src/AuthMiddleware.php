<?php

class AuthMiddleware
{
    public static function requireAuth(): array
    {
        $cfg   = require __DIR__ . '/../config/config.php';
        $token = self::extractToken();

        if (!$token) {
            self::abort(401, 'No token provided');
        }

        try {
            $payload = JWT::decode($token, $cfg['jwt_secret']);
        } catch (\Exception $e) {
            self::abort(401, $e->getMessage());
        }

        return $payload;
    }

    public static function requireAdmin(): array
    {
        $payload = self::requireAuth();
        if (($payload['role'] ?? '') !== 'admin') {
            self::abort(403, 'Admin only');
        }
        return $payload;
    }

    private static function extractToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            return trim($m[1]);
        }
        // Also accept ?token= for convenience
        return $_GET['token'] ?? null;
    }

    public static function abort(int $code, string $message): never
    {
        http_response_code($code);
        echo json_encode(['error' => $message]);
        exit;
    }
}
