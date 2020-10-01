<?php

defined( 'ABSPATH' ) or die();

/**
 * Gravity Forms HelpScout API Library.
 *
 * @since     1.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2017, Rocketgenius
 */
class GF_HelpScout_API {

	/**
	 * HelpScout access token.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $access_token HelpScout access token.
	 */
	protected $access_token;
	protected $custom_app_keys;

	/**
	 * HelpScout API URL.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $api_url HelpScout API URL.
	 */
	protected $api_url = 'https://api.helpscout.net/v2';

	/**
	 * Gravity API URL.
	 *
	 * Used to generate and refresh access tokens.
	 *
	 * @var string
	 */
	protected $gravity_api_url = 'https://gravityapi.com/wp-json/gravityapi/v1';

	protected $auth_url = 'https://secure.helpscout.net/authentication/authorizeClientApplication';

	protected $is_custom_app = false;

	public function __construct() {

		if ( defined( 'GRAVITY_API_URL' ) ) {
			$this->gravity_api_url = GRAVITY_API_URL;
		}

		if ( ! empty( $this->get_custom_app_keys() ) ) {
			gf_helpscout()->log_debug( __METHOD__ . '(): Using custom app.' );
			$this->is_custom_app = true;
		}

	}

	/**
	 * Determines if the access token is expired.
	 *
	 * @since 1.13
	 *
	 * @param array $access_token The access token properties.
	 *
	 * @return bool
	 */
	public function is_access_token_expired( $access_token = array() ) {
		if ( empty( $access_token ) ) {
			$access_token = $this->get_saved_access_token();
		}

		return (
			is_array( $access_token )
			&& ! empty( $access_token['refresh_token'] )
			&& ! empty( $access_token['expires_at'] )
			&& time() >= $access_token['expires_at']
		);
	}

	/**
	 * Retrieves the access token from the database.
	 *
	 * @since 1.13
	 *
	 * @return mixed
	 */
	public function get_saved_access_token() {
		return get_option( 'gf_helpscout_api_access_token' );
	}

	public function get_access_token( $prop = false ) {

		if ( ! $this->access_token ) {
			$this->access_token = $this->get_saved_access_token();
		}

		// Make sure access token is not expired. If it is, try to refresh it.
		if ( ! empty( $this->access_token ) && $this->is_access_token_expired( $this->access_token ) ) {
			$access_token = $this->refresh( $this->access_token['refresh_token'] );
			if ( $access_token ) {
				$this->access_token = $this->save_access_token( $access_token );
			} else {
				$this->access_token = false;
			}
		}

		if ( $prop && ! empty( $this->access_token ) ) {
			return rgar( $this->access_token, $prop, false );
		}

		return $this->access_token;
	}

	public function save_access_token( $access_token ) {

		$current_refresh_token = $this->get_access_token( 'refresh_token' );

		// Do not re-save the same access token; we will lose our custom expires_at property.
		if ( $current_refresh_token === rgar( $access_token, 'refresh_token' ) ) {
			return false;
		}

		// Set a new property with a timestamp so we now exactly when the refresh token is expired.
		$access_token['expires_at'] = time() + ( $access_token['expires_in'] - MINUTE_IN_SECONDS );

		if ( update_option( 'gf_helpscout_api_access_token', $access_token ) ) {
			return $access_token;
		}

		gf_helpscout()->log_error( __METHOD__ . '(): The access token could not be saved.' );

		return false;
	}

	public function delete_access_token() {
		return delete_option( 'gf_helpscout_api_access_token' );
	}

	/**
	 * Refreshes the access token.
	 *
	 * @since 1.6
	 * @since 1.12 Updated to support custom apps.
	 *
	 * @param string $refresh_token The refresh token
	 *
	 * @return mixed
	 */
	public function refresh( $refresh_token ) {

		$body = array( 'refresh_token' => $refresh_token );

		if ( $this->is_custom_app ) {
			$endpoint              = $this->api_url . '/oauth2/token';
			$body['client_id']     = $this->get_custom_app_keys( 'app_key' );
			$body['client_secret'] = $this->get_custom_app_keys( 'app_secret' );
			$body['grant_type']    = 'refresh_token';
		} else {
			$endpoint = $this->get_gravity_api_url( '/auth/helpscout/refresh' );
			$endpoint = add_query_arg( 'state', wp_create_nonce( gf_helpscout()->get_authentication_state_action() ), $endpoint );
		}

		$response = wp_remote_post( $endpoint, array( 'body' => $body ) );

		if ( ! $response || wp_remote_retrieve_response_code( $response ) != 200 ) {
			gf_helpscout()->log_error( __METHOD__ . '(): Response: ' . json_encode( $response ) );

			return false;
		}

		// Get the response body.
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		// If state does not match, return false.
		if ( ! $this->is_custom_app && ! wp_verify_nonce( $response_body['state'], gf_helpscout()->get_authentication_state_action() ) ) {
			gf_helpscout()->log_error( __METHOD__ .'(): State did not match.' );
			return false;
		}

		return $response_body;
	}

