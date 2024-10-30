<?php

namespace BCAPP\Includes;

use BCAPP\Admin\Categories as AdminCachedCategories;
use BCAPP\Api\Cart;
use BCAPP\Api\Checkout;
use BCAPP\Api\Product;
use BCAPP\Api\Category;
use BCAPP\Api\Customer;
use BCAPP\Api\Coupon;
use BCAPP\Api\Auth;
use BCAPP\Api\Common;
use BCAPP\Includes\I18n;
use BCAPP\Includes\Tracking;
use BCAPP\Includes\Integrations\MrejanetEcont\BeyondcartMrejanetEcontIntegration;
use BCAPP\Includes\Integrations\MrejanetSpeedy\BeyondcartMrejanetSpeedyIntegration;
use BCAPP\Includes\Integrations\FlycartWooDiscountRules\FlycartWooDiscountRulesIntegration;
use BCAPP\Includes\Integrations\WooRewards\WooRewardsIntegration;

class Loader
{

    public static function load()
    {
        self::set_locale();

        Tracking::init();

        // Admin
        \BCAPP\Admin\Nav::add_links();
        \BCAPP\Admin\SmartBanner::init();
        add_action('rest_api_init', [\BCAPP\Admin\Api::class, 'register']);
        // Add custom field to check if order from app or web in checkout web view
        \BCAPP\Admin\Orders::newHiddenFieldInWebCheckout();
        \BCAPP\Admin\Orders::addSalesChannelFieldInOrder();
        \BCAPP\Admin\Orders::updateCreatedViaOrderRestResponse();
        \BCAPP\Admin\Coupons::addAppOnlyCoupon();
        \BCAPP\Admin\Coupons::addAppOnlyCouponValidation();
        \BCAPP\Admin\Shipping::init();

        // Auth
        $auth_api = new Auth();
        add_action('rest_api_init', [$auth_api, 'add_api_routes']);
        add_filter('digits_rest_token_data', [$auth_api, 'custom_digits_rest_token_data'], 100, 2);

        // Common
        $common_api = new Common();
        add_action('rest_api_init', [$common_api, 'add_api_routes']);
        add_action('rest_api_init', [$common_api, 'add_api_fields']);
        add_filter('rest_prepare_post', [$common_api, 'customize_rest_api_post_response'], 10, 3);

        // Cart
        $cart_api = new Cart();
        add_action('wp_loaded', [$cart_api, 'beyondcart_mobile_builder_pre_car_rest_api'], 5);
        add_action('rest_api_init', [$cart_api, 'add_api_routes'], 10);
        add_filter('woocommerce_persistent_cart_enabled', [$cart_api, 'beyondcart_mobile_builder_woocommerce_persistent_cart_enabled']);
        add_action('woocommerce_load_cart_from_session', [$cart_api, 'load_cart_action'], 10);

        // Checkout
        $checkout_api = new Checkout();
        add_action('rest_api_init', [$checkout_api, 'add_api_routes'], 10);
        add_action('woocommerce_thankyou', [$checkout_api, 'handle_checkout_success'], 10);
        add_action('wp_enqueue_scripts', [$checkout_api, 'enqueue_styles']);

        // in webview app change order created_via to appset cookie
        if (isset($_GET['beyondcart_webview']) && $_GET['beyondcart_webview'] === 'on') {
            setcookie('beyondcart_webview', 'on', time() + 2592000, '/'); //36002430 - 30 days
        }

        // Customer
        $customer_api = new Customer();
        add_action('rest_api_init', [$customer_api, 'add_api_routes'], 10);
        add_filter('determine_current_user', [$customer_api, 'determine_current_user']);

        // Category (Product Listing)
        $category_api = new Category();
        add_action('rest_api_init', [$category_api, 'add_api_routes'], 10);
        add_filter('woocommerce_rest_product_object_query', [$category_api, 'modify_rest_products_object_query'], 10, 2);

        // Product
        $product_api = new Product();
        add_action('rest_api_init', [$product_api, 'add_api_routes']);
        add_filter('woocommerce_rest_prepare_product_object', [$product_api, 'custom_change_product_response'], 20, 3);
        add_filter('woocommerce_rest_prepare_product_cat', [$product_api, 'custom_change_product_cat'], 20, 3);
        add_filter('the_title', [$product_api, 'custom_the_title'], 20, 3);

        // Product variation
        add_filter('woocommerce_rest_product_variation_object_query', [$product_api, 'custom_modify_variation_object_query']);
        add_filter('woocommerce_rest_prepare_product_variation_object', [$product_api, 'custom_woocommerce_rest_prepare_product_variation_object']);
        
        // Product Attributes
        add_filter('woocommerce_rest_prepare_product_attribute', [$product_api, 'custom_woocommerce_rest_prepare_product_attribute'], 10, 3);
        add_filter('woocommerce_rest_prepare_pa_color', [$product_api, 'add_value_pa_color']);
        add_filter('woocommerce_rest_prepare_pa_image', [$product_api, 'add_value_pa_image']);

        // Coupon
        $coupon = new Coupon();
        add_action('rest_api_init', [$coupon, 'add_api_routes'], 10);

        // Initialize Cached Categories Terms for Filters
        $adminCachedCategoriesTerms = new AdminCachedCategories();

        // Multicurrency force currency for mobile checkout
        add_filter('wcml_client_currency', [$product_api, 'mbd_wcml_client_currency']);

        self::woocommerceRestFilters();

        $app_configs = maybe_unserialize(get_option('grind_mobile_app_configs', array(
            "requireLogin"       => false,
            "toggleSidebar"      => false,
            "isBeforeNewProduct" => 5
        )));

        if (isset($app_configs->integrations) && in_array('Mrejanet Econt', $app_configs->integrations)) {
            new BeyondcartMrejanetEcontIntegration;
        }
        if (isset($app_configs->integrations) && in_array('Mrejanet Speedy', $app_configs->integrations)) {
            new BeyondcartMrejanetSpeedyIntegration;
        }
        if (isset($app_configs->flycart_woo_pricing_discount_plugin_integration) && $app_configs->flycart_woo_pricing_discount_plugin_integration == true) {
            new FlycartWooDiscountRulesIntegration;
        }

        if (isset($app_configs->woo_rewards_plugin_integration) && $app_configs->woo_rewards_plugin_integration == true) {
            $rewards_api = new WooRewardsIntegration;
            add_action('rest_api_init', [$rewards_api, 'add_api_routes'], 10);
        }

        // Make the plugin Compatible with High-Performance-Order-Storage-Upgrade-Recipe-Book HPOS - New Order Tables
        add_action( 'before_woocommerce_init', function() {
            if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', 'grind-mobile-app/grind-mobile-app.php', true );
            }
        } );
    }

    private static function set_locale()
    {
        add_action('plugins_loaded', [I18n::class, 'load_plugin_textdomain']);
    }

    private static function woocommerceRestFilters()
    {
        // customers, подредени по modified_after
        add_filter('woocommerce_rest_customer_query', function (array $args, \WP_REST_Request $request) {
            $modified_after = $request->get_param('modified_after');
            if (!$modified_after) {
                return $args;
            }
            $args['meta_query'] = [
                [
                    'key' => 'last_update',
                    'value' => (int) is_numeric($modified_after) ? $modified_after : strtotime($modified_after),
                    'compare' => '>=',
                ],
            ];
            return $args;
        }, 10, 2);

        // продукти, подредени по modified_after
        add_filter('woocommerce_rest_product_object_query', function (array $args, \WP_REST_Request $request) {
            $modified_after = $request->get_param('modified_after');

            if (!$modified_after) {
                return $args;
            }

            $args['date_query'][0]['column'] = 'post_modified_gmt';
            $args['date_query'][0]['after'] = $modified_after;

            return $args;
        }, 10, 2);

        // поръчки, подредени по modified_after
        add_filter('woocommerce_rest_orders_prepare_object_query', function (array $args, \WP_REST_Request $request) {
            $modified_after = $request->get_param('modified_after');
            if (!$modified_after) {
                return $args;
            }
            $args['date_query'][0]['column'] = 'post_modified_gmt';
            $args['date_query'][0]['after'] = $modified_after;
            return $args;
        }, 10, 2);
    }
}
