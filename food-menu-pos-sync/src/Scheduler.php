<?php
namespace FoodMenu\PosSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires each provider's own configured sync interval to WP-Cron, as an
 * independent scheduled event per provider (identified by the provider
 * ID passed as the event's argument) — since more than one provider can
 * be enabled and syncing at once, each with its own schedule. Uses WP
 * core's built-in schedules (hourly/twicedaily/daily) rather than a
 * custom interval — simple, and predictable for anyone inspecting cron
 * from wp-admin.
 */
class Scheduler {

	const HOOK = 'food_menu_pos_sync_scheduled_sync';

	public function init() {
		add_action( self::HOOK, array( $this, 'run_scheduled_sync' ), 10, 1 );
	}

	/**
	 * Call whenever a provider's schedule setting changes (including on
	 * save, even if unchanged — cheap and avoids a second code path to
	 * keep in sync). Only ever touches this one provider's event.
	 */
	public static function reschedule_provider( $provider_id, $interval ) {
		wp_clear_scheduled_hook( self::HOOK, array( $provider_id ) );

		if ( empty( $interval ) || 'disabled' === $interval ) {
			return;
		}

		wp_schedule_event( time(), $interval, self::HOOK, array( $provider_id ) );
	}

	public static function unschedule_all() {
		foreach ( array_keys( Settings::get_providers() ) as $provider_id ) {
			wp_clear_scheduled_hook( self::HOOK, array( $provider_id ) );
		}
	}

	public function run_scheduled_sync( $provider_id ) {
		$settings          = Settings::get_settings();
		$provider_settings = isset( $settings[ $provider_id ] ) ? $settings[ $provider_id ] : array();

		if ( empty( $provider_settings['enabled'] ) ) {
			return;
		}

		$provider = Settings::get_provider_instance( $provider_id );
		if ( ! $provider ) {
			return;
		}

		$sync = new Sync();
		$sync->run( $provider, $provider_settings );
	}
}
