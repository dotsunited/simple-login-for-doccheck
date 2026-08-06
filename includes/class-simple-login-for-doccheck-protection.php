<?php
/**
 * Content access protection.
 *
 * @package Simple_Login_For_DocCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Simple_Login_For_DocCheck_Protection' ) ) {

	/**
	 * Keeps protected content out of public responses until the visitor logs in.
	 *
	 * @since 1.0.0
	 */
	class Simple_Login_For_DocCheck_Protection {

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
		 * Protected page IDs after the public list filter has run.
		 *
		 * @since 1.0.0
		 * @var int[]|null
		 */
		private $protected_page_ids;

		/**
		 * Per-post protection decisions for the current request.
		 *
		 * @since 1.0.0
		 * @var bool[]
		 */
		private $protection_cache = array();

		/**
		 * Post IDs whose protection filters are currently running.
		 *
		 * @since 1.0.0
		 * @var bool[]
		 */
		private $protection_in_progress = array();

		/**
		 * Whether an internal protection lookup is running.
		 *
		 * Prevents nested filter queries from re-entering protection hooks.
		 *
		 * @since 1.0.0
		 * @var bool
		 */
		private $resolving_protection = false;

		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 *
		 * @param Simple_Login_For_DocCheck_Settings $settings Settings repository.
		 * @param Simple_Login_For_DocCheck_Auth     $auth     Authentication handler.
		 */
		public function __construct( Simple_Login_For_DocCheck_Settings $settings, Simple_Login_For_DocCheck_Auth $auth ) {
			$this->settings = $settings;
			$this->auth     = $auth;
		}

		/**
		 * Registers the hooks owned by this class.
		 *
		 * @since 1.0.0
		 */
		public function init() {
			add_action( 'template_redirect', array( $this, 'enforce' ), 10 );
			add_action( 'rest_api_init', array( $this, 'register_rest_hooks' ) );
			add_action( 'pre_get_posts', array( $this, 'filter_suppressed_public_query' ), PHP_INT_MAX );

			add_filter( 'the_posts', array( $this, 'filter_public_posts' ), PHP_INT_MAX, 2 );
			add_filter( 'the_content', array( $this, 'filter_content' ) );
		}

		/**
		 * Redirects protected requests, or denies them when login is unavailable.
		 *
		 * @since 1.0.0
		 */
		public function enforce() {
			if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! is_singular() ) {
				return;
			}

			$post_id = get_queried_object_id();

			if ( ! $this->is_content_protected( $post_id ) ) {
				return;
			}

			$this->prevent_caching();

			if ( $this->visitor_can_access() ) {
				return;
			}

			if ( ! $this->settings->is_ready() ) {
				$this->deny();
			}

			$return_url = (string) get_permalink( $post_id );
			$login_url  = $this->settings->login_page_url();

			// A filtered login URL must not be allowed to redirect back to this request.
			if ( '' === $login_url || untrailingslashit( $return_url ) === untrailingslashit( $login_url ) ) {
				$this->deny();
			}

			$this->auth->remember_return_url( $return_url );

			wp_safe_redirect( $login_url );
			exit;
		}

		/**
		 * Refuses the current request for protected content.
		 *
		 * @since 1.0.0
		 */
		private function deny() {
			wp_die(
				esc_html__( 'This content requires a DocCheck login, which is currently unavailable. Please try again later.', 'simple-login-for-doccheck' ),
				esc_html__( 'DocCheck login unavailable', 'simple-login-for-doccheck' ),
				array( 'response' => 503 )
			);
		}

		/**
		 * Registers protection filters for every post type exposed through REST.
		 *
		 * @since 1.0.0
		 */
		public function register_rest_hooks() {
			$post_types = get_post_types( array( 'show_in_rest' => true ), 'names' );

			foreach ( $post_types as $post_type ) {
				add_filter( 'rest_prepare_' . $post_type, array( $this, 'protect_rest_response' ), PHP_INT_MAX, 3 );
			}
		}

		/**
		 * Excludes protected posts when a query suppresses result filters.
		 *
		 * Suppressed filters prevent redaction, so objects are omitted while ID-only
		 * results remain available.
		 *
		 * @since 1.0.0
		 *
		 * @param WP_Query $query Query instance.
		 */
		public function filter_suppressed_public_query( $query ) {
			$fields = $query->get( 'fields' );

			if (
				$this->resolving_protection
				|| ! $this->is_public_request()
				|| $this->visitor_can_access()
				|| ! $query->get( 'suppress_filters' )
				|| in_array( $fields, array( 'ids', 'id=>parent' ), true )
			) {
				return;
			}

			$protected_ids = false !== has_filter( 'simple_login_for_doccheck_is_page_protected' )
				? $this->dynamic_protected_ids_for_query( $query )
				: $this->protected_page_ids();
			$requested_id  = max(
				absint( $query->get( 'p' ) ),
				absint( $query->get( 'page_id' ) ),
				absint( $query->get( 'attachment_id' ) )
			);

			if ( $requested_id && in_array( $requested_id, $protected_ids, true ) ) {
				$query->set( 'p', 0 );
				$query->set( 'page_id', 0 );
				$query->set( 'attachment_id', 0 );
				$query->set( 'post__in', array( 0 ) );

				return;
			}

			$args = $this->exclude_protected_pages(
				array(
					'post__in'     => $query->get( 'post__in' ),
					'post__not_in' => $query->get( 'post__not_in' ),
				),
				$protected_ids
			);

			$query->set( 'post__in', $args['post__in'] );
			$query->set( 'post__not_in', $args['post__not_in'] );
		}

		/**
		 * Redacts protected bodies while preserving public listing metadata.
		 *
		 * Cloning preserves the shared cached WP_Post object.
		 *
		 * @since 1.0.0
		 *
		 * @param WP_Post[]|int[] $posts Queried posts or post IDs.
		 * @param WP_Query        $query Query instance.
		 * @return WP_Post[]|int[]
		 */
		public function filter_public_posts( $posts, $query ) {
			unset( $query );

			if ( $this->resolving_protection || ! $this->is_public_request() ) {
				return $posts;
			}

			$protected_found    = false;
			$visitor_can_access = $this->visitor_can_access();

			foreach ( $posts as $index => $post ) {
				$post_id = $post instanceof WP_Post ? $post->ID : absint( $post );

				if ( ! $this->is_content_protected( $post_id ) ) {
					continue;
				}

				$protected_found = true;

				if ( ! $visitor_can_access && $post instanceof WP_Post ) {
					$redacted                        = clone $post;
					$redacted->post_content          = '';
					$redacted->post_content_filtered = '';
					$posts[ $index ]                 = $redacted;
				}
			}

			if ( $protected_found && $visitor_can_access ) {
				$this->prevent_caching();
			}

			return $posts;
		}

		/**
		 * Prevents protected content from being returned by content filters.
		 *
		 * @since 1.0.0
		 *
		 * @param string $content Filtered post content.
		 * @return string
		 */
		public function filter_content( $content ) {
			if ( ! $this->is_public_request() ) {
				return $content;
			}

			$post_id = get_the_ID();

			if ( ! $this->is_content_protected( $post_id ) ) {
				return $content;
			}

			if ( $this->visitor_can_access() ) {
				$this->prevent_caching();

				return $content;
			}

			return '';
		}

		/**
		 * Redacts protected bodies from REST responses.
		 *
		 * Denies responses whose data cannot be safely redacted.
		 *
		 * @since 1.0.0
		 *
		 * @param WP_REST_Response $response REST response.
		 * @param WP_Post          $post     Post object.
		 * @param WP_REST_Request  $request  REST request.
		 * @return WP_REST_Response
		 */
		public function protect_rest_response( $response, $post, $request ) {
			unset( $request );

			if ( ! $this->is_content_protected( $post->ID ) ) {
				return $response;
			}

			if ( $this->visitor_can_access() ) {
				$this->prevent_caching();

				return $response;
			}

			$data = $response->get_data();

			if ( is_array( $data ) ) {
				if ( array_key_exists( 'content', $data ) ) {
					if ( ! is_array( $data['content'] ) ) {
						$data['content'] = '';
					} else {
						$redacted = array();

						foreach ( array( 'raw', 'rendered' ) as $value_key ) {
							if ( array_key_exists( $value_key, $data['content'] ) ) {
								$redacted[ $value_key ] = '';
							}
						}

						if ( array_key_exists( 'protected', $data['content'] ) ) {
							$redacted['protected'] = true;
						}

						$data['content'] = $redacted;
					}
				}

				$response->set_data( $data );

				return $response;
			}

			return new WP_REST_Response(
				array(
					'code'    => 'simple_login_for_doccheck_rest_forbidden',
					'message' => __( 'DocCheck login is required to access this content.', 'simple-login-for-doccheck' ),
					'data'    => array( 'status' => 403 ),
				),
				403
			);
		}

		/**
		 * Adds protected pages to a query's exclusion list.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $args          Query arguments.
		 * @param int[]                $protected_ids Protected post IDs.
		 * @return array<string, mixed>
		 */
		private function exclude_protected_pages( $args, $protected_ids ) {
			$included_ids = isset( $args['post__in'] ) ? wp_parse_id_list( $args['post__in'] ) : array();

			if ( $included_ids ) {
				$included_ids = array_values( array_diff( $included_ids, $protected_ids ) );

				// An empty post__in means "all posts" to WP_Query, so use an impossible ID instead.
				$args['post__in'] = $included_ids ? $included_ids : array( 0 );
			} else {
				$args['post__in']     = $included_ids;
				$args['post__not_in'] = array_values(
					array_unique(
						array_merge(
							isset( $args['post__not_in'] ) ? wp_parse_id_list( $args['post__not_in'] ) : array(),
							$protected_ids
						)
					)
				);
			}

			return $args;
		}

		/**
		 * Resolves dynamically protected posts for an unfilterable query.
		 *
		 * @since 1.0.0
		 *
		 * @param WP_Query $query Query instance.
		 * @return int[]
		 */
		private function dynamic_protected_ids_for_query( $query ) {
			$query_args                           = $query->query_vars;
			$query_args['fields']                 = 'ids';
			$query_args['posts_per_page']         = -1;
			$query_args['posts_per_archive_page'] = -1;
			$query_args['posts_per_rss']          = -1;
			$query_args['showposts']              = 0;
			$query_args['nopaging']               = true;
			$query_args['paged']                  = 0;
			$query_args['page']                   = 0;
			$query_args['offset']                 = 0;
			$query_args['no_found_rows']          = true;
			$query_args['cache_results']          = false;
			$query_args['update_post_meta_cache'] = false;
			$query_args['update_post_term_cache'] = false;
			$query_args['lazy_load_term_meta']    = false;

			$was_resolving              = $this->resolving_protection;
			$this->resolving_protection = true;

			try {
				$candidate_query = new WP_Query();
				$candidate_ids   = $candidate_query->query( $query_args );
			} finally {
				$this->resolving_protection = $was_resolving;
			}

			$protected_ids = array();

			foreach ( wp_parse_id_list( $candidate_ids ) as $post_id ) {
				if ( $this->is_content_protected( $post_id ) ) {
					$protected_ids[] = $post_id;
				}
			}

			return $protected_ids;
		}

		/**
		 * Returns the filtered configured protection list once per request.
		 *
		 * @since 1.0.0
		 *
		 * @return int[]
		 */
		private function protected_page_ids() {
			if ( null !== $this->protected_page_ids ) {
				return $this->protected_page_ids;
			}

			if ( $this->resolving_protection ) {
				return array();
			}

			$this->resolving_protection = true;

			try {
				$this->protected_page_ids = wp_parse_id_list( $this->settings->protected_page_ids() );
			} finally {
				$this->resolving_protection = false;
			}

			return $this->protected_page_ids;
		}

		/**
		 * Whether the current request may return public front-end content.
		 *
		 * @since 1.0.0
		 *
		 * @return bool
		 */
		private function is_public_request() {
			if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
				return false;
			}

			return ! is_admin() || wp_doing_ajax();
		}

		/**
		 * Whether a post is protected by settings or the public filter.
		 *
		 * @since 1.0.0
		 *
		 * @param int $post_id Post ID.
		 * @return bool
		 */
		public function is_content_protected( $post_id ) {
			$post_id = absint( $post_id );

			if ( ! $post_id || $this->settings->login_page_id() === $post_id ) {
				return false;
			}

			if ( array_key_exists( $post_id, $this->protection_cache ) ) {
				return $this->protection_cache[ $post_id ];
			}

			$protected = in_array( $post_id, $this->protected_page_ids(), true );

			if ( isset( $this->protection_in_progress[ $post_id ] ) ) {
				return $protected;
			}

			$this->protection_in_progress[ $post_id ] = true;
			$was_resolving                            = $this->resolving_protection;
			$this->resolving_protection               = true;

			/**
			 * Filters whether a specific post requires a DocCheck login.
			 *
			 * @since 1.0.0
			 *
			 * @param bool $protected Whether the post is protected.
			 * @param int  $post_id   Post ID being requested.
			 */
			try {
				$protected = (bool) apply_filters( 'simple_login_for_doccheck_is_page_protected', $protected, $post_id );
			} finally {
				$this->resolving_protection = $was_resolving;
				unset( $this->protection_in_progress[ $post_id ] );
			}

			$this->protection_cache[ $post_id ] = $protected;

			return $protected;
		}

		/**
		 * Whether the current visitor may receive protected content.
		 *
		 * @since 1.0.0
		 *
		 * @return bool
		 */
		private function visitor_can_access() {
			return current_user_can( 'edit_pages' ) || $this->auth->is_authenticated();
		}

		/**
		 * Marks the current response as unsuitable for shared caches.
		 *
		 * @since 1.0.0
		 */
		private function prevent_caching() {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standard cache-plugin interoperability constant.
				define( 'DONOTCACHEPAGE', true );
			}

			if ( ! headers_sent() ) {
				nocache_headers();
			}
		}
	}
}
