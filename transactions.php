<?php
defined( 'ABSPATH' ) || exit;

/**
 * create transactions table
 */
if(!function_exists("alnazer_create_cbk_transactions_db_table")) {
    function alnazer_create_cbk_transactions_db_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . Alnazer_CBK_KNET_TABLE;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $sql = "CREATE TABLE  $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            order_id int(11) NOT NULL,
            payment_id  varchar(100) NOT NULL,
            track_id varchar(100) NOT NULL,
            amount DECIMAL(20,3) DEFAULT 0.000 NOT NULL,
            tran_id varchar(100)  NULL,
            ref_id varchar(100)  NULL,
            pay_id varchar(100)  NULL,
            status varchar(100) DEFAULT '" . Alnazer_CBK_STATUS_FAIL . "' NOT NULL,
            result varchar(100) DEFAULT '" . Alnazer_CBK_STATUS_NEW . "' NOT NULL,
            info text  NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            INDEX (id, order_id, payment_id, pay_id ,track_id ,amount ,tran_id, result)
        ) $charset_collate;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            add_option('cbk_knet_db_version', Alnazer_CBK_KNET_DV_VERSION);
        }

        register_uninstall_hook(__FILE__, 'alnazer_cbk_knet_uninstall');
    }
}

if(!function_exists("alnazer_cbk_knet_uninstall")) {
    function alnazer_cbk_knet_uninstall()
    {
        //  codes to perform during unistallation
        delete_option('cbk_knet_db_version');
    }
}

add_action( 'add_meta_boxes', 'alnazer_cbk_knet_details_meta_boxes');
if(!function_exists("alnazer_cbk_knet_details_meta_boxes")) {
    function alnazer_cbk_knet_details_meta_boxes()
    {
        global $post;
        if (alnazer_cbk_is_transation_exsite_by_order_id($post->ID)) {
            add_meta_box('cbk_knet_details_fields', __('Knet details', 'wc_knet'), 'alnazer_fun_cbk_knet_details_meta_boxes', 'shop_order', 'side', 'core');
        }

    }
}

/**
 *
 */
if(!function_exists("alnazer_fun_cbk_knet_details_meta_boxes")) {
    function alnazer_fun_cbk_knet_details_meta_boxes()
    {
        global $post;
        $html_allow = array(
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
            'table' => array("class"=>array(), "cellpadding"=>array(), "cellpadding"=>array(),"style"=>array()),
            'tbody' => array("class"=>array()),
            'tr' => array(),
            'h3' => array(),
            'th' => array(),
            'td' => array(),
            'b' => array(),
            'br' => array(),
            'img' => array("src"=>array(), "width"=>array(), "alt"=>array()
            ) );
        $list = alnazer_cbk_get_transation_by_orderid($post->ID);
        if ($list) {
            $output = "<table class=\"woocommerce_order_items\" cellspacing=\"2\" cellpadding=\"2\" style='width: 100% !important;'>";
            $output .= "<tbody>";
            $output .= sprintf("<tr><td style='width: 20%% !important;'><b>%s</b></td><td>%s</td></tr>", __('Result', "wc_knet"), $list->result);
            $output .= sprintf("<tr><td><b>%s</b></td><td>%s</td></tr>", __('Payment id', "cbk_knet"), $list->payment_id);
            $output .= sprintf("<tr><td><b>%s</b></td><td>%s</td></tr>", __('Tracking id', "cbk_knet"), $list->track_id);
            $output .= sprintf("<tr><td><b>%s</b></td><td>%s</td></tr>", __('Transaction id', "cbk_knet"), $list->tran_id);
            $output .= sprintf("<tr><td><b>%s</b></td><td>%s</td></tr>", __('Refrance id', "cbk_knet"), $list->ref_id);
            $output .= sprintf("<tr><td><b>%s</b></td><td>%s</td></tr>", __('Pay id', "cbk_knet"), $list->pay_id);
            $output .= sprintf("<tr><td><b>%s</b></td><td>%s</td></tr>", __('Created at', "cbk_knet"), $list->created_at);
            $output .= "</tbody>";
            $output .= "</table>";

            echo wp_kses($output, $html_allow);
        }
    }
}

/**
 * @param $payment_id
 * @return string|null
 */
if(!function_exists("alnazer_cbk_is_transation_exsite")) {
    function alnazer_cbk_is_transation_exsite($payment_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . Alnazer_CBK_KNET_TABLE;
        return $wpdb->get_var("SELECT `payment_id` FROM `$table_name` WHERE `payment_id`='$payment_id' ");
    }
}

/**
 * @param $order_id
 * @return string|null
 */
if(!function_exists("alnazer_cbk_is_transation_exsite_by_order_id")) {
    function alnazer_cbk_is_transation_exsite_by_order_id($order_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . Alnazer_CBK_KNET_TABLE;
        return $wpdb->get_var("SELECT `order_id` FROM `$table_name` WHERE `order_id`='$order_id' ");
    }
}
/**
 * @param $order_id
 * @return array|object|void|null
 */
if(!function_exists("alnazer_cbk_get_transation_by_orderid")){
    function alnazer_cbk_get_transation_by_orderid($order_id){
        global $wpdb;
        $table_name = $wpdb->prefix.Alnazer_CBK_KNET_TABLE;
        return $wpdb->get_row("SELECT * FROM `$table_name` WHERE `order_id`='$order_id' ORDER BY `id` DESC  LIMIT 1");
    }
}



add_action("cbk_knet_create_new_transation", "alnazer_do_cbk_knet_create_new_transation", 10, 2);

/**
 * @param $order
 * @param $data
 * @return bool|false|int
 */
if(!function_exists("alnazer_do_cbk_knet_create_new_transation")){
    function alnazer_do_cbk_knet_create_new_transation($order, $data){
        global $wpdb;
        $table_name = $wpdb->prefix.Alnazer_CBK_KNET_TABLE;
        try {
            if(!alnazer_cbk_is_transation_exsite($data["payment_id"])){
                return $wpdb->insert(
                    $table_name,
                    [
                        'order_id' => $order->get_id(),
                        'payment_id' => $data["payment_id"],
                        'track_id' => $data["track_id"],
                        'tran_id' => $data["tran_id"],
                        'ref_id' => $data["ref_id"],
                        'pay_id' => $data["pay_id"],
                        'status' => $data["status"],
                        'result' => $data["result"],
                        'amount'=>$data["amount"],
                        'info' => json_encode($data),
                        'created_at' => current_time( 'mysql' ),
                    ]
                );
            }
            return false;
        }catch (Exception $e){
            return false;
        }
    }
}

