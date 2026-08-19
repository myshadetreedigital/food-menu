=== Food Menu – POS Sync ===
Contributors: myshadetreedigital
Tags: menu, restaurant, pos, square, toast
Requires at least: 6.0
Tested up to: 6.0
Requires PHP: 7.4
Requires Plugins: food-menu
Stable tag: 2.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pull-only POS sync (Toast, Square) into Food Menu Items. Requires the Food Menu plugin.

== Description ==

Adds Toast and Square as pull-only POS sync sources for Food Menu Items.
This addon has no data model of its own — it requires the Food Menu plugin
active and writes into its Food Menu Item post type via plain WordPress
functions (`wp_update_post`, `update_post_meta`, `wp_set_object_terms`).

Settings live under Food Menu → Settings → POS Sync, added via Food Menu's
shared settings-tabs hook rather than a separate top-level admin menu.

== Installation ==

1. Install and activate the **Food Menu** plugin first.
2. Upload the plugin files to `/wp-content/plugins/food-menu-pos-sync`.
3. Activate **Food Menu – POS Sync** through the "Plugins" screen in
   WordPress. If Food Menu isn't active, an admin notice explains why
   nothing happened instead of the site erroring.
4. Configure a provider under **Food Menu → Settings → POS Sync**.

== Changelog ==

= 2.0.1 =
* Follows Food Menu 2.2.0's Category → Label rename internally
  (Taxonomies::CATEGORY → Taxonomies::LABEL, assign_category() →
  assign_label()). No behavior change — Toast/Square's own "category"
  field is still called that in provider code, since that's their term
  for it; only the WordPress-side taxonomy reference changed.

= 2.0.0 =
* Rebuilt as an addon plugin: previously this logic lived inside
  food-menu-plugin-api, which also duplicated the entire core data model
  (its own post type, taxonomies, meta fields). That duplication is gone —
  this addon only ever touches the Food Menu plugin's post type.
* Settings moved from a standalone top-level admin menu into a tab on Food
  Menu's shared Settings page.
* Rebuilt on PHP namespaces (FoodMenu\PosSync\...) so this plugin can run
  alongside food-menu-plugin-api on the same site without a class-name
  collision.
