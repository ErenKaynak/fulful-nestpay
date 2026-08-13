<?php

declare(strict_types=1);

namespace SanalPos\Nestpay;

final class Sanitizer
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function payload(array $payload): array
    {
        $sanitized = [];
        $sensitive = [
            'pan',
            'cardnumber',
            'card_number',
            'cv2',
            'cvv',
            'cvv2',
            'Ecom_Payment_Card_ExpDate_Year',
            'Ecom_Payment_Card_ExpDate_Month',
        ];

        foreach ($payload as $key => $value) {
            $key = self::gatewayMessage($key);

            if (in_array(strtolower($key), array_map('strtolower', $sensitive), true)) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = self::payload($value);
                continue;
            }

            $sanitized[$key] = self::gatewayMessage($value);
        }

        return $sanitized;
    }

    /**
     * Cleans gateway values without redacting them, for hash verification/payment completion.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function gatewayPayload(array $payload): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            $key = function_exists('wp_unslash') ? (string) wp_unslash((string) $key) : (string) $key;
            $clean[$key] = is_array($value) ? self::gatewayPayload($value) : self::rawGatewayValue($value);
        }

        return $clean;
    }

    public static function rawGatewayValue(mixed $value): string
    {
        return function_exists('wp_unslash') ? (string) wp_unslash((string) $value) : (string) $value;
    }

    public static function gatewayMessage(mixed $value): string
    {
        if (function_exists('sanitize_text_field')) {
            return sanitize_text_field(wp_unslash((string) $value));
        }

        return trim(strip_tags((string) $value));
    }
}
