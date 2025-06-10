<?php
/*
Plugin Name: PipraPay
Plugin URI: https://github.com/mouzhhs/woocommerce-piprapay
Description: Accept payments via PipraPay in WooCommerce.
Version: 1.0
Author: PipraPay
Author URI: https://github.com/mouzhhs
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: piprapay
Domain Path: /languages
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.2
WC requires at least: 4.0
WC tested up to: 8.0
*/


if (!defined('ABSPATH')) exit;

add_action('plugins_loaded', 'piprapay_init_gateway_class');
function piprapay_init_gateway_class() {
    if (!class_exists('WC_Payment_Gateway')) return;

    include_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-piprapay.php';
    include_once plugin_dir_path(__FILE__) . 'webhook-handler.php';

    add_filter('woocommerce_payment_gateways', function($gateways) {
        $gateways[] = 'WC_Gateway_PipraPay';
        return $gateways;
    });
}
