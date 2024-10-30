<?php
namespace BCAPP\Includes\Integrations\MrejanetEcont;

if (!defined('ABSPATH')) exit;
// Exit if accessed directly

class BeyondcartMrejanetEcontIntegration
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
		register_rest_route( BCAPP_api_namespace, 'econt-calculate-shipping', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'econt_calculate_shipping' ),
			'permission_callback'   => '__return_true',
		) );

	}

  public function econt_calculate_shipping() {
		do_action( 'wp_ajax_econt_handle_ajax');
	}

  /**
   * Select if shipping method is a courier of type speedy/econt
   */
  public static function add_checkout_fields($config)
  {
    $config->checkout_fields->econt_shipping_to = array(
      'options' => array(
        'DOOR' => 'to door',
        'OFFICE' => 'to office',
        'MACHINE' => 'to APS',
      ),
      'label' => 'Shipping To',
      "type" => "select",
      "default_value" => "DOOR",
      "required" => 1,
      "visible" => 1,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );

    // To Office
    $config->checkout_fields->econt_offices_town = array(
      'options' => null,
      'label' => 'Town (Please, start typing and select from results.)',
      "type" => "autocomplete",
      "default_value" => "",
      "required" => 1,
      "visible" => 1,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->econt_offices_town_city_id = array(
      'options' => null,
      'label' => 'City ID',
      "type" => "hidden",
      "default_value" => "",
      "required" => 0,
      "visible" => 0,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->econt_offices_postcode = array(
      'options' => null,
      'label' => 'Postcode',
      "type" => "text",
      "default_value" => "",
      "required" => 1,
      "visible" => 1,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->econt_offices = array(
      'options' => null,
      'label' => 'Office',
      "type" => "autocomplete",
      "default_value" => "",
      "required" => 1,
      "visible" => 1,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->econt_offices_name = array(
      'options' => null,
      'label' => 'Office name',
      "type" => "hidden",
      "default_value" => "",
      "required" => 0,
      "visible" => 0,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );
    
    // To Machine
    $config->checkout_fields->econt_machines_town = array(
      'options' => null,
      'label' => 'Town (Please, start typing and select from results.)',
      "type" => "autocomplete",
      "default_value" => "",
      "required" => 1,
      "visible" => 1,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->econt_machines_town_city_id = array(
      'options' => null,
      'label' => 'City ID',
      "type" => "hidden",
      "default_value" => "",
      "required" => 0,
      "visible" => 0,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->econt_machines_postcode = array(
      'options' => null,
      'label' => 'Postcode',
      "type" => "text",
      "default_value" => "",
      "required" => 1,
      "visible" => 1,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->econt_machines = array(
      'options' => null,
      'label' => 'APS',
      "type" => "autocomplete",
      "default_value" => "",
      "required" => 1,
      "visible" => 1,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->econt_machines_name = array(
      'options' => null,
      'label' => 'Machine name',
      "type" => "hidden",
      "default_value" => "",
      "required" => 0,
      "visible" => 0,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );

    // To Door
    $config->checkout_fields->econt_door_town = array(
      'options' => null,
      'label' => 'Town (Please, start typing and select from results.)',
      "type" => "autocomplete",
      "default_value" => "",
      "required" => 1,
      "visible" => 1,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->econt_door_postcode = array(
      'options' => null,
      'label' => 'Postcode',
      "type" => "text",
      "default_value" => "",
      "required" => 1,
      "visible" => 1,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->econt_door_street = array(
      'options' => null,
      'label' => 'Street (Please, start typing and select from results.)',
      "type" => "autocomplete",
      "default_value" => "",
      "required" => 1,
      "visible" => 1,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->econt_door_street_num = array(
      'options' => null,
      'label' => 'Str. num.',
      "type" => "text",
      "default_value" => "",
      "required" => 1,
      "visible" => 1,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->econt_door_quarter = array(
      'options' => null,
      'label' => 'Quarter (Please, start typing and select from results.)',
      "type" => "autocomplete",
      "default_value" => "",
      "required" => 0,
      "visible" => 1,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->econt_door_street_bl = array(
      'options' => null,
      'label' => 'bl. num.',
      "type" => "text",
      "default_value" => "",
      "required" => 0,
      "visible" => 1,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->econt_door_street_vh = array(
      'options' => null,
      'label' => 'entr. num.',
      "type" => "text",
      "default_value" => "",
      "required" => 0,
      "visible" => 1,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->econt_door_street_et = array(
      'options' => null,
      'label' => 'fl. num.',
      "type" => "text",
      "default_value" => "",
      "required" => 0,
      "visible" => 1,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->econt_door_street_ap = array(
      'options' => null,
      'label' => 'ap. num.',
      "type" => "text",
      "default_value" => "",
      "required" => 0,
      "visible" => 1,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );
    $config->checkout_fields->econt_door_other = array(
      'options' => null,
      'label' => 'If your address is not in the list please type it here:',
      "type" => "text",
      "default_value" => "",
      "required" => 0,
      "visible" => 1,
      "location" => "mrejanet_econt",
      "priority" => 1,
      "columns" => 1
    );

    return $config;
  }
}
