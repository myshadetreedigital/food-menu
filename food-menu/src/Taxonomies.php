<?php
namespace FoodMenu\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Branch, Location, Menu, and Label taxonomies for Food
 * Menu Items.
 */
class Taxonomies {

	const BRANCH   = 'food_menu_branch';
	const LOCATION = 'food_menu_location';
	const MENU     = 'food_menu_menu';
	// Slug stays food_menu_category — renaming it would orphan every term
	// and term relationship already created under it on live sites. Only
	// the display label changed (Category read as a second, confusingly
	// similar taxonomy next to Menu); "Label" is a better fit for what
	// this taxonomy actually holds — promotional tags, not menu sections.
	const LABEL = 'food_menu_category';

	private $normalizing_single_term = false;

	/**
	 * Preserve commas and other punctuation in a single incoming term value.
	 * WordPress may interpret a comma-delimited string as multiple terms.
	 */
	public static function single_term_value( $value ) {
		$value = sanitize_text_field( wp_unslash( (string) $value ) );
		return '' === $value ? array() : array( $value );
	}

	public function init() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'set_object_terms', array( $this, 'enforce_single_term_relationship' ), 10, 6 );
	}

	public function register() {
		$this->register_taxonomy(
			self::BRANCH,
			__( 'Branch', 'food-menu' ),
			__( 'Branches', 'food-menu' ),
			__( 'The franchise or business unit this item belongs to, e.g. Main, Corporate, East Region.', 'food-menu' ),
			true
		);

		$this->register_taxonomy(
			self::LOCATION,
			__( 'Location', 'food-menu' ),
			__( 'Locations', 'food-menu' ),
			__( 'The physical location or outlet this item is served at, e.g. Downtown, Atlanta, Food Truck.', 'food-menu' ),
			true
		);

		$this->register_taxonomy(
			self::MENU,
			__( 'Menu', 'food-menu' ),
			__( 'Menus', 'food-menu' ),
			__( 'The menu or section this item appears under, e.g. Lunch, Brunch, Apps, Drinks.', 'food-menu' ),
			true
		);

		$this->register_taxonomy(
			self::LABEL,
			__( 'Label', 'food-menu' ),
			__( 'Labels', 'food-menu' ),
			__( 'A promotional or merchandising tag for this item, e.g. Specials, Featured, Popular, New.', 'food-menu' )
		);
	}

	private function register_taxonomy( $taxonomy, $singular, $plural, $description, $single_term = false ) {
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

		$args = array(
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
		);

		if ( $single_term ) {
			$args['meta_box_cb'] = array( $this, 'render_single_term_metabox' );
		}

		register_taxonomy(
			$taxonomy,
			array( PostTypes::POST_TYPE ),
			$args
		);
	}

	public function render_single_term_metabox( $post, $box ) {
		$taxonomy = $box['args']['taxonomy'];
		$terms    = get_the_terms( $post->ID, $taxonomy );
		$selected = ( ! empty( $terms ) && ! is_wp_error( $terms ) ) ? (int) $terms[0]->term_id : 0;
		$taxonomy_object = get_taxonomy( $taxonomy );

		wp_nonce_field( 'update-post_' . $post->ID, 'tax_input_nonce' );
		wp_dropdown_categories(
			array(
				'taxonomy'         => $taxonomy,
				'name'             => 'tax_input[' . $taxonomy . '][]',
				'id'               => 'fmp-single-' . $taxonomy,
				'show_option_none' => sprintf( __( 'Select %s', 'food-menu' ), $taxonomy_object->labels->singular_name ),
				'option_none_value' => '0',
				'hide_empty'       => false,
				'hierarchical'     => true,
				'orderby'          => 'name',
				'selected'         => $selected,
				'class'            => 'widefat',
			)
		);
	}

	public function enforce_single_term_relationship( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( $this->normalizing_single_term || ! in_array( $taxonomy, array( self::BRANCH, self::LOCATION, self::MENU ), true ) ) {
			return;
		}

		$current_ids = wp_get_object_terms( $object_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $current_ids ) || count( $current_ids ) < 2 ) {
			return;
		}

		$this->normalizing_single_term = true;
		wp_set_object_terms( $object_id, array( (int) $current_ids[0] ), $taxonomy, false );
		$this->normalizing_single_term = false;
	}
}
