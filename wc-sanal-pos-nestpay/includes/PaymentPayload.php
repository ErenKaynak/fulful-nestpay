<?php

declare(strict_types=1);

namespace SanalPos\Nestpay;

final class PaymentPayload
{
    /**
     * @param array<string, scalar|null> $fields
     */
    public function __construct(
        public readonly string $gatewayUrl,
        public readonly array $fields
    ) {
    }

    /**
     * @return array{type: string, url?: string, html?: string}
     */
    public function toRestResponse(): array
    {
        return [
            'type' => 'html_form',
            'html' => $this->toAutoSubmitForm(),
        ];
    }

    public function toAutoSubmitForm(): string
    {
        $html = '<form id="sanal-pos-nestpay-form" method="post" action="' . esc_url($this->gatewayUrl) . '">';

        foreach ($this->fields as $name => $value) {
            if ($value === null) {
                continue;
            }

            $html .= sprintf(
                '<input type="hidden" name="%s" value="%s" />',
                esc_attr((string) $name),
                esc_attr((string) $value)
            );
        }

        $html .= '<noscript><button type="submit">' . esc_html__('Continue to payment', 'wc-sanal-pos-nestpay') . '</button></noscript>';
        $html .= '</form><script>document.getElementById("sanal-pos-nestpay-form").submit();</script>';

        return $html;
    }
}
