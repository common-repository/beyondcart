<?php

namespace BCAPP\Includes;

use BCAPP\Includes\abstracts\BeyondCartSession;
use WC_Session;


if (!defined('ABSPATH')) {
	exit;
}

// Checks that BeyondCart session abstract exists first.
if (!class_exists('BCAPP\Includes\abstracts\BeyondCartSession')) {
	return;
}

/**
 * Session handler class.
 */
class BeyondCartSessionHandler extends BeyondCartSession
{

	/**
	 * Table name for cart data.
	 *
	 * @var string Custom cart table name
	 */
	protected $_table;

	/**
	 * Stores cart expiry.
	 *
	 * @var string cart due to expire timestamp
	 */
	protected $_cart_expiring;

	/**
	 * Stores cart due to expire timestamp.
	 *
	 * @var string cart expiration timestamp
	 */
	protected $_cart_expiration;

	/**
	 * Constructor for the session class.
	 */
	public function __construct()
	{
		global $wpdb;
		$this->_table = $wpdb->prefix . BCAPP_plugin_table_name . '_carts';
	}

	/**
	 * Init hooks and session data.
	 * 
	 * @access public
	 * @since 1.0.0
	 * @version 1.2.0
	 */
	public function init()
	{

		// Current user ID. If user is NOT logged in then the customer is a guest.
		$current_user_id = strval(get_current_user_id());
		$this->init_session_cart($current_user_id);

		add_action('shutdown', array($this, 'save_cart'), 20);
		// @audit this hook is the reason why guest carts get deleted on webview checkout
		add_action('wp_logout', array($this, 'destroy_cart'));

		if ( is_numeric( $current_user_id ) && $current_user_id < 1 ) {
			add_filter( 'nonce_user_logged_out', array( $this, 'nonce_user_logged_out' ) );
		}

	}


