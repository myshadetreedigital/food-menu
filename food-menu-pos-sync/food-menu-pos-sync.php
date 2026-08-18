<?php
/**
 * Plugin Name:       Food Menu – POS Sync
 * Plugin URI:         https://github.com/myshadetreedigital/food-menu-pos-sync
 * Description:        Pull-only POS sync (Toast, Square) into Food Menu Items. Requires the Food Menu plugin.
 * Version:            2.0.0
 * Requires at least:  6.0
 * Requires PHP:       7.4
 * Requires Plugins:   food-menu
 * Author:             Tiya Rabb
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        food-menu-pos-sync
 * Domain Path:        /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FOOD_MENU_POS_SYNC_VERSION', '2.0.0' );
define( 'FOOD_MENU_POS_SYNC_PLUGIN_FILE', __FILE__ );
define( 'FOOD_MENU_POS_SYNC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FOOD_MENU_POS_SYNC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FOOD_MENU_POS_SYNC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once FOOD_MENU_POS_SYNC_PLUGIN_DIR . 'src/Autoload.php';

register_activation_hook( __FILE__, array( '\\FoodMenu\\PosSync\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\\FoodMenu\\PosSync\\Deactivator', 'deactivate' ) );

/**
 * The `Requires Plugins` header above handles this on WP 6.5+ (blocks
 * activation, shows a notice). This is the fallback for older WP, and for
 * Core being deactivated later while this addon stays active — either way,
 * bail without running any addon code rather than fataling on missing
 * FoodMenu\Core classes.
 */
function food_menu_pos_sync_run() {
	if ( ! function_exists( 'foodmenu_core_is_active' ) || ! foodmenu_core_is_active() ) {
		add_action(
			'admin_notices',
			function () {
				?>
				<div class="notice notice-error">
					<p>
						<?php
						esc_html_e(
							'Food Menu – POS Sync requires the Food Menu plugin to be installed and active.',
							'food-menu-pos-sync'
						);
						?>
					</p>
				</div>
				<?php
			}
		);
		return;
	}

	$plugin = new \FoodMenu\PosSync\Plugin();
	$plugin->run();
}
add_action( 'plugins_loaded', 'food_menu_pos_sync_run', 20 );
