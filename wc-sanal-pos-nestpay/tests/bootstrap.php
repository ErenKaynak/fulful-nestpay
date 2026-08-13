<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/AmountFormatter.php';
require_once __DIR__ . '/../includes/OrderMeta.php';
require_once __DIR__ . '/../includes/Sanitizer.php';

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value): string
    {
        return trim(strip_tags((string) $value));
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return $value;
    }
}
