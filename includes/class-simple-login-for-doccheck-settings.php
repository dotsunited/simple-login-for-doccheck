<?php
/**
 * Settings repository.
 *
 * @package Simple_Login_For_DocCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Simple_Login_For_DocCheck_Settings' ) ) {

	/**
	 * Reads and sanitises the plugin options.
	 *
	 * Stores all settings in one option.
	 *
	 * @since 1.0.0
	 */
	class Simple_Login_For_DocCheck_Settings {

		/**
		 * Name of the option holding all settings.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		const OPTION_NAME = 'simple_login_for_doccheck_options';

		/**
		 * Settings group used by the Settings API.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		const OPTION_GROUP = 'simple_login_for_doccheck';

		/**
		 * Button language value meaning "follow the site language".
		 *
		 * @since 1.0.0
		 * @var string
		 */
		const LANGUAGE_AUTO = 'auto';

		/**
		 * Button language used when nothing else can be resolved.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		const LANGUAGE_FALLBACK = 'en';

		/**
		 * Cached options.
		 *
		 * @since 1.0.0
		 * @var array<string, mixed>|null
		 */
		private $options = null;

		/**
		 * Returns the option defaults.
		 *
		 * @since 1.0.0
		 *
		 * @return array<string, mixed>
		 */
		public function defaults() {
			return array(
				'login_client_id'     => '',
				'login_client_secret' => '',
				'redirect_uri'        => '',
				'login_page_id'       => 0,
				'protected_pages'     => array(),
				'button_size'         => 'medium',
				'button_language'     => self::LANGUAGE_AUTO,
				'session_lifetime'    => 60,
			);
		}

		/**
		 * Returns all options merged with the defaults.
		 *
		 * @since 1.0.0
		 *
		 * @return array<string, mixed>
		 */
		public function all() {
			if ( null === $this->options ) {
				$stored = get_option( self::OPTION_NAME, array() );

				if ( ! is_array( $stored ) ) {
					$stored = array();
				}

				$this->options = wp_parse_args( $stored, $this->defaults() );
			}

			return $this->options;
		}

		/**
		 * Returns a single option value.
		 *
		 * @since 1.0.0
		 *
		 * @param string $key     Option key.
		 * @param mixed  $default_value Value returned when the key is unknown.
		 * @return mixed
		 */
		public function get( $key, $default_value = null ) {
			$options = $this->all();

			return array_key_exists( $key, $options ) ? $options[ $key ] : $default_value;
		}

		/**
		 * Clears the internal cache so the next read hits the database.
		 *
		 * @since 1.0.0
		 */
		public function flush() {
			$this->options = null;
		}

		/**
		 * Returns the DocCheck login client ID.
		 *
		 * @since 1.0.0
		 *
		 * @return string Empty string when not configured.
		 */
		public function login_client_id() {
			return (string) $this->get( 'login_client_id', '' );
		}

		/**
		 * Returns the DocCheck login client secret.
		 *
		 * @since 1.0.0
		 *
		 * @return string Empty string when not configured.
		 */
		public function login_client_secret() {
			return (string) $this->get( 'login_client_secret', '' );
		}

		/**
		 * Returns the configured OAuth redirect URI.
		 *
		 * @since 1.0.0
		 *
		 * @return string Empty string when not configured.
		 */
		public function redirect_uri() {
			$redirect_uri = (string) $this->get( 'redirect_uri', '' );

			return self::is_allowed_redirect_uri( $redirect_uri ) ? $redirect_uri : '';
		}

		/**
		 * Whether client ID, secret and redirect URI are all present.
		 *
		 * @since 1.0.0
		 *
		 * @return bool
		 */
		public function is_configured() {
			return '' !== $this->login_client_id()
				&& '' !== $this->login_client_secret()
				&& '' !== $this->redirect_uri();
		}

		/**
		 * Whether the configured login page exists and is publicly accessible.
		 *
		 * @since 1.0.0
		 *
		 * @return bool
		 */
		public function has_valid_login_page() {
			$page_id = $this->login_page_id();

			return $page_id > 0
				&& 'page' === get_post_type( $page_id )
				&& 'publish' === get_post_status( $page_id )
				&& false !== get_permalink( $page_id );
		}

		/**
		 * Whether the configured login page contains a supported login method.
		 *
		 * The block is the primary method. The shortcode remains available as an alternative.
		 * A missing method warns administrators but does not block template-rendered buttons.
		 *
		 * @since 1.0.0
		 *
		 * @return bool
		 */
		public function login_page_has_login_method() {
			$page = get_post( $this->login_page_id() );

			return $page instanceof WP_Post
				&& (
					has_block( Simple_Login_For_DocCheck_Button::BLOCK_NAME, $page->post_content )
					|| $this->login_page_has_shortcode()
				);
		}

		/**
		 * Whether the configured login page contains the shortcode alternative.
		 *
		 * @since 1.0.0
		 *
		 * @return bool
		 */
		public function login_page_has_shortcode() {
			$page = get_post( $this->login_page_id() );

			return $page instanceof WP_Post
				&& has_shortcode( $page->post_content, Simple_Login_For_DocCheck_Button::SHORTCODE );
		}

		/**
		 * Whether the plugin can complete a login.
		 *
		 * @since 1.0.0
		 *
		 * @return bool
		 */
		public function is_ready() {
			return $this->is_configured() && $this->has_valid_login_page();
		}

		/**
		 * Returns the IDs of the pages that require a DocCheck login.
		 *
		 * @since 1.0.0
		 *
		 * @return int[]
		 */
		public function protected_page_ids() {
			$ids = $this->get( 'protected_pages', array() );
			$ids = array_map( 'absint', (array) $ids );
			$ids = array_values( array_filter( array_unique( $ids ) ) );

			/**
			 * Filters the list of pages that require a DocCheck login.
			 *
			 * @since 1.0.0
			 *
			 * @param int[] $ids Page IDs.
			 */
			return (array) apply_filters( 'simple_login_for_doccheck_protected_page_ids', $ids );
		}

		/**
		 * Returns the login page URL, or the home URL when unset.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public function login_page_url() {
			$page_id = absint( $this->get( 'login_page_id', 0 ) );
			$url     = '';

			if ( $page_id > 0 && 'page' === get_post_type( $page_id ) ) {
				$url = (string) get_permalink( $page_id );
			}

			if ( '' === $url ) {
				$url = home_url( '/' );
			}

			/**
			 * Filters the URL visitors are sent to in order to log in.
			 *
			 * @since 1.0.0
			 *
			 * @param string $url     Login page URL.
			 * @param int    $page_id Configured login page ID.
			 */
			return (string) apply_filters( 'simple_login_for_doccheck_page_url', $url, $page_id );
		}

		/**
		 * Returns the configured login page ID.
		 *
		 * @since 1.0.0
		 *
		 * @return int
		 */
		public function login_page_id() {
			return absint( $this->get( 'login_page_id', 0 ) );
		}

		/**
		 * Returns the login button size.
		 *
		 * @since 1.0.0
		 *
		 * @return string One of small, medium, large.
		 */
		public function button_size() {
			$size = (string) $this->get( 'button_size', 'medium' );

			return in_array( $size, self::button_sizes(), true ) ? $size : 'medium';
		}

		/**
		 * Returns the configured login button language.
		 *
		 * @since 1.0.0
		 *
		 * @return string Two-letter language code, or 'auto' to follow the site language.
		 */
		public function button_language() {
			$language = (string) $this->get( 'button_language', self::LANGUAGE_AUTO );

			if ( self::LANGUAGE_AUTO === $language ) {
				return self::LANGUAGE_AUTO;
			}

			return in_array( $language, self::button_languages(), true ) ? $language : self::LANGUAGE_AUTO;
		}

		/**
		 * Resolves a setting or locale to a supported language.
		 *
		 * @since 1.0.0
		 *
		 * @param string $language Requested language, 'auto', or an empty string to use the setting.
		 * @return string Two-letter language code supported by DocCheck.
		 */
		public function resolve_button_language( $language = '' ) {
			$languages = self::button_languages();
			$language  = strtolower( trim( (string) $language ) );

			if ( '' === $language ) {
				$language = $this->button_language();
			}

			if ( self::LANGUAGE_AUTO === $language ) {
				$language = self::language_from_locale();
			}

			if ( ! in_array( $language, $languages, true ) ) {
				$language = self::LANGUAGE_FALLBACK;
			}

			/**
			 * Filters the language the login button is rendered in.
			 *
			 * @since 1.0.0
			 *
			 * @param string $language Resolved two-letter language code.
			 */
			$language = (string) apply_filters( 'simple_login_for_doccheck_button_language', $language );

			return in_array( $language, $languages, true ) ? $language : self::LANGUAGE_FALLBACK;
		}

		/**
		 * Derives a DocCheck language from the current WordPress locale.
		 *
		 * @since 1.0.0
		 *
		 * @return string Two-letter language code, empty when the locale is unusable.
		 */
		private static function language_from_locale() {
			$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
			$locale = strtolower( (string) $locale );

			if ( strlen( $locale ) < 2 ) {
				return '';
			}

			return substr( $locale, 0, 2 );
		}

		/**
		 * Returns how long a DocCheck login stays valid, in minutes.
		 *
		 * @since 1.0.0
		 *
		 * @return int
		 */
		public function session_lifetime() {
			$minutes = absint( $this->get( 'session_lifetime', 60 ) );

			if ( $minutes < 1 ) {
				$minutes = 60;
			}

			return $minutes;
		}

		/**
		 * Returns the login button sizes supported by DocCheck.
		 *
		 * @since 1.0.0
		 *
		 * @return string[]
		 */
		public static function button_sizes() {
			return array( 'small', 'medium', 'large' );
		}

		/**
		 * Returns the login button languages supported by DocCheck.
		 *
		 * @since 1.0.0
		 *
		 * @return string[]
		 */
		public static function button_languages() {
			return array( 'de', 'en', 'fr', 'es', 'it', 'nl' );
		}

		/**
		 * Whether a redirect URI is an allowed URL on this site's origin.
		 *
		 * Production sites require HTTPS. Non-production environments may use HTTP
		 * to support local development and testing. Fragments and user information
		 * are not valid in an OAuth callback URI.
		 *
		 * @since 1.0.0
		 *
		 * @param string $redirect_uri Redirect URI.
		 * @return bool
		 */
		private static function is_allowed_redirect_uri( $redirect_uri ) {
			$parts = wp_parse_url( $redirect_uri );

			if (
				! is_array( $parts )
				|| empty( $parts['scheme'] )
				|| empty( $parts['host'] )
				|| isset( $parts['fragment'] )
				|| isset( $parts['user'] )
				|| isset( $parts['pass'] )
			) {
				return false;
			}

			$scheme = strtolower( (string) $parts['scheme'] );

			if ( 'production' === wp_get_environment_type() ) {
				if ( 'https' !== $scheme ) {
					return false;
				}
			} elseif ( ! in_array( $scheme, array( 'https', 'http' ), true ) ) {
				return false;
			}

			return self::url_origin( $redirect_uri ) === self::url_origin( home_url( '/' ) );
		}

		/**
		 * Returns a normalized URL origin for comparison.
		 *
		 * @since 1.0.0
		 *
		 * @param string $url Absolute URL.
		 * @return string
		 */
		private static function url_origin( $url ) {
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
		 * Sanitises the settings before they are written to the database.
		 *
		 * @since 1.0.0
		 *
		 * @param mixed $input Raw form input.
		 * @return array<string, mixed>
		 */
		public function sanitize( $input ) {
			$defaults = $this->defaults();

			if ( ! is_array( $input ) ) {
				return $defaults;
			}

			$existing = $this->all();
			$clean    = array();

			$clean['login_client_id'] = isset( $input['login_client_id'] )
				? sanitize_text_field( wp_unslash( $input['login_client_id'] ) )
				: '';

			// An empty secret field means "keep the stored secret".
			$submitted_secret = isset( $input['login_client_secret'] )
				? trim( sanitize_text_field( wp_unslash( $input['login_client_secret'] ) ) )
				: '';

			$clean['login_client_secret'] = '' === $submitted_secret
				? (string) $existing['login_client_secret']
				: $submitted_secret;

			$submitted_redirect_uri = isset( $input['redirect_uri'] ) && is_string( $input['redirect_uri'] )
				? trim( wp_unslash( $input['redirect_uri'] ) )
				: '';
			$redirect_uri           = esc_url_raw( $submitted_redirect_uri, array( 'https', 'http' ) );

			if (
				'' !== $submitted_redirect_uri
				&& ( '' === $redirect_uri || ! self::is_allowed_redirect_uri( $redirect_uri ) )
			) {
				add_settings_error(
					self::OPTION_NAME,
					'simple_login_for_doccheck_redirect_uri_invalid',
					__( 'The redirect URI must be an absolute URL on this site\'s origin and use HTTPS on production sites.', 'simple-login-for-doccheck' ),
					'error'
				);

				$redirect_uri = '';
			}

			$clean['redirect_uri'] = $redirect_uri;

			$clean['login_page_id'] = isset( $input['login_page_id'] ) ? absint( $input['login_page_id'] ) : 0;

			$protected = isset( $input['protected_pages'] ) ? (array) $input['protected_pages'] : array();
			$protected = array_map( 'absint', $protected );

			$clean['protected_pages'] = array_values( array_filter( array_unique( $protected ) ) );

			$size                 = isset( $input['button_size'] ) ? sanitize_key( $input['button_size'] ) : 'medium';
			$clean['button_size'] = in_array( $size, self::button_sizes(), true ) ? $size : 'medium';

			$language                 = isset( $input['button_language'] ) ? sanitize_key( $input['button_language'] ) : self::LANGUAGE_AUTO;
			$allowed_languages        = array_merge( array( self::LANGUAGE_AUTO ), self::button_languages() );
			$clean['button_language'] = in_array( $language, $allowed_languages, true ) ? $language : self::LANGUAGE_AUTO;

			$lifetime                  = isset( $input['session_lifetime'] ) ? absint( $input['session_lifetime'] ) : 60;
			$clean['session_lifetime'] = $lifetime > 0 ? $lifetime : 60;

			$this->flush();

			return wp_parse_args( $clean, $defaults );
		}
	}
}
