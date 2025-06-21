<?php
if (!defined('ABSPATH')) exit;

class WC_Gateway_PipraPay extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'piprapay';
        $this->method_title = __('PipraPay', 'piprapay');
        $this->method_description = __('Accept payments through PipraPay.', 'piprapay');
        $this->has_fields = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->api_key = $this->get_option('api_key');
        $this->base_url = untrailingslashit($this->get_option('base_url'));
        $this->is_digital = $this->get_option('is_digital_product') === 'yes';
        $this->logo_url = $this->get_option('logo_url');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => __('Enable/Disable', 'piprapay'),
                'type'    => 'checkbox',
                'label'   => __('Enable PipraPay Gateway', 'piprapay'),
                'default' => 'yes'
            ],
            'title' => [
                'title'   => __('Title', 'piprapay'),
                'type'    => 'text',
                'default' => __('PipraPay', 'piprapay')
            ],
            'description' => [
                'title'   => __('Description', 'piprapay'),
                'type'    => 'textarea',
                'default' => __('Pay securely via PipraPay.', 'piprapay')
            ],
            'logo_url' => [
                'title'       => __('Checkout Logo URL', 'piprapay'),
                'type'        => 'text',
                'description' => __('Optional: URL to a logo/image for checkout display.', 'piprapay')
            ],
            'api_key' => [
                'title' => __('API Key', 'piprapay'),
                'type'  => 'text'
            ],
            'base_url' => [
                'title'       => __('Base URL', 'piprapay'),
                'type'        => 'text',
                'default'     => 'https://sandbox.piprapay.com',
                'description' => __('Example: https://sandbox.piprapay.com', 'piprapay')
            ],
            'is_digital_product' => [
                'title'       => __('Product Type', 'piprapay'),
                'type'        => 'checkbox',
                'label'       => __('Mark orders as "Completed" (Digital Products)', 'piprapay'),
                'description' => __('If unchecked, orders will be marked as Processing (Physical Products)', 'piprapay'),
                'default'     => 'no'
            ],
        ];
    }

    public function payment_fields() {
        if (!empty($this->description)) {
            echo '<p>' . esc_html($this->description) . '</p>';
        }

        if (!empty($this->logo_url)) {
            echo '<div style="margin-top:10px;"><img src="' . esc_url($this->logo_url) . '" alt="' . esc_attr__('PipraPay Logo', 'piprapay') . '" style="max-width:150px;"></div>';
        }
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        $return_url = $this->get_return_url($order);
        $cancel_url = wc_get_cart_url();
        $webhook_url = home_url('/?piprapay-webhook=1');

        $payload = [
            'metadata'     => json_encode(['order_id' => $order_id]),
            'full_name'    => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'email_mobile' => $order->get_billing_email(),
            'amount'       => $order->get_total(),
            'redirect_url' => $return_url,
            'cancel_url'   => $cancel_url,
            'webhook_url'  => $webhook_url,
            'return_type'  => 'POST'
        ];

        $response = wp_remote_post($this->base_url . '/api/create-charge', [
            'headers' => [
                'Content-Type'           => 'application/json',
                'mh-piprapay-api-key'    => $this->api_key
            ],
            'body'    => json_encode($payload),
            'timeout' => 45
        ]);

        if (is_wp_error($response)) {
            wc_add_notice(__('PipraPay Error: ', 'piprapay') . $response->get_error_message(), 'error');
            return;
        }

        $body_raw = wp_remote_retrieve_body($response);
        $body = json_decode($body_raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($body['url'])) {
            wc_add_notice(__('PipraPay: Invalid or malformed response.', 'piprapay'), 'error');
            return;
        }

        return [
            'result'   => 'success',
            'redirect' => esc_url_raw($body['url'])
        ];
    }
}
