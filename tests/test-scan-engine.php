<?php
/**
 * Scan engine tests (no WordPress required).
 *
 * @package Shomer
 */

require_once __DIR__ . '/bootstrap.php';

if ( ! defined( 'SHOMER_PLUGIN_DIR' ) ) {
	define( 'SHOMER_PLUGIN_DIR', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
}

$GLOBALS['shomer_test_options'] = array();
$GLOBALS['shomer_test_logs']     = array();
$GLOBALS['shomer_test_findings'] = array();

if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can() {
		return true;
	}
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 7;
	}
}
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
		unset( $special_chars, $extra_special_chars );
		return str_repeat( 'a', (int) $length );
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message ) {
		throw new Exception( is_string( $message ) ? $message : 'wp_die' );
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
	}
}
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $key, $value, $url ) {
		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . rawurlencode( $key ) . '=' . rawurlencode( $value );
	}
}
if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect() {}
}
if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer() {}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['shomer_test_options'] ) ? $GLOBALS['shomer_test_options'][ $name ] : $default;
	}
}
if ( ! function_exists( 'add_option' ) ) {
	function add_option( $name, $value, $deprecated = '', $autoload = 'yes' ) {
		unset( $deprecated, $autoload );
		if ( isset( $GLOBALS['shomer_test_add_option_hook'] ) && is_callable( $GLOBALS['shomer_test_add_option_hook'] ) ) {
			return (bool) call_user_func( $GLOBALS['shomer_test_add_option_hook'], $name, $value );
		}
		if ( array_key_exists( $name, $GLOBALS['shomer_test_options'] ) ) {
			return false;
		}
		$GLOBALS['shomer_test_options'][ $name ] = $value;
		return true;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		unset( $autoload );
		$GLOBALS['shomer_test_options'][ $name ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		unset( $GLOBALS['shomer_test_options'][ $name ] );
		return true;
	}
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;

		public function __construct( $code, $message ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}
if ( ! class_exists( 'Shomer_Log' ) ) {
	class Shomer_Log {
		public static function append( $event, array $payload, $actor = 0 ) {
			$GLOBALS['shomer_test_logs'][] = array(
				'event'   => $event,
				'payload' => $payload,
				'actor'   => (int) $actor,
			);

			return count( $GLOBALS['shomer_test_logs'] );
		}
	}
}
if ( ! class_exists( 'Shomer_Findings' ) ) {
	class Shomer_Findings {
		public static function make_uid( $module, $check, $subject ) {
			return $module . ':' . $check . ':' . hash( 'sha256', (string) $subject );
		}

		public static function upsert( array $finding ) {
			$GLOBALS['shomer_test_findings'][] = $finding;
			return 'open';
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/class-shomer-check-dummy.php';
require_once dirname( __DIR__ ) . '/includes/class-shomer-scan-engine.php';

function shomer_assert( $cond, $msg ) {
	if ( ! $cond ) {
		fwrite( STDERR, "FAIL: {$msg}\n" );
		exit( 1 );
	}

	echo "PASS: {$msg}\n";
}

function shomer_invoke_private_static( $method, array $args = array() ) {
	$ref = new ReflectionMethod( 'Shomer_Scan_Engine', $method );
	$ref->setAccessible( true );
	return $ref->invokeArgs( null, $args );
}

function shomer_set_private_static_property( $property, $value ) {
	$ref = new ReflectionProperty( 'Shomer_Scan_Engine', $property );
	$ref->setAccessible( true );
	$ref->setValue( null, $value );
}

// Busy scan should refuse to start.
$GLOBALS['shomer_test_options']['shomer_scan_lock'] = array(
	'ts'    => time(),
	'token' => 'busy',
);
$busy = Shomer_Scan_Engine::start_manual();
shomer_assert( $busy instanceof WP_Error, 'manual start returns WP_Error when locked' );
shomer_assert( 'shomer_busy' === $busy->get_error_code(), 'busy error code' );

// Lock ownership should only mutate the lock we hold.
$GLOBALS['shomer_test_options'] = array();
shomer_assert( true === shomer_invoke_private_static( 'acquire_lock' ), 'private lock acquisition succeeds' );
$owned_lock = $GLOBALS['shomer_test_options']['shomer_scan_lock'];
$GLOBALS['shomer_test_options']['shomer_scan_lock']['token'] = 'different-token';
$mismatched_lock = $GLOBALS['shomer_test_options']['shomer_scan_lock'];
shomer_invoke_private_static( 'heartbeat_lock' );
shomer_assert( $mismatched_lock === $GLOBALS['shomer_test_options']['shomer_scan_lock'], 'heartbeat skips mismatched token' );
shomer_invoke_private_static( 'release_lock' );
shomer_assert( $mismatched_lock === $GLOBALS['shomer_test_options']['shomer_scan_lock'], 'release skips mismatched token' );

// Stale locks should only be stolen atomically.
$GLOBALS['shomer_test_options'] = array(
	'shomer_scan_lock' => array(
		'ts'    => time() - 700,
		'token' => 'stale',
	),
);
$GLOBALS['shomer_test_add_option_hook'] = static function ( $name, $value ) {
	if ( 'shomer_scan_lock' !== $name ) {
		return false;
	}

	if ( array_key_exists( $name, $GLOBALS['shomer_test_options'] ) ) {
		return false;
	}

	$GLOBALS['shomer_test_options'][ $name ] = array(
		'ts'    => time(),
		'token' => 'rival',
	);

	return false;
};
shomer_assert( false === shomer_invoke_private_static( 'acquire_lock' ), 'stale steal fails when add_option loses race' );
shomer_assert( 'rival' === $GLOBALS['shomer_test_options']['shomer_scan_lock']['token'], 'stale steal leaves rival lock intact' );
$GLOBALS['shomer_test_add_option_hook'] = null;

// A resumed tick should adopt the active lock token so release works on completion.
$GLOBALS['shomer_test_options'] = array(
	'shomer_scan_lock'   => array(
		'ts'    => time() - 5,
		'token' => 'resume-token',
	),
	'shomer_scan_cursor' => array(
		'scan_id'     => 'scan-resume',
		'trigger'     => 'manual',
		'step_index'  => 0,
		'step_ids'    => array( 'resume-step' ),
		'step_cursor' => array(),
		'started_at'  => time() - 5,
		'stats'       => array(
			'findings' => 0,
		),
	),
);
shomer_set_private_static_property( 'lock_token', null );
Shomer_Scan_Engine::register_step(
	'resume-step',
	static function ( $scan_id, array $cursor ) {
		unset( $scan_id, $cursor );
		return array(
			'done' => true,
		);
	}
);
Shomer_Scan_Engine::tick();
shomer_assert( ! isset( $GLOBALS['shomer_test_options']['shomer_scan_cursor'] ), 'resumed tick clears cursor on completion' );
shomer_assert( ! isset( $GLOBALS['shomer_test_options']['shomer_scan_lock'] ), 'resumed tick releases adopted lock' );

// Clear the lock and run a scan end-to-end.
$GLOBALS['shomer_test_options'] = array();
$GLOBALS['shomer_test_logs']     = array();
$GLOBALS['shomer_test_findings'] = array();

$started = Shomer_Scan_Engine::start_manual();
shomer_assert( true === $started, 'manual scan starts successfully' );
shomer_assert( ! isset( $GLOBALS['shomer_test_options']['shomer_scan_cursor'] ), 'cursor cleared after complete scan' );
shomer_assert( ! isset( $GLOBALS['shomer_test_options']['shomer_scan_lock'] ), 'lock released after complete scan' );

$budget_ref = new ReflectionMethod( 'Shomer_Scan_Engine', 'budget_seconds' );
$budget_ref->setAccessible( true );
$previous_max = ini_get( 'max_execution_time' );
ini_set( 'max_execution_time', '1' );
$budget = $budget_ref->invoke( null );
if ( false !== $previous_max ) {
	ini_set( 'max_execution_time', (string) $previous_max );
}
shomer_assert( 0.5 === $budget, 'budget uses half of max_execution_time without a 1-second floor' );

$events = array_map(
	static function ( $row ) {
		return $row['event'];
	},
	$GLOBALS['shomer_test_logs']
);
shomer_assert( in_array( 'scan_started', $events, true ), 'scan_started logged' );
shomer_assert( in_array( 'scan_complete', $events, true ), 'scan_complete logged' );
shomer_assert( 1 === count( $GLOBALS['shomer_test_findings'] ), 'dummy finding written once' );
shomer_assert( 0 === strpos( $GLOBALS['shomer_test_findings'][0]['uid'], 'D:dummy-heartbeat:' ), 'dummy uid prefix' );

echo "All scan engine tests passed.\n";
