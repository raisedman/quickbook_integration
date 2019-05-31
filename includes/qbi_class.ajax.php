<?php

use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Core\ServiceContext;
use QuickBooksOnline\API\Facades\Invoice;
use QuickBooksOnline\API\PlatformService\PlatformService;
use QuickBooksOnline\API\Core\Http\Serialization\XmlObjectSerializer;
use QuickBooksOnline\API\Facades\Item;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'WPC_QBI_AJAX' ) ) {

    class WPC_QBI_AJAX extends WPC_QBI_Common {

        /**
         * PHP 5 constructor
         **/

        function __construct() {

            $this->qbi_common_construct();

            add_action( 'wp_ajax_wpc_quick_book_connect', array( &$this, 'set_connect_url' ) );
            add_action( 'wp_ajax_wpc_quick_book_info', array( &$this, 'generate_quick_book_info' ) );
            add_action( 'wp_ajax_wpc_checking_quick_book_connect', array( &$this, 'wpc_checking_quick_book_connect' ) );

        }

        function wpc_checking_quick_book_connect() {
            $array_stat = array();
            if ( $_POST['dev'] == 'true' ) {
                $status_dev = $this->look_connection_status( 'dev' );
                if ( $status_dev ) {
                    $array_stat['dev'] = '<p style="color:green; display: inline-block">' . __( 'CONNECTED', WPC_CLIENT_TEXT_DOMAIN ) . '</p> ';
                } else {
                    $array_stat['dev'] = '<p style="color:red; display: inline-block">' . __( 'NOT CONNECTED', WPC_CLIENT_TEXT_DOMAIN ) . '</p> ';
                }
            }

            if ( $_POST['live'] == 'true' ) {
                $status_live = $this->look_connection_status( 'live' );
                if ( $status_live ) {
                    $array_stat['live'] = '<p style="color:green; display: inline-block">' . __( 'CONNECTED', WPC_CLIENT_TEXT_DOMAIN ) . '</p> ';
                } else {
                    $array_stat['live'] = '<p style="color:red; display: inline-block">' . __( 'NOT CONNECTED', WPC_CLIENT_TEXT_DOMAIN ) . '</p> ';
                }
            }
            wp_send_json_success( $array_stat );


        }


        function generate_quick_book_info() {
            $code    = ( isset( $_GET['code'] ) ) ? $_GET['code'] : '';
            $realmId = ( isset( $_GET['realmId'] ) ) ? $_GET['realmId'] : '';
            if ( $code && $realmId && ! empty( $this->settings ) ) {
                $settings = $this->settings;


                $role = 'wpc_client';
                $users = get_users('role='.$role);

                foreach ($users as $user) {
                    delete_user_meta($user->ID, 'quickBook_id');
                }

                if ( 'live' == $settings['mode'] ) {
                    $client_id     = $settings['live_client_id'];
                    $client_secret = $settings['live_client_secret'];
                } else {
                    $client_id     = $settings['dev_client_id'];
                    $client_secret = $settings['dev_client_secret'];
                }

                if ( ! empty( $client_id ) && ! empty( $client_secret ) ) {
                    $dataService = DataService::Configure( array(
                        'auth_mode'    => 'oauth2',
                        'ClientID'     => $client_id,
                        'ClientSecret' => $client_secret,
                        'RedirectURI'  => admin_url( 'admin-ajax.php' ) . '?action=wpc_quick_book_info',
                        'scope'        => "com.intuit.quickbooks.accounting",
                        'baseUrl'      => ( $settings['mode'] == 'live' ) ? 'production' : 'development',
                    ) );

                    $OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();
                    $accessToken       = $OAuth2LoginHelper->exchangeAuthorizationCodeForToken( $code, $realmId );
                    $dataService->updateOAuth2Token( $accessToken );
                    $accessTokenValue  = $accessToken->getAccessToken();
                    $refreshTokenValue = $accessToken->getRefreshToken();

                    $wpc_quickBook_array = get_option( 'wpc_quickBook_integration' );

                    if ( 'live' == $settings['mode'] ) {
                        $wpc_quickBook_array['live']['accessToken']  = $accessTokenValue;
                        $wpc_quickBook_array['live']['refreshToken'] = $refreshTokenValue;
                        $wpc_quickBook_array['live']['realmId']      = $realmId;
                    } else {
                        $wpc_quickBook_array['dev']['accessToken']  = $accessTokenValue;
                        $wpc_quickBook_array['dev']['refreshToken'] = $refreshTokenValue;
                        $wpc_quickBook_array['dev']['realmId']      = $realmId;
                    }
                    $wpc_quickBook_array['timeStamp'] = time() + 50 * 60;

                    update_option( 'wpc_quickBook_integration', $wpc_quickBook_array );

                    $this->wpc_get_all_products( $dataService );
                }

            }


            WPC()->redirect( admin_url( 'admin.php' ) . '?page=wpclients_settings&tab=quickBook' );
            wp_die();
        }

        function set_connect_url() {
            $client_id     = $_POST['client_id'];
            $client_secret = $_POST['client_secret'];
            $mode          = $_POST['mode'];
            $dataService   = DataService::Configure( array(
                'auth_mode'    => 'oauth2',
                'ClientID'     => $client_id,
                'ClientSecret' => $client_secret,
                'RedirectURI'  => admin_url( 'admin-ajax.php' ) . '?action=wpc_quick_book_info',
                'scope'        => "com.intuit.quickbooks.accounting",
                'baseUrl'      => ( $mode == 'live' ) ? 'production' : 'development'
            ) );


            $OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();

            $authorizationCodeUrl = $OAuth2LoginHelper->getAuthorizationCodeURL(); //authorization of user
            $error                = $dataService->getLastError();


            if ( $authorizationCodeUrl && ! $error ) {
                $settings         = WPC()->get_settings( 'quickBook' );
                $settings['mode'] = $mode;
                if ( 'dev' == $mode ) {
                    $settings['dev_client_secret'] = $client_secret;
                    $settings['dev_client_id']     = $client_id;
                } elseif ( 'live' == $mode ) {
                    $settings['live_client_secret'] = $client_secret;
                    $settings['live_client_id']     = $client_id;
                }

                WPC()->settings()->update( $settings, 'quickBook' );

                wp_send_json_success( array( 'connectUrl' => $authorizationCodeUrl ) );
            }

            wp_send_json_error();
        }


        //end class
    }

}