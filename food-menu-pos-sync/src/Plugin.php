<?php
namespace FoodMenu\PosSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Addon plugin class. Only runs once food-menu.php has confirmed Food Menu
 * Core is active (see food-menu-pos-sync.php) — this class assumes
 * FoodMenu\Core\* is fully loaded.
 */
class Plugin {

	public function run() {
		// Called directly (not hooked to plugins_loaded) because run()
		// itself only executes from inside a plugins_loaded callback (see
		// food-menu-pos-sync.php's dependency check) — by the time we get
		// here, that hook is already mid-fire, so a lower-priority
		// plugins_loaded registration added now would never run.
		$this->load_textdomain();

		$meta_fields = new MetaFields();
		$meta_fields->init();

		if ( is_admin() ) {
			$settings = new Settings();
			$settings->init();
		}

		// Not gated behind is_admin() — wp-cron.php requests aren't admin
		// requests, and the scheduled sync hook needs to be registered
		// whenever WP-Cron might fire it.
		$scheduler = new Scheduler();
		$scheduler->init();
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'food-menu-pos-sync',
			false,
			dirname( FOOD_MENU_POS_SYNC_PLUGIN_BASENAME ) . '/languages'
		);
	}
}
