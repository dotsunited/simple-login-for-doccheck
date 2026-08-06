<?php
/**
 * PHP session wrapper.
 *
 * @package Simple_Login_For_DocCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Simple_Login_For_DocCheck_Session' ) ) {

	/**
	 * Thin wrapper around the native PHP session.
	 *
	 * Starts sessions lazily to preserve full-page caching.
	 *
	 * @since 1.0.0
	 */
	class Simple_Login_For_DocCheck_Session {

		/**
		 * Prefix for all session keys owned by this plugin.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		const PREFIX = 'simple_login_for_doccheck_';

		/**
		 * Starts the session unless it is already running.
		 *
		 * @since 1.0.0
		 *
		 * @return bool True when a session is available.
		 */
		public function start() {
			if ( PHP_SESSION_DISABLED === session_status() ) {
				return false;
			}

			if ( PHP_SESSION_ACTIVE === session_status() ) {
				return true;
			}

			if ( headers_sent() ) {
				return false;
			}

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A failing session must not break the page.
			return (bool) @session_start(
				array(
					'read_and_close'  => false,
					'cookie_secure'   => is_ssl(),
					'cookie_httponly' => true,
					'cookie_samesite' => 'Lax',
				)
			);
		}

		/**
		 * Replaces the current session ID after authentication.
		 *
		 * @since 1.0.0
		 *
		 * @return bool True when the session ID was regenerated.
		 */
		public function regenerate_id() {
			if ( ! $this->start() ) {
				return false;
			}

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Authentication fails safely when regeneration is unavailable.
			return (bool) @session_regenerate_id( true );
		}

		/**
		 * Whether a session is currently active.
		 *
		 * @since 1.0.0
		 *
		 * @return bool
		 */
		public function is_active() {
			return PHP_SESSION_ACTIVE === session_status();
		}

		/**
		 * Reads a value from the session.
		 *
		 * Starts only when a session cookie already exists.
		 *
		 * @since 1.0.0
		 *
		 * @param string $key           Key without the plugin prefix.
		 * @param mixed  $default_value Value returned when the key is absent.
		 * @return mixed
		 */
		public function get( $key, $default_value = null ) {
			if ( ! $this->is_active() && ! $this->has_session_cookie() ) {
				return $default_value;
			}

			if ( ! $this->start() ) {
				return $default_value;
			}

			$name = self::PREFIX . $key;

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Session values are written by this plugin only, never by user input.
			return isset( $_SESSION[ $name ] ) ? $_SESSION[ $name ] : $default_value;
		}

		/**
		 * Writes a value to the session.
		 *
		 * @since 1.0.0
		 *
		 * @param string $key   Key without the plugin prefix.
		 * @param mixed  $value Value to store.
		 * @return bool True when the value was stored.
		 */
		public function set( $key, $value ) {
			if ( ! $this->start() ) {
				return false;
			}

			$_SESSION[ self::PREFIX . $key ] = $value;

			return true;
		}

		/**
		 * Removes a value from the session and returns it.
		 *
		 * @since 1.0.0
		 *
		 * @param string $key           Key without the plugin prefix.
		 * @param mixed  $default_value Value returned when the key is absent.
		 * @return mixed
		 */
		public function pull( $key, $default_value = null ) {
			$value = $this->get( $key, $default_value );

			$this->delete( $key );

			return $value;
		}

		/**
		 * Removes a value from the session.
		 *
		 * @since 1.0.0
		 *
		 * @param string $key Key without the plugin prefix.
		 */
		public function delete( $key ) {
			if ( ! $this->is_active() ) {
				return;
			}

			unset( $_SESSION[ self::PREFIX . $key ] );
		}

		/**
		 * Whether the request carries a session cookie.
		 *
		 * @since 1.0.0
		 *
		 * @return bool
		 */
		private function has_session_cookie() {
			$name = session_name();

			return is_string( $name ) && isset( $_COOKIE[ $name ] );
		}
	}
}
