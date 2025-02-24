<?php
// includes/class.pmpro-gateway-solid.php

// Переконуємося, що файл не викликається напряму
defined('ABSPATH') || exit;

// Якщо необхідно, підключаємо базовий клас PMProGateway
// require_once( PMPRO_DIR . '/classes/gateways/class.pmprogateway.php' );

class PMProGateway_Solid extends PMProGateway
{
    /**
     * Конструктор
     */
    public function __construct($gateway = null) {
        $this->gateway = $gateway;
        return $this->gateway;
    }

    /**
     * Ініціалізація шлюзу (запускається в add_action('init', ...))
     */
    public static function init() {
        // 1. Додаємо цей шлюз у список
        add_filter('pmpro_gateways', [__CLASS__, 'pmpro_gateways']);

        // 2. Додаємо налаштування (опції) в адмінку
        add_filter('pmpro_payment_options', [__CLASS__, 'pmpro_payment_options']);
        add_filter('pmpro_payment_option_fields', [__CLASS__, 'pmpro_payment_option_fields'], 10, 2);

        // 3. Реєструємо обробку файлів (за потреби)
        add_action('pmpro_include_payment_gateway_files', [__CLASS__, 'pmpro_include_payment_gateway_files']);
    }

    // Приклад: фільтри для реєстрації шлюзу
    public static function pmpro_gateways($gateways) {
        if (empty($gateways['solid'])) {
            $gateways['solid'] = __('Solid Gateway', 'pmpro');
        }
        return $gateways;
    }

    public static function pmpro_include_payment_gateway_files($gateways) {
        // Якщо логіка велика, можна підключити додаткові файли
    }

    public static function pmpro_payment_options($options) {
        $my_options = [
            'integration_type',
            'solid_api_key',
            'solid_api_secret',
            'webhook_public_key',
            'webhook_private_key'
        ];
        return array_merge($options, $my_options);
    }

    public static function pmpro_payment_option_fields($values, $gateway) {
        ?>
        <tr>
            <th scope="row" valign="top">
                <label for="integration_type">Integration Type:</label>
            </th>
            <td>
                <select id="integration_type" name="integration_type">
                    <option value="integrated_form" <?php if($values['integration_type'] === 'integrated_form') echo 'selected="selected"'; ?>>Integrated form</option>
                    <option value="payment_page" <?php if($values['integration_type'] === 'payment_page') echo 'selected="selected"'; ?>>Payment page</option>
                </select>
            </td>
        </tr>
        <tr class="gateway gateway_solid" <?php if($gateway !== 'solid') echo 'style="display: none;"'; ?>>
            <th scope="row" valign="top">
                <label for="solid_api_key">Solid Gateway API Key:</label>
            </th>
            <td>
                <input type="text" id="solid_api_key" name="solid_api_key"
                       value="<?php echo esc_attr($values['solid_api_key']); ?>" size="60" />
            </td>
        </tr>
        <tr class="gateway gateway_solid" <?php if($gateway !== 'solid') echo 'style="display: none;"'; ?>>
            <th scope="row" valign="top">
                <label for="solid_api_secret">Solid Gateway API Secret:</label>
            </th>
            <td>
                <input type="password" id="solid_api_secret" name="solid_api_secret"
                       value="<?php echo esc_attr($values['solid_api_secret']); ?>" size="60" />
            </td>
        </tr>
        <tr>
            <th scope="row" valign="top">
                <label for="webhook_public_key">Webhook Public Key:</label>
            </th>
            <td>
                <input type="text" id="webhook_public_key" name="webhook_public_key"
                       value="<?php echo esc_attr($values['webhook_public_key']); ?>" size="60" />
            </td>
        </tr>
        <tr>
            <th scope="row" valign="top">
                <label for="webhook_private_key">Webhook Private Key:</label>
            </th>
            <td>
                <input type="password" id="webhook_private_key" name="webhook_private_key"
                       value="<?php echo esc_attr($values['webhook_private_key']); ?>" size="60" />
            </td>
        </tr>
        <?php
    }

    /**
     * Основний метод для обробки одноразових платежів
     */
    public function process(&$order) {
        // Тут розміщується логіка виклику вашого API
        // Взяти API ключі:
        $api_key = pmpro_getOption('solid_api_key');
        $api_secret = pmpro_getOption('solid_api_secret');

        // Виклик вашого сервісу та обробка результату
        // Приклад "успіху":
        $order->payment_transaction_id = 'TRANSACTION123';
        $order->updateStatus('success');
        return true;
    }
}
