<?php
namespace BCAPP\Includes;


class I18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public static function load_plugin_textdomain() {

		load_plugin_textdomain(
			'grind-mobile-app',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/Languages/'
		);

	}



}
