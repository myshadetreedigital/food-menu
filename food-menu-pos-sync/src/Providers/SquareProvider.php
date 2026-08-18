<?php
namespace FoodMenu\PosSync\Providers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Square POS provider. Pull-only: authenticates with a static Personal
 * Access Token (no auth exchange call needed, unlike Toast) and fetches
 * the Catalog API's items/categories/images in one paginated call.
 *
 * Money is reported in the smallest currency unit (cents for USD), so
 * it's divided by 100 and formatted as text — never stored as a float.
 *
 * Square's ITEM_VARIATION concept (Small/Large-style pricing) maps
 * directly onto this plugin's Variations field, so — unlike Toast, where
 * there was no clean equivalent — this provider populates it.
 */
class SquareProvider implements ProviderInterface {

	const SANDBOX_BASE    = 'https://connect.squareupsandbox.com/v2';
	const PRODUCTION_BASE = 'https://connect.squareup.com/v2';
	const API_VERSION      = '2026-07-15';

	public function get_id() {
		return 'square';
	}

	public function get_label() {
		return __( 'Square', 'food-menu-pos-sync' );
	}

	public function get_settings_fields() {
		return array(
			array(
				'key'         => 'access_token',
				'label'       => __( 'Access Token', 'food-menu-pos-sync' ),
				'type'        => 'password',
				'description' => __( 'Personal Access Token from the Square Developer Dashboard (Credentials page).', 'food-menu-pos-sync' ),
			),
			array(
				'key'         => 'environment',
				'label'       => __( 'Environment', 'food-menu-pos-sync' ),
				'type'        => 'select',
				'options'     => array(
					'sandbox'    => __( 'Sandbox (testing)', 'food-menu-pos-sync' ),
					'production' => __( 'Production (live account)', 'food-menu-pos-sync' ),
				),
				'description' => __( 'Use Sandbox until you\'ve confirmed a pull looks right.', 'food-menu-pos-sync' ),
			),
		);
	}

	public function test_connection( array $settings ) {
		$result = $this->api_get( $this->base_url( $settings ) . '/catalog/list', $this->headers( $settings ), array( 'types' => 'CATEGORY' ) );
		return is_wp_error( $result ) ? $result : true;
	}

	public function fetch_items( array $settings ) {
		$base    = $this->base_url( $settings );
		$headers = $this->headers( $settings );

		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		$categories = array();
		$images     = array();
		$item_objects = array();
		$cursor     = '';

		do {
			$args = array( 'types' => 'ITEM,CATEGORY,IMAGE' );
			if ( '' !== $cursor ) {
				$args['cursor'] = $cursor;
			}

			$page = $this->api_get( $base . '/catalog/list', $headers, $args );
			if ( is_wp_error( $page ) ) {
				return $page;
			}

			foreach ( (array) ( isset( $page['objects'] ) ? $page['objects'] : array() ) as $object ) {
				if ( empty( $object['type'] ) || empty( $object['id'] ) ) {
					continue;
				}

				switch ( $object['type'] ) {
					case 'CATEGORY':
						$categories[ $object['id'] ] = isset( $object['category_data']['name'] ) ? $object['category_data']['name'] : '';
						break;
					case 'IMAGE':
						$images[ $object['id'] ] = isset( $object['image_data']['url'] ) ? $object['image_data']['url'] : '';
						break;
					case 'ITEM':
						$item_objects[] = $object;
						break;
				}
			}

			$cursor = isset( $page['cursor'] ) ? $page['cursor'] : '';
		} while ( '' !== $cursor );

		$items = array();
		foreach ( $item_objects as $object ) {
			$normalized = $this->normalize_item( $object, $categories, $images );
			if ( $normalized ) {
				$items[] = $normalized;
			}
		}

		return $items;
	}

