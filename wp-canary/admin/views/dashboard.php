<?php
/**
 * Dashboard view.
 *
 * @package WP_Site_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Gather data.
$db_scan_results = (array) get_option( 'wpc_last_db_scan_results', array() );
$db_scan_time    = get_option( 'wpc_last_db_scan_time', '' );
$active_floods   = (array) get_option( 'wpc_active_floods', array() );
$cron_health     = (array) get_option( 'wpc_cron_health_report', array() );

// Recent plugin changes (last 24h).
global $wpdb;
$yesterday      = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
$plugin_changes = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}wpc_plugin_changes WHERE timestamp >= %s ORDER BY timestamp DESC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$yesterday
	),
	ARRAY_A
) ?? array();

// Log errors last 24h.
$log_errors = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}wpc_error_log WHERE last_seen >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$yesterday
	)
);

$critical_db    = count( array_filter( $db_scan_results, function ( $i ) { return isset( $i['severity'] ) && 'critical' === $i['severity']; } ) );
$missed_crons   = isset( $cron_health['missed_count'] ) ? (int) $cron_health['missed_count'] : 0;

// Calculate health score.
$score = 100;
$score -= $critical_db * 15;
$score -= count( $active_floods ) * 20;
$score -= $missed_crons * 10;
$score -= (int) $log_errors * 5;
$score  = max( 0, min( 100, $score ) );

$score_class = $score >= 80 ? 'good' : ( $score >= 50 ? 'warning' : 'critical' );
$score_label = $score >= 80
	? __( 'Healthy', 'wp-canary' )
	: ( $score >= 50 ? __( 'Needs Attention', 'wp-canary' ) : __( 'Critical Issues', 'wp-canary' ) );
?>
<div class="wrap wpc-wrap">
	<h1 class="wpc-page-title">
		<span class="dashicons dashicons-shield"></span>
		<?php esc_html_e( 'WP Canary — Dashboard', 'wp-canary' ); ?>
	</h1>

	<?php if ( ! wpc_is_pro() ) : ?>
	<div class="wpc-upsell-banner">
		<span class="dashicons dashicons-star-filled"></span>
		<strong><?php esc_html_e( 'Upgrade to Pro', 'wp-canary' ); ?></strong> &mdash;
		<?php esc_html_e( 'Unlock automatic repairs, 404 flood alerts, plugin watchdog, email digests, and more.', 'wp-canary' ); ?>
		<a href="<?php echo esc_url( WPC_License::get_upgrade_url() ); ?>" class="button button-primary wpc-upsell-btn" target="_blank" rel="noopener">
			<?php esc_html_e( 'Upgrade Now', 'wp-canary' ); ?>
		</a>
	</div>
	<?php endif; ?>

	<!-- Health Score Card -->
	<div class="wpc-health-card wpc-health-<?php echo esc_attr( $score_class ); ?>">
		<div class="wpc-health-score"><?php echo (int) $score; ?></div>
		<div class="wpc-health-details">
			<h2><?php echo esc_html( $score_label ); ?></h2>
			<p><?php
			if ( $score >= 80 ) {
				esc_html_e( 'No critical issues detected. Your site is running smoothly.', 'wp-canary' );
			} elseif ( $score >= 50 ) {
				esc_html_e( 'Some warnings detected. Review the panels below and address flagged items.', 'wp-canary' );
			} else {
				esc_html_e( 'Critical issues require your immediate attention. Check the database and PHP panels below.', 'wp-canary' );
			}
			?></p>
		</div>
		<div class="wpc-health-actions">
			<button id="wpc-run-scan" class="button button-primary">
				<span class="dashicons dashicons-search"></span>
				<?php esc_html_e( 'Run Full Scan Now', 'wp-canary' ); ?>
			</button>
		</div>
	</div>

	<!-- Metric Cards -->
	<div class="wpc-metric-grid">
		<div class="wpc-metric-card">
			<span class="wpc-metric-icon dashicons dashicons-database"></span>
			<div class="wpc-metric-value"><?php echo count( $db_scan_results ); ?></div>
			<div class="wpc-metric-label"><?php esc_html_e( 'DB Issues', 'wp-canary' ); ?></div>
			<?php if ( $db_scan_time ) : ?>
			<div class="wpc-metric-sub"><?php printf( /* translators: datetime */ esc_html__( 'Last scan: %s', 'wp-canary' ), esc_html( $db_scan_time ) ); ?></div>
			<?php endif; ?>
		</div>

		<div class="wpc-metric-card <?php echo ! empty( $active_floods ) ? 'wpc-metric-alert' : ''; ?>">
			<span class="wpc-metric-icon dashicons dashicons-warning"></span>
			<div class="wpc-metric-value"><?php echo count( $active_floods ); ?></div>
			<div class="wpc-metric-label"><?php esc_html_e( 'Active 404 Floods', 'wp-canary' ); ?></div>
			<?php if ( ! wpc_is_pro() ) : ?><div class="wpc-metric-sub wpc-pro-badge"><?php esc_html_e( 'Pro', 'wp-canary' ); ?></div><?php endif; ?>
		</div>

		<div class="wpc-metric-card">
			<span class="wpc-metric-icon dashicons dashicons-plugins-checked"></span>
			<div class="wpc-metric-value"><?php echo count( $plugin_changes ); ?></div>
			<div class="wpc-metric-label"><?php esc_html_e( 'Plugin Changes (24h)', 'wp-canary' ); ?></div>
			<?php if ( ! wpc_is_pro() ) : ?><div class="wpc-metric-sub wpc-pro-badge"><?php esc_html_e( 'Pro', 'wp-canary' ); ?></div><?php endif; ?>
		</div>

		<div class="wpc-metric-card <?php echo (int) $log_errors > 0 ? 'wpc-metric-warn' : ''; ?>">
			<span class="wpc-metric-icon dashicons dashicons-editor-code"></span>
			<div class="wpc-metric-value"><?php echo (int) $log_errors; ?></div>
			<div class="wpc-metric-label"><?php esc_html_e( 'Log Errors (24h)', 'wp-canary' ); ?></div>
			<?php if ( ! wpc_is_pro() ) : ?><div class="wpc-metric-sub wpc-pro-badge"><?php esc_html_e( 'Pro', 'wp-canary' ); ?></div><?php endif; ?>
		</div>
	</div>

	<!-- Recent Alerts -->
	<?php if ( ! empty( $active_floods ) || ! empty( $db_scan_results ) ) : ?>
	<div class="wpc-panel">
		<h2><?php esc_html_e( 'Recent Alerts', 'wp-canary' ); ?></h2>
		<table class="widefat striped wpc-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Severity', 'wp-canary' ); ?></th>
					<th><?php esc_html_e( 'Alert', 'wp-canary' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-canary' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( array_slice( $active_floods, 0, 5 ) as $ip => $flood ) : ?>
				<tr>
					<td><span class="wpc-badge wpc-badge-critical"><?php esc_html_e( 'Critical', 'wp-canary' ); ?></span></td>
					<td><?php printf( /* translators: 1: IP, 2: count */ esc_html__( '404 Flood from %1$s (%2$d hits/hr)', 'wp-canary' ), esc_html( $flood['ip'] ), (int) $flood['count'] ); ?></td>
					<td><button class="button button-small wpc-dismiss-alert" data-alert-type="flood" data-alert-key="<?php echo esc_attr( $ip ); ?>"><?php esc_html_e( 'Dismiss', 'wp-canary' ); ?></button></td>
				</tr>
				<?php endforeach; ?>
				<?php foreach ( array_slice( $db_scan_results, 0, 5 ) as $issue ) : ?>
				<tr>
					<td><span class="wpc-badge wpc-badge-<?php echo esc_attr( $issue['severity'] ?? 'info' ); ?>"><?php echo esc_html( ucfirst( $issue['severity'] ?? 'info' ) ); ?></span></td>
					<td><?php echo wp_kses_post( $issue['description'] ?? '' ); ?></td>
					<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpc-database' ) ); ?>" class="button button-small"><?php esc_html_e( 'View', 'wp-canary' ); ?></a></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<div id="wpc-scan-result" class="wpc-notice" style="display:none;"></div>
</div>
