<?php
namespace FoodMenu\Core\Elementor;

use FoodMenu\Core\PostTypes;
use FoodMenu\Core\Support\Hooks;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor compatibility.
 *
 * Post title, excerpt, and featured image are already readable by
 * Elementor's own built-in dynamic tags/content types — nothing to do
 * there. Price, Variations, and the three taxonomies are exposed through
 * a small set of custom Dynamic Tags so they're just as accessible from
 * Loop Grid/Carousel, Text, Heading, and Image widgets.
 */
class Elementor {

	const GROUP = 'food-menu';

	public function init() {
		add_action( 'elementor/dynamic_tags/register', array( $this, 'register_dynamic_tags' ) );
		add_action( 'admin_init', array( $this, 'maybe_enable_cpt_support' ) );
	}

	public function register_dynamic_tags( $dynamic_tags ) {
		$dynamic_tags->register_group(
			self::GROUP,
			array(
				'title' => __( 'Food Menu', 'food-menu' ),
			)
		);

		// All Food Menu Item fields live under one group so every field
		// is discoverable in one place, rather than split across
		// Elementor's own Post group and this plugin's tags.
		$dynamic_tags->register( new Tags\ItemNameTag() );
		$dynamic_tags->register( new Tags\ItemDescriptionTag() );
		$dynamic_tags->register( new Tags\ItemImageTag() );
		$dynamic_tags->register( new Tags\BranchTag() );
		$dynamic_tags->register( new Tags\LocationTag() );
		$dynamic_tags->register( new Tags\MenuTag() );
		$dynamic_tags->register( new Tags\CategoryTag() );
		$dynamic_tags->register( new Tags\PriceTag() );
		$dynamic_tags->register( new Tags\VariationsTag() );

		/**
		 * Addons register their own Dynamic Tags here — passes the same
		 * $dynamic_tags manager Core just used above.
		 */
		do_action( Hooks::REGISTER_ELEMENTOR_TAGS, $dynamic_tags );
	}

	/**
	 * Pre-checks Food Menu Item under Elementor > Settings > Post Types so
	 * it's editable with Elementor without a manual settings step. Runs
	 * only once Elementor has actually loaded; safe no-op otherwise.
	 */
	public function maybe_enable_cpt_support() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		$enabled = get_option( 'elementor_cpt_support', array() );
		if ( ! is_array( $enabled ) ) {
			$enabled = array();
		}

		if ( ! in_array( PostTypes::POST_TYPE, $enabled, true ) ) {
			$enabled[] = PostTypes::POST_TYPE;
			update_option( 'elementor_cpt_support', $enabled );
		}
	}
}
