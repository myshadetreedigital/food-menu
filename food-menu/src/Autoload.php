<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hand-rolled PSR-4 autoloader for the FoodMenu\Core namespace.
 *
 * No Composer here on purpose: neither food-menu-plugin nor
 * food-menu-plugin-api has a build step (deploy is a plain `git pull`), and
 * pulling in Composer just for our own namespacing would add a vendor/
 * directory and a build step the current deploy model doesn't have. This
 * gets the same collision-safety as Composer's autoloader for ~15 lines.
 */
spl_autoload_register(
	function ( $class ) {
		$prefix = 'FoodMenu\\Core\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$path     = FOOD_MENU_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( file_exists( $path ) ) {
			require $path;
		}
	}
);
