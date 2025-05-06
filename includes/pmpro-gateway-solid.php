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

            add_action('wp_ajax_pmpro_solid_hook', array(__CLASS__, 'pmpro_solid_hook'));
            add_action('wp_ajax_nopriv_pmpro_solid_hook', array(__CLASS__, 'pmpro_solid_hook'));

            add_action('wp_ajax_solid_gateway_subscription_info', array(__CLASS__, 'solid_gateway_subscription_info'));
            add_action('wp_ajax_nopriv_solid_gateway_subscription_info', array(__CLASS__, 'solid_gateway_subscription_info'));

            add_action('wp_ajax_solid_gateway_set_pause', array(__CLASS__, 'solid_gateway_set_pause'));
            add_action('wp_ajax_nopriv_solid_gateway_set_pause', array(__CLASS__, 'solid_gateway_set_pause'));

            add_action('wp_ajax_solid_gateway_cancel_pause', array(__CLASS__, 'solid_gateway_cancel_pause'));
            add_action('wp_ajax_nopriv_solid_gateway_cancel_pause', array(__CLASS__, 'solid_gateway_cancel_pause'));

            add_action('wp_ajax_solid_restore_subscription', array(__CLASS__, 'solid_restore_subscription'));
            add_action('wp_ajax_nopriv_solid_restore_subscription', array(__CLASS__, 'solid_restore_subscription'));
            add_action('wp_ajax_set_pause_dates', array(__CLASS__, 'my_pause_set_dates'));

            $gateway = pmpro_getGateway();
            if ($gateway == "solid") {
                add_filter('pmpro_include_billing_address_fields', '__return_false');
                add_filter('pmpro_required_billing_fields', [__CLASS__, 'pmpro_required_billing_fields']);
                add_filter('pmpro_include_payment_information_fields', '__return_false', 20);

                if (pmpro_getOption('solid_integration_type') === 'integrated_form') {
                    add_action('pmpro_checkout_before_submit_button', array(__CLASS__, 'pmpro_checkout_before_submit_button'));
                    add_action('wp_enqueue_scripts', array(__CLASS__, 'solid_gateway_enqueue_admin_assets'));

                    add_action('wp_ajax_solid_gateway_process', array(__CLASS__, 'pmpro_checkout_before_processing'));
                    add_action('wp_ajax_nopriv_solid_gateway_process', array(__CLASS__, 'pmpro_checkout_before_processing'));
                }

                add_filter('pmpro_checkout_default_submit_button', array(__CLASS__, 'pmpro_checkout_default_submit_button'));

                add_action('pmpro_checkout_before_processing', array(__CLASS__, 'pmpro_checkout_before_processing'), 10, 2);

                add_action('pmpro_save_membership_level', array(__CLASS__, 'pmpro_hide_level_from_levels_page_save'));
            }
        }

        public static function solid_gateway_enqueue_admin_assets() {
            wp_enqueue_style( 'jquery-modal-style', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.css');
            wp_enqueue_script( 'jquery' );

            wp_enqueue_script(
                'solid-form-script',
                'https://cdn.solidgate.com/js/solid-form.js',
                [],
                PMPRO_GATEWAY_SOLID_VERSION
            );
            wp_enqueue_script(
                'jquery-modal',
                'https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.js',
                array(
                    'jquery',
                ),
                PMPRO_GATEWAY_SOLID_VERSION
            );

            wp_register_script('solid-gateway-form-js', dirname(plugin_dir_url(__FILE__)) . '/assets/js/form.js', array('jquery'), '1.0.4', true);

            wp_localize_script('solid-gateway-form-js', 'pmpro_solid', [
                'checkout_url' => pmpro_url('checkout'),
                'nonce' => wp_create_nonce('pmpro_solid_nonce')
            ]);

            wp_enqueue_script('solid-gateway-form-js');
        }

        // Приклад: фільтри для реєстрації шлюзу
        public static function pmpro_gateways($gateways)
        {
            if (empty($gateways['solid'])) {
                $gateways['solid'] = __('Solid Gateway', 'pmpro');
            }
            return $gateways;
        }

        static function getGatewayOptions(): array
        {
            return [
//                'solid_logging',
                'solid_integration_type',
                'solid_api_key',
                'solid_api_secret',
                'solid_webhook_public_key',
                'solid_webhook_private_key',
                'currency',
            ];
        }

        public static function pmpro_payment_options($options)
        {
            $my_options = self::getGatewayOptions();
            return array_merge($options, $my_options);
        }

        public static function pmpro_payment_option_fields($values, $gateway)
        {
            ?>
<!--            <tr class="gateway gateway_solid" --><?php //if ($gateway !== 'solid') echo 'style="display: none;"'; ?><!-->
<!--                <th scope="row" valign="top">-->
<!--                    <label for="solid_logging">Logging:</label>-->
<!--                </th>-->
<!--                <td>-->
<!--                    <select id="solid_logging" name="solid_logging">-->
<!--                        <option value="no" --><?php //if ($values['solid_logging'] !== 'yes') echo 'selected="selected"'; ?><!-->
<!--                            No-->
<!--                        </option>-->
<!--                        <option value="yes" --><?php //if ($values['solid_logging'] === 'yes') echo 'selected="selected"'; ?><!-->
<!--                            Yes-->
<!--                        </option>-->
<!--                    </select>-->
<!--                </td>-->
<!--            </tr>-->
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
                        <code><?php echo admin_url("admin-ajax.php") . "?action=pmpro_solid_hook&type=order.updated"; ?></code>
                        <br/>
                        <code><?php echo admin_url("admin-ajax.php") . "?action=pmpro_solid_hook&type=subscribe.updated"; ?></code>
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

        public static function pmpro_checkout_default_submit_button($show): bool
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

        private static function validate_signature(string $request_body): string
        {
            return base64_encode(
                hash_hmac('sha512',
                    pmpro_getOption('solid_webhook_public_key') . $request_body . pmpro_getOption('solid_webhook_public_key'),
                    pmpro_getOption('solid_webhook_private_key'))
            );
        }

        private static function get_solid_order_body(MemberOrder $order): array
        {
            global $pmpro_currency;

            $membershipLevel = $order->getMembershipLevel();

            $code = $order->code;

            $is_subscription = self::is_subscription($order);

            if ($is_subscription) {
                $subscription_product_id = PMProGateway_Solid_Product_Model::get_product_mapping_by_product_id($order->membership_id)->uuid;

                $order_description = $membershipLevel->name . ' - Subscription';
            } else {
                $order_description = empty($membershipLevel->description) ? $membershipLevel->name : $membershipLevel->description;
            }

            $order_data = [
                'order_id' => $code,
                'order_description' => $order_description,
                'order_number' => (int)$order->id,
                'type' => 'auth',
                'settle_interval' => 120,
                'customer_email' => $order->getUser()->user_email,
                'website' => get_home_url(),
                'force3ds' => true,
                'google_pay_allowed_auth_methods' => ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
            ];

            if ($is_subscription) {
                $order_data['product_id'] = $subscription_product_id;
                $order_data['product_price_id'] = $subscription_product_id;
                $order_data['customer_account_id'] = $order->user_id;
                $order_data['order_metadata'] = [
                    'order_id' => $order->id,
                    'membership_id' => $order->membership_id,
                    'user_id' => $order->user_id,
                    'gateway_environment' => pmpro_getOption('gateway_environment'),
                ];
            } else {
                $order_data['currency'] = $pmpro_currency;
                $order_data['amount'] = round($order->total * 100);
            }

            return $order_data;

        }

        public static function pmpro_checkout_before_processing()
        {
            global $pmpro_review;

            $api = new Api(pmpro_getOption('solid_api_key'), pmpro_getOption('solid_api_secret'));

            /**
             * @var MemberOrder $order
             */
            $order = $pmpro_review;

            if (!self::is_subscription($order)) {
                $order->status = 'pending';
            }

            $order->saveOrder();

            $order_body = self::get_solid_order_body($order);
            $order_body['success_url'] = pmpro_url("confirmation", "?level=" . $order->membership_level->id . "&order_id=" . $order->getRandomCode());
            $order_body['fail_url'] = pmpro_url("checkout", "?level=" . $order->membership_level->id . "&error=Failed&order_id=" . $order->getRandomCode());

            $page_customization = [
                'public_name' => 'Solid Gateway',
                'order_title' => 'Order for ' . $order->membership_level->name,
                'order_description' => $order_body['order_description']
            ];

            if (pmpro_getOption('solid_integration_type') === 'integrated_form') {
                $response = $api->formMerchantData($order_body)->toArray();

                $json = [
                    'result' => 'success',
                    "form" => $response,
                    "redirects" => [
                        'success_url' => $order_body['success_url'],
                        'fail_url' => $order_body['fail_url'],
                    ]
                ];

                $response = json_encode($json);

                wp_send_json($response);
                wp_die();
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
                    } elseif ($response_body['error']['code']) {
                        wp_redirect(pmpro_url("checkout", "?level=" . $order->membership_level->id . "&error=" . $response_body['error']['code']));
                    } else {
                        wp_redirect(pmpro_url("checkout", "?level=" . $order->membership_level->id . "&error=unknown"));
                    }
                } else {
                    wp_redirect(pmpro_url("checkout", "?level=" . $order->membership_level->id . "&error=unknown"));
                }
                exit();
            }
        }

        static function pmpro_checkout_before_submit_button()
        {
            ?>
            <div id="pmpro_solid_form">
                <input type="hidden" name="solid_integration_type" value="integrated_form"/>
                <div id="solid-checkout-modal" class="modal"></div>
                <div id="solid-payment-form-container"></div>
            </div>
            <?php
        }

        static function pmpro_solid_hook()
        {
            if (!isset($_SERVER['REQUEST_METHOD'])
                || ('POST' !== $_SERVER['REQUEST_METHOD'])
                || !isset($_GET['type'])
                || !in_array($_GET['type'], ['order.updated', 'subscribe.updated'])
            ) {
                return;
            }

            $hook = new PMProGateway_Solid_Hooks();

            $request_body = file_get_contents('php://input');
            $request_headers = array_change_key_case($hook->get_request_headers(), CASE_UPPER);
            $type = $_GET['type'];

            if ($request_headers['SIGNATURE'] == self::validate_signature($request_body)) {
//                PMProGateway_Solid_Logger::debug('Incoming webhook: ' . print_r($request_headers, true) . "\n" . print_r($request_body, true));
                $hook->process_webhook($type, $request_body);
                status_header(200);
            } else {
//                PMProGateway_Solid_Logger::debug('Incoming webhook failed validation: ' . print_r($request_body, true));

                status_header(204);
            }
            exit;
        }

        static function pmpro_hide_level_from_levels_page_save($level_id)
        {
            global $pmpro_currency;

            if (PMProGateway_Solid_Product_Model::get_product_mapping_by_product_id($level_id)) {
                return;
            }

            $api = new Api(pmpro_getOption('solid_api_key'), pmpro_getOption('solid_api_secret'));

            $is_subscription = $_REQUEST['recurring'] === 'yes';

            if (!$is_subscription) {
                return;
            }

            $name = $_REQUEST['name'];
            $description = $_REQUEST['description'];
            $cycle_number = $_REQUEST['cycle_number'];
            $cycle_period = $_REQUEST['cycle_period'];
            $billing_limit = $_REQUEST['billing_limit'];
            $billing_amount = $_REQUEST['billing_amount'];
            $trial_amount = $_REQUEST['trial_amount'];

            $is_trial = $_REQUEST['custom_trial'] === 'yes';

            $body = [
                'name' => $name,
                'description' => empty($description) ? $name : $description,
                'status' => 'active',
                'payment_action' => 'auth_settle',
                'settle_interval' => 48,
                'billing_period' => [
                    'unit' => strtolower($cycle_period),
                    'value' => intval($cycle_number),
                ],
            ];

            if ($billing_limit > 0) {
                $body['term_length'] = $billing_limit;
            }

            if ($is_trial) {
                $body['trial'] = [
                    'billing_period' => [
                        'unit' => strtolower($cycle_period),
                        'value' => intval($cycle_number),
                    ],
                ];

                if ($trial_amount > 0) {
                    $body['trial']['payment_action'] = 'auth_settle';
                    $body['trial']['settle_interval'] = 48;
                } else {
                    $body['trial']['payment_action'] = 'auth_void';
                }
            }

            $response = $api->addProduct($body);
            $product = json_decode($response, true);

            if (isset($product['error'])) {
                $error = $product['error'];
                $error_message = $error['message'] . ' (' . $error['code'] . ')';
//                PMProGateway_Solid_Logger::debug('Error creating product: ' . $error_message);
                return;
            }

            $product_uuid = $product['id'];

            PMProGateway_Solid_Product_Model::create_product_mapping($level_id, $product_uuid);

            $bodyPrice = [
                'default' => true,
                'status' => 'active',
                'product_price' => (int)($billing_amount * 100),
                'currency' => $pmpro_currency,
            ];

            if ($is_trial) {
                $bodyPrice['trial_price'] = (int)($trial_amount * 100);
            }

            if (!empty($product_uuid)) {
                $response = $api->addPrice($product_uuid, $bodyPrice);
                $price = json_decode($response, true);

                if (isset($price['error'])) {
                    $error = $price['error'];
                    $error_message = $error['message'] . ' (' . $error['code'] . ')';
//                    PMProGateway_Solid_Logger::debug('Error creating price: ' . $error_message);
                    return;
                }
            } else {
//                PMProGateway_Solid_Logger::debug('Product UUID is empty');
            }
        }

        public static function solid_gateway_subscription_info()
        {
            $id = $_POST['id'];
            $subscription = new PMPro_Subscription($id);

            $response = [
                'allowed' => false,
                'restore_url' => false,
                'is_paused' => 0,
                'paused_hidden' => true,
            ];

            if ($subscription->get_status() == 'cancelled') {
                $restore_url = admin_url('admin-ajax.php') . '?action=solid_restore_subscription&subscription_id=' . $id;

                $response['restore_url'] = $restore_url;
            }

            if ($subscription->get_status() == 'active') {
                $is_paused = get_post_meta($id, '_solid_subscription_paused', true) === '1';
                if ($is_paused) {
                    $response['is_paused'] = 1;
                }
                $response['paused_hidden'] = false;
            }

            if ($subscription->get_gateway() == 'solid') {
                $response['allowed'] = true;
            }

            wp_send_json_success($response);
        }

        public static function solid_restore_subscription()
        {
            $subscription_id = $_GET['subscription_id'];
            $subscription = new PMPro_Subscription($subscription_id);

            if ($subscription->get_status() == 'cancelled' && $subscription->get_gateway() == 'solid') {
                $api = new Api(pmpro_getOption('solid_api_key'), pmpro_getOption('solid_api_secret'));

                $response = $api->reactivateSubscription([
                    'subscription_id' => $subscription->get_subscription_transaction_id(),
                ]);

                if (!is_wp_error($response)) {
                    $subscription->set('status', 'active');
                    $subscription->save();
                    wp_redirect($_SERVER['HTTP_REFERER']);
                    wp_die();
                }
            }
            wp_redirect( $_SERVER['HTTP_REFERER'] );
            wp_die();
        }

        private static function is_subscription(MemberOrder $order): bool
        {
            if ($order->getMembershipLevel()->initial_payment == 0) {
                return true;
            }

            return false;
        }

        function cancel_subscription($subscription): bool
        {
            $api = new Api(pmpro_getOption('solid_api_key'), pmpro_getOption('solid_api_secret'));

            $data = [
                'subscription_id' => $subscription->get_subscription_transaction_id(),
                'force' => true,
                'cancel_code' => '8.06',
            ];

            $response = $api->cancelSubscription($data);

//            PMProGateway_Solid_Logger::debug('Cancel subscription response: ' . print_r($response, true));

            if (!is_wp_error($response)) {
                $body = json_decode($response, true);
                if ($body['status'] === 'ok') {
                    $subscription->set('status', 'cancelled');
                    return true;
                }
            }
            return false;
        }

        /**
         * @param $subscription PMPro_Subscription
         * @return void
         */
        public function update_subscription_info($subscription)
        {
            $api = new Api(pmpro_getOption('solid_api_key'), pmpro_getOption('solid_api_secret'));

            $status = $api->getSubscriptionStatus([
                'subscription_id' => $subscription->get_subscription_transaction_id(),
            ]);

            if (!is_wp_error($status)) {
                $body = json_decode($status, true);
                if ($body['subscription']['status'] === 'cancelled') {
                    $subscription->set('status', $body['subscription']['status']);
                    $subscription->set('enddate', $body['subscription']['cancelled_at']);
                    $subscription->set('next_payment_date', '0000-00-00 00:00:00');
                } elseif ($body['subscription']['status'] === 'active') {
                    $subscription->set('status', $body['subscription']['status']);
                    $subscription->set('enddate', '0000-00-00 00:00:00');
                    $subscription->set('next_payment_date', $body['subscription']['next_charge_at']);
                } elseif ($body['subscription']['status'] === 'paused') {
                    $subscription->set('status', 'active');
                    $subscription->set('enddate', '0000-00-00 00:00:00');
                    $subscription->set('next_payment_date', '0000-00-00 00:00:00');
                }
                $subscription->save();
            } else {
//                PMProGateway_Solid_Logger::debug('Error getting subscription status: ' . print_r($status, true));
                $subscription->set('status', 'sync_error');
            }
        }

        static function solid_gateway_set_pause() {
            $start = sanitize_text_field($_POST['start_date']);
            $end   = sanitize_text_field($_POST['end_date']);
            $subscription_id = sanitize_text_field($_POST['subscription_id']);

            $subscription = new PMPro_Subscription($subscription_id);

            if (strtotime($end) <= strtotime($start)) {
                wp_send_json_error('The end date should be the start date.');
            }

            if ($subscription->get_status() == 'active' && $subscription->get_gateway() == 'solid') {
                $api = new Api(pmpro_getOption('solid_api_key'), pmpro_getOption('solid_api_secret'));

                $data = [
                    'start_point' => [
                        'type' => 'specific_date',
                        'date' => date('Y-m-d H:i:s', strtotime($start)),
                    ],
                    'stop_point' => [
                        'type' => 'specific_date',
                        'date' => date('Y-m-d H:i:s', strtotime($end)),
                    ],
                ];

                $response = $api->pauseSchedule($subscription->get_subscription_transaction_id(), $data);

                $response = json_decode($response, true);

                if ($response['pause']['from_date']) {
                    update_post_meta($subscription_id, '_solid_subscription_paused', 1);
                    wp_send_json_success('The pause is installed!');
                } else {
                    wp_send_json_error('Failed to keep pause. Try later.');
                }
                return;
            }
            wp_send_json_error('Subscription is not active or not using Solid Gateway.');
        }

        static function solid_gateway_cancel_pause() {
            $subscription_id = sanitize_text_field($_POST['subscription_id']);

            $subscription = new PMPro_Subscription($subscription_id);

            if ($subscription->get_status() == 'active' && $subscription->get_gateway() == 'solid') {
                $api = new Api(pmpro_getOption('solid_api_key'), pmpro_getOption('solid_api_secret'));

                $response = $api->removePauseSchedule($subscription->get_subscription_transaction_id());

                $response = json_decode($response, true);

                if ($response['status']) {
                    delete_post_meta($subscription_id, '_solid_subscription_paused');
                    wp_send_json_success('The pause is cancelled!');
                } else {
                    wp_send_json_error('Failed to cancel pause. Try later.');
                }
                return;
            }
            wp_send_json_error('Subscription is not active or not using Solid Gateway.');
        }
    }
}
