<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WPC_QBI_Install" ) ) {

    class WPC_QBI_Install extends WPC_QBI_Common {

        private static $instance = NULL;

        static public function getInstance() {
            if ( self::$instance === NULL )
                self::$instance = new WPC_QBI_Install();
            return self::$instance;
        }

        /**
         * PHP 5 constructor
         **/
        function __construct() {
            $this->qbi_common_construct();

        }

        function install() {
            $this->creating_db();

            WPC()->update()->check_updates( 'qbi' );
        }


        /*
        * Create DB tables
        */
        function creating_db() {
            global $wpdb;
            $have_table = $wpdb->get_results( "SHOW TABLES LIKE '{$wpdb->prefix}wpc_client_invoicing_items'" );

            if ( count( $have_table ) ) {
                $row = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                      WHERE table_name = '{$wpdb->prefix}wpc_client_invoicing_items' AND column_name = 'quickbook_id'" );

                if ( empty( $row ) ) {
                    $wpdb->query(
                        "ALTER TABLE {$wpdb->prefix}wpc_client_invoicing_items ADD quickbook_id int DEFAULT 0, 
                                ADD is_active_quickbook tinyint DEFAULT 0"
                    );
                }
            } else {
                add_action( 'admin_notices', function () { ?>
                    <div class="notice notice-error' is-dismissible">
                    <p><?php _e( 'Invoice table not created', WPC_CLIENT_TEXT_DOMAIN ) ?></p>
                    </div>
                <?php } );
            }
        }

        //end class
    }

}

return WPC_QBI_Install::getInstance();