<?php

/**
 * Plugin Name: WooCommerce FloxyPay Gateway
 * Plugin URI: https://google.com/
 * Description: Get the whole new woocommerce payment gateway integrated on your site.
 * Author: Sabir Ali
 * Author URI: https://google.com/
 * Version: 1.0
 * Requires at least: 4.4
 * Tested up to: 5.6
 * WC requires at least: 3.0
 * WC tested up to: 4.9
 * Text Domain: woocommerce-gateway-floxy
 * Domain Path: /languages
 *
 */

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'floxy_add_gateway_class');
function floxy_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Floxy_Gateway'; // your class name is here
    return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'floxy_init_gateway_class');
function floxy_init_gateway_class()
{

    class WC_Floxy_Gateway extends WC_Payment_Gateway
    {

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct()
        {

            $this->id = 'floxypay'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'FloxyPay Payment Gateway';
            $this->method_description = 'Newly developed WooCommerce Extension for FloxyPay Gateway'; // will be displayed on the options page

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            // This action hook saves the settings
            foreach ( $this->settings as $setting_key => $value ) {
                $this->$setting_key = $value;
              }
            add_action( 'woocommerce_api_'.$this->id, array( $this, 'webhook' ) );
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        /**
         * Plugin options, we deal with it in Step 3 too
         */
        public function init_form_fields()
        {

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable FloxyPay Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'FloxyPay',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay with your credit card via our super-cool payment gateway.',
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'x_key' => array(
                    'title'       => 'x-key',
                    'type'        => 'text'
                ),
                'x_secret' => array(
                    'title'       => 'x-secret',
                    'type'        => 'password',
                )
            );
        }

        public function process_payment($order_id)
        {
            global $woocommerce;
            $order = wc_get_order($order_id);
            $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $customer_country = $woocommerce->customer->get_country();
            $customer_email = $order->get_billing_email();
            $customer_phone = $order->get_billing_phone();
            $amount = $order->get_total();
               if($this->testmode === 'yes'){
                    $url1 = 'https://test-api.floxypay.com/onboard/user';
                    $url2 = 'https://test-api.floxypay.com/process/payment';
               } else {
                    $url1 = 'https://api.floxypay.com/onboard/user';
                    $url2 = 'https://api.floxypay.com/process/payment';
               }
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url1,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode([
                    "name" => $customer_name, 
                    "email" => $customer_email, 
                    "country" => $customer_country, 
                    "mobile" => $customer_phone]),
                CURLOPT_HTTPHEADER => array(
                        'x-key:'. $this->x_key,
                        'x-secret:'.$this->x_secret,
                        'Content-Type: application/json'
                ),
            ));
            $response = curl_exec($curl);
            $response = json_decode($response);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($httpcode == 200) {
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $url2,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => json_encode(["userid" => $response->userid, "amount" => $amount, "orderid" =>'FLX'. $order_id.'Y'.time()]),
                    CURLOPT_HTTPHEADER => array(
                        'x-key:'. $this->x_key,
                        'x-secret:'.$this->x_secret,
                        'Content-Type: application/json'
                    ),
                ));
                $_response2 = curl_exec($curl);
                $_response2 = json_decode($_response2);
                $httpcode_2 = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                if ($httpcode_2 == 200) {
                    $environment_url = $_response2->url;
                    if($environment_url){
                        return array(
                            'result'   => 'success',
                            'redirect' => $environment_url
                        );
                    } else {
                        wc_add_notice(  'Something Went Wrong. Please try again', 'error' );
                        return;
                    }
                } else{
                    $responsefromServer = json_encode(['msg' => $_response2]);
                    $resMsg = json_decode($responsefromServer);
                    $decodedMsg = $resMsg->msg->message;
                    wc_add_notice(  $decodedMsg, 'error' );
                    return;
                } 
            }
        }

        public function webhook() {
            header( 'HTTP/1.1 200 OK' );
            $json_str = file_get_contents('php://input');
            $_ReqData = json_decode($json_str,true);
            if(!$_ReqData){
                echo "Hi There! Please Complete the Payment to see the callback";
                die();
            } else{
                 global $woocommerce;
                $gateWayOrderId = $_ReqData['orderid'];
                $merchantOrderId = trim($gateWayOrderId ,"FLX");
                $orderId = substr( $merchantOrderId, 0, -11);
                $order = wc_get_order( $orderId );
                $order->payment_complete();
                wc_reduce_stock_levels($order);            
                update_option('webhook_debug', $_ReqData);

            }

        }

    }
    
}
function floxypay_settings_link($links) { 
  $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=floxypay">Settings</a>'; 
  array_unshift($links, $settings_link); 
  return $links; 
}
$plugin = plugin_basename(__FILE__); 
add_filter("plugin_action_links_$plugin", 'floxypay_settings_link' );

