<?php

if (isset($_GET['piprapay-webhook'])) {
    add_action('init', 'piprapay_handle_webhook');
}

function piprapay_handle_webhook() {
    $settings = get_option('woocommerce_piprapay_settings');
    $apiKey = $settings['api_key'] ?? '';
    $isDigital = $settings['is_digital_product'] === 'yes';

    $headerApi = $_SERVER['HTTP_MH_PIPRAPAY_API_KEY'] ?? null;

    if ($headerApi !== $apiKey) {
        status_header(401);
        echo 'Unauthorized';
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['metadata']['order_id'])) {
        status_header(400);
        echo 'Invalid data';
        exit;
    }

    $order_id = $data['metadata']['order_id'];
    $order = wc_get_order($order_id);

    if ($data['status'] === 'COMPLETED') {
        if ($isDigital) {
            $order->payment_complete($data['transaction_id']);
            $order->update_status('completed', 'Digital product — marked completed.');
        } else {
            $order->payment_complete($data['transaction_id']);
            $order->update_status('processing', 'Physical product — marked processing.');
        }
        $order->add_order_note('PipraPay payment successful.');
    } else {
        $order->update_status('failed', 'PipraPay payment failed.');
    }

    echo 'OK';
    exit;
}
