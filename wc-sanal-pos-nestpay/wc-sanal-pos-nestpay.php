<?php
/**
 * Plugin Name: WooCommerce Sanal POS Nestpay
 * Description: Adds a WooCommerce payment gateway for Payten/Nestpay 3D Pay Hosting.
 * Version: 0.1.0
 * Author: Sanal POS
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * Text Domain: wc-sanal-pos-nestpay
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('WC_SANAL_POS_NESTPAY_FILE', __FILE__);
define('WC_SANAL_POS_NESTPAY_PATH', plugin_dir_path(__FILE__));
define('WC_SANAL_POS_NESTPAY_URL', plugin_dir_url(__FILE__));
define('WC_SANAL_POS_NESTPAY_VERSION', '0.1.0');

$autoload = WC_SANAL_POS_NESTPAY_PATH . 'vendor/autoload.php';
if (is_readable($autoload)) {
    require_once $autoload;
}

add_action('plugins_loaded', static function (): void {
    if (!class_exists('WooCommerce') || !class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('WooCommerce Sanal POS Nestpay requires WooCommerce to be active.', 'wc-sanal-pos-nestpay');
            echo '</p></div>';
        });

        return;
    }

    if (!class_exists(\Mews\Pos\Factory\PosFactory::class)) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('WooCommerce Sanal POS Nestpay dependencies are missing. Run composer install inside the plugin directory.', 'wc-sanal-pos-nestpay');
            echo '</p></div>';
        });

        return;
    }

    \SanalPos\Nestpay\Plugin::init();
}, 11);
