<?php
namespace BCAPP\Api;

use WC_Product;
use WC_Product_Variable;
use WC_REST_Products_Controller;
use WP_REST_Response;
use WP_Error;
use WP_Meta_Query;
use WP_Tax_Query;
use WP_REST_Server;


/**
 * The product-facing functionality of the plugin.
 */
class Product {

	/**
	 * Registers a REST API route
	 *
	 * @since 1.0.0
	 */
	public function add_api_routes() {

		$products = new WC_REST_Products_Controller();

		register_rest_route( BCAPP_api_namespace, 'rating-count', array(
			'methods'  => 'GET',
			'callback' => array( $this, 'rating_count' ),
			'permission_callback'   => '__return_true',
		) );

		register_rest_route( 'wc/v3', 'products-distance', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_items' ),
			'permission_callback' => array( $products, 'get_items_permissions_check' ),
		) );

		register_rest_route( BCAPP_api_namespace, 'attributes/(?P<product_id>[a-zA-Z0-9-]+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_all_product_attributes' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( BCAPP_api_namespace, 'variations/(?P<product_id>[a-zA-Z0-9-]+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_all_product_variations' ),
			'permission_callback' => '__return_true',
		) );

		//Proxy Endpoints
		register_rest_route(BCAPP_api_namespace, '/products', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => 'proxy_to_woocommerce',
			'permission_callback' => '__return_true',
		));
	
		// Register /products/{product_id}/variations endpoint
		register_rest_route(BCAPP_api_namespace, '/products/(?P<product_id>\d+)/variations', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => 'proxy_to_woocommerce',
			'permission_callback' => '__return_true',
			'args' => array(
				'product_id' => array(
					'required' => true,
					'validate_callback' => function($param, $request, $key) {
						return is_numeric($param);
					}
				),
			),
		));
	
		// Register /products/attributes endpoint
		register_rest_route(BCAPP_api_namespace, '/products/attributes', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => 'proxy_to_woocommerce',
			'permission_callback' => '__return_true',
		));

	}

	/**
	 *
	 * Get list products variable
	 *
	 * @param $request
	 *
	 * @return array|WP_Error|WP_REST_Response
	 */
	function get_all_product_attributes( $request ) {
		// Get product id from request
		$product_id = $request->get_param( 'product_id' );

		// Get all attribute
		$attributes = wc_get_attribute_taxonomies();

		// Get variation product info
		$variation_product = new WC_Product_Variable( $product_id );

		// Product variation attributes
		$variation_attributes = $variation_product->get_variation_attributes();

		// Init product attribute
		$product_attributes = array();

		foreach ( $attributes as $key => $attribute ) {

			$slug = wc_attribute_taxonomy_name( $attribute->attribute_name );

			if ( isset( $variation_attributes[ $slug ] ) ) {

				$attr = new \stdClass();

				$attr->id           = (int) $attribute->attribute_id;
				$attr->name         = $attribute->attribute_label;
				$attr->slug         = wc_attribute_taxonomy_name( $attribute->attribute_name );
				$attr->type         = $attribute->attribute_type;
				$attr->order_by     = $attribute->attribute_orderby;
				$attr->has_archives = (bool) $attribute->attribute_public;
				
                // Get attributes ordered by menu_order
                $args = array(
                    'taxonomy'   => $slug,
                    'hide_empty' => false,
                    'orderby'    => 'menu_order',
                    'order'      => 'ASC',
                    'slug'       => $variation_attributes[ $slug ],
                );
                $attr_terms = get_terms($args);
                // We want to get only the slugs, but not doing it from the args 
                // because we need the whole object below to save extra queries
                $term_slugs = array();
                foreach ($attr_terms as $term) {
                    $term_slugs[] = $term->slug;
                }
                $variation_attributes[ $slug ] = $term_slugs;

				$option_term = array();
				foreach ( $variation_attributes[ $slug ] as $value ) {
					
					// Replace old $term = get_term_by( 'slug', $value, $slug ); to save extra queries
					foreach ($attr_terms as $key => $temp_term) {
                        if ($temp_term->slug === $value) {
                            $found_key = $key;
                            break;
                        }
                    }
                    $term = $attr_terms[$found_key];
					
					// Type color
					if ( $term && $attribute->attribute_type == 'color' ) {
						$term->value = sanitize_hex_color( get_term_meta( $term->term_id, 'product_attribute_color', true ) );
					}

					// Type image
					if ( $term && $attribute->attribute_type == 'image' ) {
						$attachment_id = absint( get_term_meta( $term->term_id, 'product_attribute_image', true ) );
						$image_size    = function_exists( 'woo_variation_swatches' ) ? woo_variation_swatches()->get_option( 'attribute_image_size' ) : 'thumbnail';
						$term->value   = wp_get_attachment_image_url( $attachment_id, apply_filters( 'wvs_product_attribute_image_size', $image_size ) );
					}

					$option_term[] = $term ? $term : $value;
				}
				
				$attr->options = $option_term;
				$attr->terms   = $option_term;

				$product_attributes[] = $attr;
			}
		}

		return $product_attributes;
	}


	/**
	 *
	 * Get list products variable
	 *
	 * @param $request
	 *
	 * @return array|WP_Error|WP_REST_Response
	 */
	function get_all_product_variations($request){
		$product_id = $request->get_param('product_id');
		$handle = new WC_Product_Variable($product_id);
		$variation_attributes = $handle->get_variation_attributes();
		$variation_attributes_data = array();
		$variation_attributes_label = array();
		foreach($variation_attributes as $key => $attribute){
			$variation_attributes_result['attribute_' . sanitize_title( $key )] = $attribute;
			$variation_attributes_label['attribute_' . sanitize_title( $key )] = $key;
		}

		$data = array(
			'product_id' => $product_id,
			'variation_attributes' => $variation_attributes_result,
			'variation_attributes_label' => $variation_attributes_label
		);
		$response_data = apply_filters('beyondcart_app_product_variations_data', $data);

		return $response_data;
	}

	/**
	 *
	 * Get products items
	 *
	 * @param $request
	 *
	 * @return array|WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		global $wpdb;


		$lat = $request->get_param( 'lat' );
		$lng = $request->get_param( 'lng' );

		$productsClass = new WC_REST_Products_Controller();
		$response      = $productsClass->get_items( $request );

		if ( $lat && $lng ) {

			$ids = array();
			foreach ( $response->data as $key => $value ) {
				$ids[] = $value['id'];
			}

			// Get all locations
			$table_name    = $wpdb->prefix . 'gmw_locations';
			$query         = "SELECT * FROM $table_name WHERE object_id IN (" . implode( ',', $ids ) . ")";
			$gmw_locations = $wpdb->get_results( $query, OBJECT );

			// Calculator the distance
			$origins = [];
			foreach ( $gmw_locations as $key => $value ) {
				$origins[] = $value->latitude . ',' . $value->longitude;
			}

			$origin_string       = implode( '|', $origins );
			$destinations_string = "$lat,$lng";
			$key                 = MOBILE_BUILDER_GOOGLE_API_KEY;

			$distance_matrix = beyondcart_mobile_builder_distance_matrix( $origin_string, $destinations_string, $key );

			// map distance matrix to product
			$data = [];
			foreach ( $response->data as $key => $item ) {
				$index                   = array_search( $item['id'], array_column( $gmw_locations, 'object_id' ) );
				$item['distance_matrix'] = $distance_matrix[ $index ];
				$data[]                  = $item;
			}

			$response->data = $data;
		}

		return $response;
	}

	/**
	 * Force currency for mobile checkout
	 *
	 * @since 1.2.0
	 */
	public function mbd_wcml_client_currency( $client_currency ) {
		if ( isset( $_GET['mobile'] ) && $_GET['mobile'] == 1 && isset( $_GET['currency'] ) ) {
			$client_currency = sanitize_text_field($_GET['currency']);
		}

		return $client_currency;
	}

	/**
	 * @param $request
	 *
	 * @return array|bool|mixed|WP_Error
	 * @since    1.0.0
	 */
	public function rating_count( $request ) {
		$product_id = $request->get_param( 'product_id' );

		$result = wp_cache_get( 'rating_count' . $product_id, 'beyondcart' );

		if ( $result ) {
			return $result;
		}

		if ( $product_id ) {
			$product = new WC_Product( $product_id );

			$result = array(
				"5" => $product->get_rating_count( 5 ),
				"4" => $product->get_rating_count( 4 ),
				"3" => $product->get_rating_count( 3 ),
				"2" => $product->get_rating_count( 2 ),
				"1" => $product->get_rating_count( 1 ),
			);

			wp_cache_set( 'rating_count' . $product_id, $result, 'beyondcart' );

			return $result;
		}

		return new WP_Error(
			"product_id",
			__( "Product ID not provider.", "mobile-builder" ),
			array(
				'status' => 403,
			)
		);

	}

	/**
	 * Modify Product Object Response in REST API
	 * Apply if there are some plugins integrations
	 * @param $response
	 *
	 * @return mixed
	 * @since    1.0.0
	 */
	public function custom_change_product_response( $response, $object, $request ) {

		//	echo $request->get_param('lng');
		//	echo $request->get_param('lat'); die;

		// Variation Min/Max Prices
		$type = $response->data['type'];
		if ( $type == 'variable' ) {
			$variation_min_reg_price = $object->get_variation_regular_price('min', true);
			$variation_max_reg_price = $object->get_variation_regular_price('max', true);
			$variation_min_sale_price = $object->get_variation_sale_price('min', true);
			$variation_max_sale_price = $object->get_variation_sale_price('max', true);

			$sale_percentage_min = 0;
			if(($variation_min_reg_price -  $variation_min_sale_price) > 0) {
    			$sale_percentage_min = round( ( ( $variation_min_reg_price -  $variation_min_sale_price ) / $variation_min_reg_price ) * 100 );
			}
			$sale_percentage_max = 0;
			if(( $variation_max_reg_price -  $variation_max_sale_price ) > 0) {
    	        $sale_percentage_max = round( ( ( $variation_max_reg_price -  $variation_max_sale_price ) / $variation_max_reg_price ) * 100 );
			}

			// $price_min                   = $object->get_variation_price();
			// $price_max                   = $object->get_variation_price( 'max' );
			// $response->data['price_min'] = $price_min;
			// $response->data['price_max'] = $price_max;
			$response->data['variation_min_reg_price'] = $variation_min_reg_price;
			$response->data['variation_max_reg_price'] = $variation_max_reg_price;
			$response->data['variation_min_sale_price'] = $variation_min_sale_price;
			$response->data['variation_max_sale_price'] = $variation_max_sale_price;
			$response->data['sale_percentage_min'] = $sale_percentage_min;
			$response->data['sale_percentage_max'] = $sale_percentage_max;
		}

		// WPML and Multycurrency modifications
		global $woocommerce_wpml;
		if ( ! empty( $woocommerce_wpml->multi_currency ) && ! empty( $woocommerce_wpml->settings['currencies_order'] ) ) {

			$price = $response->data['price'];

			if ( $type == 'grouped' || $type == 'variable' ) {

				foreach ( $woocommerce_wpml->settings['currencies_order'] as $currency ) {

					if ( $currency != get_option( 'woocommerce_currency' ) ) {
						$response->data['from-multi-currency-prices'][ $currency ]['price'] = $woocommerce_wpml->multi_currency->prices->raw_price_filter( $price,
							$currency );
					}
				}
			}
		}

		// Add in_stock in default Woo endpoint (was missing) and modify  stock status based on in stock
		$response->data['in_stock'] = $object->is_in_stock();
		if ($response->data['in_stock'] == true) {
			$response->data['stock_status'] = 'instock';
		}
		if ($response->data['in_stock'] == false) {
			$response->data['stock_status'] = 'outofstock';
		}
		if ($response->data['backordered'] == true && $response->data['in_stock'] == false) {
			$response->data['stock_status'] = 'onbackorder';
		}

		//Modify the Description in case there is Visual Composer Tags
		$response->data['description'] = self::remove_vc_tags($response->data['description']);


		// Prepare Product Images
		global $_wp_additional_image_sizes;
		foreach ($response->data['images'] as $key => $image) {
			$image_urls = [];
			foreach ($_wp_additional_image_sizes as $size => $value) {
				$image_info                                = wp_get_attachment_image_src($image['id'], $size);
				$response->data['images'][$key][$size] = $image_info ? $image_info[0] : "";
			}
		}

		// Prepare Product Volume (kg or br)
		if (!empty($response->data['id'])) {
			$response->data['product_volume'] = get_post_meta($response->data['id'], 'product_volume', true);
		}

		return $response;
	}

	/**
	 * Modify ProductVariation Object Response in REST API
	 * Apply if there are some plugins integrations
	 * @param $response
	 *
	 * @return mixed
	 * @since    1.0.0
	 */
	public function custom_woocommerce_rest_prepare_product_variation_object($response)
	{

		// WPML and Multycurrency modifications
		global $woocommerce_wpml;

		if (!empty($woocommerce_wpml->multi_currency) && !empty($woocommerce_wpml->settings['currencies_order'])) {

			$response->data['multi-currency-prices'] = array();

			$custom_prices_on = get_post_meta($response->data['id'], '_wcml_custom_prices_status', true);

			foreach ($woocommerce_wpml->settings['currencies_order'] as $currency) {

				if ($currency != get_option('woocommerce_currency')) {

					if ($custom_prices_on) {

						$custom_prices = (array) $woocommerce_wpml->multi_currency->custom_prices->get_product_custom_prices(
							$response->data['id'],
							$currency
						);
						foreach ($custom_prices as $key => $price) {
							$response->data['multi-currency-prices'][$currency][preg_replace(
								'#^_#',
								'',
								$key
							)] = $price;
						}
					} else {
						$response->data['multi-currency-prices'][$currency]['regular_price'] =
							$woocommerce_wpml->multi_currency->prices->raw_price_filter(
								$response->data['regular_price'],
								$currency
							);
						if (!empty($response->data['sale_price'])) {
							$response->data['multi-currency-prices'][$currency]['sale_price'] =
								$woocommerce_wpml->multi_currency->prices->raw_price_filter(
									$response->data['sale_price'],
									$currency
								);
						}
					}
				}
			}
		}

		// Prepare Product Variation Images
		global $_wp_additional_image_sizes;
		if (!empty($response->data['image'])) {
			foreach ($_wp_additional_image_sizes as $size => $value) {
				$image_info                       = wp_get_attachment_image_src($response->data['image']['id'], $size);
				$response->data['image'][$size] = $image_info[0];
			}
		}

		// Modify response to remove 'slug' from all attributes, since Woocommerce 8.4.0 it adds slug to attributes and it breaks our app
		// We need to remove it once we update all of our apps 
		if (!empty($response->data['attributes']) && is_array($response->data['attributes'])) {
			foreach ($response->data['attributes'] as $key => $attribute) {
				if (isset($attribute['slug'])) {
					unset($response->data['attributes'][$key]['slug']);
				}
			}
		}

		$response_data = apply_filters('beyondcart_app_variations_data', $response);

		return $response_data;
	}

	/**
	 * Modify Variation Query
	 * @param $response
	 *
	 * @return mixed
	 * @since    1.3.5
	 */
	public function custom_modify_variation_object_query($args)
	{
		$args['posts_per_page'] = 200; // Set the desired limit here
		return $args;
	}

	/**
	 * Pre product attribute
	 *
	 * @param $response
	 * @param $item
	 * @param $request
	 *
	 * @return mixed
	 * @since    1.0.0
	 */
	public function custom_woocommerce_rest_prepare_product_attribute($response, $item, $request)
	{

		$taxonomy = wc_attribute_taxonomy_name($item->attribute_name);

		$options = get_terms(array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		));

		$terms = $this->term_counts($request, $taxonomy);

		foreach ($options as $key => $term) {
			if ($item->attribute_type == 'color') {
				$term->value = sanitize_hex_color(get_term_meta(
					$term->term_id,
					'product_attribute_color',
					true
				));
			}

			if ($item->attribute_type == 'image') {
				$attachment_id = absint(get_term_meta($term->term_id, 'product_attribute_image', true));
				$image_size    = function_exists('woo_variation_swatches') ? woo_variation_swatches()->get_option('attribute_image_size') : 'thumbnail';

				$term->value = wp_get_attachment_image_url(
					$attachment_id,
					apply_filters('wvs_product_attribute_image_size', $image_size)
				);
			}

			$options[$key] = $term;
		}

		$_terms = array();
		foreach ($terms as $key => $term) {
			$i = array_search($term['term_count_id'], array_column($options, 'term_id'));
			if ($i >= 0) {
				$option        = $options[$i];
				$option->count = intval($term['term_count']);
				$_terms[]      = $option;
			}
		}
		$response->data['options'] = $options;
		$response->data['terms']   = $_terms;

		return $response;
	}

	public function term_counts($request, $taxonomy)
	{
		global $wpdb;

		$term_ids = wp_list_pluck(get_terms(array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
		)), 'term_id');

		$tax_query  = array();
		$meta_query = array();

		if (isset($request['attrs']) && $request['attrs']) {
			$attrs = $request['attrs'];
			foreach ($attrs as $attr) {
				// foreach ( $attr['terms'] as $term ) {
				// 	$tax_query[] = array(
				// 		'taxonomy' => $attr['taxonomy'],
				// 		'field'    => $attr['field'],
				// 		'terms'    => $term,
				// 	);
				// }
				$tax_query[] = array(
					'taxonomy' => $attr['taxonomy'],
					'field'    => $attr['field'],
					'terms'    => $attr['terms'],
				);
			}
		}

		$meta_query     = new WP_Meta_Query($meta_query);
		$tax_query      = new WP_Tax_Query($tax_query);
		$meta_query_sql = $meta_query->get_sql('post', $wpdb->posts, 'ID');
		$tax_query_sql  = $tax_query->get_sql($wpdb->posts, 'ID');

		// Generate query.
		$query           = array();
		$query['select'] = "SELECT COUNT( DISTINCT {$wpdb->posts}.ID ) as term_count, terms.term_id as term_count_id";
		$query['from']   = "FROM {$wpdb->posts}";
		$query['join']   = "
			INNER JOIN {$wpdb->term_relationships} AS term_relationships ON {$wpdb->posts}.ID = term_relationships.object_id
			INNER JOIN {$wpdb->term_taxonomy} AS term_taxonomy USING( term_taxonomy_id )
			INNER JOIN {$wpdb->terms} AS terms USING( term_id )
			" . $tax_query_sql['join'] . $meta_query_sql['join'];

		$query['where'] = "
			WHERE {$wpdb->posts}.post_type IN ( 'product' )
			AND {$wpdb->posts}.post_status = 'publish'"
			. $tax_query_sql['where'] . $meta_query_sql['where'] .
			'AND terms.term_id IN (' . implode(',', array_map('absint', $term_ids)) . ')';

		$query['group_by'] = 'GROUP BY terms.term_id';
		$query             = apply_filters('woocommerce_get_filtered_term_product_counts_query', $query);
		$query             = implode(' ', $query);


		return $wpdb->get_results($query, ARRAY_A);
	}

	public function add_value_pa_color($response)
	{

		$term_id                 = $response->data['id'];
		$response->data['value'] = sanitize_hex_color(get_term_meta($term_id, 'product_attribute_color', true));

		return $response;
	}

	public function add_value_pa_image($response)
	{

		$term_id       = $response->data['id'];
		$attachment_id = absint(get_term_meta($term_id, 'product_attribute_image', true));
		$image_size    = woo_variation_swatches()->get_option('attribute_image_size');

		$response->data['value'] = wp_get_attachment_image_url(
			$attachment_id,
			apply_filters('wvs_product_attribute_image_size', $image_size)
		);

		return $response;
	}

	/**
	 * Decode Category names with special symbols (like dash,etc.)
	 * @param $response
	 *
	 * @return mixed
	 * @since    1.0.0
	 */
	public function custom_change_product_cat( $response ) {
		$response->data['name'] = wp_specialchars_decode( $response->data['name'] );

		return $response;
	}

	/**
	 * Decode Title names containing special symbols (like dash,etc.)
	 * @param $title
	 *
	 * @return string
	 * @since    1.0.0
	 */
	public function custom_the_title( $title ) {
		return wp_specialchars_decode( $title );
	}

	/**
	 * Remove Visual Composer and Shortcode Tags From the description
	 */
	public static function remove_vc_tags($content) {
        // Check if the required functions and classes are available
        if (function_exists('do_shortcode') && class_exists('WPBMap')) {

           // Handle [vc_raw_html] shortcode explicitly using a regular expression
            $content = preg_replace_callback(
                '/\[vc_raw_html\](.*?)\[\/vc_raw_html\]/s',
                function ($matches) {
                    // Decode the base64 content and process the shortcodes within
                    return do_shortcode(base64_decode($matches[1]));
                },
                $content
            );

             // Decode the URL-encoded content
            $content = urldecode($content);

            // Initialize the Visual Composer shortcodes
            \WPBMap::addAllMappedShortcodes();

            // Process the shortcodes and return the resulting HTML
            return do_shortcode($content);
        } else {
            // If the required functions or classes are not available, return the content as is
            return $content;
        }
    }
}
