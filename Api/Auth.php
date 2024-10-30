<?php

namespace BCAPP\Api;

use WP_REST_Server;
use Beyondcart\Firebase\JWT\JWT;
use BCAPP\Includes\PublicKey;

class Auth
{

	/**
	 * Registers a REST API route
	 *
	 * @since 1.0.0
	 */
	public function add_api_routes()
	{

		register_rest_route( BCAPP_api_namespace, 'token', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'app_token' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( BCAPP_api_namespace, 'login', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'login' ),
			'permission_callback' => '__return_true',
		) );

		// Temp Endpoint for App migration
		register_rest_route( BCAPP_api_namespace, 'login-with-hash', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'loginWithHashTemp' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( BCAPP_api_namespace, 'logout', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'logout' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( BCAPP_api_namespace, 'google', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'login_google' ),
			'permission_callback' => '__return_true',
		) );


		register_rest_route( BCAPP_api_namespace, 'facebook', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'login_facebook' ),
			'permission_callback' => '__return_true',
		) );


		register_rest_route( BCAPP_api_namespace, 'apple', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'login_apple' ),
			'permission_callback' => '__return_true',
		) );


		register_rest_route( BCAPP_api_namespace, 'login-otp', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'login_otp' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route(BCAPP_api_namespace, 'auto-login', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'auto_login' ),
			'permission_callback' => '__return_true',
		]);

	}


	/**
	 * Create token for app
	 *
	 * @param $request
	 *
	 * @return bool|WP_Error
	 */
	public function app_token() {

		$wp_auth_user = defined( 'WP_AUTH_USER' ) ? WP_AUTH_USER : "wp_auth_user";

		$user = get_user_by( 'login', $wp_auth_user );

		if ( $user ) {
			$token = self::generate_token( $user, array( 'read_only' => true ) );

			return $token;
		} else {
			return new WP_Error(
				'create_token_error',
				__( 'You did not create user wp_auth_user', "grind-mobile-app" ),
				array(
					'status' => 403,
				)
			);
		}
	}

	/**
	 * Do login with email and password
	 */
	public function login( $request ) {

		$username = $request->get_param( 'username' );
		$password = $request->get_param( 'password' );

		// try login with username and password
		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		// Generate token
		$token = self::generate_token( $user );

		// Return data
		$data = array(
			'token' => $token,
			'user'  => Customer::mbd_get_userdata( $user ),
		);

		return $data;
	}

	/**
	 * Do login with email and password
	 * Temporary method when migration to new version of new apps
	 * To be able to keep the users logged in
	 * We should remove the method and endpoint from the app and plugin after few months
	 */
    public function loginWithHashTemp( $request ) {
        global $wpdb;
        
        $username = $request->get_param( 'username' );
        $hashed_password = $request->get_param( 'password' );  // This should be the hashed password
        
        // Retrieve the stored hashed password from the database
        $stored_hashed_password = $wpdb->get_var( $wpdb->prepare(
            "SELECT user_pass FROM $wpdb->users WHERE user_login = %s",
            $username
        ));
    
        if (!$stored_hashed_password) {
            return new \WP_Error('invalid_username', __('Invalid username'));
        }
    
        // Compare the hashed passwords
        if ($stored_hashed_password === $hashed_password) {
            // Authenticated, proceed here
    
            $user = get_user_by('login', $username);
    
            // Generate token
            $token = self::generate_token( $user );
    
            // Return data
            $data = array(
                'token' => $token,
                'user'  => Customer::mbd_get_userdata( $user ),
            );
    
            return $data;
        } else {
            return new \WP_Error('incorrect_password', __('The password you entered is incorrect.'));
        }
	}


	/**
	 *
	 * Log out user
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function logout(\WP_REST_Request $request) {
	    
		// Get the Authorization header
		$auth_header = $request->get_header('Authorization');

		if (!$auth_header) {
			return array("success" => false, "error" => "Can't get Authorization header.");
		}

		$token = substr($auth_header, 7); // Get just the token from Bearer e9434...
		$key = defined('MOBILE_BUILDER_JWT_SECRET_KEY') ? MOBILE_BUILDER_JWT_SECRET_KEY : "example_key";
		$data = JWT::decode($token, $key, array('HS256'));
		$user_id = $data->data->user_id;
	   
		if (!$user_id) {
			return array("success" => false, "error" => "There is no user_id.");
		}

		wp_set_current_user($user_id);
		wp_clear_auth_cookie();

		return array("success" => true);
   }

	/**
	 *
	 * Set user login
	 * This is called when we use Native app with Webview Checkout
	 * It actually set the native session into the webview checkout
	 *
	 * @param $request
	 */
	public function auto_login($request)
	{

		$theme    = $request->get_param('theme');
		$currency = $request->get_param('currency');
		$cart_key = $request->get_param('cart_key');
		$lang = $request->get_param('lang');
		$timestamp = time();

		$user_id = get_current_user_id();
		setcookie('mobile', 1, time() + 62208000, '/', sanitize_text_field($_SERVER['HTTP_HOST']));
		
		if ($user_id > 0) {
			$user = get_user_by('id', $user_id);
			wp_set_current_user($user_id, $user->user_login);
			wp_set_auth_cookie($user_id);
		} else {
			// @audit This logs out the user and deletes the session, but used to delete the guest cart as well.
			// 			wp_logout();
			// @autid This is the new way to delete the guest cart.
			wp_destroy_current_session();
			wp_clear_auth_cookie();
			wp_set_current_user(0);
		}

		$checkout_url = wc_get_checkout_url();

		if (!empty($lang)) {
			// For WPML
			if (function_exists('icl_object_id')) {
				$translated_checkout_id = apply_filters('wpml_object_id', get_option('woocommerce_checkout_page_id'), 'page', true, $lang);
				if ($translated_checkout_id) {
					$checkout_url = get_permalink($translated_checkout_id);
				}
			}
			// For Polylang 
			if (function_exists('pll_get_post')) {
			    $translated_checkout_id = pll_get_post(get_option('woocommerce_checkout_page_id'), $lang);
			    if ($translated_checkout_id) {
			        $checkout_url = get_permalink($translated_checkout_id);
			    }
			}
		}

		wp_redirect($checkout_url . "?mobile=1&theme=$theme&currency=$currency&cart_key=$cart_key&time=$timestamp");
		exit;
	}

		/**
	 * Login with google
	 *
	 * @param $request
	 */
	public function login_google( $request ) {
		$idToken = $request->get_param( 'idToken' );

		$url  = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . $idToken;
		$data = array( 'idToken' => $idToken );

		// use key 'http' even if you send the request to https://...
		$options = array(
			'http' => array(
				'header' => "application/json; charset=UTF-8\r\n",
				'method' => 'GET'
			)
		);

		$context = stream_context_create( $options );
		$json    = $this->getUrlContent( $url );
		$result  = json_decode( $json );

		if ( $result === false ) {
			$error = new WP_Error();
			$error->add( 403, __( "Get Firebase user info error!", "grind-mobile-app" ), array( 'status' => 400 ) );

			return $error;
		}

		// Email not exist
		$email = $result->email;
		if ( ! $email ) {
			return new WP_Error(
				'email_not_exist',
				__( 'User not provider email', "grind-mobile-app" ),
				array(
					'status' => 403,
				)
			);
		}

		$user = get_user_by( 'email', $email );

		// Return data if user exist in database
		if ( $user ) {
			$token = self::generate_token( $user );
			$data  = array(
				'token' => $token,
				'user'  => Customer::mbd_get_userdata( $user ),
			);

			// TODO get cart
			return $data;
		} else {

			$user_id = wp_insert_user( array(
				"user_pass"     => wp_generate_password(),
				"user_login"    => $result->email,
				"user_nicename" => $result->name,
				"user_email"    => $result->email,
				"display_name"  => $result->name,
				"first_name"    => $result->given_name,
				"last_name"     => $result->family_name

			) );

			if ( is_wp_error( $user_id ) ) {
				$error_code = $user->get_error_code();

				return new WP_Error(
					$error_code,
					$user_id->get_error_message( $error_code ),
					array(
						'status' => 403,
					)
				);
			}

			$user = get_user_by( 'id', $user_id );

			$token = self::generate_token( $user );
			$data  = array(
				'token' => $token,
				'user'  => Customer::mbd_get_userdata( $user ),
			);

			add_user_meta( $user_id, 'mbd_login_method', 'google', true );
			add_user_meta( $user_id, 'mbd_avatar', $result->picture, true );

			return $data;
		}
	}

	/**
	 * Do login with Facebook
	 */
	public function login_facebook( $request ) {
		$token = $request->get_param( 'token' );

		// TODO

		$fb = new \Facebook\Facebook( [
			'app_id'                => BCAPP_FB_APP_ID,
			'app_secret'            => BCAPP_FB_APP_SECRET,
			'default_graph_version' => 'v2.10',
			//'default_access_token' => '{access-token}', // optional
		] );

		try {
			// Get the \Facebook\GraphNodes\GraphUser object for the current user.
			// If you provided a 'default_access_token', the '{access-token}' is optional.
			$response = $fb->get( '/me?fields=id,first_name,last_name,name,picture,email', $token );
		} catch ( \Facebook\Exceptions\FacebookResponseException $e ) {
			// When Graph returns an error
			echo __( 'Graph returned an error: ', "grind-mobile-app" ) . esc_html($e->getMessage());
			exit;
		} catch ( \Facebook\Exceptions\FacebookSDKException $e ) {
			// When validation fails or other local issues
			echo __( 'Facebook SDK returned an error: ', "grind-mobile-app" ) . esc_html($e->getMessage());
			exit;
		}

		$me = $response->getGraphUser();

		// Email not exist
		$email = $me->getEmail();
		if ( ! $email ) {
			return new WP_Error(
				'email_not_exist',
				__( 'User not provider email', "grind-mobile-app" ),
				array(
					'status' => 403,
				)
			);
		}

		$user = get_user_by( 'email', $email );

		// Return data if user exist in database
		if ( $user ) {
			$token = self::generate_token( $user );
			$data  = array(
				'token' => $token,
				'user'  => Customer::mbd_get_userdata( $user ),
			);

			return $data;
		} else {
			// Will create new user
			$first_name  = $me->getFirstName();
			$last_name   = $me->getLastName();
			$picture     = $me->getPicture();
			$name        = $me->getName();
			$facebook_id = $me->getId();

			$user_id = wp_insert_user( array(
				"user_pass"     => wp_generate_password(),
				"user_login"    => $email,
				"user_nicename" => $name,
				"user_email"    => $email,
				"display_name"  => $name,
				"first_name"    => $first_name,
				"last_name"     => $last_name

			) );

			if ( is_wp_error( $user_id ) ) {
				$error_code = $user->get_error_code();

				return new WP_Error(
					$error_code,
					$user_id->get_error_message( $error_code ),
					array(
						'status' => 403,
					)
				);
			}

			$user = get_user_by( 'id', $user_id );

			$token = self::generate_token( $user );
			$data  = array(
				'token' => $token,
				'user'  => Customer::mbd_get_userdata( $user ),
			);

			add_user_meta( $user_id, 'mbd_login_method', 'facebook', true );
			add_user_meta( $user_id, 'mbd_avatar', $picture, true );

			return $data;
		}

	}


	/**
	 * Login With Apple
	 *
	 * @param $request
	 *
	 * @return array | object
	 * @throws Exception
	 */
	public function login_apple( $request ) {
		try {
			$identityToken = $request->get_param( 'identityToken' );
			$userIdentity  = $request->get_param( 'user' );
			$fullName      = $request->get_param( 'fullName' );

			$tks = \explode( '.', $identityToken );
			if ( \count( $tks ) != 3 ) {
				return new WP_Error(
					'error_login_apple',
					__( 'Wrong number of segments', "grind-mobile-app" ),
					array(
						'status' => 403,
					)
				);
			}

			list( $headb64 ) = $tks;

			if ( null === ( $header = JWT::jsonDecode( JWT::urlsafeB64Decode( $headb64 ) ) ) ) {
				return new WP_Error(
					'error_login_apple',
					__( 'Invalid header encoding', "grind-mobile-app" ),
					array(
						'status' => 403,
					)
				);
			}

			if ( ! isset( $header->kid ) ) {
				return new WP_Error(
					'error_login_apple',
					__( '"kid" empty, unable to lookup correct key', "grind-mobile-app" ),
					array(
						'status' => 403,
					)
				);
			}

			$publicKeyDetails = PublicKey::getPublicKey( $header->kid );
			$publicKey        = $publicKeyDetails['publicKey'];
			$alg              = $publicKeyDetails['alg'];

			$payload = JWT::decode( $identityToken, $publicKey, [ $alg ] );

			if ( $payload->sub !== $userIdentity ) {
				return new WP_Error(
					'validate-user',
					__( 'User not validate', "grind-mobile-app" ),
					array(
						'status' => 403,
					)
				);
			}

			$user1 = get_user_by( 'email', $payload->email );
			$user2 = get_user_by( 'login', $userIdentity );

			// Return data if user exist in database
			if ( $user1 ) {
				$token = self::generate_token( $user1 );

				return array(
					'token' => $token,
					'user'  => Customer::mbd_get_userdata( $user1 ),
				);
			}

			if ( $user2 ) {
				$token = self::generate_token( $user2 );

				return array(
					'token' => $token,
					'user'  => Customer::mbd_get_userdata( $user2 ),
				);
			}

			$userdata = array(
				"user_pass"    => wp_generate_password(),
				"user_login"   => $userIdentity,
				"user_email"   => $payload->email,
				"display_name" => $fullName['familyName'] . " " . $fullName['givenName'],
				"first_name"   => $fullName['familyName'],
				"last_name"    => $fullName['givenName']
			);

			$user_id = wp_insert_user( $userdata );

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

			$user = get_user_by( 'id', $user_id );

			$token = self::generate_token( $user );

			add_user_meta( $user_id, 'mbd_login_method', 'apple', true );

			return array(
				'token' => $token,
				'user'  => Customer::mbd_get_userdata( $user ),
			);

		} catch ( Exception $e ) {
			return new WP_Error(
				'error_login_apple',
				$e->getMessage(),
				array(
					'status' => 403,
				)
			);
		}
	}


	/**
	 * Do login with with otp
	 */
	public function login_otp( $request ) {

		try {

			if ( ! defined( 'BCAPP_FIREBASE_SERVER_KEY' ) ) {
				return new WP_Error(
					'not_exist_firebase_server_key',
					__( 'The BCAPP_FIREBASE_SERVER_KEY not define in wp-config.php', "grind-mobile-app" ),
					array(
						'status' => 403,
					)
				);
			}

			$idToken = $request->get_param( 'idToken' );

			$url  = 'https://www.googleapis.com/identitytoolkit/v3/relyingparty/getAccountInfo?key=' . BCAPP_FIREBASE_SERVER_KEY;
			$data = array( 'idToken' => $idToken );

			$args = array(
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => http_build_query( $data ),
				'method'  => 'POST',
			);

			$response = wp_remote_post( $url, $args );

			if ( is_wp_error( $response ) ) {
				throw new Exception( __('Error fetching account info from Google API', "mobile-builder") );
			}

			$json   = wp_remote_retrieve_body( $response );
			$result = json_decode( $json );

			if ( $result === false ) {
				$error = new WP_Error();
				$error->add( 403, __( "Get Firebase user info error!", "grind-mobile-app" ), array( 'status' => 400 ) );

				return $error;
			}

			if ( ! isset( $result->users[0]->phoneNumber ) ) {
				return new WP_Error(
					'not_exist_firebase_user',
					__( 'The user not exist.', "grind-mobile-app" ),
					array(
						'status' => 403,
					)
				);
			}

			$phone_number = $result->users[0]->phoneNumber;

			$users = get_users( array(
				"meta_key"     => "digits_phone",
				"meta_value"   => $phone_number,
				"meta_compare" => "="
			) );

			if ( count( $users ) == 0 ) {
				$error = new WP_Error();
				$error->add( 403, __( "Did not find any members matching the phone number!", "grind-mobile-app" ), array( 'status' => 400 ) );

				return $error;
			}

			$user = $users[0];

			// Generate token
			$token = self::generate_token( $user );

			// Return data
			$data = array(
				'token' => $token,
				'user'  => Customer::mbd_get_userdata( $user ),
			);

			return $data;

		} catch ( Exception $err ) {
			return $err;
		}
	}

	/**
	 * Helper Function to get content from URL
	 * Used in Google Login
	 */
	public function getUrlContent( $url ) {
		$parts = parse_url( $url );
		$host  = $parts['host'];

		$args = array(
			'timeout'     => 45,
			'redirection' => 5,
			'httpversion' => '1.1',
			'blocking'    => true,
			'headers'     => array(
				'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language' => 'en-US,en;q=0.8',
				'Cache-Control'   => 'max-age=0',
				'Connection'      => 'keep-alive',
				'Host'            => $host,
				'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.116 Safari/537.36',
			),
			'cookies'     => array(),
			'sslverify'   => false,
			'stream'      => false,
			'filename'    => null,
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Change the way encode token
	 *
	 * @since 1.3.4
	 */
	public function custom_digits_rest_token_data( $token, $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( $user ) {
			$token = self::generate_token( $user );
			$data  = array(
				'token' => $token,
				'user'  => Customer::mbd_get_userdata( $user ),
			);
			wp_send_json_success( $data );
		} else {
			wp_send_json_error( new WP_Error(
				404,
				__( 'Something wrong!.', "grind-mobile-app" ),
				array(
					'status' => 403,
				)
			) );
		}
	}

	/**
	 *  General token
	 *
	 * @param $user
	 *
	 * @return string
	 */
	public static function generate_token( $user, $data = array() ) {
		$iat = time();
		$nbf = $iat;
		$exp = $iat + ( DAY_IN_SECONDS * 30 * 12);

		$token = array(
			'iss'  => get_bloginfo( 'url' ),
			'iat'  => $iat,
			'nbf'  => $nbf,
			'exp'  => $exp,
			'data' => array_merge( array(
				'user_id' => $user->data->ID
			), $data ),
		);

		$key = defined( 'MOBILE_BUILDER_JWT_SECRET_KEY' ) ? MOBILE_BUILDER_JWT_SECRET_KEY : "example_key";

		// Generate token
		return JWT::encode( $token, $key, 'HS256' );
	}

}
