<?php
/**
 * Uninstall Shomer.
 *
 * @package Shomer
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = get_option( 'shomer_settings', array() );
if ( empty( $settings['remove_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'shomer_findings', 'shomer_log', 'shomer_snapshots', 'shomer_custody' ) as $table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . $table ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name from fixed list.
}

$options = array(
	'shomer_settings',
	'shomer_db_version',
	'shomer_install_ts',
	'shomer_scan_cursor',
	'shomer_scan_lock',
	'shomer_last_digest',
);
foreach ( $options as $option ) {
	delete_option( $option );
}
