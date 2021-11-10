<?php
defined( 'ABSPATH' ) || exit;
if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class cbk_knet_trans_grid extends WP_List_Table
{
    public $table;
    public $db;
    public function __construct($args = array())
    {
        global $wpdb;
        parent::__construct( [
            'singular' => __( 'CBK_KNET_List', "cbk_knet" ), //singular name of the listed records
            'plural' => __( 'CBK_KNET_List', "cbk_knet" ), //plural name of the listed records
            'ajax' => false //should this table support ajax?

        ]);
        $this->db = $wpdb;
        $this->table = $this->db->prefix.CBK_KNET_TABLE;
    }


    private function filter_query(){
        $query = "";
        if(isset($_REQUEST["order_id"]) && !empty($_REQUEST["order_id"])){
            $order_id = sanitize_text_field($_REQUEST["order_id"]);
            $query .=" AND `order_id` = $order_id";
        }
        if(isset($_REQUEST["status"]) && !empty($_REQUEST["status"])){
            $status = sanitize_text_field($_REQUEST["status"]);
            $query .=" AND `status` = '$status'";
        }
        if(isset($_REQUEST["result"]) && !empty($_REQUEST["result"])){
            $result = sanitize_text_field($_REQUEST["result"]);
            $query .=" AND `result` LIKE '%$result%'";
        }
        if(isset($_REQUEST["amount"]) && !empty($_REQUEST["amount"])){
            $amount = sanitize_text_field($_REQUEST["amount"]);
            $query .=" AND `amount` = $amount";
        }
        if(isset($_REQUEST["payment_id"]) && !empty($_REQUEST["payment_id"])){
            $payment_id = sanitize_text_field($_REQUEST["payment_id"]);
            $query .=" AND `payment_id` = $payment_id";
        }
        if(isset($_REQUEST["track_id"]) && !empty($_REQUEST["track_id"])){
            $track_id = sanitize_text_field($_REQUEST["track_id"]);
            $query .=" AND `track_id` = $track_id";
        }
        if(isset($_REQUEST["tran_id"]) && !empty($_REQUEST["tran_id"])){
            $tran_id = sanitize_text_field($_REQUEST["tran_id"]);
            $query .=" AND `tran_id` = $tran_id";
        }
        if(isset($_REQUEST["ref_id"]) && !empty($_REQUEST["ref_id"])){
            $ref_id = sanitize_text_field($_REQUEST["ref_id"]);
            $query .=" AND `ref_id` = $ref_id";
        }
        if(isset($_REQUEST["pay_id"]) && !empty($_REQUEST["pay_id"])){
            $pay_id = sanitize_text_field($_REQUEST["pay_id"]);
            $query .=" AND `pay_id` = $pay_id";
        }
        if(isset($_REQUEST["created_at"]) && !empty($_REQUEST["created_at"])){
            $created_at = sanitize_text_field($_REQUEST["created_at"]);
            $query .=" AND `created_at` LIKE '%$created_at%'";
        }

        return $query;
    }

    public function search_box( $text, $input_id ) {
        if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
            // return;
        }

        $input_id = $input_id . '-search-input';


        ?>
        <p class="search-box">
        <form action="admin.php" method="get">
            <input type="hidden" name="page"  value="<?php echo $_REQUEST['page']; ?>"/>
            <?php
            if ( ! empty( $_REQUEST['orderby'] ) ) {
                echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
            }
            if ( ! empty( $_REQUEST['order'] ) ) {
                echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
            }
            if ( ! empty( $_REQUEST['post_mime_type'] ) ) {
                echo '<input type="hidden" name="post_mime_type" value="' . esc_attr( $_REQUEST['post_mime_type'] ) . '" />';
            }
            if ( ! empty( $_REQUEST['detached'] ) ) {
                echo '<input type="hidden" name="detached" value="' . esc_attr( $_REQUEST['detached'] ) . '" />';
            }

            ?>
            <div class="cbk-knet-field">
                <label for="order_id"><?php  echo __('Order', "cbk_knet") ?></label>
                <input type="search" id="order_id" name="order_id" value="<?php echo esc_attr($_REQUEST['order_id'] ?? ""); ?>" placeholder="<?php  echo __('Order', "cbk_knet") ?>" />
            </div>

            <div class="cbk-knet-field">
                <label for="status"><?php  echo __('Status', "cbk_knet") ?></label>
                <select name="status" id="status">
                    <option value=""><?php echo __('Status', "cbk_knet") ?></option>
                    <option value="fail" <?php echo (isset($_REQUEST["status"]) && $_REQUEST["status"] =="fail") ? "selected" : "" ?>><?php echo __('Fail', "cbk_knet") ?></option>
                    <option value="success" <?php echo (isset($_REQUEST["status"]) && $_REQUEST["status"] =="success") ? "selected" : "" ?>><?php echo __('Success', "cbk_knet") ?></option>
                </select>
            </div>
            <div class="cbk-knet-field">
                <label for="result"><?php  echo __('Result', "cbk_knet") ?></label>
                <input type="search" id="result" name="result" value="<?php  echo esc_attr($_REQUEST['result'] ?? ""); ?>" placeholder="<?php  echo __('Result', "cbk_knet") ?>" />
            </div>
            <div class="cbk-knet-field">
                <label for="amount"><?php  echo __('Amount', "cbk_knet") ?></label>
                <input type="search" id="amount" name="amount" value="<?php  echo esc_attr($_REQUEST['amount'] ?? ""); ?>" placeholder="<?php  echo __('amount', "cbk_knet") ?>" />
            </div>
            <div class="cbk-knet-field">
                <label for="payment_id"><?php  echo __('Payment id', "cbk_knet") ?></label>
                <input type="search" id="payment_id" name="payment_id" value="<?php  echo esc_attr($_REQUEST['payment_id'] ?? ""); ?>" placeholder="<?php  echo __('Payment id', "cbk_knet") ?>" />
            </div>
            <div class="cbk-knet-field">
                <label for="track_id"><?php  echo __('Tracking id', "cbk_knet") ?></label>
                <input type="search" id="track_id" name="track_id" value="<?php  echo esc_attr($_REQUEST['track_id'] ?? ""); ?>" placeholder="<?php  echo __('Tracking id', "cbk_knet") ?>" />
            </div>
            <div class="cbk-knet-field">
                <label for="tran_id"><?php  echo __('Transaction id', "cbk_knet") ?></label>
                <input type="search" id="tran_id" name="tran_id" value="<?php  echo esc_attr($_REQUEST['tran_id'] ?? ""); ?>" placeholder="<?php  echo __('Transaction id', "cbk_knet") ?>" />
            </div>
            <div class="cbk-knet-field">
                <label for="ref_id"><?php  echo __('Refrance id', "cbk_knet") ?></label>
                <input type="search" id="ref_id" name="ref_id" value="<?php  echo esc_attr($_REQUEST['ref_id'] ?? ""); ?>" placeholder="<?php  echo __('Refrance id', "cbk_knet") ?>" />
            </div>
            <div class="cbk-knet-field">
                <label for="ref_id"><?php  echo __('Pay id', "cbk_knet") ?></label>
                <input type="search" id="pay_id" name="pay_id" value="<?php  echo esc_attr($_REQUEST['pay_id'] ?? ""); ?>" placeholder="<?php  echo __('Pay id', "cbk_knet") ?>" />
            </div>
            <div class="cbk-knet-field">
                <label for="created_at"><?php  echo __('Created at', "cbk_knet") ?></label>
                <input type="date" id="created_at" name="created_at" value="<?php  echo esc_attr($_REQUEST['created_at'] ?? ""); ?>" placeholder="<?php  echo __('Created at', "cbk_knet") ?>" />
            </div>
            <div>
                <?php submit_button( $text, 'submit', '', false, array( 'id' => 'search-submit' ) ); ?>
                <a class="button reset" href="admin.php?page=<?php echo $_REQUEST['page'] ?? ""; ?>"><?php  echo __('Reset', "cbk_knet") ?></a>
                <?php $http_build_query = $_GET; unset($http_build_query["page"])  ?>
                <a class="button reset" href="admin.php?page=<?php echo $_REQUEST['page'] ?? ""; ?>&cbk_knet_export=excel&<?php echo http_build_query($http_build_query) ?>"><?php  echo __('Export excel', "cbk_knet") ?></a>
                <a class="button reset" href="admin.php?page=<?php echo $_REQUEST['page'] ?? ""; ?>&cbk_knet_export=csv&<?php echo http_build_query($http_build_query) ?>"><?php  echo __('Export csv', "cbk_knet") ?></a>
            </div>

        </form>
        </p>
        <style type="text/css">
            .cbk-knet-field{
                display: flex;
                gap: 3rem;
                align-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }
            .cbk-knet-field label{
                width: 7rem;
            }

        </style>
        <?php
    }
    public function no_items() {
        _e( 'No Transations avaliable.',  "cbk_knet" );
    }
    public function get_columns()
    {
        return $columns= array(
            'order_id'=>__('Order', "cbk_knet"),
            'status'=>__('Status', "cbk_knet"),
            'result'=>__('Result', "cbk_knet"),
            'amount'=>__('Amount', "cbk_knet"),
            'payment_id'=>__('Payment id', "cbk_knet"),
            'track_id'=>__('Tracking id', "cbk_knet"),
            'tran_id'=>__('Transaction id', "cbk_knet"),
            'ref_id'=>__('Refrance id', "cbk_knet"),
            'pay_id'=>__('Pay id', "cbk_knet"),
            'created_at'=>__('Created at', "cbk_knet"),
        );
    }

    public function get_sortable_columns() {
        return $sortable = array(
            'order_id'=>'order_id',
            'status'=>'status',
            'result'=>'result',
            'payment_id'=>'payment_id',
            'track_id'=>'track_id',
            'amount'=>'amount',
            'tran_id'=>'tran_id',
            'ref_id'=>'ref_id',
            'pay_id'=>'pay_id',
            'created_at'=>'created_at',
        );
    }
    /**
     * Render a column when no column specific method exists.
     *
     * @param array $item
     * @param string $column_name
     *
     * @return mixed
     */
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'order_id':
                return sprintf("<a href='%s' target='_blank'>#%s</a>",get_edit_post_link($item[$column_name]),$item[$column_name]);
            case 'status':
                return ($item[$column_name] == "fail") ? sprintf("<span  style='color:red'>%s</span>",__($item[ $column_name ],"cbk_knet")) : sprintf("<span  style='color:green'>%s</span>",__($item[ $column_name ],"cbk_knet"));
            case 'result':
                return ($item[$column_name] != "CAPTURED") ? sprintf("<span style='color:red'>%s</span>",$item[ $column_name ]) : sprintf("<span  style='color:green'>%s</span>",$item[ $column_name ]);
            default:
                return $item["$column_name"]; //Show the whole array for troubleshooting purposes
        }
    }
    /**
     * Handles data query and filter, sorting, and pagination.
     */
    public function prepare_items() {

        $this->_column_headers = $this->get_column_info();

        /** Process bulk action */
        $this->process_bulk_action();

        $per_page = $this->get_items_per_page( 'trans_per_page', 5 );
        $current_page = $this->get_pagenum();
        $total_items = self::record_count();

        $this->set_pagination_args( [
            'total_items' => $total_items, //WE have to calculate the total number of items
            'per_page' => $per_page //WE have to determine how many items to show on a page
        ] );

        $this->items = self::get_transations( $per_page = 5, $page_number = 1 );
    }
    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public static function record_count() {
        $sql = "SELECT COUNT(*) FROM ".(new self)->table." WHERE 1=1 ".(new self)->filter_query();
        return (new self)->db->get_var( $sql );
    }
    public static function get_transations($per_page, $page_number = 1)
    {

        $sql = "SELECT * FROM ".(new self)->table." WHERE 1=1 ".(new self)->filter_query();

        if ( ! empty( $_REQUEST['orderby'] ) ) {
            $sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
            $sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
        }

        $sql .= " LIMIT $per_page";

        $sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;

        $result = (new self)->db->get_results( $sql, 'ARRAY_A' );

        return $result;
    }
}

