<?php
/**
 * Settings screen.
 *
 * @package Simple_Login_For_DocCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Simple_Login_For_DocCheck_Admin' ) ) {

	/**
	 * Builds the Simple Login for DocCheck settings screen with the WordPress Settings API.
	 *
	 * @since 1.0.0
	 */
	class Simple_Login_For_DocCheck_Admin {

		/**
		 * Settings page slug.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		const PAGE_SLUG = 'simple-login-for-doccheck';

		/**
		 * Capability required to manage the settings.
		 *
		 * @since 1.0.0
		 * @var string
		 */
		const CAPABILITY = 'manage_options';

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
			add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'admin_notices', array( $this, 'render_misconfiguration_notice' ) );
			add_filter(
				'plugin_action_links_' . plugin_basename( SIMPLE_LOGIN_FOR_DOCCHECK_FILE ),
				array( $this, 'add_action_link' )
			);
		}

		/**
		 * Adds the settings page below the Settings menu.
		 *
		 * @since 1.0.0
		 */
		public function add_settings_page() {
			add_options_page(
				__( 'Simple Login for DocCheck', 'simple-login-for-doccheck' ),
				__( 'Simple Login for DocCheck', 'simple-login-for-doccheck' ),
				self::CAPABILITY,
				self::PAGE_SLUG,
				array( $this, 'render_settings_page' )
			);
		}

		/**
		 * Adds a settings shortcut to the plugin list.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $links Existing action links.
		 * @return string[]
		 */
		public function add_action_link( $links ) {
			$link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
				esc_html__( 'Settings', 'simple-login-for-doccheck' )
			);

			array_unshift( $links, $link );

			return $links;
		}

		/**
		 * Loads the settings screen stylesheet.
		 *
		 * @since 1.0.0
		 *
		 * @param string $hook_suffix Current admin page.
		 */
		public function enqueue_assets( $hook_suffix ) {
			if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
				return;
			}

			wp_enqueue_style(
				'simple-login-for-doccheck-admin',
				SIMPLE_LOGIN_FOR_DOCCHECK_URL . 'admin/css/simple-login-for-doccheck-admin.css',
				array(),
				SIMPLE_LOGIN_FOR_DOCCHECK_VERSION
			);
		}

		/**
		 * Registers the option, sections and fields.
		 *
		 * @since 1.0.0
		 */
		public function register_settings() {
			register_setting(
				Simple_Login_For_DocCheck_Settings::OPTION_GROUP,
				Simple_Login_For_DocCheck_Settings::OPTION_NAME,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( $this->settings, 'sanitize' ),
					'default'           => $this->settings->defaults(),
				)
			);

			add_settings_section(
				'simple_login_for_doccheck_credentials',
				__( 'DocCheck credentials', 'simple-login-for-doccheck' ),
				array( $this, 'render_credentials_intro' ),
				self::PAGE_SLUG
			);

			add_settings_section(
				'simple_login_for_doccheck_pages',
				__( 'Protected content', 'simple-login-for-doccheck' ),
				array( $this, 'render_pages_intro' ),
				self::PAGE_SLUG
			);

			add_settings_section(
				'simple_login_for_doccheck_appearance',
				__( 'Login button', 'simple-login-for-doccheck' ),
				'__return_empty_string',
				self::PAGE_SLUG
			);

			$fields = array(
				array( 'login_client_id', __( 'Login-Client ID', 'simple-login-for-doccheck' ), 'render_text_field', 'simple_login_for_doccheck_credentials' ),
				array( 'login_client_secret', __( 'Login-Client Secret', 'simple-login-for-doccheck' ), 'render_secret_field', 'simple_login_for_doccheck_credentials' ),
				array( 'redirect_uri', __( 'Redirect URL', 'simple-login-for-doccheck' ), 'render_url_field', 'simple_login_for_doccheck_credentials' ),
				array( 'login_page_id', __( 'Login page', 'simple-login-for-doccheck' ), 'render_login_page_field', 'simple_login_for_doccheck_pages' ),
				array( 'protected_pages', __( 'Protected pages', 'simple-login-for-doccheck' ), 'render_protected_pages_field', 'simple_login_for_doccheck_pages' ),
				array( 'session_lifetime', __( 'Login validity', 'simple-login-for-doccheck' ), 'render_lifetime_field', 'simple_login_for_doccheck_pages' ),
				array( 'button_size', __( 'Button size', 'simple-login-for-doccheck' ), 'render_button_size_field', 'simple_login_for_doccheck_appearance' ),
				array( 'button_language', __( 'Button language', 'simple-login-for-doccheck' ), 'render_button_language_field', 'simple_login_for_doccheck_appearance' ),
			);

			foreach ( $fields as $field ) {
				list( $key, $label, $callback, $section ) = $field;

				add_settings_field(
					'simple_login_for_doccheck_' . $key,
					$label,
					array( $this, $callback ),
					self::PAGE_SLUG,
					$section,
					array( 'label_for' => 'simple_login_for_doccheck_' . $key )
				);
			}
		}

		/**
		 * Warns across the admin while protected content is being blocked.
		 *
		 * @since 1.0.0
		 */
		public function render_misconfiguration_notice() {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				return;
			}

			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

			if ( $screen instanceof WP_Screen && 'settings_page_' . self::PAGE_SLUG === $screen->id ) {
				return;
			}

			$has_dynamic_protection = false !== has_filter( 'simple_login_for_doccheck_is_page_protected' );

			if ( array() === $this->settings->protected_page_ids() && ! $has_dynamic_protection ) {
				return;
			}

			$missing_login_method = $this->settings->is_ready() && ! $this->settings->login_page_has_login_method();

			if ( $this->settings->is_ready() && ! $missing_login_method ) {
				return;
			}

			$title = $missing_login_method
				? __( 'The DocCheck login page is missing its login block.', 'simple-login-for-doccheck' )
				: __( 'Simple Login for DocCheck is not fully configured.', 'simple-login-for-doccheck' );

			$message = $missing_login_method
				? __( 'The selected login page does not contain the DocCheck Login block or the [simple_login_for_doccheck] shortcode alternative. Visitors cannot start the login flow until one is added.', 'simple-login-for-doccheck' )
				: __( 'Visitors currently receive an error instead of your protected pages. Access stays blocked until the client ID, client secret, redirect URL and a published login page are all in place.', 'simple-login-for-doccheck' );

			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p><p><a href="%3$s">%4$s</a></p></div>',
				esc_html( $title ),
				esc_html( $message ),
				esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
				esc_html__( 'Review the Simple Login for DocCheck settings', 'simple-login-for-doccheck' )
			);
		}

		/**
		 * Renders the settings page wrapper.
		 *
		 * @since 1.0.0
		 */
		public function render_settings_page() {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'You are not allowed to manage these settings.', 'simple-login-for-doccheck' ) );
			}

			?>
			<div class="wrap simple-login-for-doccheck-settings">
				<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

				<?php if ( ! $this->settings->is_ready() ) : ?>
					<div class="notice notice-warning inline">
						<p><?php esc_html_e( 'Protected pages stay blocked until the client ID, client secret, redirect URL and a published login page are all configured. Visitors receive an error until then.', 'simple-login-for-doccheck' ); ?></p>
					</div>
				<?php elseif ( ! $this->settings->login_page_has_login_method() ) : ?>
					<div class="notice notice-warning inline">
						<p><?php esc_html_e( 'The selected login page does not contain the DocCheck Login block or the [simple_login_for_doccheck] shortcode alternative. Visitors cannot start the login flow until one is added.', 'simple-login-for-doccheck' ); ?></p>
					</div>
				<?php endif; ?>

				<form action="options.php" method="post">
					<?php
					settings_fields( Simple_Login_For_DocCheck_Settings::OPTION_GROUP );
					do_settings_sections( self::PAGE_SLUG );
					submit_button();
					?>
				</form>
			</div>
			<?php
		}

		/**
		 * Renders the credentials section description.
		 *
		 * @since 1.0.0
		 */
		public function render_credentials_intro() {
			printf(
				'<p>%s</p>',
				wp_kses_post( __( 'You can get these values from <a href="https://access.doccheck.com/" target="_blank">DocCheck Access</a> by creating a new Login-Client. The Redirect-URL must match the value provided by DocCheck Access.', 'simple-login-for-doccheck' ) )
			);
		}

		/**
		 * Renders the protected content section description.
		 *
		 * @since 1.0.0
		 */
		public function render_pages_intro() {
			printf(
				'<p>%s</p>',
				esc_html__( 'Add the DocCheck Login block to your login page, then choose the pages that require a login. The [simple_login_for_doccheck] shortcode is available as an alternative.', 'simple-login-for-doccheck' )
			);
		}

		/**
		 * Renders a plain text field.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $args Field arguments.
		 */
		public function render_text_field( $args ) {
			$key = $this->key_from_args( $args );

			printf(
				'<input type="text" class="regular-text" id="%1$s" name="%2$s" value="%3$s" autocomplete="off" />',
				esc_attr( $args['label_for'] ),
				esc_attr( $this->field_name( $key ) ),
				esc_attr( (string) $this->settings->get( $key, '' ) )
			);
		}

		/**
		 * Renders the client secret field without exposing the stored value.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $args Field arguments.
		 */
		public function render_secret_field( $args ) {
			$key       = $this->key_from_args( $args );
			$has_value = '' !== (string) $this->settings->get( $key, '' );

			printf(
				'<input type="password" class="regular-text" id="%1$s" name="%2$s" value="" autocomplete="new-password" placeholder="%3$s" />',
				esc_attr( $args['label_for'] ),
				esc_attr( $this->field_name( $key ) ),
				esc_attr(
					$has_value
						? __( 'A secret is stored — leave empty to keep it', 'simple-login-for-doccheck' )
						: __( 'Enter your client secret', 'simple-login-for-doccheck' )
				)
			);

			printf(
				'<p class="description">%s</p>',
				esc_html__( 'The stored secret is never displayed. Leave this field empty to keep the current value.', 'simple-login-for-doccheck' )
			);
		}

		/**
		 * Renders the redirect URL field.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $args Field arguments.
		 */
		public function render_url_field( $args ) {
			$key = $this->key_from_args( $args );

			printf(
				'<input type="url" class="large-text code" id="%1$s" name="%2$s" value="%3$s" placeholder="https://example.com/simple-login-for-doccheck/" />',
				esc_attr( $args['label_for'] ),
				esc_attr( $this->field_name( $key ) ),
				esc_attr( (string) $this->settings->get( $key, '' ) )
			);

			printf(
				'<p class="description">%s</p>',
				esc_html__( 'Use a URL on this site\'s origin. HTTPS is required on production sites; HTTP is accepted only when the WordPress environment type is local, development or staging.', 'simple-login-for-doccheck' )
			);
		}

		/**
		 * Renders the login page selector.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $args Field arguments.
		 */
		public function render_login_page_field( $args ) {
			$key = $this->key_from_args( $args );

			wp_dropdown_pages(
				array(
					'id'                => esc_attr( $args['label_for'] ),
					'name'              => esc_attr( $this->field_name( $key ) ),
					'selected'          => absint( $this->settings->login_page_id() ),
					'show_option_none'  => esc_html__( '— Select —', 'simple-login-for-doccheck' ),
					'option_none_value' => '0',
					'post_status'       => 'publish',
				)
			);

			printf(
				'<p class="description">%s</p>',
				esc_html__( 'Visitors are redirected here when they request protected content. This published page must contain the DocCheck Login block or, alternatively, the [simple_login_for_doccheck] shortcode.', 'simple-login-for-doccheck' )
			);
		}

		/**
		 * Renders the protected pages multi select.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $args Field arguments.
		 */
		public function render_protected_pages_field( $args ) {
			$key      = $this->key_from_args( $args );
			$selected = $this->settings->protected_page_ids();

			$pages = get_posts(
				array(
					'post_type'        => 'page',
					'post_status'      => array( 'publish', 'private', 'draft', 'pending', 'future' ),
					'numberposts'      => -1,
					'orderby'          => 'title',
					'order'            => 'ASC',
					'suppress_filters' => false,
				)
			);

			if ( empty( $pages ) ) {
				printf( '<p>%s</p>', esc_html__( 'No pages found.', 'simple-login-for-doccheck' ) );

				return;
			}

			printf(
				'<select multiple size="10" class="simple-login-for-doccheck-settings__pages" id="%1$s" name="%2$s[]">',
				esc_attr( $args['label_for'] ),
				esc_attr( $this->field_name( $key ) )
			);

			foreach ( $pages as $page ) {
				$title = '' !== trim( $page->post_title )
					? $page->post_title
					/* translators: %d: page ID. */
					: sprintf( __( '(no title) #%d', 'simple-login-for-doccheck' ), $page->ID );

				if ( 'publish' !== $page->post_status ) {
					$title .= ' — ' . $page->post_status;
				}

				printf(
					'<option value="%1$d" %2$s>%3$s</option>',
					absint( $page->ID ),
					selected( in_array( (int) $page->ID, $selected, true ), true, false ),
					esc_html( $title )
				);
			}

			echo '</select>';

			printf(
				'<p class="description">%s</p>',
				esc_html__( 'Hold Ctrl (Cmd on macOS) to select several pages. WordPress administrators and editors always keep access.', 'simple-login-for-doccheck' )
			);
		}

		/**
		 * Renders the session lifetime field.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $args Field arguments.
		 */
		public function render_lifetime_field( $args ) {
			$key = $this->key_from_args( $args );

			printf(
				'<input type="number" class="small-text" min="1" step="1" id="%1$s" name="%2$s" value="%3$d" /> %4$s',
				esc_attr( $args['label_for'] ),
				esc_attr( $this->field_name( $key ) ),
				absint( $this->settings->session_lifetime() ),
				esc_html__( 'minutes', 'simple-login-for-doccheck' )
			);

			printf(
				'<p class="description">%s</p>',
				esc_html__( 'How long a DocCheck login remains valid. The login always ends when the browser session ends.', 'simple-login-for-doccheck' )
			);
		}

		/**
		 * Renders the button size selector.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $args Field arguments.
		 */
		public function render_button_size_field( $args ) {
			$this->render_select(
				$args,
				array(
					'small'  => __( 'Small', 'simple-login-for-doccheck' ),
					'medium' => __( 'Medium', 'simple-login-for-doccheck' ),
					'large'  => __( 'Large', 'simple-login-for-doccheck' ),
				),
				$this->settings->button_size()
			);
		}

		/**
		 * Renders the button language selector.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $args Field arguments.
		 */
		public function render_button_language_field( $args ) {
			$choices = array(
				Simple_Login_For_DocCheck_Settings::LANGUAGE_AUTO => __( 'Automatic (site language)', 'simple-login-for-doccheck' ),
			);

			foreach ( Simple_Login_For_DocCheck_Settings::button_languages() as $code ) {
				$choices[ $code ] = strtoupper( $code );
			}

			$this->render_select( $args, $choices, $this->settings->button_language() );

			printf(
				/* translators: %s: language the button currently falls back to, e.g. EN. */
				'<p class="description">' . esc_html__( 'Automatic follows the site language and falls back to %s when DocCheck does not offer it.', 'simple-login-for-doccheck' ) . '</p>',
				esc_html( strtoupper( Simple_Login_For_DocCheck_Settings::LANGUAGE_FALLBACK ) )
			);
		}

		/**
		 * Renders a select field.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $args    Field arguments.
		 * @param array<string, string> $choices Value to label map.
		 * @param string                $current Currently selected value.
		 */
		private function render_select( $args, $choices, $current ) {
			$key = $this->key_from_args( $args );

			printf(
				'<select id="%1$s" name="%2$s">',
				esc_attr( $args['label_for'] ),
				esc_attr( $this->field_name( $key ) )
			);

			foreach ( $choices as $value => $label ) {
				printf(
					'<option value="%1$s" %2$s>%3$s</option>',
					esc_attr( $value ),
					selected( $current, $value, false ),
					esc_html( $label )
				);
			}

			echo '</select>';
		}

		/**
		 * Builds the form input name for an option key.
		 *
		 * @since 1.0.0
		 *
		 * @param string $key Option key.
		 * @return string
		 */
		private function field_name( $key ) {
			return Simple_Login_For_DocCheck_Settings::OPTION_NAME . '[' . $key . ']';
		}

		/**
		 * Extracts the option key from the field arguments.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $args Field arguments.
		 * @return string
		 */
		private function key_from_args( $args ) {
			$label_for = isset( $args['label_for'] ) ? (string) $args['label_for'] : '';

			return (string) preg_replace( '/^simple_login_for_doccheck_/', '', $label_for );
		}
	}
}
