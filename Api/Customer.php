<?php
namespace BCAPP\Api;

use Exception;
use Beyondcart\Firebase\JWT\JWT;;
use WC_REST_Customers_Controller;
use \WC_REST_Product_Reviews_Controller;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

class Customer {

    /**
	 * Then key to encode token
	 * @since    1.0.0
	 * @access   private
	 * @var      string $key The key to encode token
	 */
	private $key;

	public function __construct(  ) {
		$this->key         = defined( 'MOBILE_BUILDER_JWT_SECRET_KEY' ) ? MOBILE_BUILDER_JWT_SECRET_KEY : "example_key";
	}

	/**
	 * Registers a REST API route
	 *
	 * @since 1.0.0
	 */
	public function add_api_routes() {
		$review    = new WC_REST_Product_Reviews_Controller();

		/**
		 * @since 1.3.4
		 */
		register_rest_route( BCAPP_api_namespace, 'reviews', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $review, 'create_item' ),
			'permission_callback' => '__return_true',
		) );

		/**
		 * @since 1.3.4
		 */
		register_rest_route( BCAPP_api_namespace, 'customers/(?P<id>[\d]+)', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'update_user' ),
			'permission_callback' => array($this, 'user_permissions_check'),
		) );


        register_rest_route( BCAPP_api_namespace, 'register', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'register' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( BCAPP_api_namespace, 'lost-password', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'retrieve_password' ),
			'permission_callback' => '__return_true',
		) );

        register_rest_route( BCAPP_api_namespace, 'current', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'currentUser' ),
			'permission_callback' => '__return_true',
		) );

        register_rest_route( BCAPP_api_namespace, 'change-password', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'change_password' ),
			'permission_callback' => '__return_true',
		) );


        register_rest_route( BCAPP_api_namespace, 'update-location', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'update_location' ),
			'permission_callback' => '__return_true',
		) );

        register_rest_route( BCAPP_api_namespace, 'delete-user/(?P<id>[\d]+)', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( self::class, 'delete_user' ),
			'permission_callback' => array($this, 'user_permissions_check'),
		) );


        /**
		 * Check mobile phone number
		 */
		register_rest_route( BCAPP_api_namespace, 'check-phone-number', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'mbd_check_phone_number' ),
			'permission_callback' => '__return_true',
		) );

		/**
		 * Check email and username
		 */
		register_rest_route( BCAPP_api_namespace, 'check-info', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'mbd_validate_user_info' ),
			'permission_callback' => '__return_true',
		) );

		// Proxy Endpoints to WooCommerce API
		register_rest_route(BCAPP_api_namespace, '/customers/(?P<id>\d+)', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => 'proxy_to_woocommerce',
			'permission_callback' => array($this, 'user_permissions_check'),
			'args' => array(
				'id' => array(
					'required' => true,
					'validate_callback' => function($param, $request, $key) {
						return is_numeric($param);
					}
				),
			),
		));
	
		register_rest_route(BCAPP_api_namespace, '/orders', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => 'proxy_to_woocommerce',
			'permission_callback' => array($this, 'user_permissions_check'),
		));


	}

	/**
	 * Check mobile phone number
	 *
	 * @since 1.2.0
	 */
	public function mbd_check_phone_number( $request ) {
		$digits_phone = $request->get_param( 'digits_phone' );
		$type         = $request->get_param( 'type' );

		$users = get_users( array(
			"meta_key"     => "digits_phone",
			"meta_value"   => $digits_phone,
			"meta_compare" => "="
		) );

		if ( $type == "register" ) {
			if ( count( $users ) > 0 ) {
				$error = new WP_Error();
				$error->add( 403, __( "Your phone number already exist in database!", "grind-mobile-app" ), array( 'status' => 400 ) );

				return $error;
			}

			return new WP_REST_Response( array( "data" => __( "Phone number not exist!", "grind-mobile-app" ) ), 200 );
		}

		// Login folow
		if ( count( $users ) == 0 ) {
			$error = new WP_Error();
			$error->add( 403, __( "Your phone number not exist in database!", "grind-mobile-app" ), array( 'status' => 400 ) );

			return $error;
		}

		return new WP_REST_Response( array( "data" => __( "Phone number number exist!", "grind-mobile-app" ) ), 200 );
	}

    /**
     * Change User Password
     */
	public function change_password( $request ) {

		$current_user = wp_get_current_user();
		if ( ! $current_user->exists() ) {
			return new WP_Error(
				'user_not_login',
				__( 'Please login first.', "grind-mobile-app" ),
				array(
					'status' => 403,
				)
			);
		}

		$username     = $current_user->user_login;
		$password_old = $request->get_param( 'password_old' );
		$password_new = $request->get_param( 'password_new' );

		// try login with username and password
		$user = wp_authenticate( $username, $password_old );

		if ( is_wp_error( $user ) ) {
			$error_code = $user->get_error_code();

			return new WP_Error(
				$error_code,
				$user->get_error_message( $error_code ),
				array(
					'status' => 403,
				)
			);
		}

		wp_set_password( $password_new, $current_user->ID );

		return $current_user->ID;
	}

	/**
	 *
	 * Update User Location
	 *
	 * @param $request
	 *
	 * @return int|WP_Error
	 * @since 1.4.3
	 *
	 */
	public function update_location( $request ) {

		$current_user = wp_get_current_user();

		if ( ! $current_user->exists() ) {
			return new WP_Error(
				'user_not_login',
				__( 'Please login first.', "grind-mobile-app" ),
				array(
					'status' => 403,
				)
			);
		}

		$location = $request->get_param( 'location' );

		update_user_meta( $current_user->ID, 'mbd_location', $location );

		return $current_user->ID;
	}

	/**
	 *
	 * Update User 
	 *
	 * @param $request
	 *
	 * @return int|WP_Error
	 * @since 1.7.6
	 *
	 */
	public function update_user( $request ) {

		$current_user = wp_get_current_user();

		$user_id = get_current_user_id();
		$id = (int) $request['id'];

		if ( $user_id != $id ) {
			return new \WP_Error( 'rest_user_cannot_edit', __( 'Sorry, you are not allowed to update this user.', "grind-mobile-app" ), array( 'status' => rest_authorization_required_code() ) );
		}

		$customer  = new \WC_REST_Customers_Controller();
		$user = $customer->update_item( $request );

		return $user->data;
	}

	/**
	 * Lost password for user
	 *
	 * @param $request
	 *
	 * @return bool|WP_Error
	 */
	public function retrieve_password( $request ) {
		$errors = new WP_Error();

		$user_login = $request->get_param( 'user_login' );

		if ( empty( $user_login ) || ! is_string( $user_login ) ) {
			$errors->add( 'empty_username', __( '<strong>ERROR</strong>: Enter a username or email address.', "grind-mobile-app" ) );
		} elseif ( strpos( $user_login, '@' ) ) {
			$user_data = get_user_by( 'email', trim( wp_unslash( $user_login ) ) );
			if ( empty( $user_data ) ) {
				$errors->add( 'invalid_email',
					__( '<strong>ERROR</strong>: There is no account with that username or email address.', "grind-mobile-app" ) );
			}
		} else {
			$login     = trim( $user_login );
			$user_data = get_user_by( 'login', $login );
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		if ( ! $user_data ) {
			$errors->add( 'invalidcombo',
				__( '<strong>ERROR</strong>: There is no account with that username or email address.', "grind-mobile-app" ) );

			return $errors;
		}

		// Redefining user_login ensures we return the right case in the email.
		$user_login = $user_data->user_login;
		$user_email = $user_data->user_email;
		$key        = get_password_reset_key( $user_data );

		if ( is_wp_error( $key ) ) {
			return $key;
		}

		if ( is_multisite() ) {
			$site_name = get_network()->site_name;
		} else {
			/*
			 * The blogname option is escaped with esc_html on the way into the database
			 * in sanitize_option we want to reverse this for the plain text arena of emails.
			 */
			$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		}

		$message = __( 'Someone has requested a password reset for the following account:', "grind-mobile-app" ) . "\r\n\r\n";
		/* translators: %s: site name */
		$message .= sprintf( __( 'Site Name: %s', "grind-mobile-app" ), $site_name ) . "\r\n\r\n";
		/* translators: %s: user login */
		$message .= sprintf( __( 'Username: %s', "grind-mobile-app" ), $user_login ) . "\r\n\r\n";
		$message .= __( 'If this was a mistake, just ignore this email and nothing will happen.', "grind-mobile-app" ) . "\r\n\r\n";
		$message .= __( 'To reset your password, visit the following address:', "grind-mobile-app" ) . "\r\n\r\n";
		$message .= '<' . network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ),
				'login' ) . ">\r\n";

		/* translators: Password reset notification email subject. %s: Site title */
		$title = sprintf( __( '[%s] Password Reset', "grind-mobile-app" ), $site_name );

		/**
		 * Filters the subject of the password reset email.
		 *
		 * @param string $title Default email title.
		 * @param string $user_login The username for the user.
		 * @param WP_User $user_data WP_User object.
		 *
		 * @since 4.4.0 Added the `$user_login` and `$user_data` parameters.
		 *
		 * @since 2.8.0
		 */
		$title = apply_filters( 'retrieve_password_title', $title, $user_login, $user_data );

		/**
		 * Filters the message body of the password reset mail.
		 *
		 * If the filtered message is empty, the password reset email will not be sent.
		 *
		 * @param string $message Default mail message.
		 * @param string $key The activation key.
		 * @param string $user_login The username for the user.
		 * @param WP_User $user_data WP_User object.
		 *
		 * @since 2.8.0
		 * @since 4.1.0 Added `$user_login` and `$user_data` parameters.
		 *
		 */
		$message = apply_filters( 'retrieve_password_message', $message, $key, $user_login, $user_data );

		if ( $message && ! wp_mail( $user_email, wp_specialchars_decode( $title ), $message ) ) {
			return new WP_Error(
				'send_email',
				__( 'Possible reason: your host may have disabled the mail() function.', "grind-mobile-app" ),
				array(
					'status' => 403,
				)
			);
		}

		return true;
	}

	/**
	 *  Get currently logged User
	 *
	 * @param $request
	 *
	 * @return mixed
	 */
	public function currentUser( $request ) {
		$current_user = wp_get_current_user();

		return $current_user->data;
	}

	/**
	 *  Validate user
	 *
	 * @param $request
	 *
	 * @return mixed
	 */
	public function mbd_validate_user_info( $request ) {

		$email = $request->get_param( 'email' );
		$name  = $request->get_param( 'name' );

		// Validate email
		if ( ! is_email( $email ) || email_exists( $email ) ) {
			return new WP_Error(
				"email",
				__( "Your input email not valid or exist in database.", "grind-mobile-app" ),
				array(
					'status' => 403,
				)
			);
		}

		// Validate username
		if ( username_exists( $name ) || empty( $name ) ) {
			return new WP_Error(
				"name",
				__( "Your username exist.", "grind-mobile-app" ),
				array(
					'status' => 403,
				)
			);
		}

		return array( "message" => __( "success!", "grind-mobile-app" ) );
	}

	/**
	 *  Register new user
	 *
	 * @param $request
	 *
	 * @return mixed
	 */
	public function register( $request ) {
		$email      = $request->get_param( 'email' );
		$name       = $request->get_param( 'name' );
		$first_name = $request->get_param( 'first_name' );
		$last_name  = $request->get_param( 'last_name' );
		$password   = $request->get_param( 'password' );
		$subscribe  = $request->get_param( 'subscribe' );
		$role       = $request->get_param( 'role' );

		if ( ! $role || $role != 'wcfm_vendor' ) {
			$role = "customer";
		}

		$enable_phone_number = $request->get_param( 'enable_phone_number' );

		// Validate email
		if ( ! is_email( $email ) || email_exists( $email ) ) {
			return new WP_Error(
				"email",
				__( "Your input email not valid or exist in database.", "grind-mobile-app" ),
				array(
					'status' => 403,
				)
			);
		}

		// Validate username
		if ( username_exists( $name ) || empty( $name ) ) {
			return new WP_Error(
				"name",
				__( "Your username exist.", "grind-mobile-app" ),
				array(
					'status' => 403,
				)
			);
		}

		// Validate first name
		if ( mb_strlen( $first_name ) < 2 ) {
			return new WP_Error(
				"first_name",
				__( "First name not valid.", "grind-mobile-app" ),
				array(
					'status' => 403,
				)
			);
		}

		// Validate last name
		if ( mb_strlen( $last_name ) < 2 ) {
			return new WP_Error(
				"last_name",
				__( "Last name not valid.", "grind-mobile-app" ),
				array(
					'status' => 403,
				)
			);
		}

		// Validate password
		if ( empty( $password ) ) {
			return new WP_Error(
				"password",
				__( "Password is required.", "grind-mobile-app" ),
				array(
					'status' => 403,
				)
			);
		}

		$user_id = wp_insert_user( array(
			"user_pass"    => $password,
			"user_email"   => $email,
			"user_login"   => $name,
			"display_name" => "$first_name $last_name",
			"first_name"   => $first_name,
			"last_name"    => $last_name,
			"role"         => $role,

		) );

		if ( is_wp_error( $user_id ) ) {
			$error_code = $user_id->get_error_code();

			return new WP_Error(
				$error_code,
				$user_id->get_error_message( $error_code ),
				array(
					'status' => 403,
				)
			);
		}

		// Update phone number
		if ( $enable_phone_number ) {
			$digits_phone     = $request->get_param( 'digits_phone' );
			$digt_countrycode = $request->get_param( 'digt_countrycode' );
			$digits_phone_no  = $request->get_param( 'digits_phone_no' );

			// Validate phone
			if ( ! $digits_phone || ! $digt_countrycode || ! $digits_phone_no ) {
				wp_delete_user( $user_id );

				return new WP_Error(
					'number_not_validate',
					__( 'Your phone number not validate', "grind-mobile-app" ),
					array(
						'status' => 403,
					)
				);
			}

			// Check phone number in database
			$users = get_users( array(
				"meta_key"     => "digits_phone",
				"meta_value"   => $digits_phone,
				"meta_compare" => "="
			) );

			if ( count( $users ) > 0 ) {
				wp_delete_user( $user_id );

				return new WP_Error(
					'phone_number_exist',
					__( "Your phone number already exist in database!", "grind-mobile-app" ),
					array( 'status' => 400 )
				);
			}

			add_user_meta( $user_id, 'digt_countrycode', $digt_countrycode, true );
			add_user_meta( $user_id, 'digits_phone_no', $digits_phone_no, true );
			add_user_meta( $user_id, 'digits_phone', $digits_phone, true );
		}

		// Subscribe
		add_user_meta( $user_id, 'mbd_subscribe', $subscribe, true );

		$user  = get_user_by( 'id', $user_id );
		$token = Auth::generate_token( $user );
		$data  = array(
			'token' => $token,
			'user'  => self::mbd_get_userdata( $user ),
		);

		return $data;

	}

    /**
     * Hooked to filter: determine_current_user in Loader class
     */
	public function determine_current_user( $user ) {
		// Run only on REST API
		if ( ! beyondcart_mobile_builder_is_rest_api_request() ) {
			return $user;
		}

		$token = beyondcart_mobile_builder_token();

		if ( $token ) {
			$user = $this->decode( $token );
			if(!empty($user->data)) {
				return $user->data->user_id;
			}
		}

		return $user;
	}

	/**
	 * Decode token
	 * @return array|WP_Error
	 */
	public function decode( $token = '' ) {
		/*
		 * Get token on header
		 */

		if ( ! $token ) {

			$token = beyondcart_mobile_builder_token();

			if ( ! $token ) {
				return new WP_Error(
					'no_auth_header',
					__( 'Authorization header not found.', "grind-mobile-app" ),
					array(
						'status' => 403,
					)
				);
			}
		}

		$match = preg_match( '/Bearer\s(\S+)/', $token, $matches );

		if ( ! $match ) {
			return new WP_Error(
				'token_not_validate',
				__( 'Token not validate format.', "grind-mobile-app" ),
				array(
					'status' => 403,
				)
			);
		}

		$token = $matches[1];

		/** decode token */
		try {
			$data = JWT::decode( $token, $this->key, array( 'HS256' ) );

			if ( $data->iss != get_bloginfo( 'url' ) ) {
				return new WP_Error(
					'bad_iss',
					__( 'The iss do not match with this server', "grind-mobile-app" ),
					array(
						'status' => 403,
					)
				);
			}
			if ( ! isset( $data->data->user_id ) ) {
				return new WP_Error(
					'id_not_found',
					__( 'User ID not found in the token', "grind-mobile-app" ),
					array(
						'status' => 403,
					)
				);
			}

			return $data;

		} catch ( Exception $e ) {
			return new WP_Error(
				'invalid_token',
				$e->getMessage(),
				array(
					'status' => 403,
				)
			);
		}
	}

    	/**
	 * @param $user
	 *
	 * Add more info to user data response
	 *
	 * @return mixed
	 * @since 1.3.7
	 *
	 */
	public static function mbd_get_userdata( $user ) {

		$user_data             = $user->data;
		$user_data->first_name = $user->first_name;
		$user_data->last_name  = $user->last_name;
		$user_data->avatar     = 'https://www.gravatar.com/avatar/' . md5( $user_data->user_email );
		$user_data->location   = get_user_meta( $user->ID, 'mbd_location', true );
		$user_data->roles      = $user->roles;

		return $user_data;
	}


	/**
	 *
	 * Delete User
	 *
	 * @param $request
	 */
	public static function delete_user($request)
	{
        $user_id = get_current_user_id();
		$id = (int) $request['id'];

		if ( $user_id != $id ) {
			return new \WP_Error( 'rest_user_cannot_delete', __( 'Sorry, you are not allowed to delete this user.', "grind-mobile-app" ), array( 'status' => rest_authorization_required_code() ) );
		}
		require_once(ABSPATH.'wp-admin/includes/user.php');
		$result = \wp_delete_user( $user_id );

		return $result;
	}


	/**
	 * Check if a given request has access. Checks for Bearer token in Authorization header.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|boolean
	 *
	 * @since 1.3.4
	 */
	public function user_permissions_check( $request ) {
		$id = (int) $request['id'];

		if(!isset($request['id'])) {
			$id = (int) $request['customer'];
		}
		

		if ( get_current_user_id() != $id ) {
			return new WP_Error( 'BeyondCart', __( 'Sorry, you are not allowed to do this.', "grind-mobile-app" ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}


}
