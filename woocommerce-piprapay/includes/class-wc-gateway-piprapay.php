<?php

if (!defined('ABSPATH')) exit;

class WC_Gateway_PipraPay extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'piprapay';
        $this->method_title = 'PipraPay';
        $this->method_description = 'Accept payments through PipraPay.';
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
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable PipraPay Gateway',
                'default' => 'yes'
            ],
            'title' => [
                'title' => 'Title',
                'type' => 'text',
                'default' => 'PipraPay'
            ],
            'description' => [
                'title' => 'Description',
                'type' => 'textarea',
                'default' => 'Pay securely via PipraPay.'
            ],
            'logo_url' => [
                'title' => 'Checkout Logo URL',
                'type' => 'text',
                'description' => 'Optional: URL to a logo/image for checkout display.'
            ],
            'api_key' => [
                'title' => 'API Key',
                'type' => 'text'
            ],
            'base_url' => [
                'title' => 'Base URL',
                'type' => 'text',
                'default' => 'https://sandbox.piprapay.com',
                'description' => 'Example: https://sandbox.piprapay.com'
            ],
            'is_digital_product' => [
                'title' => 'Product Type',
                'type' => 'checkbox',
                'label' => 'Mark orders as "Completed" (Digital Products)',
                'description' => 'If unchecked, orders will be marked as Processing (Physical Products)',
                'default' => 'no'
            ],
        ];
    }

    public function payment_fields() {
        if (!empty($this->description)) {
            echo '<p>' . esc_html($this->description) . '</p>';
        }

        if (!empty($this->logo_url)) {
            echo '<img src="' . esc_url($this->logo_url) . '" alt="PipraPay" style="max-width:150px;margin-top:10px;">';
        }
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        $return_url = $this->get_return_url($order);
        $cancel_url = wc_get_cart_url();
        $webhook_url = home_url('/?piprapay-webhook=1');

        $payload = [
            'metadata' => json_encode(['order_id' => $order_id]),
            'full_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'email_mobile' => $order->get_billing_email(),
            'amount' => $order->get_total(),
            'redirect_url' => $return_url,
            'cancel_url' => $cancel_url,
            'webhook_url' => $webhook_url,
            'return_type' => 'POST'
        ];

        $response = wp_remote_post($this->base_url . '/api/create-charge', [
            'headers' => [
                'Content-Type' => 'application/json',
                'mh-piprapay-api-key' => $this->api_key
            ],
            'body' => json_encode($payload),
            'timeout' => 45
        ]);

        if (is_wp_error($response)) {
            wc_add_notice('PipraPay Error: ' . $response->get_error_message(), 'error');
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['url'])) {
            return [
                'result' => 'success',
                'redirect' => $body['url']
            ];
        } else {
            wc_add_notice('PipraPay: Invalid response.', 'error');
            return;
        }
    }
}
