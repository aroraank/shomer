<?php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * @param string $url       URL.
	 * @param int    $component Component.
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data Data.
	 * @param int   $options Options.
	 * @return string|false
	 */
	function wp_json_encode( $data, $options = 0 ) {
		return json_encode( $data, $options ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}
if ( ! function_exists( 'wp_convert_hr_to_bytes' ) ) {
	/**
	 * @param string $value Human-readable size.
	 * @return int
	 */
	function wp_convert_hr_to_bytes( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 0;
		}

		$last  = strtolower( substr( $value, -1 ) );
		$bytes = (float) $value;

		switch ( $last ) {
			case 'g':
				$bytes *= 1024;
				// Intentional fall-through.
			case 'm':
				$bytes *= 1024;
				// Intentional fall-through.
			case 'k':
				$bytes *= 1024;
				break;
		}

		return (int) $bytes;
	}
}
