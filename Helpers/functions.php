<?php

/**
 *
 * Handle network request
 *
 * @param $method
 * @param $url
 * @param bool $data
 *
 * @return bool|string
 * @since    1.3.3
 */
function beyondcart_mobile_builder_request( $method, $url, $data = false ) {
	$args = array(
		'method'      => $method,
		'timeout'     => 45,
		'redirection' => 5,
		'httpversion' => '1.0',
		'blocking'    => true,
		'headers'     => array(),
		'cookies'     => array(),
		'body'        => $data,
		'compress'    => false,
		'decompress'  => true,
		'sslverify'   => false,
		'stream'      => false,
		'filename'    => null,
		'auth'        => array( 'username', 'password' ),
	);

	$response = wp_remote_request( $url, $args );

	if ( is_wp_error( $response ) ) {
		return false;
	}

	return wp_remote_retrieve_body( $response );
}

/**
 *
 * Distance matrix
 *
 * @param $origin_string
 * @param $destinations_string
 * @param $key
 * @param string $units
 *
 * @return mixed
 */
function beyondcart_mobile_builder_distance_matrix( $origin_string, $destinations_string, $key, $units = 'metric' ) {
	$google_map_api = 'https://maps.googleapis.com/maps/api';
	$url            = "$google_map_api/distancematrix/json?units=$units&origins=$origin_string&destinations=$destinations_string&key=$key";

	return json_decode( beyondcart_mobile_builder_request( 'GET', $url ) )->rows;
}

/**
 *
 * Send Notification
 *
 * @param $fields
 * @param $api_key
 *
 * @return bool|string
 */
function beyondcart_mobile_builder_send_notification( $fields, $api_key ) {
	$fields = json_encode( $fields );

	$args = array(
		'headers' => array(
			'Content-Type'  => 'application/json; charset=utf-8',
			'Authorization' => 'Basic ' . $api_key,
		),
		'body'    => $fields,
	);

	$response = wp_remote_post( "https://onesignal.com/api/v1/notifications", $args );

	if ( is_wp_error( $response ) ) {
		return false;
	}

	return wp_remote_retrieve_body( $response );
}

/**
 * Get request headers
 * @return array|false
 */
function beyondcart_mobile_builder_headers() {
	if ( function_exists( 'apache_request_headers' ) ) {
		return apache_request_headers();
	} else {

		$out = array();

		foreach ( $_SERVER as $key => $value ) {
			if ( substr( $key, 0, 5 ) == "HTTP_" ) {
				$key         = str_replace( " ", "-",
					ucwords( strtolower( str_replace( "_", " ", substr( $key, 5 ) ) ) ) );
				$out[ $key ] = $value;
			} else {
				$out[ $key ] = $value;
			}
		}

		return $out;
	}
}

/**
 * Get request headers
 * @return array|false
 */

function beyondcart_mobile_builder_token() {
	if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
		return sanitize_text_field(wp_unslash($_SERVER['HTTP_AUTHORIZATION'])); // WPCS: sanitization ok.
	}

	if ( function_exists( 'getallheaders' ) ) {
		$headers = getallheaders();
		// Check for the authoization header case-insensitively.
		foreach ( $headers as $key => $value ) {
			if ( 'authorization' === strtolower( $key ) ) {
				return $value;
			}
		}
	}

	return '';
}

/**
 * Returns true if we are making a REST API request for Mobile builder.
 *
 * @return  bool
 */
function beyondcart_mobile_builder_is_rest_api_request() {
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return false;
	}

	$rest_prefix = trailingslashit( rest_get_url_prefix() );
	$uri = sanitize_url( $_SERVER['REQUEST_URI'] );
	$allows      = array( 'grind-mobile-app/', 'wcfmmp/', 'dokan/', 'wp/', 'wc/' );

	foreach ( $allows as $allow ) {
		$check = strpos( $uri, $rest_prefix . $allow ) !== false;
		if ( $check ) {
			return true;
		}
	}

	return false;
}

/**
 * Method acts as a proxy to WooCommerce API endpoints 
 * in order to not expose consumer key and secret to the mobile app.
 * wp-json/wc/v3//customers/${id}
 * wp-json/wc/v3//orders
 * wp-json/wc/v3//products
 * wp-json/wc/v3//products/${product.id}/variations
 * wp-json/wc/v3//products/attributes
 * 
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function proxy_to_woocommerce($request) {
    $endpoint = $request->get_route();
    $params = $request->get_params();

    // Get the consumer key and secret from the secure location
    $consumer_key =  esc_attr(get_option('grind_mobile_app_woo_consumer_api_key', null));
    $consumer_secret = esc_attr(get_option('grind_mobile_app_woo_consumer_api_secret', null));;

    // Get the base URL dynamically
    $base_url = home_url('/wp-json/wc/v3');
    $url = $base_url . str_replace('/grind-mobile-app/v1', '', $endpoint);

    $args = array(
        'method' => $request->get_method(),
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($consumer_key . ':' . $consumer_secret),
        ),
        'body' => $params,
    );

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        return new \WP_Error('woocommerce_proxy_error', 'Error forwarding request to WooCommerce', array('status' => 500));
    }

    $body = wp_remote_retrieve_body($response);
    $code = wp_remote_retrieve_response_code($response);

    return new \WP_REST_Response(json_decode($body, true), $code);
}
