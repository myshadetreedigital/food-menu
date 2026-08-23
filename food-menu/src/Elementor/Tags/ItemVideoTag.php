<?php
namespace FoodMenu\Core\Elementor\Tags;

use FoodMenu\Core\Elementor\Elementor;
use FoodMenu\Core\MetaFields;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ItemVideoTag extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'fmp-item-video';
	}

	public function get_title() {
		return __( 'Item Video', 'food-menu' );
	}

	public function get_group() {
		return Elementor::GROUP;
	}

	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::URL_CATEGORY );
	}

	public function render() {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		echo esc_url( get_post_meta( $post_id, MetaFields::VIDEO_URL, true ) );
	}
}