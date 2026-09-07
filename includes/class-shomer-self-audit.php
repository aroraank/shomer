<?php
/**
 * Self-audit for zero unauthenticated surface.
 *
 * @package Shomer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Scans Shomer PHP sources for forbidden patterns.
 */
class Shomer_Self_Audit {

	/**
	 * @return array{ok:bool,failures:array<int,string>}
	 */
	public static function run() {
		$failures = array();
		$root     = SHOMER_PLUGIN_DIR;
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			if ( 'php' !== strtolower( $file->getExtension() ) ) {
				continue;
			}
			$path = $file->getPathname();
			if ( false !== strpos( $path, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR ) ) {
				continue;
			}
			// Skip own file: regex literals here would false-positive the audit patterns.
			if ( false !== strpos( $path, 'class-shomer-self-audit.php' ) ) {
				continue;
			}
			$contents = file_get_contents( $path ); // phpcs:ignore
			if ( false === $contents ) {
				continue;
			}
			if ( preg_match( "/permission_callback\s*=>\s*'__return_true'/", $contents )
				|| preg_match( '/permission_callback\s*=>\s*"__return_true"/', $contents )
				|| preg_match( '/permission_callback\s*=>\s*__return_true/', $contents ) ) {
				$failures[] = 'Forbidden __return_true permission_callback in ' . $path;
			}
			if ( preg_match( "/wp_ajax_nopriv_shomer_/", $contents ) ) {
				$failures[] = 'Forbidden nopriv AJAX in ' . $path;
			}
		}
		return array(
			'ok'       => empty( $failures ),
			'failures' => $failures,
		);
	}
}
