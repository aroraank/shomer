<?php
/**
 * Stores Module B probe outcomes for the honest status line.
 *
 * @package Shomer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Probe state helper. States: VERIFIED, FAILED, UNTESTABLE.
 */
class Shomer_Probe_Results {

	const OPTION = 'shomer_probe_results';

	/**
	 * Record one probe result.
	 *
	 * @param string $check Check slug.
	 * @param string $state VERIFIED|FAILED|UNTESTABLE.
	 * @param array  $meta  Optional meta.
	 * @return void
	 */
	public static function set( $check, $state, array $meta = array() ) {
		$allowed = array( 'VERIFIED', 'FAILED', 'UNTESTABLE' );
		$state   = strtoupper( (string) $state );
		if ( ! in_array( $state, $allowed, true ) ) {
			return;
		}

		$all = get_option( self::OPTION, array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}

		$all[ (string) $check ] = array(
			'state'      => $state,
			'updated_at' => time(),
			'meta'       => $meta,
		);

		update_option( self::OPTION, $all, false );
	}

	/**
	 * Counts for the dashboard status line.
	 *
	 * @return array{verified:int,failed:int,untestable:int}
	 */
	public static function counts() {
		$all = get_option( self::OPTION, array() );
		$out = array(
			'verified'   => 0,
			'failed'     => 0,
			'untestable' => 0,
		);
		if ( ! is_array( $all ) ) {
			return $out;
		}
		foreach ( $all as $row ) {
			if ( empty( $row['state'] ) ) {
				continue;
			}
			$key = strtolower( (string) $row['state'] );
			if ( isset( $out[ $key ] ) ) {
				$out[ $key ]++;
			}
		}
		return $out;
	}
}
