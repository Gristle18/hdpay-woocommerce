<?php
/**
 * Plugin Name: HDPay for WooCommerce
 * Plugin URI: https://github.com/Gristle18/hdpay-woocommerce
 * Description: Accept payments through HDPay - Secure payment processing with fraud prevention powered by GoVerify.
 * Version: 1.0.0
 * Author: HDPay
 * Author URI: https://goverify.cc
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hdpay-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('HDPAY_VERSION', '1.0.0');
define('HDPAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HDPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HDPAY_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Check if WooCommerce is active
 */
function hdpay_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'hdpay_woocommerce_missing_notice');
        return false;
    }
    return true;
}

/**
 * Admin notice for missing WooCommerce
 */
function hdpay_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php _e('HDPay for WooCommerce requires WooCommerce to be installed and active.', 'hdpay-woocommerce'); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function hdpay_init() {
    if (!hdpay_check_woocommerce()) {
        return;
    }

    // Include required files
    require_once HDPAY_PLUGIN_DIR . 'includes/class-hdpay-gateway.php';
    require_once HDPAY_PLUGIN_DIR . 'includes/class-hdpay-webhook.php';
    require_once HDPAY_PLUGIN_DIR . 'includes/class-hdpay-api.php';

    // Initialize webhook handler
    new HDPay_Webhook();
}
add_action('plugins_loaded', 'hdpay_init');

/**
 * Add HDPay gateway to WooCommerce
 */
function hdpay_add_gateway($gateways) {
    $gateways[] = 'HDPay_Gateway';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'hdpay_add_gateway');

/**
 * Add settings link to plugins page
 */
function hdpay_settings_link($links) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=hdpay">' . __('Settings', 'hdpay-woocommerce') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . HDPAY_PLUGIN_BASENAME, 'hdpay_settings_link');

/**
 * Declare HPOS compatibility
 */
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