	public function transition( $v1_api_key ) {

		$response = wp_remote_post( $this->get_gravity_api_url( '/auth/helpscout/transition' ), array(
			'body' => array(
				'api_key' => $v1_api_key,
			)
		) );

		if ( ! $response || wp_remote_retrieve_response_code( $response ) != 200 ) {
			gf_helpscout()->log_error( __METHOD__ . '(): Response: ' . json_encode( $response ) );

			return false;
		}

		$raw_access_token = json_decode( wp_remote_retrieve_body( $response ), true );

		// Standardize the properties; this endpoint returns them camel case instead of underscored (i.e. expiresIn vs expires_in).
		$access_token = array(
			'access_token'  => $raw_access_token['accessToken'],
			'expires_in'    => $raw_access_token['expiresIn'],
			'refresh_token' => $raw_access_token['refreshToken'],
		);

		return $access_token;

	}

	public function get_custom_app_keys( $key = '' ) {

		if ( empty( $this->custom_app_keys ) ) {
			$app_keys              = get_option( 'gf_helpscout_api_custom_app_keys' );
			$this->custom_app_keys = $app_keys;
		}

		if ( $key && ! empty( $this->custom_app_keys ) ) {
			return $this->custom_app_keys[ $key ];
		}

		return $this->custom_app_keys;
	}

	public function save_app_keys( $app_key, $app_secret ) {

		$result = update_option( 'gf_helpscout_api_custom_app_keys', array(
			'app_key'    => $app_key,
			'app_secret' => $app_secret,
		) );

		return $result;
	}

	public function delete_app_keys() {
		return delete_option( 'gf_helpscout_api_custom_app_keys' );
	}

	public function validate_app_keys( $app_key, $app_secret ) {

		$response = $this->make_request( '/oauth2/token', array(
			'grant_type'    => 'client_credentials',
			'client_id'     => $app_key,
			'client_secret' => $app_secret,
		), 'POST' );

		return $response;
	}

	public function get_gravity_api_url( $path = '' ) {
		return $this->gravity_api_url . $path;
	}

	public function get_auth_url() {
		$args = array(
			'client_id' => $this->get_custom_app_keys( 'app_key' ),
			'state'     => wp_create_nonce( gf_helpscout()->get_authentication_state_action() ),
		);

		return add_query_arg( $args, $this->auth_url );
	}

