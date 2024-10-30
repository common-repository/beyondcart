<?php
namespace BCAPP\Api;

use BCAPP\Admin\Api as AdminApi;
use Exception;
use WP_Error;
use WP_REST_Server;

class Common {


	/**
	 * Registers a REST API route
	 *
	 * @since 1.0.0
	 */
	public function add_api_routes() {

		register_rest_route( BCAPP_api_namespace, 'settings', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'settings' ),
			'permission_callback' => '__return_true',
		) );

	}


	/**
	 * Registers a REST API fields
	 * @since 1.4
	 */
	public function add_api_fields() {

		/**
		 * Add categories in REST API post response
		 * @since 1.1.0
		 */
		register_rest_field( 'post', '_categories', array(
			'get_callback' => function ( $post ) {
				$cats = array();
				foreach ( $post['categories'] as $c ) {
					$cat    = get_category( $c );
					$cats[] = $cat->name;
				}

				return $cats;
			},
		) );

		/**
		 * Add feature image in REST API post response
		 * @since 1.1.0
		 */
		register_rest_field( 'post', 'featured_image_url',
			array(
				'get_callback'    => array( $this, 'get_featured_media_url' ),
				'update_callback' => null,
				'schema'          => null,
			)
		);
	}


	/**
	 * Get BeyondCart Settings & Template Config
	 */
	public function settings( $request ) {

		$decode = $request->get_param( 'decode' );

		$result = wp_cache_get( 'settings_' . $decode, 'beyondcart' );

		if ( $result ) {
			return $result;
		}

		try {
			global $woocommerce_wpml;

			$currencies = array();

			$languages    = apply_filters( 'wpml_active_languages', array(), 'orderby=id&order=desc' );
			$default_lang = apply_filters( 'wpml_default_language', substr( get_locale(), 0, 2 ) );

			$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';

			if ( ! empty( $woocommerce_wpml->multi_currency ) && ! empty( $woocommerce_wpml->settings['currencies_order'] ) ) {
				$currencies = $woocommerce_wpml->multi_currency->get_currencies( 'include_default = true' );
			}

			$app_configs = maybe_unserialize( get_option( 'grind_mobile_app_configs', array(
				"requireLogin"       => false,
				"toggleSidebar"      => false,
				"isBeforeNewProduct" => 5
			) ) );

			$configs = apply_filters('beyondcart_app_configs', $app_configs);

			$gmw = get_option( 'gmw_options' );

			$templates      = array();
			$templates_data = AdminApi::template_configs();

			if ( $decode ) {
				foreach ( $templates_data as $template ) {
					$template->data     = json_decode( $template->data );
					$template->settings = json_decode( $template->settings );
					$templates[]        = $template;
				}
			}

			$result = array(
				'language'               => $default_lang,
				'languages'              => $languages,
				'currencies'             => $currencies,
				'currency'               => $currency,
				'enable_guest_checkout'  => get_option( 'woocommerce_enable_guest_checkout', true ),
				'timezone_string'        => get_option( 'timezone_string' ) ? get_option( 'timezone_string' ) : wc_timezone_string(),
				'date_format'            => get_option( 'date_format' ),
				'time_format'            => get_option( 'time_format' ),
				'configs'                => $configs,
				'default_location'       => $gmw ? $gmw['post_types_settings'] : $gmw,
				'templates'              => $decode ? $templates : $templates_data,
				'checkout_user_location' => apply_filters( 'wcfmmp_is_allow_checkout_user_location', true ),
			);

			wp_cache_set( 'settings_' . $decode, $result, 'beyondcart' );

			wp_send_json( $result );
		} catch ( Exception $e ) {
			return new WP_Error(
				'error_setting',
				__( 'Something wrong.', "grind-mobile-app" ),
				array(
					'status' => 403,
				)
			);
		}
	}

	/**
	 * Get Feature Image URL
	 */
	public function get_featured_media_url( $object, $field_name, $request ) {
		$featured_media_url = '';
		$image_attributes   = wp_get_attachment_image_src(
			get_post_thumbnail_id( $object['id'] ),
			'full'
		);
		if ( is_array( $image_attributes ) && isset( $image_attributes[0] ) ) {
			$featured_media_url = (string) $image_attributes[0];
		}

		return $featured_media_url;
	}


	/**
	 * Check if a given request has access to read a customer.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|boolean
	 *
	 * @since 1.3.4
	 */
	public function update_item_permissions_check( $request ) {
		$id = (int) $request['id'];

		if ( get_current_user_id() != $id ) {
			return new WP_Error( 'mobile_builder', __( 'Sorry, you cannot change info.', "grind-mobile-app" ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Modify the post description directly in the WordPress REST API endpoint /wp-json/wp/v2/posts
	 * and remove Visual Composer tags from the content
	 */
	public function customize_rest_api_post_response($response, $post, $request) {
		// Check if the description field is set in the response
		if (isset($response->data['content']['rendered'])) {
			// Use your existing method to clean up the description
			$clean_content = Product::remove_vc_tags($response->data['content']['rendered']);
			
			// Update the description in the response
			$response->data['content']['rendered'] = $clean_content;
		}

		// Check if the excerpt field is set in the response and clean it
		if (isset($response->data['excerpt']['rendered'])) {
			$clean_excerpt = Product::remove_vc_tags($response->data['excerpt']['rendered']);
			$response->data['excerpt']['rendered'] = $clean_excerpt;
		}
	
		return $response;
	}
}
