<?php
/**
 * Probe: PHP must not execute inside uploads.
 *
 * @package Shomer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Writes a canary PHP file under uploads, requests it, then deletes it.
 */
class Shomer_Probe_Uploads_Php {

	/**
	 * Run the probe as a scan step.
	 *
	 * @param string $scan_id Scan id.
	 * @param array  $cursor  Step cursor.
	 * @return array
	 */
	public static function run( $scan_id, array $cursor ) {
		unset( $cursor );

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
			return self::finish( $scan_id, 'UNTESTABLE', __( 'Could not resolve the uploads directory.', 'shomer' ), array( 'reason' => 'uploads_dir' ) );
		}

		$basedir = wp_normalize_path( $uploads['basedir'] );
		$real    = realpath( $basedir );
		if ( false === $real || 0 !== strpos( wp_normalize_path( $real ), wp_normalize_path( ABSPATH ) ) ) {
			return self::finish( $scan_id, 'UNTESTABLE', __( 'Uploads path could not be confined to the site root.', 'shomer' ), array( 'reason' => 'path' ) );
		}

		$name = 'shomer-canary-' . wp_generate_password( 12, false, false ) . '.php';
		$path = trailingslashit( $real ) . $name;
		$url  = trailingslashit( $uploads['baseurl'] ) . $name;
		$code = "<?php echo 'shomer-canary';";

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$written = file_put_contents( $path, $code );
		if ( false === $written ) {
			return self::finish( $scan_id, 'UNTESTABLE', __( 'Could not write a canary file into uploads.', 'shomer' ), array( 'reason' => 'write' ) );
		}

		$response = Shomer_Http::request(
			$url,
			array(
				'timeout' => 10,
				'method'  => 'GET',
			)
		);

		$deleted = self::delete_canary( $path );
		if ( ! $deleted ) {
			Shomer_Probe_Results::set( 'uploads-php-exec', 'FAILED', array( 'canary_left' => $path ) );
			Shomer_Findings::upsert(
				array(
					'uid'         => Shomer_Findings::make_uid( 'B', 'uploads-php-canary-left', $path ),
					'module'      => 'B',
					'check'       => 'uploads-php-canary-left',
					'severity'    => 'critical',
					'title'       => __( 'Canary PHP file could not be deleted from uploads', 'shomer' ),
					'explanation' => __( 'Shomer wrote a test file and failed to remove it. Delete that file by hand and check uploads permissions.', 'shomer' ),
					'evidence'    => array(
						'kind' => 'file',
						'path' => $path,
						'url'  => $url,
					),
					'fix'         => array(
						'action'     => 'delete-file',
						'reversible' => false,
						'label'      => __( 'Delete the leftover canary file', 'shomer' ),
					),
					'scan_id'     => $scan_id,
				)
			);
			return array(
				'done'           => true,
				'findings_delta' => 1,
			);
		}

		if ( is_wp_error( $response ) ) {
			return self::finish(
				$scan_id,
				'UNTESTABLE',
				__( 'Could not request the canary over HTTP.', 'shomer' ),
				array(
					'reason' => 'http',
					'error'  => $response->get_error_message(),
					'url'    => $url,
				)
			);
		}

		$code_http = (int) wp_remote_retrieve_response_code( $response );
		$body      = (string) wp_remote_retrieve_body( $response );
		$executed  = ( false !== strpos( $body, 'shomer-canary' ) && false === strpos( $body, '<?php' ) );

		if ( $executed || ( 200 === $code_http && false !== strpos( $body, 'shomer-canary' ) ) ) {
			Shomer_Probe_Results::set( 'uploads-php-exec', 'FAILED', array( 'http' => $code_http, 'url' => $url ) );
			Shomer_Findings::upsert(
				array(
					'uid'         => Shomer_Findings::make_uid( 'B', 'uploads-php-exec', $uploads['basedir'] ),
					'module'      => 'B',
					'check'       => 'uploads-php-exec',
					'severity'    => 'critical',
					'title'       => __( 'PHP can run inside the uploads folder', 'shomer' ),
					'explanation' => __( 'Shomer placed a harmless test file in uploads and the server executed it. Attackers who can upload a file can run code. Ask your host to block PHP in uploads.', 'shomer' ),
					'evidence'    => array(
						'kind'   => 'http_probe',
						'url'    => $url,
						'status' => $code_http,
						'body'   => substr( $body, 0, 200 ),
					),
					'fix'         => null,
					'scan_id'     => $scan_id,
				)
			);
			return array(
				'done'           => true,
				'findings_delta' => 1,
			);
		}

		// 403/404/or raw download of source without execution counts as verified.
		return self::finish(
			$scan_id,
			'VERIFIED',
			__( 'Uploads did not execute the canary PHP file.', 'shomer' ),
			array(
				'http' => $code_http,
				'url'  => $url,
			),
			true
		);
	}

	/**
	 * @param string $path Absolute path.
	 * @return bool
	 */
	private static function delete_canary( $path ) {
		if ( ! file_exists( $path ) ) {
			return true;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		return unlink( $path );
	}

	/**
	 * @param string $scan_id Scan id.
	 * @param string $state   Probe state.
	 * @param string $message Message.
	 * @param array  $meta    Meta.
	 * @param bool   $clear   Clear open finding when verified.
	 * @return array
	 */
	private static function finish( $scan_id, $state, $message, array $meta, $clear = false ) {
		Shomer_Probe_Results::set( 'uploads-php-exec', $state, $meta );

		$delta = 0;
		if ( 'UNTESTABLE' === $state ) {
			Shomer_Findings::upsert(
				array(
					'uid'         => Shomer_Findings::make_uid( 'B', 'uploads-php-exec', 'untestable' ),
					'module'      => 'B',
					'check'       => 'uploads-php-exec',
					'severity'    => 'info',
					'title'       => __( 'Could not finish the uploads PHP execution probe', 'shomer' ),
					'explanation' => $message,
					'evidence'    => array_merge( array( 'kind' => 'http_probe' ), $meta ),
					'fix'         => null,
					'scan_id'     => $scan_id,
				)
			);
			$delta = 1;
		} elseif ( $clear ) {
			// Leave a quiet trail only in probe results; no open finding for green probes.
			$delta = 0;
		}

		return array(
			'done'           => true,
			'findings_delta' => $delta,
		);
	}
}
