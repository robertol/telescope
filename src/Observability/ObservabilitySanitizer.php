<?php

namespace Laravel\Telescope\Observability;

class ObservabilitySanitizer
{
    /**
     * @var array<int, string>
     */
    protected array $sensitiveKeys = [
        'authorization',
        'cookie',
        'set-cookie',
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'confirm_password',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'secret',
        'client_secret',
        'cvv',
        'cvc',
        'card_number',
        'pan',
        'security_code',
        'php-auth-pw',
        'x-csrf-token',
        'x-xsrf-token',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sanitize(array $payload): array
    {
        return $this->walk($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function walk(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            if ($this->isSensitiveKey((string) $key) || $this->looksLikePan($value)) {
                $sanitized[$key] = '********';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->walk($value);

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    protected function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '_'], '', $key));

        foreach ($this->sensitiveKeys as $sensitive) {
            if (strtolower(str_replace(['-', '_'], '', $sensitive)) === $normalized) {
                return true;
            }
        }

        return false;
    }

    protected function looksLikePan(mixed $value): bool
    {
        if (! is_string($value) && ! is_int($value)) {
            return false;
        }

        $raw = (string) $value;

        if (! preg_match('/^[\d\s-]{13,23}$/', $raw)) {
            return false;
        }

        $digits = preg_replace('/\D/', '', $raw);

        return is_string($digits) && strlen($digits) >= 13 && strlen($digits) <= 19;
    }
}
