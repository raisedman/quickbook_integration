<?php

if ( ! class_exists( 'WPC_QBI_Admin' ) ) {

    class WPC_QBI_Admin extends WPC_QBI_Common {


        /**
         * PHP 5 constructor
         **/
        function __construct() {

            $this->qbi_common_construct();

            //item
            add_action( 'init', array( $this, 'update_items' ) );

            add_action( 'wpc_items_top_action', array( &$this, 'action_update_items' ), 10 );
            add_filter( 'wpc_create_item_columns', array( &$this, 'create_quickBook_column' ) );
            add_filter( 'wpc_client_is_active_qb_custom_column_of_items', array(
                &$this,
                'status_active_quickBook'
            ), 10, 2 );

            //invoice
            add_action( 'wpc_edit_invoice_js_scripts', array( &$this, 'scripts_for_add_invoice' ), 10 );
            add_action( 'wpc_create_invoice_add_button_publish', array( &$this, 'add_button_save_quickBook' ), 10 );
            add_action( 'wpc_edit_invoice_add_button_publish', array( &$this, 'add_button_edit_quickBook' ), 10, 1 );
            //settings
            add_action( 'wpc_client_settings_tab_quickBook', array( &$this, 'settings_page' ), 100 );
            add_filter( 'wpc_client_settings_tabs', array( &$this, 'settings_tabs' ) );
            add_filter( 'wpc_inv_summary_number_column', array( &$this, 'edit_string_invoice_number_column' ), 10, 2 );
            //add actions links on plugins page
            add_filter( 'plugin_action_links_wp-client-quickbooks-integration/wp-client-quickbooks-integration.php', array(
                &$this,
                'add_action_links'
            ), 99 );

        }


        function add_action_links( $links ) {
            $links['settings'] = sprintf( '<a href="admin.php?page=wpclients_settings&tab=quickBook">%s</a>', __( 'Settings', WPC_CLIENT_TEXT_DOMAIN ) );

            return $links;
        }

        function edit_string_invoice_number_column( $html, $item_id ) {
            $send = get_post_meta( $item_id, 'send_on_QuickBooks', true );
            if ( $send ) {
                $img_url = $this->extension_url . 'images/quickbook_logo.png';
                $html    .= '    <img src="' . $img_url . '" width="17px" height="17px" style="vertical-align : bottom;">';
            }

            return $html;
        }

        function action_update_items() {
            $nonce = wp_create_nonce( 'qbi_update_items' );
            ?>
            <div class="alignleft actions">
            <a target="_self" href="<?php echo add_query_arg( array(
                'wpc_action' => 'update_quickBook_items',
                'nonce'      => $nonce
            ), get_admin_url() . 'admin.php' ) ?>">
                <input type="button" value="<?php _e( 'Update QuickBooks items', WPC_CLIENT_TEXT_DOMAIN ) ?>"
                       class="button-secondary"/>
            </a>

            </div><?php
        }

        function update_items() {
            $action = filter_input( 1, 'wpc_action' );
            $nonce  = filter_input( 1, 'nonce' );
            if ( 'update_quickBook_items' === $action && wp_verify_nonce( $nonce, 'qbi_update_items' ) ) {
                $this->wpc_get_all_products();
                WPC()->redirect( admin_url( 'admin.php' ) . '?page=wpclients_invoicing&tab=invoicing_items' );
            }
        }

        function create_quickBook_column( $array ) {
            $array['is_active_qb'] = '<div class="wpc_text_center">' . __( 'QuickBooks', WPC_CLIENT_TEXT_DOMAIN ) . '</div>';

            return $array;
        }

        function status_active_quickBook( $html, $item ) {
            $checked = ( isset( $item['is_active_quickbook'] ) && $item['is_active_quickbook'] == 1 ) ? 'checked' : '';
            $html    = '<div class="wpc_text_center"><input type="checkbox" disabled ' . $checked . '></div>';

            return $html;
        }

        function add_button_save_quickBook() {
            ?>
            <label>
                <input id="send_quickbook" type="checkbox" name="wpc_data[send_quickBook]" value="1"/>
                <?php _e( 'Send on QuickBooks', WPC_CLIENT_TEXT_DOMAIN ) ?>
            </label>
            <?php
        }

        function add_button_edit_quickBook( $invoice_id ) {
            $quickBook_id = get_post_meta( $invoice_id, 'quickBook_id', true );
            if ( $quickBook_id ) {
                ?>
                <label>
                    <input id="send_quickbook" type="checkbox" name="wpc_data[edit_quickBook]" value="1"/>
                    <?php _e( 'Edit on QuickBooks', WPC_CLIENT_TEXT_DOMAIN ) ?>
                </label>
            <?php }
        }


        function scripts_for_add_invoice() {
            ?>
            <script>
                var num_item = 0;
                var array_state_item = [];
                jQuery( 'body' ).on( 'click', '#button_add_item', function () {
                    jQuery( '.item_checkbox' ).each( function () {
                        if ( jQuery( this ).attr( 'checked' ) ) {
                            var tds = jQuery( this ).closest( '.preset_item' ).find( '>.column_preset_item' );
                            var id = tds.eq( 0 ).find( 'input' ).val();
                            var value = tds.find( '>.quickBook_status' ).find( 'input' ).attr( 'checked' );
                            value = (
                                value === undefined
                            ) ? 0 : 1;
                            array_state_item.push( {
                                id_item: id,
                                quickBook_status: value,
                            } );
                        }
                    } );
                } );

                function add_errors( errors ) {
                    if ( jQuery( '#send_quickbook' ).attr( 'checked' ) ) {
                        var isset_items = jQuery( '#added_items tbody ' ).find( '.invoice_items:not(:nth-child(1))' ).eq( 0 ).val();
                        if ( isset_items !== undefined ) {
                            jQuery( '#added_items tbody .invoice_items:not(:nth-child(1))' ).each( function () {
                                var item_id = jQuery( this ).find( 'input.id_current_item' ).val();
                                if ( item_id ) {
                                    if ( array_state_item.length ) {
                                        for ( var i = 0; i < array_state_item.length; i ++ ) {
                                            if ( array_state_item[i].id_item == item_id ) {
                                                if ( array_state_item[i].quickBook_status == 0 ) {
                                                    jQuery( this ).addClass( 'wpc_error' );
                                                    return ++ errors;
                                                }
                                                break;
                                            }
                                        }
                                    }
                                } else {
                                    jQuery( this ).addClass( 'wpc_error' );
                                    return ++ errors;
                                }
                            } );
                            return errors;
                        }
                        else {
                            return ++ errors;
                        }
                    }
                    return errors;
                }

            </script>
            <?php
        }

        function settings_tabs( $tabs ) {

            $tabs['quickBook'] = array(
                'title' => __( 'QuickBooks', WPC_CLIENT_TEXT_DOMAIN ),
            );

            return $tabs;
        }


        function settings_page() {
            require_once $this->extension_dir . 'includes/admin/settings.php';
        }

        //end class
    }
}