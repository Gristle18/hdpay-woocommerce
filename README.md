# HDPay for WooCommerce

Accept payments through HDPay - Secure payment processing with fraud prevention powered by GoVerify.

## Features

- **Easy Setup**: Simple configuration through WooCommerce settings
- **Secure Payments**: Redirect customers to secure HDPay checkout
- **Fraud Prevention**: Built-in fraud prevention powered by GoVerify
- **Automatic Updates**: Order status automatically updated via webhooks
- **Refund Support**: Process refunds directly from WooCommerce
- **Test Mode**: Test your integration before going live
- **Customizable**: Configure payment method title and description

## Installation

1. Download the plugin from [Releases](https://github.com/Gristle18/hdpay-woocommerce/releases)
2. Upload to `/wp-content/plugins/hdpay-woocommerce`
3. Activate through WordPress admin
4. Go to **WooCommerce > Settings > Payments > HDPay**

## Configuration

| Setting | Description |
|---------|-------------|
| **Title** | Payment method name shown at checkout (default: "Pay with HDPay") |
| **Description** | Description shown during checkout |
| **Webhook URL** | Your n8n webhook endpoint (e.g., `https://api.goverify.cc/webhook/...`) |
| **API Key** | Your HDPay API key for authentication |
| **Project ID** | Your HDPay project identifier |
| **Test Mode** | Enable for testing |

## How It Works

```
Customer Checkout -> HDPay Plugin -> n8n Webhook -> Supabase
                                                        |
                                                        v
Customer <- HDPay Checkout Page <- Checkout URL returned
    |
    v
Pays with Stripe
    |
    v
Success -> Webhook to WooCommerce -> Order marked "Paid"
```

## Webhook Endpoint

Your WooCommerce site will receive payment confirmations at:
```
https://yoursite.com/wc-api/hdpay_webhook/
```

Configure your n8n workflow to POST to this URL with:
```json
{
  "event": "payment.completed",
  "order_id": 123,
  "transaction_id": "txn_xxx",
  "status": "paid"
}
```

## Requirements

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+

## License

GPL v2 or later
