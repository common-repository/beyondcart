<?php
namespace BCAPP\Includes\Integrations\MrejanetSpeedy;

if (!defined('ABSPATH')) exit;
// Exit if accessed directly

class BeyondcartMrejanetSpeedyIntegration
{

  public function __construct()
  {
    add_filter('beyondcart_app_configs', array($this, 'add_checkout_fields'));
    add_action('rest_api_init', [$this, 'add_api_routes']);
  }


	/**
	 * Registers a REST API route
	 *
	 * @since 1.0.0
	 */
	public function add_api_routes() {
		register_rest_route( BCAPP_api_namespace, 'speedy-calculate-shipping', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'speedy_calculate_shipping' ),
			'permission_callback'   => '__return_true',
		) );

	}

  public function speedy_calculate_shipping() {
		do_action( 'wp_ajax_speedy_handle_ajax');
	}

  /**
   * Select if shipping method is a courier of type speedy/econt
   */
  public static function add_checkout_fields($config)
  {
    $config->checkout_fields->speedy_shipping_to = array(
      'options' => array(
        'ADDRESS' => 'to address',
        'OFFICE' => 'to office',
        'APT' => 'to APT',
      ),
      'label' => 'Shipping To',
      "type" => "select",
      "default_value" => "ADDRESS",
      "required" => 1,
      "visible" => 1,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );

    $config->checkout_fields->speedy_country_id = array(
      'options' => array(
        '100' => 'Bulgaria',
      ),
      'label' => 'Country',
      "type" => "select",
      "default_value" => '100',
      "required" => 1,
      "visible" => 0,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );

    $config->checkout_fields->speedy_site_id = array(
      'options' => null,
      'label' => 'Locality',
      "type" => "autocomplete",
      "default_value" => "",
      "required" => 1,
      "visible" => 1,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    // To Office
    
    $config->checkout_fields->speedy_pickup_office_id = array(
      'options' => null,
      'label' => 'Office',
      "type" => "autocomplete",
      "default_value" => "",
      "required" => 1,
      "visible" => 1,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    
    // To Machine
    $config->checkout_fields->speedy_pickup_apt_id = array(
      'options' => null,
      'label' => 'APS',
      "type" => "autocomplete",
      "default_value" => "",
      "required" => 1,
      "visible" => 1,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    
    // To Address/Door
    $config->checkout_fields->speedy_street_id = array(
      'options' => null,
      'label' => 'Street',
      "type" => "autocomplete",
      "default_value" => "",
      "required" => 1,
      "visible" => 1,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->speedy_street_no = array(
      'options' => null,
      'label' => 'Street num.:',
      "type" => "text",
      "default_value" => "",
      "required" => 1,
      "visible" => 1,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->speedy_complex_id = array(
      'options' => null,
      'label' => 'Neighborhood',
      "type" => "autocomplete",
      "default_value" => "",
      "required" => 0,
      "visible" => 1,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->speedy_block_no = array(
      'options' => null,
      'label' => 'Street block num.:',
      "type" => "text",
      "default_value" => "",
      "required" => 0,
      "visible" => 1,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->speedy_entrance_no = array(
      'options' => null,
      'label' => 'Street entrance num.:',
      "type" => "text",
      "default_value" => "",
      "required" => 0,
      "visible" => 1,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->speedy_floor_no = array(
      'options' => null,
      'label' => 'Street floor num.:',
      "type" => "text",
      "default_value" => "",
      "required" => 0,
      "visible" => 1,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->speedy_apartment_no = array(
      'options' => null,
      'label' => 'Street apartment num.:',
      "type" => "text",
      "default_value" => "",
      "required" => 0,
      "visible" => 1,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->speedy_address_note = array(
      'options' => null,
      'label' => 'Аdditional аddress note',
      "type" => "text",
      "default_value" => "",
      "required" => 0,
      "visible" => 1,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );

    $config->checkout_fields->speedy_destination_services_id = array(
      'options' => null,
      'label' => 'Services',
      "type" => "select",
      "default_value" => "",
      "required" => 1,
      "visible" => 1,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );

    // Hidden fields

    $config->checkout_fields->speedy_country_name = array(
      'options' => null,
      'label' => 'Country Name',
      "type" => "hidden",
      "default_value" => "България",
      "required" => 0,
      "visible" => 0,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->speedy_site_name = array(
      'options' => null,
      'label' => 'Town/City',
      "type" => "hidden",
      "default_value" => "",
      "required" => 1,
      "visible" => 0,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->speedy_post_code = array(
      'options' => null,
      'label' => 'Postcode',
      "type" => "hidden",
      "default_value" => "",
      "required" => 1,
      "visible" => 0,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->speedy_address_line1 = array(
      'options' => null,
      'label' => 'Street address line 1',
      "type" => "hidden",
      "default_value" => "",
      "required" => 1,
      "visible" => 0,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->speedy_address_line2 = array(
      'options' => null,
      'label' => 'Street address line 2',
      "type" => "hidden",
      "default_value" => "",
      "required" => 0,
      "visible" => 0,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->speedy_street_name = array(
      'options' => null,
      'label' => 'Street name',
      "type" => "hidden",
      "default_value" => "",
      "required" => 0,
      "visible" => 0,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->speedy_complex_name = array(
      'options' => null,
      'label' => 'Complex name',
      "type" => "hidden",
      "default_value" => "",
      "required" => 0,
      "visible" => 0,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->speedy_pickup_office_name = array(
      'options' => null,
      'label' => 'Office name',
      "type" => "hidden",
      "default_value" => "",
      "required" => 0,
      "visible" => 0,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->speedy_pickup_apt_name = array(
      'options' => null,
      'label' => 'Apt name',
      "type" => "hidden",
      "default_value" => "",
      "required" => 0,
      "visible" => 0,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->speedy_destination_services_name = array(
      'options' => null,
      'label' => 'Services name',
      "type" => "hidden",
      "default_value" => "",
      "required" => 0,
      "visible" => 0,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->speedy_total_price = array(
      'options' => null,
      'label' => 'Total Price',
      "type" => "hidden",
      "default_value" => "",
      "required" => 0,
      "visible" => 0,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->speedy_recipient_price = array(
      'options' => null,
      'label' => 'Recepient Price',
      "type" => "hidden",
      "default_value" => "",
      "required" => 0,
      "visible" => 0,
      "location" => "mrejanet_speedy",
      "priority" => 1,
      "columns" => 1
    );


    return $config;
  }
}
