<?php
namespace FoodMenu\PosSync;

use FoodMenu\Core\Admin\SettingsPage;
use FoodMenu\Core\PostTypes;
use FoodMenu\Core\Taxonomies;
use FoodMenu\Core\Support\Hooks;
use FoodMenu\PosSync\Providers\SquareProvider;
use FoodMenu\PosSync\Providers\ToastProvider;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POS Sync's tab on Food Menu's shared Settings page (see
 * FoodMenu\Core\Admin\SettingsPage — this addon attaches via the
 * Hooks::SETTINGS_TABS filter rather than registering its own top-level
 * admin menu, which is what food-menu-plugin-api's version of this class
 * used to do).
 *
 * Structured like WooCommerce's Payments screen: a Providers tab lists
 * every provider with its status and a Manage link into that provider's
 * own view. Each provider's view is fully self-contained — credentials,
 * category filter, Branch/Location assignment, new-item status, schedule,
 * Test Connection, and Pull Now all live there — because more than one
 * provider can be enabled and syncing independently at once (e.g. one
 * per Location on a different POS system), so there's no single shared
 * "active provider" or shared sync options. Restricted to
 * `manage_options` (enforced by SettingsPage before this tab's render
 * callback ever runs) since it handles API credentials, unlike the rest
 * of Core's admin UI.
 */
class Settings {

	const TAB_SLUG     = 'pos-sync';
	const OPTION_KEY   = 'food_menu_pos_sync_settings';
	const NONCE_ACTION = 'food_menu_pos_sync_save_settings';
	const NONCE_NAME   = 'food_menu_pos_sync_settings_nonce';

