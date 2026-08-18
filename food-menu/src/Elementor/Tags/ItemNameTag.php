<?php
namespace FoodMenu\Core\Elementor\Tags;

use FoodMenu\Core\Elementor\Elementor;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convenience wrapper around the post title, grouped alongside the rest
 * of the Food Menu fields so every item field is discoverable in one
 * place in Elementor's Dynamic Tag list (Elementor's native Post Title
 * tag still works too — this isn't required, just easier to find).
 */
class ItemNameTag extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'fmp-item-name';
	}

	public function get_title() {
		return __( 'Item Name', 'food-menu' );
	}

	public function get_group() {
		return Elementor::GROUP;
	}

	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}

	public function render() {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		echo esc_html( get_the_title( $post_id ) );
	}
}
