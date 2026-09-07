<?php
/**
 * Resumable scan engine.
 *
 * @package Shomer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runs registered check steps in budgeted batches.
 */
class Shomer_Scan_Engine {

	/** @var array<string,callable> */
	private static $steps = array();

	/** @var string|null */
	private static $lock_token = null;

	/**
	 * Register a scan step.
	 *
	 * @param string   $id       Step id.
	 * @param callable $callback Callback receiving (scan_id, cursor_array).
	 * @return void
	 */
	public static function register_step( $id, $callback ) {
		self::$steps[ (string) $id ] = $callback;
	}

	/**
	 * Wire scan hooks.
	 *
	 * @return void
	 */
	public static function boot_hooks() {
		add_action( 'shomer_tick', array( __CLASS__, 'tick' ) );
		add_action( 'shomer_daily_scan', array( __CLASS__, 'start_cron' ) );
		add_action( 'admin_post_shomer_scan_now', array( __CLASS__, 'handle_scan_now' ) );
	}

	/**
	 * Register built-in steps.
	 *
	 * @return void
	 */
	public static function register_default_steps() {
		require_once SHOMER_PLUGIN_DIR . 'includes/class-shomer-check-dummy.php';
		self::register_step( 'dummy', array( 'Shomer_Check_Dummy', 'run' ) );

		// Hardening probes need a full WordPress load (WPINC), not a CLI smoke harness.
		if ( defined( 'WPINC' ) ) {
			require_once SHOMER_PLUGIN_DIR . 'includes/hardening/class-shomer-probe-results.php';
			require_once SHOMER_PLUGIN_DIR . 'includes/hardening/class-shomer-probe-uploads-php.php';
			require_once SHOMER_PLUGIN_DIR . 'includes/hardening/class-shomer-probe-rest-users.php';
			self::register_step( 'b-uploads-php', array( 'Shomer_Probe_Uploads_Php', 'run' ) );
			self::register_step( 'b-rest-users', array( 'Shomer_Probe_Rest_Users', 'run' ) );
		}
	}

	/**
	 * Rebuild the step list for a new scan so leftover static registrations cannot leak.
	 *
	 * @return array<int,string>
	 */
	private static function fresh_step_ids() {
		self::$steps = array();
		self::register_default_steps();
		return array_keys( self::$steps );
	}

	/**
	 * Start a manual scan.
	 *
	 * @return true|WP_Error
	 */
	public static function start_manual() {
		self::register_default_steps();

		if ( self::is_locked() ) {
			return new WP_Error( 'shomer_busy', __( 'Scan in progress.', 'shomer' ) );
		}

		return self::start_new_scan( 'manual' );
	}

	/**
	 * Start a cron scan.
	 *
	 * @return void
	 */
	public static function start_cron() {
		self::register_default_steps();

		if ( self::is_locked() ) {
			return;
		}

		self::start_new_scan( 'cron' );
	}

	/**
	 * Start and run a scan.
	 *
	 * @param string $trigger Trigger.
	 * @return true|WP_Error
	 */
	private static function start_new_scan( $trigger ) {
		if ( ! self::acquire_lock() ) {
			return new WP_Error( 'shomer_busy', __( 'Scan in progress.', 'shomer' ) );
		}

		$scan_id = 'scan_' . gmdate( 'Y-m-d' ) . '_' . wp_generate_password( 6, false, false );
		$cursor  = array(
			'scan_id'     => $scan_id,
			'trigger'     => $trigger,
			'step_index'  => 0,
			'step_ids'    => self::fresh_step_ids(),
			'step_cursor' => array(),
			'started_at'  => time(),
			'stats'       => array(
				'findings' => 0,
			),
		);

		update_option( 'shomer_scan_cursor', $cursor, false );
		Shomer_Log::append( 'scan_started', array( 'scan_id' => $scan_id, 'trigger' => $trigger ), get_current_user_id() );

		self::tick();

		return true;
	}

