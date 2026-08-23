<?php
namespace FoodMenu\Core\Elementor\Tags;

use FoodMenu\Core\Elementor\Elementor;
use FoodMenu\Core\MetaFields;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ItemVideoPosterTag extends \Elementor\Core\DynamicTags\Data_Tag {

	public function get_name() {
		return 'fmp-item-video-poster';
	}

	public function get_title() {
		return __( 'Item Video Poster', 'food-menu' );
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

		$poster_id = absint( get_post_meta( $post_id, MetaFields::VIDEO_POSTER, true ) );
		if ( ! $poster_id ) {
			$poster_id = get_post_thumbnail_id( $post_id );
		}
		if ( ! $poster_id ) {
			return array();
		}

		return array(
			'id'  => $poster_id,
			'url' => wp_get_attachment_url( $poster_id ),
		);
	}
}