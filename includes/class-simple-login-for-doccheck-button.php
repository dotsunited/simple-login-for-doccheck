<?php
/**
 * Login button rendering.
 *
 * @package Simple_Login_For_DocCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Simple_Login_For_DocCheck_Button' ) ) {

	/**
	 * Registers the DocCheck Login block, shortcode alternative and button assets.
	 *
	 * @since 1.0.0
	 */
	class Simple_Login_For_DocCheck_Button {

		/**
		 * Block type registered by this plugin.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		const BLOCK_NAME = 'dotsunited/simple-login-for-doccheck';

		/**
		 * Handle of the block editor script.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		const EDITOR_SCRIPT_HANDLE = 'simple-login-for-doccheck-block-editor';

		/**
		 * Handle of the block editor stylesheet.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		const EDITOR_STYLE_HANDLE = 'simple-login-for-doccheck-block-editor';

		/**
		 * Handle of the DocCheck web component script.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		const SCRIPT_HANDLE = 'simple-login-for-doccheck-button';

		/**
		 * Shortcode tag registered by this plugin.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		const SHORTCODE = 'simple_login_for_doccheck';

		/**
		 * Handle of the plugin stylesheet.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		const STYLE_HANDLE = 'simple-login-for-doccheck';

		/**
		 * URL of the DocCheck login button web component.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		const SCRIPT_URL = 'https://dccdn.de/static.doccheck.com/components/login-button/@latest/main.js';

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
		 * Registers the hooks owned by this class.
		 *
		 * @since 1.0.0
		 */
		public function init() {
			add_action( 'init', array( $this, 'register_assets' ) );
			add_action( 'init', array( $this, 'register_block' ) );
			add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
		}

		/**
		 * Registers front-end assets for on-demand enqueueing.
		 *
		 * @since 1.0.0
		 */
		public function register_assets() {
			wp_register_script( self::SCRIPT_HANDLE, self::SCRIPT_URL, array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Version is pinned by DocCheck via the @latest path segment.
			wp_register_script(
				self::EDITOR_SCRIPT_HANDLE,
				SIMPLE_LOGIN_FOR_DOCCHECK_URL . 'blocks/login-button/index.js',
				array( 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n' ),
				SIMPLE_LOGIN_FOR_DOCCHECK_VERSION,
				true
			);

			wp_register_style(
				self::STYLE_HANDLE,
				SIMPLE_LOGIN_FOR_DOCCHECK_URL . 'public/css/simple-login-for-doccheck.css',
				array(),
				SIMPLE_LOGIN_FOR_DOCCHECK_VERSION
			);

			wp_register_style(
				self::EDITOR_STYLE_HANDLE,
				SIMPLE_LOGIN_FOR_DOCCHECK_URL . 'blocks/login-button/editor.css',
				array( 'wp-edit-blocks' ),
				SIMPLE_LOGIN_FOR_DOCCHECK_VERSION
			);

			wp_set_script_translations(
				self::EDITOR_SCRIPT_HANDLE,
				'simple-login-for-doccheck',
				SIMPLE_LOGIN_FOR_DOCCHECK_DIR . 'languages'
			);
		}

		/**
		 * Registers the dynamic DocCheck Login block.
		 *
		 * @since 1.0.0
		 */
		public function register_block() {
			register_block_type(
				SIMPLE_LOGIN_FOR_DOCCHECK_DIR . 'blocks/login-button',
				array(
					'render_callback' => array( $this, 'render_block' ),
				)
			);
		}

		/**
		 * Renders the [simple_login_for_doccheck] shortcode.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string>|string $atts Shortcode attributes.
		 * @return string
		 */
		public function render_shortcode( $atts ) {
			$atts = shortcode_atts(
				array(
					'size'     => $this->settings->button_size(),
					'language' => $this->settings->button_language(),
				),
				$atts,
				self::SHORTCODE
			);

			$output = $this->render( $atts, 'class="simple-login-for-doccheck"' );

			if ( ! $this->settings->is_configured() ) {
				return $output;
			}

			/**
			 * Filters the complete markup rendered by the [simple_login_for_doccheck] shortcode.
			 *
			 * @since 1.0.0
			 *
			 * @param string                $output Rendered markup.
			 * @param array<string, string> $atts   Resolved shortcode attributes.
			 */
			return (string) apply_filters( 'simple_login_for_doccheck_shortcode_html', $output, $atts );
		}

		/**
		 * Renders the dynamic DocCheck Login block.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $attributes Block attributes.
		 * @return string
		 */
		public function render_block( $attributes ) {
			$attributes = wp_parse_args(
				$attributes,
				array(
					'size'     => '',
					'language' => '',
				)
			);

			$output = $this->render(
				$attributes,
				get_block_wrapper_attributes( array( 'class' => 'simple-login-for-doccheck' ) )
			);

			/**
			 * Filters the complete markup rendered by the DocCheck Login block.
			 *
			 * @since 1.0.0
			 *
			 * @param string                $output     Rendered markup.
			 * @param array<string, string> $attributes Resolved block attributes.
			 */
			return (string) apply_filters( 'simple_login_for_doccheck_block_html', $output, $attributes );
		}

		/**
		 * Renders the shared login button markup.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $attributes         Button attributes.
		 * @param string                $wrapper_attributes Escaped wrapper attributes.
		 * @return string
		 */
		private function render( $attributes, $wrapper_attributes ) {
			$button = $this->get_button_html( $attributes['size'], $attributes['language'] );

			if ( '' === $button ) {
				if ( current_user_can( 'manage_options' ) ) {
					return sprintf(
						'<p class="simple-login-for-doccheck__notice">%s</p>',
						esc_html__( 'Simple Login for DocCheck is not configured yet. Add your client ID, client secret and redirect URL in Settings → Simple Login for DocCheck.', 'simple-login-for-doccheck' )
					);
				}

				return '';
			}

			wp_enqueue_script( self::SCRIPT_HANDLE );
			wp_enqueue_style( self::STYLE_HANDLE );

			return '<div ' . $wrapper_attributes . '>' . $button . $this->get_error_html() . '</div>';
		}

		/**
		 * Returns the DocCheck login button element.
		 *
		 * @since 1.0.0
		 *
		 * @param string $size     Button size.
		 * @param string $language Button language.
		 * @return string Empty string when the plugin is not configured.
		 */
		public function get_button_html( $size = '', $language = '' ) {
			if ( ! $this->settings->is_configured() ) {
				return '';
			}

			$sizes = Simple_Login_For_DocCheck_Settings::button_sizes();

			$size     = in_array( $size, $sizes, true ) ? $size : $this->settings->button_size();
			$language = $this->settings->resolve_button_language( $language );

			return sprintf(
				'<dc-login-button size="%1$s" language="%2$s" loginClientId="%3$s" redirectUri="%4$s"></dc-login-button>',
				esc_attr( $size ),
				esc_attr( $language ),
				esc_attr( $this->settings->login_client_id() ),
				esc_url( $this->settings->redirect_uri() )
			);
		}

		/**
		 * Returns the error message shown after a failed login attempt.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		private function get_error_html() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag set by our own redirect.
			if ( empty( $_GET[ Simple_Login_For_DocCheck_Auth::ERROR_ARG ] ) ) {
				return '';
			}

			$message = __( 'The login failed. Please try again.', 'simple-login-for-doccheck' );

			/**
			 * Filters the error message shown after a failed DocCheck login.
			 *
			 * @since 1.0.0
			 *
			 * @param string $message Error message.
			 */
			$message = (string) apply_filters( 'simple_login_for_doccheck_error_message', $message );

			return sprintf(
				'<p class="simple-login-for-doccheck__error" role="alert">%s</p>',
				esc_html( $message )
			);
		}
	}
}
