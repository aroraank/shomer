<?php
/**
 * Canonical JSON and hash-chain helpers.
 *
 * @package Shomer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pure helpers for tamper-evident logging.
 */
class Shomer_Hash {

	/**
	 * Stable JSON: sorted keys, no whitespace.
	 *
	 * @param array $data Data.
	 * @return string
	 */
	public static function canonical_json( array $data ) {
		self::ksort_recursive( $data );
		return wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Hash one chain entry.
	 *
	 * @param string $prev_hash Previous entry hash.
	 * @param array  $row       Row without prev_hash/entry_hash.
	 * @return string
	 */
	public static function entry_hash( string $prev_hash, array $row ) {
		return hash( 'sha256', $prev_hash . self::canonical_json( $row ) );
	}

	/**
	 * Genesis hash for an empty chain.
	 *
	 * @param string $site_url   Site URL.
	 * @param int    $install_ts Install timestamp.
	 * @return string
	 */
	public static function genesis_hash( string $site_url, int $install_ts ) {
		return hash( 'sha256', $site_url . '|' . (string) $install_ts );
	}

	/**
	 * @param array $data Data by ref.
	 * @return void
	 */
	private static function ksort_recursive( array &$data ) {
		ksort( $data );
		foreach ( $data as &$value ) {
			if ( is_array( $value ) ) {
				self::ksort_recursive( $value );
			}
		}
	}
}
