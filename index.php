<?php
/*
*Plugin Name: Gateway for CBK Al-Tijari Knet
*Plugin URI: https://github.com/alnazer/cbk-knet
*Description: The Gateway for CBK Al-Tijari Knet on WooCommerce update of the CBK (Al-Tijari) Payment gateway via woocommerce paymemt.
*Author: alnazer
*Version: 1.0.0
*Author URI: https://github.com/alnazer
*Text Domain: wc-cbk
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
    define("Alnazer_CBK_KNET_TABLE",'cbk_knet_transactions');
    define("Alnazer_CBK_KNET_DV_VERSION","1.0");
    define("Alnazer_CBK_STATUS_SUCCESS","success");
    define("Alnazer_CBK_STATUS_FAIL","fail");
    define("Alnazer_CBK_STATUS_NEW","new");

    // initialization payment class when plugin load
    $Alnazer_CBK_KNET_CLASS_NAME = 'Alnazer_CBK_Gateway_KNET';
    add_action('plugins_loaded', 'alnazer_init_cbk_knet', 0);

    // callback  plugin load
if(!function_exists("alnazer_init_cbk_knet")){
    function alnazer_init_cbk_knet()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        // call transaction grid class
        Alnazer_CBK_KNET_Plugin::get_instance();

        // create table in data base
        if ( get_site_option('cbk_knet_db_version') != Alnazer_CBK_KNET_DV_VERSION ) {
            alnazer_create_cbk_transactions_db_table();
        }
        /**
         *  KNET Gateway.
         *
         * Provides a KNET Payment Gateway.
         *
         * @class       CBK_Gateway_Knet
         * @extends     WC_Payment_Gateway
         *
         * @version     1.0.0
         */
        class Alnazer_CBK_Gateway_KNET extends WC_Payment_Gateway
        {
            public $is_test;
            public $client_allow_test;
            public $client_id;
            public $client_secret;
            public $encamp_key;
            public $currency;
            public $exchange;
            public $GatewayUrl = 'https://pgtest.cbk.com';
            private $auth_url;
            public $request_url = '';
            public $result_url = '';
            public $access_token = false;
            public $lang = 'ar';
            public $name = '';
            public $email = '';
            public $mobile = '';
            public $user_id = '';
            private $error;

            public $html_allow = array(
                "meta"=>["charset"=>[]],
                "script"=>["type"=>[]],
                "body"=>[],
                "html"=>[],
                "style"=>[],
                "head"=>[],
                "title"=>[],
                "button"=>["type"=>[],"class"=>[],"id"=>[]],
                "input"=>["type"=>[],"name"=>[],"value"=>[]],
                "form"=>["id"=>[], "method"=>[], "action"=>[], "enctype"=>[]],
                'div'=>["class"=>[],"style"=>[]],
                'h2' => array("class"=>array(),"style"=>array()),
                "span"=>array("style"=>array()),
                'table' => array("class"=>array()),
                'tr' => array(),
                'h3' => array(),
                'th' => array(),
                'td' => array(),
                'b' => array(),
                'br' => array(),
                'img' => array("src"=>array(), "width"=>array(), "alt"=>array()
            ) );

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
                $this->encamp_key = $this->get_option('encrp_key');
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
                $this->method_title = __('CBK (Al-Tijari) via Knet', 'wc-cbk');
                $this->method_description = __('intgration with CBK (Al-Tijari) via knet.', 'wc-cbk');
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
                        'title' => __('Currency exchange rate ', 'wc-cbk'),
                        'type' => 'number',
                        'custom_attributes' => array( 'step' => 'any', 'min' => '0' ),
                        'description' => __('It is the rate of multiplying the currency account in the event that the base currency of the store is not the Kuwaiti dinar', 'wc-cbk')." ".__('KWD = exchange rate * amount(USD)', 'wc-cbk'),
                        'default' => 1,
                        'desc_tip' => false,
                    ],
                    'client_id' => [
                        'title' => __('client Id', 'wc-cbk'),
                        'type' => 'text',
                        'description' => __('Necessary data requested from the bank', 'wc-cbk'),
                        'default' => '',
                    ],
                    'client_secret' => [
                        'title' => __('Transportal client_secret', 'wc-cbk'),
                        'type' => 'password',
                        'description' => __('Necessary data requested from the bank', 'wc-cbk'),
                        'default' => '',
                        'desc_tip' => false,
                    ],
                    'encrp_key' => [
                        'title' => __('Terminal Resource Key', 'wc-cbk'),
                        'type' => 'password',
                        'description' => __('Necessary data requested from the bank', 'wc-cbk'),
                        'default' => '',
                        'desc_tip' => false,
                    ],
                    'lang' => [
                        'title' => __('Language', 'wc-cbk'),
                        'type' => 'select',
                        'description' => __('payment page lang', 'wc-cbk'),
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
                echo wp_kses(sprintf("<h3>%s</h3>", __('(Al-Tijari) KNET', 'wc-cbk')),$this->html_allow);
                echo wp_kses(sprintf("<p>%s</p>", __('(Al-Tijari) KNET', 'wc-cbk')),$this->html_allow);
                echo wp_kses('<table class="form-table">',$this->html_allow);
                $this->generate_settings_html();
                echo wp_kses('</table>',$this->html_allow);
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
             * 3- Return thank you redirect
             * 4- or failed pay.
             * @param $order_id
             * @return array
             */
            public function process_payment($order_id)
            {

                $order = new WC_Order($order_id);
                if (!$order->get_id()) {
                    wc_add_notice(__('Order not found', 'wc-cbk'), 'error');

                    return [
                        'result' => 'error',
                        'redirect' => $this->get_return_url($order),
                    ];
                }
                //get request data (pay url)
                $request = $this->request($order);
                // Mark as on-hold (we're awaiting the cheque)
                $order->update_status('on-hold', __('Awaiting cheque payment', 'woocommerce'));


                if ($request) {
                    // Return thank you redirect
                    return [
                        'result' => 'success',
                        'redirect' => $request['url'],
                    ];
                } else {
                    $order->add_order_note(__('Payment error:', 'woothemes').__("Knet can't get data", 'wc-cbk'), 'error');
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
             * @param $order
             * @return bool|string[]
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

            /**
             * @param $order
             * @return float|int
             */
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
             * @param null $encamp
             * @return string
             */
            public function updateOrder($error_code, $order_id, $encamp = null)
            {
                WC()->session->set('cbk_session_order_id', 0);
                // define response data
                $order = new WC_Order($order_id);
                if (!$order->get_id()) {
                    wc_add_notice(__('Order not found', 'wc-cbk'), 'error');

                    return $order->get_view_order_url();
                }
                $responseData = $this->response($error_code, $encamp);

                if ($responseData === false && $order) {
                    wc_add_notice(__('Order Payment has error', 'wc-cbk'), 'error');
                    wc_add_notice(__($this->error, 'wc-cbk'), 'error');
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
                        'status' => (strtolower($result) == "success") ? Alnazer_CBK_STATUS_SUCCESS : Alnazer_CBK_STATUS_FAIL,
                        "data" => json_encode($responseData),
                    ];
                    if (!$order->get_id()) {
                        wc_add_notice(__('Order not found', 'wc-cbk'), 'error');
                        return $order->get_view_order_url();
                    } elseif (isset($status)) {

                        do_action("cbk_knet_create_new_transation", $order, $transaction_data);
                        $knetInfomation = '';
                        $knetInfomation .= __('Result', 'wc-cbk')."           : $result\n";
                        $knetInfomation .= __('Payment id', 'wc-cbk')."       : $payment_id\n";
                        $knetInfomation .= __('track id', 'wc-cbk')."         : $track_id\n";
                        $knetInfomation .= __('Transaction id', 'wc-cbk')."   : $tran_id\n";
                        $knetInfomation .= __('Refrance id', 'wc-cbk')."      : $ref\n";
                        $knetInfomation .= __('PayId', 'wc-cbk')."            : $PayId\n";

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
             * get response came from KNET payment
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

                $args = array(
                    'method'      => 'GET',
                    'timeout'     => 30,
                    'httpversion' => CURL_HTTP_VERSION_1_1,
                    'headers'     => [
                        'Authorization' => 'Basic '.base64_encode($this->client_id. ":" . $this->client_secret),
                        'Content-Type' => 'application/json',
                        'cache-control' => 'no-cache',
                    ],
                );
                $response = wp_remote_get($this->result_url, $args );
                $response_code = wp_remote_retrieve_response_code( $response );
                $response_body = wp_remote_retrieve_body( $response);
                if ( is_wp_error( $response ) ) {
                    $this->error = $response->get_error_message();
                }
                return json_decode($response_body);
                
            }

            /**
             * @return bool
             */
            public function getAccessToken()
            {
                $post_fields = ['ClientId' => $this->client_id, 'ClientSecret' => $this->client_secret, 'ENCRP_KEY' => $this->encamp_key];
                $args = array(
                    'method'      => 'POST',
                    'body'        =>  wp_json_encode($post_fields),
                    'timeout'     => 30,
                    'httpversion' => CURL_HTTP_VERSION_2TLS,
                    'headers'     => [
                        'Authorization' => 'Basic '.base64_encode($this->client_id.':'.$this->client_secret),
                        'Content-Type' => 'application/json',
                        'cache-control' => 'no-cache',
                    ],
                );
                $response = wp_remote_post( $this->auth_url, $args );
                $response_code = wp_remote_retrieve_response_code( $response );
                $response_body = wp_remote_retrieve_body( $response);
                if(!empty($response_body) && $response_code == 200){
                    $jsonData = json_decode($response_body);
                    if($jsonData->Status == 1){
                        return  $jsonData->AccessToken;
                    }
                }
                return false;

            }

            /**
             * @param $_code
             * @return mixed|string|void
             */
            public function getErrorCode($_code)
            {
                $code['TIJ0001'] = __('Invalid Merchant Language', 'wc-cbk');
                $code['TIJ0002'] = __('Invalid Merchant Amount ', 'wc-cbk');
                $code['TIJ0003'] = __('Invalid Merchant Amount KWD ', 'wc-cbk');
                $code['TIJ0004'] = __('Invalid Merchant Track ID ', 'wc-cbk');
                $code['TIJ0005'] = __('Invalid Merchant UDF1 ', 'wc-cbk');
                $code['TIJ0006'] = __('Invalid Merchant Currency  ', 'wc-cbk');
                $code['TIJ0007'] = __('Invalid Merchant Payment reference ', 'wc-cbk');
                $code['TIJ0008'] = __('Invalid Merchant Pay Type ', 'wc-cbk');
                $code['TIJ0009'] = __('Invalid Merchant API Authenticate Key ', 'wc-cbk');
                $code['TIJ0010'] = __('Invalid Access ', 'wc-cbk');
                $code['TIJ0011'] = __('Invalid Merchant Key ', 'wc-cbk');
                $code['TIJ0012'] = __('Duplicate Merchant Track ID  ', 'wc-cbk');
                $code['TIJ0013'] = __('Invalid Merchant Key (not match) ', 'wc-cbk');
                $code['TIJ0014'] = __('Invalid Merchant Key (not available) ', 'wc-cbk');
                $code['TIJ0015'] = __('Invalid Merchant UDF2 ', 'wc-cbk');
                $code['TIJ0016'] = __('Error in QR ', 'wc-cbk');
                $code['TIJ0017'] = __('Invalid Page Access ', 'wc-cbk');
                $code['TIJ0019'] = __('Invalid KNET/QR Data ', 'wc-cbk');
                $code['TIJ0020'] = __('Error in KNET ', 'wc-cbk');
                $code['TIJ0021'] = __('Error Processing Data ', 'wc-cbk');
                $code['TIJ0022'] = __('Invalid Merchant UDF3 ', 'wc-cbk');
                $code['TIJ0023'] = __('Invalid Merchant UDF4 ', 'wc-cbk');
                $code['TIJ0024'] = __('Invalid Merchant UDF5 ', 'wc-cbk');
                $code['TIJ0027'] = __('Invalid Merchant Return URL ', 'wc-cbk');
                $code['TIJ0031'] = __('Transaction session expired ', 'wc-cbk');
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
                $order = $this->get_order_in_received_page($id,true);
                $order_status = $order->get_status();
                return  sprintf("%s <b><span style=\"color:%s\">%s</span></b>.",__("Thank you. Your order has been","cbk_knet"),$this->get_status_color($order_status),__(ucfirst($order_status),"woocommerce"));
            }

            public function cbk_thank_you_title( $old_title){
                global  $id;
                $order_status = $this->get_order_in_received_page($id);

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
            private function get_order_in_received_page($page_id, $return_order= false){
                global $wp;
                if ( is_order_received_page() && get_the_ID() === $page_id ) {
                    $order_id  = apply_filters( 'woocommerce_thankyou_order_id', absint( $wp->query_vars['order-received'] ) );
                    $order_key = apply_filters( 'woocommerce_thankyou_order_key', empty( sanitize_text_field($_GET['key'])  ) ? '' : wc_clean( sanitize_text_field($_GET['key']) ) );
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


            /**
             * @param $available_gateways
             * @return mixed
             */
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
                $knet_details = alnazer_cbk_get_transation_by_orderid($order->get_id());

                if(!$knet_details){
                    return;
                }
                $output = $this->format_html_output($order,$knet_details,"templates/knet-details.html");
                echo wp_kses($output,$this->html_allow) ;

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
                $knet_details = alnazer_cbk_get_transation_by_orderid($order->get_id());

                if (!$knet_details) {
                    return;
                }
                if ($text_plan) {
                    $output = $this->format_html_output($order, $knet_details, "emails/knet-text-details.html");
                } else {
                    $output = $this->format_html_output($order, $knet_details, "emails/knet-html-details.html");
                }
                echo wp_kses($output,$this->html_allow) ;

            }

            /**
             * @param $order
             * @param $knet_details
             * @param string $template
             * @return mixed
             */
            private function format_html_output($order, $knet_details, $template="templates/knet-details.html")
            {

                $template = file_get_contents(plugin_dir_path(__FILE__).$template);
                $replace = [
                    "{icon}"=> plugin_dir_url(__FILE__)."assets/knet-logo.png",
                    "{title}" => __("Knet details","cbk_knet"),
                    "{payment_id}" => ($knet_details->payment_id) ? $knet_details->payment_id : "---",
                    "{track_id}" => ($knet_details->track_id) ? $knet_details->track_id : "---",
                    "{amount}" => ($knet_details->amount) ? $knet_details->amount : "---",
                    "{tran_id}" => ($knet_details->tran_id) ? $knet_details->tran_id : "---",
                    "{ref_id}" => ($knet_details->ref_id) ? $knet_details->ref_id : "---",
                    "{pay_id}" => ($knet_details->pay_id) ? $knet_details->pay_id : "---",
                    "{created_at}" => ($knet_details->created_at) ? wp_date("F j, Y", strtotime($knet_details->created_at) ) : "---",
                    "{result}" => sprintf("<b><span style=\"color:%s\">%s</span></b>", $this->get_status_color($order->get_status()), $knet_details->result),
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
                    "{result}" => sprintf("<b><span style=\"color:%s\">%s</span></b>", $this->get_status_color($order->get_status()), $knet_details->result),
                ];
                $replace = array_merge($replace, $replace_lang);
                return str_replace(array_keys($replace), array_values($replace), $template);
            }
        }
    }

}

    /**
     * Add the Gateway to WooCommerce.
     * @param $methods
     * @return mixed
     */
    if(!function_exists("alnazer_woocommerce_add_cbk_knet_gateway")){
        function alnazer_woocommerce_add_cbk_knet_gateway($methods)
        {
            global $Alnazer_CBK_KNET_CLASS_NAME;
            $methods[] = $Alnazer_CBK_KNET_CLASS_NAME;
            return $methods;
        }
    }


        add_filter('woocommerce_payment_gateways', 'alnazer_woocommerce_add_cbk_knet_gateway');

        if(!function_exists("alnazer_cbk_knet_load_my_own_text_domain")){
            function alnazer_cbk_knet_load_my_own_text_domain($mo_file, $domain ) {

                if ( 'wc-cbk' === $domain && false !== strpos( $mo_file, WP_LANG_DIR . '/plugins/' ) ) {

                    $locale = apply_filters( 'plugin_locale', determine_locale(), $domain );
                    $mo_file = WP_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) ) . '/languages/' . $domain . '-' . $locale . '.mo';
                }
                return $mo_file;
            }
        }

        add_filter( 'load_textdomain_mofile', 'alnazer_cbk_knet_load_my_own_text_domain', 10, 2 );


        add_action( 'init', 'alnazer_cbk_knet_load_text_domain');
        if(!function_exists("alnazer_cbk_knet_load_text_domain"))
        {
            function alnazer_cbk_knet_load_text_domain() {
                load_plugin_textdomain( 'wc-cbk', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
            }
        }

    /*
     * add knet response query var
     */
    add_filter('query_vars', function ($query_vars) {
        $query_vars[] = 'cbk_knet_redirect';
        $query_vars[] = 'ErrorCode';
        $query_vars[] = 'encrp';

        return $query_vars;
    });
    /*
     * define knet response
     */
    add_action('wp', function ($request) {
        if (isset($request->query_vars['ErrorCode']) && null !== sanitize_text_field($request->query_vars['ErrorCode'])) {
            
            $CBK_Gateway_Knet = new Alnazer_CBK_Gateway_KNET();
            $ErrorCode = $request->query_vars['ErrorCode'];
            $order_id = WC()->session->get('cbk_session_order_id');
            $url = $CBK_Gateway_Knet->updateOrder($ErrorCode, $order_id);
            if (wp_redirect($url)) {
                exit;
            }
        }
        if (isset($request->query_vars['encrp']) && null !== sanitize_text_field($request->query_vars['encrp'])) {
           
            $CBK_Gateway_Knet = new Alnazer_CBK_Gateway_KNET();
            $order_id = WC()->session->get('cbk_session_order_id');
     
            $url = $CBK_Gateway_Knet->updateOrder(null, $order_id,$request->query_vars['encrp']);
            if (wp_redirect($url)) {
                exit;
            }
        }
        if (isset($request->query_vars['cbk_knet_redirect']) && null !== sanitize_text_field($request->query_vars['cbk_knet_redirect']) && sanitize_text_field($request->query_vars['cbk_knet_redirect']) > 0 && intval(sanitize_text_field($request->query_vars['cbk_knet_redirect']))) {
            $CBK_Gateway_Knet = new Alnazer_CBK_Gateway_KNET();
            
            $order_id = $request->query_vars['cbk_knet_redirect'];
            WC()->session->set('cbk_session_order_id', $order_id);
            $order = new WC_Order($order_id);
            $CBK_Gateway_Knet->get_access_token();

            $postdata = [
                'tij_MerchantEncryptCode' => $CBK_Gateway_Knet->encamp_key,
                'tij_MerchAuthKeyApi' => $CBK_Gateway_Knet->access_token,
                'tij_MerchantPaymentLang' => $CBK_Gateway_Knet->lang,
                'tij_MerchantPaymentAmount' => $CBK_Gateway_Knet->getTotalAmount($order),
                'tij_MerchantPaymentTrack' => uniqid(),
                'tij_MerchantPaymentRef' => date('YmdHis').rand(1, 1000),
                'tij_MerchantUdf1' => sanitize_text_field($CBK_Gateway_Knet->name) ?? "",
                'tij_MerchantUdf2' => "",
                'tij_MerchantUdf3' => $CBK_Gateway_Knet->user_id ?? "",
                'tij_MerchantUdf4' => $order->get_id(),
                'tij_MerchantUdf5' => "",
                'tij_MerchPayType' => 1,
                'tij_MerchReturnUrl' =>  get_site_url()."/index.php",
            ];

            $inputs = "";
            foreach ($postdata as $k => $v) {
                $inputs .= sprintf("<input type='hidden' name='%s' value='%s'>",sanitize_text_field($k), sanitize_text_field($v));
            }
            $replace_vars = [
                "{action}" => $CBK_Gateway_Knet->request_url.$CBK_Gateway_Knet->access_token,
                "{title}" => get_bloginfo("title"),
                "{hidden_inputs}"=>$inputs,
                "{logo}"=> sprintf("<img src='%s' width='110px'  alt='%s'/>",plugin_dir_url(__FILE__)."assets/knet-logo.png",get_bloginfo("title")),
                "{text1}" => __("You are being taken to the payment page...","cbk_knet"),
                "{text2}" => __("dont't refresh the page...","cbk_knet"),
                "{text3}" => __("جاري إعادة توجيهك الص ثفحة الدفع لاتقم باعادة تحديث الصفحة","cbk_knet"),
                "{submit_text}" => __("or Click here","cbk_knet"),
            ];
            $template = file_get_contents(plugin_dir_path(__FILE__)."templates/redirect_page.html");
            $template = str_replace(array_keys($replace_vars),array_values($replace_vars),$template);
            echo wp_kses($template,$CBK_Gateway_Knet->html_allow);
            die;
        }
    });

    // call to install data
    register_activation_hook( __FILE__, 'alnazer_create_cbk_transactions_db_table');

    add_action("admin_init",function (){
        $action = (isset($_GET["cbk_knet_export"])) ? sanitize_text_field($_GET["cbk_knet_export"]) : "";
        if($action){
            $action = sanitize_text_field($action);
        }

        if(is_admin()){
            if(sanitize_text_field($action) == "excel"){
                $rows = Alnazer_Cbk_KNET_Trans_Grid::get_transations(1000);
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

                $rows = Alnazer_Cbk_KNET_Trans_Grid::get_transations(1000);
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
    add_action('admin_notices', 'alnazer_cbk_knet_is_currency_not_kwd');
    if(!function_exists("alnazer_cbk_knet_is_currency_not_kwd")){
        function alnazer_cbk_knet_is_currency_not_kwd(){
            $currency = get_option('woocommerce_currency');
            if(isset($currency) && $currency != "KWD"){
                echo '<div class="notice notice-warning is-dismissible">
                 <p>'.__("currency must be KWD when using this knet payment","cbk_knet").'</p>
             </div>';
            }
        }
    }


    /**
     * die and dump
     */
    if(!function_exists("dd")){
        function dd(...$data){
            echo "<pre>";
            print_r($data);
            echo "</pre>";
            die;
        }
    }
