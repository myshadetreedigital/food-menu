<?php
namespace FoodMenu\Core;

use FoodMenu\Core\Admin\Admin;
use FoodMenu\Core\Admin\SettingsPage;
use FoodMenu\Core\Elementor\Elementor;
use FoodMenu\Core\Support\Hooks;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin class. Registers hooks, wires up the admin/public areas, and
 * fires Hooks::CORE_LOADED once everything is registered so addons can
 * safely hang their own run() off that instead of guessing load order.
 */
class Plugin {

	public function run() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		$post_types = new PostTypes();
		$post_types->init();

		$taxonomies = new Taxonomies();
		$taxonomies->init();

		$meta_fields = new MetaFields();
		$meta_fields->init();

		if ( is_admin() ) {
			$admin = new Admin();
			$admin->init();

			$settings_page = new SettingsPage();
			$settings_page->init();
		}

		$elementor = new Elementor();
		$elementor->init();

		do_action( Hooks::CORE_LOADED );
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'food-menu',
			false,
			dirname( FOOD_MENU_PLUGIN_BASENAME ) . '/languages'
		);
	}
}
