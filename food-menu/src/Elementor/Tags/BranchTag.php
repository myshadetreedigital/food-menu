<?php
namespace FoodMenu\Core\Elementor\Tags;

use FoodMenu\Core\Taxonomies;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BranchTag extends TaxonomyTagBase {

	public function get_name() {
		return 'fmp-branch';
	}

	public function get_title() {
		return __( 'Branch', 'food-menu' );
	}

	protected function get_taxonomy() {
		return Taxonomies::BRANCH;
	}
}
