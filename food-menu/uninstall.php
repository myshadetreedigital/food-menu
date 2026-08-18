<?php
// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Food Menu Item posts, taxonomy terms, and meta are intentionally left
// in place — that's the site owner's content, and deleting it on
// uninstall risks destroying data they didn't ask to lose. Only remove
// the setting this plugin added to Elementor's own option.
$enabled = get_option( 'elementor_cpt_support', array() );
if ( is_array( $enabled ) && in_array( 'food_menu_item', $enabled, true ) ) {
	$enabled = array_values( array_diff( $enabled, array( 'food_menu_item' ) ) );
	update_option( 'elementor_cpt_support', $enabled );
}
