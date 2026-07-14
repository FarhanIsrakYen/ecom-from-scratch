<?php

namespace App\Support\Monitoring;

class LogSanitizer
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function context(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, ['password', 'token', 'secret', 'authorization', 'card', 'card_number', 'cvc'], true)) {
                $sanitized[$key] = '[redacted]';

                continue;
            }

            $sanitized[$key] = match (true) {
                is_array($value) => self::context($value),
                is_string($value) && str_contains($normalizedKey, 'email') => self::email($value),
                is_string($value) && str_contains($normalizedKey, 'phone') => self::phone($value),
                is_string($value) => self::line($value),
                default => $value,
            };
        }

        return $sanitized;
    }

    public static function line(string $line): string
    {
        $line = (string) preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[masked-email]', $line);

        return (string) preg_replace('/(?<!\d)(\+?\d[\d\s().-]{7,}\d)(?!\d)/', '[masked-phone]', $line);
    }

    private static function email(string $email): string
    {
        if (! str_contains($email, '@')) {
            return '[masked-email]';
        }

        [$name, $domain] = explode('@', $email, 2);

        return substr($name, 0, 1).'***@'.$domain;
    }

    private static function phone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        return $digits === '' ? '[masked-phone]' : '***'.substr($digits, -4);
    }
}
