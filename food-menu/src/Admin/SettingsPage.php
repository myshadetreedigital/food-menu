<?php
namespace FoodMenu\Core\Admin;

use FoodMenu\Core\PostTypes;
use FoodMenu\Core\Support\Hooks;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared "Food Menu" settings page. Addons add a tab via the
 * Hooks::SETTINGS_TABS filter instead of registering their own top-level
 * admin menu — POS Sync is the first consumer. Each tab owns its own form
 * handling (admin-post.php actions, nonces, etc.); this page only renders
 * the tab shell and dispatches to the active tab's render callback.
 */
class SettingsPage {

	const MENU_SLUG = 'food-menu-settings';

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
	}

	public function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=' . PostTypes::POST_TYPE,
			__( 'Settings', 'food-menu' ),
			__( 'Settings', 'food-menu' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * @return array List of ['slug' => string, 'label' => string, 'render' => callable].
	 */
	private function get_tabs() {
		$tabs = apply_filters( Hooks::SETTINGS_TABS, array() );
		return is_array( $tabs ) ? $tabs : array();
	}

	private function tab_url( $slug ) {
		return add_query_arg(
			array(
				'post_type' => PostTypes::POST_TYPE,
				'page'      => self::MENU_SLUG,
				'tab'       => $slug,
			),
			admin_url( 'edit.php' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = $this->get_tabs();

		$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active    = '';

		foreach ( $tabs as $tab ) {
			if ( isset( $tab['slug'] ) && $tab['slug'] === $requested ) {
				$active = $requested;
				break;
			}
		}
		if ( '' === $active && ! empty( $tabs ) ) {
			$active = $tabs[0]['slug'];
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Food Menu Settings', 'food-menu' ); ?></h1>

			<?php if ( empty( $tabs ) ) : ?>
				<p><?php esc_html_e( 'No addons with settings are active yet.', 'food-menu' ); ?></p>
			<?php else : ?>
				<h2 class="nav-tab-wrapper">
					<?php foreach ( $tabs as $tab ) : ?>
						<a
							href="<?php echo esc_url( $this->tab_url( $tab['slug'] ) ); ?>"
							class="nav-tab <?php echo $tab['slug'] === $active ? 'nav-tab-active' : ''; ?>"
						>
							<?php echo esc_html( $tab['label'] ); ?>
						</a>
					<?php endforeach; ?>
				</h2>

				<?php
				foreach ( $tabs as $tab ) {
					if ( $tab['slug'] === $active && is_callable( $tab['render'] ) ) {
						call_user_func( $tab['render'] );
						break;
					}
				}
				?>
			<?php endif; ?>
		</div>
		<?php
	}
}