	/**
	 * Setup cart.
	 *
	 * @access  public
	 * @since   1.2.0
	 * @version 1.2.0
	 * @param   int $current_user_id Current user ID.
	 */
	public function init_session_cart($current_user_id = 0)
	{
		// Check if we requested to load a specific cart.
		if (isset($_REQUEST['cart_key'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// Set requested cart key as customer ID in session.
			$this->_customer_id = (string) trim(sanitize_key(wp_unslash($_REQUEST['cart_key']))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		// If cart retrieved then update cart.
		if ($this->_customer_id) {
			$this->_data = $this->get_cart_data();

			// If the user logged in, update cart.
			if (is_numeric($current_user_id) && $current_user_id > 0 && $current_user_id !== $this->_customer_id) {
				// Update customer ID details.
				$guest_cart_id      = $this->_customer_id;
				$this->_customer_id = $current_user_id;

				// Save cart data under customers ID number and remove old guest cart.
				$this->save_cart($guest_cart_id);
			}

			// Update cart if its close to expiring.
			if (time() > $this->_cart_expiring || empty($this->_cart_expiring)) {
				$this->set_cart_expiration();
				$this->update_cart_timestamp($this->_customer_id, $this->_cart_expiration);
			}
		} else {
			// New guest customer.
			$this->set_cart_expiration();
			$this->_customer_id = $this->generate_customer_id();
			$this->_data        = $this->get_cart_data();
		}
	} // END init_session_cart()

	/**
	 *
	 * Get cart key saved in database
	 *
	 * @return string
	 */
	public function get_cart_key()
	{
		return $this->_customer_id;
	}


	/**
	 * Return true if the current user has an active cart.
	 *
	 * @access public
	 * @since   1.2.0
	 * @version 1.2.0
	 * @return bool
	 */
	public function has_session()
	{
		// Current user ID. If value is above zero then user is logged in.
		$current_user_id = strval(get_current_user_id());
		if (is_numeric($current_user_id) && $current_user_id > 0) {
			return true;
		}

		if (!empty($this->_customer_id)) {
			return true;
		}

		return false;
	} // END has_session()

	/**
	 * Get cart data.
	 *
	 * @access public
	 * @return array
	 */
	public function get_cart_data()
	{
		return $this->has_session() ? (array) $this->get_cart($this->_customer_id, array()) : array();
	} // END get_cart_data()

	/**
	 * Returns the cart.
	 *
	 * @access public
	 * @param  string $cart_key The customer ID or cart key.
	 * @param  mixed  $default  Default cart value.
	 * @global $wpdb
	 * @return string|array
	 */
	public function get_cart($cart_key, $default = false)
	{
		global $wpdb;
		$cart_db_table = $wpdb->prefix . BCAPP_plugin_table_name . '_carts';

		$value = $wpdb->get_var(
			$wpdb->prepare("SELECT cart_value FROM {$cart_db_table} WHERE cart_key = %s", $cart_key)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if (is_null($value)) {
			$value = $default;
		}

		return maybe_unserialize($value);
	} // END get_cart()

		/**
	 * Returns the session.
	 *
	 * @access public
	 * @since  3.1.0
	 * @param  string $cart_key The customer ID or cart key.
	 * @param  mixed  $default  Default cart value.
	 * @return string|array
	 */
	public function get_session( $cart_key, $default = false ) {
		return $this->get_cart( $cart_key, $default );
	} // END get_session()


	/**
	 * Delete the cart from the database.
	 *
	 * @access public
	 *
	 * @param string $customer_id Customer ID.
	 *
	 * @global $wpdb
	 */
	public function delete_cart($customer_id)
	{
		global $wpdb;

		// Delete cart from database.
		$wpdb->delete($this->_table, array('cart_key' => $customer_id));
	}

	/**
	 * Generate a unique customer ID for guests, or return user ID if logged in.
	 *
	 * Uses Portable PHP password hashing framework to generate a unique cryptographically strong ID.
	 *
	 * @return string
	 */
	public function generate_customer_id()
	{
		$customer_id = '';

		$current_user_id = strval(get_current_user_id());
		if (is_numeric($current_user_id) && $current_user_id > 0) {
			$customer_id = $current_user_id;
		}

		if (empty($customer_id)) {
			require_once ABSPATH . 'wp-includes/class-phpass.php';
			$hasher      = new \PasswordHash(8, false);
			$customer_id = md5($hasher->get_random_bytes(32));
		}

		return $customer_id;
	}

	/**
	 * Save cart data and delete previous cart data.
	 *
	 * @access  public
	 * @since   1.0.0
	 * @version 1.2.0
	 * @param   int $old_cart_key cart ID before user logs in.
	 * @global  $wpdb
	 */
	public function save_cart($old_cart_key = 0)
	{
		if ($this->has_session()) {
			global $wpdb;
			if (!$this->_data || empty($this->_data) || is_null($this->_data)) {
				return true;
			}

			$cart_db_table = $wpdb->prefix . BCAPP_plugin_table_name . '_carts';

			// Save or update cart data.
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$cart_db_table} (`cart_key`, `blog_id`, `cart_value`, `cart_expiry`) VALUES (%s, %s, %s, %d)
 					ON DUPLICATE KEY UPDATE `cart_key` = VALUES(`cart_key`), `cart_value` = VALUES(`cart_value`), `cart_expiry` = VALUES(`cart_expiry`)",
					$this->_customer_id,
					get_current_blog_id(),
					maybe_serialize($this->_data),
					$this->_cart_expiration
				)
			);

			// Customer is now registered so we delete the previous cart as guest to prevent duplication.
			if (get_current_user_id() !== $old_cart_key && !is_object(get_user_by('id', $old_cart_key))) {
				$this->delete_cart($old_cart_key);
			}
		}
	} // END save_cart()

	/**
	 * Destroy all cart data.
	 */
	public function destroy_cart()
	{
		wc_empty_cart();
		$this->delete_cart($this->_customer_id);
		$this->_data  = array();
		$this->_dirty = false;
	}

	/**
	 * When a user is logged out, ensure they have a unique nonce by using the customer/session ID.
	 *
	 * @param int $uid User ID.
	 *
	 * @return string
	 */
	public function nonce_user_logged_out($uid)
	{
		return $this->_customer_id ? $this->_customer_id : $uid;
	}

	/**
	 * Set cart expiration.
	 *
	 * @access public
	 */
	public function set_cart_expiration()
	{
		$this->_cart_expiring   = time() + intval(DAY_IN_SECONDS * 6); // 6 Days.
		$this->_cart_expiration = time() + intval(DAY_IN_SECONDS * 7); // 7 Days.
	}

	/**
	 * Update the cart expiry timestamp.
	 *
	 * @access public
	 * @param  string $cart_key  The cart key.
	 * @param  int    $timestamp Timestamp to expire the cookie.
	 * @global $wpdb
	 */
	public function update_cart_timestamp($cart_key, $timestamp)
	{
		global $wpdb;

		$wpdb->update(
			$this->_table,
			array('cart_expiry' => $timestamp),
			array('cart_key' => $cart_key),
			array('%d'),
			array('%s')
		);
	} // END update_cart_timestamp()

	/**
	 * Overwrites WC_Session_Handler method that saves the data in woocommers_sessions
	 * and breaks our app to create a order since Woocommerce v8.3.1
	 * That's why we just overwrite the method and do nothing.
	 * 
	 * Save data and delete guest session.
	 *
	 * @param int $old_session_key session ID before user logs in.
	 */
	public function save_data( $old_session_key = 0 ) {
	}
}
