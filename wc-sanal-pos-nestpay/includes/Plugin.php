<?php

declare(strict_types=1);

namespace SanalPos\Nestpay;

final class Plugin
{
    public static function init(): void
    {
        add_filter('woocommerce_payment_gateways', [self::class, 'registerGateway']);
        add_action('rest_api_init', static function (): void {
            (new RestController())->registerRoutes();
        });
    }

    /**
     * @param array<int, string> $methods
     * @return array<int, string>
     */
    public static function registerGateway(array $methods): array
    {
        $methods[] = Gateway::class;

        return $methods;
    }
}
