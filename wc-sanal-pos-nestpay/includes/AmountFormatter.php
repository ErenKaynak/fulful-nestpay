<?php

declare(strict_types=1);

namespace SanalPos\Nestpay;

final class AmountFormatter
{
    public static function format(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
