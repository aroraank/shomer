<?php
/**
 * Allowlisted HTTP gateway.
 *
 * @package Shomer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sole outbound HTTP entry point.
 */
class Shomer_Http {

	/**
	 * @param string $url           URL.
	 * @param array  $allowed_hosts Hostnames.
	 * @return bool
	 */
	public static function host_is_allowed( $url, array $allowed_hosts ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}
		$host = strtolower( $host );
		foreach ( $allowed_hosts as $allowed ) {
			if ( $host === strtolower( $allowed ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Allowed hosts for this site.
	 *
	 * @return array
	 */
	public static function allowed_hosts() {
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$hosts     = array( 'api.wordpress.org' );
		if ( is_string( $home_host ) && '' !== $home_host ) {
			$hosts[] = $home_host;
		}
		return $hosts;
	}

	/**
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_url_allowed( $url ) {
		return self::host_is_allowed( $url, self::allowed_hosts() );
	}

	/**
	 * Perform an allowlisted request.
	 *
	 * Redirects are not followed automatically. Callers that need a hop must
	 * call request() again on the Location URL so every host is rechecked.
	 *
	 * @param string $url  URL.
	 * @param array  $args wp_remote args.
	 * @return array|WP_Error
	 */
	public static function request( $url, $args = array() ) {
		if ( ! self::host_is_allowed( $url, self::allowed_hosts() ) ) {
			return new WP_Error( 'shomer_http_denied', 'Outbound URL not on Shomer allowlist.' );
		}
		$args = wp_parse_args(
			$args,
			array(
				'timeout'     => 15,
				'redirection' => 0,
			)
		);
		$args['redirection'] = 0;

		return wp_remote_request( $url, $args );
	}
}
