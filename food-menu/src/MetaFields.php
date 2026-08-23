<?php
namespace FoodMenu\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and sanitizes post meta for Food Menu Items.
 *
 * Meta keys are centralized here as constants, and kept identical to
 * food-menu-plugin's (fmp_price, fmp_variations) so a site's existing
 * Food Menu Item content carries over unchanged if food-menu-plugin is
 * ever swapped for this plugin. Addons (POS Sync, etc.) write to these
 * same fields the admin UI and Elementor read from.
 */
class MetaFields {

	const PRICE        = 'fmp_price';
	const VARIATIONS   = 'fmp_variations';
	const VIDEO_URL    = 'fmp_video_url';
	const VIDEO_POSTER = 'fmp_video_poster_id';
	const NONCE_ACTION = 'fmp_save_meta';
	const NONCE_NAME   = 'fmp_meta_nonce';

	public function init() {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register() {
		register_post_meta(
			PostTypes::POST_TYPE,
			self::PRICE,
			array(
				'type'              => 'string',
				'description'       => __( 'Item price, stored as free-form text (e.g. $12, MKT, Starting at $10). Never cast to a number.', 'food-menu' ),
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_price' ),
				'auth_callback'     => array( __CLASS__, 'auth_callback' ),
			)
		);

		register_post_meta(
			PostTypes::POST_TYPE,
			self::VARIATIONS,
			array(
				'type'              => 'array',
				'description'       => __( 'Repeatable list of item variations, each with a name and a text price.', 'food-menu' ),
				'single'            => true,
				'default'           => array(),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'name'  => array( 'type' => 'string' ),
								'price' => array( 'type' => 'string' ),
							),
						),
					),
				),
				'sanitize_callback' => array( __CLASS__, 'sanitize_variations' ),
				'auth_callback'     => array( __CLASS__, 'auth_callback' ),
			)
		);

		register_post_meta(
			PostTypes::POST_TYPE,
			self::VIDEO_URL,
			array(
				'type'              => 'string',
				'description'       => __( 'Optional MP4 or WebM video URL.', 'food-menu' ),
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_video_url' ),
				'auth_callback'     => array( __CLASS__, 'auth_callback' ),
			)
		);

		register_post_meta(
			PostTypes::POST_TYPE,
			self::VIDEO_POSTER,
			array(
				'type'              => 'integer',
				'description'       => __( 'Optional image attachment ID used as the video poster.', 'food-menu' ),
				'single'            => true,
				'default'           => 0,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( __CLASS__, 'auth_callback' ),
			)
		);
	}

	public static function auth_callback( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Price is intentionally text-only: "MKT", "Starting at $10", and
	 * "2 for $8" are all valid and must never be coerced to a number.
	 */
	public static function sanitize_price( $value ) {
		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	public static function sanitize_video_url( $value ) {
		$url = esc_url_raw( wp_unslash( (string) $value ), array( 'http', 'https' ) );
		if ( '' === $url ) {
			return '';
		}

		$path = strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( ! preg_match( '/\.(mp4|webm)$/', $path ) ) {
			return '';
		}

		return $url;
	}

	public static function sanitize_variations( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();

		foreach ( $value as $row ) {
			$name  = isset( $row['name'] ) ? sanitize_text_field( wp_unslash( (string) $row['name'] ) ) : '';
			$price = isset( $row['price'] ) ? sanitize_text_field( wp_unslash( (string) $row['price'] ) ) : '';

			if ( '' === $name && '' === $price ) {
				continue;
			}

			$clean[] = array(
				'name'  => $name,
				'price' => $price,
			);
		}

		return $clean;
	}
}
