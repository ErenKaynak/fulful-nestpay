<?php

declare(strict_types=1);

namespace SanalPos\Nestpay;

use WC_Order;

final class OrderMeta
{
    public const GATEWAY_ORDER_ID = '_sanal_pos_nestpay_oid';
    public const ATTEMPT_STATUS = '_sanal_pos_nestpay_attempt_status';
    public const RAW_CALLBACK = '_sanal_pos_nestpay_callback_payload';
    public const RESPONSE = '_sanal_pos_nestpay_response';
    public const INSTALLMENT_COUNT = '_sanal_pos_nestpay_installment_count';

    public static function gatewayOrderId(WC_Order $order): string
    {
        $existing = (string) $order->get_meta(self::GATEWAY_ORDER_ID, true);
        if ($existing !== '') {
            return $existing;
        }

        $gatewayOrderId = 'WC-' . $order->get_id();
        $order->update_meta_data(self::GATEWAY_ORDER_ID, $gatewayOrderId);
        $order->save_meta_data();

        return $gatewayOrderId;
    }

    public static function orderIdFromGatewayOrderId(string $gatewayOrderId): int
    {
        if (preg_match('/^WC-(\d+)$/', $gatewayOrderId, $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }

    public static function markAttemptStarted(WC_Order $order): void
    {
        self::gatewayOrderId($order);
        $order->update_meta_data(self::ATTEMPT_STATUS, 'started');
        $order->save_meta_data();
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $response
     */
    public static function persistGatewayResult(WC_Order $order, array $payload, array $response, bool $success): void
    {
        $order->update_meta_data(self::ATTEMPT_STATUS, $success ? 'approved' : 'failed');
        $order->update_meta_data(self::RAW_CALLBACK, wp_json_encode($payload));
        $order->update_meta_data(self::RESPONSE, wp_json_encode($response));

        foreach ([
            'TransId' => '_sanal_pos_nestpay_trans_id',
            'AuthCode' => '_sanal_pos_nestpay_auth_code',
            'HostRefNum' => '_sanal_pos_nestpay_host_ref_num',
            'ProcReturnCode' => '_sanal_pos_nestpay_proc_return_code',
        ] as $payloadKey => $metaKey) {
            if (isset($payload[$payloadKey])) {
                $order->update_meta_data($metaKey, Sanitizer::gatewayMessage($payload[$payloadKey]));
            }
        }

        $order->save_meta_data();
    }
}
