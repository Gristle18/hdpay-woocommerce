=== HDPay for WooCommerce ===
Contributors: hdpay
Tags: woocommerce, payment, gateway, hdpay, goverify
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept payments through HDPay - Secure payment processing with fraud prevention powered by GoVerify.

== Description ==

HDPay for WooCommerce allows you to accept payments through the HDPay payment gateway. Your customers will be redirected to a secure payment page to complete their purchase.

**Features:**

* Easy setup and configuration
* Secure payment processing
* Fraud prevention powered by GoVerify
* Automatic order status updates via webhooks
* Support for refunds
* Test mode for development
* Customizable payment method title and description

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/hdpay-woocommerce` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to WooCommerce > Settings > Payments > HDPay to configure the plugin.
4. Enter your Webhook URL, API Key, and Project ID.
5. Enable the payment method and save changes.

== Configuration ==

1. **Webhook URL**: Your n8n/HDPay webhook endpoint (e.g., https://api.goverify.cc/webhook/...)
2. **API Key**: Your HDPay API key for authentication
3. **Project ID**: Your HDPay project identifier
4. **Title**: The payment method name shown to customers (default: "Pay with HDPay")
5. **Description**: Description shown during checkout
6. **Test Mode**: Enable for testing with test credentials

== Webhook Setup ==

Configure your n8n workflow to send payment confirmations to:
`https://yoursite.com/wc-api/hdpay_webhook/`

The webhook should send JSON data with:
* `event`: Event type (payment.completed, payment.failed, payment.refunded)
* `order_id`: WooCommerce order ID
* `transaction_id`: HDPay transaction ID
* `status`: Payment status

== Frequently Asked Questions ==

= What is HDPay? =

HDPay is a secure payment processing solution with built-in fraud prevention powered by GoVerify.

= Is this plugin free? =

Yes, the plugin is free. However, you need an HDPay account to process payments.

= Does this support refunds? =

Yes, you can process refunds directly from WooCommerce.

== Changelog ==

= 1.0.0 =
* Initial release
* Payment gateway integration
* Webhook handler for payment confirmations
* Refund support
* Test mode
* Customizable payment method title

== Upgrade Notice ==

= 1.0.0 =
Initial release of HDPay for WooCommerce.
