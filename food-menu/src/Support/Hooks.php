<?php
namespace FoodMenu\Core\Support;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The addon-facing hook surface. Addons (POS Sync, and anything future)
 * should only ever reach into Core through these — never by calling Core
 * classes/methods that aren't part of this list.
 */
class Hooks {

	/**
	 * Action, no args. Fires once Core has finished registering its post
	 * type/taxonomies/meta/admin/Elementor integration. Addons should hang
	 * their own run() off this instead of guessing plugin load order.
	 */
	const CORE_LOADED = 'foodmenu/core/loaded';

	/**
	 * Filter. Receives an array of tabs, each
	 * ['slug' => string, 'label' => string, 'render' => callable], and
	 * must return the (possibly appended) array. Used to add a tab to Food
	 * Menu's shared settings page instead of registering a new top-level
	 * admin menu.
	 */
	const SETTINGS_TABS = 'foodmenu/core/settings_tabs';

	/**
	 * Action. Receives the Elementor Dynamic Tags manager
	 * (\Elementor\Core\DynamicTags\Manager), fired right after Core
	 * registers its own tags. Addons register their own Dynamic Tags here.
	 */
	const REGISTER_ELEMENTOR_TAGS = 'foodmenu/core/register_elementor_tags';
}
