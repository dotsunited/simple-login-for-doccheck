<?php
/**
 * Plugin Name:       Simple Login for DocCheck
 * Plugin URI:        https://github.com/dotsunited/simple-login-for-doccheck
 * Update URI:        false
 * Description:       Protects selected pages with a DocCheck OAuth 2.0 login for verified medical professionals.
 * Version:           1.0.1
 * Requires at least: 6.3
 * Requires PHP:      8.0
 * Author:            Dots United GmbH
 * Author URI:        https://dotsunited.de/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simple-login-for-doccheck
 * Domain Path:       /languages
 *
 * @package Simple_Login_For_DocCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'SIMPLE_LOGIN_FOR_DOCCHECK_VERSION' ) ) {
	return;
}

define( 'SIMPLE_LOGIN_FOR_DOCCHECK_VERSION', '1.0.1' );
define( 'SIMPLE_LOGIN_FOR_DOCCHECK_FILE', __FILE__ );
define( 'SIMPLE_LOGIN_FOR_DOCCHECK_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIMPLE_LOGIN_FOR_DOCCHECK_URL', plugin_dir_url( __FILE__ ) );

/**
 * Minimum PHP version.
 *
 * @since 1.0.0
 */
define( 'SIMPLE_LOGIN_FOR_DOCCHECK_MIN_PHP', '8.0' );

if ( version_compare( PHP_VERSION, SIMPLE_LOGIN_FOR_DOCCHECK_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version. */
						__( 'Simple Login for DocCheck requires PHP %1$s or higher. This site runs PHP %2$s, so the plugin has been deactivated.', 'simple-login-for-doccheck' ),
						SIMPLE_LOGIN_FOR_DOCCHECK_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}
	);

	return;
}

if ( ! class_exists( 'Simple_Login_For_DocCheck' ) ) {
	$simple_login_for_doccheck_autoloader = SIMPLE_LOGIN_FOR_DOCCHECK_DIR . 'vendor/autoload.php';

	if ( is_readable( $simple_login_for_doccheck_autoloader ) ) {
		require_once $simple_login_for_doccheck_autoloader;
	}

	unset( $simple_login_for_doccheck_autoloader );
}

if ( ! class_exists( 'Simple_Login_For_DocCheck' ) ) {
	add_action(
		'init',
		static function () {
			load_plugin_textdomain(
				'simple-login-for-doccheck',
				false,
				dirname( plugin_basename( SIMPLE_LOGIN_FOR_DOCCHECK_FILE ) ) . '/languages'
			);
		}
	);

	add_action(
		'admin_notices',
		static function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Simple Login for DocCheck could not load its Composer autoloader. Run composer install or install an official release archive.', 'simple-login-for-doccheck' )
			);
		}
	);

	return;
}

/**
 * Returns the shared plugin instance.
 *
 * @since 1.0.0
 *
 * @return Simple_Login_For_DocCheck
 */
function simple_login_for_doccheck() {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new Simple_Login_For_DocCheck();
	}

	return $plugin;
}

simple_login_for_doccheck()->init();
