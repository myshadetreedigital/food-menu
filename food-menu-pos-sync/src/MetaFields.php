<?php
namespace FoodMenu\PosSync;

use FoodMenu\Core\PostTypes;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers POS sync bookkeeping meta against Core's Food Menu Item post
 * type — this addon has no post type of its own. POS_ITEM_ID is what the
 * sync engine matches against on every pull to decide update-existing vs
 * create-new, so a re-run never produces duplicate menu items.
 */
class MetaFields {

	const POS_PROVIDER    = 'food_menu_pos_provider';
	const POS_ITEM_ID     = 'food_menu_pos_item_id';
	const POS_LAST_SYNCED = 'food_menu_pos_last_synced';

	public function init() {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register() {
		register_post_meta(
			PostTypes::POST_TYPE,
			self::POS_PROVIDER,
			array(
				'type'              => 'string',
				'description'       => __( 'Which POS provider last synced this item (e.g. toast), if any.', 'food-menu-pos-sync' ),
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_key',
				'auth_callback'     => array( __CLASS__, 'auth_callback' ),
			)
		);

		register_post_meta(
			PostTypes::POST_TYPE,
			self::POS_ITEM_ID,
			array(
				'type'              => 'string',
				'description'       => __( 'The POS provider\'s own identifier for this item. Used to match on re-sync so pulling never creates duplicates.', 'food-menu-pos-sync' ),
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => array( __CLASS__, 'auth_callback' ),
			)
		);

		register_post_meta(
			PostTypes::POST_TYPE,
			self::POS_LAST_SYNCED,
			array(
				'type'              => 'string',
				'description'       => __( 'GMT datetime (MySQL format) this item was last updated from the POS.', 'food-menu-pos-sync' ),
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => array( __CLASS__, 'auth_callback' ),
			)
		);
	}

	public static function auth_callback( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}
}
