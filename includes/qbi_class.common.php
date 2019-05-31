<?php

use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Core\ServiceContext;
use QuickBooksOnline\API\Facades\Invoice;
use QuickBooksOnline\API\PlatformService\PlatformService;
use QuickBooksOnline\API\Core\Http\Serialization\XmlObjectSerializer;
use QuickBooksOnline\API\Facades\Item;
use QuickBooksOnline\API\Facades\Customer;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( "WPC_QBI_Common" ) ) {

    class WPC_QBI_Common {

        public $extension_dir;
        public $extension_url;

        protected $settings;


        /**
         * constructor
         **/
        function qbi_common_construct() {

            $this->settings      = WPC()->get_settings( 'quickBook' );
            $this->extension_dir = WPC()->extensions()->get_dir( 'qbi' );
            $this->extension_url = WPC()->extensions()->get_url( 'qbi' );

            require_once $this->extension_dir . 'includes/libs/quickbooks/src/config.php';
            add_filter( 'wpc_add_update_item', array( &$this, 'save_quickBook_id' ), 10, 2 );
            add_filter( 'wpc_add_update_item', array( &$this, 'wpc_send_quickBook_product' ), 20, 2 );
            add_filter( 'wpc_save_invoices', array( &$this, 'wpc_send_quickBook_invoice' ), 10, 3 );
            add_filter( 'wpc_edit_invoices', array( &$this, 'wpc_edit_quickBook_invoice' ), 10, 3 );

            // add column of preset items for page create invoice
            add_filter( 'wpc_inv_items_list_column_name', array( &$this, 'add_preset_items_column' ) );
            add_filter( 'wpc_inv_item_list_column_' . 'QuickBooks' . '_value', array(
                &$this,
                'add_value_column_of_item'
            ), 10, 2 );

        }

        function add_preset_items_column( $array_column ) {
            $array_column[] = array( 'width' => '80', 'text' => 'QuickBooks' );

            return $array_column;
        }

        function add_value_column_of_item( $html, $item ) {
            $checked = ( isset( $item['is_active_quickbook'] ) && $item['is_active_quickbook'] == 1 ) ? 'checked' : '';
            $html    = "<div class='wpc_text_center quickBook_status'><input type='checkbox' disabled $checked ></div>";

            return $html;
        }

        function save_quickBook_id( $errors_notification, $item ) {
            global $wpdb;
            if ( ! empty( $item['quickBook_id'] ) && $item['action'] == 'add' && ! empty( $item['id'] ) ) {
                $table = $wpdb->prefix . 'wpc_client_invoicing_items';
                $wpdb->update( $table, array(
                    'quickbook_id'        => $item['quickBook_id'],
                    'is_active_quickbook' => 1
                ), array( 'id' => $item['id'] ) );
            }

            return $errors_notification;
        }


        function wpc_create_item( $product_data ) {
            $dateTime = new \DateTime( 'NOW' );
            $Item     = Item::create( [
                "Name"              => $product_data['name'],
                "Description"       => $product_data['description'],
                "Active"            => true,
                "Type"              => "Inventory",
                "IncomeAccountRef"  => [
                    "value" => 79,
                    "name"  => "Landscaping Services:Job Materials:Fountains and Garden Lighting"
                ],
                "PurchaseCost"      => $product_data['rate'],
                "ExpenseAccountRef" => [
                    "value" => 80,
                    "name"  => "Cost of Goods Sold"
                ],
                "AssetAccountRef"   => [
                    "value" => 81,
                    "name"  => "Inventory Asset"
                ],
                "TrackQtyOnHand"    => true,
                "QtyOnHand"         => 100,
                "InvStartDate"      => $dateTime
            ] );

            return $Item;
        }


        function wpc_update_item( $obj_to_update, $product_data ) {
            $new_item = Item::update( $obj_to_update, array(
                "Name"        => $product_data['name'],
                "Description" => $product_data['description'],
                "UnitPrice"   => $product_data['rate'],
            ) );

            return $new_item;
        }

        function wpc_update_invoice( $obj_to_update, $array_line_items, $description ) {
            $new_invoice = Invoice::update( $obj_to_update, array(
                    "Line"         => $array_line_items,
                    "CustomerMemo" => array(
                        "value" => $description,
                    ),
                )

            );

            return $new_invoice;
        }


        function wpc_get_all_products( $dataService = '' ) {
            global $wpdb;

            if ( ! is_object( $dataService ) ) {
                $dataService = $this->get_valid_dataService();
            }

            if ( ! is_object( $dataService ) ) {
                return;
            }

            $allItems = $dataService->Query( "SELECT * FROM Item" );
            $error    = $dataService->getLastError();

            if ( $error ) {
                echo "The Status code is: " . $error->getHttpStatusCode() . "\n";
                echo "The Helper message is: " . $error->getOAuthHelperError() . "\n";
                echo "The Response message is: " . $error->getResponseBody() . "\n";
                exit;

            } else {
                $sql            = "SELECT quickbook_id,is_active_quickbook,it.name,description,rate FROM {$wpdb->prefix}wpc_client_invoicing_items it WHERE 1=1";
                $existing_items = $wpdb->get_results( $sql, ARRAY_A );
                $array_ids      = array_map( function ( $el ) {
                    return $el['quickbook_id'];
                }, $existing_items );

                $wpdb->query( "UPDATE {$wpdb->prefix}wpc_client_invoicing_items SET is_active_quickbook = 0" );
                $array_isset_ids = array();
                foreach ( $allItems as $item ) {
                    if ( ! in_array( $item->Id, $array_ids ) ) {
                        $GLOBALS['wpc_inv']->save_items( array(
                            'name'         => $item->Name,
                            'description'  => $item->Description,
                            'rate'         => $item->UnitPrice,
                            'quickBook_id' => $item->Id,
                        ) );
                        $array_isset_ids[] = $item->Id;
                    } else {
                        $current_elem = array_filter( $existing_items, function ( $innerArray ) use ( $item ) {
                            return ( $item->ID == $innerArray['quickbook_id'] );
                        } );
                        $current_elem = (array) $current_elem;
                        $current_elem = $current_elem[0];

                        if ( $current_elem['name'] != $item->Name || $current_elem['description'] != $item->Description
                             || $current_elem['rate'] != $item->UnitPrice ) {
                            $table = $wpdb->prefix . 'wpc_client_invoicing_items';
                            $data  = array(
                                'name'        => $item->Name,
                                'description' => $item->Description,
                                'rate'        => $item->UnitPrice,
                            );
                            $wpdb->update( $table, $data, array( 'quickBook_id' => $item->Id ) );
                        }
                        $array_isset_ids[] = $item->Id;
                    }
                }
                $string_query = implode( ',', $array_isset_ids );
                $sql          = "UPDATE {$wpdb->prefix}wpc_client_invoicing_items SET is_active_quickbook = 1 WHERE quickbook_id IN (" . $string_query . ")";
                $wpdb->query( $sql );

            }

            return;
        }

        //Function return object of customer on quickBook. Update token before using this method.
        function wpc_synchronize_customer_quickBook( $customer_id, $dataService ) {

            $quickBook_id = get_user_meta( $customer_id, 'quickBook_id', true );
            if ( $quickBook_id ) {
                $customer_obj_quickBook = $dataService->FindById( "Customer", $quickBook_id );
                if ( is_object( $customer_obj_quickBook ) ) {
                    update_user_meta( $customer_id, 'quickBook_id', $customer_obj_quickBook->Id );

                    return $customer_obj_quickBook;
                }
            }

            $customer_obj           = get_user_by( 'id', $customer_id );
            $customer_obj_quickBook = $dataService->Query( "select * from Customer Where primaryemailaddr = '{$customer_obj->user_email}' " );
            $customer_obj_quickBook = $customer_obj_quickBook[0];

            $error = $dataService->getLastError();
            if ( $error ) {

                return false;
            }

            if ( ! is_object( $customer_obj_quickBook ) ) {
                $meta            = get_user_meta( $customer_id );
                $given_name      = ( ! empty( $meta['first_name'][0] ) ) ? $meta['first_name'][0] : $customer_obj->user_nicename;
                $family_name     = ( ! empty( $meta['last_name'][0] ) ) ? $meta['last_name'][0] : '';
                $display_name    = ( ! empty( $meta['wpc_cl_business_name'][0] ) ) ? $meta['wpc_cl_business_name'][0] : $customer_obj->display_name;
                $customer_create = array(
                    "GivenName"        => $given_name,
                    "DisplayName"      => $display_name,
                    "PrimaryEmailAddr" => array(
                        "Address" => $customer_obj->user_email,
                    )
                );
                if ( ! empty( $family_name ) ) {
                    $customer_create['FamilyName'] = $family_name;
                }

                $customerObj = Customer::create( $customer_create );

                $customer_obj_quickBook = $dataService->Add( $customerObj );
                if ( is_object( $customer_obj_quickBook ) ) {
                    update_user_meta( $customer_id, 'quickBook_id', $customer_obj_quickBook->Id );

                    return $customer_obj_quickBook;
                } else {
                    return false;
                }

            } else {
                update_user_meta( $customer_id, 'quickBook_id', $customer_obj_quickBook->Id );

                return $customer_obj_quickBook;
            }

        }


        function wpc_edit_quickBook_invoice( $errors_notification, $data, $type = '', $dataService = '' ) {
            if ( 'inv' == $type ) {
                $send = ( isset( $data['edit_quickBook'] ) && 1 == $data['edit_quickBook'] ) ? 1 : 0;
                if ( $send ) {
                    if ( ! empty( $data['id'] ) ) {
                        $quickBook_id = get_post_meta( $data['id'], 'quickBook_id', true );
                    } else {
                        $errors_notification .= __( 'Invoice not updated on QuickBooks.<br/>', WPC_CLIENT_TEXT_DOMAIN );

                        return $errors_notification;
                    }

                    global $wpdb;

                    if ( ! is_object( $dataService ) ) {
                        $dataService = $this->get_valid_dataService();
                    }

                    if ( ! is_object( $dataService ) ) {
                        $errors_notification .= __( 'Invoice not updated on QuickBooks.<br/>', WPC_CLIENT_TEXT_DOMAIN );

                        return $errors_notification;
                    }

                    $invoice = $dataService->FindById( "Invoice", $quickBook_id );
                    if ( ! is_object( $invoice ) ) {
                        $errors_notification .= __( 'QuickBooks search invoice error.<br/>', WPC_CLIENT_TEXT_DOMAIN );

                        return $errors_notification;
                    }

                    $items      = $data['items'];
                    $items_true = array();
                    $i          = 1;
                    foreach ( $items as $item ) {
                        if ( $i == 1 ) {
                            $i ++;
                            continue;
                        }
                        $items_true[] = $item;
                    }

                    //create array of items for send on QuickBooks
                    $array_line_items = array();
                    foreach ( $items_true as $item ) {
                        $item_array                        = array();
                        $quickBook_id                      = $wpdb->get_var( "SELECT quickbook_id FROM {$wpdb->prefix}wpc_client_invoicing_items WHERE id = {$item['id']}" );
                        $item_array['Description']         = $item['description'];
                        $item_array['DetailType']          = 'SalesItemLineDetail';
                        $item_array['Amount']              = $item['quantity'] * $item['price'];
                        $item_array['SalesItemLineDetail'] = array(
                            'ItemRef'   => array(
                                'value' => $quickBook_id,
                                'name'  => $item['name'],
                            ),
                            'Qty'       => $item['quantity'],
                            'UnitPrice' => $item['price'],
                        );
                        $array_line_items[]                = $item_array;
                    }

                    if ( ! empty( $array_line_items ) ) {
                        $invoice_for_update = $this->wpc_update_invoice( $invoice, $array_line_items, $data['description'] );
                        unset($invoice_for_update->Deposit);
                        $resultingObj       = $dataService->Update( $invoice_for_update );
                    } else {
                        $errors_notification .= __( 'Item not updated on QuickBooks.<br/>', WPC_CLIENT_TEXT_DOMAIN );

                        return $errors_notification;
                    }

                    $error    = $dataService->getLastError();

                    if ( $error ) {
                        echo "The Status code is: " . $error->getHttpStatusCode() . "\n";
                        echo "The Helper message is: " . $error->getOAuthHelperError() . "\n";
                        echo "The Response message is: " . $error->getResponseBody() . "\n";
                        exit;
                    }

                        if ( ! is_object( $resultingObj ) ) {
                        $errors_notification .= __( 'Item not updated on QuickBooks.<br/>', WPC_CLIENT_TEXT_DOMAIN );

                        return $errors_notification;
                    }

                } else {
                    return $errors_notification;
                }

            }

            return $errors_notification;
        }


        function wpc_send_quickBook_invoice( $errors_notification, $data, $type = '', $dataService = '' ) {
            if ( 'inv' == $type ) {
                $send = ( isset( $data['send_quickBook'] ) && 1 == $data['send_quickBook'] ) ? 1 : 0;
                if ( $send ) {

                    global $wpdb;
                    if ( ! is_object( $dataService ) ) {
                        $dataService = $this->get_valid_dataService();
                    }
                    if ( ! is_object( $dataService ) ) {
                        $errors_notification .= __( 'Invoice not created on QuickBooks.<br/>', WPC_CLIENT_TEXT_DOMAIN );

                        return $errors_notification;
                    }

                    $items      = $data['items'];
                    $items_true = array();
                    $i          = 1;
                    foreach ( $items as $item ) {
                        if ( $i == 1 ) {
                            $i ++;
                            continue;
                        }
                        $items_true[] = $item;
                    }

                    //create array of items for send on QuickBooks
                    $array_line_items = array();
                    foreach ( $items_true as $item ) {
                        $item_array                        = array();
                        $quickBook_id                      = $wpdb->get_var( "SELECT quickbook_id FROM {$wpdb->prefix}wpc_client_invoicing_items WHERE id = {$item['id']}" );
                        $item_array['Description']         = $item['description'];
                        $item_array['DetailType']          = 'SalesItemLineDetail';
                        $item_array['Amount']              = $item['quantity'] * $item['price'];
                        $item_array['SalesItemLineDetail'] = array(
                            'ItemRef'   => array(
                                'value' => $quickBook_id,
                                'name'  => $item['name'],
                            ),
                            'Qty'       => $item['quantity'],
                            'UnitPrice' => $item['price'],
                        );
                        $array_line_items[]                = $item_array;
                    }


                    $customer_ids = $data['clients_id'];
                    $customer_ids = explode( ",", $customer_ids );
                    //check on the existence of customer

                    $index_id           = - 1;
                    $error_send_invoice = 0;
                    foreach ( $customer_ids as $customer_id ) {
                        $index_id ++;
                        $customer_obj_quickBook = $this->wpc_synchronize_customer_quickBook( $customer_id, $dataService );

                        if ( is_object( $customer_obj_quickBook ) && $customer_obj_quickBook->Active === 'true' ) {
                            $invoiceToCreate = Invoice::create( array(
                                "Line"         => $array_line_items,
                                "CustomerRef"  => array(
                                    "value" => $customer_obj_quickBook->Id,
                                ),
                                "CustomerMemo" => array(
                                    "value" => $data['description'],
                                ),
                                "BillEmail"    => array(
                                    'Address' => $customer_obj_quickBook->PrimaryEmailAddr->Address,
                                ),
                            ) );
                            $resultingObj    = $dataService->Add( $invoiceToCreate );

                            if ( is_object( $resultingObj ) ) {
                                if ( ! empty( $data['id'] ) ) {
                                    update_post_meta( $data['id'][ $index_id ], 'send_on_QuickBooks', '1' );
                                    update_post_meta( $data['id'][ $index_id ], 'quickBook_id', $resultingObj->Id );
                                };
                            } else {
                                $error_send_invoice ++;
                            }
                        }
                    }
                    if ( $error_send_invoice ) {
                        $errors_notification .= __( 'Some invoices were not created on QuickBooks.<br/>', WPC_CLIENT_TEXT_DOMAIN );

                        return $errors_notification;
                    }

                    $error = $dataService->getLastError();

                    if ( $error ) {
                        $errors_notification .= __( 'Invoice not created on QuickBooks.<br/>', WPC_CLIENT_TEXT_DOMAIN );

                        return $errors_notification;
                    }

                }
            }

            return $errors_notification;
        }

        function get_valid_dataService() {
            $settings = $this->settings;

            if ( 'live' == $settings['mode'] ) {
                $client_id     = $settings['live_client_id'];
                $client_secret = $settings['live_client_secret'];
            } elseif ( 'dev' == $settings['mode'] ) {
                $client_id     = $settings['dev_client_id'];
                $client_secret = $settings['dev_client_secret'];
            } else {
                return false;
            }

            $quickBook_option = get_option( 'wpc_quickBook_integration' );
            if ( empty( $quickBook_option[ $settings['mode'] ]['accessToken'] ) || empty( $quickBook_option[ $settings['mode'] ]['refreshToken'] )
                 || empty( $quickBook_option[ $settings['mode'] ]['realmId'] ) || empty( $quickBook_option['timeStamp'] ) ) {
                return false;
            }


            $dataService = DataService::Configure( array(
                'auth_mode'       => 'oauth2',
                'ClientID'        => $client_id,
                'ClientSecret'    => $client_secret,
                'accessTokenKey'  => $quickBook_option[ $settings['mode'] ]['accessToken'],
                'refreshTokenKey' => $quickBook_option[ $settings['mode'] ]['refreshToken'],
                'QBORealmID'      => $quickBook_option[ $settings['mode'] ]['realmId'],
                'baseUrl'         => ( $settings['mode'] == 'live' ) ? 'production' : 'development',
            ) );
            if ( time() > $quickBook_option['timeStamp'] ) {
                $OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();
                try {
                    $accessToken = $OAuth2LoginHelper->refreshToken();
                } catch ( Exception $e ) {
                    return false;
                }
                $accessTokenValue  = $accessToken->getAccessToken();
                $refreshTokenValue = $accessToken->getRefreshToken();

                $quickBook_option[ $settings['mode'] ]['accessToken']  = $accessTokenValue;
                $quickBook_option[ $settings['mode'] ]['refreshToken'] = $refreshTokenValue;
                $quickBook_option['timeStamp']                         = time() + 50 * 60;
                update_option( 'wpc_quickBook_integration', $quickBook_option );

                $dataService->updateOAuth2Token( $accessToken );

                $error = $dataService->getLastError();
                if ( $error ) {
                    return false;
                }

                return $dataService;
            }

            return $dataService;

        }

        function look_connection_status( $mode ) {
            $settings = $this->settings;

            if ( 'live' == $mode ) {
                $client_id     = $settings['live_client_id'];
                $client_secret = $settings['live_client_secret'];
            } elseif ( 'dev' == $mode ) {
                $client_id     = $settings['dev_client_id'];
                $client_secret = $settings['dev_client_secret'];
            } else {
                return false;
            }

            if ( empty( $client_id ) || empty( $client_secret ) ) {
                return false;
            }

            $quickBook_option = get_option( 'wpc_quickBook_integration' );
            if ( empty( $quickBook_option[ $mode ]['accessToken'] ) || empty( $quickBook_option[ $mode ]['refreshToken'] )
                 || empty( $quickBook_option[ $mode ]['realmId'] ) || empty( $quickBook_option['timeStamp'] ) ) {
                return false;
            }

            $dataService       = DataService::Configure( array(
                'auth_mode'       => 'oauth2',
                'ClientID'        => $client_id,
                'ClientSecret'    => $client_secret,
                'accessTokenKey'  => $quickBook_option[ $mode ]['accessToken'], // test error
                'refreshTokenKey' => $quickBook_option[ $mode ]['refreshToken'],
                'QBORealmID'      => $quickBook_option[ $mode ]['realmId'],
                'baseUrl'         => ( $mode == 'live' ) ? 'production' : 'development',
            ) );
            $OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();
            try {
                $OAuth2LoginHelper->refreshToken();
                $error = $dataService->getLastError();

                if ( $error ) {
                    return false;
                } else {
                    return true;
                }
            } catch ( Exception $e ) {
                return false;
            }


        }


        function wpc_send_quickBook_product( $errors_notification, $product_data, $dataService = '' ) {
            global $wpdb;
            if ( 'update' == $product_data['action'] ) {

                $quickBook_option = $wpdb->get_row( "SELECT is_active_quickbook,quickbook_id 
                FROM {$wpdb->prefix}wpc_client_invoicing_items WHERE id = {$product_data['id']}", ARRAY_A );

                if ( $quickBook_option['is_active_quickbook'] != 1 ) {
                    return $errors_notification;
                }
                if ( ! is_object( $dataService ) ) {
                    $dataService = $this->get_valid_dataService();
                }

                if ( ! is_object( $dataService ) ) {
                    $errors_notification .= __( 'Item not updated on QuickBooks.<br/>', WPC_CLIENT_TEXT_DOMAIN );

                    return $errors_notification;
                }

                $item = $dataService->FindById( "Item", $quickBook_option['quickbook_id'] );
                if ( is_object( $item ) && $item->Active == 'true' ) {
                    $item_for_update = $this->wpc_update_item( $item, $product_data );
                    $resultingObj    = $dataService->Update( $item_for_update );
                } else {
                    $errors_notification .= __( 'Item not updated on QuickBooks, because hi is not active .<br/>', WPC_CLIENT_TEXT_DOMAIN );

                    return $errors_notification;
                }
                if ( ! is_object( $resultingObj ) ) {
                    $errors_notification .= __( 'Item not updated on QuickBooks.<br/>', WPC_CLIENT_TEXT_DOMAIN );

                    return $errors_notification;
                }
            } else {
                return $errors_notification;
            }

            $error = $dataService->getLastError();

            if ( $error ) {
                $errors_notification .= __( 'Item not updated on QuickBooks.<br/>', WPC_CLIENT_TEXT_DOMAIN );

                return $errors_notification;
            }

            return $errors_notification;
        }


        //end class
    }
}