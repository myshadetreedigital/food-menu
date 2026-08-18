<?php
namespace FoodMenu\Core\Elementor\Tags;

use FoodMenu\Core\Taxonomies;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MenuTag extends TaxonomyTagBase {

	public function get_name() {
		return 'fmp-menu';
	}

	public function get_title() {
		return __( 'Menu', 'food-menu' );
	}

	protected function get_taxonomy() {
		return Taxonomies::MENU;
	}
}
