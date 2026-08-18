<?php
namespace FoodMenu\Core\Elementor\Tags;

use FoodMenu\Core\Elementor\Elementor;
use FoodMenu\Core\MetaFields;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor Dynamic Tag: outputs the Food Menu Item's price meta as plain text.
 */
class PriceTag extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'fmp-price';
	}

	public function get_title() {
		return __( 'Item Price', 'food-menu' );
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

		$price = get_post_meta( $post_id, MetaFields::PRICE, true );
		echo esc_html( $price );
	}
}
