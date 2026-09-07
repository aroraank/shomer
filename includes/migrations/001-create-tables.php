<?php
/**
 * Migration 001: create Shomer tables.
 *
 * @package Shomer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create findings, log, snapshots, and custody tables.
 *
 * @return void
 */
function shomer_migration_001_create_tables() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$findings = $wpdb->prefix . 'shomer_findings';
	$sql_findings = "CREATE TABLE {$findings} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		uid varchar(191) NOT NULL,
		module char(1) NOT NULL,
		check_slug varchar(100) NOT NULL,
		severity varchar(20) NOT NULL DEFAULT 'info',
		status varchar(20) NOT NULL DEFAULT 'open',
		title text NOT NULL,
		explanation text NOT NULL,
		evidence longtext NOT NULL,
		fix_action varchar(100) DEFAULT NULL,
		fix_payload longtext,
		first_seen bigint(20) unsigned NOT NULL DEFAULT 0,
		last_seen bigint(20) unsigned NOT NULL DEFAULT 0,
		scan_id varchar(64) NOT NULL DEFAULT '',
		prev_hash char(64) NOT NULL DEFAULT '',
		entry_hash char(64) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		UNIQUE KEY uid (uid),
		KEY status_severity (status, severity)
	) {$charset_collate};";

	$log = $wpdb->prefix . 'shomer_log';
	$sql_log = "CREATE TABLE {$log} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		ts bigint(20) unsigned NOT NULL DEFAULT 0,
		actor bigint(20) unsigned NOT NULL DEFAULT 0,
		event varchar(100) NOT NULL DEFAULT '',
		payload longtext NOT NULL,
		prev_hash char(64) NOT NULL DEFAULT '',
		entry_hash char(64) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY ts (ts),
		KEY event (event)
	) {$charset_collate};";

	$snapshots = $wpdb->prefix . 'shomer_snapshots';
	$sql_snapshots = "CREATE TABLE {$snapshots} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		ts bigint(20) unsigned NOT NULL DEFAULT 0,
		kind varchar(32) NOT NULL DEFAULT '',
		trigger_src varchar(32) NOT NULL DEFAULT '',
		payload longtext NOT NULL,
		sha256 char(64) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY kind_ts (kind, ts)
	) {$charset_collate};";

	$custody = $wpdb->prefix . 'shomer_custody';
	$sql_custody = "CREATE TABLE {$custody} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		ts bigint(20) unsigned NOT NULL DEFAULT 0,
		type varchar(64) NOT NULL DEFAULT '',
		initiator longtext NOT NULL,
		request_payload longtext NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		decided_by bigint(20) unsigned NOT NULL DEFAULT 0,
		decided_at bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY status_ts (status, ts)
	) {$charset_collate};";

	dbDelta( $sql_findings );
	dbDelta( $sql_log );
	dbDelta( $sql_snapshots );
	dbDelta( $sql_custody );

	update_option( 'shomer_db_version', '1', false );
}
