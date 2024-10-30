<?php
namespace BCAPP\Includes;

class Activator {

	public static function activate() {
		// Install database tables.
		self::create_tables();
	}

	private static function create_tables() {
		global $wpdb;

		$table_name_templates = $wpdb->prefix . BCAPP_plugin_table_name;
		$table_name_carts     = $wpdb->prefix . BCAPP_plugin_table_name . '_carts';
		$table_name_terms     = $wpdb->prefix . BCAPP_plugin_table_name . '_terms';

		$wpdb->hide_errors();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		// Queries create terms table 
		$table_terms = "CREATE TABLE {$table_name_terms} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			category_id INT NOT NULL,
			data longtext NULL DEFAULT NULL,
			date_created DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			date_updated DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			UNIQUE KEY category_id (category_id)
		) $collate;";

		// Queries create carts table
		$table_carts = "CREATE TABLE {$table_name_carts} (
					cart_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
					blog_id INT NOT NULL,
					cart_key char(42) NOT NULL,
					cart_value longtext NOT NULL,
					cart_expiry BIGINT UNSIGNED NOT NULL,
					PRIMARY KEY (cart_id),
					UNIQUE KEY cart_key (cart_key)
				) $collate;";

		// Queries create templates table
		$table_templates = "CREATE TABLE {$table_name_templates} (
		  id mediumint(9) NOT NULL AUTO_INCREMENT,
		  name VARCHAR(254) NULL DEFAULT 'Template Name',
		  data longtext NULL DEFAULT NULL,
		  settings longtext NULL DEFAULT NULL,
		  status TINYINT NOT NULL DEFAULT '0',
		  date_created DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
		  date_updated DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
		  PRIMARY KEY (id)
		) $collate;";

		// Execute
		dbDelta( $table_carts );
		dbDelta( $table_templates );
		dbDelta( $table_terms );
	} // END create_tables()

}
