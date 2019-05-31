<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


if ( isset( $_POST['wpc_settings'] ) ) {
    WPC()->settings()->update( $_POST['wpc_settings'], 'quickBook' );

    WPC()->redirect( WPC()->settings()->get_current_setting_url() . '&msg=u' );
}
$quickBook_option = get_option( 'wpc_quickBook_integration' );
$settings         = WPC()->get_settings( 'quickBook' );

$dev_custom_attributes  = array();
$live_custom_attributes = array();

if ( empty( $quickBook_option['dev']['accessToken'] ) || empty( $quickBook_option['dev']['refreshToken'] ) || empty( $quickBook_option['dev']['realmId'] ) ) {
    $status                  = __( 'NOT CONNECTED', WPC_CLIENT_TEXT_DOMAIN );
    $status_connect_html_dev = '<p style="color:red ; display: inline-block">' . $status . '</p> ';

    $status_connect_html_dev .= '<input type="button" class="button" id="dev_quick_book_connect"
           value="' . __( 'Connect to QuickBooks', WPC_CLIENT_TEXT_DOMAIN ) . '"/>';
} else {
    $dev_custom_attributes = array( 'readonly' => 'readonly' );

    $status_connect_html_dev = '<div id="wpc_status_dev_connection" style="display: inline-block">
        <div class="wpc_ajax_checking_quickBook" style="display: inline-block"> 
            <span >' . __( 'Checking', WPC_CLIENT_TEXT_DOMAIN ) . '</span>
            <span class="wpc_ajax_loading" id="wpc_role_loading"></span>
        </div>
         <span id="edit_dev_connection_block">   (<a href="javascript:void(0);" id="edit_dev_connection" >' . __( 'Edit Connection', WPC_CLIENT_TEXT_DOMAIN ) . '</a>)</span>
    </div>';

    $status_connect_html_dev .= '<input type="button" style="display:none" class="button" style="display:block" id="dev_quick_book_connect"
           value="' . __( 'Reconnect to QuickBooks', WPC_CLIENT_TEXT_DOMAIN ) . '"/>';
}

if ( empty( $quickBook_option['live']['accessToken'] ) || empty( $quickBook_option['live']['refreshToken'] ) || empty( $quickBook_option['live']['realmId'] ) ) {
    $status                   = __( 'NOT CONNECTED', WPC_CLIENT_TEXT_DOMAIN );
    $status_connect_html_live = '<p style="color:red; display: inline-block">' . $status . '</p> ';
    $status_connect_html_live .= '<input type="button"  class="button" id="live_quick_book_connect"
           value="' . __( 'Connect to QuickBooks', WPC_CLIENT_TEXT_DOMAIN ) . '"/>';
} else {
    $live_custom_attributes = array( 'readonly' => 'readonly' );

    $status_connect_html_live = '<div id="wpc_status_live_connection">
        <div class="wpc_ajax_checking_quickBook" style="display: inline-block"> 
            <span>' . __( 'Checking', WPC_CLIENT_TEXT_DOMAIN ) . '</span>
            <span class="wpc_ajax_loading" id="wpc_role_loading"></span>
        </div>
        <span id="edit_live_connection_block"> (<a href="javascript:void(0);" id="edit_live_connection" >' . __( 'Edit Connection', WPC_CLIENT_TEXT_DOMAIN ) . '</a>) </span>
    </div>';
    $status_connect_html_live .= '<input type="button" style="display:none" class="button" id="live_quick_book_connect"
           value="' . __( 'Reconnect to QuickBooks', WPC_CLIENT_TEXT_DOMAIN ) . '"/>';
}

$redirect_url = admin_url( 'admin-ajax.php' ) . '?action=wpc_quick_book_info';

