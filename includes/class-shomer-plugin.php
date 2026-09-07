<?php
/**
 * Main plugin bootstrap.
 *
 * @package Shomer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires hooks. Business logic lives in dedicated classes.
 */
class Shomer_Plugin {

	/**
	 * Boot the plugin.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( 'init', array( __CLASS__, 'maybe_reschedule_cron' ) );
		Shomer_Scan_Engine::boot_hooks();
		if ( is_admin() ) {
			Shomer_Admin::init();
		}
	}

	/**
	 * Register a five-minute cron interval.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function cron_schedules( $schedules ) {
		if ( ! isset( $schedules['shomer_five_minutes'] ) ) {
			$schedules['shomer_five_minutes'] = array(
				'interval' => 300,
				'display'  => __( 'Every five minutes (Shomer)', 'shomer' ),
			);
		}
		return $schedules;
	}

	/**
	 * Re-register cron if wiped by another plugin.
	 *
	 * @return void
	 */
	public static function maybe_reschedule_cron() {
		if ( ! wp_next_scheduled( 'shomer_daily_scan' ) ) {
			$offset = abs( crc32( home_url() ) ) % HOUR_IN_SECONDS;
			wp_schedule_event( time() + $offset, 'daily', 'shomer_daily_scan' );
		}
		if ( ! wp_next_scheduled( 'shomer_tick' ) ) {
			wp_schedule_event( time() + 60, 'shomer_five_minutes', 'shomer_tick' );
		}
	}
}
