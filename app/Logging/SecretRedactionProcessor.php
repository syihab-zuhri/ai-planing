<?php

namespace App\Logging;

use Monolog\LogRecord;

/**
 * SecretRedactionProcessor — Laravel tap yang mengimplementasi Monolog 3 ProcessorInterface
 * (sesuai Laravel 11: protected function tap($name, Logger $logger) membaca class yang punya __invoke).
 *
 * Acuan: SECURITY.md §3 TM-002 + §11.
 */
class SecretRedactionProcessor
{
    private const PATTERNS = [
        '/sk-[A-Za-z0-9_\-]{20,}/',
        '/sk-(proj-)?[A-Za-z0-9_\-]{20,}/',
        '/key-[A-Za-z0-9_\-]{20,}/',
        '/Bearer\s+[A-Za-z0-9_\-\.=]{10,}/i',
        '/(?<![A-Za-z0-9])AIza[0-9A-Za-z_\-]{30,}/',
        '/(?<![A-Za-z0-9])ghp_[A-Za-z0-9]{30,}/',
    ];

    private const SENSITIVE_FIELDS = [
        'api_key', 'apikey', 'api-key',
        'password', 'passwd',
        'secret',
        'token',
        'authorization',
        'access_token', 'refresh_token', 'private_token',
        'ninerouter_api_key', 'openai_api_key', 'openai_compat_api_key',
        'session_cookie',
    ];

    /**
     * Callback Monolog 3: menerima dan mengembalikan LogRecord immutable.
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->scrubMessage($record->message),
            context: $this->scrubArray($record->context),
            extra: $this->scrubArray($record->extra),
        );
    }

    /**
     * Melakukan scrubbing pada array (context atau extra).
     */
    private function scrubArray(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);
            if ($this->isSensitiveKey($lowerKey)) {
                $out[$key] = '***REDACTED***';
                continue;
            }
            if (is_string($value)) {
                $out[$key] = $this->scrubString($value);
            } elseif (is_array($value)) {
                $out[$key] = $this->scrubArray($value);
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    private function scrubMessage(string $message): string
    {
        return $this->scrubString($message);
    }

    private function scrubString(string $value): string
    {
        foreach (self::PATTERNS as $pattern) {
            $value = preg_replace_callback(
                $pattern,
                fn ($matches) => '***REDACTED***',
                $value,
            ) ?? $value;
        }
        return $value;
    }

    private function isSensitiveKey(string $lowerKey): bool
    {
        foreach (self::SENSITIVE_FIELDS as $field) {
            if ($lowerKey === $field) {
                return true;
            }
        }
        return false;
    }
}
