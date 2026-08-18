<?php
namespace FoodMenu\Core\Elementor\Tags;

use FoodMenu\Core\Elementor\Elementor;
use FoodMenu\Core\MetaFields;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor Dynamic Tag: outputs the Food Menu Item's variations as a
 * formatted text list. No generic Elementor tag can read a repeatable
 * meta field, so this is the minimum custom integration needed.
 */
class VariationsTag extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'fmp-variations';
	}

	public function get_title() {
		return __( 'Item Variations', 'food-menu' );
	}

	public function get_group() {
		return Elementor::GROUP;
	}

	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}

	protected function register_controls() {
		$this->add_control(
			'row_format',
			array(
				'label'       => __( 'Row Format', 'food-menu' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '{name} - {price}',
				'description' => __( 'Use {name} and {price} as placeholders.', 'food-menu' ),
			)
		);

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

		$variations = get_post_meta( $post_id, MetaFields::VARIATIONS, true );
		if ( empty( $variations ) || ! is_array( $variations ) ) {
			return;
		}

		$separator = $this->get_settings( 'separator' );
		$format    = $this->get_settings( 'row_format' );

		$rows = array();
		foreach ( $variations as $variation ) {
			$name    = isset( $variation['name'] ) ? $variation['name'] : '';
			$price   = isset( $variation['price'] ) ? $variation['price'] : '';
			$rows[]  = str_replace( array( '{name}', '{price}' ), array( $name, $price ), $format );
		}

		echo esc_html( implode( $separator, $rows ) );
	}
}
