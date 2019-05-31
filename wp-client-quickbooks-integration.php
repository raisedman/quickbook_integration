<?php
/*
Plugin Name: WP-Client: QuickBooks Integration
Plugin URI: http://www.WP-Client.com
Description:
Author: WP-Client.com
Version: 1.0.0
Author URI: http://www.WP-Client.com
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

function wpc_activation_plugin_qbi() {
    $active_plugins = get_option( 'active_plugins' );

    if ( ! is_array( $active_plugins ) || ! in_array( WPC()->extensions()->get_plugin( 'inv' ), $active_plugins ) ) {
        deactivate_plugins( WPC()->extensions()->get_plugin( 'qbi' ), true, true );
        _e( 'You cannot activate this plugin because Estimate / Invoices is not activated.');
        exit;
    }
}

register_activation_hook( __FILE__, 'wpc_activation_plugin_qbi' );


//pre init extension
add_action( 'wpc_client_pre_init', 'wpc_pre_qbi', - 10 );

if ( ! function_exists( 'wpc_pre_qbi' ) ) {
    function wpc_pre_qbi() {

        define( 'WPC_QBI_VER', '1.0.0' );
        define( 'WPC_QBI_REQUIRED_VER', '4.5.0' );

        //set extension data for using in other products
        WPC()->extensions()->add( 'qbi', array(
            'title'            => 'QuickBooks Integration',
            'plugin'           => 'wp-client-quickbooks-integration/wp-client-quickbooks-integration.php',
            'dir'              => WPC()->gen_plugin_dir( __FILE__ ),
            'url'              => WPC()->gen_plugin_url( __FILE__ ),
            'defined_version'  => WPC_QBI_VER,
            'required_version' => WPC_QBI_REQUIRED_VER,
            'product_name'     => 'QuickBooks Integration',
            'is_free'          => true,
        ) );


        function wpc_activation_qbi() {
            require_once 'includes/qbi_class.common.php';

            $install = require_once 'includes/qbi_class.install.php';
            $install->install();
        }

        //Install and Updates
        add_action( 'wpc_client_extension_install_qbi', 'wpc_activation_qbi' );


        //maybe create class var
        add_action( 'wpc_client_pre_init', 'wpc_init_classes_qbi', - 8 );

        if ( ! function_exists( 'wpc_init_classes_qbi' ) ) {
            function wpc_init_classes_qbi() {

                //checking for version required
                if ( WPC()->compare_versions( 'qbi' ) && ! WPC()->update()->is_crashed( 'qbi' ) ) {

                    require_once 'includes/qbi_class.common.php';

                    if ( defined( 'DOING_AJAX' ) ) {
                        require_once 'includes/qbi_class.ajax.php';
                    } elseif ( is_admin() ) {
                        require_once 'includes/qbi_class.admin.php';
                    } else {
                        require_once 'includes/qbi_class.user.php';
                    }


                    if ( defined( 'DOING_AJAX' ) ) {
                        $GLOBALS['wpc_qbi'] = new WPC_QBI_AJAX();
                    } elseif ( is_admin() ) {
                        $GLOBALS['wpc_qbi'] = new WPC_QBI_Admin();
                    } else {
                        $GLOBALS['wpc_qbi'] = new WPC_QBI_User();
                    }

                }
            }
        }



        /*
        * Function deactivation
        *
        * @return void
        */
        function wpc_deactivation_qbi() {

            WPC()->update()->deactivation( 'qbi' );
        }

        register_deactivation_hook( WPC()->extensions()->get_plugin( 'qbi' ), 'wpc_deactivation_qbi' );

    }
}