<?php
// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// POS settings (including stored API credentials) and each provider's
// last-sync cache are plugin configuration, not site content — safe to
// remove. The Food Menu Items themselves, and the POS bookkeeping meta on
// them, are left in place — that's the site owner's content.
delete_option( 'food_menu_pos_sync_settings' );

global $wpdb;
$last_sync_options = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'food_menu_pos_sync_last_sync_' ) . '%'
	)
);
foreach ( $last_sync_options as $option_name ) {
	delete_option( $option_name );
}
