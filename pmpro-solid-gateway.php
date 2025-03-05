<?php
/*
Plugin Name: PMPro Solid Gateway
Description: Custom gateway for Paid Memberships Pro
Version: 0.0.1
Author: CoDi
*/

defined('ABSPATH') || exit; // Коментар українською: захист від прямого виклику файлу

define( 'PMPRO_GATEWAY_SOLID_VERSION', '0.0.1' );

if ( file_exists(__DIR__ . '/vendor/autoload.php') ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/hooks.php';
require_once __DIR__ . '/includes/pmpro-gateway-solid.php';

if ( is_admin() ) {
    require_once __DIR__ . '/includes/pmpro-gateway-solid-admin.php';
}

add_action('init', function() {
    // Викликаємо статичний метод init (або щось подібне) з основного класу
    PMProGateway_Solid::init();
});

add_filter('pmpro_before_change_membership_level', array('PMProGateway_Solid', 'pmpro_added_membership_level'), 10, 4);

function solid_gateway_enqueue_admin_assets() {
    wp_enqueue_style('solid-gateway-admin-css', plugin_dir_url(__FILE__) . 'assets/css/style.css');
    wp_enqueue_script('solid-gateway-admin-js', plugin_dir_url(__FILE__) . 'assets/js/script.js', array('jquery'), '1.0', true);
}
add_action('admin_enqueue_scripts', 'solid_gateway_enqueue_admin_assets');
