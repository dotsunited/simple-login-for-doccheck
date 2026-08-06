<?php
/**
 * Authentication handler.
 *
 * @package Simple_Login_For_DocCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Simple_Login_For_DocCheck_Auth' ) ) {

	/**
	 * Handles the DocCheck OAuth callback and the resulting login state.
	 *
	 * @since 1.0.0
	 */
	class Simple_Login_For_DocCheck_Auth {

		/**
		 * Session key holding the login expiry timestamp.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		const KEY_EXPIRES_AT = 'expires_at';

		/**
		 * Session key holding the URL to return to after a successful login.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		const KEY_RETURN_URL = 'return_url';

		/**
		 * Query argument added to the login page URL after a failed login.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		const ERROR_ARG = 'doccheck_error';

		/**
		 * Settings repository.
		 *
		 * @since 1.0.0
		 * @var Simple_Login_For_DocCheck_Settings
		 */
		private $settings;

		/**
		 * Session wrapper.
		 *
		 * @since 1.0.0
		 * @var Simple_Login_For_DocCheck_Session
		 */
		private $session;

		/**
		 * DocCheck API client.
		 *
		 * @since 1.0.0
		 * @var Simple_Login_For_DocCheck_Client
		 */
		private $client;

		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 *
		 * @param Simple_Login_For_DocCheck_Settings $settings Settings repository.
		 * @param Simple_Login_For_DocCheck_Session  $session  Session wrapper.
		 * @param Simple_Login_For_DocCheck_Client   $client   DocCheck API client.
		 */
		public function __construct(
			Simple_Login_For_DocCheck_Settings $settings,
			Simple_Login_For_DocCheck_Session $session,
			Simple_Login_For_DocCheck_Client $client
		) {
			$this->settings = $settings;
			$this->session  = $session;
			$this->client   = $client;
		}

		/**
		 * Registers the hooks owned by this class.
		 *
		 * @since 1.0.0
		 */
		public function init() {
			// Runs before Simple_Login_For_DocCheck_Protection so a fresh callback is not redirected away.
			add_action( 'template_redirect', array( $this, 'maybe_handle_callback' ), 5 );
		}

		/**
		 * Handles the OAuth callback when the current request is one.
		 *
		 * @since 1.0.0
		 */
		public function maybe_handle_callback() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback from DocCheck; the code is verified by the server-to-server exchange below.
			$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

			if ( '' === $code ) {
				return;
			}

			if ( ! $this->is_callback_request() ) {
				return;
			}

			$token = $this->client->exchange_authorization_code( $code );

			if ( is_wp_error( $token ) ) {
				/**
				 * Fires when a DocCheck login attempt failed.
				 *
				 * @since 1.0.0
				 *
				 * @param WP_Error $error The failure reason.
				 */
				do_action( 'simple_login_for_doccheck_failed', $token );

				$this->fail( $token->get_error_code() );
			}

			if ( ! $this->authenticate() ) {
				$error = new WP_Error(
					'simple_login_for_doccheck_session_error',
					__( 'The DocCheck login session could not be secured.', 'simple-login-for-doccheck' )
				);

				do_action( 'simple_login_for_doccheck_failed', $error );

				$this->fail( $error->get_error_code() );
			}

			wp_safe_redirect( $this->consume_return_url() );
			exit;
		}

		/**
		 * Whether the current request is the configured OAuth redirect URI.
		 *
		 * @since 1.0.0
		 *
		 * @return bool
		 */
		public function is_callback_request() {
			$redirect_uri = $this->settings->redirect_uri();

			if ( '' === $redirect_uri ) {
				return false;
			}

			$request_uri = isset( $_SERVER['REQUEST_URI'] )
				? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
				: '';

			if ( '' === $request_uri ) {
				return false;
			}

			$redirect_origin = $this->url_origin( $redirect_uri );

			if ( '' === $redirect_origin || $this->url_origin( home_url( '/' ) ) !== $redirect_origin ) {
				return false;
			}

			$current_path   = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
			$redirect_path  = (string) wp_parse_url( $redirect_uri, PHP_URL_PATH );
			$current_query  = $this->parse_query( (string) wp_parse_url( $request_uri, PHP_URL_QUERY ) );
			$redirect_query = $this->parse_query( (string) wp_parse_url( $redirect_uri, PHP_URL_QUERY ) );

			if (
				'' === $redirect_path
				|| untrailingslashit( $current_path ) !== untrailingslashit( $redirect_path )
			) {
				return false;
			}

			foreach ( $redirect_query as $key => $value ) {
				if ( ! array_key_exists( $key, $current_query ) || $current_query[ $key ] !== $value ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Whether the current visitor may view protected content.
		 *
		 * @since 1.0.0
		 *
		 * @return bool
		 */
		public function is_authenticated() {
			$expires_at = (int) $this->session->get( $this->session_key( self::KEY_EXPIRES_AT ), 0 );
			$valid      = $expires_at > time();

			/**
			 * Filters whether the current visitor counts as logged in via DocCheck.
			 *
			 * @since 1.0.0
			 *
			 * @param bool $valid      Whether a valid DocCheck login exists.
			 * @param int  $expires_at Expiry timestamp stored in the session.
			 */
			return (bool) apply_filters( 'simple_login_for_doccheck_is_authenticated', $valid, $expires_at );
		}

		/**
		 * Marks the current visitor as logged in via DocCheck.
		 *
		 * @since 1.0.0
		 *
		 * @return bool True when the authenticated session was established.
		 */
		public function authenticate() {
			if ( ! $this->session->regenerate_id() ) {
				return false;
			}

			$lifetime = $this->settings->session_lifetime() * MINUTE_IN_SECONDS;

			if ( ! $this->session->set( $this->session_key( self::KEY_EXPIRES_AT ), time() + $lifetime ) ) {
				return false;
			}

			/**
			 * Fires after a visitor successfully logged in via DocCheck.
			 *
			 * @since 1.0.0
			 */
			do_action( 'simple_login_for_doccheck_authenticated' );

			return true;
		}

		/**
		 * Clears the DocCheck login state.
		 *
		 * @since 1.0.0
		 */
		public function logout() {
			$this->session->delete( $this->session_key( self::KEY_EXPIRES_AT ) );
			$this->session->delete( $this->session_key( self::KEY_RETURN_URL ) );
		}

		/**
		 * Stores the URL the visitor wanted to see before being asked to log in.
		 *
		 * @since 1.0.0
		 *
		 * @param string $url Absolute URL on this site.
		 */
		public function remember_return_url( $url ) {
			$this->session->set( $this->session_key( self::KEY_RETURN_URL ), esc_url_raw( $url ) );
		}

		/**
		 * Returns and clears the stored return URL.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public function consume_return_url() {
			$url = (string) $this->session->pull( $this->session_key( self::KEY_RETURN_URL ), '' );

			if ( '' === $url ) {
				$url = home_url( '/' );
			}

			/**
			 * Filters the URL a visitor is sent to after a successful login.
			 *
			 * @since 1.0.0
			 *
			 * @param string $url Return URL.
			 */
			return (string) apply_filters( 'simple_login_for_doccheck_return_url', $url );
		}

		/**
		 * Returns a normalized URL origin for comparison.
		 *
		 * @since 1.0.0
		 *
		 * @param string $url Absolute URL.
		 * @return string
		 */
		private function url_origin( $url ) {
			$parts = wp_parse_url( $url );

			if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
				return '';
			}

			$scheme = strtolower( (string) $parts['scheme'] );
			$host   = strtolower( (string) $parts['host'] );
			$port   = isset( $parts['port'] ) ? absint( $parts['port'] ) : 0;

			if ( 0 === $port ) {
				$port = 'https' === $scheme ? 443 : 80;
			}

			return $scheme . '://' . $host . ':' . $port;
		}

		/**
		 * Parses a URL query into a comparable array.
		 *
		 * @since 1.0.0
		 *
		 * @param string $query URL query string.
		 * @return array<string, mixed>
		 */
		private function parse_query( $query ) {
			$values = array();

			if ( '' !== $query ) {
				wp_parse_str( $query, $values );
			}

			return $values;
		}

		/**
		 * Scopes a session key to this WordPress site and DocCheck client.
		 *
		 * @since 1.0.0
		 *
		 * @param string $key Unscoped session key.
		 * @return string
		 */
		private function session_key( $key ) {
			$scope = implode(
				'|',
				array(
					(string) get_current_blog_id(),
					$this->settings->login_client_id(),
				)
			);

			return $key . '_' . hash_hmac( 'sha256', $scope, wp_salt( 'auth' ) );
		}

		/**
		 * Redirects to the login page with an error flag and stops execution.
		 *
		 * @since 1.0.0
		 *
		 * @param string $reason Machine readable failure reason.
		 */
		private function fail( $reason ) {
			$url = add_query_arg(
				self::ERROR_ARG,
				rawurlencode( $reason ),
				$this->settings->login_page_url()
			);

			wp_safe_redirect( $url );
			exit;
		}
	}
}
