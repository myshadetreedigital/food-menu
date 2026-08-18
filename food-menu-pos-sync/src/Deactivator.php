<?php
namespace FoodMenu\PosSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin deactivation.
 */
class Deactivator {

	public static function deactivate() {
		Scheduler::unschedule_all();
	}
}
