<?php
namespace FoodMenu\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Food Menu Item custom post type.
 */
class PostTypes {

	const POST_TYPE = 'food_menu_item';

	public function init() {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register() {
		add_theme_support( 'post-thumbnails' );

		$labels = array(
			'name'                  => __( 'Food Menu Items', 'food-menu' ),
			'singular_name'         => __( 'Food Menu Item', 'food-menu' ),
			'menu_name'             => __( 'Food Menu', 'food-menu' ),
			'name_admin_bar'        => __( 'Food Menu Item', 'food-menu' ),
			'all_items'             => __( 'All Menu Items', 'food-menu' ),
			'add_new'               => __( 'Add Menu Item', 'food-menu' ),
			'add_new_item'          => __( 'Add Menu Item', 'food-menu' ),
			'edit_item'             => __( 'Edit Menu Item', 'food-menu' ),
			'new_item'              => __( 'New Menu Item', 'food-menu' ),
			'view_item'             => __( 'View Menu Item', 'food-menu' ),
			'view_items'            => __( 'View Menu Items', 'food-menu' ),
			'search_items'          => __( 'Search Menu Items', 'food-menu' ),
			'not_found'             => __( 'No menu items found.', 'food-menu' ),
			'not_found_in_trash'    => __( 'No menu items found in Trash.', 'food-menu' ),
			'featured_image'        => __( 'Item Image', 'food-menu' ),
			'set_featured_image'    => __( 'Set item image', 'food-menu' ),
			'remove_featured_image' => __( 'Remove item image', 'food-menu' ),
			'use_featured_image'    => __( 'Use as item image', 'food-menu' ),
			'archives'              => __( 'Menu Item Archives', 'food-menu' ),
			'insert_into_item'      => __( 'Insert into menu item', 'food-menu' ),
			'uploaded_to_this_item' => __( 'Uploaded to this menu item', 'food-menu' ),
			'filter_items_list'     => __( 'Filter menu items list', 'food-menu' ),
			'items_list_navigation' => __( 'Menu items list navigation', 'food-menu' ),
			'items_list'            => __( 'Menu items list', 'food-menu' ),
		);

		$args = array(
			'labels'             => $labels,
			'description'        => __( 'Individual food and drink items for the restaurant menu.', 'food-menu' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'show_in_admin_bar'  => true,
			'show_in_rest'       => true,
			'rest_base'          => 'food-menu-items',
			'menu_position'      => 26,
			'menu_icon'          => 'dashicons-carrot',
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'food-menu-item' ),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			// Deliberately no 'editor' or 'custom-fields' support: item description
			// lives in the excerpt, and price/variations use a dedicated meta box
			// instead of the generic Custom Fields UI (cleaner for non-technical staff).
			'supports'           => array( 'title', 'excerpt', 'thumbnail', 'revisions' ),
		);

		register_post_type( self::POST_TYPE, $args );
	}
}
