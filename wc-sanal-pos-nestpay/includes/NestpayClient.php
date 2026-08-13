<?php

declare(strict_types=1);

namespace SanalPos\Nestpay;

use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\PosInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use WC_Order;

final class NestpayClient
{
    /**
     * @param array<string, string|bool> $settings
     */
    public function __construct(private readonly array $settings)
    {
    }

    public function buildPaymentPayload(WC_Order $order): PaymentPayload
    {
        $pos = $this->createPos();
        $gatewayOrder = $this->buildGatewayOrder($order);

        $formData = $pos->get3DFormData(
            $gatewayOrder,
            $this->paymentModel(),
            $this->transactionType(),
            null,
            true
        );

        [$gatewayUrl, $fields] = $this->normalizeFormData($formData);

        return new PaymentPayload($gatewayUrl, $fields);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, response: array<string, mixed>, message: string}
     */
    public function completePayment(WC_Order $order, array $payload): array
    {
        $payload = $this->normalizeCallbackPayload($payload);

        if (($payload['HASH'] ?? '') === '') {
            throw new \Mews\Pos\Exception\HashMismatchException('Missing Nestpay hash.');
        }

        $pos = $this->createPos();
        $gatewayOrder = $this->buildGatewayOrder($order);

        try {
            $response = $pos->payment(
                $this->paymentModel(),
                $gatewayOrder,
                $this->transactionType(),
                null,
                $payload
            );
            $response = is_array($response) ? $response : (array) $response;
        } catch (\Mews\Pos\Exception\HashMismatchException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            if ($this->isApprovedPayload($payload)) {
                throw $exception;
            }

            $response = $this->fallbackDeclinedResponse($payload, $exception->getMessage());
        }

        $isApproved = $pos->isSuccess()
            || $this->isApprovedPayload($payload);

        return [
            'success' => $isApproved,
            'response' => $response,
            'message' => Sanitizer::gatewayMessage($payload['ErrMsg'] ?? $payload['mdErrorMsg'] ?? $response['error_message'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeCallbackPayload(array $payload): array
    {
        if (($payload['HASH'] ?? '') === '' && ($payload['hash'] ?? '') !== '') {
            $payload['HASH'] = $payload['hash'];
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isApprovedPayload(array $payload): bool
    {
        return strcasecmp((string) ($payload['Response'] ?? ''), 'Approved') === 0
            && (string) ($payload['ProcReturnCode'] ?? '') === '00';
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function fallbackDeclinedResponse(array $payload, string $error): array
    {
        return [
            'status' => 'declined',
            'order_id' => $payload['oid'] ?? $payload['OrderId'] ?? '',
            'proc_return_code' => $payload['ProcReturnCode'] ?? '',
            'error_code' => $payload['ErrorCode'] ?? '',
            'error_message' => $payload['ErrMsg'] ?? $payload['mdErrorMsg'] ?? $error,
            'all' => Sanitizer::payload($payload),
        ];
    }

    private function createPos(): PosInterface
    {
        if (method_exists(AccountFactory::class, 'createAssecoPosAccount')) {
            $account = AccountFactory::createAssecoPosAccount(
                $this->setting('bank_key', 'payten_v3_hash'),
                $this->setting('merchant_id'),
                $this->setting('api_username'),
                $this->setting('api_password'),
                $this->setting('store_key')
            );
        } else {
            $account = AccountFactory::createEstPosAccount(
                $this->setting('bank_key', 'payten_v3_hash'),
                $this->setting('merchant_id'),
                $this->setting('api_username'),
                $this->setting('api_password'),
                $this->paymentModel(),
                $this->setting('store_key'),
                $this->language()
            );
        }

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(Before3DFormHashCalculatedEvent::class, static function (Before3DFormHashCalculatedEvent $event): void {
            $inputs = $event->getFormInputs();
            $transactionType = $inputs['TranType'] ?? 'Auth';

            $inputs['islemtipi'] = $transactionType;
            unset($inputs['trantype']);

            if (!isset($inputs['callbackurl']) && isset($inputs['okUrl'])) {
                $inputs['callbackurl'] = $inputs['okUrl'];
            }

            if (isset($inputs['amount'])) {
                $inputs['amount'] = number_format((float) $inputs['amount'], 2, '.', '');
            }

            if (isset($inputs['rnd']) && strlen((string) $inputs['rnd']) !== 20) {
                $inputs['rnd'] = substr(str_pad((string) $inputs['rnd'], 20, '0'), 0, 20);
            }

            $event->setFormInputs($inputs);
        });

        if (method_exists(PosFactory::class, 'create')) {
            $pos = PosFactory::create($account, $this->bankConfig(), $eventDispatcher);
        } else {
            $pos = PosFactory::createPosGateway($account, $this->gatewayConfig(), $eventDispatcher);
        }

        if (is_callable([$pos, 'setTestMode'])) {
            $pos->setTestMode($this->setting('environment', 'test') === 'test');
        }

        return $pos;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function gatewayConfig(): array
    {
        return [
            'banks' => [
                $this->setting('bank_key', 'payten_v3_hash') => $this->bankConfig(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bankConfig(): array
    {
        $gatewayClass = class_exists('Mews\\Pos\\Gateway\\AssecoPos')
            ? 'Mews\\Pos\\Gateway\\AssecoPos'
            : 'Mews\\Pos\\Gateways\\EstV3Pos';

        return [
            'name' => $this->setting('bank_name', 'Payten Nestpay'),
            'class' => $gatewayClass,
            'gateway_class' => $gatewayClass,
            'lang' => $this->language(),
            'credentials' => [
                'payment_model' => $this->paymentModel(),
                'merchant_id' => $this->setting('merchant_id'),
                'user_name' => $this->setting('api_username'),
                'user_password' => $this->setting('api_password'),
                'enc_key' => $this->setting('store_key'),
            ],
            'gateway_endpoints' => [
                'payment_api' => $this->setting('payment_api_url'),
                'gateway_3d' => $this->setting('gateway_3d_url'),
                'gateway_3d_host' => $this->setting('gateway_3d_host_url') ?: $this->setting('gateway_3d_url'),
            ],
            'gateway_configs' => [
                'test_mode' => $this->setting('environment', 'test') === 'test',
                'disable_3d_hash_check' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGatewayOrder(WC_Order $order): array
    {
        $callbackUrl = add_query_arg(
            'wc-api',
            Gateway::CALLBACK_API,
            home_url('/')
        );

        return [
            'id' => OrderMeta::gatewayOrderId($order),
            'amount' => AmountFormatter::format((float) $order->get_total()),
            'currency' => $this->setting('currency', $this->currencyTry()),
            'installment' => $this->installmentsEnabled() ? (int) $order->get_meta(OrderMeta::INSTALLMENT_COUNT, true) : 0,
            'ip' => $order->get_customer_ip_address() ?: '127.0.0.1',
            'success_url' => add_query_arg('result', 'success', $callbackUrl),
            'fail_url' => add_query_arg('result', 'failure', $callbackUrl),
            'lang' => $this->language(),
        ];
    }

    /**
     * @param mixed $formData
     * @return array{0: string, 1: array<string, scalar|null>}
     */
    private function normalizeFormData(mixed $formData): array
    {
        if (!is_array($formData)) {
            throw new \RuntimeException('Gateway did not return usable 3D form data.');
        }

        $gatewayUrl = $formData['gateway'] ?? $formData['url'] ?? $formData['action'] ?? null;
        $fields = $formData['inputs'] ?? $formData['fields'] ?? $formData['data'] ?? null;

        if (!is_string($gatewayUrl) || !is_array($fields)) {
            throw new \RuntimeException('Gateway returned incomplete 3D form data.');
        }

        return [$gatewayUrl, $fields];
    }

    private function installmentsEnabled(): bool
    {
        return $this->setting('installments_enabled', 'no') === 'yes';
    }

    private function setting(string $key, string $default = ''): string
    {
        $value = $this->settings[$key] ?? $default;

        return is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value;
    }

    private function paymentModel(): string
    {
        return defined(PosInterface::class . '::MODEL_3D_PAY_HOSTING')
            ? constant(PosInterface::class . '::MODEL_3D_PAY_HOSTING')
            : '3d_pay_hosting';
    }

    private function transactionType(): string
    {
        return defined(PosInterface::class . '::TX_TYPE_PAY_AUTH')
            ? constant(PosInterface::class . '::TX_TYPE_PAY_AUTH')
            : 'pay';
    }

    private function language(): string
    {
        $language = $this->setting('language', 'tr');

        if ($language === 'tr' && defined(PosInterface::class . '::LANG_TR')) {
            return constant(PosInterface::class . '::LANG_TR');
        }

        if ($language === 'en' && defined(PosInterface::class . '::LANG_EN')) {
            return constant(PosInterface::class . '::LANG_EN');
        }

        return $language;
    }

    private function currencyTry(): string
    {
        return defined(PosInterface::class . '::CURRENCY_TRY')
            ? constant(PosInterface::class . '::CURRENCY_TRY')
            : 'TRY';
    }
}
