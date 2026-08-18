<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hand-rolled PSR-4 autoloader for the FoodMenu\PosSync namespace. See
 * food-menu/src/Autoload.php for why this isn't Composer.
 */
spl_autoload_register(
	function ( $class ) {
		$prefix = 'FoodMenu\\PosSync\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$path     = FOOD_MENU_POS_SYNC_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( file_exists( $path ) ) {
			require $path;
		}
	}
);