	public function init() {
		add_filter( Hooks::SETTINGS_TABS, array( $this, 'register_tab' ) );
		add_action( 'admin_post_food_menu_pos_sync_save_provider', array( $this, 'handle_save_provider' ) );
		add_action( 'admin_post_food_menu_pos_sync_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'admin_post_food_menu_pos_sync_manual_sync', array( $this, 'handle_manual_sync' ) );
		add_action( 'admin_post_food_menu_pos_sync_discover_categories', array( $this, 'handle_discover_categories' ) );
		add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
	}

	public function register_tab( array $tabs ) {
		$tabs[] = array(
			'slug'   => self::TAB_SLUG,
			'label'  => __( 'POS Sync', 'food-menu-pos-sync' ),
			'render' => array( $this, 'render_tab' ),
		);
		return $tabs;
	}

	private static function discovered_categories_transient_key( $provider_id ) {
		return 'food_menu_pos_sync_categories_' . $provider_id;
	}

	/**
	 * Registry of available providers. Clover slots in here later
	 * without touching sync/scheduling/settings-rendering code.
	 */
	public static function get_providers() {
		return array(
			'square' => new SquareProvider(),
			'toast'  => new ToastProvider(),
		);
	}

	public static function get_provider_instance( $id ) {
		$providers = self::get_providers();
		return isset( $providers[ $id ] ) ? $providers[ $id ] : null;
	}

	/**
	 * Each provider carries its own complete settings — credentials plus
	 * category_filter, branch_term_id, location_term_id, new_item_status,
	 * schedule, and enabled. There is no shared/global sync config.
	 */
	public static function get_settings() {
		$defaults = array();

		foreach ( array_keys( self::get_providers() ) as $provider_id ) {
			$defaults[ $provider_id ] = array(
				'enabled'          => false,
				'branch_term_id'   => 0,
				'location_term_id' => 0,
				'new_item_status'  => 'draft',
				'schedule'         => 'disabled',
				'category_filter'  => array(),
			);
		}

		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array_replace_recursive( $defaults, $saved );
	}

	private function settings_url( array $extra_args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'post_type' => PostTypes::POST_TYPE,
					'page'      => SettingsPage::MENU_SLUG,
					'tab'       => self::TAB_SLUG,
				),
				$extra_args
			),
			admin_url( 'edit.php' )
		);
	}

	private function provider_url( $id, array $extra_args = array() ) {
		return $this->settings_url( array_merge( array( 'provider' => $id ), $extra_args ) );
	}

	private function providers_list_url() {
		return $this->settings_url();
	}

	public function render_tab() {
		$provider_id = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<p><?php esc_html_e( 'Pull-only: this never writes changes back to a POS. More than one provider can be enabled at once — useful if different Branches/Locations run different POS systems. Name, Price, Label, and Variations (for providers that report them) are kept in sync on every pull; Item Description and Item Image are only set the first time an item is imported, so your edits in wp-admin are never overwritten.', 'food-menu-pos-sync' ); ?></p>

		<?php
		if ( $provider_id && self::get_provider_instance( $provider_id ) ) {
			$this->render_provider_manage( $provider_id );
		} else {
			$this->render_providers_list();
		}
	}

	private function render_providers_list() {
		$settings = self::get_settings();
		?>
		<table class="widefat striped" style="max-width:900px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Provider', 'food-menu-pos-sync' ); ?></th>
					<th><?php esc_html_e( 'Status', 'food-menu-pos-sync' ); ?></th>
					<th><?php esc_html_e( 'Syncs into', 'food-menu-pos-sync' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( self::get_providers() as $id => $provider ) : ?>
					<?php $values = isset( $settings[ $id ] ) ? $settings[ $id ] : array(); ?>
					<?php $configured = $this->provider_has_credentials( $values ); ?>
					<tr>
						<td><strong><?php echo esc_html( $provider->get_label() ); ?></strong></td>
						<td>
							<?php if ( ! empty( $values['enabled'] ) ) : ?>
								<span style="color:#008a20;">&#9679; <?php esc_html_e( 'Enabled', 'food-menu-pos-sync' ); ?></span><br />
								<span class="description"><?php echo esc_html( $this->schedule_label( isset( $values['schedule'] ) ? $values['schedule'] : 'disabled' ) ); ?></span>
							<?php elseif ( $configured ) : ?>
								<span><?php esc_html_e( 'Configured, not enabled', 'food-menu-pos-sync' ); ?></span>
							<?php else : ?>
								<span style="color:#787c82;"><?php esc_html_e( 'Not connected', 'food-menu-pos-sync' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $this->assignment_label( $values ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( $this->provider_url( $id ) ); ?>" class="button">
								<?php esc_html_e( 'Manage', 'food-menu-pos-sync' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<td><strong><?php esc_html_e( 'Clover', 'food-menu-pos-sync' ); ?></strong></td>
					<td><span style="color:#787c82;"><?php esc_html_e( 'Coming soon', 'food-menu-pos-sync' ); ?></span></td>
					<td>&mdash;</td>
					<td><button class="button" disabled="disabled"><?php esc_html_e( 'Manage', 'food-menu-pos-sync' ); ?></button></td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	private function provider_has_credentials( array $values ) {
		$skip = array( 'category_filter', 'enabled', 'branch_term_id', 'location_term_id', 'new_item_status', 'schedule' );
		foreach ( $values as $key => $value ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return true;
			}
		}
		return false;
	}

	private function schedule_label( $schedule ) {
		$labels = array(
			'disabled'   => __( 'Manual pull only', 'food-menu-pos-sync' ),
			'hourly'     => __( 'Hourly', 'food-menu-pos-sync' ),
			'twicedaily' => __( 'Twice daily', 'food-menu-pos-sync' ),
			'daily'      => __( 'Daily', 'food-menu-pos-sync' ),
		);
		return isset( $labels[ $schedule ] ) ? $labels[ $schedule ] : $schedule;
	}

	private function assignment_label( array $values ) {
		$parts = array();

		if ( ! empty( $values['branch_term_id'] ) ) {
			$term = get_term( (int) $values['branch_term_id'], Taxonomies::BRANCH );
			if ( $term && ! is_wp_error( $term ) ) {
				$parts[] = $term->name;
			}
		}

		if ( ! empty( $values['location_term_id'] ) ) {
			$term = get_term( (int) $values['location_term_id'], Taxonomies::LOCATION );
			if ( $term && ! is_wp_error( $term ) ) {
				$parts[] = $term->name;
			}
		}

		return $parts ? implode( ' / ', $parts ) : __( '— not set —', 'food-menu-pos-sync' );
	}

	private function render_provider_manage( $id ) {
		$provider  = self::get_provider_instance( $id );
		$settings  = self::get_settings();
		$values    = isset( $settings[ $id ] ) ? $settings[ $id ] : array();
		$last_sync = get_option( 'food_menu_pos_sync_last_sync_' . $id );
		?>
		<p><a href="<?php echo esc_url( $this->providers_list_url() ); ?>">&larr; <?php esc_html_e( 'Back to Providers', 'food-menu-pos-sync' ); ?></a></p>
		<h2><?php echo esc_html( $provider->get_label() ); ?></h2>

		<?php $this->render_last_sync( $last_sync ); ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
			<input type="hidden" name="action" value="food_menu_pos_sync_save_provider" />
			<input type="hidden" name="provider" value="<?php echo esc_attr( $id ); ?>" />

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enabled', 'food-menu-pos-sync' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $values['enabled'] ) ); ?> />
							<?php esc_html_e( 'Sync from this provider (both the schedule below and Pull Now require this)', 'food-menu-pos-sync' ); ?>
						</label>
					</td>
				</tr>

				<?php foreach ( $provider->get_settings_fields() as $field ) : ?>
					<tr>
						<th scope="row">
							<label for="food_menu_pos_sync_<?php echo esc_attr( $id . '_' . $field['key'] ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
						</th>
						<td>
							<?php $this->render_provider_field( $id, $field, isset( $values[ $field['key'] ] ) ? $values[ $field['key'] ] : '' ); ?>
							<?php if ( ! empty( $field['description'] ) ) : ?>
								<p class="description"><?php echo esc_html( $field['description'] ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>

				<tr>
					<th scope="row"><?php esc_html_e( 'Categories to sync', 'food-menu-pos-sync' ); ?></th>
					<td><?php $this->render_category_filter( $id, isset( $values['category_filter'] ) ? $values['category_filter'] : array() ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="food_menu_pos_sync_branch_term_id"><?php esc_html_e( 'Assign synced items to Branch', 'food-menu-pos-sync' ); ?></label></th>
					<td><?php $this->render_term_select( 'branch_term_id', Taxonomies::BRANCH, (int) $values['branch_term_id'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="food_menu_pos_sync_location_term_id"><?php esc_html_e( 'Assign synced items to Location', 'food-menu-pos-sync' ); ?></label></th>
					<td><?php $this->render_term_select( 'location_term_id', Taxonomies::LOCATION, (int) $values['location_term_id'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'New items default to', 'food-menu-pos-sync' ); ?></th>
					<td>
						<label><input type="radio" name="new_item_status" value="draft" <?php checked( $values['new_item_status'], 'draft' ); ?> /> <?php esc_html_e( 'Draft (review before it goes live)', 'food-menu-pos-sync' ); ?></label><br />
						<label><input type="radio" name="new_item_status" value="publish" <?php checked( $values['new_item_status'], 'publish' ); ?> /> <?php esc_html_e( 'Published (goes live immediately)', 'food-menu-pos-sync' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="food_menu_pos_sync_schedule"><?php esc_html_e( 'Automatic pull schedule', 'food-menu-pos-sync' ); ?></label></th>
					<td>
						<select name="schedule" id="food_menu_pos_sync_schedule">
							<option value="disabled" <?php selected( $values['schedule'], 'disabled' ); ?>><?php esc_html_e( 'Off — manual pull only', 'food-menu-pos-sync' ); ?></option>
							<option value="hourly" <?php selected( $values['schedule'], 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'food-menu-pos-sync' ); ?></option>
							<option value="twicedaily" <?php selected( $values['schedule'], 'twicedaily' ); ?>><?php esc_html_e( 'Twice daily', 'food-menu-pos-sync' ); ?></option>
							<option value="daily" <?php selected( $values['schedule'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'food-menu-pos-sync' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Relies on WP-Cron, which only fires on site visits unless you have real server cron configured — fine for most sites, but if traffic is very low, pulls may run later than scheduled.', 'food-menu-pos-sync' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save', 'food-menu-pos-sync' ) ); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:10px;">
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
			<input type="hidden" name="action" value="food_menu_pos_sync_test_connection" />
			<input type="hidden" name="provider" value="<?php echo esc_attr( $id ); ?>" />
			<?php submit_button( __( 'Test Connection', 'food-menu-pos-sync' ), 'secondary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
			<input type="hidden" name="action" value="food_menu_pos_sync_manual_sync" />
			<input type="hidden" name="provider" value="<?php echo esc_attr( $id ); ?>" />
			<?php submit_button( __( 'Pull from POS Now', 'food-menu-pos-sync' ), 'primary', 'submit', false ); ?>
		</form>
		<p class="description"><?php esc_html_e( 'Test Connection and Pull Now both work even while Enabled is unchecked — handy for setting things up before this provider goes live.', 'food-menu-pos-sync' ); ?></p>
		<?php
	}

	private function render_provider_field( $provider_id, array $field, $current_value ) {
		$id   = 'food_menu_pos_sync_' . $provider_id . '_' . $field['key'];
		$name = $field['key'];

		if ( 'select' === $field['type'] ) {
			?>
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
				<?php foreach ( (array) $field['options'] as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_value, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php
			return;
		}
		?>
		<input
			type="<?php echo esc_attr( $field['type'] ); ?>"
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
			class="regular-text"
			<?php if ( 'password' === $field['type'] ) : ?>
				value=""
				placeholder="<?php echo '' !== $current_value ? esc_attr__( '(unchanged)', 'food-menu-pos-sync' ) : ''; ?>"
				autocomplete="new-password"
			<?php else : ?>
				value="<?php echo esc_attr( $current_value ); ?>"
			<?php endif; ?>
		/>
		<?php
	}

	/**
	 * The Discover/Refresh action is a plain nonce-protected link (GET),
	 * not a nested <form> — this whole block lives inside the provider's
	 * settings <form>, and HTML doesn't allow forms inside forms. The
	 * checkboxes themselves ARE inside that form so they save together
	 * with the credentials.
	 */
	private function render_category_filter( $provider_id, array $saved_filter ) {
		$discovered = get_transient( self::discovered_categories_transient_key( $provider_id ) );

		$discover_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'   => 'food_menu_pos_sync_discover_categories',
					'provider' => $provider_id,
				),
				admin_url( 'admin-post.php' )
			),
			'food_menu_pos_sync_discover_' . $provider_id
		);
		?>
		<p>
			<a href="<?php echo esc_url( $discover_url ); ?>" class="button">
				<?php echo is_array( $discovered ) ? esc_html__( 'Refresh Categories', 'food-menu-pos-sync' ) : esc_html__( 'Discover Categories', 'food-menu-pos-sync' ); ?>
			</a>
			<span class="description"> <?php esc_html_e( 'Uses the credentials above as currently saved — save first if you just changed them.', 'food-menu-pos-sync' ); ?></span>
		</p>
		<?php if ( is_array( $discovered ) ) : ?>
			<?php if ( empty( $discovered ) ) : ?>
				<p class="description"><?php esc_html_e( 'No categories found in this account.', 'food-menu-pos-sync' ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Leave everything checked to sync all categories. Uncheck any you don\'t want brought into WordPress — a category added later in the POS stays excluded until you check it here.', 'food-menu-pos-sync' ); ?></p>
				<div style="max-height:200px;overflow-y:auto;border:1px solid #dcdcde;padding:8px;max-width:400px;">
					<?php foreach ( $discovered as $cat ) : ?>
						<?php $checked = empty( $saved_filter ) || in_array( $cat['id'], $saved_filter, true ); ?>
						<label style="display:block;">
							<input type="checkbox" name="category_filter[]" value="<?php echo esc_attr( $cat['id'] ); ?>" <?php checked( $checked ); ?> />
							<?php echo esc_html( $cat['name'] ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	private function render_term_select( $field_name, $taxonomy, $selected ) {
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
		?>
		<select name="<?php echo esc_attr( $field_name ); ?>" id="food_menu_pos_sync_<?php echo esc_attr( $field_name ); ?>">
			<option value="0"><?php esc_html_e( '— None —', 'food-menu-pos-sync' ); ?></option>
			<?php foreach ( $terms as $term ) : ?>
				<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( $selected, $term->term_id ); ?>>
					<?php echo esc_html( $term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php if ( empty( $terms ) ) : ?>
			<p class="description"><?php esc_html_e( 'No terms yet — add one under Food Menu first.', 'food-menu-pos-sync' ); ?></p>
		<?php endif; ?>
		<?php
	}

	private function render_last_sync( $last_sync ) {
		if ( empty( $last_sync['time'] ) ) {
			return;
		}

		$results = isset( $last_sync['results'] ) ? $last_sync['results'] : array();
		?>
		<div class="notice notice-info inline">
			<p>
				<?php
				printf(
					/* translators: 1: date/time, 2: created count, 3: updated count, 4: excluded-by-filter count, 5: skipped count */
					esc_html__( 'Last pull: %1$s — %2$d created, %3$d updated, %4$d excluded by category filter, %5$d skipped.', 'food-menu-pos-sync' ),
					esc_html( get_date_from_gmt( $last_sync['time'], 'Y-m-d H:i:s' ) ),
					isset( $results['created'] ) ? (int) $results['created'] : 0,
					isset( $results['updated'] ) ? (int) $results['updated'] : 0,
					isset( $results['excluded'] ) ? (int) $results['excluded'] : 0,
					isset( $results['skipped'] ) ? count( $results['skipped'] ) : 0
				);
				?>
			</p>
			<?php if ( ! empty( $results['skipped'] ) ) : ?>
				<ul style="list-style:disc;margin-left:20px;">
					<?php foreach ( $results['skipped'] as $skip ) : ?>
						<li><?php echo esc_html( $skip['name'] . ' — ' . $skip['reason'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_save_provider() {
		$this->verify_request();

		$provider_id       = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$provider_instance = self::get_provider_instance( $provider_id );

		if ( ! $provider_instance ) {
			$this->redirect_with_notice( 'no_provider' );
		}

		$settings        = self::get_settings();
		$existing_values = isset( $settings[ $provider_id ] ) ? $settings[ $provider_id ] : array();
		$clean           = array();

		$clean['enabled'] = ! empty( $_POST['enabled'] );

		foreach ( $provider_instance->get_settings_fields() as $field ) {
			$key           = $field['key'];
			$submitted_val = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';

			// Password fields render blank on purpose (never re-echoed);
			// leaving one blank on save means "keep the existing value".
			if ( 'password' === $field['type'] && '' === $submitted_val ) {
				$clean[ $key ] = isset( $existing_values[ $key ] ) ? $existing_values[ $key ] : '';
			} else {
				$clean[ $key ] = $submitted_val;
			}
		}

		$clean['branch_term_id']   = isset( $_POST['branch_term_id'] ) ? absint( $_POST['branch_term_id'] ) : 0;
		$clean['location_term_id'] = isset( $_POST['location_term_id'] ) ? absint( $_POST['location_term_id'] ) : 0;
		$clean['new_item_status']  = ( isset( $_POST['new_item_status'] ) && 'publish' === $_POST['new_item_status'] ) ? 'publish' : 'draft';
		$clean['schedule']         = isset( $_POST['schedule'] ) ? sanitize_key( wp_unslash( $_POST['schedule'] ) ) : 'disabled';

		// Category checkboxes only exist in the form once "Discover
		// Categories" has been run at least once for this provider — if
		// it never has, leave whatever's already saved untouched.
		if ( is_array( get_transient( self::discovered_categories_transient_key( $provider_id ) ) ) ) {
			$submitted_ids = isset( $_POST['category_filter'] ) && is_array( $_POST['category_filter'] )
				? array_map( 'sanitize_text_field', wp_unslash( $_POST['category_filter'] ) )
				: array();
			$clean['category_filter'] = $submitted_ids;
		} else {
			$clean['category_filter'] = isset( $existing_values['category_filter'] ) ? $existing_values['category_filter'] : array();
		}

		$settings[ $provider_id ] = $clean;
		update_option( self::OPTION_KEY, $settings );

		Scheduler::reschedule_provider( $provider_id, $clean['schedule'] );

		$this->redirect_with_notice( 'settings_saved', '', $provider_id );
	}

	public function handle_test_connection() {
		$this->verify_request();

		$provider_id = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$provider    = self::get_provider_instance( $provider_id );

		if ( ! $provider ) {
			$this->redirect_with_notice( 'no_provider' );
		}

		$settings          = self::get_settings();
		$provider_settings = isset( $settings[ $provider_id ] ) ? $settings[ $provider_id ] : array();
		$result            = $provider->test_connection( $provider_settings );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( 'connection_failed', $result->get_error_message(), $provider_id );
		}

		$this->redirect_with_notice( 'connection_ok', '', $provider_id );
	}

	public function handle_manual_sync() {
		$this->verify_request();

		$provider_id = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$provider    = self::get_provider_instance( $provider_id );

		if ( ! $provider ) {
			$this->redirect_with_notice( 'no_provider' );
		}

		$settings          = self::get_settings();
		$provider_settings = isset( $settings[ $provider_id ] ) ? $settings[ $provider_id ] : array();

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$sync   = new Sync();
		$result = $sync->run( $provider, $provider_settings );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( 'sync_failed', $result->get_error_message(), $provider_id );
		}

		$this->redirect_with_notice( 'sync_done', '', $provider_id );
	}

	/**
	 * GET-based (nonce-protected link, not a POST form) since it's
	 * triggered from inside the provider's settings <form>.
	 */
	public function handle_discover_categories() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'food-menu-pos-sync' ) );
		}

		$provider_id = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : '';

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'food_menu_pos_sync_discover_' . $provider_id ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'food-menu-pos-sync' ) );
		}

		$provider = self::get_provider_instance( $provider_id );
		if ( ! $provider ) {
			$this->redirect_with_notice( 'no_provider' );
		}

		$settings          = self::get_settings();
		$provider_settings = isset( $settings[ $provider_id ] ) ? $settings[ $provider_id ] : array();

		$categories = $provider->discover_categories( $provider_settings );

		if ( is_wp_error( $categories ) ) {
			$this->redirect_with_notice( 'discover_failed', $categories->get_error_message(), $provider_id );
		}

		set_transient( self::discovered_categories_transient_key( $provider_id ), $categories, HOUR_IN_SECONDS );

		$this->redirect_with_notice( 'discover_done', '', $provider_id );
	}

	private function verify_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'food-menu-pos-sync' ) );
		}

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed. Please go back and try again.', 'food-menu-pos-sync' ) );
		}
	}

	private function redirect_with_notice( $code, $message = '', $provider_id = '' ) {
		set_transient(
			'food_menu_pos_sync_notice_' . get_current_user_id(),
			array(
				'code'    => $code,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);

		$args = array(
			'provider' => $provider_id,
		);
		if ( '' === $provider_id ) {
			unset( $args['provider'] );
		}

		wp_safe_redirect( $this->settings_url( $args ) );
		exit;
	}

	public function show_admin_notices() {
		$notice = get_transient( 'food_menu_pos_sync_notice_' . get_current_user_id() );
		if ( ! $notice ) {
			return;
		}
		delete_transient( 'food_menu_pos_sync_notice_' . get_current_user_id() );

		$map = array(
			'settings_saved'    => array( 'success', __( 'Settings saved.', 'food-menu-pos-sync' ) ),
			'connection_ok'     => array( 'success', __( 'Connection successful.', 'food-menu-pos-sync' ) ),
			'connection_failed' => array( 'error', __( 'Connection failed: ', 'food-menu-pos-sync' ) . $notice['message'] ),
			'sync_done'         => array( 'success', __( 'Pull complete — see results above.', 'food-menu-pos-sync' ) ),
			'sync_failed'       => array( 'error', __( 'Pull failed: ', 'food-menu-pos-sync' ) . $notice['message'] ),
			'no_provider'       => array( 'error', __( 'Unknown POS provider.', 'food-menu-pos-sync' ) ),
			'discover_done'     => array( 'success', __( 'Categories loaded — choose which ones to sync below, then Save.', 'food-menu-pos-sync' ) ),
			'discover_failed'   => array( 'error', __( 'Could not load categories: ', 'food-menu-pos-sync' ) . $notice['message'] ),
		);

		if ( ! isset( $map[ $notice['code'] ] ) ) {
			return;
		}

		list( $type, $text ) = $map[ $notice['code'] ];
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $text )
		);
	}
}
