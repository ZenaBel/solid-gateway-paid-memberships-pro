<?php
/*
Plugin Name: Solidgate Gateway for Paid Memberships Pro
Description: Plugin to add Solidgate payment gateway into Paid Memberships Pro
Version: 1.0.0
Author: CoDi
*/

defined('ABSPATH') || exit;

const PMPRO_GATEWAY_SOLID_VERSION = '1.0.0';

if ( file_exists(__DIR__ . '/vendor/autoload.php') ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/hooks.php';
require_once __DIR__ . '/includes/model.php';
require_once __DIR__ . '/includes/pmpro-gateway-solid.php';

add_action('init', function() {
    PMProGateway_Solid::init();
});

function solid_gateway_enqueue_admin_assets() {
    wp_enqueue_style('solid-gateway-admin-css', plugin_dir_url(__FILE__) . 'assets/css/style.css');
    wp_register_script('solid-gateway-admin-js', plugin_dir_url(__FILE__) . 'assets/js/script.js', array('jquery'), '1.2.2', true);

    wp_localize_script('solid-gateway-admin-js', 'pmpro_solid', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pmpro_solid_nonce')
    ]);

    wp_enqueue_script('solid-gateway-admin-js');
}
add_action('admin_enqueue_scripts', 'solid_gateway_enqueue_admin_assets');

register_activation_hook(__FILE__, function () {
    if ( ! class_exists( 'PMProGateway_Solid_Product_Model' ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'includes/model.php';
    }

    PMProGateway_Solid_Product_Model::create_table();
});
