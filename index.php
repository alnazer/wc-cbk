<?php
/*
*Plugin Name: CBK Payment Gateway for knet on WooCommerce
*Plugin URI: https://github.com/alnazer/woocommerce-payment-kent-v2
*Description: The new update of the CBK Payment gateway via woocommerce paymemt.
*Author: Hassan
*Version: 1.0.0
*Author URI: https://github.com/alnazer
*Text Domain: cbk_knet
* Domain Path: /languages
*/
/*
 * @package cbk knet woocommerce
*/
if (!defined('ABSPATH')) {
    exit();
}
 
    // initialization payment class when plugin load
    $CBK_KNET_CLASS_NAME = 'CBK_Gateway_Knet';
    add_action('plugins_loaded', 'init_cbk_knet', 0);

    function init_cbk_knet()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }
        /**
         *  Knet Gateway.
         *
         * Provides a VISA Payment Gateway.
         *
         * @class       CBK_Gateway_Knet
         * @extends     WC_Payment_Gateway
         *
         * @version     2.1.0
         */
        class CBK_Gateway_Knet extends WC_Payment_Gateway
        {
            public $client_id;
            public $client_secret;
            public $encrp_key;
            public $GatewayUrl = 'https://pgtest.cbk.com';
            private $auth_url = '';
            public $request_url = '';
            public $result_url = '';
            public $access_token = false;
            public $lang = 'ar';
            public $name = '';
            public $email = '';
            public $mobile = '';
            public $user_id = '';
            private $trackId;
            private $responseURL;
            private $errorURL;
            private $error;

            public function __construct()
            {
                $this->init_gateway();
                $this->init_form_fields();
                $this->init_settings();
                $this->initUserInformation();
                $this->title = $this->get_option('title');
                $this->lang = $this->get_option('lang');
                $this->description = $this->get_option('description');
                $this->client_id = $this->get_option('client_id');
                $this->client_secret = $this->get_option('client_secret');
                $this->encrp_key = $this->get_option('encrp_key');
                $this->is_test = $this->get_option('is_test');
                if ($this->is_test == 'no') {
                    $this->GatewayUrl = 'https://pg.cbk.com';
                }
                $this->auth_url = $this->GatewayUrl.'/ePay/api/cbk/online/pg/merchant/Authenticate';
                $this->request_url = $this->GatewayUrl.'/ePay/pg/epay?_v=';
                $this->result_url = $this->GatewayUrl.'/ePay/api/cbk/online/pg/GetTransactions/';
                add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'process_admin_options']);
            }
            function initUserInformation(){
                $current_user = wp_get_current_user();
                if($current_user){
                    if(empty($this->name)){
                        $this->name = $current_user->display_name;
                    }
                    if(empty($this->email)){
                        $this->email = $current_user->user_email;
                    }
                    if(empty($this->user_id)){
                        $this->user_id = $current_user->ID;
                    }
                }
            }
            /**
             * initialization gateway call default data
             * like id,icon.
             */
            public function init_gateway()
            {
                $this->id = 'cbk_knet';
                $this->icon = plugins_url('assets/knet-logo.png', __FILE__);
                $this->method_title = __('Knet', 'cbk_knet');
                $this->method_description = __('intgration with knet php raw.', 'woocommerce');
                $this->has_fields = true;
            }

            /**
             * Define Form Option fields
             * - Options for payment like 'title', 'description', 'client_id', 'client_secret', 'encrp_key'.
             **/
            public function init_form_fields()
            {
                $this->form_fields = [
                    'enabled' => [
                        'title' => __('Enable/Disable', 'woocommerce'),
                        'type' => 'checkbox',
                        'label' => __('Enable Knet Payment', 'woocommerce'),
                        'default' => 'yes',
                    ],
                    'is_test' => [
                        'title' => 'Test mode',
                        'label' => 'Enable Test Mode',
                        'type' => 'checkbox',
                        'description' => 'Place the payment gateway in test mode using test API keys.',
                        'default' => 'no',
                        'desc_tip' => true,
                    ],
                    'title' => [
                        'title' => __('Title', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                        'default' => __('knet', 'woocommerce'),
                        'desc_tip' => true,
                    ],
                    'description' => [
                            'title' => __('Description', 'woocommerce'),
                            'type' => 'textarea',
                            'default' => '',
                    ],
                    'client_id' => [
                        'title' => __('client Id', 'cbk_knet'),
                        'type' => 'text',
                        'label' => __('Necessary data requested from the bank ', 'cbk_knet'),
                        'default' => '',
                    ],
                    'client_secret' => [
                        'title' => __('Transportal client_secret', 'cbk_knet'),
                        'type' => 'password',
                        'description' => __('Necessary data requested from the bank ', 'cbk_knet'),
                        'default' => '',
                        'desc_tip' => true,
                    ],
                    'encrp_key' => [
                        'title' => __('Terminal Resource Key', 'cbk_knet'),
                        'type' => 'password',
                        'description' => __('Necessary data requested from the bank', 'cbk_knet'),
                        'default' => '',
                        'desc_tip' => true,
                    ],
                    'lang' => [
                        'title' => __('Language', 'cbk_knet'),
                        'type' => 'select',
                        'description' => __('payment page lang', 'cbk_knet'),
                        'default' => 'ar',
                        'options' => [
                            'ar' => __('Arabic'),
                            'en' => __('English'),
                        ],
                        'desc_tip' => true,
                    ],
                ];
            }

            /**
             * Admin Panel Options
             * - Options for bits like 'title', 'description', 'alias'.
             **/
            public function admin_options()
            {
                echo '<h3>'.__('Knet', 'cbk_knet').'</h3>';
                echo '<p>'.__('Knet', 'cbk_knet').'</p>';
                echo '<table class="form-table">';
                $this->generate_settings_html();
                echo '</table>';
            }

            public function get_access_token()
            {
                $response = $this->getAccessToken();
                if ($response !== false) {
                    $this->access_token = $response;
                } else {
                    wc_add_notice(__($this->error), 'error');
                    $this->access_token = false;
                }
            }

            /**
             * Process payment
             * return array
             * status,pay url
             * 1- get request data (pay url)
             * 2- Mark as on-hold (we're awaiting the cheque)
             * 3- Remove cart
             * 4- Return thankyou redirect
             * 5- or failed pay.
             */
            public function process_payment($order_id)
            {
                global $woocommerce;
                $order = new WC_Order($order_id);
                if (!$order->get_id()) {
                    wc_add_notice(__('Order not found', 'cbk_knet'), 'error');

                    return [
                        'result' => 'error',
                        'redirect' => $this->get_return_url($order),
                    ];
                }
                //get request data (pay url)
                $request = $this->request($order);
                // Mark as on-hold (we're awaiting the cheque)
                $order->update_status('on-hold', __('Awaiting cheque payment', 'woocommerce'));

                // Remove cart
                //$woocommerce->cart->empty_cart();

                if ($request) {
                    // Return thankyou redirect
                    return [
                        'result' => 'success',
                        'redirect' => $request['url'],
                    ];
                } else {
                    $order->add_order_note(__('Payment error:', 'woothemes').__("Knet can't get data", 'cbk_knet'), 'error');
                    $order->update_status('failed');

                    return [
                        'result' => 'error',
                        'redirect' => $this->get_return_url($order),
                    ];
                }
            }

            /**
             * return pay url to rediredt to knet gateway web site
             * return array.
             */
            public function request($order)
            {
                $this->initUserInformation();
                $this->get_access_token();
                if ($this->access_token == false) {
                    wc_add_notice(__("can't get assces token"), 'error');

                    return false;
                }

                return [
                    'url' => get_site_url().'/index.php?cbk_knetredirect='.$order->get_id(),
                ];
            }

            /**
             * update order after responce Done from knet
             * return string
             * url for order view.
             */
            public function updateOrder($errorcode = null, $order_id, $encrp = null)
            {
                WC()->session->set('cbk_session_order_id', 0);
                // defince rexpoce data
                $order = new WC_Order($order_id);
                if (!$order->get_id()) {
                    wc_add_notice(__('Order not found', 'cbk_knet'), 'error');

                    return $order->get_view_order_url();
                }
                $resnopseData = $this->responce($errorcode, $encrp);
                
                if ($resnopseData === false && $order) {
                    wc_add_notice(__('Order Payment has error', 'cbk_knet'), 'error');
                    wc_add_notice(__($this->error, 'cbk_knet'), 'error');
                    $order->add_order_note($this->error);
                    $order->update_status('refunded');

                    return $order->get_view_order_url();
                }

                if ($resnopseData) {
                    $MerchUdf5 = $resnopseData->MerchUdf5;
                    $status = $resnopseData->Status;
                    $tranid = $resnopseData->TransactionId;
                    $ref = $resnopseData->ReferenceId;
                    $paymentid = $resnopseData->PaymentId;
                    $trackid = $resnopseData->TrackId;
                    $result = $resnopseData->Message;
                    $PayId = $resnopseData->PayId;
            
                    if (!$order->get_id()) {
                        wc_add_notice(__('Order not found', 'cbk_knet'), 'error');
                        return $order->get_view_order_url();
                    } elseif (isset($status) && $status == 1) {

                        $knetInfomation = '';
                        $knetInfomation .= __('Result', 'woothemes')."           : $result\n";
                        $knetInfomation .= __('Payment id', 'woothemes')."       : $paymentid\n";
                        $knetInfomation .= __('track id', 'woothemes')."         : $trackid\n";
                        $knetInfomation .= __('Transaction id', 'woothemes')."   : $tranid\n";
                        $knetInfomation .= __('Refrance id', 'woothemes')."      : $ref\n";
                        $knetInfomation .= __('PayId', 'woothemes')."            : $PayId\n";
                        $order->update_status('completed');
                        $order->add_order_note($knetInfomation);

                    } elseif (isset($status) && $status != 1) {
                        $knetInfomation = '';
                        $knetInfomation .= __('Result', 'woothemes')."           : $result\n";
                        $knetInfomation .= __('Payment id', 'woothemes')."       : $paymentid\n";
                        $knetInfomation .= __('track id', 'woothemes')."         : $trackid\n";
                        $knetInfomation .= __('Transaction id', 'woothemes')."   : $tranid\n";
                        $knetInfomation .= __('Refrance id', 'woothemes')."      : $ref\n";
                        $knetInfomation .= __('PayId', 'woothemes')."            : $PayId\n";

                        $order->add_order_note($knetInfomation);
                        if($status == 3){
                            $order->update_status('cancelled');
                        }
                        elseif($status == 2){
                            $order->update_status('failed');
                        }
                        else
                        {
                            $order->update_status('refunded');
                        }
                        
                    }
                }

                return $this->get_return_url($order);
            }

            /**
             * get responce came from kney payment
             * return array().
             */
            public function responce($errorcode = null, $encrp = null)
            {
                $result = [];
                if ($errorcode) {
                    $errorcode = sanitize_text_field($errorcode);
                    $this->error = $this->getErrorcode($errorcode);

                    return false;
                }
                if (!$encrp) {
                    $encrp = sanitize_text_field($encrp);
                }

                $this->get_access_token();
                $this->result_url = $this->result_url.$encrp.'/'.$this->access_token;
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $this->result_url,
                    CURLOPT_ENCODING => "",
                    CURLOPT_FOLLOWLOCATION => 1,
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "GET",
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_HTTPHEADER => array(
                        'Authorization: Basic ' .base64_encode($this->client_id. ":" . $this->client_secret),
                        "Content-Type: application/json",
                        "cache-control: no-cache"
                    ),
                ));

                $response = curl_exec($curl);
                $this->error = curl_error($curl);
                curl_close($curl);                
                if ($response) {
                    if($response){
                        $result = json_decode($response);
                    }

                }

                return  $result;
            }

            public function getAccessToken()
            {
                $postfield = ['ClientId' => $this->client_id, 'ClientSecret' => $this->client_secret, 'ENCRP_KEY' => $this->encrp_key];

                $curl = curl_init();

                curl_setopt_array($curl, [
                CURLOPT_URL => $this->auth_url,
                CURLOPT_ENCODING => '',
                CURLOPT_FOLLOWLOCATION => 1,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_POSTFIELDS => json_encode($postfield),
                CURLOPT_HTTPHEADER => [
                        'Authorization: Basic '.base64_encode($this->client_id.':'.$this->client_secret),
                        'Content-Type: application/json',
                        'cache-control: no-cache',
                    ],
                ]);

                $response = curl_exec($curl);
                $this->error = curl_error($curl);

                curl_close($curl);

                $authenticateData = json_decode($response);

                if ($authenticateData->Status == '1') {
                    //save access token till expiry
                    return $authenticateData->AccessToken;
                } else {
                    return false;
                }
            }

            public function getErrorcode($_code)
            {
                $code['TIJ0001'] = __('Invalid Merchant Language', 'cbk_knet');
                $code['TIJ0002'] = __('Invalid Merchant Amount ', 'cbk_knet');
                $code['TIJ0003'] = __('Invalid Merchant Amount KWD ', 'cbk_knet');
                $code['TIJ0004'] = __('Invalid Merchant Track ID ', 'cbk_knet');
                $code['TIJ0005'] = __('Invalid Merchant UDF1 ', 'cbk_knet');
                $code['TIJ0006'] = __('Invalid Merchant Currency  ', 'cbk_knet');
                $code['TIJ0007'] = __('Invalid Merchant Payment reference ', 'cbk_knet');
                $code['TIJ0008'] = __('Invalid Merchant Pay Type ', 'cbk_knet');
                $code['TIJ0009'] = __('Invalid Merchant API Authenticate Key ', 'cbk_knet');
                $code['TIJ0010'] = __('Invalid Access ', 'cbk_knet');
                $code['TIJ0011'] = __('Invalid Merchant Key ', 'cbk_knet');
                $code['TIJ0012'] = __('Duplicate Merchant Track ID  ', 'cbk_knet');
                $code['TIJ0013'] = __('Invalid Merchant Key (not match) ', 'cbk_knet');
                $code['TIJ0014'] = __('Invalid Merchant Key (not available) ', 'cbk_knet');
                $code['TIJ0015'] = __('Invalid Merchant UDF2 ', 'cbk_knet');
                $code['TIJ0016'] = __('Error in QR ', 'cbk_knet');
                $code['TIJ0017'] = __('Invalid Page Access ', 'cbk_knet');
                $code['TIJ0019'] = __('Invalid KNET/QR Data ', 'cbk_knet');
                $code['TIJ0020'] = __('Error in KNET ', 'cbk_knet');
                $code['TIJ0021'] = __('Error Processing Data ', 'cbk_knet');
                $code['TIJ0022'] = __('Invalid Merchant UDF3 ', 'cbk_knet');
                $code['TIJ0023'] = __('Invalid Merchant UDF4 ', 'cbk_knet');
                $code['TIJ0024'] = __('Invalid Merchant UDF5 ', 'cbk_knet');
                $code['TIJ0027'] = __('Invalid Merchant Return URL ', 'cbk_knet');
                $code['TIJ0031'] = __('Transaction session expired ', 'cbk_knet');
                if (isset($code[$_code])) {
                    return $code[$_code];
                }

                return $_code;
            }
        }
    }

    /**
     * Add the Gateway to WooCommerce.
     **/
    function woocommerce_add_cbk_knet_gateway($methods)
    {
        global $CBK_KNET_CLASS_NAME;
        $methods[] = $CBK_KNET_CLASS_NAME;

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_cbk_knet_gateway');

    function cbk_knet_load_languages_textdomain($mofile, $domain)
    {
        if ('cbk_knet' === $domain && false !== strpos($mofile, WP_LANG_DIR.'/plugins/')) {
            $locale = apply_filters('plugin_locale', determine_locale(), $domain);
            $mofile = WP_PLUGIN_DIR.'/'.dirname(plugin_basename(__FILE__)).'/languages/'.$domain.'-'.$locale.'.mo';
        }

        return $mofile;
    }
    add_filter('load_textdomain_mofile', 'cbk_knet_load_languages_textdomain', 10, 2);

    /*
     * add knet responce query var
     */
    add_filter('query_vars', function ($query_vars) {
        $query_vars[] = 'cbk_knetredirect';
        $query_vars[] = 'ErrorCode';
        $query_vars[] = 'encrp';

        return $query_vars;
    });
    /*
     * define knet responce
     */
    add_action('wp', function ($request) {
        if (isset($request->query_vars['ErrorCode']) && null !== sanitize_text_field($request->query_vars['ErrorCode'])) {
            
            $CBK_Gateway_Knet = new CBK_Gateway_Knet();
            $ErrorCode = $request->query_vars['ErrorCode'];
            $order_id = WC()->session->get('cbk_session_order_id');
            $url = $CBK_Gateway_Knet->updateOrder($ErrorCode, $order_id);
            if (wp_redirect($url)) {
                exit;
            }
        }
        if (isset($request->query_vars['encrp']) && null !== sanitize_text_field($request->query_vars['encrp'])) {
           
            $CBK_Gateway_Knet = new CBK_Gateway_Knet();
            $order_id = WC()->session->get('cbk_session_order_id');
     
            $url = $CBK_Gateway_Knet->updateOrder(null, $order_id,$request->query_vars['encrp']);
            if (wp_redirect($url)) {
                exit;
            }
        }
        if (isset($request->query_vars['cbk_knetredirect']) && null !== sanitize_text_field($request->query_vars['cbk_knetredirect']) && sanitize_text_field($request->query_vars['cbk_knetredirect']) > 0 && intval(sanitize_text_field($request->query_vars['cbk_knetredirect']))) {
            $CBK_Gateway_Knet = new CBK_Gateway_Knet();
            
            $order_id = $request->query_vars['cbk_knetredirect'];
            WC()->session->set('cbk_session_order_id', $order_id);
            $order = new WC_Order($order_id);
            $CBK_Gateway_Knet->get_access_token();

            $postdata = [
                'tij_MerchantEncryptCode' => $CBK_Gateway_Knet->encrp_key,
                'tij_MerchAuthKeyApi' => $CBK_Gateway_Knet->access_token,
                'tij_MerchantPaymentLang' => $CBK_Gateway_Knet->lang,
                'tij_MerchantPaymentAmount' => $order->get_total(),
                'tij_MerchantPaymentTrack' => uniqid(),
                'tij_MerchantPaymentRef' => date('YmdHis').rand(1, 1000),
                'tij_MerchantUdf1' => "",
                'tij_MerchantUdf2' => "",
                'tij_MerchantUdf3' => "",
                'tij_MerchantUdf4' => $CBK_Gateway_Knet->user_id,
                'tij_MerchantUdf5' => $order->get_id(),
                'tij_MerchPayType' => 1,
                'tij_MerchReturnUrl' =>  get_site_url()."/index.php",
            ];
   
         
            $form = "<form id='pgForm' method='post' action='$CBK_Gateway_Knet->request_url$CBK_Gateway_Knet->access_token' enctype='application/x-www-form-urlencoded'>";
            foreach ($postdata as $k => $v) {
                $form .= "<input type='hidden' name='$k' value='$v'>";
            }
            $form .= '</form>';
           
            $form .= "<div style='position: fixed;top: 50%;left: 50%;transform: translate(-50%, -50%);text-align:center'>جاري عملية نقلك الي صفحة الدفع ... <br> <b> لا تقم بعمل تحديث للصفحة</b></div>";
            $form .= "<script type='text/javascript'>";
            $form .= "document.getElementById('pgForm').submit();";
            $form .= '</script>';
            echo $form;
            die;
        }
    });
    if(!function_exists("pr")){
        function dd($data){
            echo "<pre>";
            print_r($data);
            echo "</pre>";
            die;
        }
    }
