<?php
namespace FoodMenu\Core\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxonomyResolver {

	public static function resolve( $taxonomy, $post_id = 0 ) {
		$queried = get_queried_object();
		if ( $queried instanceof \WP_Term && $queried->taxonomy === $taxonomy ) {
			return $queried;
		}

		$post_id = $post_id ? $post_id : get_the_ID();
		if ( ! $post_id ) {
			return false;
		}

		$terms = get_the_terms( $post_id, $taxonomy );
		return empty( $terms ) || is_wp_error( $terms ) ? false : $terms[0];
	}

	public static function related_term_id( $taxonomy, $post_id = 0 ) {
		$term = self::resolve( $taxonomy, $post_id );
		return $term ? (int) $term->term_id : 0;
	}
}