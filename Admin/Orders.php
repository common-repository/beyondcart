<?php
namespace BCAPP\Admin;

class Orders
{
    /**
     * Show Sales Channel In Admin Order Listing
     * Is the order from Website or App
     */
    public static function addSalesChannelFieldInOrder()
    {
         /**
         * If the store is NOT using HPOS we use the default hooks
         */ 
        add_filter( 'manage_edit-shop_order_columns', function ( $columns ) {
            $new_columns = array();
            foreach ( $columns as $column_name => $column_info ) {
                $new_columns[ $column_name ] = $column_info;
                if ( 'order_total' === $column_name ) {
                    $new_columns['sales_channel'] = __( 'Sales Channel', 'grind-mobile-app' );
                    
                }
            }
            return $new_columns;
        }, 20 );
        
        add_action( 'manage_shop_order_posts_custom_column', function ( $column ) {
            global $post;
            if ( 'sales_channel' === $column ) {
                $order = wc_get_order( $post->ID );
                $order_from_mobile_app = get_post_meta( $order->get_id(), '_mobile_app', true );
                $order_from_mobile_app_proofnutrition = get_post_meta( $order->get_id(), 'mobile', true ); // Proofnutrition fix, store in other meta field
                if($order_from_mobile_app == 'Yes' || $order_from_mobile_app == 1 || $order_from_mobile_app == '1') {
                    echo 'Mobile App';
                }
                elseif($order_from_mobile_app_proofnutrition == 'Yes' || $order_from_mobile_app_proofnutrition == 1 || $order_from_mobile_app_proofnutrition == '1') {
                    echo 'Mobile App';
                }
                else {
                    echo 'Website';
                }
            }
        });
        
        /**
         * If the store is using HPOS we need to use different hooks
         * https://rudrastyh.com/woocommerce/columns.html
         */ 
         add_filter( 'manage_woocommerce_page_wc-orders_columns', function ( $columns ) {
            $new_columns = array();
            foreach ( $columns as $column_name => $column_info ) {
                $new_columns[ $column_name ] = $column_info;
                if ( 'order_total' === $column_name ) {
                    $new_columns['sales_channel'] = __( 'Sales Channel', 'grind-mobile-app' );
                    
                }
            }
            return $new_columns;
        }, 20 );
        
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', function ( $column_name, $order ) {
            if ( 'sales_channel' === $column_name ) {
                $order_from_mobile_app = get_post_meta( $order->get_id(), '_mobile_app', true );
                $order_from_mobile_app_proofnutrition = get_post_meta( $order->get_id(), 'mobile', true ); // Proofnutrition fix, store in other meta field
                
                // If it's empty, double check for the other meta field that we store information. There is a problem after Woo 8.5.0 that doesn't store in our custom _mobile meta field.
                if(!$order_from_mobile_app){
                    $order_from_mobile_app = get_post_meta( $order->get_id(), '_billing_mobile_app', true );
                }
                
                if($order_from_mobile_app == 'Yes' || $order_from_mobile_app == 1 || $order_from_mobile_app == '1') {
                    echo 'Mobile App';
                }
                elseif($order_from_mobile_app_proofnutrition == 'Yes' || $order_from_mobile_app_proofnutrition == 1 || $order_from_mobile_app_proofnutrition == '1') {
                    echo 'Mobile App';
                }
                else {
                    echo 'Website';
                }
            }
        }, 25, 2);

        // Make custom column sortable
        // add_filter( "manage_edit-shop_order_sortable_columns", function ( $columns )
        // {
        //     $meta_key = '_mobile_app';
        //     return wp_parse_args( array('sales_channel' => $meta_key), $columns );
        // });

        // Make sorting work properly (by numerical values)
        // add_action('pre_get_posts', function ( $query ) {
        //     global $pagenow;

        //     if ( 'edit.php' === $pagenow && isset($_GET['post_type']) && 'shop_order' === $_GET['post_type'] ){

        //         $orderby  = $query->get( 'orderby');
        //         $meta_key = '_mobile_app';

        //         if ('_mobile_app' === $orderby){
        //             $query->set( 'meta_query', array(
        //                 'relation' => 'OR',
        //                 array(
        //                     'key' => $meta_key,
        //                     'compare' => 'EXISTS'
        //                 ),
        //                 array(
        //                     'key' => $meta_key,
        //                     'compare' => 'NOT EXISTS'
        //                 )
        //             ) );
        //             $query->set('orderby', 'meta_value');
        //         }
        //     }
        // } );
    }

    /**
     * Add new hidden field in Web Checkout in order to detect if
     * the order is comming from the app
     */
    public static function newHiddenFieldInWebCheckout()
    {

        /**
         * Checkout fields management
         */
        add_filter('woocommerce_checkout_fields', function ($fields) {

            $fields['billing']['billing_mobile_app'] = array(
                'type'      => 'hidden',
                'clear'     => true,
                'default' => !empty($_REQUEST['mobile']) ? sanitize_text_field($_REQUEST['mobile']) : '',
            );
            return $fields;
        }, 9999999, 1);

        add_filter( 'woocommerce_checkout_get_value', function ( $value, $input ) {
            $item_to_set_null = array(
                    'billing_mobile_app',
                ); // All the fields in this array will be set as empty string, add or remove as required.
            if (in_array($input, $item_to_set_null)) {
                $value = !empty($_REQUEST['mobile']) ? sanitize_text_field($_REQUEST['mobile']) : '';
            }
        
            return $value;
        }, 9999999, 2 );

        /**
         * Save custom field mobile  after checkout submit
         * and show in order inner page in the back office
         */
        add_action('woocommerce_checkout_update_order_meta', function ($order_id ) {
            // not working with update_post_meta when HPOS feature is active
            $order = wc_get_order($order_id);
            $mobile_app_value = $_POST['billing_mobile_app'] || 
                                (isset($_COOKIE['mobile']) && $_COOKIE['mobile'] == 1) || 
                                (isset($_COOKIE['beyondcart_webview']) && $_COOKIE['beyondcart_webview'] === 'on') ? 'Yes' : 'No';
            $order->update_meta_data('_mobile_app', $mobile_app_value);
            $order->save();
        }, 99999999);
        /**
         * Mobile app in WooCommerce Admin - Orders
         * in Order inner page in back office
         */
        add_action('woocommerce_admin_order_data_after_billing_address', function ($order) {
            $is_it_mobile_app_order = get_post_meta( $order->get_id(), '_mobile_app', true );
            
            // double check for the other meta field we store info
            if(!$is_it_mobile_app_order) {
                $is_it_mobile_app_order = get_post_meta( $order->get_id(), '_billing_mobile_app', true );
            }
            echo '<p><strong>' . __( 'Mobile App', 'grind-mobile-app' ) . ':</strong> ' . esc_html($is_it_mobile_app_order) . '</p>';
        }, 10, 1);
    }

     /**
     * Checks for mobile_app meta in order and modifies created_via in response
     */
    public static function updateCreatedViaOrderRestResponse()
    {
        add_filter( "woocommerce_rest_prepare_shop_order_object", function ( $response, $object, $request ) {
        
            $mobile_app = get_post_meta( $object->get_id(), '_mobile_app', true );
            if($mobile_app == "Yes" || $mobile_app == "1"){
                $response->data['created_via'] = 'app';
                return $response;
            }

            // Proofnutrition fix ... store if from app in other meta field
            $mobile_app = get_post_meta( $object->get_id(), 'mobile', true );
            if($mobile_app == 1 || $mobile_app == "1" || $mobile_app == "Yes"){
                $response->data['created_via'] = 'app';
                return $response;
            }

            return $response;
        }  , 10, 3 );
    }
}
