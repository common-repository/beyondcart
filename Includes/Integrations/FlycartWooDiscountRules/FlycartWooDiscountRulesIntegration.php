<?php
namespace BCAPP\Includes\Integrations\FlycartWooDiscountRules;

if (!defined('ABSPATH')) exit;
// Exit if accessed directly

class FlycartWooDiscountRulesIntegration
{
    public function __construct()
    {
        // If the integration is active we'll trigger filter to modify the rest api for variation and modify with the price from the plugin 
        add_filter('woocommerce_rest_prepare_product_variation_object', [$this, 'flycart_woo_discount_plugin_apply_prices_to_variations']);
         // If the integration is active we'll trigger filter to modify the rest api for product object (/wp-json/wc/v3/product/1234 and modify with the price from the plugin 
        add_filter('woocommerce_rest_prepare_product_object', [$this, 'flycart_woo_discount_plugin_apply_prices_to_product_object'], 99, 3);
         // If the integration is active we'll trigger filter to modify the rest api for products listing (/wp-json/wc/v3/products) and modify with the price from the plugin 
        add_filter('woocommerce_rest_prepare_product_object', [$this, 'flycart_woo_discount_plugin_apply_prices_to_products_rest_api'], 99, 3);
    }

	/**
	 * Helper function to extract the prices from prices from price_html.
	 * Flycart Woo Discount Prices Plugin change the price_html but not the price and sale_price
	 * So the only way to integrate the plugin (without additionl query) is to extract the prices from the html
     * 
     * Note: we can use plugin's \Wdr\App\Controllers\ManageDiscount::calculateInitialAndDiscountedPrice()
     * but it may be much slower due to additional requests.
	 */
    public static function modifyVariableProductPricesFlycartDiscountPlugin($item, $_product) {

		$flycart_prices = self::extractPricesFromPriceHtmlVariable($_product->get_price_html());
		$item['variation_min_reg_price'] = isset($flycart_prices['min_regular_price']) && !empty($flycart_prices['min_regular_price']) ? number_format($flycart_prices['min_regular_price'], 2) : '';
		$item['variation_max_reg_price'] = isset($flycart_prices['max_regular_price']) && !empty($flycart_prices['max_regular_price']) ? number_format($flycart_prices['max_regular_price'], 2) : '';
		$item['variation_min_sale_price'] = isset($flycart_prices['min_sale_price']) && !empty($flycart_prices['min_sale_price']) ? number_format($flycart_prices['min_sale_price'], 2) : '';
		$item['variation_max_sale_price'] = isset($flycart_prices['max_sale_price']) && !empty($flycart_prices['max_sale_price']) ? number_format($flycart_prices['max_sale_price'], 2) : '';
		
		if(isset($item['variation_max_sale_price']) && !empty($item['variation_max_sale_price']) && ($item['variation_max_reg_price'] <> $item['variation_max_sale_price'])) {
			$item['sale_percentage_min'] = round(100 - ($flycart_prices['min_sale_price'] / $flycart_prices['min_regular_price']) * 100);
			$item['sale_percentage_max'] = round(100 - ($flycart_prices['max_sale_price'] / $flycart_prices['max_regular_price']) * 100);
			$item['on_sale'] = true;
		} else {
		    $item['on_sale'] = false;
		}
		
		return $item;
    }
    
    public static function modifySimpleProductPricesFlycartDiscountPlugin($item, $_product) {
        
		$flycart_prices = self::extractPricesFromPriceHtml($_product->get_price_html());
		$item['sale_price'] = isset($flycart_prices[1]) && !empty($flycart_prices[1]) ? number_format($flycart_prices[1], 2) : '';
		$item['regular_price'] = isset($flycart_prices[0]) && !empty($flycart_prices[0]) ? number_format($flycart_prices[0], 2) : '';
		$item['price'] = isset($item['sale_price']) && !empty($item['sale_price']) ? $item['sale_price'] : $item['regular_price'];
		$item['on_sale'] = isset($item['sale_price']) && !empty($item['sale_price']) ? true : false;
        
        return $item;
    }

    /**
     * Helper function to extract the price from html
     */
	public static function extractPricesFromPriceHtml($html) {
          $dom = new \DOMDocument();
          @$dom->loadHTML($html);
          
          $price_elements = $dom->getElementsByTagName("bdi");
          
          $prices = array();
          foreach ($price_elements as $price_element) {
            $price = $price_element->nodeValue;
            $price = preg_replace('/[^\d.,]/', '', $price); // remove everything but numbers,comma,dot
            $price = str_replace(',', '.', $price); // replace comma with dot
            $prices[] = floatval($price);
          }
          
          return $prices;
    }

    /**
     * Helper function to extract the price from html for variable product
     */
    public static function extractPricesFromPriceHtmlVariable($html) {
       
        preg_match_all('/(?<=<bdi>)[0-9,\.]+/', $html, $matches);
        $prices = array_map(function($price) {
            return str_replace(',', '.', $price);
        }, $matches[0]);

        $min_regular_price = $prices[0];
        $max_regular_price = $prices[1];
        $min_sale_price = $prices[2];
        $max_sale_price = $prices[3];

		return [
          'min_regular_price' => $min_regular_price,
          'max_regular_price' => $max_regular_price,
          'min_sale_price' => $min_sale_price,
          'max_sale_price' => $max_sale_price
		];

    }

    /** 
     * Search in price_html for dash symbol - ranges (ex. 23.00 - 27.00) 
     * */
    public static function checkIfThereIsRangeInHtml($html) {
        if(strpos($html, "&ndash;") !== false) {
            return true;
        }
        return false;
    }


