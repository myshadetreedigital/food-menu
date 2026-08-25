<?php
namespace FoodMenu\Core\Elementor;

use FoodMenu\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxonomyLoopWidget extends \Elementor\Widget_Base {

	public function get_name() { return 'food-menu-taxonomy-loop'; }

	public function get_title() { return __( 'Food Menu Taxonomy Loop', 'food-menu' ); }

	public function get_icon() { return 'eicon-posts-grid'; }

	public function get_categories() { return array( 'general' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content_section', array( 'label' => __( 'Taxonomy Loop', 'food-menu' ) ) );
		$this->add_control( 'taxonomy', array( 'label' => __( 'Loop Terms', 'food-menu' ), 'type' => \Elementor\Controls_Manager::SELECT, 'options' => array( Taxonomies::BRANCH => __( 'Branches', 'food-menu' ), Taxonomies::LOCATION => __( 'Locations', 'food-menu' ), Taxonomies::MENU => __( 'Menus', 'food-menu' ) ), 'default' => Taxonomies::LOCATION ) );
		$this->add_control( 'show_media', array( 'label' => __( 'Show Images', 'food-menu' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->end_controls_section();
	}

	protected function render() {
		$taxonomy = $this->get_settings_for_display( 'taxonomy' );
		$terms    = $this->get_terms( $taxonomy );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}

		echo '<div class="food-menu-taxonomy-loop">';
		foreach ( $terms as $term ) {
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			echo '<article class="food-menu-taxonomy-loop__item">';
			if ( 'yes' === $this->get_settings_for_display( 'show_media' ) ) {
				$image_id = absint( get_term_meta( $term->term_id, Taxonomies::TERM_IMAGE, true ) );
				if ( $image_id ) {
					echo '<a href="' . esc_url( $link ) . '">' . wp_get_attachment_image( $image_id, 'medium' ) . '</a>';
				}
			}
			echo '<h3><a href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a></h3></article>';
		}
		echo '</div>';
	}

	private function get_terms( $taxonomy ) {
		$parent_id = 0;
		$current   = get_queried_object();
		if ( $current instanceof \WP_Term ) {
			if ( Taxonomies::LOCATION === $taxonomy && Taxonomies::BRANCH === $current->taxonomy ) {
				$parent_id = $current->term_id;
			} elseif ( Taxonomies::MENU === $taxonomy && Taxonomies::LOCATION === $current->taxonomy ) {
				$parent_id = $current->term_id;
			}
		}

		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name' ) );
		if ( is_wp_error( $terms ) || ! $parent_id ) {
			return $terms;
		}

		$meta_key = Taxonomies::LOCATION === $taxonomy ? Taxonomies::LOCATION_BRANCH : Taxonomies::MENU_LOCATION;
		return array_values( array_filter( $terms, function ( $term ) use ( $meta_key, $parent_id ) {
			return $parent_id === absint( get_term_meta( $term->term_id, $meta_key, true ) );
		} ) );
	}
}