<?php

namespace BCAPP\Api;

use Exception;

use WP_Error;
use WP_REST_Server;
use WP_REST_Response;
use WC_Countries;
use WC_Shipping_Zones;
use WC_gate2play_Gateway;
use WC_Gateway_Stripe;

class Checkout
{

    /**
     * Registers a REST API route
     *
     * @since 1.0.0
     */
    public function add_api_routes()
    {

        register_rest_route(BCAPP_api_namespace, 'get-checkout-fields', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_checkout_fields'),
            'permission_callback' => array($this, 'user_permissions_check'),
        ));

        register_rest_route(BCAPP_api_namespace, 'update-shipping', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'update_shipping'),
            'permission_callback' => array($this, 'user_permissions_check'),
        ));

        register_rest_route(BCAPP_api_namespace, 'update-order-review', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'update_order_review'),
            'permission_callback' => array($this, 'user_permissions_check'),
        ));

        register_rest_route(BCAPP_api_namespace, 'checkout', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'checkout'),
            'permission_callback' => array($this, 'user_permissions_check'),
        ));

        register_rest_route(BCAPP_api_namespace, 'shipping-methods', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'shipping_methods'),
            'permission_callback' => array($this, 'user_permissions_check'),
        ));

        register_rest_route( BCAPP_api_namespace, 'zones', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'zones' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( BCAPP_api_namespace, 'get-continent-code-for-country', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_continent_code_for_country' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( BCAPP_api_namespace, 'get-allowed-countries', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_allowed_countries' ),
			'permission_callback' => '__return_true',
		) );

        register_rest_route( BCAPP_api_namespace, 'process_payment', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'beyondcart_process_payment' ),
			'permission_callback' => '__return_true',
		) );


		register_rest_route( BCAPP_api_namespace, 'payment-stripe', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'payment_stripe' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( BCAPP_api_namespace, 'payment-hayperpay', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'payment_hayperpay' ),
			'permission_callback' => '__return_true',
		) );


        register_rest_route(BCAPP_api_namespace, 'analytic', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'analytic'),
            'permission_callback' => '__return_true',
        ));

    }

    /**
	 * Add style for checkout page
     * Hooked in Loader.php
	 *
	 * @since    1.2.0
	 */
	public function enqueue_styles() {
		if ( isset( $_GET['mobile'] ) ) {
			wp_enqueue_style( 'grind-mobile-app', BCAPP_plugin_dir_url. '/Public/css/checkout.css', array(), 999, 'all' );
		}
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
     *
     * Update shipping method
     *
     * @param $request
     *
     * @return array
     * @since    1.0.0
     */
    public function update_shipping($request)
    {
        remove_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
        // remove_filter('woocommerce_package_rates', 'BCAPP\Admin\hide_shipping_method_from_site', 10, 2);
        // add_filter('woocommerce_package_rates', 'BCAPP\Admin\hide_shipping_method_from_app', 99, 2);
        $posted_shipping_methods = $request->get_param('shipping_method') ? wc_clean(wp_unslash($request->get_param('shipping_method'))) : array();
        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');

        if (is_array($posted_shipping_methods)) {
            foreach ($posted_shipping_methods as $i => $value) {
                $chosen_shipping_methods[$i] = $value;
            }
        }

        WC()->session->set('chosen_shipping_methods', $chosen_shipping_methods);

        WC()->customer->save();

        // Calculate shipping before totals. This will ensure any shipping methods that affect things like taxes are chosen prior to final totals being calculated. Ref: #22708.
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();

        // Get messages if reload checkout is not true.
        // $reload_checkout = isset(WC()->session->reload_checkout) ? true : false;

        // unset(WC()->session->refresh_totals, WC()->session->reload_checkout);
        $totals = WC()->cart->get_totals();

        if (WC()->cart->needs_payment()) {
            $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
        } else {
            $available_gateways = array();
        }
        add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
        // add_filter('woocommerce_package_rates', 'BCAPP\Admin\hide_shipping_method_from_site', 10, 2);
        // remove_filter('woocommerce_package_rates', 'BCAPP\Admin\hide_shipping_method_from_app', 99, 2);
        return array(
            'totals' => $totals,
            'payment_gateways' => $available_gateways,
        );
    }

    public function update_order_review($request)
    {
        global $WCFM, $WCFMmp;
        remove_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
        // remove_filter('woocommerce_package_rates', 'BCAPP\Admin\hide_shipping_method_from_site', 10, 2);
        // add_filter('woocommerce_package_rates', 'BCAPP\Admin\hide_shipping_method_from_app', 99, 2);
        // check_ajax_referer( 'update-order-review', 'security' );

        wc_maybe_define_constant('WOOCOMMERCE_CHECKOUT', true);

        if (WC()->cart->is_empty() && !is_customize_preview() && apply_filters('woocommerce_checkout_update_order_review_expired', true)) {
            return new WP_Error(404, __('Sorry, your session has expired.', "grind-mobile-app"));
        }

        do_action('woocommerce_checkout_update_order_review', $request->get_param('post_data') ? wp_unslash($request->get_param('post_data')) : '');

        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
        $posted_shipping_methods = $request->get_param('shipping_method') ? wc_clean(wp_unslash($request->get_param('shipping_method'))) : array();

        if (is_array($posted_shipping_methods)) {
            foreach ($posted_shipping_methods as $i => $value) {
                $chosen_shipping_methods[$i] = $value;
            }
        }

        WC()->session->set('chosen_shipping_methods', $chosen_shipping_methods);
        WC()->session->set('chosen_payment_method', empty($request->get_param('payment_method')) ? '' : wc_clean(wp_unslash($request->get_param('payment_method'))));
        WC()->customer->set_props(
            array(
                'billing_country' => $request->get_param('country') ? wc_clean(wp_unslash($request->get_param('country'))) : null,
                'billing_state' => $request->get_param('state') ? wc_clean(wp_unslash($request->get_param('state'))) : null,
                'billing_postcode' => $request->get_param('postcode') ? wc_clean(wp_unslash($request->get_param('postcode'))) : null,
                'billing_city' => $request->get_param('city') ? wc_clean(wp_unslash($request->get_param('city'))) : null,
                'billing_address_1' => $request->get_param('address') ? wc_clean(wp_unslash($request->get_param('address'))) : null,
                'billing_address_2' => $request->get_param('address_2') ? wc_clean(wp_unslash($request->get_param('address_2'))) : null,
                'billing_company' => $request->get_param('company') ? wc_clean(wp_unslash($request->get_param('company'))) : null,
            )
        );

        if (wc_ship_to_billing_address_only()) {
            WC()->customer->set_props(
                array(
                    'shipping_country' => $request->get_param('country') ? wc_clean(wp_unslash($request->get_param('country'))) : null,
                    'shipping_state' => $request->get_param('state') ? wc_clean(wp_unslash($request->get_param('state'))) : null,
                    'shipping_postcode' => $request->get_param('postcode') ? wc_clean(wp_unslash($request->get_param('postcode'))) : null,
                    'shipping_city' => $request->get_param('city') ? wc_clean(wp_unslash($request->get_param('city'))) : null,
                    'shipping_address_1' => $request->get_param('address') ? wc_clean(wp_unslash($request->get_param('address'))) : null,
                    'shipping_address_2' => $request->get_param('address_2') ? wc_clean(wp_unslash($request->get_param('address_2'))) : null,
                    'shipping_company' => $request->get_param('company') ? wc_clean(wp_unslash($request->get_param('company'))) : null,
                )
            );
        } else {
            WC()->customer->set_props(
                array(
                    'shipping_country' => $request->get_param('s_country') ? wc_clean(wp_unslash($request->get_param('s_country'))) : null,
                    'shipping_state' => $request->get_param('s_state') ? wc_clean(wp_unslash($request->get_param('s_state'))) : null,
                    'shipping_postcode' => $request->get_param('s_postcode') ? wc_clean(wp_unslash($request->get_param('s_postcode'))) : null,
                    'shipping_city' => $request->get_param('s_city') ? wc_clean(wp_unslash($request->get_param('s_city'))) : null,
                    'shipping_address_1' => $request->get_param('s_address') ? wc_clean(wp_unslash($request->get_param('s_address'))) : null,
                    'shipping_address_2' => $request->get_param('s_address_2') ? wc_clean(wp_unslash($request->get_param('s_address_2'))) : null,
                    'shipping_company' => $request->get_param('s_company') ? wc_clean(wp_unslash($request->get_param('s_company'))) : null,
                )
            );
        }

        if ($request->get_param('has_full_address') && wc_string_to_bool(wc_clean(wp_unslash($request->get_param('has_full_address'))))) {
            WC()->customer->set_calculated_shipping(true);
        } else {
            WC()->customer->set_calculated_shipping(false);
        }


        WC()->customer->save();

        // // Calculate shipping before totals. This will ensure any shipping methods that affect things like taxes are chosen prior to final totals being calculated. Ref: #22708.
        // WC()->cart->calculate_shipping();
        // WC()->cart->calculate_totals();


        unset(WC()->session->refresh_totals, WC()->session->reload_checkout);

        $shipping_methods = $this->shipping_methods();

        $totals = WC()->cart->get_totals();

        if (WC()->cart->needs_payment()) {
            $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
        } else {
            $available_gateways = array();
        }

        add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
        // add_filter('woocommerce_package_rates', 'BCAPP\Admin\hide_shipping_method_from_site', 10, 2);
        // remove_filter('woocommerce_package_rates', 'BCAPP\Admin\hide_shipping_method_from_app', 99, 2);
        wp_send_json(
            array(
                'result' => empty($messages) ? 'success' : 'failure',
                'messages' => !empty($messages) ? $messages : '',
                'nonce' => wp_create_nonce('woocommerce-process_checkout'),
                'totals' => $totals,
                'shipping_methods' => $shipping_methods,
                'payment_gateways' => $available_gateways,
            )
        );
    }

    public function get_checkout_fields()
    {
        $fields = WC()->checkout->checkout_fields;
        return $fields;
    }


    /**
     *
     * Checkout progress
     *
     * @throws Exception
     */
    public function checkout($request)
    {
        remove_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
        // remove_filter('woocommerce_package_rates', 'BCAPP\Admin\hide_shipping_method_from_site', 10, 2);
        // add_filter('woocommerce_package_rates', 'BCAPP\Admin\hide_shipping_method_from_app', 99, 2);
        wc_maybe_define_constant('WOOCOMMERCE_CHECKOUT', true);
        wc_maybe_define_constant('DOING_AJAX', true);
        add_filter('woocommerce_checkout_customer_id', function () use ($request) {
            if ($request->get_param('customer_id') && $request->get_param('customer_id') !== 'undefined') {
                return $request->get_param('customer_id'); // user id
            }
            return get_current_user_id();
        }, 9999);
        WC()->checkout()->process_checkout();
        add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
        // add_filter('woocommerce_package_rates', 'BCAPP\Admin\hide_shipping_method_from_site', 10, 2);
        // remove_filter('woocommerce_package_rates', 'BCAPP\Admin\hide_shipping_method_from_app', 99, 2);
        wp_die(0);
    }

    /**
     * Get shipping methods.
     *
     * @since    1.0.0
     */
    public function shipping_methods()
    {

        remove_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
        // remove_filter('woocommerce_package_rates', 'BCAPP\Admin\hide_shipping_method_from_site', 10, 2);
        // add_filter('woocommerce_package_rates', 'BCAPP\Admin\hide_shipping_method_from_app', 99, 2);
        // Calculate shipping before totals. This will ensure any shipping methods that affect things like taxes are chosen prior to final totals being calculated. Ref: #22708.
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();

        $packages = WC()->shipping()->get_packages();

        $first = true;
        $methods = array();

        foreach ($packages as $i => $package) {
            $chosen_method = isset(WC()->session->chosen_shipping_methods[$i]) ? WC()->session->chosen_shipping_methods[$i] : '';
            $product_names = array();

            if (count($packages) > 1) {
                foreach ($package['contents'] as $item_id => $values) {
                    $product_names[$item_id] = $values['data']->get_name() . ' &times;' . $values['quantity'];
                }
                $product_names = apply_filters('woocommerce_shipping_package_details_array', $product_names, $package);
            }

            $available_methods = array();

            foreach ($package['rates'] as $j => $value) {
                $shipping_rate_id = str_replace(':', '_', $j);
                $available_methods[] = array(
                    // 'label' => wc_cart_totals_shipping_method_label($value),
                    'courier' => !empty(get_option('woocommerce_' . $shipping_rate_id . '_settings')['shipping_courier']) ? get_option('woocommerce_' . $shipping_rate_id . '_settings')['shipping_courier'] : '',
                    'label' => $value->get_label(),
                    'price' =>  WC()->cart->display_prices_including_tax() ? $value->cost + $value->get_shipping_tax() : $value->cost,
                    'id' => $j,
                );
            }

            $methods[] = array(
                'package' => $package,
                'available_methods' => $available_methods,
                'show_package_details' => count($packages) > 1,
                'show_shipping_calculator' => is_cart() && apply_filters('woocommerce_shipping_show_shipping_calculator', $first, $i, $package),
                'package_details' => implode(', ', $product_names),
                /* translators: %d: shipping package number */
                'package_name' => apply_filters('woocommerce_shipping_package_name', ((intval($i) + 1) > 1) ? sprintf(_x('Shipping %d', 'shipping packages', 'woocommerce'), (intval($i) + 1)) : _x('Shipping', 'shipping packages', 'woocommerce'), $i, $package),
                'index' => $i,
                'chosen_method' => $chosen_method,
                'formatted_destination' => WC()->countries->get_formatted_address($package['destination'], ', '),
                'has_calculated_shipping' => WC()->customer->has_calculated_shipping(),
                'store' => !empty($package['vendor_id']) ? get_user_meta($package['vendor_id'], 'wcfmmp_profile_settings', true) : '',
            );

            $first = false;
        }
        add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
        // add_filter('woocommerce_package_rates', 'BCAPP\Admin\hide_shipping_method_from_site', 10, 2);
        // remove_filter('woocommerce_package_rates', 'BCAPP\Admin\hide_shipping_method_from_app', 99, 2);
        return $methods;
    }

    /**
     * Get All Allowed Countries
     */
    public function get_allowed_countries() {
		$countries = new WC_Countries();

		return new WP_REST_Response( $countries->get_allowed_countries() );
	}

    /**
     * Get Continent Code For a Country
     */
	public function get_continent_code_for_country( $request ) {
		$cc         = $request->get_param( 'cc' );
		$wc_country = new WC_Countries();

		wp_send_json( $wc_country->get_continent_code_for_country( $cc ) );
	}

    /**
     * Get All Shipping Zones
     */
	public function zones() {
		$delivery_zones = (array) WC_Shipping_Zones::get_zones();

		$data = [];
		foreach ( $delivery_zones as $key => $the_zone ) {

			$shipping_methods = [];

			foreach ( $the_zone['shipping_methods'] as $value ) {

				$shipping_methods[] = array(
					'instance_id'        => $value->instance_id,
					'id'                 => $value->instance_id,
					'method_id'          => $value->id,
					'method_title'       => $value->title,
					'method_description' => $value->method_description,
					'settings'           => array(
						'cost' => array(
							'value' => $value->cost
						)
					),
				);
			}

			$data[] = array(
				'id'               => $the_zone['id'],
				'zone_name'        => $the_zone['zone_name'],
				'zone_locations'   => $the_zone['zone_locations'],
				'shipping_methods' => $shipping_methods,
			);

		}

		wp_send_json( $data );
	}


    /**
	 * Find the selected Gateway, and process payment
	 *
	 * @since 1.1.0
	 */
	public function beyondcart_process_payment( $request = null ) {

		// Create a Response Object
		$response = array();

		// Get parameters
		$order_id       = $request->get_param( 'order_id' );
		$payment_method = $request->get_param( 'payment_method' );

		$error = new WP_Error();

		// Perform Pre Checks
		if ( ! class_exists( 'WooCommerce' ) ) {
			$error->add( 400, __( "Failed to process payment. WooCommerce either missing or deactivated.", 'mobile-builder' ), array( 'status' => 400 ) );

			return $error;
		}

		if ( empty( $order_id ) ) {
			$error->add( 401, __( "Order ID 'order_id' is required.", 'mobile-builder' ), array( 'status' => 400 ) );

			return $error;
		} else if ( wc_get_order( $order_id ) == false ) {
			$error->add( 402, __( "Order ID 'order_id' is invalid. Order does not exist.", 'mobile-builder' ), array( 'status' => 400 ) );

			return $error;
		} else if ( wc_get_order( $order_id )->get_status() !== 'pending' && wc_get_order( $order_id )->get_status() !== 'failed' ) {
			$error->add( 403, __( "Order status is '" . wc_get_order( $order_id )->get_status() . "', meaning it had already received a successful payment. Duplicate payments to the order is not allowed. The allow status it is either 'pending' or 'failed'. ", 'mobile-builder' ), array( 'status' => 400 ) );

			return $error;
		}
		if ( empty( $payment_method ) ) {
			$error->add( 404, __( "Payment Method 'payment_method' is required.", 'mobile-builder' ), array( 'status' => 400 ) );

			return $error;
		}

		// Find Gateway
		$avaiable_gateways = WC()->payment_gateways->get_available_payment_gateways();
		$gateway           = $avaiable_gateways[ $payment_method ];

		if ( empty( $gateway ) ) {
			$all_gateways = WC()->payment_gateways->payment_gateways();
			$gateway      = $all_gateways[ $payment_method ];

			if ( empty( $gateway ) ) {
				$error->add( 405, __( "Failed to process payment. WooCommerce Gateway '" . $payment_method . "' is missing.", 'mobile-builder' ), array( 'status' => 400 ) );

				return $error;
			} else {
				$error->add( 406, __( "Failed to process payment. WooCommerce Gateway '" . $payment_method . "' exists, but is not available.", 'mobile-builder' ), array( 'status' => 400 ) );

				return $error;
			}
		} else if ( ! has_filter( 'beyondcart_pre_process_' . $payment_method . '_payment' ) ) {
			$error->add( 407, __( "Failed to process payment. WooCommerce Gateway '" . $payment_method . "' exists, but 'REST Payment - " . $payment_method . "' is not available.", 'mobile-builder' ), array( 'status' => 400 ) );

			return $error;
		} else {

			// Pre Process Payment
			$parameters = apply_filters( 'beyondcart_pre_process_' . $payment_method . '_payment', array(
				"order_id"       => $order_id,
				"payment_method" => $payment_method
			) );

			if ( $parameters['pre_process_result'] === true ) {

				// Process Payment
				$payment_result = $gateway->process_payment( $order_id );
				if ( $payment_result['result'] === "success" ) {
					$response['code']    = 200;
					$response['message'] = __( "Payment Successful.", "grind-mobile-app" );
					$response['data']    = $payment_result;

					// Return Successful Response
					return new WP_REST_Response( $response, 200 );
				} else {
					return new WP_Error( 500, __( 'Payment Failed, Check WooCommerce Status Log for further information.', "grind-mobile-app" ), $payment_result );
				}
			} else {
				return new WP_Error( 408, __( 'Payment Failed, Pre Process Failed.', "grind-mobile-app" ), $parameters['pre_process_result'] );
			}

		}

	}


    /**
	 * Handles stripe create/update payment intent and customer
	 *
	 */
	public function payment_stripe( $request ) {
	    $response = array();
	    // Get stripe settings
		$arr_stripe = get_option("woocommerce_stripe_settings");

        $is_enabled = $arr_stripe['enabled'];

        if($is_enabled == 'yes'){
            $is_testmode = $arr_stripe['testmode'];

            if($is_testmode == 'yes'){
                $publishable_key = $arr_stripe['test_publishable_key'];
                $secret_key = $arr_stripe['test_secret_key'];
            }
            elseif ($is_testmode == 'no') {
                $publishable_key = $arr_stripe['publishable_key'];
                $secret_key = $arr_stripe['secret_key'];
            }
        }else{
            return ['error' => 'Stripe must be enabled first'];
        }


        //TODO check if class exists
        //TODO fill vars

        // Set Secret Key
        \Stripe\Stripe::setApiKey($secret_key);


        // Create or retrieve existing stripe customer
        $userId = $request->get_param('userId');

        // If we pass a user id, we should update customer, else we should create a new one
        $customer = new \WC_Stripe_Customer( $userId );
        $customer->update_or_create_customer();


        // Get total price from cart total
        $total_amt = (float) WC()->cart->total;

        // Get Currency from request
        $currency = $request->get_param('currency');

        // If we have a stripe customer, create ephemeralKey
        if($customer){
            $ephemeralKey = \Stripe\EphemeralKey::create([
                'customer' => $customer->get_id()],
                ['stripe_version' => '2020-08-27']
            );
        }


        $intentId = $request->get_param('paymentIntentId');

        // If we have an existing intent, update it, else create new intent
        if($intentId)
        {
            //Update Existing Payment Intent
             $paymentIntent = \Stripe\PaymentIntent::update(
                    $intentId,
                    array(
                        "amount" => ($total_amt * 100),
                        "currency" => $currency,
                        // "customer" => $customer->get_id(),
                    )
                );
        }else {
            //Create Payment Intent
            $paymentIntent = \Stripe\PaymentIntent::create(array(
                "amount" => ($total_amt * 100),
                "currency" => $currency,
                "customer" => $customer->get_id(),
                'automatic_payment_methods' => [
                  'enabled' => 'true',
                ],
                'description' => 'App Checkout',
            ));
        }

        $response = [
            'paymentIntentId' => $paymentIntent->id,
            'paymentIntentSecret' => $paymentIntent->client_secret,
            'ephemeralKey' => $ephemeralKey ? $ephemeralKey->secret : null,
            'customer' => $customer->get_id(),
            'publishableKey' => $publishable_key
        ];

        return new WP_REST_Response( $response );
	}



	/**
	 * Payment with HyperPay
	 *
	 * @since 1.0.5
	 */
	public function payment_hayperpay( $request ) {
		$response = array();

		$order_id             = $request->get_param( 'order_id' );
		$wc_gate2play_gateway = new WC_gate2play_Gateway();
		$payment_result       = $wc_gate2play_gateway->process_payment( $order_id );

		if ( $payment_result['result'] === "success" ) {
			$response['code']     = 200;
			$response['message']  = __( "Your Payment was Successful", "grind-mobile-app" );
			$response['redirect'] = $payment_result['redirect'];
		} else {
			$response['code']    = 401;
			$response['message'] = __( "Please enter valid card details", "grind-mobile-app" );
		}

		return new WP_REST_Response( $response );
	}


    /**
	 * Change checkout template
     * We're not using this method at the moment but it's a good idea for the future
     * to create template for the webview checkout.
     * Currently we're just injecting custom css to the website webview checkout
     * //TODO
	 */
	public function woocommerce_locate_template( $template, $template_name, $template_path ) {
		if ( 'checkout/form-checkout.php' == $template_name && isset( $_GET['mobile'] ) ) {
			return plugin_dir_path( __DIR__ ) . 'templates/checkout/form-checkout.php';
		}

		if ( 'checkout/thankyou.php' == $template_name && isset( $_GET['mobile'] ) ) {
			return plugin_dir_path( __DIR__ ) . 'templates/checkout/thankyou.php';
		}

		if ( 'checkout/form-pay.php' == $template_name && isset( $_GET['mobile'] ) ) {
			return plugin_dir_path( __DIR__ ) . 'templates/checkout/form-pay.php';
		}

		return $template;
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
}
