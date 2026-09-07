<?php
/**
 * Probe: REST API must not list users to strangers.
 *
 * @package Shomer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Requests /wp-json/wp/v2/users without auth.
 */
class Shomer_Probe_Rest_Users {

	/**
	 * Run the probe.
	 *
	 * @param string $scan_id Scan id.
	 * @param array  $cursor  Step cursor.
	 * @return array
	 */
	public static function run( $scan_id, array $cursor ) {
		unset( $cursor );

		$url = home_url( '/wp-json/wp/v2/users' );
		$res = Shomer_Http::request(
			$url,
			array(
				'timeout' => 10,
				'method'  => 'GET',
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $res ) ) {
			Shomer_Probe_Results::set( 'rest-user-enum', 'UNTESTABLE', array( 'error' => $res->get_error_message() ) );
			Shomer_Findings::upsert(
				array(
					'uid'         => Shomer_Findings::make_uid( 'B', 'rest-user-enum', 'untestable' ),
					'module'      => 'B',
					'check'       => 'rest-user-enum',
					'severity'    => 'info',
					'title'       => __( 'Could not test REST user listing', 'shomer' ),
					'explanation' => __( 'Shomer could not reach the users REST route on this site. The probe is untestable until HTTP self requests work.', 'shomer' ),
					'evidence'    => array(
						'kind'  => 'http_probe',
						'url'   => $url,
						'error' => $res->get_error_message(),
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

		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = (string) wp_remote_retrieve_body( $res );
		$data = json_decode( $body, true );

		$lists_users = is_array( $data )
			&& ! empty( $data )
			&& isset( $data[0] )
			&& is_array( $data[0] )
			&& ( isset( $data[0]['slug'] ) || isset( $data[0]['id'] ) );

		if ( $lists_users && $code >= 200 && $code < 300 ) {
			Shomer_Probe_Results::set( 'rest-user-enum', 'FAILED', array( 'http' => $code, 'url' => $url ) );
			Shomer_Findings::upsert(
				array(
					'uid'         => Shomer_Findings::make_uid( 'B', 'rest-user-enum', home_url() ),
					'module'      => 'B',
					'check'       => 'rest-user-enum',
					'severity'    => 'warning',
					'title'       => __( 'Anyone can list users through the REST API', 'shomer' ),
					'explanation' => __( 'An unauthenticated request to /wp-json/wp/v2/users returned account details. That helps attackers guess logins. You can require authentication for that route.', 'shomer' ),
					'evidence'    => array(
						'kind'   => 'http_probe',
						'url'    => $url,
						'status' => $code,
						'sample' => isset( $data[0]['slug'] ) ? $data[0]['slug'] : '',
					),
					'fix'         => array(
						'action'     => 'rest-users-require-auth',
						'reversible' => true,
						'label'      => __( 'Require login for the users REST route', 'shomer' ),
					),
					'scan_id'     => $scan_id,
				)
			);
			return array(
				'done'           => true,
				'findings_delta' => 1,
			);
		}

		Shomer_Probe_Results::set(
			'rest-user-enum',
			'VERIFIED',
			array(
				'http' => $code,
				'url'  => $url,
			)
		);

		return array(
			'done'           => true,
			'findings_delta' => 0,
		);
	}
}
