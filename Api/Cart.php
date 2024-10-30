<?php

namespace BCAPP\Api;

use Exception;
use BCAPP\Includes\BeyondCartSessionHandler;
use WC_Cart;
use WC_Customer;
use WP_Error;
use WP_REST_Server;
use WP_REST_Request;

class Cart
{

    /**
     * Registers a REST API route
     *
     * @since 1.0.0
     */
    public function add_api_routes()
    {

        register_rest_route(BCAPP_api_namespace, 'cart', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_cart'),
                'permission_callback' => array($this, 'user_permissions_check'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'add_to_cart'),
                'permission_callback' => array($this, 'user_permissions_check'),
            ),
        ));

        register_rest_route(BCAPP_api_namespace, 'cart-total', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_total'),
            'permission_callback' => array($this, 'user_permissions_check'),
        ));

        register_rest_route(BCAPP_api_namespace, 'validate-cart', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'validate_cart'),
            'permission_callback' => array($this, 'user_permissions_check'),
        ));

        register_rest_route(BCAPP_api_namespace, 'set-quantity', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'set_quantity'),
            'permission_callback' => array($this, 'user_permissions_check'),
        ));

        register_rest_route(BCAPP_api_namespace, 'remove-cart-item', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'remove_cart_item'),
            'permission_callback' => array($this, 'user_permissions_check'),
        ));

        register_rest_route(BCAPP_api_namespace, 'add-discount', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'add_discount'),
            'permission_callback' => array($this, 'user_permissions_check'),
        ));

        register_rest_route(BCAPP_api_namespace, 'add-points-discount', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'add_points_discount'),
            'permission_callback' => array($this, 'user_permissions_check'),
        ));

        register_rest_route(BCAPP_api_namespace, 'remove-coupon', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'remove_coupon'),
            'permission_callback' => array($this, 'user_permissions_check'),
        ));

        register_rest_route(BCAPP_api_namespace, 'clear-cart', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'clear_cart'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(BCAPP_api_namespace, 'analytic', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'analytic'),
            'permission_callback' => '__return_true',
        ));
    }


    /**
     * Loads a specific cart into session and merge cart contents
     * with a logged in customer if cart contents exist.
     *
     * Triggered when "woocommerce_load_cart_from_session" is called
     * to make sure the cart from session is loaded in time.
     *
     * THIS IS FOR REST API USE ONLY!
     *
     * @access  public
     * @static
     * @since   2.1.0
     * @version 3.1.0
     */
    public static function load_cart_action()
    {

        // Do not Initialize our session handler if CoCart Plugin is  active
        if (class_exists('CoCart')) {
            return;
        }

        $cart_key = '';

        // @audit this runs the old flow if mobile param is present in the request, meaning the request is for the webview-checkout
        if (isset($_REQUEST['mobile'])) {
            self::load_cart_action_old();
            return true;
        }

        // Check if we requested to load a specific cart.
        if (isset($_REQUEST['cart_key'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $cart_key = sanitize_text_field(wp_unslash($_REQUEST['cart_key'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        }

        // Check if the user is logged in.
        if (is_user_logged_in()) {
            $customer_id = strval(get_current_user_id());

            // Compare the customer ID with the requested cart key. If they match then return error message.
            if (isset($_REQUEST['cart_key']) && $customer_id === $_REQUEST['cart_key']) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                // 	$error = new WP_Error( 'cocart_already_authenticating_user', __( 'You are already authenticating as the customer. Cannot set cart key as the user.', 'cart-rest-api-for-woocommerce' ), array( 'status' => 403 ) );
                // 	wp_send_json_error( $error, 403 );
                // 	exit;
                self::load_cart_action_old();
                return true;
            }
        } else {
            $user = get_user_by('id', $cart_key);

            // If the user exists then return error message.
            if (!empty($user) && apply_filters('cocart_secure_registered_users', true)) {
                $error = new WP_Error('cocart_must_authenticate_user', __('Must authenticate customer as the cart key provided is a registered customer.', 'cart-rest-api-for-woocommerce'), array('status' => 403));
                wp_send_json_error($error, 403);
                exit;
            }
        }

        // Get requested cart.
        $cart = WC()->session->get_session($cart_key);

        // Get current cart contents.
        $cart_contents = WC()->session->get('cart', array());

        // Merge requested cart. - ONLY ITEMS, COUPONS AND FEES THAT ARE NOT APPLIED TO THE CART IN SESSION WILL MERGE!!!
        if (!empty($cart_key)) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $merge_cart = array();

            $applied_coupons       = WC()->session->get('applied_coupons', array());
            $removed_cart_contents = WC()->session->get('removed_cart_contents', array());
            $cart_fees             = WC()->session->get('cart_fees', array());

            $merge_cart['cart']                  = isset($cart['cart']) ? maybe_unserialize($cart['cart']) : array();
            $merge_cart['applied_coupons']       = isset($cart['applied_coupons']) ? maybe_unserialize($cart['applied_coupons']) : array();
            $merge_cart['applied_coupons']       = array_unique(array_merge($applied_coupons, $merge_cart['applied_coupons'])); // Merge applied coupons.
            $merge_cart['removed_cart_contents'] = isset($cart['removed_cart_contents']) ? maybe_unserialize($cart['removed_cart_contents']) : array();
            $merge_cart['removed_cart_contents'] = array_merge($removed_cart_contents, $merge_cart['removed_cart_contents']); // Merge removed cart contents.
            $merge_cart['cart_fees']             = isset($cart['cart_fees']) ? maybe_unserialize($cart['cart_fees']) : array();

            // Check cart fees return as an array so not to crash if PHP 8 or higher is used.
            if (is_array($merge_cart['cart_fees'])) {
                $merge_cart['cart_fees'] = array_merge($cart_fees, $merge_cart['cart_fees']); // Merge cart fees.
            }

            // Checking if there is cart content to merge.
            if (!empty($merge_cart['cart'])) {
                $cart_contents = array_merge($merge_cart['cart'], $cart_contents); // Merge carts.
            }
        }

        // Merge saved cart with current cart.
        if (!empty($cart_contents) && strval(get_current_user_id()) > 0) {
            $saved_cart    = self::get_saved_cart();
            $cart_contents = array_merge($saved_cart, $cart_contents);
        }

        // Set cart for customer if not empty.
        if (!empty($cart)) {
            WC()->session->set('cart', $cart_contents);
            WC()->session->set('cart_totals', maybe_unserialize($cart['cart_totals']));
            WC()->session->set('applied_coupons', !empty($merge_cart['applied_coupons']) ? $merge_cart['applied_coupons'] : maybe_unserialize($cart['applied_coupons']));
            WC()->session->set('coupon_discount_totals', maybe_unserialize($cart['coupon_discount_totals']));
            WC()->session->set('coupon_discount_tax_totals', maybe_unserialize($cart['coupon_discount_tax_totals']));
            WC()->session->set('removed_cart_contents', !empty($merge_cart['removed_cart_contents']) ? $merge_cart['removed_cart_contents'] : maybe_unserialize($cart['removed_cart_contents']));

            if (!empty($cart['chosen_shipping_methods'])) {
                WC()->session->set('chosen_shipping_methods', maybe_unserialize($cart['chosen_shipping_methods']));
            }

            if (!empty($cart['cart_fees'])) {
                WC()->session->set('cart_fees', !empty($merge_cart['cart_fees']) ? $merge_cart['cart_fees'] : maybe_unserialize($cart['cart_fees']));
            }
        }
    } // END load_cart_from_session()

    /**
     * Get the persistent cart from the database.
     *
     * @access private
     * @static
     * @since  2.9.1
     * @return array
     */
    private static function get_saved_cart()
    {
        $saved_cart = array();

        if (apply_filters('woocommerce_persistent_cart_enabled', true)) {
            $saved_cart_meta = get_user_meta(get_current_user_id(), '_woocommerce_persistent_cart_' . get_current_blog_id(), true);

            if (isset($saved_cart_meta['cart'])) {
                $saved_cart = array_filter((array) $saved_cart_meta['cart']);
            }
        }

        return $saved_cart;
    } // END get_saved_cart()

    /**
     * Restore cart for web
     * Handles Native App with Webview Checkout only
     */
    public static function load_cart_action_old()
    {

        global $wpdb;

        $table = $wpdb->prefix . BCAPP_plugin_table_name . '_carts';
        $cart_key = '';

        // If no cart key and no user authenticated, return.
        if (!isset($_REQUEST['cart_key']) && !is_user_logged_in()) {
            return;
        }

        if (WC()->is_rest_api_request()) {
            return;
        }

        $cart_key = trim(wp_unslash(sanitize_text_field($_REQUEST['cart_key'])));

        // If user is logged in, use user ID as cart key.
        if (is_user_logged_in()) {
            $cart_key = strval(get_current_user_id());
        }
        wc_nocache_headers();

        $value = $wpdb->get_var($wpdb->prepare("SELECT cart_value FROM $table WHERE cart_key = %s", $cart_key));

        $cart_data = maybe_unserialize($value);

        // Clear old cart
        WC()->cart->empty_cart();

        // Set new cart data
        $cart = $cart_data['cart'] ?? '';
        WC()->session->set('cart', maybe_unserialize($cart));
        $cart_totals = $cart_data['cart_totals'] ?? '';
        WC()->session->set('cart_totals', maybe_unserialize($cart_totals));
        $applied_coupons = $cart_data['applied_coupons'] ?? '';
        WC()->session->set('applied_coupons', maybe_unserialize($applied_coupons));
        $coupon_discount_totals = $cart_data['coupon_discount_totals'] ?? '';
        WC()->session->set('coupon_discount_totals', maybe_unserialize($coupon_discount_totals));
        $coupon_discount_tax_totals = $cart_data['coupon_discount_tax_totals'] ?? '';
        WC()->session->set('coupon_discount_tax_totals', maybe_unserialize($coupon_discount_tax_totals));
        $removed_cart_contents = $cart_data['removed_cart_contents'] ?? '';
        WC()->session->set('removed_cart_contents', maybe_unserialize($removed_cart_contents));
        $customer = $cart_data['customer'] ?? '';
        WC()->session->set('customer', maybe_unserialize($customer));

        // @todo these maybe need to be added
        $chosen_shipping_methods = $cart_data['chosen_shipping_methods'] ?? '';
        WC()->session->set('chosen_shipping_methods', maybe_unserialize($chosen_shipping_methods));
        $cart_fees = $cart_data['cart_fees'] ?? '';
        WC()->session->set('cart_fees', maybe_unserialize($cart_fees));
    }

    /**
     *
     * Handle action after user go to checkout success page
     *
     * @param $order_id
     *
     */
    public function handle_checkout_success($order_id)
    {
        if (!session_id()) {
            session_start();
        }

        if (isset($_SESSION['cart_key']) && !is_null($_SESSION['cart_key'])) {
            global $wpdb;

            // Delete cart from database.
            $wpdb->delete($wpdb->prefix . BCAPP_plugin_table_name . '_carts', array('cart_key' => $_SESSION['cart_key']));

            // unset cart key in session
            unset($_SESSION['cart_key']);
        }
    }

    /**
     * Triggered from filter: 
     * woocommerce_persistent_cart_enabled
     */
    public function beyondcart_mobile_builder_woocommerce_persistent_cart_enabled()
    {
        return true;
    }

    /**
     * Use our own session and DB tables for our Cart
     * @throws Exception
     * @since    1.0.0
     */
    public function beyondcart_mobile_builder_pre_car_rest_api()
    {
        // Do not Initialize our session handler if CoCart Plugin is  active
        if (class_exists('CoCart')) {
            return;
        }

        if (defined('WC_VERSION') && version_compare(WC_VERSION, '3.6.0', '>=') && WC()->is_rest_api_request()) {
            require_once WC_ABSPATH . 'includes/wc-cart-functions.php';
            require_once WC_ABSPATH . 'includes/wc-notice-functions.php';


            // Disable cookie authentication REST check and only if site is secure.
            if (is_ssl()) {
                remove_filter('rest_authentication_errors', 'rest_cookie_check_errors', 100);
            }

            if (is_null(WC()->session)) {

                WC()->session = new BeyondCartSessionHandler();
                WC()->session->init();
            }

            /**
             * Choose the location save data user
             */
            if (is_null(WC()->customer)) {

                $customer_id = strval(get_current_user_id());

                // If the ID is not ZERO, then the user is logged in.
                // if ( $customer_id > 0 ) {
                //     WC()->customer = new WC_Customer( $customer_id ); // Loads from database.
                // } else {
                //     WC()->customer = new WC_Customer( $customer_id, true ); // Loads from session.
                // }

                WC()->customer = new WC_Customer($customer_id, true); // Loads from session

                add_action('shutdown', array(WC()->customer, 'save'), 10);
            }

            // Init cart if null
            if (is_null(WC()->cart)) {
                WC()->cart = new WC_Cart();
            }
        }
    }

    /**
     * Get list cart
     * @return array
     */
    public function get_cart(WP_REST_Request $request = null)
    {
        remove_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
        
        $this->switch_language($request); //WPML support

        WC()->cart->calculate_totals();
        WC()->cart->calculate_shipping();

        $items = WC()->cart->get_cart();
        $items = $this->modifyItemsData($items);

        $totals = WC()->cart->get_totals();
        $coupons = $this->getCartAppliedCoupons();
        add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);

        $data = array(
            'items' => $items,
            'totals' => $totals,
            'coupons' => $coupons,
            'cart_valid' => true,
            'message' => '',
        );

        $response_data = apply_filters('beyondcart_app_cart_data', $data);

        return $response_data;
    }


    /**
     * Clear cart
     * @return array
     */
    public function clear_cart()
    {
        remove_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
        WC()->cart->empty_cart();

        // Check if the cart is now empty
        if(!WC()->cart->is_empty()) {
            return new WP_Error( 'clear_cart_failed', 'Failed to clear cart', array( 'status' => 500 ) );
        }
 
        add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
        return true;
    }

    /**
     *
     * Method Add to cart
     *
     * @param $request
     *
     * @return array|WP_Error
     * @since    1.0.0
     */
    public function add_to_cart($request)
    {
        global $woocommerce;

        // Do not Initialize our session handler if CoCart Plugin is  active
        if (class_exists('CoCart')) {
            return;
        }

        $this->switch_language($request); //WPML support

        remove_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
        try {
            $product_id = $request->get_param('product_id');
            $quantity = $request->get_param('quantity');
            $variation_id = $request->get_param('variation_id');
            $variation = $request->get_param('variation');
            $cart_item_data = $request->get_param('cart_item_data');

            $product_addons = array(
                'quantity' => $quantity,
                'add-to-cart' => $product_id,
            );

            // Prepare data validate add-ons
            if (isset($cart_item_data['addons']) && !is_null($cart_item_data['addons'])) {
                foreach ($cart_item_data['addons'] as $addon) {
                    $product_addons['addon-' . $addon['field_name']][] = $addon['value'];
                }
            }

            $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity, $product_addons);

            if ($passed_validation) {
                global $woocommerce;
                $cart_item_key = $woocommerce->cart->add_to_cart($product_id, $quantity, $variation_id, $variation, $cart_item_data);
            }

            if (!$passed_validation || !$cart_item_key) {
                add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
                //if validation failed or add to cart failed, return response from woocommerce
                return new WP_Error('add_to_cart', htmlspecialchars_decode(strip_tags(wc_print_notices(true))), array(
                    'status' => 403,
                    'message' => $cart_item_key . ' ----passed:' . $passed_validation . ' ----productid:' . $product_id .  ' ----quantity:' . $quantity.' ----addons:' . json_encode($product_addons)
                ));
            }

            $cart_key = WC()->session->get_cart_key();
            $cart = $this->get_cart();
            add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
            return array(
                "cart_key" => $cart_key,
                "cart" => $cart,
                'message' => $cart_item_key . ' ----passed:' . $passed_validation . ' ----productid:' . $product_id .  ' ----quantity:' . $quantity.' ----addons:' . json_encode($product_addons)
            );
        } catch (\Exception $e) {
            add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
            //do something when exception is thrown
            return new WP_Error('add_to_cart', $e->getMessage(), array(
                'status' => 403,
            ));
        }
    }


    /**
     * Get total cart
     * @return array
     * @since    1.0.0
     */
    public function get_total()
    {
        remove_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
        $totals = WC()->cart->get_totals();
        add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
        return $totals;
    }

    /**
     * Validate the current cart before proceeding to webview checkout.
     * Checks for product stock availability and promo code validity.
     * Removes out-of-stock products and invalid promo codes.
     *
     * @param WP_REST_Request $request
     * @return array
     * @since    1.7.7
     */
    public function validate_cart(WP_REST_Request $request)
    {
        $removed_products = [];
        $removed_coupons = [];
    
        // Check each cart item for stock availability
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $_product = $cart_item['data'];
    
            if (!$_product->is_in_stock() || ($_product->managing_stock() && $_product->get_stock_quantity() < $cart_item['quantity'])) {
                WC()->cart->remove_cart_item($cart_item_key);
                $removed_products[] = $_product->get_name();
            }
        }
    
        // Check each applied coupon for validity
        foreach (WC()->cart->get_applied_coupons() as $coupon_code) {
            $coupon = new \WC_Coupon($coupon_code);
            remove_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
            if (!$coupon->is_valid()) {
                WC()->cart->remove_coupon($coupon_code);
                $removed_coupons[] = $coupon_code;
            }
            add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
        }
    
        // Recalculate totals after modifications
        WC()->cart->calculate_totals();
    
        // Prepare response data
        $response_data = $this->get_cart($request); 
    
        if (!empty($removed_products) || !empty($removed_coupons)) {
            $response_message = '';
            if (!empty($removed_products)) {
                $response_message .= __('', "grind-mobile-app") . implode(', ', $removed_products) . '.';
            }
            if (!empty($removed_coupons)) {
                $response_message .= __('', "grind-mobile-app") . implode(', ', $removed_coupons) . '.';
            }
            $message = $response_message;
            $response_data['cart_valid'] = false;
        } else {
            $message = 'Your cart is up to date.';
            $response_data['cart_valid'] = true;
        }
        
        $response_data['message'] = $message;
        
        // Return WP_REST_Response
        return $response_data;
    }

    /**
     *
     * Set cart item quantity
     *
     * @param $request
     *
     * @return Array | WP_Error
     * @since    1.0.0
     */
    public function set_quantity($request)
    {
        remove_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);

        $this->switch_language($request); //WPML support

        $cart_item_key = $request->get_param('cart_item_key') ? wc_clean(wp_unslash($request->get_param('cart_item_key'))) : '';
        $quantity = $request->get_param('quantity') ? wc_clean(wp_unslash($request->get_param('quantity'))) : 1;
        $cart_item = WC()->cart->get_cart_item($cart_item_key);
        
        if (!$cart_item_key || !$cart_item) {
            add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
            return new WP_Error(
                'set_quantity_error',
                __('Cart item or cart item key not exist.', "grind-mobile-app")
            );
        }
        
        $product = $cart_item['data'];
        $stock_qty = $product->get_stock_quantity();

        if (!is_null($stock_qty) && $quantity > $stock_qty) {
            add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
            return new WP_Error(
                'set_quantity_error',
                $product->get_title() . __(' not enough stock. Available stock: ', "grind-mobile-app") . $stock_qty,
                array('cart_item_key' => $cart_item_key, 'available_stock' => $stock_qty)
            );
        }

        if (0 === $quantity || $quantity < 0) {
            add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
            return new WP_Error(
                'set_quantity_error',
                __('The quantity not validate', "grind-mobile-app")
            );
        }

        try {
            $success = WC()->cart->set_quantity($cart_item_key, $quantity);
            $cart = $this->get_cart();
            add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
            return array(
                "success" => $success,
                "cart" =>  $cart,
            );
        } catch (Exception $e) {
            add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
            return new WP_Error(
                'set_quantity_error',
                $e->getMessage()
            );
        }
    }

    /**
     *
     * Remove cart item
     *
     * @param $request
     *
     * @return Array |WP_Error
     * @since    1.0.0
     */
    public function remove_cart_item($request)
    {
        remove_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);

        $this->switch_language($request); //WPML support

        $cart_item_key = $request->get_param('cart_item_key') ? wc_clean(wp_unslash($request->get_param('cart_item_key'))) : '';

        if (!$cart_item_key) {
            add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
            return new WP_Error(
                'remove_cart_item',
                __('Cart item key not exist.', "grind-mobile-app")
            );
        }


        try {
            $success = WC()->cart->remove_cart_item($cart_item_key);
            $cart = $this->get_cart();
            add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
            return array(
                "success" => $success,
                "cart" =>  $cart,
            );
        } catch (Exception $e) {
            add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
            return new WP_Error(
                'set_quantity_error',
                $e->getMessage()
            );
        }
    }

    /**
     *
     * Add coupon code
     *
     * @param $request
     *
     * @return Array |WP_Error
     * @since 1.0.0
     */
    public function add_discount($request)
    {
        remove_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
        $coupon_code = $request->get_param('coupon_code') ? wc_format_coupon_code(wp_unslash($request->get_param('coupon_code'))) : "";

        $this->switch_language($request); //WPML support

        if (!$coupon_code) {
            add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
            return new WP_Error(
                'add_discount',
                __('Coupon not exist.', "grind-mobile-app")
            );
        }

        try {

            $success = WC()->cart->add_discount($coupon_code);
            $totals = WC()->cart->get_totals();
            $coupons = $this->getCartAppliedCoupons();

            add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);

            // Get cart items, $items used only for Woodenspoon app
            $items = WC()->cart->get_cart();
            $items = $this->modifyItemsData($items);

            $data = array(
                "success" => $success,
                "items" => $items,
                "totals" => $totals,
                "coupons" => $coupons,
            );

            $response_data = apply_filters('beyondcart_app_cart_data_apply_coupon', $data);
            return $response_data;

        } catch (Exception $e) {
            add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);

            return new WP_Error(
                'set_quantity_error',
                $e->getMessage()
            );
        }
    }


    /**
     *
     * Add coupon code
     *
     * @param $request
     *
     * @return Array |WP_Error
     * @since 1.0.0
     */
    public function add_points_discount($request)
    {
        if (!class_exists('ParkmartPoints')) {
            return false;
        }

        $user_id = get_current_user_id();

        if(!$user_id) {
            return new WP_Error(
                'Points Error',
                'User not logged in'
            );
        }

        $max_points = $request->get_param('ywpar_points_max');
        $input_points = $request->get_param('ywpar_input_points');
        
        $params = array(
            "ywpar_points_max" => $max_points,
            "ywpar_max_discount" => $max_points / 100,
            "ywpar_rate_method" => 'fixed',
            "ywpar_input_points" => $input_points,
            "ywpar_input_points_check" => 1,
        );
        
        $coupon_name = 'ywpar_discount_' . $user_id;


        // Create an instance of the ParkmartPoints class
        $parkmart_points = new \ParkmartPoints();
        
        // Attempt to create or update the coupon based on points
        $success_coupon = $parkmart_points->create_or_update_coupon($user_id, $input_points, $params);
        if (!$success_coupon) {
            WC()->cart->calculate_totals();
            $coupons = $this->getCartAppliedCoupons();
            $totals = WC()->cart->get_totals();
    
            return array(
                "totals" => $totals,
                'coupons' => $coupons,
            );
        }
        
        
        remove_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
        
        //$success = WC()->cart->add_discount($coupon_name);
        $this->apply_points_coupon_first($coupon_name);
        WC()->cart->calculate_totals();
        $coupons = $this->getCartAppliedCoupons();
        $totals = WC()->cart->get_totals();
        
        add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);


        return array(
            "totals" => $totals,
            'coupons' => $coupons,
        );
    }


    /**
     *
     * Remove coupon code
     *
     * @param $request
     *
     * @return Array |WP_Error
     * @since 1.0.0
     */
    public function remove_coupon($request)
    {
        remove_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
        $coupon_code = $request->get_param('coupon_code') ? wc_format_coupon_code(wp_unslash($request->get_param('coupon_code'))) : "";

        $this->switch_language($request); //WPML support

        if (!$coupon_code) {
            add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
            return new WP_Error(
                'remove_coupon',
                __('Coupon not exist.', "grind-mobile-app")
            );
        }

        try {
            $status = WC()->cart->remove_coupon($coupon_code);
            WC()->cart->calculate_totals();
            $totals = WC()->cart->get_totals();
            add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);

            // Get cart items, $items used only for Woodenspoon app
            $items = WC()->cart->get_cart();
            $items = $this->modifyItemsData($items);

            $coupons = $this->getCartAppliedCoupons();

            $data =array(
                "items" => $items,
                "success" => $status,
                "totals" => $totals,
                "coupons" => $coupons,
            );
            $response_data = apply_filters('beyondcart_app_cart_data_remove_coupon', $data);
            return $response_data;

        } catch (Exception $e) {
            add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
            return new WP_Error(
                'set_quantity_error',
                $e->getMessage()
            );
        }
    }

    public function analytic($request)
    {
        $headers = beyondcart_mobile_builder_headers();
        // TODO да се изчистят константите отдолу, ако се ползва
        $data = array(
            "authStatus" => false,
            "WooCommerce" => false,
            "wcfm" => class_exists('WCFM'),
            "jwtAuthKey" => defined('MOBILE_BUILDER_JWT_SECRET_KEY'),
            "googleMapApiKey" => defined('MOBILE_BUILDER_GOOGLE_API_KEY'),
            "facebookAppId" => defined('BCAPP_FB_APP_ID'),
            "facebookAppSecret" => defined('BCAPP_FB_APP_SECRET'),
            "oneSignalId" => defined('BCAPP_ONESIGNAL_APP_ID'),
            "oneSignalApiKey" => defined('BCAPP_ONESIGNAL_API_KEY'),
        );

        if (isset($headers['Authorization']) && $headers['Authorization'] == "Bearer test") {
            $data['authStatus'] = true;
        }

        if (class_exists('WooCommerce')) {
            $data['WooCommerce'] = true;
        }

        return $data;
    }

    /**
     * Switches the language based on the 'lang' parameter.
     * This is used for WPML and Polylang Integrations in Cart actions 
     *
     * @param WP_REST_Request $request
     */
    private function switch_language($request) {
        
        if(is_null($request)) {
            return;
        }
        
        $lang = $request->get_param('lang');
        if (!empty($lang)) {
            if (function_exists('pll_switch_language')) {
                // For Polylang
                pll_switch_language($lang);
            } elseif (isset($GLOBALS['sitepress'])) {
                // For WPML
                $GLOBALS['sitepress']->switch_lang($lang);
            }
        }
    }

    /**
     * Helper method to modify Items Data (WC()->cart->get_cart();)
     * and add thumbs, images, modify prices, stock, slug and other item data
     *
     * @param array $items
     */
    private function modifyItemsData($items) {
        

        foreach ($items as $cart_item_key => $cart_item) {
            $_product = $cart_item['data'];
            $vendor_id = '';

            if (function_exists('wcfm_get_vendor_id_by_post')) {
                $vendor_id = wcfm_get_vendor_id_by_post($_product->get_id());
            }

            $image = wp_get_attachment_image_src(get_post_thumbnail_id($_product->get_id()), 'single-post-thumbnail');
            if ($_product && $_product->exists() && $cart_item['quantity'] > 0) {

                if (!empty($cart_item['variation'])) {
                    $variation_attributes = [];
                    // loop through product attributes
                    foreach ($cart_item['variation'] as $attribute => $term_name) {
                        $taxonomy       = str_replace('attribute_', '', $attribute);
                        $attribute_name = wc_attribute_label($taxonomy);
                        $term_name      = get_term_by('slug', $term_name, $taxonomy)->name;

                        $variation_attributes[$attribute_name] = $term_name;
                    }
                    $items[$cart_item_key]['variation_attributes'] = $variation_attributes;
                }

                if (WC()->cart->display_prices_including_tax()) {
                    $product_price = wc_get_price_including_tax($_product);
                } else {
                    $product_price = wc_get_price_excluding_tax($_product);
                }

                $items[$cart_item_key]['thumbnail'] = $_product->get_image();
                $items[$cart_item_key]['thumb'] = $image[0] ?? '';
                $items[$cart_item_key]['is_sold_individually'] = $_product->is_sold_individually();
                $items[$cart_item_key]['name'] = $_product->get_name();
                $items[$cart_item_key]['price'] = $product_price;
                $items[$cart_item_key]['price_html'] = WC()->cart->get_product_price($_product);
                $items[$cart_item_key]['vendor_id'] = $vendor_id;
                $items[$cart_item_key]['store'] = $vendor_id ? $store_user = get_user_meta($vendor_id, 'wcfmmp_profile_settings', true) : null;
                $items[$cart_item_key]['stock'] = $_product->get_stock_quantity();
                $items[$cart_item_key]['slug'] = $_product->get_slug();
            }
        }

        return $items;
    }


    /**
     *
     * Check user logged in
     *
     * @param $request
     *
     * @return bool
     * @since 1.0.0
     */
    public function user_permissions_check($request)
    {
        return true;
    }

    /**
     * Get Cart Applied Coupons, modify if it's object to return array of coupon codes names
     * Since Woocommerce 7.7.0 there is a problem with the coupons applied to the cart in the app
     * It get_applied_coupons returns an object instead of an array
     */
    public function getCartAppliedCoupons()
    {
        $appliedCoupons = WC()->cart->get_applied_coupons();
        $new_coupons = array();
        // Check if the coupons data is an object 
        // Always Convert the object to an array of coupon names
        foreach ( $appliedCoupons as $key => $value ) {
            array_push($new_coupons, $value);
        }
        return $new_coupons;
    }
    
    /**
     * Used only for points coupons and solving problems with other coupons applied before
     */ 
    public function apply_points_coupon_first($ywpar_coupon_code) {
        $applied_coupons = WC()->cart->get_applied_coupons();
        $non_ywpar_coupons = [];
    
        // Remove all currently applied coupons
        foreach ($applied_coupons as $coupon_code) {
            WC()->cart->remove_coupon($coupon_code);
            if ($coupon_code !== $ywpar_coupon_code) {
                $non_ywpar_coupons[] = $coupon_code;
            }
        }
        // Apply 'ywpar' coupon first if it exists among the previously applied coupons
        WC()->cart->add_discount($ywpar_coupon_code);
    
        // Re-apply the other coupons
        foreach ($non_ywpar_coupons as $coupon_code) {
            WC()->cart->add_discount($coupon_code);
        }
    }
}
