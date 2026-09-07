<?php
/**
 * Dummy scan step to prove the pipeline.
 *
 * @package Shomer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Writes one informational heartbeat finding.
 */
class Shomer_Check_Dummy {

	/**
	 * Run the dummy step.
	 *
	 * @param string $scan_id Scan id.
	 * @param array  $cursor  Step cursor.
	 * @return array
	 */
	public static function run( $scan_id, array $cursor ) {
		unset( $cursor );

		$uid = Shomer_Findings::make_uid( 'D', 'dummy-heartbeat', 'shomer' );
		Shomer_Findings::upsert(
			array(
				'uid'         => $uid,
				'module'      => 'D',
				'check'       => 'dummy-heartbeat',
				'severity'    => 'info',
				'title'       => __( 'Shomer scan pipeline is working', 'shomer' ),
				'explanation' => __( 'This informational finding confirms the scan engine can write to the findings store. It is not a security issue.', 'shomer' ),
				'evidence'    => array(
					'kind'    => 'info',
					'message' => 'dummy',
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
}
