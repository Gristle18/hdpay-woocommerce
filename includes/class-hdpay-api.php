<?php
/**
 * HDPay API Handler
 *
 * @package HDPay_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * HDPay API Class
 */
class HDPay_API {

    /**
     * Webhook URL
     *
     * @var string
     */
    private $webhook_url;

    /**
     * API Key
     *
     * @var string
     */
    private $api_key;

    /**
     * Project ID
     *
     * @var string
     */
    private $project_id;

    /**
     * Constructor
     *
     * @param string $webhook_url Webhook URL.
     * @param string $api_key     API Key.
     * @param string $project_id  Project ID.
     */
    public function __construct($webhook_url, $api_key, $project_id) {
        $this->webhook_url = $webhook_url;
        $this->api_key     = $api_key;
        $this->project_id  = $project_id;
    }

    /**
     * Create checkout session
     *
     * @param array $order_data Order data.
     * @return array|WP_Error
     */
    public function create_checkout_session($order_data) {
        if (empty($this->webhook_url)) {
            return new WP_Error('no_webhook_url', __('HDPay webhook URL is not configured.', 'hdpay-woocommerce'));
        }

        $response = wp_remote_post($this->webhook_url, array(
            'method'  => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type'     => 'application/json',
                'X-HDPay-API-Key'  => $this->api_key,
                'X-HDPay-Project'  => $this->project_id,
            ),
            'body'    => json_encode(array(
                'action'     => 'create_checkout',
                'order_data' => $order_data,
            )),
        ));

        if (is_wp_error($response)) {
            $this->log('API Error: ' . $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $this->log('API Response [' . $status_code . ']: ' . $body);

        if ($status_code !== 200) {
            $error_message = isset($data['error']) ? $data['error'] : __('Unknown error occurred.', 'hdpay-woocommerce');
            return new WP_Error('api_error', $error_message);
        }

        return $data;
    }

    /**
     * Refund payment
     *
     * @param string $transaction_id Transaction ID.
     * @param int    $amount         Amount in cents.
     * @param string $reason         Refund reason.
     * @return array|WP_Error
     */
    public function refund_payment($transaction_id, $amount, $reason = '') {
        if (empty($this->webhook_url)) {
            return new WP_Error('no_webhook_url', __('HDPay webhook URL is not configured.', 'hdpay-woocommerce'));
        }

        $response = wp_remote_post($this->webhook_url, array(
            'method'  => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type'     => 'application/json',
                'X-HDPay-API-Key'  => $this->api_key,
                'X-HDPay-Project'  => $this->project_id,
            ),
            'body'    => json_encode(array(
                'action'         => 'refund',
                'transaction_id' => $transaction_id,
                'amount'         => $amount,
                'reason'         => $reason,
            )),
        ));

        if (is_wp_error($response)) {
            $this->log('Refund API Error: ' . $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $this->log('Refund API Response [' . $status_code . ']: ' . $body);

        if ($status_code !== 200) {
            $error_message = isset($data['error']) ? $data['error'] : __('Refund failed.', 'hdpay-woocommerce');
            return new WP_Error('refund_error', $error_message);
        }

        return $data;
    }

    /**
     * Log message
     *
     * @param string $message Message to log.
     */
    private function log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HDPay API] ' . $message);
        }

        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info($message, array('source' => 'hdpay-api'));
        }
    }
}