    /** 
     * App Settings 
     * The only need for the moment is Flyccart Woo Pricing Discount Plugin Integration
     * And more specifically to get if the integration is active and OnSale Category Id
     * */
    public static function getBeyondCartConfigs() {
        $settings = get_transient('beyondcart_settings');
		if(!$settings) {
		    global $wpdb;
    		$settings_serialized = $wpdb->get_var("SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = 'grind_mobile_app_configs'");
	        $settings = unserialize($settings_serialized);
		    set_transient('beyondcart_settings', $settings, 3600);
		}
        return $settings;
    }

    /** 
     * If it's the selected OnSale category from BeyondCart configs
     * get OnSale product ids from plugin's method and modify the query
     * */
    public static function getOnSaleProductIdsAndModifyTheQueryArgs($args) {
        // unset query for searching for products in this category
        if($args['tax_query'][0]['taxonomy'] == 'product_cat') {
            unset($args['tax_query'][0]);
        }
        // instead add the products by id manually from the plugins method
        $on_sale_list = \Wdr\App\Controllers\OnSaleShortCode::getOnSaleList();
        if(isset($on_sale_list['list']) && !empty($on_sale_list['list'])) {
            $args['post__in']  = array_values($on_sale_list['list']);
        }
        return $args;
    }
    
	/**
	 * Flycart Woo Discount Plugin Apply Dynamic Prices To Variations
	 * Handles the price coming from the plugin when open product inner in the app and select variation 
	 */
	public function flycart_woo_discount_plugin_apply_prices_to_variations($product_data) {
	    
	    $discount = \Wdr\App\Controllers\ManageDiscount::calculateInitialAndDiscountedPrice($product_data->data['id'], 1);
	    
	    if(!$discount) {
	        return $product_data;
	    }

	    $flycart_prices[0] = $discount['initial_price'];
	    $flycart_prices[1] = $discount['discounted_price'] ?: ''; // sale price
	    
        $product_data->data['sale_price'] = isset($flycart_prices[1]) && !empty($flycart_prices[1]) ? number_format($flycart_prices[1], 2) : '';
        $product_data->data['regular_price'] = isset($flycart_prices[0]) && !empty($flycart_prices[0]) ? number_format($flycart_prices[0], 2) : '';
        $product_data->data['price'] = isset($item['sale_price']) && !empty($item['sale_price']) ? $item['sale_price'] : $item['regular_price'];
        $product_data->data['on_sale'] = isset($product_data->data['sale_price']) && !empty($product_data->data['sale_price']) ? true : false;
	   

		return $product_data;
	}
	
	/**
	 * Flycart Woo Discount Plugin Apply Dynamic Prices To Woo API Product Object
	 * /wp-json/wc/v3/products/8687
	 * Fix enetering in product from Cart or from Wishlist
	 */
	public function flycart_woo_discount_plugin_apply_prices_to_product_object($response, $product, $request) {
	    
	    $discount = \Wdr\App\Controllers\ManageDiscount::calculateInitialAndDiscountedPrice($product->get_id(), 1);
	    
	    if(!$discount) {
	        return $response;
	    }

	    $flycart_prices[0] = $discount['initial_price'];
	    $flycart_prices[1] = $discount['discounted_price'] ?: ''; // sale price
	    
	    
	    $response->data['sale_price'] = isset($flycart_prices[1]) && !empty($flycart_prices[1]) ? number_format($flycart_prices[1], 2) : '';
	    $response->data['regular_price'] = isset($flycart_prices[0]) && !empty($flycart_prices[0]) ? number_format($flycart_prices[0], 2) : '';
	    $response->data['price'] = isset($response->data['sale_price']) && !empty($response->data['sale_price']) ? $response->data['sale_price'] : $response->data['regular_price'];
	    $response->data['on_sale'] = isset($response->data['sale_price']) && !empty($response->data['sale_price']) ? true : false;
	   
		return $response;
	}
	
		/**
	 * Flycart Woo Discount Plugin Apply Dynamic Prices To Woo API Products
	 * /wp-json/wc/v3/products?include=6707,6725
	 * Fix prices for the products in Wishlist
	 */
	public function flycart_woo_discount_plugin_apply_prices_to_products_rest_api($product_data, $product, $request) {
	    
	    $discount = \Wdr\App\Controllers\ManageDiscount::calculateInitialAndDiscountedPrice($product_data->data['id'], 1);
	    
        $product_data->data['on_sale'] = isset($product_data->data['sale_price']) && !empty($product_data->data['sale_price']) ? true : false;
        
	    if(!$discount) {
	        return $product_data;
	    }

	    $flycart_prices[0] = $discount['initial_price'];
	    $flycart_prices[1] = $discount['discounted_price'] ?: ''; // sale price
	    
        $product_data->data['sale_price'] = isset($flycart_prices[1]) && !empty($flycart_prices[1]) ? number_format($flycart_prices[1], 2) : '';
        $product_data->data['regular_price'] = isset($flycart_prices[0]) && !empty($flycart_prices[0]) ? number_format($flycart_prices[0], 2) : '';
        $product_data->data['price'] = isset($product_data->data['sale_price']) && !empty($product_data->data['sale_price']) ? $product_data->data['sale_price'] : $product_data->data['regular_price'];
        $product_data->data['on_sale'] = isset($product_data->data['sale_price']) && !empty($product_data->data['sale_price']) ? true : false;
	   

		return $product_data;
	}
}
