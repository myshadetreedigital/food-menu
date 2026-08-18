# Development Workflow

## Branches

- `main` — production-ready code.
- `staging` — integration branch for work in progress before it goes live.

This repo is brand new — no deployment target, SSH deploy key, or live
install has been configured yet. See `food-menu-plugin-api`'s original
`DEVELOPMENT.md` for the pattern to follow once one is chosen (dedicated
deploy key, distinct SSH config alias, read-only GitHub deploy key on the
server).

## Local development

1. Work locally inside this folder.
2. Commit changes with clear messages.
3. Push to the appropriate branch on GitHub once a remote exists.

## Relationship to `food-menu-plugin` / `food-menu-plugin-api`

Those two repos are untouched by this rebuild and keep running independently.
`food-menu/` in this repo reuses `food-menu-plugin`'s post type and taxonomy
slugs (`food_menu_item`, `food_menu_branch/location/category`) and meta keys
on purpose, so a site can eventually swap `food-menu-plugin` for `food-menu`
with its existing content carrying over unchanged. `food-menu-plugin-api`'s
data model (`fmpa_menu_item` etc.) is not carried forward — only its POS sync
logic was ported, into `food-menu-pos-sync/`.

## Safety notes

- Never commit credentials, API keys, `.env` files, or WordPress config
  (`wp-config.php`).
- Do not force-push to `main` or `staging`.
- Test changes on `staging` (or locally) before merging into `main`.
