<?php
namespace FoodMenu\Core\Elementor\Tags;

use FoodMenu\Core\Taxonomies;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CategoryTag extends TaxonomyTagBase {

	public function get_name() {
		return 'fmp-category';
	}

	public function get_title() {
		return __( 'Category', 'food-menu' );
	}

	protected function get_taxonomy() {
		return Taxonomies::CATEGORY;
	}
}