$section_fields = array(
    array(
        'type'  => 'title',
        'label' => __( 'Connect to QuickBooks', WPC_CLIENT_TEXT_DOMAIN ),
    ),
    array(
        'id'      => 'mode',
        'type'    => 'selectbox',
        'label'   => __( 'Mode', WPC_CLIENT_TEXT_DOMAIN ),
        'value'   => $settings['mode'],
        'options' => array(
            'live' => 'Production',
            'dev'  => 'Development'
        ),
    ),
    array(
        'id'                => 'dev_client_id',
        'type'              => 'text',
        'custom_attributes' => $dev_custom_attributes,
        'label'             => __( 'Development Client ID', WPC_CLIENT_TEXT_DOMAIN ),
        'value'             => ( isset( $settings['dev_client_id'] ) ) ? $settings['dev_client_id'] : '',
        'description'       => sprintf( __( 'Your Client ID from the keys tab.', WPC_CLIENT_TEXT_DOMAIN ), WPC()->custom_titles['circle']['s'] ),
    ),
    array(
        'id'                => 'dev_client_secret',
        'type'              => 'text',
        'custom_attributes' => $dev_custom_attributes,
        'label'             => __( 'Development Client Secret', WPC_CLIENT_TEXT_DOMAIN ),
        'value'             => ( isset( $settings['dev_client_secret'] ) ) ? $settings['dev_client_secret'] : '',
        'description'       => __( 'Your Client Secret from the keys tab.', WPC_CLIENT_TEXT_DOMAIN ),
    ),
    array(
        'id'          => 'dev_redirect_url',
        'type'        => 'checkbox',
        'label'       => __( 'Development Redirect URL', WPC_CLIENT_TEXT_DOMAIN ),
        'value'       => ( isset( $settings['dev_redirect_url'] ) ) ? $settings['dev_redirect_url'] : '',
        'description' => __( 'I entered the Redirect URL on Intuit Developer:', WPC_CLIENT_TEXT_DOMAIN )
                         . '<br><strong> ' . $redirect_url . ' </strong>',
    ),
    array(
        'id'          => 'dev_connection_quickBook_status',
        'type'        => 'custom',
        'label'       => __( 'Connection status', WPC_CLIENT_TEXT_DOMAIN ),
        'custom_html' => $status_connect_html_dev,
        'description' => '',
    ),
    array(
        'id'                => 'live_client_id',
        'type'              => 'text',
        'custom_attributes' => $live_custom_attributes,
        'label'             => __( 'Production Client ID', WPC_CLIENT_TEXT_DOMAIN ),
        'value'             => ( isset( $settings['live_client_id'] ) ) ? $settings['live_client_id'] : '',
        'description'       => __( 'Your Client ID from the keys tab.', WPC_CLIENT_TEXT_DOMAIN ),
    ),
    array(
        'id'                => 'live_client_secret',
        'type'              => 'text',
        'custom_attributes' => $live_custom_attributes,
        'label'             => __( 'Production Client Secret', WPC_CLIENT_TEXT_DOMAIN ),
        'value'             => ( isset( $settings['live_client_secret'] ) ) ? $settings['live_client_secret'] : '',
        'description'       => __( 'Your Client Secret from the keys tab.', WPC_CLIENT_TEXT_DOMAIN ),
    ),
    array(
        'id'          => 'live_redirect_url',
        'type'        => 'checkbox',
        'label'       => __( 'Production Redirect URL', WPC_CLIENT_TEXT_DOMAIN ),
        'value'       => ( isset( $settings['live_redirect_url'] ) ) ? $settings['live_redirect_url'] : '',
        'description' => __( 'I entered the Redirect URL on Intuit Developer:', WPC_CLIENT_TEXT_DOMAIN )
                         . '<br><strong> ' . $redirect_url . ' </strong>',
    ),
    array(
        'id'          => 'live_connection_quickBook_status',
        'type'        => 'custom',
        'label'       => __( 'Connection status', WPC_CLIENT_TEXT_DOMAIN ),
        'custom_html' => $status_connect_html_live,
        'description' => '',
    ),

);


WPC()->settings()->render_settings_section( $section_fields );

