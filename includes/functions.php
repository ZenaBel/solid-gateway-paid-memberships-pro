<?php
// includes/functions.php

defined('ABSPATH') || exit;

/**
 * Допоміжна функція для логування.
 */
function solid_gateway_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        error_log('[SolidGateway] ' . print_r($message, true));
    }
}
