<?php
namespace FoodMenu\PosSync\Providers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract every POS provider (Toast, Square, Clover, ...) implements.
 * The sync engine only ever talks to providers through this interface,
 * so adding a new provider never touches the sync/scheduling/admin code.
 *
 * This is deliberately read-only: no provider implementation should ever
 * expose a method that writes back to the POS. Pull-only, by design.
 */
interface ProviderInterface {

	/**
	 * Short machine identifier, e.g. 'toast'. Stored in post meta so synced
	 * items can be traced back to the provider that created them.
	 */
	public function get_id();

	/**
	 * Human-readable name shown in the admin settings screen.
	 */
	public function get_label();

	/**
	 * The settings fields this provider needs (credentials, location ID,
	 * etc). Returns an array of ['key' => ..., 'label' => ..., 'type' =>
	 * 'text'|'password'|'select', 'description' => ..., 'options' =>
	 * ['value' => 'Label', ...] (required when type is 'select')] used
	 * to render the settings form generically.
	 */
	public function get_settings_fields();

	/**
	 * Verifies the configured credentials actually authenticate, without
	 * pulling a full menu. Returns true on success, or a WP_Error
	 * describing what failed.
	 *
	 * @param array $settings This provider's saved settings.
	 * @return true|WP_Error
	 */
	public function test_connection( array $settings );

	/**
	 * Fetches the current menu and returns it as a flat array of
	 * normalized items:
	 *
	 * [
	 *   'pos_item_id' => string,  // stable ID used to prevent duplicates on re-sync
	 *   'name'        => string,
	 *   'price'       => string,  // text, e.g. "$12.99" — never a float
	 *   'category'    => string|null, // display name, for assigning the WP Category term
	 *   'category_id' => string|null, // the provider's own stable category ID — used for filtering,
	 *                                 // never for display. Required if this provider supports
	 *                                 // discover_categories(); the sync engine matches against it.
	 *   'description' => string|null, // used to seed new items only
	 *   'image_url'   => string|null, // used to seed new items only
	 *   'variations'  => array,       // optional, e.g. [['name'=>'Small','price'=>'$6'], ...] — kept in
	 *                                 // sync every pull like price/category, since it's POS-reported
	 *                                 // pricing structure, not staff-authored content. Omit/empty if
	 *                                 // this provider has no equivalent concept.
	 * ]
	 *
	 * @param array $settings This provider's saved settings.
	 * @return array|WP_Error
	 */
	public function fetch_items( array $settings );

	/**
	 * Lists the categories available in this account, cheaply — without
	 * necessarily pulling every item — so the settings screen can let the
	 * user pick which ones to sync instead of always pulling everything.
	 *
	 * Returns an array of ['id' => string, 'name' => string]. The 'id'
	 * values are exactly what fetch_items() puts in each item's
	 * 'category_id', so the sync engine can filter by simple string match.
	 *
	 * @param array $settings This provider's saved settings.
	 * @return array|WP_Error
	 */
	public function discover_categories( array $settings );
}
