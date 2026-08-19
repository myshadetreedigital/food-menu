<?php
namespace FoodMenu\Core\Elementor\Tags;

use FoodMenu\Core\Taxonomies;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LabelTag extends TaxonomyTagBase {

	public function get_name() {
		// Kept as 'fmp-category' (not renamed to match the taxonomy) —
		// this is the identifier Elementor stores in every page/template
		// that already uses this Dynamic Tag. Changing it would silently
		// break the tag on any existing page still referencing it.
		return 'fmp-category';
	}

	public function get_title() {
		return __( 'Label', 'food-menu' );
	}

	protected function get_taxonomy() {
		return Taxonomies::LABEL;
	}
}
