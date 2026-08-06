<?php
/**
 * DocCheck OAuth 2.0 HTTP client.
 *
 * @package Simple_Login_For_DocCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Simple_Login_For_DocCheck_Client' ) ) {

	/**
	 * Talks to the DocCheck authentication service.
	 *
	 * @since 1.0.0
	 */
	class Simple_Login_For_DocCheck_Client {

		/**
		 * Default DocCheck token endpoint.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		const TOKEN_ENDPOINT = 'https://auth.doccheck.com/token';

		/**
		 * Settings repository.
		 *
		 * @since 1.0.0
		 * @var Simple_Login_For_DocCheck_Settings
		 */
		private $settings;

		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 *
		 * @param Simple_Login_For_DocCheck_Settings $settings Settings repository.
		 */
		public function __construct( Simple_Login_For_DocCheck_Settings $settings ) {
			$this->settings = $settings;
		}

		/**
		 * Exchanges an authorization code for an access token.
		 *
		 * @since 1.0.0
		 *
		 * @param string $code Authorization code returned by DocCheck.
		 * @return string|WP_Error Access token on success, WP_Error on failure.
		 */
		public function exchange_authorization_code( $code ) {
			if ( ! $this->settings->is_configured() ) {
				return new WP_Error(
					'simple_login_for_doccheck_not_configured',
					__( 'The Simple Login for DocCheck settings are incomplete.', 'simple-login-for-doccheck' )
				);
			}

			$response = wp_remote_post(
				$this->token_endpoint(),
				array(
					'timeout' => 15,
					'headers' => array(
						'Accept' => 'application/json',
					),
					'body'    => array(
						'grant_type'    => 'authorization_code',
						'client_id'     => $this->settings->login_client_id(),
						'client_secret' => $this->settings->login_client_secret(),
						'code'          => $code,
						'redirect_uri'  => $this->settings->redirect_uri(),
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$status = (int) wp_remote_retrieve_response_code( $response );
			$body   = (string) wp_remote_retrieve_body( $response );

			if ( $status < 200 || $status > 299 ) {
				return new WP_Error(
					'simple_login_for_doccheck_http_error',
					sprintf(
						/* translators: %d: HTTP status code. */
						__( 'DocCheck returned an unexpected HTTP status (%d).', 'simple-login-for-doccheck' ),
						$status
					),
					array( 'status' => $status )
				);
			}

			$data = json_decode( $body, true );

			if ( ! is_array( $data ) ) {
				return new WP_Error(
					'simple_login_for_doccheck_invalid_response',
					__( 'The response from DocCheck could not be decoded.', 'simple-login-for-doccheck' )
				);
			}

			if ( isset( $data['error'] ) ) {
				return new WP_Error(
					'simple_login_for_doccheck_oauth_error',
					is_string( $data['error'] ) ? $data['error'] : __( 'DocCheck rejected the login.', 'simple-login-for-doccheck' )
				);
			}

			if ( empty( $data['access_token'] ) || ! is_string( $data['access_token'] ) ) {
				return new WP_Error(
					'simple_login_for_doccheck_missing_token',
					__( 'DocCheck did not return an access token.', 'simple-login-for-doccheck' )
				);
			}

			return $data['access_token'];
		}

		/**
		 * Returns the token endpoint to use.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		private function token_endpoint() {
			/**
			 * Filters the DocCheck token endpoint.
			 *
			 * Useful for pointing the plugin at a DocCheck staging environment.
			 *
			 * @since 1.0.0
			 *
			 * @param string $endpoint Token endpoint URL.
			 */
			return (string) apply_filters( 'simple_login_for_doccheck_token_endpoint', self::TOKEN_ENDPOINT );
		}
	}
}