class CBK_KNET_Plugin
{

// class instance
    static $instance;

// customer WP_List_Table object
    public $transations_obj;

// class constructor
    public function __construct()
    {
        add_filter('set-screen-option', [__CLASS__, 'set_screen'], 10, 3);
        add_action('admin_menu', [$this, 'plugin_menu']);

    }
    public static function set_screen( $status, $option, $value ) {
        return $value;
    }
    /** Singleton instance */
    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }
    /**
     * Screen options
     */
    public function screen_option() {

        $option = 'per_page';
        $args = [
            'label' => 'Transations count',
            'default' => 5,
            'option' => 'trans_per_page'
        ];

        add_screen_option( $option, $args );

        $this->transations_obj = new cbk_knet_trans_grid();
    }
    public function plugin_menu() {

        $hook =add_submenu_page(
            'woocommerce',
            __( 'Cbk Knet transactions', 'cbk_knet' ),
            __( 'Cbk Knet transactions', 'cbk_knet' ),
            'manage_woocommerce',
            "cbk-knet-transactions",
            [ $this, 'plugin_settings_page' ],
            6
        );
        add_action( "load-$hook", [ $this, 'screen_option' ] );


    }
    /**
     * Plugin settings page
     */
    public function plugin_settings_page() {
        ?>
        <div class="wrap">
            <h2><?php echo __( 'Cbk Knet transactions', 'cbk_knet' ) ?></h2>

            <div style="display: flex;gap: 3rem;">

                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <div class="meta-box-sortables ui-sortable">
                            <form method="post">
                                <?php
                                $this->transations_obj->prepare_items();
                                $this->transations_obj->display(); ?>
                            </form>
                        </div>
                    </div>
                </div>
                <div id="post-body" class="metabox-holder columns-2">
                    <?php $this->transations_obj->search_box(_e( 'Filter', 'cbk_knet' ), "cbk_knet_filter") ?>
                </div>
            </div>
        </div>
        <?php
    }
}