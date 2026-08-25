<?php
namespace FoodMenu\Core\Elementor\Tags;

use FoodMenu\Core\Elementor\Elementor;
use FoodMenu\Core\Elementor\TaxonomyResolver;
use FoodMenu\Core\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TermFieldTag extends \Elementor\Core\DynamicTags\Tag {

	private $taxonomy;
	private $field;
	private $name;
	private $title;
	private $category;

	public function __construct( $taxonomy, $field, $name, $title, $category ) {
		$this->taxonomy = $taxonomy;
		$this->field    = $field;
		$this->name     = $name;
		$this->title    = $title;
		$this->category = $category;
	}

	public function get_name() { return $this->name; }

	public function get_title() { return $this->title; }

	public function get_group() { return Elementor::GROUP; }

	public function get_categories() { return array( $this->category ); }

	public function render() {
		$term = TaxonomyResolver::resolve( $this->taxonomy );
		if ( ! $term ) {
			return;
		}

		$value = get_term_meta( $term->term_id, $this->field, true );
		echo Taxonomies::TERM_VIDEO === $this->field ? esc_url( $value ) : esc_html( $value );
	}
}