<?php
namespace FoodMenu\Core\Elementor\Tags;

use FoodMenu\Core\Elementor\Elementor;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared rendering logic for the Branch/Location/Category dynamic tags.
 * Each taxonomy gets its own standalone tag (not one tag with a taxonomy
 * picker control) so all three show up as separate, directly selectable
 * options in Elementor's Dynamic Tag list.
 */
abstract class TaxonomyTagBase extends \Elementor\Core\DynamicTags\Tag {

	abstract protected function get_taxonomy();

	public function get_group() {
		return Elementor::GROUP;
	}

	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}

	protected function register_controls() {
		$this->add_control(
			'separator',
			array(
				'label'   => __( 'Separator', 'food-menu' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => ', ',
			)
		);
	}

	public function render() {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$terms = get_the_terms( $post_id, $this->get_taxonomy() );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}

		$separator = $this->get_settings( 'separator' );
		$names     = wp_list_pluck( $terms, 'name' );

		echo esc_html( implode( $separator, $names ) );
	}
}
