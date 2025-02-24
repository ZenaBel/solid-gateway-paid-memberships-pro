<?php
// includes/class.pmpro-gateway-solid-admin.php

defined('ABSPATH') || exit;

class PMProGateway_Solid_Admin {

    public function __construct() {
        // Тут підписуємося на потрібні адмін-хуки (наприклад, додавання меню).
    }

    public function admin_menu() {
        // Приклад створення сторінки в меню WordPress
        add_menu_page(
            'Solid Gateway Settings',
            'Solid Gateway',
            'manage_options',
            'solid-gateway',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page() {
        // Рендер сторінки з налаштуваннями
        echo '<h1>Налаштування Solid Gateway</h1>';
        // ...
    }
}

// Запуск цього класу, якщо ми в адмінці
if ( is_admin() ) {
    new PMProGateway_Solid_Admin();
}
