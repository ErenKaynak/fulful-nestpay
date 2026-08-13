<?php

declare(strict_types=1);

namespace SanalPos\Nestpay\Tests;

use PHPUnit\Framework\TestCase;
use SanalPos\Nestpay\Sanitizer;

final class SanitizerTest extends TestCase
{
    public function testRedactsCardDataFromPayload(): void
    {
        $payload = Sanitizer::payload([
            'pan' => '4111111111111111',
            'cv2' => '123',
            'Response' => '<b>Approved</b>',
        ]);

        self::assertSame('[redacted]', $payload['pan']);
        self::assertSame('[redacted]', $payload['cv2']);
        self::assertSame('Approved', $payload['Response']);
    }

    public function testGatewayPayloadKeepsValuesForHashVerification(): void
    {
        $payload = Sanitizer::gatewayPayload([
            'pan' => '4111111111111111',
            'Response' => '<b>Approved</b>',
        ]);

        self::assertSame('4111111111111111', $payload['pan']);
        self::assertSame('Approved', $payload['Response']);
    }
}
