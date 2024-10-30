<?php

/**
 * Plugin Name: BeyondCart Connector
 * Plugin URI: https://beyondcart.com
 * Description: Connector to BeyondCart - Mobile App & Powerful Engagement Platform
 * Author: Grind
 * Author URI: https://grind.studio
 * Version: 2.0.1
 * Text Domain: grind-mobile-app
 * Tested up to: 6.6
 * WC requires at least: 5.0
 * WC tested up to: 9.1.2
 * Requires PHP: 7.3
 */

use BCAPP\Includes\Activator;
use BCAPP\Includes\Deactivator;
use BCAPP\Includes\Loader;

if (!defined('ABSPATH')) exit; // Exit if accessed directly
require __DIR__ . '/vendor/autoload.php';

define('BCAPP_plugin_table_name', 'grind_mobile_app');
define('BCAPP_api_namespace', 'grind-mobile-app/v1');
define('BCAPP_plugin_basename', plugin_basename(__FILE__));
define('BCAPP_plugin_dir', plugin_dir_path(__FILE__));
define('BCAPP_plugin_dir_url', plugin_dir_url(__FILE__));


function activate_beyondcart_mobile_builder()
{
	Activator::activate();
}

function deactivate_beyondcart_mobile_builder()
{
	Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_beyondcart_mobile_builder');
register_deactivation_hook(__FILE__, 'deactivate_beyondcart_mobile_builder');

Loader::load();

// Define third party constants used in the plugin - added from plugin settings
define('BCAPP_ONESIGNAL_APP_ID', get_option('grind_mobile_app_onesignal_app_id', null));
define('BCAPP_ONESIGNAL_API_KEY', get_option('grind_mobile_app_onesignal_api_key', null));
define('BCAPP_FIREBASE_SERVER_KEY', get_option('grind_mobile_app_firebase_server_key', null));
define('BCAPP_FB_APP_ID', get_option('grind_mobile_app_facebook_app_id', null));
define('BCAPP_FB_APP_SECRET', get_option('grind_mobile_app_facebook_app_secret', null));
