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

function my_pmpro_custom_field() {
    ?>
    <div class="my-custom-field">
        <label for="my_field"><?php _e('My Custom Field', 'your-textdomain'); ?></label>
        <input type="text" id="my_field" name="my_field" value="" />
    </div>
    <?php
}
add_action('pmpro_checkout_after_level', 'my_pmpro_custom_field');
