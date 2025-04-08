<?php
// includes/hooks.php

use SolidGate\API\Api;

defined('ABSPATH') || exit;

class PMProGateway_Solid_Hooks
{

    public function get_request_headers(): array
    {
        if ( ! function_exists( 'getallheaders' ) ) {
            $headers = [];
            foreach ( $_SERVER as $name => $value ) {
                if ( 'HTTP_' === substr( $name, 0, 5 ) ) {
                    $headers[ str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ) ] = $value;
                }
            }

            return $headers;
        } else {
            return getallheaders();
        }
    }

    private function process_webhook_auth($notification)
    {
        PMProGateway_Solid_Logger::debug( sprintf( 'Webhook auth: %1$s', $notification->order->status ) );

        $order = new MemberOrder($notification->order->order_id);

        $order->payment_transaction_id = current($notification->transactions)->card->card_token->token;
        $order->saveOrder();
    }

    private function process_webhook_charge_approved($notification)
    {
        PMProGateway_Solid_Logger::debug( sprintf( 'Webhook charge approved: %1$s', $notification->order->status ) );

        $order = new MemberOrder($notification->order->order_id);

        $order->payment_transaction_id = current($notification->transactions)->card->card_token->token;
        $order->status = 'success';
        $order->updateStatus('success');
        $order->saveOrder();

        if ( function_exists('pmpro_changeMembershipLevel') ) {
            pmpro_changeMembershipLevel($order->membership_id, $order->user_id);
        }

        $this->create_subscription( $notification, $order );
    }

    private function process_webhook_charge_declined($notification)
    {
        PMProGateway_Solid_Logger::debug( sprintf( 'Webhook charge declined: %1$s', $notification->order->status ) );

        $order = new MemberOrder($notification->order->order_id);

        $order->payment_transaction_id = current($notification->transactions)->card->card_token->token;
        $order->status = 'error';
        $order->updateStatus('error');
        $order->saveOrder();
    }

    private function process_webhook_charge_refunded($notification)
    {
        PMProGateway_Solid_Logger::debug( sprintf( 'Webhook charge refunded: %1$s', $notification->order->status ) );

        $order = new MemberOrder($notification->order->order_id);

        $order->payment_transaction_id = current($notification->transactions)->card->card_token->token;
        $order->status = 'refunded';
        $order->updateStatus('refunded');
        $order->saveOrder();
    }

    public function process_webhook(string $type, string $request_body)
    {
        $notification = json_decode($request_body);
        switch ( $type ) {
            case 'order.updated':
                switch ($notification->order->status) {
                    case 'auth_ok':
                        $this->process_webhook_auth( $notification );
                        break;
                    case 'approved':
                    case 'settle_ok':
                        $this->process_webhook_charge_approved( $notification );
                        break;
                    case 'declined':
                        $this->process_webhook_charge_declined( $notification );
                        break;
                    case 'refunded':
                        $this->process_webhook_charge_refunded( $notification );
                        break;
                    default:
                        PMProGateway_Solid_Logger::debug( sprintf( 'Необработанный hook: %1$s -> %2$s', $type, $notification->order->status ) );
                        break;
                }
                break;
            case 'subscribe.updated':
                switch ($notification->callback_type) {
                    case 'init':
                    case 'active':
                        $this->subscription_create( $notification );
                        break;
                    case 'cancel':
                        $this->process_cancel_subscription( $notification );
                        break;
                    case 'renew':
                    case 'restore':
                        $this->process_renew_subscription( $notification );
                        break;
                    case 'expire':
                        $this->process_expire_subscription( $notification );
                        break;
                    case 'pause_schedule.create':
                    case 'pause_schedule.update':
                        $this->process_pause_schedule_create( $notification );
                        break;
                    case 'pause_schedule.delete':
                        $this->process_pause_schedule_delete( $notification );
                        break;
                    case 'order_update':
                        $this->process_order_update( $notification );
                        break;
                    default:
                        PMProGateway_Solid_Logger::debug( sprintf( 'Необработанный hook: %1$s -> %2$s', $type, $notification->callback_type ) );
                        break;
                }
                break;
            default:
                PMProGateway_Solid_Logger::debug( sprintf( 'Необработанный hook: %1$s',$type ) );
                break;
        }
    }

    private function create_subscription($notification, MemberOrder $order)
    {
        global $pmpro_currency;

        $api = new Api(pmpro_getOption('solid_api_key'), pmpro_getOption('solid_api_secret'));

        $membershipLevel = $order->getMembershipLevel();

        $code = $order->getRandomCode();

        $body = [
            'order_id' => $code,
            'product_id' => PMProGateway_Solid_Product_Model::get_product_mapping_by_product_id($order->membership_id)->uuid,
            'currency' => $pmpro_currency,
            'order_description' => empty(trim($membershipLevel->description)) ? $membershipLevel->name : $membershipLevel->description ,
            'order_number' => $order->id,
            'type' => 'auth',
            'settle_interval' => 48,
            'payment_type' => 'rebill',
            'recurring_token' => current($notification->transactions)->card->card_token->token,
//            'force3ds' => false,
//            'external_mpi_data' => [
//                'three_ds_version' => '2.2.0',
//                'three_ds_flow' => 'challenge',
//                'ds_enrollment_response' => 'Y',
//
//            ],
            'customer_account_id' => $order->user_id,
            'customer_email' => $order->getUser()->user_email,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'platform' => 'WEB',
            'order_metadata' => [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'membership_id' => $order->membership_id,
                'gateway_environment' => pmpro_getOption('gateway_environment'),
            ],
        ];

        PMProGateway_Solid_Logger::debug(sprintf( 'Создание подписки $body: %1$s', print_r($body, true) ) );

        $response = $api->recurring($body);

        PMProGateway_Solid_Logger::debug(sprintf( 'Создание подписки: %1$s', print_r($response, true) ) );

        if ( ! empty( $response->error ) ) {
            PMProGateway_Solid_Logger::debug( sprintf( 'Ошибка создания подписки: %1$s', print_r($response, true) ) );
            return;
        }

        if ( ! empty( $response->order->status ) && $response->order->status === 'approved' ) {
            $order->payment_transaction_id = $response->order->id;
            $order->status = 'success';
            $order->updateStatus('success');
            $order->saveOrder();

            PMProGateway_Solid_Logger::debug( sprintf( 'Создание подписки: %1$s', print_r($response, true) ) );
        } else {
            PMProGateway_Solid_Logger::debug( sprintf( 'Ошибка создания подписки: %1$s', $response ) );
        }
    }

    private function subscription_create($notification)
    {
        PMProGateway_Solid_Logger::debug( sprintf( 'Активація підписки: %1$s', $notification->order->status ) );

        PMProGateway_Solid_Logger::debug('Контент: ' . print_r($notification, true));

        $invoices = $notification->invoices;
        if (!empty($invoices)) {
            $firstInvoice = current($invoices);
            if (isset($firstInvoice->order_metadata) && isset($firstInvoice->order_metadata->membership_id)) {
                $membershipId = $firstInvoice->order_metadata->membership_id;
            } else {
                $membershipId = null;
            }
            if (isset($firstInvoice->order_metadata) && isset($firstInvoice->order_metadata->order_id)) {
                $orderId = $firstInvoice->order_metadata->order_id;
            } else {
                $orderId = null;
            }
        } else {
            $membershipId = null;
        }

        $membershipLevel = (new MemberOrder($orderId))->getMembershipLevel();

        $subscription = new PMPro_Subscription();

        $subscription->set('id', (new MemberOrder())->getRandomCode());
        $subscription->set('user_id', $notification->customer->customer_account_id);
        $subscription->set('membership_level_id', $membershipId);
        $subscription->set('gateway', 'solid');
        $subscription->set('gateway_environment', pmpro_getOption('gateway_environment'));
        $subscription->set('subscription_transaction_id', $notification->subscription->id);
        $subscription->set('status', 'active');
        $subscription->set('startdate', $notification->subscription->started_at);
        $subscription->set('next_payment_date', $notification->subscription->next_charge_at);
        $subscription->set('billing_amount', $membershipLevel->billing_amount);
        $subscription->set('cycle_number', $membershipLevel->cycle_number);
        $subscription->set('cycle_period', $membershipLevel->cycle_period);
        $subscription->set('billing_limit', $membershipLevel->billing_limit);
        $subscription->set('trial_amount', $membershipLevel->trial_amount);
        $subscription->set('trial_limit', $membershipLevel->trial_limit);
        $subscription->set('initial_payment', $membershipLevel->initial_payment);
        $subscription_id = $subscription->save();

        if ( function_exists('pmpro_changeMembershipLevel') ) {
            pmpro_changeMembershipLevel($membershipLevel->id, $notification->customer->customer_account_id);
        }

        if ( ! $subscription_id ) {
            PMProGateway_Solid_Logger::debug( sprintf( 'Ошибка создания подписки: %1$s', print_r($subscription, true) ) );
            return;
        }

        PMProGateway_Solid_Logger::debug( sprintf( 'Создание подписки: %1$s', print_r($subscription, true) ) );
    }

    private function process_order_update($notification)
    {
        PMProGateway_Solid_Logger::debug( sprintf( 'Обновление заказа: %1$s', $notification->order->status ) );

        $invoice = current($notification->invoices);

        $order_id = current($invoice->orders);

        if ( ! $order_id ) {
            PMProGateway_Solid_Logger::debug( sprintf( 'Не удалось получить ID заказа: %1$s', print_r($invoice, true) ) );
            return;
        }

        $order = new MemberOrder($order_id->id);

        if ( ! $order ) {
            PMProGateway_Solid_Logger::debug( sprintf( 'Не удалось получить заказ: %1$s', print_r($invoice, true) ) );
            return;
        }



        $order = new MemberOrder($notification->order->order_id);

        $order->payment_transaction_id = current($notification->transactions)->card->card_token->token;
        $order->status = 'success';
        $order->updateStatus('success');
        $order->saveOrder();
    }

    private function process_cancel_subscription($notification)
    {
        PMProGateway_Solid_Logger::debug( sprintf( 'Отмена подписки: %1$s', $notification->order->status ) );

        $subscription = PMPro_Subscription::get_subscription_from_subscription_transaction_id($notification->subscription->id, 'solid', pmpro_getOption('gateway_environment'));

        $subscription->set('status', 'cancelled');
        $subscription->set('enddate', $notification->subscription->cancelled_at);
        $subscription->set('next_payment_date', '0000-00-00 00:00:00');
        $s = $subscription->save();

        if ( ! $s ) {
            PMProGateway_Solid_Logger::debug( sprintf( 'Не удалось отменить подписку: %1$s', print_r($subscription, true) ) );
            return;
        }

        if ( function_exists('pmpro_changeMembershipLevel') ) {
            pmpro_changeMembershipLevel($subscription->get_membership_level_id(), $subscription->get_user_id());
        }
    }

    private function process_renew_subscription($notification)
    {
        PMProGateway_Solid_Logger::debug( sprintf( 'Продление подписки: %1$s', $notification->order->status ) );

        $subscription = PMPro_Subscription::get_subscription_from_subscription_transaction_id($notification->subscription->id, 'solid', pmpro_getOption('gateway_environment'));

        PMProGateway_Solid_Logger::debug( sprintf( 'Подписка: %1$s', print_r($subscription, true) ) );

        $subscription->set('status', 'active');
        $subscription->set('enddate', '0000-00-00 00:00:00');
        $subscription->set('next_payment_date', $notification->subscription->next_charge_at);
        $s = $subscription->save();

        if ( ! $s ) {
            PMProGateway_Solid_Logger::debug( sprintf( 'Не удалось продлить подписку: %1$s', print_r($subscription, true) ) );
            return;
        }

        if ( function_exists('pmpro_changeMembershipLevel') ) {
            pmpro_changeMembershipLevel($subscription->get_membership_level_id(), $subscription->get_user_id());
        }
    }

    private function process_expire_subscription($notification)
    {
        PMProGateway_Solid_Logger::debug( sprintf( 'Истечение подписки: %1$s', $notification->order->status ) );

        $subscription = PMPro_Subscription::get_subscription_from_subscription_transaction_id($notification->subscription->id, 'solid', pmpro_getOption('gateway_environment'));

        PMProGateway_Solid_Logger::debug( sprintf( 'Подписка: %1$s', print_r($subscription, true) ) );

        $subscription->set('status', 'cancelled');
        $subscription->set('enddate', $notification->subscription->expired_at);
        $subscription->set('next_payment_date', '0000-00-00 00:00:00');
        $s = $subscription->save();

        if ( ! $s ) {
            PMProGateway_Solid_Logger::debug( sprintf( 'Не удалось истечь подписку: %1$s', print_r($subscription, true) ) );
            return;
        }

        if ( function_exists('pmpro_changeMembershipLevel') ) {
            pmpro_changeMembershipLevel($subscription->get_membership_level_id(), $subscription->get_user_id());
        }
    }

    private function process_pause_schedule_create($notification)
    {
        PMProGateway_Solid_Logger::debug( sprintf( 'Создание расписания паузы: %1$s', $notification->order->status ) );

        $subscription = PMPro_Subscription::get_subscription_from_subscription_transaction_id($notification->subscription->id, 'solid', pmpro_getOption('gateway_environment'));

        PMProGateway_Solid_Logger::debug( sprintf( 'Подписка: %1$s', print_r($subscription, true) ) );

        update_post_meta($subscription->get_id(), '_solid_subscription_paused', 1);
    }

    private function process_pause_schedule_delete($notification)
    {
        PMProGateway_Solid_Logger::debug( sprintf( 'Удаление расписания паузы: %1$s', $notification->order->status ) );

        $subscription = PMPro_Subscription::get_subscription_from_subscription_transaction_id($notification->subscription->id, 'solid', pmpro_getOption('gateway_environment'));

        PMProGateway_Solid_Logger::debug( sprintf( 'Подписка: %1$s', print_r($subscription, true) ) );

        delete_post_meta($subscription->get_id(), '_solid_subscription_paused');

    }
}
