<?php
/**
 * Findings store.
 *
 * @package Shomer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Persistence for scan findings.
 */
class Shomer_Findings {

	/**
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'shomer_findings';
	}

	/**
	 * Deterministic finding identity.
	 *
	 * @param string $module  A|B|C|D.
	 * @param string $check   Check slug.
	 * @param string $subject Stable subject (path, option, user id, route).
	 * @return string
	 */
	public static function make_uid( $module, $check, $subject ) {
		return $module . ':' . $check . ':' . hash( 'sha256', (string) $subject );
	}

	/**
	 * Lifecycle transition helper (pure).
	 *
	 * @param string $current Current status.
	 * @param bool   $seen    Present in this scan.
	 * @return string
	 */
	public static function next_status( $current, $seen ) {
		if ( $seen ) {
			if ( 'resolved' === $current || 'excepted' === $current ) {
				return ( 'resolved' === $current ) ? 'reappeared' : $current;
			}
			return ( 'reappeared' === $current ) ? 'reappeared' : 'open';
		}
		return 'resolved';
	}

	/**
	 * Insert or update by uid.
	 *
	 * @param array $finding Finding payload.
	 * @return string Resulting status.
	 */
	public static function upsert( array $finding ) {
		global $wpdb;

		$uid      = $finding['uid'];
		$now      = time();
		$existing = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE uid = %s', $uid ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		$evidence_json = wp_json_encode( isset( $finding['evidence'] ) ? $finding['evidence'] : array() );
		$fix           = isset( $finding['fix'] ) && is_array( $finding['fix'] ) ? $finding['fix'] : null;

		if ( ! $existing ) {
			$row = array(
				'module'      => $finding['module'],
				'check'       => $finding['check'],
				'severity'    => $finding['severity'],
				'status'      => 'open',
				'title'       => $finding['title'],
				'explanation' => $finding['explanation'],
				'evidence'    => isset( $finding['evidence'] ) ? $finding['evidence'] : array(),
				'scan_id'     => $finding['scan_id'],
				'first_seen'  => $now,
				'last_seen'   => $now,
			);
			$prev = self::latest_entry_hash();
			if ( '' === $prev ) {
				$install = (int) get_option( 'shomer_install_ts', 0 );
				$prev    = Shomer_Hash::genesis_hash( home_url(), $install );
			}
			$entry_hash = Shomer_Hash::entry_hash( $prev, $row );

			$wpdb->insert(
				self::table(),
				array(
					'uid'         => $uid,
					'module'      => $finding['module'],
					'check_slug'  => $finding['check'],
					'severity'    => $finding['severity'],
					'status'      => 'open',
					'title'       => $finding['title'],
					'explanation' => $finding['explanation'],
					'evidence'    => $evidence_json,
					'fix_action'  => $fix ? $fix['action'] : null,
					'fix_payload' => $fix ? wp_json_encode( $fix ) : null,
					'first_seen'  => $now,
					'last_seen'   => $now,
					'scan_id'     => $finding['scan_id'],
					'prev_hash'   => $prev,
					'entry_hash'  => $entry_hash,
				)
			);
			return 'open';
		}

		$status = self::next_status( $existing['status'], true );
		if ( 'reappeared' === $status ) {
			$finding['severity'] = 'critical';
		}

		$wpdb->update(
			self::table(),
			array(
				'severity'    => isset( $finding['severity'] ) ? $finding['severity'] : $existing['severity'],
				'status'      => $status,
				'title'       => $finding['title'],
				'explanation' => $finding['explanation'],
				'evidence'    => $evidence_json,
				'last_seen'   => $now,
				'scan_id'     => $finding['scan_id'],
			),
			array( 'uid' => $uid )
		);
		return $status;
	}

	/**
	 * Resolve findings not seen in this scan (optional helper for full scans later).
	 *
	 * @param string $scan_id   Scan id.
	 * @param array  $seen_uids Uids seen.
	 * @return int
	 */
	public static function mark_missing( $scan_id, array $seen_uids ) {
		global $wpdb;
		if ( empty( $seen_uids ) ) {
			return 0;
		}
		unset( $scan_id, $wpdb );
		return 0;
	}

	/**
	 * Dashboard counts.
	 *
	 * @return array
	 */
	public static function status_counts() {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT status, severity, COUNT(*) AS c FROM {$table} GROUP BY status, severity", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out   = array(
			'open'       => 0,
			'resolved'   => 0,
			'reappeared' => 0,
			'excepted'   => 0,
			'critical'   => 0,
			'warning'    => 0,
			'info'       => 0,
			'verified'   => 0,
			'failed'     => 0,
			'untestable' => 0,
		);
		foreach ( (array) $rows as $row ) {
			$status         = $row['status'];
			$out[ $status ] = ( isset( $out[ $status ] ) ? $out[ $status ] : 0 ) + (int) $row['c'];
			if ( in_array( $status, array( 'open', 'reappeared' ), true ) ) {
				$sev = $row['severity'];
				if ( isset( $out[ $sev ] ) ) {
					$out[ $sev ] += (int) $row['c'];
				}
			}
		}

		if ( ! class_exists( 'Shomer_Probe_Results' ) ) {
			require_once SHOMER_PLUGIN_DIR . 'includes/hardening/class-shomer-probe-results.php';
		}
		$probes              = Shomer_Probe_Results::counts();
		$out['verified']     = (int) $probes['verified'];
		$out['failed']       = (int) $probes['failed'];
		$out['untestable']   = (int) $probes['untestable'];

		return $out;
	}

	/**
	 * Clean-order list.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function list_clean_order( $limit = 100 ) {
		global $wpdb;
		$table = self::table();
		$limit = absint( $limit );
		$sql   = "SELECT * FROM {$table} WHERE status IN ('open','reappeared') ORDER BY
			FIELD(status,'reappeared','open'),
			FIELD(severity,'critical','warning','info'),
			FIELD(module,'A','C','B','D'),
			last_seen DESC
			LIMIT {$limit}";
		return $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
}
