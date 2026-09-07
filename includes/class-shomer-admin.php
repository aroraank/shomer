<?php
/**
 * Admin UI shell.
 *
 * @package Shomer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard and settings.
 */
class Shomer_Admin {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_shomer_verify_chain', array( __CLASS__, 'handle_verify_chain' ) );
	}

	/**
	 * @return void
	 */
	public static function menu() {
		add_menu_page(
			__( 'Shomer', 'shomer' ),
			__( 'Shomer', 'shomer' ),
			'manage_options',
			'shomer',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-shield',
			58
		);
	}

	/**
	 * @param string $hook Hook.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( 'toplevel_page_shomer' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'shomer-admin',
			SHOMER_PLUGIN_URL . 'assets/css/shomer-admin.css',
			array(),
			SHOMER_VERSION
		);
	}

	/**
	 * @return void
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'shomer' ) );
		}
		$counts = Shomer_Findings::status_counts();
		$audit  = Shomer_Self_Audit::run();
		$notice = self::dashboard_notice();
		?>
		<div class="wrap shomer-wrap">
			<h1><?php echo esc_html__( 'Shomer', 'shomer' ); ?></h1>
			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['class'] ); ?> is-dismissible">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>
			<p class="shomer-status">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: verified 2: failed 3: untestable */
						__( '%1$d checks verified, %2$d failed, %3$d untestable.', 'shomer' ),
						(int) $counts['verified'],
						(int) $counts['failed'],
						(int) $counts['untestable']
					)
				);
				?>
			</p>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: open 2: critical 3: warning */
						__( 'Open findings: %1$d (%2$d critical, %3$d warning).', 'shomer' ),
						(int) $counts['open'] + (int) $counts['reappeared'],
						(int) $counts['critical'],
						(int) $counts['warning']
					)
				);
				?>
			</p>
			<?php if ( ! $audit['ok'] ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html__( 'Self-audit failed:', 'shomer' ); ?></p>
					<ul><?php foreach ( $audit['failures'] as $f ) : ?>
						<li><?php echo esc_html( $f ); ?></li>
					<?php endforeach; ?></ul>
				</div>
			<?php else : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Self-audit passed: no unauthenticated surface patterns detected in Shomer source.', 'shomer' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="shomer_verify_chain" />
				<?php wp_nonce_field( 'shomer_verify_chain' ); ?>
				<?php submit_button( __( 'Verify log chain', 'shomer' ), 'secondary', 'submit', false ); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="shomer_scan_now" />
				<?php wp_nonce_field( 'shomer_scan_now' ); ?>
				<?php submit_button( __( 'Scan now', 'shomer' ), 'primary', 'submit', false ); ?>
			</form>

			<h2><?php echo esc_html__( 'Findings', 'shomer' ); ?></h2>
			<?php
			$findings = Shomer_Findings::list_clean_order( 50 );
			if ( empty( $findings ) ) {
				echo '<p>' . esc_html__( 'No open findings yet. Run a scan to populate this list.', 'shomer' ) . '</p>';
			} else {
				echo '<ol class="shomer-findings">';
				foreach ( $findings as $f ) {
					printf(
						'<li><strong>%s</strong> — %s</li>',
						esc_html( strtoupper( $f['severity'] ) ),
						esc_html( $f['title'] )
					);
				}
				echo '</ol>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * @return void
	 */
	public static function handle_verify_chain() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'shomer' ) );
		}
		check_admin_referer( 'shomer_verify_chain' );
		$result = Shomer_Log::verify_chain();
		$msg    = ( true === $result )
			? 'shomer_chain_ok'
			: 'shomer_chain_break_' . (int) $result;
		wp_safe_redirect( add_query_arg( 'shomer_notice', $msg, admin_url( 'admin.php?page=shomer' ) ) );
		exit;
	}

	/**
	 * Strictly allow dashboard notices from redirects.
	 *
	 * @return array{class:string,message:string}|null
	 */
	private static function dashboard_notice() {
		if ( empty( $_GET['shomer_notice'] ) ) {
			return null;
		}

		$notice = (string) wp_unslash( $_GET['shomer_notice'] );
		$map    = array(
			'shomer_scan_started' => array(
				'class'   => 'success',
				'message' => __( 'Scan started.', 'shomer' ),
			),
			'shomer_scan_busy' => array(
				'class'   => 'warning',
				'message' => __( 'A scan is already running.', 'shomer' ),
			),
			'shomer_chain_ok' => array(
				'class'   => 'success',
				'message' => __( 'Log chain verified.', 'shomer' ),
			),
		);

		if ( isset( $map[ $notice ] ) ) {
			return $map[ $notice ];
		}

		if ( preg_match( '/^shomer_chain_break_[0-9]+$/', $notice ) ) {
			return array(
				'class'   => 'error',
				'message' => __( 'Log chain verification failed.', 'shomer' ),
			);
		}

		return null;
	}
}
