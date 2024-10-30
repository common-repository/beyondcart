<?php

namespace BCAPP\Api;

use WC_REST_Products_Controller;
use WP_Meta_Query;
use WP_REST_Server;
use WP_Tax_Query;
use WC_Product_Query;
use BCAPP\Includes\Integrations\FlycartWooDiscountRules\FlycartWooDiscountRulesIntegration as FlycartWooDiscountRules;

class Category
{

	/**
	 * Registers a REST API route
	 *
	 * @since 1.0.0
	 */
	public function add_api_routes()
	{

		$product = new WC_REST_Products_Controller();

		register_rest_route('wc/v3', 'min-max-prices', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array($this, 'get_min_max_prices'),
			'permission_callback' => array($product, 'get_items_permissions_check'),
		));

		// Legacy endpoint, after most of the users updated the app to the last version we'll remove it
		register_rest_route(BCAPP_api_namespace, 'filter_products', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array($this, 'getProductsWithFilter'),
			'permission_callback' => '__return_true',
		));

		register_rest_route( BCAPP_api_namespace, 'categories', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'categories' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route(BCAPP_api_namespace, 'get-terms-by-cat-id', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array($this, 'getCachedTermsByCategoryId'),
			'permission_callback' => '__return_true',
		));
	}


	/**
	 * Get products with filter
	 * Modify Woocommerce Default Query To Work With Our Filters
	 * /wp-json/wc/v3/products?
	 * category=19&per_page=35&orderby=date&order=desc&page=1&attributes[pa_marki]=armani&attributes[pa_test]=red&status=publish
	 * &consumer_key=00000000&consumer_secret=00000&lang=bg
	 * Basically every Category, Category Filter, Search, Product Inner works with this method
	 * @link https://woocommerce.github.io/woocommerce-rest-api-docs/#list-all-products
	 * @param request
	 * @return object
	 */
	public function modify_rest_products_object_query($args, $request)
	{

		$args = array(
			'post_type' => array('product'),
			'post_status' => $request['status'] ? $request['status'] : 'publish',
			'posts_per_page' => $request['per_page'] ? $request['per_page'] : 8,
			'orderby' => 'relevance',
			'paginate' => true,
			'paged' => $request['page'] ? $request['page'] : 1,
			'fields' => 'ids',
			's' => $request['search'] ? $request['search'] : '',
		);
		// $status                      = $request['status'] ? $request['status'] : 'publish';
		$args['lang']                = $request['lang'];

		if ($request['include']) {
			$args['post__in'] = $request['include'];
		}

		if ($request['stock_status']) {
			$args['meta_query']['relation'] = 'AND';
			$args['meta_query'][] = [
				'key'     => '_stock_status',
				'value'   => $request['stock_status'],
			];
		}

		switch ($request['orderby']) {
			case 'id':
				$args['orderby'] = 'ID';
				break;
			case 'menu_order':
				$args['orderby'] = 'menu_order';
				$args['order']   = ('desc' === $request['order']) ? 'desc' : 'asc';
				break;
			case 'title':
				$args['orderby'] = 'title';
				$args['order']   = ('desc' === $request['order']) ? 'desc' : 'asc';
				break;
			case 'relevance':
				$args['orderby'] = 'relevance';
				$args['order']   = 'desc';
				break;
			case 'rand':
				$args['orderby'] = 'rand'; // @codingStandardsIgnoreLine
				break;
			case 'date':
				$args['orderby'] = 'date ID';
				$args['order']   = ('asc' === $request['order']) ? 'asc' : 'desc';
				break;
			case 'price':
				$callback = 'desc' === $request['order'] ? 'order_by_price_desc_post_clauses' : 'order_by_price_asc_post_clauses';
				add_filter('posts_clauses', array('BCAPP\Api\Category', $callback));
				break;
			case 'popularity':
				add_filter('posts_clauses', array('BCAPP\Api\Category', 'order_by_popularity_post_clauses'));
				break;
			case 'rating':
				add_filter('posts_clauses', array('BCAPP\Api\Category', 'order_by_rating_post_clauses'));
				break;
		}

		if (!empty($request['attributes'])) {
		    wc_get_logger()->debug( 'Request attributes: ' . print_r($request['attributes'], true) );

			foreach ($request['attributes'] as $filter_key => $filter_value) {
				if ($filter_value) {
					$array_terms = \explode(',', $filter_value);
					// !strange fix to filter by multiple attributes
					// $args['tax_query'][0]['terms'] = ['', 'dadati', 'calamaro']; = working
					// $args['tax_query'][0]['terms'] = ['dadati', 'calamaro']; = not working (select the first one only)
					$empty_array = [""];
					$array_terms = array_merge($empty_array, $array_terms);
		            //$array_terms = array_filter($array_terms); // Remove any empty values -- seems to working fine and the comment above is not valid anymore

					$args['tax_query']['relation'] = 'AND';
					$args['tax_query'][] = [
						'taxonomy' => $filter_key,
						'field'    => 'slug',
						'terms'    => $array_terms,
						'operator'    => "IN",
					];
				}
			}
		}

		if ($request['category']) {
			$args['tax_query']['relation'] = 'AND';
			$args['tax_query'][] = [
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $request['category'],
			];

			// filter out hidden products
			$args['tax_query'][] = [
				'taxonomy'     => 'product_visibility',
				'field'   => 'name',
				'terms'   => 'exclude-from-catalog',
				'operator'   => 'NOT IN',
			];
		}

		// filter by other custom taxonomies like product_tag, product_collection, ...
		if ($request['taxonomy'] && $request['taxonomy_id']) {
			$args['tax_query']['relation'] = 'AND';
			$args['tax_query'][] = [
				'taxonomy' => $request['taxonomy'],
				'field'    => 'term_id',
				'terms'    => $request['taxonomy_id'],
			];

			// filter out hidden products
			$args['tax_query'][] = [
				'taxonomy'     => 'product_visibility',
				'field'   => 'name',
				'terms'   => 'exclude-from-catalog',
				'operator'   => 'NOT IN',
			];
		}

		if ($request['search']) {
			// filter out hidden products
			$args['tax_query'][] = [
				'taxonomy'     => 'product_visibility',
				'field'   => 'name',
				'terms'   => 'exclude-from-catalog',
				'operator'   => 'NOT IN',
			];
		}

		// Min / Max price filter.
		if (isset($request['min_price']) || isset($request['max_price'])) {
			$price_request = [];

			if (isset($request['min_price'])) {
				$price_request['min_price'] = $request['min_price'];
			}

			if (isset($request['max_price'])) {
				$price_request['max_price'] = $request['max_price'];
			}

			// Get min/max price meta query - replace deprecated function wc_get_min_max_price_meta_query()
		    $current_min_price = isset( $price_request['min_price'] ) ? floatval( $price_request['min_price'] ) : 0;
            $current_max_price = isset( $price_request['max_price'] ) ? floatval( $price_request['max_price'] ) : PHP_INT_MAX;
            $args['meta_query'][] = [
                    'key'     => '_price',
                    'value'   => array( $current_min_price, $current_max_price ),
                    'compare' => 'BETWEEN',
                    'type'    => 'DECIMAL(10,' . wc_get_price_decimals() . ')',
            ];
		}

		// App Settings - the only need for the moment is Flyccart Woo Pricing Discount Plugin Integration
		$settings = FlycartWooDiscountRules::getBeyondCartConfigs();

		// If Flycart Woo Pricing Discount Plugin is active and On Sale category is set get products ids from the plugin's method
		if($settings->flycart_woo_pricing_discount_plugin_integration && !empty($settings->flycart_woo_pricing_discount_sale_category_id)
		    && !empty($request['category']) && in_array($settings->flycart_woo_pricing_discount_sale_category_id, $request['category'] )) {
			$args = FlycartWooDiscountRules::getOnSaleProductIdsAndModifyTheQueryArgs($args);
			unset($request['category']);
		}

        // Log the generated WC_Product_Query
        // $product_query = new \WC_Product_Query($args);
        // wc_get_logger()->debug( 'Category: ' . print_r($request['category'], true));

		return $args;
	}

	/**
	 * Get products with filter
	 * Legacy custom endpoint wp-json/grind-mobile-app/v1/filter_products
	 * after most of the users updated the app to the latest version we'll remove this method and endpoint
	 * @param request
	 * @return object
	 */
	public static function getProductsWithFilter($request)
	{

		$args = array(
			'post_type' => array('product'),
			'post_status' => $request['status'] ? $request['status'] : 'publish',
			'posts_per_page' => $request['per_page'] ? $request['per_page'] : 8,
			'orderby' => 'relevance',
			'paginate' => true,
			'paged' => $request['page'] ? $request['page'] : 1,
			'fields' => 'ids',
			's' => $request['search'] ? $request['search'] : '',
		);
		// $status                      = $request['status'] ? $request['status'] : 'publish';
		$args['lang']                = $request['lang'];

		if ($request['include']) {
			$args['post__in'] = \explode(',', $request['include']);
		}

		if ($request['stock_status']) {
			$args['meta_query']['relation'] = 'AND';
			$args['meta_query'][] = [
				'key'     => '_stock_status',
				'value'   => $request['stock_status'],
			];
		}

		switch ($request['orderby']) {
			case 'id':
				$args['orderby'] = 'ID';
				break;
			case 'menu_order':
				$args['orderby'] = 'menu_order';
				$args['order']   = ('DESC' === $request['order']) ? 'DESC' : 'ASC';
				break;
			case 'title':
				$args['orderby'] = 'title';
				$args['order']   = ('DESC' === $request['order']) ? 'DESC' : 'ASC';
				break;
			case 'relevance':
				$args['orderby'] = 'relevance';
				$args['order']   = 'DESC';
				break;
			case 'rand':
				$args['orderby'] = 'rand'; // @codingStandardsIgnoreLine
				break;
			case 'date':
				$args['orderby'] = 'date ID';
				$args['order']   = ('ASC' === $request['order']) ? 'ASC' : 'DESC';
				break;
			case 'price':
				$callback = 'DESC' === $request['order'] ? 'order_by_price_desc_post_clauses' : 'order_by_price_asc_post_clauses';
				add_filter('posts_clauses', array('BCAPP\Api\Category', $callback));
				break;
			case 'popularity':
				add_filter('posts_clauses', array('BCAPP\Api\Category', 'order_by_popularity_post_clauses'));
				break;
			case 'rating':
				add_filter('posts_clauses', array('BCAPP\Api\Category', 'order_by_rating_post_clauses'));
				break;
		}

		if (!empty($request['attributes'])) {
			foreach ($request['attributes'] as $filter_key => $filter_value) {
				if ($filter_value) {
					$array_terms = \explode(',', $filter_value);
					// !strange fix to filter by multiple attributes
					// $args['tax_query'][0]['terms'] = ['', 'dadati', 'calamaro']; = working
					// $args['tax_query'][0]['terms'] = ['dadati', 'calamaro']; = not working (select the first one only)
					$empty_array = [""];
					$array_terms = array_merge($empty_array, $array_terms);

					$args['tax_query']['relation'] = 'AND';
					$args['tax_query'][] = [
						'taxonomy' => $filter_key,
						'field'    => 'slug',
						'terms'    => $array_terms,
						'operator'    => "IN",
					];
				}
			}
		}

		if ($request['category']) {
			$args['tax_query']['relation'] = 'AND';
			$args['tax_query'][] = [
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => \explode(',', $request['category']),
			];

			// filter out hidden products
			$args['tax_query'][] = [
				'taxonomy'     => 'product_visibility',
				'field'   => 'name',
				'terms'   => 'exclude-from-catalog',
				'operator'   => 'NOT IN',
			];
		}

		// filter by other custom taxonomies like product_tag, product_collection, ...
		if ($request['taxonomy'] && $request['taxonomy_id']) {
			$args['tax_query']['relation'] = 'AND';
			$args['tax_query'][] = [
				'taxonomy' => $request['taxonomy'],
				'field'    => 'term_id',
				'terms'    => \explode(',', $request['taxonomy_id']),
			];

			// filter out hidden products
			$args['tax_query'][] = [
				'taxonomy'     => 'product_visibility',
				'field'   => 'name',
				'terms'   => 'exclude-from-catalog',
				'operator'   => 'NOT IN',
			];
		}

		if ($request['search']) {
			// filter out hidden products
			$args['tax_query'][] = [
				'taxonomy'     => 'product_visibility',
				'field'   => 'name',
				'terms'   => 'exclude-from-catalog',
				'operator'   => 'NOT IN',
			];

            $args['tax_query'][] = [
                'taxonomy'     => 'product_visibility',
                'field'   => 'name',
                'terms'   => 'exclude-from-search',
                'operator'   => 'NOT IN',
            ];
		}

		// Min / Max price filter.
		if (isset($request['min_price']) || isset($request['max_price'])) {
			$price_request = [];

			if (isset($request['min_price'])) {
				$price_request['min_price'] = $request['min_price'];
			}

			if (isset($request['max_price'])) {
				$price_request['max_price'] = $request['max_price'];
			}

			// Get min/max price meta query - replace deprecated function wc_get_min_max_price_meta_query()
		    $current_min_price = isset( $price_request['min_price'] ) ? floatval( $price_request['min_price'] ) : 0;
            $current_max_price = isset( $price_request['max_price'] ) ? floatval( $price_request['max_price'] ) : PHP_INT_MAX;
            $args['meta_query'][] = [
                    'key'     => '_price',
                    'value'   => array( $current_min_price, $current_max_price ),
                    'compare' => 'BETWEEN',
                    'type'    => 'DECIMAL(10,' . wc_get_price_decimals() . ')',
            ];
		}

		// App Settings - the only need for the moment is Flyccart Woo Pricing Discount Plugin Integration
		$settings = FlycartWooDiscountRules::getBeyondCartConfigs();


		// If Flycart Woo Pricing Discount Plugin is active and On Sale category is set get products ids from the plugin's method
		if($settings->flycart_woo_pricing_discount_plugin_integration && !empty($settings->flycart_woo_pricing_discount_sale_category_id)
		    && $request['category'] == $settings->flycart_woo_pricing_discount_sale_category_id) {
			$args = FlycartWooDiscountRules::getOnSaleProductIdsAndModifyTheQueryArgs($args);
		}

		add_action('pre_get_posts', array('BCAPP\Api\Category', 'fix_query_vars'));
		$query = new \WP_Query($args);

		$products_query = $query->get_posts();
		$products = array();
		$rest_controller = new \WC_REST_Products_V2_Controller;

		foreach ($products_query as $id) {
			$_product = wc_get_product($id);
			$item = $rest_controller->prepare_object_for_response($_product, $request)->data;

			// Modify Price If Flycart Woo Pricing Discount Plugin is active AND variable product AND range price
			if($settings->flycart_woo_pricing_discount_plugin_integration && !empty($_product->get_price_html()) && $_product->get_type() == 'variable' && FlycartWooDiscountRules::checkIfThereIsRangeInHtml($_product->get_price_html())) {
			    $item = FlycartWooDiscountRules::modifyVariableProductPricesFlycartDiscountPlugin($item, $_product);
			}
			// Modify Price If Flycart Woo Pricing Discount Plugin is active AND variable product AND not range price
			if($settings->flycart_woo_pricing_discount_plugin_integration && !empty($_product->get_price_html()) && $_product->get_type() == 'variable' && !FlycartWooDiscountRules::checkIfThereIsRangeInHtml($_product->get_price_html())) {
			    $item = FlycartWooDiscountRules::modifySimpleProductPricesFlycartDiscountPlugin($item, $_product);
			}
			// Modify Price If Flycart Woo Pricing Discount Plugin is active AND simple product
			if($settings->flycart_woo_pricing_discount_plugin_integration && !empty($_product->get_price_html()) && $_product->get_type() == 'simple') {
			    $item = FlycartWooDiscountRules::modifySimpleProductPricesFlycartDiscountPlugin($item, $_product);
			}

			$item['description'] = self::remove_vc_tags($item['description']);

			$products[] = $item;
		}
		$response = new \WP_REST_Response($products);
		$response->set_status(200);

		remove_action('pre_get_posts', array('BCAPP\Api\Category', 'fix_query_vars'));
		self::remove_ordering_args();
		return $response;
	}

	/**
	 * Query vars fix
	 * Legacy method, we'll remove it when we remove getProductsWithFilter method
	 */
	public static function fix_query_vars($query)
	{
		$query->set('post_type', 'product');
		return $query;
	}

	/**
	 * Remove ordering queries.
	 * Legacy method, we'll remove it when we remove getProductsWithFilter method
	 */
	public static function remove_ordering_args()
	{
		remove_filter('posts_clauses', array('BCAPP\Api\Category', 'order_by_price_asc_post_clauses'));
		remove_filter('posts_clauses', array('BCAPP\Api\Category', 'order_by_price_desc_post_clauses'));
		remove_filter('posts_clauses', array('BCAPP\Api\Category', 'order_by_popularity_post_clauses'));
		remove_filter('posts_clauses', array('BCAPP\Api\Category', 'order_by_rating_post_clauses'));
	}


	/**
	 * Handle numeric price sorting.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function order_by_price_asc_post_clauses($args)
	{
		$args['join']    = self::append_product_sorting_table_join($args['join']);
		$args['orderby'] = ' wc_product_meta_lookup.min_price ASC, wc_product_meta_lookup.product_id ASC ';
		return $args;
	}

	/**
	 * Handle numeric price sorting.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function order_by_price_desc_post_clauses($args)
	{
		$args['join']    = self::append_product_sorting_table_join($args['join']);
		$args['orderby'] = ' wc_product_meta_lookup.max_price DESC, wc_product_meta_lookup.product_id DESC ';
		return $args;
	}

	/**
	 * WP Core does not let us change the sort direction for individual orderby params - https://core.trac.wordpress.org/ticket/17065.
	 *
	 * This lets us sort by meta value desc, and have a second orderby param.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function order_by_popularity_post_clauses($args)
	{
		$args['join']    = self::append_product_sorting_table_join($args['join']);
		$args['orderby'] = ' wc_product_meta_lookup.total_sales DESC, wc_product_meta_lookup.product_id DESC ';
		return $args;
	}

	/**
	 * Order by rating post clauses.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function order_by_rating_post_clauses($args)
	{
		$args['join']    = self::append_product_sorting_table_join($args['join']);
		$args['orderby'] = ' wc_product_meta_lookup.average_rating DESC, wc_product_meta_lookup.rating_count DESC, wc_product_meta_lookup.product_id DESC ';
		return $args;
	}

	/**
	 * Join wc_product_meta_lookup to posts if not already joined.
	 *
	 * @param string $sql SQL join.
	 * @return string
	 */
	private static function append_product_sorting_table_join($sql)
	{
		global $wpdb;

		if (!strstr($sql, 'wc_product_meta_lookup')) {
			$sql .= " LEFT JOIN {$wpdb->wc_product_meta_lookup} wc_product_meta_lookup ON $wpdb->posts.ID = wc_product_meta_lookup.product_id ";
		}
		return $sql;
	}


	/**
	 * Get Min/Max Prices For the Filters in Category
	 * @link wc/v3/min-max-prices
	 */
	public function get_min_max_prices($request)
	{
		global $wpdb;

		$tax_query = array();

		if (isset($request['category']) && $request['category']) {
			$tax_query[] = array(
				'relation' => 'AND',
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'cat_id',
					'terms'    => array($request['category']),
				),
			);
		}

		$meta_query = array();

		$meta_query = new WP_Meta_Query($meta_query);
		$tax_query  = new WP_Tax_Query($tax_query);

		$meta_query_sql = $meta_query->get_sql('post', $wpdb->posts, 'ID');
		$tax_query_sql  = $tax_query->get_sql($wpdb->posts, 'ID');

		$sql = "
			SELECT min( min_price ) as min_price, MAX( max_price ) as max_price
			FROM {$wpdb->wc_product_meta_lookup}
			WHERE product_id IN (
				SELECT ID FROM {$wpdb->posts}
				" . $tax_query_sql['join'] . $meta_query_sql['join'] . "
				WHERE {$wpdb->posts}.post_type IN ('" . implode("','", array_map('esc_sql', apply_filters('woocommerce_price_filter_post_type', array('product')))) . "')
				AND {$wpdb->posts}.post_status = 'publish'
				" . $tax_query_sql['where'] . $meta_query_sql['where'] . '
			)';

		$sql = apply_filters('woocommerce_price_filter_sql', $sql, $meta_query_sql, $tax_query_sql);

		return $wpdb->get_row($sql); // WPCS: unprepared SQL ok.
	}

	/**
	 * Remove Visual Composer and Shortcode Tags From the description
	 * Legacy method, remove when we remove getProductsWithFilter()
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

	/**
	 * Get recursively categories by parent
	 *
	 * @param $request
	 *
	 * @return array
	 * @since 1.3.4
	 */
	public function categories( $request ) {
		$parent = $request->get_param( 'parent' );

		$result = wp_cache_get( 'category_' . $parent, 'beyondcart' );

		if ( $result ) {
			return $result;
		}

		$result = $this->get_category_by_parent_id( $parent );
		wp_cache_set( 'category_' . $parent, $result, 'beyondcart' );

		return $result;
	}

	/**
	 * Helper Function to get Category by parent id
	 * Used in categories()
	 */
	public function get_category_by_parent_id( $parent ) {
		$args = array(
			'hierarchical'     => 1,
			'show_option_none' => '',
			'hide_empty'       => 0,
			'parent'           => $parent,
			'taxonomy'         => 'product_cat',
		);

		$categories = get_categories( $args );

		if ( count( $categories ) ) {
			$with_subs = [];
			foreach ( $categories as $category ) {

				$image = null;
				// Get category image. ACF
				$image_id_acf = get_term_meta($category->term_id, 'app_image', true);
				// Get category image.
				$image_id = get_term_meta( $category->term_id, 'thumbnail_id', true );
				if ( isset($image_id_acf) && !empty($image_id_acf) ) {
					$attachment = get_post( $image_id_acf );

					$image = array(
						'id'   => (int) $image_id_acf,
						'src'  => wp_get_attachment_url( $image_id_acf ),
						'name' => get_the_title( $attachment ),
						'alt'  => get_post_meta( $image_id_acf, '_wp_attachment_image_alt', true ),
					);
				}elseif ($image_id) {
					$attachment = get_post( $image_id );

					$image = array(
						'id'   => (int) $image_id,
						'src'  => wp_get_attachment_url( $image_id ),
						'name' => get_the_title( $attachment ),
						'alt'  => get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
					);
				}
				$with_subs[] = array(
					'id'         => (int) $category->term_id,
					'name'       => $category->name,
					'description' => $category->description,
					'parent'     => $category->parent,
					'categories' => $this->get_category_by_parent_id( (int) $category->term_id ),
					'image'      => $image,
					'count'      => (int) $category->count
				);
			}
			$with_subs = apply_filters('beyondcart_modify_category_content', $with_subs);
			return $with_subs;

		} else {
			return [];
		}
	}


	/**
	 * Get Cached Terms Ids by Category Ids (stored in woo DB table wp_grind_mobile_app_terms)
	 * Returns all taxonomies with the theirs terms
	 * Only terms with products assigned to them are included
	 * @param $request [$category_id => 123]
	 */
	public function getCachedTermsByCategoryId($request)
	{
		$category_id = $request['category_id'];

		if (empty($category_id)) {
			return false;
		}

		global $wpdb;
		$query = "SELECT data FROM {$wpdb->prefix}grind_mobile_app_terms WHERE category_id={$category_id} LIMIT 1";
		return $wpdb->get_var($query);
	}
}
