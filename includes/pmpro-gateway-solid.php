<?php
// includes/class.pmpro-gateway-solid.php

// Переконуємося, що файл не викликається напряму
use SolidGate\API\Api;

defined('ABSPATH') || exit;

// paid memberships pro required
if (!class_exists('PMProGateway')) {
    return;
}

if (!class_exists('PMProGateway_Solid')) {

    class PMProGateway_Solid extends PMProGateway
    {
        private $api;

        /**
         * Конструктор
         */
        public function __construct($gateway = null)
        {
            $this->gateway = $gateway;
            return $this->gateway;
        }

        /**
         * Ініціалізація шлюзу (запускається в add_action('init', ...))
         */
        public static function init()
        {
            // 1. Додаємо цей шлюз у список
            add_filter('pmpro_gateways', [__CLASS__, 'pmpro_gateways']);

            // 2. Додаємо налаштування (опції) в адмінку
            add_filter('pmpro_payment_options', [__CLASS__, 'pmpro_payment_options']);
            add_filter('pmpro_payment_option_fields', [__CLASS__, 'pmpro_payment_option_fields'], 10, 2);

            add_action('wp_ajax_pmpro_solid_ipn', array(__CLASS__, 'pmpro_solid_ipn'));
            add_action('wp_ajax_nopriv_pmpro_solid_ipn', array(__CLASS__, 'pmpro_solid_ipn'));

            $gateway = pmpro_getGateway();
            if ($gateway == "solid") {
                add_filter('pmpro_include_billing_address_fields', '__return_false');
                add_filter('pmpro_required_billing_fields', [__CLASS__, 'pmpro_required_billing_fields']);
                add_filter('pmpro_include_payment_information_fields', '__return_false', 20);

                add_filter('pmpro_checkout_default_submit_button', array(__CLASS__, 'pmpro_checkout_default_submit_button'));

                add_action('wp_enqueue_scripts', array(__CLASS__, 'pmpro_solidgate_enqueue_scripts'));

                add_action('pmpro_checkout_confirmed', array(__CLASS__, 'pmpro_checkout_confirmed'), 10, 2);
            }
        }

        // Приклад: фільтри для реєстрації шлюзу
        public static function pmpro_gateways($gateways)
        {
            if (empty($gateways['solid'])) {
                $gateways['solid'] = __('Solid Gateway', 'pmpro');
            }
            return $gateways;
        }

        public static function pmpro_payment_options($options)
        {
            $my_options = [
                'solid_logging',
                'solid_integration_type',
                'solid_api_key',
                'solid_api_secret',
                'solid_webhook_public_key',
                'solid_webhook_private_key',
                'currency',
                'tax_state',
                'tax_rate'
            ];
            return array_merge($options, $my_options);
        }

        public static function pmpro_payment_option_fields($values, $gateway)
        {
            ?>
            <tr class="gateway gateway_solid" <?php if ($gateway !== 'solid') echo 'style="display: none;"'; ?>>
                <th scope="row" valign="top">
                    <label for="solid_logging">Logging:</label>
                </th>
                <td>
                    <select id="solid_logging" name="solid_logging">
                        <option value="no" <?php if ($values['solid_logging'] !== 'yes') echo 'selected="selected"'; ?>>
                            No
                        </option>
                        <option value="yes" <?php if ($values['solid_logging'] === 'yes') echo 'selected="selected"'; ?>>
                            Yes
                        </option>
                    </select>
                </td>
            </tr>
            <tr class="gateway gateway_solid" <?php if ($gateway !== 'solid') echo 'style="display: none;"'; ?>>
                <th scope="row" valign="top">
                    <label for="solid_integration_type">Integration Type:</label>
                </th>
                <td>
                    <select id="solid_integration_type" name="solid_integration_type">
                        <option value="integrated_form" <?php if ($values['solid_integration_type'] === 'integrated_form') echo 'selected="selected"'; ?>>
                            Integrated form
                        </option>
                        <option value="payment_page" <?php if ($values['solid_integration_type'] === 'payment_page') echo 'selected="selected"'; ?>>
                            Payment page
                        </option>
                    </select>
                </td>
            </tr>
            <tr class="gateway gateway_solid" <?php if ($gateway !== 'solid') echo 'style="display: none;"'; ?>>
                <th scope="row" valign="top">
                    <label for="solid_api_key">Solid Gateway API Key:</label>
                </th>
                <td>
                    <input type="text" id="solid_api_key" name="solid_api_key"
                           value="<?php echo esc_attr($values['solid_api_key']); ?>" size="60"/>
                </td>
            </tr>
            <tr class="gateway gateway_solid" <?php if ($gateway !== 'solid') echo 'style="display: none;"'; ?>>
                <th scope="row" valign="top">
                    <label for="solid_api_secret">Solid Gateway API Secret:</label>
                </th>
                <td>
                    <input type="password" id="solid_api_secret" name="solid_api_secret"
                           value="<?php echo esc_attr($values['solid_api_secret']); ?>" size="60"/>
                </td>
            </tr>
            <tr class="gateway gateway_solid" <?php if ($gateway !== 'solid') echo 'style="display: none;"'; ?>>
                <th scope="row" valign="top">
                    <label for="solid_webhook_public_key">Webhook Public Key:</label>
                </th>
                <td>
                    <input type="text" id="solid_webhook_public_key" name="solid_webhook_public_key"
                           value="<?php echo esc_attr($values['solid_webhook_public_key']); ?>" size="60"/>
                </td>
            </tr>
            <tr class="gateway gateway_solid" <?php if ($gateway !== 'solid') echo 'style="display: none;"'; ?>>
                <th scope="row" valign="top">
                    <label for="solid_webhook_private_key">Webhook Private Key:</label>
                </th>
                <td>
                    <input type="password" id="solid_webhook_private_key" name="solid_webhook_private_key"
                           value="<?php echo esc_attr($values['solid_webhook_private_key']); ?>" size="60"/>
                </td>
            </tr>
            <tr class="gateway gateway_solid" <?php if ($gateway !== 'solid') echo 'style="display: none;"'; ?>>
                <th scope="row" valign="top">
                    <label>Webhook:</label>
                </th>
                <td>
                    <p>
                        <?php esc_html_e('To setup your webhook, use the following URL as the webhook URL in your Solid Gateway dashboard.', 'pmpro'); ?>
                        <br/>
                        <br/>
                        <code><?php echo admin_url("admin-ajax.php") . "?action=pmpro_solid_ipn"; ?></code>
                    </p>
                </td>
            </tr>
            <?php
        }

        public static function supports($feature)
        {
            $supports = array(
                'subscription_sync' => true,
                'payment_method_updates' => false
            );

            if (empty($supports[$feature])) {
                return false;
            }

            return $supports[$feature];
        }

        public static function pmpro_required_billing_fields($fields)
        {
            unset($fields['bfirstname']);
            unset($fields['blastname']);
            unset($fields['baddress1']);
            unset($fields['bcity']);
            unset($fields['bstate']);
            unset($fields['bzipcode']);
            unset($fields['bphone']);
            unset($fields['bemail']);
            unset($fields['bcountry']);
            unset($fields['CardType']);
            unset($fields['AccountNumber']);
            unset($fields['ExpirationMonth']);
            unset($fields['ExpirationYear']);
            unset($fields['CVV']);

            return $fields;
        }

        public static function pmpro_checkout_default_submit_button($show)
        {
            global $gateway, $pmpro_requirebilling;

            //show our submit buttons
            ?>
            <span id="pmpro_submit_span">
                <input type="hidden" name="submit-checkout" value="1"/>
                <input type="hidden" name="gateway" value="<?php echo esc_attr($gateway); ?>"/>
                <input type="submit" id="pmpro_btn-submit"
                       class="<?php echo esc_attr(pmpro_get_element_class('pmpro_btn pmpro_btn-submit-checkout', 'pmpro_btn-submit-checkout')); ?>"
                       value="<?php if ($pmpro_requirebilling) {
                           esc_html_e('Check Out with Solidgate', 'paystack-gateway-paid-memberships-pro');
                       } else {
                           esc_html_e('Submit and Confirm', 'paystack-gateway-paid-memberships-pro');
                       } ?>"/>
            </span>
            <?php

            //don't show the default
            return false;
        }

        public function check_for_webhook()
        {
            if (!isset($_SERVER['REQUEST_METHOD'])
                || ('POST' !== $_SERVER['REQUEST_METHOD'])
                || !isset($_GET['solid-api'])
                || ('solid_subscribe_hook' !== $_GET['solid-api'])
            ) {
                return;
            }

            $request_body = file_get_contents('php://input');
            $request_headers = array_change_key_case($this->hooks->get_request_headers(), CASE_UPPER);

            WC_Solid_Subscribe_Logger::debug('Incoming webhook: ' . print_r($request_headers, true) . "\n" . print_r($request_body, true));
            if ($request_headers['SIGNATURE'] == $this->validate_signature($request_body)) {
                WC_Solid_Subscribe_Logger::debug('Incoming webhook123: ' . print_r($_GET['type'], true) . "\n" . print_r($request_headers, true) . "\n" . print_r($request_body, true));
                $this->hooks->process_webhook($_GET['type'], $request_body);
                status_header(200);
            } else {
                WC_Solid_Subscribe_Logger::debug('Incoming webhook failed validation: ' . print_r($request_body, true));

                status_header(204);
            }
            exit;

        }

        public static function pmpro_solidgate_enqueue_scripts()
        {
            wp_register_script('pmpro_solid',
                dirname(plugin_dir_url(__FILE__)) . '/assets/js/script.js',
                array('jquery'),
                '1.0.0',
                true
            );

            // Підключаємо скрипт
            wp_localize_script('pmpro_solid', 'pmpro_solid', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pmpro_solid_nonce')
            ]);

            wp_enqueue_script('pmpro_solid');
        }

        /**
         * Основний метод для обробки одноразових платежів
         */
        public function process(&$order): bool
        {
            $order->status = 'pending';
            $order->saveOrder();

            return true;
        }

        private static function get_solid_order_body(MemberOrder $order): array
        {
            global $pmpro_currency;

            return [
                'order_id' => $order->getRandomCode(),
                'currency' => $pmpro_currency,
                'amount' => round($order->total * 100),
                'order_description' => $order->membership_level->name,
                'website' => get_home_url(),
                'google_pay_allowed_auth_methods' => ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
//                'order_items' => $items_str,
                'type' => 'auth',
                'order_number' => (int)$order->id,
                'settle_interval' => 120,
//                'customer_email' => $order->get_billing_email(),
//                'customer_first_name' => $order->get_billing_first_name(),
//                'customer_last_name' => $order->get_billing_last_name(),
            ];

        }

        public static function pmpro_checkout_confirmed($pmpro_confirmed, $order)
        {
            if (!$pmpro_confirmed) {
                return;
            }

            $api = new Api(pmpro_getOption('solid_api_key'), pmpro_getOption('solid_api_secret'));

            $order_body = self::get_solid_order_body($order);
            $nonce = wp_create_nonce('s_checkout_nonce');
            $order_body['success_url'] = admin_url("admin-ajax.php") . '?action=pmpro_solid_ipn&order_id=' . $order->getRandomCode() . '&_wpnonce=' . $nonce . '&status=success';
            $order_body['fail_url'] = admin_url("admin-ajax.php") . '?action=pmpro_solid_ipn&order_id=' . $order->getRandomCode() . '&_wpnonce=' . $nonce . '&status=fail';

            $page_customization = [
                'public_name' => 'Solid Gateway',
                'order_title' => 'Order for ' . $order->membership_level->name,
                'order_description' => $order_body['order_description']
            ];

            if (pmpro_getOption('solid_integration_type') === 'integrated_form') {
                $response = $api->formMerchantData($order_body)->toArray();
                return [
                    'result' => 'success',
                    "form" => $response,
                    "redirects" => [
                        'success_url' => $order_body['success_url'],
                        'fail_url' => $order_body['fail_url'],
                    ]
                ];
            } else {
                $request_body = json_encode([
                    'order' => $order_body,
                    'page_customization' => $page_customization
                ]);

                $signature = $api->generateSignature($request_body);

                $args = [
                    'headers' => [
                        'merchant' => pmpro_getOption('solid_api_key'),
                        'Signature' => $signature,
                        'Content-Type' => 'application/json'
                    ],
                    'body' => $request_body
                ];

                $response = wp_remote_post('https://payment-page.solidgate.com/api/v1/init', $args);

                if (!is_wp_error($response)) {
                    $response_body = json_decode($response['body'], true);
                    if ($response_body['url']) {
                        wp_redirect($response_body['url']);
                        exit();
                    } elseif ($response_body['error']['code']) {
                        wc_add_notice('Connection error. [' . $response_body['error']['code'] . ']', 'error');
                    } else {
                        wc_add_notice('Please try again.', 'error');
                    }
                    exit;
                } else {
                    wc_add_notice('Connection error.', 'error');
                }

            }
        }

        function update(&$order)
        {
            dd($order);
        }

        function cancel_subscription($subscription)
        {
            dd($subscription);
        }

        function check_token_order($order)
        {
            dd($order);
        }

        static function pmpro_added_membership_level($level_id, $user_id, $old_levels, $cancel_level)
        {
            error_log("Зміна членства: Новий рівень - $level_id, Користувач - $user_id");
            dd($level_id, $user_id, $old_levels, $cancel_level);
        }

        static function pmpro_solid_ipn()
        {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 's_checkout_nonce')) {
                wp_send_json_error(['message' => 'Invalid nonce']);
            }

            $order_id = $_GET['order_id'];
            $status = $_GET['status'];

            $order = new MemberOrder($order_id);
            if ($status === 'success') {
                $order->updateStatus('success');
            } else {
                $order->updateStatus('error');
            }

            wp_send_json_success(['message' => 'Order status updated']);
        }


    }
}
