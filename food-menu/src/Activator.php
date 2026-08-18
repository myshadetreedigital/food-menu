<?php
namespace FoodMenu\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin activation.
 */
class Activator {

	public static function activate() {
		$post_types = new PostTypes();
		$post_types->register();

		$taxonomies = new Taxonomies();
		$taxonomies->register();

		flush_rewrite_rules();
	}
}
