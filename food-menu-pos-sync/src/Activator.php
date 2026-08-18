<?php
namespace FoodMenu\PosSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin activation. This addon has no post type/taxonomies
 * of its own (Core owns those) — activation only needs to restore any
 * previously-saved POS sync schedules.
 */
class Activator {

	public static function activate() {
		self::restore_pos_schedules();
	}

	/**
	 * Deactivation clears every provider's scheduled event (see
	 * Deactivator). If settings already have a provider enabled with a
	 * schedule from before, reactivating should bring that schedule back
	 * rather than leaving it silently off until the user re-saves that
	 * provider's settings.
	 */
	private static function restore_pos_schedules() {
		$settings = Settings::get_settings();

		foreach ( array_keys( Settings::get_providers() ) as $provider_id ) {
			$values = isset( $settings[ $provider_id ] ) ? $settings[ $provider_id ] : array();

			if ( ! empty( $values['enabled'] ) && ! empty( $values['schedule'] ) && 'disabled' !== $values['schedule'] ) {
				Scheduler::reschedule_provider( $provider_id, $values['schedule'] );
			}
		}
	}
}
