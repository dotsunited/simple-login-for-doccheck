<?php
/**
 * Plugin bootstrap.
 *
 * @package Simple_Login_For_DocCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Simple_Login_For_DocCheck' ) ) {

	/**
	 * Wires the plugin components into WordPress.
	 *
	 * @since 1.0.0
	 */
	class Simple_Login_For_DocCheck {

		/**
		 * Settings repository.
		 *
		 * @since 1.0.0
		 * @var Simple_Login_For_DocCheck_Settings
		 */
		private $settings;

		/**
		 * Authentication handler.
		 *
		 * @since 1.0.0
		 * @var Simple_Login_For_DocCheck_Auth
		 */
		private $auth;

		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			$this->settings = new Simple_Login_For_DocCheck_Settings();
			$this->auth     = new Simple_Login_For_DocCheck_Auth(
				$this->settings,
				new Simple_Login_For_DocCheck_Session(),
				new Simple_Login_For_DocCheck_Client( $this->settings )
			);
		}

		/**
		 * Registers all hooks.
		 *
		 * @since 1.0.0
		 */
		public function init() {
			add_action( 'init', array( $this, 'load_textdomain' ) );

			$this->auth->init();

			( new Simple_Login_For_DocCheck_Protection( $this->settings, $this->auth ) )->init();
			( new Simple_Login_For_DocCheck_Button( $this->settings ) )->init();

			if ( is_admin() ) {
				( new Simple_Login_For_DocCheck_Admin( $this->settings ) )->init();
			}
		}

		/**
		 * Loads the plugin translations.
		 *
		 * @since 1.0.0
		 */
		public function load_textdomain() {
			load_plugin_textdomain(
				'simple-login-for-doccheck',
				false,
				dirname( plugin_basename( SIMPLE_LOGIN_FOR_DOCCHECK_FILE ) ) . '/languages'
			);
		}

		/**
		 * Returns the settings repository.
		 *
		 * @since 1.0.0
		 *
		 * @return Simple_Login_For_DocCheck_Settings
		 */
		public function settings() {
			return $this->settings;
		}

		/**
		 * Returns the authentication handler.
		 *
		 * @since 1.0.0
		 *
		 * @return Simple_Login_For_DocCheck_Auth
		 */
		public function auth() {
			return $this->auth;
		}
	}
}
