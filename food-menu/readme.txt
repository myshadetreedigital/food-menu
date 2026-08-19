=== Food Menu ===
Contributors: myshadetreedigital
Tags: menu, restaurant, food, elementor
Requires at least: 6.0
Tested up to: 6.0
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Structured food menu data (branches, locations, menus, labels, prices, variations) for restaurants, franchises, and food trucks — built for Elementor. Core data model; addons attach behavior to it.

== Description ==

Food Menu creates a Food Menu Item custom post type with Branch,
Location, Menu, and Label taxonomies, a text-based Price field, and
repeatable Variations (name + price). The plugin owns the data model only —
build the actual menu layout in Elementor using Loop Grid, Loop Carousel,
and Dynamic Tags. Elementor's Loop Grid/Carousel Query controls can
include/exclude by any of these taxonomies automatically, since they're
registered against the post type in the standard WordPress way.

This is the Core plugin in a Core + addon architecture: addons (starting
with Food Menu – POS Sync) attach to it through a small documented hook
surface instead of registering their own copy of the post type. See
README.md for full documentation.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/food-menu`.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Use the new "Food Menu" admin menu to add Branches, Locations,
   Menus, Labels, and Menu Items.

== Changelog ==

= 2.2.0 =
* Renamed the Category taxonomy's label to Label (e.g. Specials, Featured,
  Popular, New) — "Category" read as a second, confusingly similar
  taxonomy sitting right next to Menu. Slug (food_menu_category) and all
  existing term data are unchanged, so nothing on an already-live site
  needs migrating. The Elementor Dynamic Tag's internal name (fmp-category)
  is also unchanged so existing templates keep working; only its display
  title changed to "Label".

= 2.1.0 =
* Added the Menu taxonomy (e.g. Lunch, Brunch, Apps, Drinks) — the "which
  menu/section is this on" dimension that Category used to cover.
* Category's meaning narrowed to a promotional/merchandising tag (e.g.
  Specials, Featured, Popular, New) instead of menu section — description
  text updated accordingly; slug and existing term data are unchanged.
* Menu is now required to publish, alongside Branch, Location, and
  Category.
* Added the Menu Elementor Dynamic Tag (fmp-menu).

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
