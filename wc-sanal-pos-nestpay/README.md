# WooCommerce Sanal POS Nestpay

Custom WooCommerce gateway for Payten/Nestpay 3D Pay Hosting, intended for headless WordPress and WooCommerce stores.

## Install

```sh
cd wc-sanal-pos-nestpay
composer install --no-dev
```

Copy or deploy this folder to `wp-content/plugins/wc-sanal-pos-nestpay`, then activate **WooCommerce Sanal POS Nestpay** in WordPress.

## Configure

Open WooCommerce payment settings and configure **Sanal POS Nestpay**:

- Merchant/client ID
- API username
- API password
- 3D store key
- Test/live environment
- Payten endpoint URLs
- Headless result URL

## Headless Start Endpoint

```http
POST /wp-json/sanal-pos/v1/payments/{order_id}/start
```

The requester must own the order, be a WooCommerce manager, or include the WooCommerce order key as `?key=...`.

The response returns an auto-submit HTML form:

```json
{
  "type": "html_form",
  "html": "<form ...></form>"
}
```

Render the HTML into the browser to continue to Payten/Nestpay.

## Callback

Configure Payten/Nestpay success and failure URLs through the plugin-generated form. Both return to:

```text
/wc-api/wc_gateway_sanal_pos_nestpay
```

The plugin verifies the gateway response through `mews/pos`, persists sanitized metadata, and redirects the customer to the configured headless result URL.

Classic WooCommerce checkout is also supported through:

```text
/wc-api/wc_gateway_sanal_pos_nestpay_pay
```
