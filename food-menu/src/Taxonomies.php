<?php
namespace FoodMenu\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Branch, Location, Menu, and Category taxonomies for Food
 * Menu Items.
 */
class Taxonomies {

	const BRANCH   = 'food_menu_branch';
	const LOCATION = 'food_menu_location';
	const MENU     = 'food_menu_menu';
	const CATEGORY = 'food_menu_category';

	public function init() {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register() {
		$this->register_taxonomy(
			self::BRANCH,
			__( 'Branch', 'food-menu' ),
			__( 'Branches', 'food-menu' ),
			__( 'The franchise or business unit this item belongs to, e.g. Main, Corporate, East Region.', 'food-menu' )
		);

		$this->register_taxonomy(
			self::LOCATION,
			__( 'Location', 'food-menu' ),
			__( 'Locations', 'food-menu' ),
			__( 'The physical location or outlet this item is served at, e.g. Downtown, Atlanta, Food Truck.', 'food-menu' )
		);

		$this->register_taxonomy(
			self::MENU,
			__( 'Menu', 'food-menu' ),
			__( 'Menus', 'food-menu' ),
			__( 'The menu or section this item appears under, e.g. Lunch, Brunch, Apps, Drinks.', 'food-menu' )
		);

		$this->register_taxonomy(
			self::CATEGORY,
			__( 'Category', 'food-menu' ),
			__( 'Categories', 'food-menu' ),
			__( 'A promotional or merchandising tag for this item, e.g. Specials, Featured, Popular, New.', 'food-menu' )
		);
	}

	private function register_taxonomy( $taxonomy, $singular, $plural, $description ) {
		$labels = array(
			'name'          => $plural,
			'singular_name' => $singular,
			'menu_name'     => $plural,
			/* translators: %s: taxonomy plural label */
			'search_items'  => sprintf( __( 'Search %s', 'food-menu' ), $plural ),
			/* translators: %s: taxonomy plural label */
			'all_items'     => sprintf( __( 'All %s', 'food-menu' ), $plural ),
			/* translators: %s: taxonomy singular label */
			'edit_item'     => sprintf( __( 'Edit %s', 'food-menu' ), $singular ),
			/* translators: %s: taxonomy singular label */
			'update_item'   => sprintf( __( 'Update %s', 'food-menu' ), $singular ),
			/* translators: %s: taxonomy singular label */
			'add_new_item'  => sprintf( __( 'Add New %s', 'food-menu' ), $singular ),
			/* translators: %s: taxonomy singular label */
			'new_item_name' => sprintf( __( 'New %s Name', 'food-menu' ), $singular ),
			/* translators: %s: taxonomy plural label, lowercased */
			'not_found'     => sprintf( __( 'No %s found.', 'food-menu' ), strtolower( $plural ) ),
		);

		register_taxonomy(
			$taxonomy,
			array( PostTypes::POST_TYPE ),
			array(
				'labels'             => $labels,
				'description'        => $description,
				'public'             => true,
				'publicly_queryable' => true,
				'hierarchical'       => true,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_nav_menus'  => true,
				'show_in_rest'       => true,
				'rest_base'          => $taxonomy,
				'query_var'          => true,
				'rewrite'            => array( 'slug' => str_replace( '_', '-', $taxonomy ) ),
			)
		);
	}
}
