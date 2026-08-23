# Development Workflow

## Branches

- `main` — production-ready code.
- `staging` — integration branch for work in progress before it goes live.

## Local development

1. Work locally inside this folder.
2. Commit changes with clear messages.
3. Push to the appropriate branch on GitHub (`staging` for work in progress,
   `main` for release-ready code).

```bash
git add <files>
git commit -m "Describe the change"
git push origin <branch-name>
```

## Deploying to tapmytee for testing

This repo is a monorepo (two plugins, one repo), but WordPress needs each
plugin as its own top-level folder under `wp-content/plugins/`. So instead of
cloning straight into `wp-content/plugins/` (like `food-menu-plugin` does),
this repo is cloned into the account home directory and each plugin folder is
symlinked in:

```bash
ssh food-menu-deploy
cd ~/repos/food-menu
git pull origin staging
```

The symlinks (`wp-content/plugins/food-menu` and
`wp-content/plugins/food-menu-pos-sync`, each pointing into
`~/repos/food-menu/`) only need to be created once — they already exist on
tapmytee. A `git pull` updates both plugins at once.

`food-menu-deploy` is a dedicated SSH config alias (`~/.ssh/config`, not in
this repo) using its own deploy key (`id_ed25519_food_menu`) — deliberately
separate from `food-menu-plugin`'s deploy key, so this project's access can
be revoked independently later. The server authenticates to GitHub with its
own read-only deploy key, via a `github.com-food-menu` host alias in the
server's `~/.ssh/config`, registered on this repo only (title "tapmytee
server (read-only)" in the repo's Deploy keys settings).

Both plugins are deployed **inactive** — activate manually from wp-admin
(Core/`food-menu` first, confirm it's stable, then `food-menu-pos-sync`)
rather than auto-activating on deploy, since this hasn't been through a real
WordPress install before.

Once a live (not just test) deploy target is decided, point it at `main`
instead of `staging`, following the same pattern `food-menu-plugin` uses.

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

When assigning a taxonomy value from external data, pass values as an array to
`wp_set_object_terms()`. WordPress can interpret a comma-delimited string as
multiple terms, which would split a full address such as `123 Main St, Suite
200`. Preserve the complete source value and let the user correct bad source
data rather than silently transforming it.