?>

    <script>

        var mode = jQuery( '#wpc_settings_mode' ).val();
        if ( mode === 'dev' ) {
            jQuery( '#wpc_settings_live_client_id' ).parents( '.wpc-settings-line' ).hide();
            jQuery( '#wpc_settings_live_client_secret' ).parents( '.wpc-settings-line' ).hide();
            jQuery( '#wpc_settings_live_redirect_url' ).parents( '.wpc-settings-line' ).hide();
            jQuery( '#live_quick_book_connect' ).parents( '.wpc-settings-line' ).hide();
        } else {
            jQuery( '#wpc_settings_dev_client_id' ).parents( '.wpc-settings-line' ).hide();
            jQuery( '#wpc_settings_dev_client_secret' ).parents( '.wpc-settings-line' ).hide();
            jQuery( '#wpc_settings_dev_redirect_url' ).parents( '.wpc-settings-line' ).hide();
            jQuery( '#dev_quick_book_connect' ).parents( '.wpc-settings-line' ).hide();
        }

        var data = {
            action: 'wpc_checking_quick_book_connect',
            dev: ! ! jQuery( 'div' ).is( '#wpc_status_dev_connection' ),
            live: ! ! jQuery( 'div' ).is( '#wpc_status_live_connection' ),
        };

        jQuery.post( ajaxurl, data, function ( json ) {
            if ( json.success ) {
                if ( json.data.dev ) {
                    jQuery( '#wpc_status_dev_connection .wpc_ajax_checking_quickBook' ).hide();
                    jQuery( '#wpc_status_dev_connection' ).prepend( json.data.dev );
                }
                if ( json.data.live ) {
                    jQuery( '#wpc_status_live_connection .wpc_ajax_checking_quickBook' ).hide();
                    jQuery( '#wpc_status_live_connection' ).prepend( json.data.live );
                }
            }
        } );


        jQuery( document ).ready( function () {

            jQuery( '#wpc_settings_mode' ).change( function () {
                var modeChanged = jQuery( '#wpc_settings_mode' ).val();
                if ( modeChanged === 'live' ) {
                    jQuery( '#wpc_settings_live_client_id' ).parents( '.wpc-settings-line' ).show();
                    jQuery( '#wpc_settings_live_client_secret' ).parents( '.wpc-settings-line' ).show();
                    jQuery( '#wpc_settings_live_redirect_url' ).parents( '.wpc-settings-line' ).show();
                    jQuery( '#live_quick_book_connect' ).parents( '.wpc-settings-line' ).show();
                    jQuery( '#wpc_settings_dev_client_id' ).parents( '.wpc-settings-line' ).hide();
                    jQuery( '#wpc_settings_dev_client_secret' ).parents( '.wpc-settings-line' ).hide();
                    jQuery( '#wpc_settings_dev_redirect_url' ).parents( '.wpc-settings-line' ).hide();
                    jQuery( '#dev_quick_book_connect' ).parents( '.wpc-settings-line' ).hide();
                } else {
                    jQuery( '#wpc_settings_dev_client_id' ).parents( '.wpc-settings-line' ).show();
                    jQuery( '#wpc_settings_dev_client_secret' ).parents( '.wpc-settings-line' ).show();
                    jQuery( '#wpc_settings_dev_redirect_url' ).parents( '.wpc-settings-line' ).show();
                    jQuery( '#dev_quick_book_connect' ).parents( '.wpc-settings-line' ).show();
                    jQuery( '#wpc_settings_live_client_id' ).parents( '.wpc-settings-line' ).hide();
                    jQuery( '#wpc_settings_live_client_secret' ).parents( '.wpc-settings-line' ).hide();
                    jQuery( '#wpc_settings_live_redirect_url' ).parents( '.wpc-settings-line' ).hide();
                    jQuery( '#live_quick_book_connect' ).parents( '.wpc-settings-line' ).hide();
                }
            } );


            jQuery( '#live_quick_book_connect' ).click( function () {
                var client_secret = jQuery( '#wpc_settings_live_client_secret' );
                var client_id = jQuery( '#wpc_settings_live_client_id' );

                client_secret.parents( '.wpc-settings-line' ).removeClass( 'wpc_error' );
                client_id.parents( '.wpc-settings-line' ).removeClass( 'wpc_error' );
                jQuery( '#wpc_settings_live_redirect_url' ).parents( '.wpc-settings-line' ).removeClass( 'wpc_error' );

                var clientIdVal = client_id.val();
                var clientSecretVal = client_secret.val();

                var error = 0;

                if ( jQuery( '#wpc_settings_live_redirect_url' ).attr( 'checked' ) != 'checked' ) {
                    jQuery( '#wpc_settings_live_redirect_url' ).parents( '.wpc-settings-line' ).addClass( 'wpc_error' );
                    error += 1;
                }
                if ( ! clientIdVal ) {
                    client_id.parents( '.wpc-settings-line' ).addClass( 'wpc_error' );
                    error += 1;
                }
                if ( ! clientSecretVal ) {
                    client_secret.parents( '.wpc-settings-line' ).addClass( 'wpc_error' );
                    error += 1;
                }

                if ( ! error ) {
                    jQuery( '#live_quick_book_connect' ).attr( 'disabled', 'disabled' );

                    var data = {
                        action: 'wpc_quick_book_connect',
                        mode: 'live',
                        client_id: clientIdVal,
                        client_secret: clientSecretVal,
                    };

                    jQuery.post( ajaxurl, data, function ( json ) {
                        if ( json.success ) {
                            window.location.replace( json.data.connectUrl );
                            jQuery( '#live_quick_book_connect' ).removeAttr( 'disabled' );
                        }
                        else {
                            alert( 'error :(' );
                            jQuery( '#live_quick_book_connect' ).removeAttr( 'disabled' );
                        }
                    } );
                }
            } );

            jQuery( '#dev_quick_book_connect' ).click( function () {
                var client_secret = jQuery( '#wpc_settings_dev_client_secret' );
                var client_id = jQuery( '#wpc_settings_dev_client_id' );

                client_secret.parents( '.wpc-settings-line' ).removeClass( 'wpc_error' );
                client_id.parents( '.wpc-settings-line' ).removeClass( 'wpc_error' );
                jQuery( '#wpc_settings_dev_redirect_url' ).parents( '.wpc-settings-line' ).removeClass( 'wpc_error' );

                var clientIdVal = client_id.val();
                var clientSecretVal = client_secret.val();

                var error = 0;

                if ( jQuery( '#wpc_settings_dev_redirect_url' ).attr( 'checked' ) != 'checked' ) {
                    jQuery( '#wpc_settings_dev_redirect_url' ).parents( '.wpc-settings-line' ).addClass( 'wpc_error' );
                    error += 1;
                }
                if ( ! clientIdVal ) {
                    client_id.parents( '.wpc-settings-line' ).addClass( 'wpc_error' );
                    error += 1;
                }
                if ( ! clientSecretVal ) {
                    client_secret.parents( '.wpc-settings-line' ).addClass( 'wpc_error' );
                    error += 1;
                }

                if ( ! error ) {
                    jQuery( '#dev_quick_book_connect' ).attr( 'disabled', 'disabled' );

                    var data = {
                        action: 'wpc_quick_book_connect',
                        mode: 'dev',
                        client_id: clientIdVal,
                        client_secret: clientSecretVal,
                    };

                    jQuery.post( ajaxurl, data, function ( json ) {
                        if ( json.success ) {
                            window.location.replace( json.data.connectUrl );
                            jQuery( '#dev_quick_book_connect' ).removeAttr( 'disabled' );
                        }
                        else {
                            alert( 'error :(' );
                            jQuery( '#dev_quick_book_connect' ).removeAttr( 'disabled' );
                        }
                    } );
                }
            } );

            jQuery( '#edit_dev_connection' ).click( function () {
                jQuery( '#edit_dev_connection_block' ).empty().append( jQuery( '#dev_quick_book_connect' ).show() );
                jQuery( '#wpc_settings_dev_client_id' ).prop( 'readonly', false );
                jQuery( '#wpc_settings_dev_client_secret' ).prop( 'readonly', false );
            } );
            jQuery( '#edit_live_connection' ).click( function () {
                jQuery( '#edit_live_connection_block' ).empty().append( jQuery( '#live_quick_book_connect' ).show() );
                jQuery( '#wpc_settings_live_client_id' ).prop( 'readonly', false );
                jQuery( '#wpc_settings_live_client_secret' ).prop( 'readonly', false );
            } );

        } );
    </script>
<?php
