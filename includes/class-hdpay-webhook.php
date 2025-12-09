<?php
/**
 * HDPay Webhook Handler
 *
 * @package HDPay_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * HDPay Webhook Class
 */
class HDPay_Webhook {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('woocommerce_api_hdpay_webhook', array($this, 'handle_webhook'));
    }

    /**
     * Handle incoming webhook
     */
    public function handle_webhook() {
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);

        // Log webhook for debugging
        $this->log('Webhook received: ' . $payload);

        // Validate payload
        if (empty($data)) {
            $this->log('Invalid webhook payload');
            status_header(400);
            echo json_encode(array('error' => 'Invalid payload'));
            exit;
        }

        // Verify API key if provided
        $api_key = isset($_SERVER['HTTP_X_HDPAY_API_KEY']) ? sanitize_text_field($_SERVER['HTTP_X_HDPAY_API_KEY']) : '';
        $settings = get_option('woocommerce_hdpay_settings', array());
        $stored_api_key = isset($settings['api_key']) ? $settings['api_key'] : '';

        if ($stored_api_key && $api_key !== $stored_api_key) {
            $this->log('Invalid API key');
            status_header(401);
            echo json_encode(array('error' => 'Unauthorized'));
            exit;
        }

        // Process based on event type
        $event_type = isset($data['event']) ? sanitize_text_field($data['event']) : '';

        switch ($event_type) {
            case 'payment.completed':
            case 'payment.succeeded':
                $this->handle_payment_completed($data);
                break;

            case 'payment.failed':
                $this->handle_payment_failed($data);
                break;

            case 'payment.refunded':
                $this->handle_payment_refunded($data);
                break;

            default:
                // Try to process as a generic payment confirmation
                if (!empty($data['order_id']) && !empty($data['status'])) {
                    $this->handle_generic_update($data);
                } else {
                    $this->log('Unknown event type: ' . $event_type);
                }
        }

        status_header(200);
        echo json_encode(array('success' => true));
        exit;
    }

    /**
     * Handle payment completed event
     *
     * @param array $data Webhook data.
     */
    private function handle_payment_completed($data) {
        $order = $this->get_order_from_data($data);

        if (!$order) {
            $this->log('Order not found for payment completed event');
            return;
        }

        // Check if already processed
        if ($order->is_paid()) {
            $this->log('Order already paid: ' . $order->get_id());
            return;
        }

        // Update transaction ID if provided
        if (!empty($data['transaction_id'])) {
            $order->update_meta_data('_hdpay_transaction_id', sanitize_text_field($data['transaction_id']));
        }

        // Store payment details
        if (!empty($data['payment_id'])) {
            $order->update_meta_data('_hdpay_payment_id', sanitize_text_field($data['payment_id']));
        }

        // Mark order as paid
        $order->payment_complete($data['transaction_id'] ?? '');

        // Add order note
        $order->add_order_note(
            sprintf(
                __('HDPay payment completed. Transaction ID: %s', 'hdpay-woocommerce'),
                $data['transaction_id'] ?? 'N/A'
            )
        );

        $order->save();

        $this->log('Order marked as paid: ' . $order->get_id());
    }

    /**
     * Handle payment failed event
     *
     * @param array $data Webhook data.
     */
    private function handle_payment_failed($data) {
        $order = $this->get_order_from_data($data);

        if (!$order) {
            $this->log('Order not found for payment failed event');
            return;
        }

        $order->update_status('failed', __('HDPay payment failed.', 'hdpay-woocommerce'));

        // Add failure reason if provided
        if (!empty($data['failure_reason'])) {
            $order->add_order_note(
                sprintf(__('Payment failed: %s', 'hdpay-woocommerce'), sanitize_text_field($data['failure_reason']))
            );
        }

        $order->save();

        $this->log('Order marked as failed: ' . $order->get_id());
    }

    /**
     * Handle payment refunded event
     *
     * @param array $data Webhook data.
     */
    private function handle_payment_refunded($data) {
        $order = $this->get_order_from_data($data);

        if (!$order) {
            $this->log('Order not found for refund event');
            return;
        }

        $refund_amount = isset($data['refund_amount']) ? floatval($data['refund_amount']) / 100 : 0;

        if ($refund_amount > 0) {
            // Create refund
            $refund = wc_create_refund(array(
                'amount'   => $refund_amount,
                'reason'   => isset($data['refund_reason']) ? sanitize_text_field($data['refund_reason']) : __('Refunded via HDPay', 'hdpay-woocommerce'),
                'order_id' => $order->get_id(),
            ));

            if (!is_wp_error($refund)) {
                $this->log('Refund created for order: ' . $order->get_id());
            }
        }
    }

    /**
     * Handle generic status update
     *
     * @param array $data Webhook data.
     */
    private function handle_generic_update($data) {
        $order = $this->get_order_from_data($data);

        if (!$order) {
            $this->log('Order not found for generic update');
            return;
        }

        $status = sanitize_text_field($data['status']);

        switch ($status) {
            case 'paid':
            case 'completed':
            case 'succeeded':
                if (!$order->is_paid()) {
                    $order->payment_complete($data['transaction_id'] ?? '');
                    $this->log('Order marked as paid via generic update: ' . $order->get_id());
                }
                break;

            case 'failed':
                $order->update_status('failed', __('Payment failed.', 'hdpay-woocommerce'));
                $this->log('Order marked as failed via generic update: ' . $order->get_id());
                break;

            case 'refunded':
                $order->update_status('refunded', __('Payment refunded.', 'hdpay-woocommerce'));
                $this->log('Order marked as refunded via generic update: ' . $order->get_id());
                break;
        }

        // Update transaction ID if provided
        if (!empty($data['transaction_id'])) {
            $order->update_meta_data('_hdpay_transaction_id', sanitize_text_field($data['transaction_id']));
            $order->save();
        }
    }

    /**
     * Get order from webhook data
     *
     * @param array $data Webhook data.
     * @return WC_Order|false
     */
    private function get_order_from_data($data) {
        // Try order_id first
        if (!empty($data['order_id'])) {
            $order = wc_get_order(absint($data['order_id']));
            if ($order) {
                // Verify order key if provided
                if (!empty($data['order_key']) && $order->get_order_key() !== $data['order_key']) {
                    $this->log('Order key mismatch');
                    return false;
                }
                return $order;
            }
        }

        // Try metadata
        if (!empty($data['metadata']['order_id'])) {
            $order = wc_get_order(absint($data['metadata']['order_id']));
            if ($order) {
                return $order;
            }
        }

        // Try transaction ID lookup
        if (!empty($data['transaction_id'])) {
            $orders = wc_get_orders(array(
                'meta_key'   => '_hdpay_transaction_id',
                'meta_value' => sanitize_text_field($data['transaction_id']),
                'limit'      => 1,
            ));

            if (!empty($orders)) {
                return $orders[0];
            }
        }

        return false;
    }

    /**
     * Log message
     *
     * @param string $message Message to log.
     */
    private function log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HDPay] ' . $message);
        }

        // Also log to WooCommerce logger if available
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info($message, array('source' => 'hdpay'));
        }
    }
}
