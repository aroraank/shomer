<?php
/**
 * Activation / deactivation.
 *
 * @package Shomer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles install schema and cron registration.
 */
class Shomer_Activator {

	const DB_VERSION = '1';

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		require_once SHOMER_PLUGIN_DIR . 'includes/migrations/001-create-tables.php';
		shomer_migration_001_create_tables();

		add_option( 'shomer_settings', array(
			'custody_mode'           => 'observe',
			'custody_observe_until'  => time() + ( 7 * DAY_IN_SECONDS ),
			'remove_data_on_uninstall' => 0,
		), '', 'no' );

		add_option( 'shomer_install_ts', time(), '', 'no' );

		if ( ! wp_next_scheduled( 'shomer_daily_scan' ) ) {
			$offset = abs( crc32( home_url() ) ) % HOUR_IN_SECONDS;
			wp_schedule_event( time() + $offset, 'daily', 'shomer_daily_scan' );
		}
		if ( ! wp_next_scheduled( 'shomer_tick' ) ) {
			wp_schedule_event( time() + 60, 'shomer_five_minutes', 'shomer_tick' );
		}

		if ( class_exists( 'Shomer_Log' ) ) {
			Shomer_Log::append( 'activated', array( 'version' => SHOMER_VERSION ), 0 );
		}
	}

	/**
	 * Run on plugin deactivation (keep data; clear cron).
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'shomer_daily_scan' );
		wp_clear_scheduled_hook( 'shomer_tick' );
	}
}
