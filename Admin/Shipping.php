<?php

namespace BCAPP\Admin;

class Shipping
{

  public static function init()
  {
    self::addCourierOptionToShippingMethod();
    self::hideShippingMethodInSite();
    self::hideShippingMethodInApp();
  }

  /**
   * Select if shipping method is a courier of type speedy/econt
   */
  public static function addCourierOptionToShippingMethod()
  {
    add_action('woocommerce_init', function () {

      if ( self::is_rest_api_request() ) {
        return; // Exit if it is a REST request
      }

      $shipping_methods = \WC()->shipping->get_shipping_methods();
      foreach ($shipping_methods as $shipping_method) {
        add_filter('woocommerce_shipping_instance_form_fields_' . $shipping_method->id, function ($settings) {
          $settings['shipping_courier'] = array(
            'title'       => esc_html__('Courier', 'grind-mobile-app'),
            'type'        => 'select',
            'placeholder' => esc_html__('Select Courier related to this field if any', 'grind-mobile-app'),
            'description' => 'Select Courier related to this field. Leave empty for none. Related with Beyondcart',
            'default' => '',
            'options' => array(
              ''      => __('None', 'grind-mobile-app'),
              'speedy'      => __('Speedy', 'grind-mobile-app'),
              'econt' => __('Econt', 'grind-mobile-app'),
            ),
          );
          return $settings;
        });
      }
    });
  }

  /**
   * Hide Shipping methods from site
   */
  public static function hideShippingMethodInSite()
  {
    add_action('woocommerce_init', function () {

      if ( self::is_rest_api_request() ) {
        return; // Exit if it is a REST request
      }

      $shipping_methods = \WC()->shipping->get_shipping_methods();
      foreach ($shipping_methods as $shipping_method) {
        add_filter('woocommerce_shipping_instance_form_fields_' . $shipping_method->id, function ($settings) {
          $settings['shipping_hide_from_site'] = array(
            'title'       => esc_html__('Hide In Website', 'grind-mobile-app'),
            'type'        => 'checkbox',
            'placeholder' => esc_html__('Hide this Shipping method', 'grind-mobile-app'),
            'description' => 'Hide this shipping method in site. Related with Beyondcart',
          );
          return $settings;
        });
      }
    });



    // add_filter('woocommerce_package_rates', 'BCAPP\Admin\hide_shipping_method_from_site', 10, 2);
    /**
     * Checks if hide from site option is checked and removes method from site
     */
    function hide_shipping_method_from_site($rates, $package)
    {
      $available_methods = array();
      foreach ($rates as $rate_id => $rate) {

        $shipping_rate_id = str_replace(':', '_', $rate_id);
        if (
          !isset(get_option('woocommerce_' . $shipping_rate_id . '_settings')['shipping_hide_from_site']) ||
          (isset(get_option('woocommerce_' . $shipping_rate_id . '_settings')['shipping_hide_from_site']) && get_option('woocommerce_' . $shipping_rate_id . '_settings')['shipping_hide_from_site'] != 'yes')
        ) {
          $available_methods[$rate_id] = $rate;
        }
      }
      return $available_methods;
    }
  }
  /**
   * Hides shipping methods from app and selects the first available as default
   */
  public static function hideShippingMethodInApp()
  {
    add_action('woocommerce_init', function () {

      if ( self::is_rest_api_request() ) {
        return; // Exit if it is a REST request
      }

      $shipping_methods = \WC()->shipping->get_shipping_methods();
      foreach ($shipping_methods as $shipping_method) {
        add_filter('woocommerce_shipping_instance_form_fields_' . $shipping_method->id, function ($settings) {
          $settings['shipping_hide_from_app'] = array(
            'title'       => esc_html__('Hide In App', 'grind-mobile-app'),
            'type'        => 'checkbox',
            'placeholder' => esc_html__('Hide this Shipping method', 'grind-mobile-app'),
            'description' => 'Hide this shipping method in app. Related with Beyondcart',
          );
          return $settings;
        });
      }
    });

    /**
     * Removes shipping method if "hide from app" option is checked OR if method name is in beyondcart config
     */
    function hide_shipping_method_from_app($rates, $package)
    {
      $configs = get_option('grind_mobile_app_configs', array(
        "requireLogin"           => false,
        "toggleSidebar"          => false,
        "hide_shipping_from_app" => '',
        "isBeforeNewProduct"     => 5
      ));
      $hide_shipping_from_app = explode(',', $configs->hide_shipping_from_app);

      $available_methods = array();
      foreach ($rates as $rate_id => $rate) {
        $shipping_rate_id = str_replace(':', '_', $rate_id);
        if (get_option('woocommerce_' . $shipping_rate_id . '_settings')['shipping_hide_from_app'] != 'yes' && !in_array($rate_id, $hide_shipping_from_app)) {
          $available_methods[$rate_id] = $rate;
        }
      }
      return $available_methods;
    }

    /**
     * Sets the default shipping option to the first available one
     */
    function reset_default_shipping_method($method, $available_methods)
    {
      $configs = get_option('grind_mobile_app_configs', array(
        "requireLogin"           => false,
        "toggleSidebar"          => false,
        "hide_shipping_from_app" => '',
        "isBeforeNewProduct"     => 5
      ));
      $hide_shipping_from_app = explode(',', $configs->hide_shipping_from_app);

      if (in_array($method, $hide_shipping_from_app) || empty($method)) {
        $method = array_key_first($available_methods);
      }
      return $method;
    }

    // add_filter('woocommerce_shipping_chosen_method', 'BCAPP\Admin\reset_default_shipping_method', 9999, 2);
  }

  /**
   * Checks if it's a REST request, because it throws a warning in newest Woo version 8.4.0 
   * when using the following endpoint: wp-json/wc/v3/shipping/zones/2/methods
   */ 
  private static function is_rest_api_request() {
    if ( wp_doing_ajax() || wp_doing_cron() ) {
        return false;
    }

    $rest_prefix = trailingslashit( rest_get_url_prefix() );
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    return ( false !== strpos( $request_uri, $rest_prefix ) );
  }
}
