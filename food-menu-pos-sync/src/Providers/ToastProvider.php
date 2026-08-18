<?php
namespace FoodMenu\PosSync\Providers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Toast POS provider. Pull-only: authenticates, fetches the published
 * menu, and normalizes it into the shape ProviderInterface expects.
 *
 * Endpoint choice: Toast's Configuration API (config/v2/menus,
 * config/v2/menuItems) does NOT include pricing — Toast's own docs say
 * pricing "is not yet available" there. The Menus V2 API
 * (menus/v2/menus) is a separate, fully-nested endpoint (menus ->
 * menuGroups -> menuItems) that resolves price inline, so that's what
 * this uses. One request gets the whole menu, categories included.
 */
class ToastProvider implements ProviderInterface {

	const AUTH_URL        = 'https://ws-api.toasttab.com/authentication/v1/authentication/login';
	const MENUS_URL       = 'https://ws-api.toasttab.com/menus/v2/menus';
	const MENU_GROUPS_URL = 'https://ws-api.toasttab.com/config/v2/menuGroups';

	public function get_id() {
		return 'toast';
	}

	public function get_label() {
		return __( 'Toast', 'food-menu-pos-sync' );
	}

	public function get_settings_fields() {
		return array(
			array(
				'key'         => 'client_id',
				'label'       => __( 'Client ID', 'food-menu-pos-sync' ),
				'type'        => 'text',
				'description' => __( 'From your Toast API credentials.', 'food-menu-pos-sync' ),
			),
			array(
				'key'         => 'client_secret',
				'label'       => __( 'Client Secret', 'food-menu-pos-sync' ),
				'type'        => 'password',
				'description' => '',
			),
			array(
				'key'         => 'restaurant_guid',
				'label'       => __( 'Restaurant GUID', 'food-menu-pos-sync' ),
				'type'        => 'text',
				'description' => __( 'The Toast location this connection pulls from. Toast credentials are scoped to one restaurant, so this also determines which Branch/Location synced items are assigned to (set below).', 'food-menu-pos-sync' ),
			),
		);
	}

	public function test_connection( array $settings ) {
		$token = $this->authenticate( $settings );
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		return true;
	}

