<?php
namespace FoodMenu\Core\Elementor\Tags;

use FoodMenu\Core\Elementor\Elementor;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convenience wrapper around the post excerpt, grouped alongside the
 * rest of the Food Menu fields (see ItemNameTag).
 */
class ItemDescriptionTag extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'fmp-item-description';
	}

	public function get_title() {
		return __( 'Item Description', 'food-menu' );
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

		echo esc_html( get_the_excerpt( $post_id ) );
	}
}
