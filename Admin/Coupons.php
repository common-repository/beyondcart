<?php

namespace BCAPP\Admin;

class Coupons
{
  /**
   * Show App Only Coupon Checkbox in Coupon CRUD in Woocommerce Admin
   */
  public static function addAppOnlyCoupon()
  {
    add_action('woocommerce_coupon_options', function () {
      woocommerce_wp_checkbox(array(
        'id' => 'app_only',
        'label' => __('App only coupon', 'grind-mobile-app'),
        'description' => sprintf(__('Allows this coupon code to be used only via the mobile application', 'grind-mobile-app'))
      ));
    }, 10, 0);


    add_action('woocommerce_coupon_options_save', function ($post_id, $coupon) {
      $include_stats = isset($_POST['app_only']) ? 'yes' : 'no';
      update_post_meta($post_id, 'app_only', $include_stats);
    }, 10, 2);
  }

  /**
   * Show Sales Channel In Admin Order Listing
   * Is the order from Website or App
   */
  public static function addAppOnlyCouponValidation()
  {
    add_filter('woocommerce_coupon_is_valid', 'BCAPP\Admin\remove_product_cat_coupon_validation', 1, 2);
    function remove_product_cat_coupon_validation($valid, $coupon)
    {
      if ($coupon->get_meta('app_only') === 'yes' && !array_key_exists('mobile', $_COOKIE) && !$_COOKIE['mobile']) {
        $valid = false;
      }
      return $valid;
    }
  }
}
