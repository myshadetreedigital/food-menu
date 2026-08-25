<?php
namespace FoodMenu\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Branch, Location, Menu, and Label taxonomies for Food
 * Menu Items.
 */
class Taxonomies {

	const BRANCH   = 'food_menu_branch';
	const LOCATION = 'food_menu_location';
	const MENU     = 'food_menu_menu';
	// Slug stays food_menu_category — renaming it would orphan every term
	// and term relationship already created under it on live sites. Only
	// the display label changed (Category read as a second, confusingly
	// similar taxonomy next to Menu); "Label" is a better fit for what
	// this taxonomy actually holds — promotional tags, not menu sections.
	const LABEL = 'food_menu_category';
	const TERM_ADDRESS = 'fmp_term_address';
	const TERM_IMAGE   = 'fmp_term_image_id';
	const TERM_VIDEO   = 'fmp_term_video_url';
	const TERM_POSTER  = 'fmp_term_video_poster_id';
	const LOCATION_BRANCH = 'fmp_location_branch_id';
	const MENU_LOCATION   = 'fmp_menu_location_id';

	private $normalizing_single_term = false;

	/**
	 * Preserve commas and other punctuation in a single incoming term value.
	 * WordPress may interpret a comma-delimited string as multiple terms.
	 */
	public static function single_term_value( $value ) {
		$value = sanitize_text_field( wp_unslash( (string) $value ) );
		return '' === $value ? array() : array( $value );
	}

