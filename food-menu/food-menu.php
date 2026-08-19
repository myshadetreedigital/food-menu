<?php
/**
 * Plugin Name:       Food Menu
 * Plugin URI:         https://github.com/myshadetreedigital/food-menu
 * Description:        Structured food menu data (branches, locations, menus, labels, prices, variations) built for Elementor Loop Grid/Carousel presentation. Core data model — addons (POS sync, etc.) attach to it.
 * Version:            2.2.1
 * Requires at least:  6.0
 * Requires PHP:       7.4
 * Author:             Tiya Rabb
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        food-menu
 * Domain Path:        /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FOOD_MENU_VERSION', '2.2.1' );
define( 'FOOD_MENU_PLUGIN_FILE', __FILE__ );
define( 'FOOD_MENU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FOOD_MENU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FOOD_MENU_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once FOOD_MENU_PLUGIN_DIR . 'src/Autoload.php';

register_activation_hook( __FILE__, array( '\\FoodMenu\\Core\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\FoodMenu\\Core\\Deactivator', 'deactivate' ) );

/**
 * Returns true when Food Menu Core is loaded — the dependency check every
 * addon (POS Sync and anything future) should use in its own bootstrap.
 */
function foodmenu_core_is_active() {
	return class_exists( '\\FoodMenu\\Core\\Plugin' );
}

/**
 * Boot the plugin.
 */
function food_menu_run() {
	$plugin = new \FoodMenu\Core\Plugin();
	$plugin->run();
}
food_menu_run();
