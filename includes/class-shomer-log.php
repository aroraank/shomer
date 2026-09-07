<?php
/**
 * Append-only tamper-evident event log.
 *
 * @package Shomer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Log store. No UPDATE/DELETE paths except retention prune (later).
 */
class Shomer_Log {

	/**
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'shomer_log';
	}

	/**
	 * Append an event.
	 *
	 * @param string $event   Event name (machine slug).
	 * @param array  $payload Payload.
	 * @param int    $actor   User id or 0.
	 * @return int Insert id.
	 */
	public static function append( $event, array $payload, $actor = 0 ) {
		global $wpdb;

		$prev = self::latest_entry_hash();
		if ( '' === $prev ) {
			$install = (int) get_option( 'shomer_install_ts', 0 );
			$prev    = Shomer_Hash::genesis_hash( home_url(), $install );
		}

		$ts  = time();
		$row = array(
			'ts'      => $ts,
			'actor'   => (int) $actor,
			'event'   => (string) $event,
			'payload' => $payload,
		);
		$entry_hash = Shomer_Hash::entry_hash( $prev, $row );

		$inserted = $wpdb->insert(
			self::table(),
			array(
				'ts'         => $ts,
				'actor'      => (int) $actor,
				'event'      => (string) $event,
				'payload'    => wp_json_encode( $payload ),
				'prev_hash'  => $prev,
				'entry_hash' => $entry_hash,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * @return string
	 */
	private static function latest_entry_hash() {
		global $wpdb;
		$table = self::table();
		$hash  = $wpdb->get_var( "SELECT entry_hash FROM {$table} ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_string( $hash ) ? $hash : '';
	}

	/**
	 * Verify chain integrity.
	 *
	 * @return true|int True or first broken row id.
	 */
	public static function verify_chain() {
		global $wpdb;
		$table   = self::table();
		$rows    = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$install = (int) get_option( 'shomer_install_ts', 0 );
		$prev    = Shomer_Hash::genesis_hash( home_url(), $install );

		foreach ( (array) $rows as $row ) {
			$payload = json_decode( $row['payload'], true );
			if ( ! is_array( $payload ) ) {
				$payload = array();
			}
			$check = array(
				'ts'      => (int) $row['ts'],
				'actor'   => (int) $row['actor'],
				'event'   => $row['event'],
				'payload' => $payload,
			);
			$expect = Shomer_Hash::entry_hash( $prev, $check );
			if ( ! hash_equals( $expect, $row['entry_hash'] ) || ! hash_equals( $prev, $row['prev_hash'] ) ) {
				return (int) $row['id'];
			}
			$prev = $row['entry_hash'];
		}
		return true;
	}
}
