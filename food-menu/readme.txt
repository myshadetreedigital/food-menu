=== Food Menu ===
Contributors: myshadetreedigital
Tags: menu, restaurant, food, elementor
Requires at least: 6.0
Tested up to: 6.0
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Structured food menu data (branches, locations, categories, prices, variations) for restaurants, franchises, and food trucks — built for Elementor. Core data model; addons attach behavior to it.

== Description ==

Food Menu creates a Food Menu Item custom post type with Branch,
Location, and Category taxonomies, a text-based Price field, and
repeatable Variations (name + price). The plugin owns the data model only —
build the actual menu layout in Elementor using Loop Grid, Loop Carousel,
and Dynamic Tags.

This is the Core plugin in a Core + addon architecture: addons (starting
with Food Menu – POS Sync) attach to it through a small documented hook
surface instead of registering their own copy of the post type. See
README.md for full documentation.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/food-menu`.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Use the new "Food Menu" admin menu to add Branches, Locations,
   Categories, and Menu Items.

== Changelog ==

= 2.0.0 =
* Rebuilt as the Core plugin of a Core + addon architecture. Reuses
  food-menu-plugin's post type, taxonomy, and meta-field slugs so existing
  content carries over unchanged.
* Added a shared Settings page (Admin\SettingsPage) and a small hook
  surface (Support\Hooks) addons attach to instead of duplicating the data
  model.
* Rebuilt on PHP namespaces (FoodMenu\Core\...) with a hand-rolled PSR-4
  autoloader, so this plugin can run alongside food-menu-plugin on the
  same site without a class-name collision.
