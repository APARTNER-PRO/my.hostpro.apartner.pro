<?php

/**
 * PasswordPolicy — перевірка надійності паролів.
 *
 * Вимоги:
 *  - Мінімум 8 символів
 *  - Хоча б одна ВЕЛИКА літера (A-Z)
 *  - Хоча б одна МАЛЕНЬКА літера (a-z)
 *  - Хоча б одна цифра (0-9)
 *  - Хоча б один спеціальний символ: !@#$%^&*()_+-=[]{}|;':\",./<>?
 */
class PasswordPolicy
{
    // ── Мінімальні вимоги ─────────────────────────────────────────────────────
    private const MIN_LENGTH        = 8;
    private const REQUIRE_UPPERCASE = true;
    private const REQUIRE_LOWERCASE = true;
    private const REQUIRE_DIGIT     = true;
    private const REQUIRE_SPECIAL   = true;

    /**
     * Перевіряє пароль і повертає масив помилок (порожній = пароль валідний).
     *
     * @return string[]
     */
    public static function validate(string $password): array
    {
        $errors = [];

        if (mb_strlen($password) < self::MIN_LENGTH) {
            $errors[] = 'Пароль повинен містити щонайменше ' . self::MIN_LENGTH . ' символів';
        }

        if (self::REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Пароль повинен містити хоча б одну велику літеру (A-Z)';
        }

        if (self::REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'Пароль повинен містити хоча б одну малу літеру (a-z)';
        }

        if (self::REQUIRE_DIGIT && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Пароль повинен містити хоча б одну цифру (0-9)';
        }

        if (self::REQUIRE_SPECIAL && !preg_match('/[!@#$%^&*()\-_=+\[\]{}|;:\'",.<>\/?\\\\]/', $password)) {
            $errors[] = 'Пароль повинен містити хоча б один спеціальний символ (!@#$%^&*...)';
        }

        return $errors;
    }

    /**
     * Повертає true якщо пароль валідний.
     */
    public static function isValid(string $password): bool
    {
        return empty(self::validate($password));
    }

    /**
     * Повертає перший рядок помилки або null.
     */
    public static function firstError(string $password): ?string
    {
        $errors = self::validate($password);
        return $errors[0] ?? null;
    }

    /**
     * Повертає рядок з усіма вимогами (для UI підказки).
     *
     * @return string[]
     */
    public static function requirements(): array
    {
        $reqs = ['Мінімум ' . self::MIN_LENGTH . ' символів'];
        if (self::REQUIRE_UPPERCASE) $reqs[] = 'Хоча б одна велика літера';
        if (self::REQUIRE_LOWERCASE) $reqs[] = 'Хоча б одна мала літера';
        if (self::REQUIRE_DIGIT)     $reqs[] = 'Хоча б одна цифра';
        if (self::REQUIRE_SPECIAL)   $reqs[] = 'Хоча б один спеціальний символ';
        return $reqs;
    }
}
