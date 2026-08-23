<?php
namespace FoodMenu\Core\Admin;

use FoodMenu\Core\PostTypes;
use FoodMenu\Core\Taxonomies;
use FoodMenu\Core\MetaFields;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI: meta boxes for price/variations, saving, and required-field
 * handling for the Food Menu Item post type.
 */
class Admin {

	public function init() {
		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_block_editor' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . PostTypes::POST_TYPE, array( $this, 'save_meta_boxes' ), 10, 2 );
		add_action( 'save_post_' . PostTypes::POST_TYPE, array( $this, 'enforce_required_fields' ), 20, 2 );
		add_filter( 'redirect_post_location', array( $this, 'append_notice_query_var' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
		add_filter( 'enter_title_here', array( $this, 'filter_title_placeholder' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( $this, 'add_plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Informational only — the CPT, admin UI, and REST API all work with
	 * no Elementor at all, and the Dynamic Tags work with free Elementor.
	 * Only the Loop Grid/Carousel layout widgets this menu is built with
	 * are Elementor Pro-gated, so that's the specific thing to disclose
	 * on the Plugins list page.
	 */
	public function add_plugin_row_meta( $links, $plugin_file ) {
		if ( FOOD_MENU_PLUGIN_BASENAME !== $plugin_file ) {
			return $links;
		}

		$links[] = '<span style="color:#d63638;">'
			. esc_html__( 'Requires Elementor Pro for Loop Grid / Loop Carousel layouts.', 'food-menu' )
			. '</span>';

		return $links;
	}

	/**
	 * This post type has no block content (no 'editor' support) — it's a
	 * structured data form, not a writing surface. The classic screen gives
	 * predictable, admin-controlled field ordering via meta box priorities;
	 * Gutenberg's native Excerpt panel is a separate React component that
	 * can't be reliably reordered or forced open from PHP.
	 */
	public function disable_block_editor( $use_block_editor, $post_type ) {
		if ( PostTypes::POST_TYPE === $post_type ) {
			return false;
		}
		return $use_block_editor;
	}

	public function filter_title_placeholder( $placeholder, $post ) {
		if ( $post && PostTypes::POST_TYPE === $post->post_type ) {
			return __( 'Item name', 'food-menu' );
		}
		return $placeholder;
	}

	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || PostTypes::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'food-menu-admin', FOOD_MENU_PLUGIN_URL . 'admin/css/admin.css', array(), FOOD_MENU_VERSION );
		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'food-menu-admin', FOOD_MENU_PLUGIN_URL . 'admin/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), FOOD_MENU_VERSION, true );
	}

	public function add_meta_boxes() {
		// Replace the native Excerpt box with our own so the field is
		// always visible (not a collapsible panel) and sits at the top.
		remove_meta_box( 'postexcerpt', PostTypes::POST_TYPE, 'normal' );

		add_meta_box(
			'fmp_price',
			__( 'Price', 'food-menu' ),
			array( $this, 'render_price_meta_box' ),
			PostTypes::POST_TYPE,
			'side',
			'high'
		);

		add_meta_box(
			'fmp_video',
			__( 'Item Video (optional)', 'food-menu' ),
			array( $this, 'render_video_meta_box' ),
			PostTypes::POST_TYPE,
			'side',
			'default'
		);

		add_meta_box(
			'fmp_description',
			__( 'Item Description', 'food-menu' ),
			array( $this, 'render_description_meta_box' ),
			PostTypes::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'fmp_variations',
			__( 'Variations (optional)', 'food-menu' ),
			array( $this, 'render_variations_meta_box' ),
			PostTypes::POST_TYPE,
			'normal',
			'default'
		);
	}

	public function render_description_meta_box( $post ) {
		?>
		<label class="screen-reader-text" for="excerpt"><?php esc_html_e( 'Item Description', 'food-menu' ); ?></label>
		<textarea
			rows="4"
			id="excerpt"
			name="excerpt"
			class="widefat"
			placeholder="<?php esc_attr_e( 'A short description shown wherever Item Description is used in Elementor.', 'food-menu' ); ?>"
		><?php echo esc_textarea( $post->post_excerpt ); ?></textarea>
		<?php
	}

	public function render_price_meta_box( $post ) {
		wp_nonce_field( MetaFields::NONCE_ACTION, MetaFields::NONCE_NAME );
		$price = get_post_meta( $post->ID, MetaFields::PRICE, true );
		?>
		<p>
			<label for="fmp_price">
				<strong><?php esc_html_e( 'Item Price', 'food-menu' ); ?></strong>
				<span class="fmp-required">*</span>
			</label>
		</p>
		<input
			type="text"
			id="fmp_price"
			name="fmp_price"
			class="widefat"
			value="<?php echo esc_attr( $price ); ?>"
			placeholder="<?php esc_attr_e( 'e.g. $12, MKT, Starting at $10', 'food-menu' ); ?>"
		/>
		<p class="description">
			<?php esc_html_e( 'Stored as text, not a number, so values like "MKT" or "2 for $8" work fine.', 'food-menu' ); ?>
		</p>
		<?php
	}

	public function render_video_meta_box( $post ) {
		$video_url  = get_post_meta( $post->ID, MetaFields::VIDEO_URL, true );
		$poster_id  = absint( get_post_meta( $post->ID, MetaFields::VIDEO_POSTER, true ) );
		$poster_url = $poster_id ? wp_get_attachment_image_url( $poster_id, 'thumbnail' ) : '';
		?>
		<p>
			<label for="fmp_video_url"><strong><?php esc_html_e( 'Video URL', 'food-menu' ); ?></strong></label>
		</p>
		<input type="url" id="fmp_video_url" name="fmp_video_url" class="widefat" value="<?php echo esc_attr( $video_url ); ?>" placeholder="https://example.com/item.mp4" />
		<p class="description"><?php esc_html_e( 'Use an external MP4/WebM URL or choose a video from the Media Library.', 'food-menu' ); ?></p>
		<p>
			<button type="button" class="button" id="fmp-select-video"><?php esc_html_e( 'Choose Video', 'food-menu' ); ?></button>
			<button type="button" class="button-link fmp-remove-media" id="fmp-remove-video" <?php disabled( empty( $video_url ) ); ?>><?php esc_html_e( 'Remove', 'food-menu' ); ?></button>
		</p>
		<hr />
		<p><strong><?php esc_html_e( 'Video Poster', 'food-menu' ); ?></strong></p>
		<input type="hidden" id="fmp_video_poster_id" name="fmp_video_poster_id" value="<?php echo esc_attr( $poster_id ); ?>" />
		<div id="fmp-video-poster-preview"><?php echo $poster_url ? '<img src="' . esc_url( $poster_url ) . '" alt="" />' : ''; ?></div>
		<p>
			<button type="button" class="button" id="fmp-select-video-poster"><?php esc_html_e( 'Choose Poster', 'food-menu' ); ?></button>
			<button type="button" class="button-link fmp-remove-media" id="fmp-remove-video-poster" <?php disabled( ! $poster_id ); ?>><?php esc_html_e( 'Remove', 'food-menu' ); ?></button>
		</p>
		<p class="description"><?php esc_html_e( 'Defaults to the featured image when no poster is selected.', 'food-menu' ); ?></p>
		<?php
	}

	public function render_variations_meta_box( $post ) {
		$variations = get_post_meta( $post->ID, MetaFields::VARIATIONS, true );
		if ( ! is_array( $variations ) || empty( $variations ) ) {
			$variations = array( array( 'name' => '', 'price' => '' ) );
		}
		?>
		<p class="description">
			<?php esc_html_e( 'Add sizes or options for this item, e.g. Small - $6, Large - $10. Drag the handle to reorder.', 'food-menu' ); ?>
		</p>
		<table class="fmp-variations-table widefat">
			<thead>
				<tr>
					<th class="fmp-drag-handle-col"></th>
					<th><?php esc_html_e( 'Variation Name', 'food-menu' ); ?></th>
					<th><?php esc_html_e( 'Variation Price', 'food-menu' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody id="fmp-variations-rows">
				<?php foreach ( $variations as $row ) : ?>
					<?php $this->render_variation_row( $row ); ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<button type="button" class="button" id="fmp-add-variation"><?php esc_html_e( '+ Add Variation', 'food-menu' ); ?></button>
		</p>

		<script type="text/template" id="fmp-variation-row-template">
			<?php $this->render_variation_row( array( 'name' => '', 'price' => '' ) ); ?>
		</script>
		<?php
	}

	private function render_variation_row( $row ) {
		$name  = isset( $row['name'] ) ? $row['name'] : '';
		$price = isset( $row['price'] ) ? $row['price'] : '';
		?>
		<tr class="fmp-variation-row">
			<td class="fmp-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'food-menu' ); ?>">&#9776;</td>
			<td>
				<input type="text" class="widefat" name="fmp_variations[name][]" value="<?php echo esc_attr( $name ); ?>" placeholder="<?php esc_attr_e( 'e.g. Large', 'food-menu' ); ?>" />
			</td>
			<td>
				<input type="text" class="widefat" name="fmp_variations[price][]" value="<?php echo esc_attr( $price ); ?>" placeholder="<?php esc_attr_e( 'e.g. $10', 'food-menu' ); ?>" />
			</td>
			<td>
				<button type="button" class="button-link fmp-remove-variation" aria-label="<?php esc_attr_e( 'Remove variation', 'food-menu' ); ?>">&times;</button>
			</td>
		</tr>
		<?php
	}

	private function has_valid_nonce() {
		return isset( $_POST[ MetaFields::NONCE_NAME ] )
			&& wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST[ MetaFields::NONCE_NAME ] ) ),
				MetaFields::NONCE_ACTION
			);
	}

	public function save_meta_boxes( $post_id, $post ) {
		if ( ! $this->has_valid_nonce() ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['fmp_price'] ) ) {
			$price = MetaFields::sanitize_price( $_POST['fmp_price'] );
			if ( '' === $price ) {
				delete_post_meta( $post_id, MetaFields::PRICE );
			} else {
				update_post_meta( $post_id, MetaFields::PRICE, $price );
			}
		}

		$video_url = isset( $_POST['fmp_video_url'] ) ? MetaFields::sanitize_video_url( $_POST['fmp_video_url'] ) : '';
		if ( '' === $video_url ) {
			delete_post_meta( $post_id, MetaFields::VIDEO_URL );
		} else {
			update_post_meta( $post_id, MetaFields::VIDEO_URL, $video_url );
		}

		$poster_id = isset( $_POST['fmp_video_poster_id'] ) ? absint( $_POST['fmp_video_poster_id'] ) : 0;
		if ( $poster_id && wp_attachment_is_image( $poster_id ) ) {
			update_post_meta( $post_id, MetaFields::VIDEO_POSTER, $poster_id );
		} else {
			delete_post_meta( $post_id, MetaFields::VIDEO_POSTER );
		}

		$names  = isset( $_POST['fmp_variations']['name'] ) ? (array) $_POST['fmp_variations']['name'] : array();
		$prices = isset( $_POST['fmp_variations']['price'] ) ? (array) $_POST['fmp_variations']['price'] : array();

		$rows = array();
		foreach ( $names as $index => $name ) {
			$rows[] = array(
				'name'  => $name,
				'price' => isset( $prices[ $index ] ) ? $prices[ $index ] : '',
			);
		}
		$variations = MetaFields::sanitize_variations( $rows );

		if ( empty( $variations ) ) {
			delete_post_meta( $post_id, MetaFields::VARIATIONS );
		} else {
			update_post_meta( $post_id, MetaFields::VARIATIONS, $variations );
		}
	}

	/**
	 * Downgrades a menu item to Draft instead of blocking the save outright
	 * whenever a required field is missing at publish time. Nothing the
	 * user typed is lost — they can fix the fields and publish again.
	 *
	 * Only runs for classic edit-screen submissions (our nonce is present),
	 * so programmatic writes such as POS Sync are never blocked.
	 */
	public function enforce_required_fields( $post_id, $post ) {
		if ( ! $this->has_valid_nonce() ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$wants_publish = ( isset( $_POST['post_status'] ) && 'publish' === $_POST['post_status'] )
			|| 'publish' === $post->post_status;

		if ( ! $wants_publish ) {
			return;
		}

		$title = isset( $_POST['post_title'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) ) : '';
		$price = isset( $_POST['fmp_price'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['fmp_price'] ) ) ) : '';

		$missing = array();

		if ( '' === $title ) {
			$missing[] = __( 'Item Name', 'food-menu' );
		}
		if ( '' === $price ) {
			$missing[] = __( 'Item Price', 'food-menu' );
		}

		// Label is deliberately not required — it's a promotional tag
		// (Specials, Featured, ...), not every item needs one.
		$taxonomies = array(
			Taxonomies::BRANCH   => __( 'Branch', 'food-menu' ),
			Taxonomies::LOCATION => __( 'Location', 'food-menu' ),
			Taxonomies::MENU     => __( 'Menu', 'food-menu' ),
		);

		foreach ( $taxonomies as $taxonomy => $label ) {
			$terms = isset( $_POST['tax_input'][ $taxonomy ] ) ? (array) $_POST['tax_input'][ $taxonomy ] : array();
			$terms = array_filter(
				$terms,
				function ( $term ) {
					return '' !== $term && '0' !== $term;
				}
			);
			if ( empty( $terms ) ) {
				$missing[] = $label;
			}
		}

		if ( empty( $missing ) ) {
			return;
		}

		remove_action( 'save_post_' . PostTypes::POST_TYPE, array( $this, 'enforce_required_fields' ), 20 );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);
		add_action( 'save_post_' . PostTypes::POST_TYPE, array( $this, 'enforce_required_fields' ), 20, 2 );

		set_transient( 'fmp_missing_fields_' . get_current_user_id(), $missing, MINUTE_IN_SECONDS );
	}

	public function append_notice_query_var( $location, $post_id ) {
		if ( PostTypes::POST_TYPE !== get_post_type( $post_id ) ) {
			return $location;
		}

		if ( get_transient( 'fmp_missing_fields_' . get_current_user_id() ) ) {
			$location = add_query_arg( 'fmp_missing_fields', '1', $location );
		}

		return $location;
	}

	public function show_admin_notices() {
		if ( empty( $_GET['fmp_missing_fields'] ) ) {
			return;
		}

		$missing = get_transient( 'fmp_missing_fields_' . get_current_user_id() );
		delete_transient( 'fmp_missing_fields_' . get_current_user_id() );

		if ( empty( $missing ) ) {
			return;
		}
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %s: bolded, comma-separated list of missing required fields */
					esc_html__( 'This menu item was saved as a draft because the following required fields are missing: %s. Fill them in and publish again.', 'food-menu' ),
					'<strong>' . esc_html( implode( ', ', $missing ) ) . '</strong>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
