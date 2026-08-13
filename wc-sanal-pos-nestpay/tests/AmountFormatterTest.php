<?php

declare(strict_types=1);

namespace SanalPos\Nestpay\Tests;

use PHPUnit\Framework\TestCase;
use SanalPos\Nestpay\AmountFormatter;

final class AmountFormatterTest extends TestCase
{
    public function testFormatsTryAmountWithDotDecimalSeparator(): void
    {
        self::assertSame('1234.50', AmountFormatter::format(1234.5));
        self::assertSame('0.99', AmountFormatter::format(0.99));
    }
}
