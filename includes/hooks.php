<?php
// includes/hooks.php

defined('ABSPATH') || exit;

/**
 * Приклад хуку, що викликається після успішного оформлення.
 */
function solid_gateway_after_checkout($user_id, $order) {
    // Можна викликати функцію логування
    solid_gateway_log("У користувача з ID $user_id успішна покупка. Order code: " . $order->code);
    // Можна виконати будь-яку інтеграцію чи надсилання email
}

add_action('pmpro_after_checkout', 'solid_gateway_after_checkout', 10, 2);