	/**
	 * Resume a scan batch.
	 *
	 * @return void
	 */
	public static function tick() {
		$cursor = get_option( 'shomer_scan_cursor', null );
		if ( ! is_array( $cursor ) ) {
			return;
		}

		$lock = get_option( 'shomer_scan_lock', null );
		if ( is_array( $lock ) && ! empty( $lock['token'] ) ) {
			self::$lock_token = (string) $lock['token'];
		}

		self::register_default_steps();

		if ( ! self::is_locked() ) {
			if ( ! self::acquire_lock() ) {
				return;
			}
		} else {
			self::heartbeat_lock();
		}

		$deadline = microtime( true ) + self::budget_seconds();
		$step_ids = isset( $cursor['step_ids'] ) && is_array( $cursor['step_ids'] ) ? $cursor['step_ids'] : array();

		while ( microtime( true ) < $deadline ) {
			if ( $cursor['step_index'] >= count( $step_ids ) ) {
				Shomer_Log::append( 'scan_complete', array( 'scan_id' => $cursor['scan_id'], 'stats' => $cursor['stats'] ), 0 );
				delete_option( 'shomer_scan_cursor' );
				self::release_lock();
				return;
			}

			if ( self::should_yield_for_memory() ) {
				update_option( 'shomer_scan_cursor', $cursor, false );
				self::heartbeat_lock();
				return;
			}

			$step_id = $step_ids[ $cursor['step_index'] ];
			if ( ! isset( self::$steps[ $step_id ] ) ) {
				$cursor['step_index']++;
				continue;
			}

			$step_cursor = array();
			if ( isset( $cursor['step_cursor'][ $step_id ] ) && is_array( $cursor['step_cursor'][ $step_id ] ) ) {
				$step_cursor = $cursor['step_cursor'][ $step_id ];
			}

			$result = call_user_func( self::$steps[ $step_id ], $cursor['scan_id'], $step_cursor );
			if ( ! is_array( $result ) ) {
				$result = array(
					'done' => true,
				);
			}

			if ( ! empty( $result['findings_delta'] ) ) {
				$cursor['stats']['findings'] += (int) $result['findings_delta'];
			}

			if ( ! empty( $result['done'] ) ) {
				$cursor['step_index']++;
			} else {
				$cursor['step_cursor'][ $step_id ] = isset( $result['cursor'] ) && is_array( $result['cursor'] ) ? $result['cursor'] : array();
				break;
			}

			if ( microtime( true ) >= $deadline ) {
				break;
			}
		}

		update_option( 'shomer_scan_cursor', $cursor, false );
		self::heartbeat_lock();
	}

	/**
	 * Handle the manual scan request.
	 *
	 * @return void
	 */
	public static function handle_scan_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'shomer' ) );
		}

		check_admin_referer( 'shomer_scan_now' );

		$result = self::start_manual();
		$msg    = is_wp_error( $result ) ? 'shomer_scan_busy' : 'shomer_scan_started';

		wp_safe_redirect( add_query_arg( 'shomer_notice', $msg, admin_url( 'admin.php?page=shomer' ) ) );
		exit;
	}

	/**
	 * Scan budget in seconds.
	 *
	 * @return float
	 */
	private static function budget_seconds() {
		$max = (int) ini_get( 'max_execution_time' );
		if ( $max <= 0 ) {
			$max = 30;
		}

		return (float) min( 15, $max * 0.5 );
	}

	/**
	 * Check whether the scan lock is active.
	 *
	 * @return bool
	 */
	private static function is_locked() {
		$lock = get_option( 'shomer_scan_lock', null );
		if ( ! is_array( $lock ) || empty( $lock['ts'] ) ) {
			return false;
		}

		if ( ( time() - (int) $lock['ts'] ) > 600 ) {
			return false;
		}

		return true;
	}

	/**
	 * Acquire the scan lock.
	 *
	 * @return bool
	 */
	private static function acquire_lock() {
		$token   = wp_generate_password( 8, false, false );
		$payload = array(
			'ts'    => time(),
			'token' => $token,
		);

		if ( add_option( 'shomer_scan_lock', $payload, '', 'no' ) ) {
			self::$lock_token = $token;
			return true;
		}

		$lock = get_option( 'shomer_scan_lock', null );
		if ( ! is_array( $lock ) || empty( $lock['ts'] ) || ( time() - (int) $lock['ts'] ) <= 600 ) {
			return false;
		}

		delete_option( 'shomer_scan_lock' );
		if ( ! add_option( 'shomer_scan_lock', $payload, '', 'no' ) ) {
			return false;
		}

		self::$lock_token = $token;
		return true;
	}

	/**
	 * Refresh the scan lock heartbeat.
	 *
	 * @return void
	 */
	private static function heartbeat_lock() {
		$lock = get_option( 'shomer_scan_lock', array() );
		if ( is_array( $lock ) && self::lock_matches_current_process( $lock ) ) {
			$lock['ts'] = time();
			update_option( 'shomer_scan_lock', $lock, false );
		}
	}

	/**
	 * Release the scan lock.
	 *
	 * @return void
	 */
	private static function release_lock() {
		$lock = get_option( 'shomer_scan_lock', array() );
		if ( is_array( $lock ) && self::lock_matches_current_process( $lock ) ) {
			delete_option( 'shomer_scan_lock' );
			self::$lock_token = null;
		}
	}

	/**
	 * Check whether the lock belongs to this process.
	 *
	 * @param array $lock Lock payload.
	 * @return bool
	 */
	private static function lock_matches_current_process( array $lock ) {
		return ! empty( self::$lock_token ) && isset( $lock['token'] ) && hash_equals( (string) self::$lock_token, (string) $lock['token'] );
	}

	/**
	 * Check whether scan should yield for memory headroom.
	 *
	 * @return bool
	 */
	private static function should_yield_for_memory() {
		$memory_limit = ini_get( 'memory_limit' );
		if ( false === $memory_limit || '-1' === (string) $memory_limit ) {
			return false;
		}

		$limit = wp_convert_hr_to_bytes( $memory_limit );
		if ( $limit <= 0 ) {
			return false;
		}

		return memory_get_usage( true ) >= ( $limit - ( 10 * 1024 * 1024 ) );
	}
}
