<?php
/*
Plugin Name: PMPro Solid Gateway
Description: Custom gateway for Paid Memberships Pro
Version: 0.0.1
Author: CoDi
*/

defined('ABSPATH') || exit;

const PMPRO_GATEWAY_SOLID_VERSION = '0.0.1';

if ( file_exists(__DIR__ . '/vendor/autoload.php') ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/hooks.php';
require_once __DIR__ . '/includes/model.php';
require_once __DIR__ . '/includes/pmpro-gateway-solid.php';

if ( is_admin() ) {
    require_once __DIR__ . '/includes/pmpro-gateway-solid-admin.php';
}

add_action('init', function() {
    PMProGateway_Solid::init();
});

function solid_gateway_enqueue_admin_assets() {
    wp_enqueue_style('solid-gateway-admin-css', plugin_dir_url(__FILE__) . 'assets/css/style.css');
    wp_enqueue_script('solid-gateway-admin-js', plugin_dir_url(__FILE__) . 'assets/js/script.js', array('jquery'), '1.0', true);
}
add_action('admin_enqueue_scripts', 'solid_gateway_enqueue_admin_assets');

register_activation_hook(__FILE__, function () {
    if ( ! class_exists( 'PMProGateway_Solid_Product_Model' ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'includes/model.php';
    }

    PMProGateway_Solid_Product_Model::create_table();
});
