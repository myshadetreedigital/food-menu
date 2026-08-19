# Food Menu

A Core + addon rebuild of `food-menu-plugin` / `food-menu-plugin-api`. Those two
repos were independent forks that happened to look similar — this repo instead
has one plugin own the data model, with addons attaching behavior to it.

- **`food-menu/`** — Core plugin. Owns the Food Menu Item post type, the
  Branch/Location/Menu/Label taxonomies, Price/Variations meta, the admin UI,
  and the Elementor Dynamic Tags. Nothing else needs to be installed for a
  site to have a working food menu.
- **`food-menu-pos-sync/`** — Addon plugin. Requires Core active. Adds
  pull-only POS sync (Toast, Square) into Core's post type. No data model of
  its own.

Both plugins use PHP namespaces (`FoodMenu\Core\...`, `FoodMenu\PosSync\...`)
rather than the global class-prefix convention `food-menu-plugin` and
`food-menu-plugin-api` use, specifically so this repo's plugins can run
alongside those two on the same WordPress install without a class-name
collision, and so future addons can't collide with Core or each other either.

## Requirements

- WordPress 6.0+ (6.5+ recommended — `food-menu-pos-sync` uses the
  `Requires Plugins` header for automatic dependency handling on 6.5+, and
  falls back to an admin notice on older versions)
- PHP 7.4+
- Elementor (free or Pro) if you want to build menu layouts with it

## Addon hook surface

Core exposes a small, documented set of hooks so addons never need to touch
Core internals — see `food-menu/src/Support/Hooks.php` for the full list:

- `foodmenu/core/loaded` (action) — fires once Core finishes booting.
- `foodmenu/core/settings_tabs` (filter) — add a tab to Food Menu's shared
  settings page instead of registering a new top-level admin menu.
- `foodmenu/core/register_elementor_tags` (action) — register additional
  Elementor Dynamic Tags.
- `foodmenu_core_is_active()` (global helper function) — boolean dependency
  check addons can use in their own bootstrap.

See `DEVELOPMENT.md` for the branch model and deploy notes.
