<?php
namespace FoodMenu\Core\Elementor\Tags;

use FoodMenu\Core\Taxonomies;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LocationTag extends TaxonomyTagBase {

	public function get_name() {
		return 'fmp-location';
	}

	public function get_title() {
		return __( 'Location', 'food-menu' );
	}

	protected function get_taxonomy() {
		return Taxonomies::LOCATION;
	}
}
