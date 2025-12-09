<?php
/**
 * HDPay Payment Gateway
 *
 * @package HDPay_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * HDPay Gateway Class
 */
class HDPay_Gateway extends WC_Payment_Gateway {

    /**
     * API instance
     *
     * @var HDPay_API
     */
    private $api;

    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'hdpay';
        $this->icon               = HDPAY_PLUGIN_URL . 'assets/hdpay-logo.png';
        $this->has_fields         = false;
        $this->method_title       = __('HDPay', 'hdpay-woocommerce');
        $this->method_description = __('Accept payments through HDPay - Secure payment processing with fraud prevention.', 'hdpay-woocommerce');
        $this->supports           = array('products', 'refunds');

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Get settings
        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');
        $this->enabled      = $this->get_option('enabled');
        $this->testmode     = 'yes' === $this->get_option('testmode');
        $this->webhook_url  = $this->get_option('webhook_url');
        $this->api_key      = $this->get_option('api_key');
        $this->project_id   = $this->get_option('project_id');

        // Initialize API
        $this->api = new HDPay_API($this->webhook_url, $this->api_key, $this->project_id);

        // Hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_hdpay_return', array($this, 'handle_return'));
    }

    /**
     * Initialize form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'hdpay-woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Enable HDPay', 'hdpay-woocommerce'),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __('Title', 'hdpay-woocommerce'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'hdpay-woocommerce'),
                'default'     => __('Pay with HDPay', 'hdpay-woocommerce'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'hdpay-woocommerce'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'hdpay-woocommerce'),
                'default'     => __('Secure payment processing. You will be redirected to complete your payment.', 'hdpay-woocommerce'),
                'desc_tip'    => true,
            ),
            'testmode' => array(
                'title'       => __('Test Mode', 'hdpay-woocommerce'),
                'type'        => 'checkbox',
                'label'       => __('Enable test mode', 'hdpay-woocommerce'),
                'description' => __('Place the payment gateway in test mode using test API credentials.', 'hdpay-woocommerce'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'webhook_url' => array(
                'title'       => __('Webhook URL', 'hdpay-woocommerce'),
                'type'        => 'text',
                'description' => __('Enter your HDPay/n8n webhook URL.', 'hdpay-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => 'https://api.goverify.cc/webhook/...',
            ),
            'api_key' => array(
                'title'       => __('API Key', 'hdpay-woocommerce'),
                'type'        => 'password',
                'description' => __('Enter your HDPay API key for authentication.', 'hdpay-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'project_id' => array(
                'title'       => __('Project ID', 'hdpay-woocommerce'),
                'type'        => 'text',
                'description' => __('Enter your HDPay Project ID.', 'hdpay-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'webhook_info' => array(
                'title'       => __('Webhook Endpoint', 'hdpay-woocommerce'),
                'type'        => 'title',
                'description' => sprintf(
                    __('Your webhook endpoint for payment confirmations: %s', 'hdpay-woocommerce'),
                    '<code>' . home_url('/wc-api/hdpay_webhook/') . '</code>'
                ),
            ),
        );
    }

    /**
     * Process payment
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_add_notice(__('Order not found.', 'hdpay-woocommerce'), 'error');
            return array('result' => 'failure');
        }

        // Prepare order data for HDPay
        $order_data = array(
            'order_id'       => $order->get_id(),
            'order_key'      => $order->get_order_key(),
            'amount'         => intval(floatval($order->get_total()) * 100), // Convert to cents
            'currency'       => $order->get_currency(),
            'customer'       => array(
                'name'       => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'email'      => $order->get_billing_email(),
                'phone'      => $order->get_billing_phone(),
                'address_1'  => $order->get_billing_address_1(),
                'address_2'  => $order->get_billing_address_2(),
                'city'       => $order->get_billing_city(),
                'state'      => $order->get_billing_state(),
                'zip_code'   => $order->get_billing_postcode(),
                'country'    => $order->get_billing_country(),
            ),
            'items'          => $this->get_order_items($order),
            'description'    => $this->get_order_description($order),
            'success_url'    => $this->get_return_url($order),
            'cancel_url'     => $order->get_cancel_order_url_raw(),
            'webhook_url'    => home_url('/wc-api/hdpay_webhook/'),
            'testmode'       => $this->testmode,
            'project_id'     => $this->project_id,
            'metadata'       => array(
                'order_id'   => $order->get_id(),
                'order_key'  => $order->get_order_key(),
                'site_url'   => home_url(),
            ),
        );

        // Send to HDPay/n8n
        $response = $this->api->create_checkout_session($order_data);

        if (is_wp_error($response)) {
            wc_add_notice($response->get_error_message(), 'error');
            return array('result' => 'failure');
        }

        if (empty($response['checkout_url'])) {
            wc_add_notice(__('Unable to create payment session. Please try again.', 'hdpay-woocommerce'), 'error');
            return array('result' => 'failure');
        }

        // Store transaction ID if returned
        if (!empty($response['transaction_id'])) {
            $order->update_meta_data('_hdpay_transaction_id', $response['transaction_id']);
            $order->save();
        }

        // Mark order as pending payment
        $order->update_status('pending', __('Awaiting HDPay payment.', 'hdpay-woocommerce'));

        // Return redirect
        return array(
            'result'   => 'success',
            'redirect' => $response['checkout_url'],
        );
    }

    /**
     * Get order items
     *
     * @param WC_Order $order Order object.
     * @return array
     */
    private function get_order_items($order) {
        $items = array();

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = array(
                'name'     => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price'    => intval(floatval($item->get_total()) * 100),
                'sku'      => $product ? $product->get_sku() : '',
            );
        }

        return $items;
    }

    /**
     * Get order description
     *
     * @param WC_Order $order Order object.
     * @return string
     */
    private function get_order_description($order) {
        $items = $order->get_items();
        $item_names = array();

        foreach ($items as $item) {
            $item_names[] = $item->get_name() . ' x ' . $item->get_quantity();
        }

        return implode(', ', $item_names);
    }

    /**
     * Handle return from payment page
     */
    public function handle_return() {
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $order_key = isset($_GET['order_key']) ? sanitize_text_field($_GET['order_key']) : '';

        if (!$order_id || !$order_key) {
            wp_redirect(home_url());
            exit;
        }

        $order = wc_get_order($order_id);

        if (!$order || $order->get_order_key() !== $order_key) {
            wp_redirect(home_url());
            exit;
        }

        // Redirect to thank you page
        wp_redirect($this->get_return_url($order));
        exit;
    }

    /**
     * Process refund
     *
     * @param int    $order_id Order ID.
     * @param float  $amount   Refund amount.
     * @param string $reason   Refund reason.
     * @return bool|WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_Error('invalid_order', __('Invalid order.', 'hdpay-woocommerce'));
        }

        $transaction_id = $order->get_meta('_hdpay_transaction_id');

        if (!$transaction_id) {
            return new WP_Error('no_transaction', __('No HDPay transaction ID found.', 'hdpay-woocommerce'));
        }

        $response = $this->api->refund_payment($transaction_id, intval($amount * 100), $reason);

        if (is_wp_error($response)) {
            return $response;
        }

        $order->add_order_note(
            sprintf(__('Refunded %s via HDPay. Reason: %s', 'hdpay-woocommerce'), wc_price($amount), $reason)
        );

        return true;
    }
}
