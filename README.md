# Fulful Nestpay

WooCommerce Nestpay / Ziraat Bankasi E-POS integration work for Fulful.

## Contents

- `wc-sanal-pos-nestpay/` - Custom WooCommerce payment gateway plugin.
- `Documentation/` - Nestpay / Payten integration PDFs used during implementation.
- `github-repos.md` - Initial repository/library references reviewed before implementation.

## Plugin

The plugin targets Payten/Nestpay `3d_pay_hosting`, so card entry happens on the bank-hosted payment page.

Install dependencies before deploying the plugin:

```sh
cd wc-sanal-pos-nestpay
composer install --no-dev
```

Then deploy `wc-sanal-pos-nestpay/` to:

```text
wp-content/plugins/wc-sanal-pos-nestpay
```

No production credentials are stored in this repository. Configure merchant ID, API user, API password, store key, endpoints, and result URL in WooCommerce payment settings.

