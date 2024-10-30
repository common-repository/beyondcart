<?php

namespace BCAPP\Api;

use WP_REST_Server;
use WP_REST_Response;

class Coupon
{

	/**
	 * Registers a REST API route
	 *
	 * @since 1.3.0
	 */
	public function add_api_routes()
	{
		register_rest_route(BCAPP_api_namespace, 'coupons/(?P<id>[\d]+)', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array($this, 'deleteUsedBy'),
			'permission_callback' => array($this, 'check_auth'),
		));
	}

	/**
	 * Coupon delete used by for user
	 * @since 1.3.0
	 * @param request
	 * @return object
	 */
	public static function deleteUsedBy($request)
	{
		global $wpdb;

		$coupon_id = $request->get_param('id');
		$used_by = $request->get_param('used_by');

		if (empty($coupon_id) || empty($used_by)) {
			return new \WP_Error('rest_forbidden', __('required parameter is missing.'), ['status' => 401]);
		}

		foreach ($used_by as $key => $id) {
			if (!is_numeric($id) && !filter_var($id, FILTER_VALIDATE_EMAIL)) {
				return new \WP_Error('rest_forbidden', __('used_by value not integer or email.'), ['status' => 401]);
			}
		}
		$used_by_ids = implode(',', array_map(function ($value) {
			return is_numeric($value) ? $value : "'$value'";
		}, $used_by));

		$table_name = $wpdb->prefix . 'postmeta';
		// Delete the row from the postmeta table
		$wpdb->query(
			$wpdb->prepare(
				"
			DELETE FROM $table_name
			WHERE post_id = %d 
			AND meta_key = '_used_by'
			AND meta_value IN ( $used_by_ids )",
				$coupon_id
			)
		);

		$response = [
			'success' => true,
			'message' => 'User is removed from coupon successfully.',
		];
		return new WP_REST_Response($response, 200);
	}

	public static function check_auth($request)
	{
		$token = $request->get_header('token');
		if ($token === 'Grind') {
			return true;
		}

		return false;
	}
}
