<?php

declare(strict_types=1);

namespace SanalPos\Nestpay;

use Mews\Pos\Exception\HashMismatchException;
use WC_Order;
use WC_Payment_Gateway;

final class Gateway extends WC_Payment_Gateway
{
    public const ID = 'sanal_pos_nestpay';
    public const CALLBACK_API = 'wc_gateway_sanal_pos_nestpay';
    public const PAY_API = 'wc_gateway_sanal_pos_nestpay_pay';

    public function __construct()
    {
        $this->id = self::ID;
        $this->method_title = __('Sanal POS Nestpay', 'wc-sanal-pos-nestpay');
        $this->method_description = __('Accept Payten/Nestpay 3D Pay Hosting payments.', 'wc-sanal-pos-nestpay');
        $this->has_fields = false;
        $this->supports = ['products'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title', __('Credit/Debit Card', 'wc-sanal-pos-nestpay'));
        $this->description = $this->get_option('description', __('Pay securely by card.', 'wc-sanal-pos-nestpay'));
        $this->enabled = $this->get_option('enabled', 'no');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_' . self::CALLBACK_API, [$this, 'handleCallback']);
        add_action('woocommerce_api_' . self::PAY_API, [$this, 'renderPaymentForm']);
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'wc-sanal-pos-nestpay'),
                'type' => 'checkbox',
                'label' => __('Enable Sanal POS Nestpay', 'wc-sanal-pos-nestpay'),
                'default' => 'no',
            ],
            'title' => [
                'title' => __('Checkout title', 'wc-sanal-pos-nestpay'),
                'type' => 'text',
                'default' => __('Credit/Debit Card', 'wc-sanal-pos-nestpay'),
            ],
            'description' => [
                'title' => __('Checkout description', 'wc-sanal-pos-nestpay'),
                'type' => 'textarea',
                'default' => __('Pay securely through our bank payment page.', 'wc-sanal-pos-nestpay'),
            ],
            'environment' => [
                'title' => __('Environment', 'wc-sanal-pos-nestpay'),
                'type' => 'select',
                'default' => 'test',
                'options' => [
                    'test' => __('Test', 'wc-sanal-pos-nestpay'),
                    'live' => __('Live', 'wc-sanal-pos-nestpay'),
                ],
            ],
            'bank_key' => [
                'title' => __('Bank config key', 'wc-sanal-pos-nestpay'),
                'type' => 'text',
                'default' => 'payten_v3_hash',
            ],
            'bank_name' => [
                'title' => __('Bank name', 'wc-sanal-pos-nestpay'),
                'type' => 'text',
                'default' => 'Payten Nestpay',
            ],
            'merchant_id' => [
                'title' => __('Merchant/client ID', 'wc-sanal-pos-nestpay'),
                'type' => 'text',
                'default' => '',
            ],
            'api_username' => [
                'title' => __('API username', 'wc-sanal-pos-nestpay'),
                'type' => 'text',
                'default' => '',
            ],
            'api_password' => [
                'title' => __('API password', 'wc-sanal-pos-nestpay'),
                'type' => 'password',
                'default' => '',
            ],
            'store_key' => [
                'title' => __('3D store key', 'wc-sanal-pos-nestpay'),
                'type' => 'password',
                'default' => '',
            ],
            'payment_api_url' => [
                'title' => __('Payment API URL', 'wc-sanal-pos-nestpay'),
                'type' => 'text',
                'default' => 'https://entegrasyon.asseco-see.com.tr/fim/api',
            ],
            'gateway_3d_url' => [
                'title' => __('3D gateway URL', 'wc-sanal-pos-nestpay'),
                'type' => 'text',
                'default' => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
            ],
            'gateway_3d_host_url' => [
                'title' => __('3D Pay Hosting URL', 'wc-sanal-pos-nestpay'),
                'type' => 'text',
                'default' => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
            ],
            'currency' => [
                'title' => __('Currency', 'wc-sanal-pos-nestpay'),
                'type' => 'text',
                'default' => 'TRY',
            ],
            'language' => [
                'title' => __('Language', 'wc-sanal-pos-nestpay'),
                'type' => 'select',
                'default' => 'tr',
                'options' => [
                    'tr' => __('Turkish', 'wc-sanal-pos-nestpay'),
                    'en' => __('English', 'wc-sanal-pos-nestpay'),
                ],
            ],
            'installments_enabled' => [
                'title' => __('Installments', 'wc-sanal-pos-nestpay'),
                'type' => 'checkbox',
                'label' => __('Allow installment count from order metadata', 'wc-sanal-pos-nestpay'),
                'default' => 'no',
            ],
            'frontend_result_url' => [
                'title' => __('Headless result URL', 'wc-sanal-pos-nestpay'),
                'type' => 'text',
                'default' => '/checkout/result',
            ],
        ];
    }

    /**
     * @return array{result: string, redirect?: string}
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            wc_add_notice(__('Invalid order.', 'wc-sanal-pos-nestpay'), 'error');

            return ['result' => 'failure'];
        }

        return [
            'result' => 'success',
            'redirect' => add_query_arg([
                'wc-api' => self::PAY_API,
                'order_id' => $order->get_id(),
                'key' => $order->get_order_key(),
            ], home_url('/')),
        ];
    }

    public function renderPaymentForm(): void
    {
        $orderId = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $orderKey = isset($_GET['key']) ? wc_clean(wp_unslash($_GET['key'])) : '';
        $order = $orderId > 0 ? wc_get_order($orderId) : false;

        if (!$order instanceof WC_Order || !hash_equals($order->get_order_key(), $orderKey) || !OrderAuthorization::isPayable($order)) {
            wp_die(esc_html__('This order cannot be paid.', 'wc-sanal-pos-nestpay'), esc_html__('Payment error', 'wc-sanal-pos-nestpay'), ['response' => 403]);
        }

        try {
            $payload = $this->client()->buildPaymentPayload($order);
            OrderMeta::markAttemptStarted($order);

            echo '<!doctype html><html><head><meta charset="utf-8"><title>';
            echo esc_html__('Redirecting to payment', 'wc-sanal-pos-nestpay');
            echo '</title></head><body>';
            echo $payload->toAutoSubmitForm(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</body></html>';
        } catch (\Throwable $exception) {
            $this->logger()->error('Nestpay payment form render failed.', ['order_id' => $order->get_id(), 'error' => $exception->getMessage()]);
            wp_die(esc_html__('Payment could not be started.', 'wc-sanal-pos-nestpay'), esc_html__('Payment error', 'wc-sanal-pos-nestpay'), ['response' => 500]);
        }

        exit;
    }

    public function handleCallback(): void
    {
        $payload = $_POST ?: $_GET;
        $gatewayOrderId = Sanitizer::gatewayMessage($payload['oid'] ?? $payload['OrderId'] ?? '');
        $orderId = OrderMeta::orderIdFromGatewayOrderId($gatewayOrderId);
        $order = $orderId > 0 ? wc_get_order($orderId) : false;

        if (!$order instanceof WC_Order) {
            $this->logger()->error('Nestpay callback received for unknown order.', ['payload' => Sanitizer::payload($payload)]);
            wp_redirect($this->frontendResultUrl(0, 'failure'));
            exit;
        }

        if ($order->is_paid()) {
            wp_redirect($this->frontendResultUrl($order->get_id(), 'success'));
            exit;
        }

        try {
            $result = $this->client()->completePayment($order, Sanitizer::gatewayPayload($payload));
            OrderMeta::persistGatewayResult($order, Sanitizer::payload($payload), $result['response'], $result['success']);

            if ($result['success']) {
                $order->payment_complete($result['response']['transaction_id'] ?? $payload['TransId'] ?? '');
                $order->add_order_note(__('Nestpay payment approved.', 'wc-sanal-pos-nestpay'));
                wp_redirect($this->frontendResultUrl($order->get_id(), 'success'));
                exit;
            }

            $order->update_status('failed', sprintf(
                /* translators: %s is gateway error message. */
                __('Nestpay payment failed: %s', 'wc-sanal-pos-nestpay'),
                $result['message']
            ));
        } catch (HashMismatchException $exception) {
            $this->logger()->warning('Nestpay hash verification failed.', [
                'order_id' => $order->get_id(),
                'payload' => Sanitizer::payload($payload),
            ]);
            $order->add_order_note(__('Nestpay hash verification failed; order left unchanged.', 'wc-sanal-pos-nestpay'));
        } catch (\Throwable $exception) {
            $this->logger()->error('Nestpay callback failed.', ['order_id' => $order->get_id(), 'error' => $exception->getMessage()]);
            $order->update_status('failed', __('Nestpay callback processing failed.', 'wc-sanal-pos-nestpay'));
        }

        wp_redirect($this->frontendResultUrl($order->get_id(), 'failure'));
        exit;
    }

    public function client(): NestpayClient
    {
        return new NestpayClient($this->settings);
    }

    public function logger(): \WC_Logger
    {
        return wc_get_logger();
    }

    private function frontendResultUrl(int $orderId, string $status): string
    {
        $base = $this->get_option('frontend_result_url', '/checkout/result');
        $url = str_starts_with($base, 'http') ? $base : home_url($base);

        return add_query_arg([
            'order_id' => $orderId,
            'status' => $status,
        ], $url);
    }
}