	private function normalize_item( array $object, array $categories, array $images ) {
		$data = isset( $object['item_data'] ) ? $object['item_data'] : array();

		if ( empty( $object['id'] ) || empty( $data['name'] ) ) {
			return null;
		}

		$variations = array();
		foreach ( (array) ( isset( $data['variations'] ) ? $data['variations'] : array() ) as $variation ) {
			$vdata = isset( $variation['item_variation_data'] ) ? $variation['item_variation_data'] : array();
			$price = $this->format_money( isset( $vdata['price_money'] ) ? $vdata['price_money'] : null );

			if ( '' === $price && empty( $vdata['name'] ) ) {
				continue;
			}

			$variations[] = array(
				'name'  => isset( $vdata['name'] ) ? $vdata['name'] : '',
				'price' => $price,
			);
		}

		// A simple (non-variable) item still has exactly one Square
		// variation under the hood (often named "Regular"); treat that
		// as the item's single price rather than a one-row Variations list.
		if ( 1 === count( $variations ) ) {
			$price      = $variations[0]['price'];
			$variations = array();
		} elseif ( count( $variations ) > 1 ) {
			$prices = array_filter( array_map( array( $this, 'price_to_float' ), wp_list_pluck( $variations, 'price' ) ) );
			$price  = $prices ? ( 'Starting at $' . number_format( min( $prices ), 2 ) ) : '';
		} else {
			$price = '';
		}

		// Current Square API versions report `categories` (array of
		// {id, ordinal}) on the item, not the older singular
		// `category_id` string. Support both — confirmed via a live
		// account that only `categories` is actually present now.
		if ( ! empty( $data['categories'][0]['id'] ) ) {
			$category_id = $data['categories'][0]['id'];
		} elseif ( ! empty( $data['category_id'] ) ) {
			$category_id = $data['category_id'];
		} else {
			$category_id = '';
		}

		$image_id = ! empty( $data['image_ids'][0] ) ? $data['image_ids'][0] : '';

		return array(
			'pos_item_id' => $object['id'],
			'name'        => $data['name'],
			'price'       => $price,
			'category'    => isset( $categories[ $category_id ] ) && '' !== $categories[ $category_id ] ? $categories[ $category_id ] : null,
			'category_id' => '' !== $category_id ? $category_id : null,
			'description' => ! empty( $data['description'] ) ? $data['description'] : null,
			'image_url'   => isset( $images[ $image_id ] ) && '' !== $images[ $image_id ] ? $images[ $image_id ] : null,
			'variations'  => $variations,
		);
	}

	public function discover_categories( array $settings ) {
		$base    = $this->base_url( $settings );
		$headers = $this->headers( $settings );

		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		$categories = array();
		$cursor     = '';

		do {
			$args = array( 'types' => 'CATEGORY' );
			if ( '' !== $cursor ) {
				$args['cursor'] = $cursor;
			}

			$page = $this->api_get( $base . '/catalog/list', $headers, $args );
			if ( is_wp_error( $page ) ) {
				return $page;
			}

			foreach ( (array) ( isset( $page['objects'] ) ? $page['objects'] : array() ) as $object ) {
				if ( 'CATEGORY' !== ( isset( $object['type'] ) ? $object['type'] : '' ) || empty( $object['id'] ) ) {
					continue;
				}

				$categories[] = array(
					'id'   => $object['id'],
					'name' => isset( $object['category_data']['name'] ) ? $object['category_data']['name'] : $object['id'],
				);
			}

			$cursor = isset( $page['cursor'] ) ? $page['cursor'] : '';
		} while ( '' !== $cursor );

		return $categories;
	}

	private function price_to_float( $price_string ) {
		return (float) preg_replace( '/[^0-9.]/', '', $price_string );
	}

	private function format_money( $money ) {
		if ( empty( $money ) || ! isset( $money['amount'] ) || ! is_numeric( $money['amount'] ) ) {
			return '';
		}

		$amount   = (float) $money['amount'] / 100;
		$currency = isset( $money['currency'] ) ? $money['currency'] : 'USD';

		return 'USD' === $currency
			? '$' . number_format( $amount, 2 )
			: number_format( $amount, 2 ) . ' ' . $currency;
	}

	private function base_url( array $settings ) {
		return ( isset( $settings['environment'] ) && 'production' === $settings['environment'] )
			? self::PRODUCTION_BASE
			: self::SANDBOX_BASE;
	}

	private function headers( array $settings ) {
		$token = isset( $settings['access_token'] ) ? trim( $settings['access_token'] ) : '';

		if ( '' === $token ) {
			return new \WP_Error( 'food_menu_pos_sync_square_missing_token', __( 'Square Access Token is required.', 'food-menu-pos-sync' ) );
		}

		return array(
			'Authorization' => 'Bearer ' . $token,
			'Square-Version' => self::API_VERSION,
			'Content-Type'   => 'application/json',
		);
	}

	private function api_get( $url, $headers, array $query_args = array() ) {
		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		$response = wp_remote_get(
			add_query_arg( $query_args, $url ),
			array(
				'headers' => $headers,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$reason = ! empty( $body['errors'][0]['detail'] ) ? $body['errors'][0]['detail'] : sprintf( 'HTTP %d', $code );
			return new \WP_Error(
				'food_menu_pos_sync_square_request_failed',
				sprintf(
					/* translators: %s: failure reason from Square */
					__( 'Square request failed: %s', 'food-menu-pos-sync' ),
					$reason
				)
			);
		}

		return $body;
	}
}