	public static function sanitize_address( $value ) {
		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	public static function sanitize_video_url( $value ) {
		$url  = esc_url_raw( wp_unslash( (string) $value ), array( 'http', 'https' ) );
		$path = strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		return $url && preg_match( '/\.(mp4|webm)$/', $path ) ? $url : '';
	}

	public function init() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'set_object_terms', array( $this, 'enforce_single_term_relationship' ), 10, 6 );
	}

	public function register() {
		foreach ( array( self::BRANCH, self::LOCATION, self::MENU ) as $taxonomy ) {
			add_action( $taxonomy . '_add_form_fields', array( $this, 'render_term_fields' ) );
			add_action( $taxonomy . '_edit_form_fields', array( $this, 'render_term_fields' ), 10, 2 );
			add_action( 'created_' . $taxonomy, array( $this, 'save_term_fields' ) );
			add_action( 'edited_' . $taxonomy, array( $this, 'save_term_fields' ) );
		}
		$this->register_taxonomy(
			self::BRANCH,
			__( 'Branch', 'food-menu' ),
			__( 'Branches', 'food-menu' ),
			__( 'The franchise or business unit this item belongs to, e.g. Main, Corporate, East Region.', 'food-menu' ),
			true
		);

		$this->register_taxonomy(
			self::LOCATION,
			__( 'Location', 'food-menu' ),
			__( 'Locations', 'food-menu' ),
			__( 'The physical location or outlet this item is served at, e.g. Downtown, Atlanta, Food Truck.', 'food-menu' ),
			true
		);

		$this->register_taxonomy(
			self::MENU,
			__( 'Menu', 'food-menu' ),
			__( 'Menus', 'food-menu' ),
			__( 'The menu or section this item appears under, e.g. Lunch, Brunch, Apps, Drinks.', 'food-menu' ),
			true
		);

		$this->register_taxonomy(
			self::LABEL,
			__( 'Label', 'food-menu' ),
			__( 'Labels', 'food-menu' ),
			__( 'A promotional or merchandising tag for this item, e.g. Specials, Featured, Popular, New.', 'food-menu' )
		);

		foreach ( array( self::BRANCH, self::LOCATION, self::MENU ) as $taxonomy ) {
			register_term_meta( $taxonomy, self::TERM_IMAGE, array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint' ) );
			register_term_meta( $taxonomy, self::TERM_VIDEO, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => array( __CLASS__, 'sanitize_video_url' ) ) );
			register_term_meta( $taxonomy, self::TERM_POSTER, array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint' ) );
		}
		register_term_meta( self::LOCATION, self::LOCATION_BRANCH, array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint' ) );
		register_term_meta( self::MENU, self::MENU_LOCATION, array( 'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'absint' ) );
		register_term_meta( self::LOCATION, self::TERM_ADDRESS, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => array( __CLASS__, 'sanitize_address' ) ) );
	}

	private function register_taxonomy( $taxonomy, $singular, $plural, $description, $single_term = false ) {
		$labels = array(
			'name'          => $plural,
			'singular_name' => $singular,
			'menu_name'     => $plural,
			/* translators: %s: taxonomy plural label */
			'search_items'  => sprintf( __( 'Search %s', 'food-menu' ), $plural ),
			/* translators: %s: taxonomy plural label */
			'all_items'     => sprintf( __( 'All %s', 'food-menu' ), $plural ),
			/* translators: %s: taxonomy singular label */
			'edit_item'     => sprintf( __( 'Edit %s', 'food-menu' ), $singular ),
			/* translators: %s: taxonomy singular label */
			'update_item'   => sprintf( __( 'Update %s', 'food-menu' ), $singular ),
			/* translators: %s: taxonomy singular label */
			'add_new_item'  => sprintf( __( 'Add New %s', 'food-menu' ), $singular ),
			/* translators: %s: taxonomy singular label */
			'new_item_name' => sprintf( __( 'New %s Name', 'food-menu' ), $singular ),
			/* translators: %s: taxonomy plural label, lowercased */
			'not_found'     => sprintf( __( 'No %s found.', 'food-menu' ), strtolower( $plural ) ),
		);

		$args = array(
			'labels'             => $labels,
			'description'        => $description,
			'public'             => true,
			'publicly_queryable' => true,
			'hierarchical'       => true,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'show_in_nav_menus'  => true,
			'show_in_rest'       => true,
			'rest_base'          => $taxonomy,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => str_replace( '_', '-', $taxonomy ) ),
		);

		if ( $single_term ) {
			$args['meta_box_cb'] = array( $this, 'render_single_term_metabox' );
		}

		register_taxonomy(
			$taxonomy,
			array( PostTypes::POST_TYPE ),
			$args
		);
	}

	public function render_term_fields( $term = null, $taxonomy = null ) {
		if ( is_string( $term ) && null === $taxonomy ) {
			$taxonomy = $term;
			$term     = null;
		}
		if ( null === $taxonomy ) {
			$screen   = get_current_screen();
			$taxonomy = $screen ? $screen->taxonomy : null;
		}
		$term_id    = $term instanceof \WP_Term ? $term->term_id : 0;
		$taxonomy   = $term instanceof \WP_Term ? $term->taxonomy : $taxonomy;
		$image_id   = $term_id ? absint( get_term_meta( $term_id, self::TERM_IMAGE, true ) ) : 0;
		$poster_id  = $term_id ? absint( get_term_meta( $term_id, self::TERM_POSTER, true ) ) : 0;
		$video_url  = $term_id ? get_term_meta( $term_id, self::TERM_VIDEO, true ) : '';
		$address    = ( self::LOCATION === $taxonomy && $term_id ) ? get_term_meta( $term_id, self::TERM_ADDRESS, true ) : '';
		$parent_id  = self::LOCATION === $taxonomy && $term_id ? absint( get_term_meta( $term_id, self::LOCATION_BRANCH, true ) ) : ( self::MENU === $taxonomy && $term_id ? absint( get_term_meta( $term_id, self::MENU_LOCATION, true ) ) : 0 );
		$image_url  = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
		$poster_url = $poster_id ? wp_get_attachment_image_url( $poster_id, 'thumbnail' ) : '';
		$is_edit    = (bool) $term_id;
		?>
		<?php if ( $is_edit ) : ?><tr class="form-field"><th scope="row"><label><?php esc_html_e( 'Media', 'food-menu' ); ?></label></th><td><?php else : ?><div class="form-field"><label><?php esc_html_e( 'Media', 'food-menu' ); ?></label><p class="description"><?php esc_html_e( 'Optional image, MP4/WebM video, and video poster.', 'food-menu' ); ?></p><?php endif; ?>
			<?php if ( self::LOCATION === $taxonomy ) : ?>
				<p><label for="fmp_term_parent_id"><?php esc_html_e( 'Branch', 'food-menu' ); ?></label><?php $this->render_parent_dropdown( self::BRANCH, $parent_id ); ?></p>
			<p><label for="fmp_term_address"><?php esc_html_e( 'Address', 'food-menu' ); ?></label><input type="text" name="fmp_term_address" id="fmp_term_address" class="widefat" value="<?php echo esc_attr( $address ); ?>" /></p>
			<?php endif; ?>
				<?php if ( self::MENU === $taxonomy ) : ?><p><label for="fmp_term_parent_id"><?php esc_html_e( 'Location', 'food-menu' ); ?></label><?php $this->render_parent_dropdown( self::LOCATION, $parent_id ); ?></p><?php endif; ?>
			<p><label for="fmp_term_image_id"><?php esc_html_e( 'Image', 'food-menu' ); ?></label><input type="hidden" name="fmp_term_image_id" id="fmp_term_image_id" value="<?php echo esc_attr( $image_id ); ?>" /><span id="fmp-term-image-preview"><?php echo $image_url ? '<img src="' . esc_url( $image_url ) . '" alt="" />' : ''; ?></span> <button type="button" class="button fmp-term-select-image"><?php esc_html_e( 'Choose Image', 'food-menu' ); ?></button> <button type="button" class="button-link fmp-term-remove-image" <?php disabled( ! $image_id ); ?>><?php esc_html_e( 'Remove', 'food-menu' ); ?></button></p>
			<p><label for="fmp_term_video_url"><?php esc_html_e( 'Video URL', 'food-menu' ); ?></label><input type="url" name="fmp_term_video_url" id="fmp_term_video_url" class="widefat" value="<?php echo esc_attr( $video_url ); ?>" placeholder="https://example.com/video.mp4" /><button type="button" class="button fmp-term-select-video"><?php esc_html_e( 'Choose MP4/WebM Video', 'food-menu' ); ?></button> <button type="button" class="button-link fmp-term-remove-video" <?php disabled( empty( $video_url ) ); ?>><?php esc_html_e( 'Remove', 'food-menu' ); ?></button></p>
			<p><label for="fmp_term_video_poster_id"><?php esc_html_e( 'Video Poster', 'food-menu' ); ?></label><input type="hidden" name="fmp_term_video_poster_id" id="fmp_term_video_poster_id" value="<?php echo esc_attr( $poster_id ); ?>" /><span id="fmp-term-poster-preview"><?php echo $poster_url ? '<img src="' . esc_url( $poster_url ) . '" alt="" />' : ''; ?></span> <button type="button" class="button fmp-term-select-poster"><?php esc_html_e( 'Choose Poster', 'food-menu' ); ?></button> <button type="button" class="button-link fmp-term-remove-poster" <?php disabled( ! $poster_id ); ?>><?php esc_html_e( 'Remove', 'food-menu' ); ?></button></p>
		<?php if ( $is_edit ) : ?></td></tr><?php else : ?></div><?php endif; ?>
		<?php
	}

	public function save_term_fields( $term_id ) {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$image_id  = isset( $_POST['fmp_term_image_id'] ) ? absint( $_POST['fmp_term_image_id'] ) : 0;
		$poster_id = isset( $_POST['fmp_term_video_poster_id'] ) ? absint( $_POST['fmp_term_video_poster_id'] ) : 0;
		$video_url = isset( $_POST['fmp_term_video_url'] ) ? self::sanitize_video_url( $_POST['fmp_term_video_url'] ) : '';
		$address   = isset( $_POST['fmp_term_address'] ) ? self::sanitize_address( $_POST['fmp_term_address'] ) : '';
		$parent_id = isset( $_POST['fmp_term_parent_id'] ) ? absint( $_POST['fmp_term_parent_id'] ) : 0;
		$this->update_or_delete_term_meta( $term_id, self::TERM_IMAGE, $image_id && wp_attachment_is_image( $image_id ) ? $image_id : 0 );
		$this->update_or_delete_term_meta( $term_id, self::TERM_POSTER, $poster_id && wp_attachment_is_image( $poster_id ) ? $poster_id : 0 );
		$this->update_or_delete_term_meta( $term_id, self::TERM_VIDEO, $video_url );
		if ( '' !== $address ) {
			update_term_meta( $term_id, self::TERM_ADDRESS, $address );
		} else {
			delete_term_meta( $term_id, self::TERM_ADDRESS );
		}
		$term = get_term( $term_id );
		if ( $term && self::LOCATION === $term->taxonomy ) {
			$this->update_or_delete_term_meta( $term_id, self::LOCATION_BRANCH, $parent_id );
		} elseif ( $term && self::MENU === $term->taxonomy ) {
			$this->update_or_delete_term_meta( $term_id, self::MENU_LOCATION, $parent_id );
		}
	}

	private function render_parent_dropdown( $taxonomy, $selected ) {
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name' ) );
		echo '<select name="fmp_term_parent_id" id="fmp_term_parent_id" class="widefat"><option value="0">' . esc_html__( 'Select parent', 'food-menu' ) . '</option>';
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				echo '<option value="' . esc_attr( $term->term_id ) . '" ' . selected( $selected, $term->term_id, false ) . '>' . esc_html( $term->name ) . '</option>';
			}
		}
		echo '</select>';
	}

	private function update_or_delete_term_meta( $term_id, $key, $value ) {
		if ( $value ) {
			update_term_meta( $term_id, $key, $value );
		} else {
			delete_term_meta( $term_id, $key );
		}
	}

	public function render_single_term_metabox( $post, $box ) {
		$taxonomy = $box['args']['taxonomy'];
		$terms    = get_the_terms( $post->ID, $taxonomy );
		$selected = ( ! empty( $terms ) && ! is_wp_error( $terms ) ) ? (int) $terms[0]->term_id : 0;
		$taxonomy_object = get_taxonomy( $taxonomy );

		wp_nonce_field( 'update-post_' . $post->ID, 'tax_input_nonce' );
		wp_dropdown_categories(
			array(
				'taxonomy'         => $taxonomy,
				'name'             => 'tax_input[' . $taxonomy . '][]',
				'id'               => 'fmp-single-' . $taxonomy,
				'show_option_none' => sprintf( __( 'Select %s', 'food-menu' ), $taxonomy_object->labels->singular_name ),
				'option_none_value' => '0',
				'hide_empty'       => false,
				'hierarchical'     => true,
				'orderby'          => 'name',
				'selected'         => $selected,
				'class'            => 'widefat',
			)
		);
	}

	public function enforce_single_term_relationship( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( $this->normalizing_single_term || ! in_array( $taxonomy, array( self::BRANCH, self::LOCATION, self::MENU ), true ) ) {
			return;
		}

		$current_ids = wp_get_object_terms( $object_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $current_ids ) || count( $current_ids ) < 2 ) {
			return;
		}

		$this->normalizing_single_term = true;
		wp_set_object_terms( $object_id, array( (int) $current_ids[0] ), $taxonomy, false );
		$this->normalizing_single_term = false;
	}
}
