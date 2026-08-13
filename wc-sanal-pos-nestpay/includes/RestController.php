<?php

declare(strict_types=1);

namespace SanalPos\Nestpay;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WC_Order;

final class RestController
{
    public function registerRoutes(): void
    {
        register_rest_route('sanal-pos/v1', '/payments/(?P<order_id>\d+)/start', [
            'methods' => 'POST',
            'callback' => [$this, 'startPayment'],
            'permission_callback' => [$this, 'canStartPayment'],
            'args' => [
                'order_id' => [
                    'required' => true,
                    'validate_callback' => static fn ($value): bool => is_numeric($value) && (int) $value > 0,
                ],
            ],
        ]);
    }

    public function canStartPayment(WP_REST_Request $request): bool|WP_Error
    {
        $order = wc_get_order((int) $request['order_id']);
        if (!$order instanceof WC_Order) {
            return new WP_Error('sanal_pos_invalid_order', __('Order not found.', 'wc-sanal-pos-nestpay'), ['status' => 404]);
        }

        if (!OrderAuthorization::canCurrentUserPay($order)) {
            return new WP_Error('sanal_pos_forbidden', __('You cannot pay for this order.', 'wc-sanal-pos-nestpay'), ['status' => 403]);
        }

        if (!OrderAuthorization::isPayable($order)) {
            return new WP_Error('sanal_pos_not_payable', __('Order is not payable.', 'wc-sanal-pos-nestpay'), ['status' => 409]);
        }

        return true;
    }

    public function startPayment(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $order = wc_get_order((int) $request['order_id']);
        if (!$order instanceof WC_Order) {
            return new WP_Error('sanal_pos_invalid_order', __('Order not found.', 'wc-sanal-pos-nestpay'), ['status' => 404]);
        }

        $gateway = $this->gateway();
        if (!$gateway instanceof Gateway || $gateway->enabled !== 'yes') {
            return new WP_Error('sanal_pos_gateway_disabled', __('Payment gateway is disabled.', 'wc-sanal-pos-nestpay'), ['status' => 503]);
        }

        try {
            $payload = $gateway->client()->buildPaymentPayload($order);
            OrderMeta::markAttemptStarted($order);

            return new WP_REST_Response($payload->toRestResponse(), 200);
        } catch (\Throwable $exception) {
            $gateway->logger()->error('Nestpay start payment failed.', [
                'order_id' => $order->get_id(),
                'error' => $exception->getMessage(),
            ]);

            return new WP_Error('sanal_pos_start_failed', __('Payment could not be started.', 'wc-sanal-pos-nestpay'), ['status' => 500]);
        }
    }

    private function gateway(): ?Gateway
    {
        $gateways = WC()->payment_gateways()->payment_gateways();
        $gateway = $gateways[Gateway::ID] ?? null;

        return $gateway instanceof Gateway ? $gateway : null;
    }
}
