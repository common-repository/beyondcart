<?php

namespace BCAPP\Includes\abstracts;

/**
 * Handle data for the current customers session.
 *
 * Extends WC_Session and added a few tweaks to support our features.
 *
 * @author  Alex Maslarski
 * @package BeyondCart\abstracts
 * @since   1.2.0
 * @version 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BeyondCartSession
 */
abstract class BeyondCartSession extends \WC_Session {

	/**
	 * Stores cart hash.
	 *
	 * @access protected
	 * @var    string $_cart_hash cart hash
	 */
	protected $_cart_hash;

	/**
	 * Get customer ID.
	 *
	 * @access public
	 * @return string
	 */
	public function get_customer_id() {
		return $this->_customer_id;
	}

	/**
	 * Set customer ID.
	 *
	 * @access public
	 * @param  string $customer_id Customer ID.
	 */
	public function set_customer_id( $customer_id ) {
		$this->_customer_id = $customer_id;
	}

	/**
	 * Get session data
	 *
	 * @access public
	 * @return array
	 */
	public function get_data() {
		return $this->_data;
	}

	/**
	 * Get cart hash
	 *
	 * @access public
	 * @return string
	 */
	public function get_cart_hash() {
		return $this->_cart_hash;
	}

}
