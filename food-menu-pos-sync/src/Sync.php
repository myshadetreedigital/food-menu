<?php
namespace FoodMenu\PosSync;

use FoodMenu\Core\PostTypes;
use FoodMenu\Core\Taxonomies;
use FoodMenu\Core\MetaFields as CoreMetaFields;
use FoodMenu\PosSync\Providers\ProviderInterface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pulls items from a POS provider and upserts them into Core's Food Menu
 * Items — this addon never creates its own post type, only writes into
 * FoodMenu\Core\PostTypes::POST_TYPE using plain WordPress functions.
 *
 * Matching is always by (provider, pos_item_id) meta, never by name — so
 * re-running a sync updates the same posts instead of creating
 * duplicates, and a manually-created item with a matching name is never
 * silently merged into a POS-synced one.
 *
 * Fields owned by POS (name, price, Category, and Variations — for
 * providers that report a variations concept) are overwritten on every
 * sync. Fields owned by staff (Item Description, Item Image) are only
 * ever set once, when a POS item is first imported — never touched
 * again, so manual edits in wp-admin are never clobbered. Branch/Location
 * are also set on create only, since they represent the connection's
 * configuration, not something the POS reports per item.
 *
 * Each provider carries its own complete settings (including
 * branch/location/status/schedule) rather than sharing one global set,
 * so more than one provider can be enabled and syncing independently at
 * once — e.g. Toast feeding one Location, Square feeding another.
 *
 * If the provider's settings include a non-empty `category_filter`
 * (an array of the provider's own category IDs, set via the settings
 * screen's category discovery UI), only items in those categories are
 * synced — everything else is counted as 'excluded', not 'skipped'
 * (skipped implies something went wrong; excluded is intentional).
 */
class Sync {

	/**
	 * @param ProviderInterface $provider
	 * @param array              $provider_settings This provider's full settings: credentials,
	 *                                               category_filter, branch_term_id, location_term_id,
	 *                                               new_item_status. Each provider carries its own
	 *                                               complete config so more than one can be enabled
	 *                                               and syncing independently at once.
	 * @return array|\WP_Error Results summary, or WP_Error if the fetch itself failed.
	 */
	public function run( ProviderInterface $provider, array $provider_settings ) {
		$items = $provider->fetch_items( $provider_settings );

		if ( is_wp_error( $items ) ) {
			return $items;
		}

		$category_filter = ( ! empty( $provider_settings['category_filter'] ) && is_array( $provider_settings['category_filter'] ) )
			? $provider_settings['category_filter']
			: array();

		$results = array(
			'created'  => 0,
			'updated'  => 0,
			'excluded' => 0,
			'skipped'  => array(),
			'total'    => count( $items ),
		);

		foreach ( $items as $item ) {
			// Whitelist: an empty filter means "sync everything" (today's
			// default, unchanged). A non-empty filter only lets through
			// items whose category_id is explicitly included — an item
			// with no category at all is excluded once a filter is set,
			// since it can't be confirmed as wanted.
			if ( ! empty( $category_filter ) ) {
				$item_category_id = isset( $item['category_id'] ) ? $item['category_id'] : null;
				if ( null === $item_category_id || ! in_array( $item_category_id, $category_filter, true ) ) {
					++$results['excluded'];
					continue;
				}
			}

			$outcome = $this->upsert_item( $provider->get_id(), $item, $provider_settings );

			if ( is_wp_error( $outcome ) ) {
				$results['skipped'][] = array(
					'name'   => isset( $item['name'] ) ? $item['name'] : $item['pos_item_id'],
					'reason' => $outcome->get_error_message(),
				);
				continue;
			}

			++$results[ $outcome ]; // 'created' or 'updated'
		}

		update_option(
			'food_menu_pos_sync_last_sync_' . $provider->get_id(),
			array(
				'time'    => current_time( 'mysql', true ),
				'results' => $results,
			),
			false
		);

		return $results;
	}

	/**
	 * @return string|\WP_Error 'created' or 'updated' on success.
	 */
	private function upsert_item( $provider_id, array $item, array $provider_settings ) {
		if ( empty( $item['pos_item_id'] ) || empty( $item['name'] ) ) {
			return new \WP_Error( 'food_menu_pos_sync_invalid_item', __( 'Item is missing a POS ID or name.', 'food-menu-pos-sync' ) );
		}

		$existing_id = $this->find_existing_post( $provider_id, $item['pos_item_id'] );
		$is_create   = ! $existing_id;

		$post_args = array(
			'post_type'   => PostTypes::POST_TYPE,
			'post_title'  => sanitize_text_field( $item['name'] ),
		);

		if ( $is_create ) {
			$post_args['post_status'] = isset( $provider_settings['new_item_status'] ) ? $provider_settings['new_item_status'] : 'draft';
			if ( ! empty( $item['description'] ) ) {
				$post_args['post_excerpt'] = sanitize_textarea_field( $item['description'] );
			}
			$post_id = wp_insert_post( wp_slash( $post_args ), true );
		} else {
			$post_args['ID'] = $existing_id;
			$post_id         = wp_update_post( wp_slash( $post_args ), true );
		}

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, CoreMetaFields::PRICE, CoreMetaFields::sanitize_price( isset( $item['price'] ) ? $item['price'] : '' ) );
		update_post_meta( $post_id, MetaFields::POS_PROVIDER, sanitize_key( $provider_id ) );
		update_post_meta( $post_id, MetaFields::POS_ITEM_ID, sanitize_text_field( $item['pos_item_id'] ) );
		update_post_meta( $post_id, MetaFields::POS_LAST_SYNCED, current_time( 'mysql', true ) );

		// Only touch Variations if this provider actually reports them
		// (Square does; Toast's provider omits the key entirely) — a
		// provider with no concept of variations must never wipe out
		// ones staff added manually in wp-admin.
		if ( array_key_exists( 'variations', $item ) ) {
			$variations = CoreMetaFields::sanitize_variations( is_array( $item['variations'] ) ? $item['variations'] : array() );
			if ( empty( $variations ) ) {
				delete_post_meta( $post_id, CoreMetaFields::VARIATIONS );
			} else {
				update_post_meta( $post_id, CoreMetaFields::VARIATIONS, $variations );
			}
		}

		if ( ! empty( $item['category'] ) ) {
			$this->assign_category( $post_id, $item['category'] );
		}

		if ( $is_create ) {
			if ( ! empty( $provider_settings['branch_term_id'] ) ) {
				wp_set_object_terms( $post_id, (int) $provider_settings['branch_term_id'], Taxonomies::BRANCH, false );
			}
			if ( ! empty( $provider_settings['location_term_id'] ) ) {
				wp_set_object_terms( $post_id, (int) $provider_settings['location_term_id'], Taxonomies::LOCATION, false );
			}
			if ( ! empty( $item['image_url'] ) ) {
				$this->sideload_featured_image( $post_id, $item['image_url'] );
			}
		}

		return $is_create ? 'created' : 'updated';
	}

	private function find_existing_post( $provider_id, $pos_item_id ) {
		$posts = get_posts(
			array(
				'post_type'      => PostTypes::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => MetaFields::POS_PROVIDER,
						'value' => $provider_id,
					),
					array(
						'key'   => MetaFields::POS_ITEM_ID,
						'value' => $pos_item_id,
					),
				),
			)
		);

		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	private function assign_category( $post_id, $category_name ) {
		$category_name = sanitize_text_field( $category_name );
		$term           = get_term_by( 'name', $category_name, Taxonomies::CATEGORY );

		if ( ! $term ) {
			$inserted = wp_insert_term( $category_name, Taxonomies::CATEGORY );
			if ( is_wp_error( $inserted ) ) {
				return;
			}
			$term_id = $inserted['term_id'];
		} else {
			$term_id = $term->term_id;
		}

		wp_set_object_terms( $post_id, (int) $term_id, Taxonomies::CATEGORY, false );
	}

	private function sideload_featured_image( $post_id, $image_url ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( esc_url_raw( $image_url ), $post_id, null, 'id' );

		if ( ! is_wp_error( $attachment_id ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}
}