	public function do_custom_app_auth( $code ) {

		$response = $this->make_request( '/oauth2/token', array(
			'code'          => $code,
			'client_id'     => $this->get_custom_app_keys( 'app_key' ),
			'client_secret' => $this->get_custom_app_keys( 'app_secret' ),
			'grant_type'    => 'authorization_code',
		), 'POST' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->save_access_token( $response );

	}

	public function get_me() {
		return $this->make_request( '/users/me' );
	}

	/**
	 * Returns the mailboxes for the connected Help Scout account.
	 *
	 * @since unknown
	 *
	 * @param null|int $page_number          The page number or null to retrieve all pages.
	 * @param bool     $return_page_response Indicates if the full response for the page specific request should be immediately returned.
	 *
	 * @return array|WP_Error
	 */
	public function get_mailboxes( $page_number = null, $return_page_response = false ) {

		$options = array();

		if ( ! is_null( $page_number ) ) {
			$options['page'] = $page_number;
		}

		$response = $this->make_request( '/mailboxes', $options );
		if ( $return_page_response || is_wp_error( $response ) ) {
			return $response;
		}

		$page        = 1;
		$total_pages = absint( rgars( $response, 'page/totalPages' ) );
		$mailboxes   = rgars( $response, '_embedded/mailboxes', array() );

		if ( empty( $mailboxes ) || ! is_null( $page_number ) || $total_pages <= 1 ) {
			return $mailboxes;
		}

		while ( $page < $total_pages ) {
			$response = $this->get_mailboxes( ++$page, true );

			if ( is_wp_error( $response ) ) {
				gf_helpscout()->log_error( sprintf( '%s(): unable to get page %d; %s', __METHOD__, $page, $response->get_error_message() ) );

				return $mailboxes;
			}

			$page_mailboxes = rgars( $response, '_embedded/mailboxes' );
			if ( empty( $page_mailboxes ) || ! is_array( $page_mailboxes ) ) {
				gf_helpscout()->log_error( sprintf( '%s(): page %d is invalid.', __METHOD__, $page ) );

				return $mailboxes;
			}

			$mailboxes = array_merge( $mailboxes, $page_mailboxes );
		}

		gf_helpscout()->log_debug( sprintf( '%s(): retrieved %d pages.', __METHOD__, $total_pages ) );

		return $mailboxes;
	}

	public function get_mailbox( $mailbox_id ) {
		return $this->make_request( "/mailboxes/{$mailbox_id}" );
	}

	public function get_user( $user_id ) {
		return $this->make_request( "/users/{$user_id}" );
	}

	public function get_users_for_mailbox( $mailbox_id ) {
		return $this->make_request( '/users', array( 'mailbox' => $mailbox_id ), 'GET', '_embedded/users' );
	}

	public function create_conversation( $conversation ) {
		return $this->make_request( '/conversations', $conversation, 'POST' );
	}

	public function get_conversation( $conversation_id ) {
		return $this->make_request( "/conversations/{$conversation_id}" );
	}

	public function get_customer_by_email( $email ) {
		$response = $this->make_request( '/customers', array( 'query' => sprintf( '(email:"%s")', $email ) ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return rgars( $response, '_embedded/customers/0', false );
	}

	public function create_customer( $email, $first_name, $last_name = '', $phone = '' ) {

		$options = array(
			'firstName' => $first_name,
			'lastName'  => $last_name,
			'emails'    => array(
				array(
					'type'  => 'other',
					'value' => $email,
				),
			),
		);

		if ( $phone ) {
			$options['phones'] = array(
				array(
					'type'  => 'other',
					'value' => $phone
				),
			);
		}

		return $this->make_request( '/customers', $options, 'POST' );
	}

	/**
	 * Updates an existing customer.
	 *
	 * @since 1.6
	 *
	 * @param int   $customer_id The ID of the customer to be updated.
	 * @param array $customer    The customer fields.
	 *
	 * @return array|bool|string|WP_Error
	 * @throws Exception
	 */
	public function update_customer( $customer_id, $customer ) {

		// Ensure only supported fields are included in the request.
		// https://developer.helpscout.com/mailbox-api/endpoints/customers/update/#request-fields.
		$options = array_intersect_key( $customer, array_flip( array(
			'firstName',
			'lastName',
			'phone',
			'photoUrl',
			'jobTitle',
			'photoType',
			'background',
			'location',
			'organization',
			'gender',
			'age',
		) ) );

		return $this->make_request( "/customers/{$customer_id}", $options, 'PUT' );
	}

	/**
	 * Add a phone number to a customer.
	 *
	 * @param int    $customer_id Help Scout Customer ID.
	 * @param string $phone       Phone number.
	 * @param string $type        Location for this phone. Possible values include: fax, home, mobile, other, pager, work.
	 *
	 * @return int|WP_Error
	 */
	public function add_customer_phone( $customer_id, $phone, $type = 'other' ) {

		$options = array(
			'type'  => $type,
			'value' => $phone,
		);

		return $this->make_request( "/customers/{$customer_id}/phones", $options, 'POST' );
	}

	public function add_note( $conversation_id, $note ) {

		if ( is_string( $note ) ) {
			$note = array(
				'text' => $note,
			);
		}

		return $this->make_request( "/conversations/{$conversation_id}/notes", $note, 'POST' );
	}





	// # REQUEST METHODS -----------------------------------------------------------------------------------------------

	/**
	 * Make API request.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @param string $action     Request action.
	 * @param array  $options    Request options.
	 * @param string $auth_type  Authentication token to use. Defaults to server.
	 * @param string $method     HTTP method. Defaults to GET.
	 * @param string $return_key Array key from response to return. Defaults to null (return full response).
	 *
	 * @return array|string|bool|WP_Error
	 */
	private function make_request( $action, $options = array(), $method = 'GET', $return_key = null ) {

		// Build request options string.
		$request_options = 'GET' === $method ? '?' . http_build_query( $options ) : null;

		// Build request URL.
		$request_url = $this->api_url . $action . $request_options;

		// Build request arguments.
		$request_args = array(
			'body'      => 'GET' !== $method ? json_encode( $options ) : '',
			'method'    => $method,
			'sslverify' => false,
			'headers'   => array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
			),
		);

		$request_args['headers']['Authorization'] = 'Bearer ' . $this->get_access_token( 'access_token' );

		// Execute API request.
		$response = wp_remote_request( $request_url, $request_args );
		if ( is_wp_error( $response ) ) {

			return $response;
		}

		switch ( wp_remote_retrieve_response_code( $response ) ) {
			case 200:
				// Convert JSON response to array.
				$response = json_decode( $response['body'], true );
				break;
			// Created resource.
			case 201:
				$resource_id = wp_remote_retrieve_header( $response, 'resource-id' );

				return $resource_id ? $resource_id : true;
			// Updated resource.
			case 204:
				return true;
			default:
				return new WP_Error( wp_remote_retrieve_response_code( $response ), wp_remote_retrieve_response_message( $response ), wp_remote_retrieve_body( $response ) );
		}

		// If a return key is defined and array item exists, return it.
		if ( ! empty( $return_key ) && rgars( $response, $return_key ) ) {
			return rgars( $response, $return_key );
		}

		return $response;
	}

}