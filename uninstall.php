<?php
/**
 * Removes all data created by the plugin.
 *
 * Runs when the user deletes the plugin from the WordPress admin.
 *
 * @package Simple_Login_For_DocCheck
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$simple_login_for_doccheck_option = 'simple_login_for_doccheck_options';

if ( is_multisite() ) {
	$simple_login_for_doccheck_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $simple_login_for_doccheck_site_ids as $simple_login_for_doccheck_site_id ) {
		switch_to_blog( $simple_login_for_doccheck_site_id );
		delete_option( $simple_login_for_doccheck_option );
		restore_current_blog();
	}

	unset( $simple_login_for_doccheck_site_ids, $simple_login_for_doccheck_site_id );
} else {
	delete_option( $simple_login_for_doccheck_option );
}

unset( $simple_login_for_doccheck_option );