	public function fetch_items( array $settings ) {
		$token = $this->authenticate( $settings );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$restaurant_guid = isset( $settings['restaurant_guid'] ) ? trim( $settings['restaurant_guid'] ) : '';
		if ( '' === $restaurant_guid ) {
			return new \WP_Error( 'food_menu_pos_sync_toast_missing_guid', __( 'Toast Restaurant GUID is required.', 'food-menu-pos-sync' ) );
		}

		$response = wp_remote_get(
			self::MENUS_URL,
			array(
				'headers' => array(
					'Authorization'                 => 'Bearer ' . $token,
					'Toast-Restaurant-External-ID' => $restaurant_guid,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			return new \WP_Error(
				'food_menu_pos_sync_toast_fetch_failed',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Toast menu request failed (HTTP %d).', 'food-menu-pos-sync' ),
					$code
				)
			);
		}

		// Toast has returned this either as {"menus": [...]} or as a bare
		// array in different account configurations — handle both.
		if ( isset( $body['menus'] ) && is_array( $body['menus'] ) ) {
			$menus = $body['menus'];
		} elseif ( isset( $body[0] ) ) {
			$menus = $body;
		} else {
			$menus = array();
		}

		$items = array();
		foreach ( $menus as $menu ) {
			$this->collect_items_from_groups(
				isset( $menu['menuGroups'] ) && is_array( $menu['menuGroups'] ) ? $menu['menuGroups'] : array(),
				$items
			);
		}

		return array_values( $items );
	}

	/**
	 * Uses config/v2/menuGroups rather than the full menus/v2/menus pull
	 * used by fetch_items() — cheaper, since discovery only needs
	 * guid/name and menu group lists are typically small (no pagination
	 * implemented here for that reason).
	 */
	public function discover_categories( array $settings ) {
		$token = $this->authenticate( $settings );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$restaurant_guid = isset( $settings['restaurant_guid'] ) ? trim( $settings['restaurant_guid'] ) : '';
		if ( '' === $restaurant_guid ) {
			return new \WP_Error( 'food_menu_pos_sync_toast_missing_guid', __( 'Toast Restaurant GUID is required.', 'food-menu-pos-sync' ) );
		}

		$response = wp_remote_get(
			self::MENU_GROUPS_URL,
			array(
				'headers' => array(
					'Authorization'                 => 'Bearer ' . $token,
					'Toast-Restaurant-External-ID' => $restaurant_guid,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			return new \WP_Error(
				'food_menu_pos_sync_toast_fetch_failed',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Toast menu groups request failed (HTTP %d).', 'food-menu-pos-sync' ),
					$code
				)
			);
		}

		$categories = array();
		foreach ( (array) $body as $group ) {
			if ( empty( $group['guid'] ) || empty( $group['name'] ) ) {
				continue;
			}
			$categories[] = array(
				'id'   => $group['guid'],
				'name' => $group['name'],
			);
		}

		return $categories;
	}

	private function authenticate( array $settings ) {
		$client_id     = isset( $settings['client_id'] ) ? trim( $settings['client_id'] ) : '';
		$client_secret = isset( $settings['client_secret'] ) ? trim( $settings['client_secret'] ) : '';

		if ( '' === $client_id || '' === $client_secret ) {
			return new \WP_Error( 'food_menu_pos_sync_toast_missing_credentials', __( 'Toast Client ID and Client Secret are both required.', 'food-menu-pos-sync' ) );
		}

		$response = wp_remote_post(
			self::AUTH_URL,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'clientId'       => $client_id,
						'clientSecret'   => $client_secret,
						'userAccessType' => 'TOAST_MACHINE_CLIENT',
					)
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body['token']['accessToken'] ) ) {
			$reason = isset( $body['status'] ) ? $body['status'] : sprintf( 'HTTP %d', $code );
			return new \WP_Error(
				'food_menu_pos_sync_toast_auth_failed',
				sprintf(
					/* translators: %s: failure reason from Toast */
					__( 'Toast authentication failed: %s', 'food-menu-pos-sync' ),
					$reason
				)
			);
		}

		return $body['token']['accessToken'];
	}

	/**
	 * Recurses through menuGroups (they can nest) collecting items keyed
	 * by GUID, so an item referenced by more than one group is only
	 * counted once instead of imported as a duplicate.
	 */
	private function collect_items_from_groups( array $groups, array &$items ) {
		foreach ( $groups as $group ) {
			$category = isset( $group['name'] ) && '' !== $group['name'] ? $group['name'] : null;

			foreach ( (array) ( isset( $group['menuItems'] ) ? $group['menuItems'] : array() ) as $item ) {
				if ( empty( $item['guid'] ) || empty( $item['name'] ) ) {
					continue;
				}

				$items[ $item['guid'] ] = array(
					'pos_item_id' => $item['guid'],
					'name'        => $item['name'],
					'price'       => $this->format_price( $item ),
					'category'    => $category,
					'category_id' => isset( $group['guid'] ) ? $group['guid'] : null,
					'description' => ! empty( $item['description'] ) ? $item['description'] : null,
					'image_url'   => $this->extract_image_url( $item ),
				);
			}

			if ( ! empty( $group['menuGroups'] ) && is_array( $group['menuGroups'] ) ) {
				$this->collect_items_from_groups( $group['menuGroups'], $items );
			}
		}
	}

	private function format_price( array $item ) {
		if ( isset( $item['price'] ) && is_numeric( $item['price'] ) ) {
			return '$' . number_format( (float) $item['price'], 2 );
		}
		return '';
	}

	private function extract_image_url( array $item ) {
		if ( ! empty( $item['images'][0]['url'] ) ) {
			return $item['images'][0]['url'];
		}
		return null;
	}
}
