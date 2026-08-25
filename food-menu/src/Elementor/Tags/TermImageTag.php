<?php
namespace FoodMenu\Core\Elementor\Tags;

use FoodMenu\Core\Elementor\Elementor;
use FoodMenu\Core\Elementor\TaxonomyResolver;
use FoodMenu\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TermImageTag extends \Elementor\Core\DynamicTags\Data_Tag {

	private $taxonomy;
	private $poster;

	public function __construct( $taxonomy, $poster = false ) {
		$this->taxonomy = $taxonomy;
		$this->poster   = $poster;
	}

	public function get_name() { return 'fmp-' . sanitize_key( $this->taxonomy ) . ( $this->poster ? '-video-poster' : '-image' ); }

	public function get_title() {
		$taxonomy = get_taxonomy( $this->taxonomy );
		$label    = $taxonomy ? $taxonomy->labels->singular_name : __( 'Term', 'food-menu' );
		return sprintf( __( '%s %s', 'food-menu' ), $label, $this->poster ? __( 'Video Poster', 'food-menu' ) : __( 'Image', 'food-menu' ) );
	}

	public function get_group() { return Elementor::GROUP; }

	public function get_categories() { return array( \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY ); }

	public function get_value( array $options = array() ) {
		$term = TaxonomyResolver::resolve( $this->taxonomy );
		if ( ! $term ) {
			return array();
		}

		$key      = $this->poster ? Taxonomies::TERM_POSTER : Taxonomies::TERM_IMAGE;
		$image_id = absint( get_term_meta( $term->term_id, $key, true ) );
		if ( $this->poster && ! $image_id ) {
			$image_id = absint( get_term_meta( $term->term_id, Taxonomies::TERM_IMAGE, true ) );
		}

		return $image_id ? array( 'id' => $image_id, 'url' => wp_get_attachment_url( $image_id ) ) : array();
	}
}