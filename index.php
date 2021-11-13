<?php
/*
*Plugin Name: Gateway for CBK Al-Tijari Knet on WooCommerce
*Plugin URI: https://github.com/alnazer/cbk-knet
*Description: The Gateway for CBK Al-Tijari Knet on WooCommerce update of the CBK (Al-Tijari) Payment gateway via woocommerce paymemt.
*Author: Hassan - hassanaliksa@gmail.com - +96590033807
*Version: 1.0.0
*Author URI: https://github.com/alnazer
*Text Domain: cbk_knet
* Domain Path: /languages
*/
/*
 * @package cbk_knet
*/

if (!defined('ABSPATH')) {
    exit();
}
    // include transactions table
    require_once plugin_dir_path(__FILE__)."transactions.php";
    require_once plugin_dir_path(__FILE__)."cbk_knet_trans_grid.php";
    require_once plugin_dir_path(__FILE__)."classes/SimpleXLSXGen.php";

    // define global variables
    define("CBK_KNET_TABLE",'cbk_knet_transactions');
    define("CBK_KNET_DV_VERSION","1.0");
    define("CBK_STATUS_SUCCESS","success");
    define("CBK_STATUS_FAIL","fail");
    define("CBK_STATUS_NEW","new");

    // initialization payment class when plugin load
    $CBK_KNET_CLASS_NAME = 'CBK_Gateway_Knet';
    add_action('plugins_loaded', 'init_cbk_knet', 0);

    // callback  plugin load
    function init_cbk_knet()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        // call transaction grid class
        CBK_KNET_Plugin::get_instance();

        // create table in data base
        if ( get_site_option('cbk_knet_db_version') != CBK_KNET_DV_VERSION ) {
            create_cbk_transactions_db_table();
        }
        /**
         *  Knet Gateway.
         *
         * Provides a VISA Payment Gateway.
         *
         * @class       CBK_Gateway_Knet
         * @extends     WC_Payment_Gateway
         *
         * @version     1.0.0
         */
        class CBK_Gateway_Knet extends WC_Payment_Gateway
        {
            public $is_test;
            public $client_allow_test;
            public $client_id;
            public $client_secret;
            public $encrp_key;
            public $currency;
            public $exchange;
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
            private $error;

            public function __construct()
            {
                $this->init_gateway();
                $this->init_form_fields();
                $this->init_settings();
                $this->initUserInformation();
                $this->currency = get_option('woocommerce_currency');
                $this->title = $this->get_option('title');
                $this->lang = $this->get_option('lang');
                $this->description = $this->get_option('description');
                $this->client_id = $this->get_option('client_id');
                $this->exchange = $this->get_option('exchange');
                $this->client_secret = $this->get_option('client_secret');
                $this->encrp_key = $this->get_option('encrp_key');
                $this->is_test = $this->get_option('is_test');
                $this->client_allow_test = $this->get_option('client_allow_test');
                if ($this->is_test == 'no') {
                    $this->GatewayUrl = 'https://pg.cbk.com';
                }
                $this->auth_url = $this->GatewayUrl.'/ePay/api/cbk/online/pg/merchant/Authenticate';
                $this->request_url = $this->GatewayUrl.'/ePay/pg/epay?_v=';
                $this->result_url = $this->GatewayUrl.'/ePay/api/cbk/online/pg/GetTransactions/';

                // class actions
                add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'process_admin_options']);
                add_filter('woocommerce_thankyou_order_received_text', [$this,'cbk_woo_change_order_received_text'] );
                add_filter( 'woocommerce_endpoint_order-received_title', [$this,'cbk_thank_you_title']);

                // add KNET payment details to thank you page
                add_action("woocommerce_order_details_before_order_table", [$this,'cbk_knet_details'],10,1);

                // add KNET payment details to to email
                add_action("woocommerce_email_after_order_table", [$this,'cbk_knet_email_details'],10,3);

                // hidden cbk gateway  for customer when on test mode (only available for administrator ,shop manager )
                add_filter('woocommerce_available_payment_gateways', [$this,'cbk_conditional_payment_gateways'], 10, 1);
            }

            /**
             * inti user information
             */
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
                $this->method_title = __('CBK (Al-Tijari) via Knet', 'cbk_knet');
                $this->method_description = __('intgration with CBK (Al-Tijari) via knet.', 'cbk_knet');
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
                        'description' => __('Place the payment gateway in test mode using test API keys. only this user roles [Shop manager,Administrator] can test payment',"cbk_knet"),
                        'default' => 'no',
                        'desc_tip' => false,
                    ],
                    'title' => [
                        'title' => __('Title', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                        'default' => __('knet', 'woocommerce'),
                        'desc_tip' => false,
                    ],
                    'description' => [
                            'title' => __('Description', 'woocommerce'),
                            'type' => 'textarea',
                            'default' => '',
                    ],
                    'exchange' => [
                        'title' => __('Currency exchange rate ', 'cbk_knet'),
                        'type' => 'number',
                        'custom_attributes' => array( 'step' => 'any', 'min' => '0' ),
                        'description' => __('It is the rate of multiplying the currency account in the event that the base currency of the store is not the Kuwaiti dinar', 'cbk_knet')." ".__('KWD = exchange rate * amount(USD)', 'cbk_knet'),
                        'default' => 1,
                        'desc_tip' => false,
                    ],
                    'client_id' => [
                        'title' => __('client Id', 'cbk_knet'),
                        'type' => 'text',
                        'description' => __('Necessary data requested from the bank', 'cbk_knet'),
                        'default' => '',
                    ],
                    'client_secret' => [
                        'title' => __('Transportal client_secret', 'cbk_knet'),
                        'type' => 'password',
                        'description' => __('Necessary data requested from the bank', 'cbk_knet'),
                        'default' => '',
                        'desc_tip' => false,
                    ],
                    'encrp_key' => [
                        'title' => __('Terminal Resource Key', 'cbk_knet'),
                        'type' => 'password',
                        'description' => __('Necessary data requested from the bank', 'cbk_knet'),
                        'default' => '',
                        'desc_tip' => false,
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
                        'desc_tip' => false,
                    ],
                ];
            }

            /**
             * Admin Panel Options
             * - Options for bits like 'title', 'description', 'alias'.
             **/
            public function admin_options()
            {
                echo '<h3>'.__('(Al-Tijari) KNET', 'cbk_knet').'</h3>';
                echo '<p>'.__('(Al-Tijari) KNET', 'cbk_knet').'</p>';
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
                    'url' => get_site_url().'/index.php?cbk_knet_redirect='.$order->get_id(),
                ];
            }

            public function getTotalAmount($order){
               if($this->currency == "KWD"){
                    return $order->get_total();
               }elseif(!empty($this->exchange) && $this->exchange > 0){
                   return $order->get_total()*$this->exchange;
               }
                return $order->get_total();

            }

            /**
             * update order after responce Done from knet
             * return string
             * url for order view.
             * @param null $error_code
             * @param $order_id
             * @param null $encrp
             * @return string
             */
            public function updateOrder($error_code = null, $order_id, $encrp = null)
            {
                WC()->session->set('cbk_session_order_id', 0);
                // define response data
                $order = new WC_Order($order_id);
                if (!$order->get_id()) {
                    wc_add_notice(__('Order not found', 'cbk_knet'), 'error');

                    return $order->get_view_order_url();
                }
                $responseData = $this->response($error_code, $encrp);
                
                if ($responseData === false && $order) {
                    wc_add_notice(__('Order Payment has error', 'cbk_knet'), 'error');
                    wc_add_notice(__($this->error, 'cbk_knet'), 'error');
                    $order->add_order_note($this->error);
                    $order->update_status('refunded');

                    return $order->get_view_order_url();
                }

                if ($responseData) {
                    $MerchUdf1 = $responseData->MerchUdf1;
                    $MerchUdf2 = $responseData->MerchUdf2;
                    $MerchUdf3 = $responseData->MerchUdf3;
                    $MerchUdf4 = $responseData->MerchUdf4;
                    $MerchUdf5 = $responseData->MerchUdf5;
                    $status = $responseData->Status;
                    $tran_id = $responseData->TransactionId;
                    $ref = $responseData->ReferenceId;
                    $payment_id = $responseData->PaymentId;
                    $track_id = $responseData->TrackId;
                    $result = $responseData->Message;
                    $PayId = $responseData->PayId;
                    $transaction_data = [
                        "payment_id"=> $payment_id,
                        "track_id"=>$track_id,
                        "tran_id"=>$tran_id,
                        "ref_id"=>$ref,
                        "pay_id"=>$PayId,
                        "result"=>$result,
                        "amount"=> $this->getTotalAmount($order),
                        'status' => (strtolower($result) == "success") ? CBK_STATUS_SUCCESS : CBK_STATUS_FAIL,
                        "data" => json_encode($responseData),
                    ];
                    if (!$order->get_id()) {
                        wc_add_notice(__('Order not found', 'cbk_knet'), 'error');
                        return $order->get_view_order_url();
                    } elseif (isset($status)) {

                        do_action("cbk_knet_create_new_transation", $order, $transaction_data);
                        $knetInfomation = '';
                        $knetInfomation .= __('Result', 'cbk_knet')."           : $result\n";
                        $knetInfomation .= __('Payment id', 'cbk_knet')."       : $payment_id\n";
                        $knetInfomation .= __('track id', 'cbk_knet')."         : $track_id\n";
                        $knetInfomation .= __('Transaction id', 'cbk_knet')."   : $tran_id\n";
                        $knetInfomation .= __('Refrance id', 'cbk_knet')."      : $ref\n";
                        $knetInfomation .= __('PayId', 'cbk_knet')."            : $PayId\n";

                        $order->add_order_note($knetInfomation);
                        switch ($status){
                            case 1:{
                                $order->update_status('completed');
                            }
                            break;
                            case 2:{
                                $order->update_status('failed');
                            }
                            break;
                            case 3:{
                                $order->update_status('cancelled');
                            }
                            default:{
                                $order->update_status('refunded');
                            }
                        }

                    }
                }

                return $this->get_return_url($order);
            }

            /**
             * get responce came from kney payment
             * return array().
             * @param null $error_code
             * @param null $encrp
             * @return array|bool|mixed
             */

            public function response($error_code = null, $encrp = null)
            {
                $result = [];
                if ($error_code) {
                    $error_code = sanitize_text_field($error_code);
                    $this->error = $this->getErrorCode($error_code);

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

            /**
             * @return bool
             */
            public function getAccessToken()
            {
                $post_fields = ['ClientId' => $this->client_id, 'ClientSecret' => $this->client_secret, 'ENCRP_KEY' => $this->encrp_key];

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
                    CURLOPT_POSTFIELDS => json_encode($post_fields),
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

            /**
             * @param $_code
             * @return mixed|string|void
             */
            public function getErrorCode($_code)
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
            /**
             * @param $str
             * @return string
             */
            public function cbk_woo_change_order_received_text($str) {
                global  $id;
                $order = $this->get_order_in_recived_page($id,true);
                $order_status = $order->get_status();
                return  sprintf("%s <b><span style=\"color:%s\">%s</span></b>.",__("Thank you. Your order has been","cbk_knet"),$this->get_status_color($order_status),__(ucfirst($order_status),"woocommerce"));
            }

            public function cbk_thank_you_title( $old_title){
                global  $id;
                $order_status = $this->get_order_in_recived_page($id);

                if ( isset ( $order_status ) ) {
                    return  sprintf( "%s , <b><span style=\"color:%s\">%s</span></b>",__('Order',"cbk_knet"),$this->get_status_color($order_status), esc_html( __(ucfirst($order_status),"woocommerce")) );
                }
                return $old_title;
            }
            /**
             * set status color
             * @param $status
             * @return string
             */
            private function get_status_color($status){
                switch ($status){
                    case "pending":
                        return "#0470fb";
                    case "processing":
                        return "#fbbd04";
                    case "on-hold":
                        return "#04c1fb";
                    case "completed":
                        return "green";
                    default:
                        return "#fb0404";
                }
            }


            /**
             * @param $page_id
             * @param bool $return_order
             * @return bool|string|WC_Order
             */
            private function get_order_in_recived_page($page_id,$return_order= false){
                global $wp;
                if ( is_order_received_page() && get_the_ID() === $page_id ) {
                    $order_id  = apply_filters( 'woocommerce_thankyou_order_id', absint( $wp->query_vars['order-received'] ) );
                    $order_key = apply_filters( 'woocommerce_thankyou_order_key', empty( $_GET['key'] ) ? '' : wc_clean( $_GET['key'] ) );
                    if ( $order_id > 0 ) {
                        $order = new WC_Order( $order_id );

                        if ( $order->get_order_key() != $order_key ) {
                            $order = false;
                        }
                        if($return_order){
                            return $order;
                        }
                        return $order->get_status();
                    }
                }
                return false;
            }



            public  function  cbk_conditional_payment_gateways($available_gateways){

                if(is_admin()){
                    return $available_gateways;
                }
                if($this->is_test == "yes"){
                    $available_gateways[$this->id]->title= $available_gateways[$this->id]->title. " <b style=\"color:red\">" .__("Test Mode","wc_knet")."</b>";
                    $wp_get_current_user = wp_get_current_user();
                    if(isset($wp_get_current_user)){
                        if(!in_array("shop_manager",$wp_get_current_user->roles) && !in_array("administrator",$wp_get_current_user->roles)){
                            unset($available_gateways[$this->id]);
                        }
                    }

                }
                return $available_gateways;
            }
            /**
             * @param $order
             */
            public function cbk_knet_details($order){
                if($order->get_payment_method() != $this->id) {
                    return;
                }
                $knet_details = cbk_get_transation_by_orderid($order->get_id());

                if(!$knet_details){
                    return;
                }
                $output = $this->format_email($order,$knet_details,"templates/knet-details.html");
                echo $output;

            }

            /**
             * @param $order
             * @param $is_admin
             * @param $text_plan
             */
            public function cbk_knet_email_details($order,$is_admin,$text_plan){
                if($order->get_payment_method() != $this->id) {
                    return;
                }
                $knet_details = cbk_get_transation_by_orderid($order->get_id());

                if (!$knet_details) {
                    return;
                }
                if ($text_plan) {
                    $output = $this->format_email($order, $knet_details, "emails/knet-text-details.html");
                } else {
                    $output = $this->format_email($order, $knet_details, "emails/knet-html-details.html");
                }
                echo $output;

            }

            /**
             * @param $order
             * @param $knet_detials
             * @param string $template
             * @return mixed
             */
            private function format_email($order,$knet_detials,$template="templates/knet-details.html")
            {

                $template = file_get_contents(plugin_dir_path(__FILE__).$template);
                $replace = [
                    "{icon}"=> plugin_dir_url(__FILE__)."assets/knet-logo.png",
                    "{title}" => __("Knet details","cbk_knet"),
                    "{payment_id}" => ($knet_detials->payment_id) ? $knet_detials->payment_id : "---",
                    "{track_id}" => ($knet_detials->track_id) ? $knet_detials->track_id : "---",
                    "{amount}" => ($knet_detials->amount) ? $knet_detials->amount : "---",
                    "{tran_id}" => ($knet_detials->tran_id) ? $knet_detials->tran_id : "---",
                    "{ref_id}" => ($knet_detials->ref_id) ? $knet_detials->ref_id : "---",
                    "{pay_id}" => ($knet_detials->pay_id) ? $knet_detials->pay_id : "---",
                    "{created_at}" => ($knet_detials->created_at) ? wp_date("F j, Y", strtotime($knet_detials->created_at) ) : "---",
                    "{result}" => sprintf("<b><span style=\"color:%s\">%s</span></b>", $this->get_status_color($order->get_status()), $knet_detials->result),
                ];
                $replace_lang = [
                    "_lang(result)" => __("Result","cbk_knet"),
                    "_lang(payment_id)" => __("Payment id","cbk_knet"),
                    "_lang(trnac_id)" => __("Transaction id","cbk_knet"),
                    "_lang(track_id)" => __("Tracking id","cbk_knet"),
                    "_lang(amount)" => __("Amount","cbk_knet"),
                    "_lang(ref_id)" => __("Refrance id","cbk_knet"),
                    "_lang(pay_id)" => __("Pay id","cbk_knet"),
                    "_lang(created_at)" => __('Created at', "cbk_knet"),
                    "{result}" => sprintf("<b><span style=\"color:%s\">%s</span></b>", $this->get_status_color($order->get_status()), $knet_detials->result),
                ];
                $replace = array_merge($replace, $replace_lang);
                return str_replace(array_keys($replace), array_values($replace), $template);
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

        function my_plugin_load_my_own_textdomain( $mofile, $domain ) {

            if ( 'cbk_knet' === $domain && false !== strpos( $mofile, WP_LANG_DIR . '/plugins/' ) ) {

                $locale = apply_filters( 'plugin_locale', determine_locale(), $domain );
                $mofile = WP_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) ) . '/languages/' . $domain . '-' . $locale . '.mo';
            }
            return $mofile;
        }
        add_filter( 'load_textdomain_mofile', 'my_plugin_load_my_own_textdomain', 10, 2 );


        add_action( 'init', 'wpdocs_load_textdomain' );

        function wpdocs_load_textdomain() {
            load_plugin_textdomain( 'cbk_knet', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        }
    /*
     * add knet responce query var
     */
    add_filter('query_vars', function ($query_vars) {
        $query_vars[] = 'cbk_knet_redirect';
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
        if (isset($request->query_vars['cbk_knet_redirect']) && null !== sanitize_text_field($request->query_vars['cbk_knet_redirect']) && sanitize_text_field($request->query_vars['cbk_knet_redirect']) > 0 && intval(sanitize_text_field($request->query_vars['cbk_knet_redirect']))) {
            $CBK_Gateway_Knet = new CBK_Gateway_Knet();
            
            $order_id = $request->query_vars['cbk_knet_redirect'];
            WC()->session->set('cbk_session_order_id', $order_id);
            $order = new WC_Order($order_id);
            $CBK_Gateway_Knet->get_access_token();

            $postdata = [
                'tij_MerchantEncryptCode' => $CBK_Gateway_Knet->encrp_key,
                'tij_MerchAuthKeyApi' => $CBK_Gateway_Knet->access_token,
                'tij_MerchantPaymentLang' => $CBK_Gateway_Knet->lang,
                'tij_MerchantPaymentAmount' => $CBK_Gateway_Knet->getTotalAmount($order),
                'tij_MerchantPaymentTrack' => uniqid(),
                'tij_MerchantPaymentRef' => date('YmdHis').rand(1, 1000),
                'tij_MerchantUdf1' => $CBK_Gateway_Knet->name,
                'tij_MerchantUdf2' => $CBK_Gateway_Knet->email,
                'tij_MerchantUdf3' => $CBK_Gateway_Knet->user_id,
                'tij_MerchantUdf4' => $order->get_id(),
                'tij_MerchantUdf5' => "",
                'tij_MerchPayType' => 1,
                'tij_MerchReturnUrl' =>  get_site_url()."/index.php",
            ];
            $inputs = "";
            foreach ($postdata as $k => $v) {
                $inputs .= "<input type='hidden' name='$k' value='$v'>";
            }
            $replace_vars = [
                "{action}" => $CBK_Gateway_Knet->request_url.$CBK_Gateway_Knet->access_token,
                "{title}" => get_bloginfo("title"),
                "{hidden_inputs}"=>$inputs,
                "{logo}"=> sprintf("<img src='%s' width='110px' />",plugin_dir_url(__FILE__)."assets/knet-logo.png"),
                "{text1}" => __("You are being taken to the payment page...","cbk_knet"),
                "{text2}" => __("You are being taken to the payment page...","cbk_knet"),
                "{submit_text}" => __("or Click here","cbk_knet"),
            ];
            $template = file_get_contents(plugin_dir_path(__FILE__)."templates/redirect_page.html");
            $template = str_replace(array_keys($replace_vars),array_values($replace_vars),$template);
            echo $template;
            die;
        }
    });

    // call to install data
    register_activation_hook( __FILE__, 'create_cbk_transactions_db_table');
    add_action("admin_init",function (){
    $action = esc_attr($_GET["cbk_knet_export"] ?? "");
    if(is_admin()){
        if(sanitize_text_field($action) == "excel"){
            $rows = cbk_knet_trans_grid::get_transations(1000);
            $list[] =[__('Order', "cbk_knet"), __('Status', "cbk_knet"), __('Result', "cbk_knet"), __('Amount', "cbk_knet"), __('Payment id', "cbk_knet"), __('Tracking id', "cbk_knet"), __('Transaction id', "cbk_knet"), __('Refrance id', "cbk_knet"), __('Pay id', "cbk_knet"), __('Created at', "cbk_knet") ];
            if($rows){
                foreach ($rows as $row){
                    $list[] = [$row['order_id'],__($row['status'],"wc_kent"),$row['result'],$row['amount'],$row['payment_id'],$row['track_id'],$row['tran_id'],$row['ref_id'],$row['pay_id'],$row['created_at']];
                }
            }
            $xlsx = SimpleXLSXGen::fromArray( $list );
            $xlsx->downloadAs(date("YmdHis").'.xlsx'); // or downloadAs('books.xlsx') or $xlsx_content = (string) $xlsx
            exit();
        }elseif (sanitize_text_field($action) == "csv"){

            $rows = cbk_knet_trans_grid::get_transations(1000);
            if($rows){
                $filename =  date('YmdHis') . ".csv";
                $f = fopen('php://memory', 'w');
                $delimiter = ",";
                $head = [__('Order', "cbk_knet"), __('Status', "cbk_knet"), __('Result', "cbk_knet"), __('Amount', "cbk_knet"), __('Payment id', "cbk_knet"), __('Tracking id', "cbk_knet"), __('Transaction id', "cbk_knet"), __('Refrance id', "cbk_knet"), __('Pay id', "cbk_knet"), __('Created at', "cbk_knet") ];
                fputcsv($f, $head,$delimiter);
                foreach ($rows as $row){
                    $listData = [$row['order_id'],__($row['status'],"wc_kent"),$row['result'],$row['amount'],$row['payment_id'],$row['track_id'],$row['tran_id'],$row['ref_id'],$row['pay_id'],$row['created_at']];
                    fputcsv($f, $listData, $delimiter);
                }
                fseek($f, 0);
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '";');
                fpassthru($f);
                exit();
            }
        }
    }

});

    /**
     * notify is currency not KWD
     */
    add_action('admin_notices', 'cbk_knet_is_curnancy_not_kwd');
    function cbk_knet_is_curnancy_not_kwd(){
        $currency = get_option('woocommerce_currency');
        if(isset($currency) && $currency != "KWD"){
            echo '<div class="notice notice-warning is-dismissible">
                 <p>'.__("currency must be KWD when using this knet payment","cbk_knet").'</p>
             </div>';
        }
    }

    if(!function_exists("dd")){
        function dd(...$data){
            echo "<pre>";
            print_r($data);
            echo "</pre>";
            die;
        }
    }
