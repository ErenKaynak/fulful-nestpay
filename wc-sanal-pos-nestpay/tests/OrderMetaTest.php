<?php

declare(strict_types=1);

namespace SanalPos\Nestpay\Tests;

use PHPUnit\Framework\TestCase;
use SanalPos\Nestpay\OrderMeta;

final class OrderMetaTest extends TestCase
{
    public function testExtractsOrderIdFromGatewayOrderId(): void
    {
        self::assertSame(42, OrderMeta::orderIdFromGatewayOrderId('WC-42'));
        self::assertSame(0, OrderMeta::orderIdFromGatewayOrderId('ABC-42'));
        self::assertSame(0, OrderMeta::orderIdFromGatewayOrderId('WC-abc'));
    }
}
