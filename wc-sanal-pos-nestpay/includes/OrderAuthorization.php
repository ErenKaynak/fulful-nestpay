<?php

declare(strict_types=1);

namespace SanalPos\Nestpay;

use WC_Order;

final class OrderAuthorization
{
    public static function canCurrentUserPay(WC_Order $order): bool
    {
        if (current_user_can('manage_woocommerce')) {
            return true;
        }

        $userId = get_current_user_id();
        if ($userId > 0 && (int) $order->get_user_id() === $userId) {
            return true;
        }

        $orderKey = isset($_REQUEST['key']) ? wc_clean(wp_unslash($_REQUEST['key'])) : '';

        return $orderKey !== '' && hash_equals($order->get_order_key(), $orderKey);
    }

    public static function isPayable(WC_Order $order): bool
    {
        return !$order->is_paid() && $order->needs_payment() && in_array($order->get_status(), ['pending', 'failed'], true);
    }
}
