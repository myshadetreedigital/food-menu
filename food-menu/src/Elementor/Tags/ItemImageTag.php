<?php
namespace FoodMenu\Core\Elementor\Tags;

use FoodMenu\Core\Elementor\Elementor;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convenience wrapper around the featured image, grouped alongside the
 * rest of the Food Menu fields (see ItemNameTag). Uses Data_Tag (not Tag)
 * because image controls expect an ['id' => .., 'url' => ..] array rather
 * than a rendered string.
 */
class ItemImageTag extends \Elementor\Core\DynamicTags\Data_Tag {

	public function get_name() {
		return 'fmp-item-image';
	}

	public function get_title() {
		return __( 'Item Image', 'food-menu' );
	}

	public function get_group() {
		return Elementor::GROUP;
	}

	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY );
	}

	public function get_value( array $options = array() ) {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return array();
		}

		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( ! $thumbnail_id ) {
			return array();
		}

		return array(
			'id'  => $thumbnail_id,
			'url' => wp_get_attachment_url( $thumbnail_id ),
		);
	}
}
