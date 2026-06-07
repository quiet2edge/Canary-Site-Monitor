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
$db_scan_results = (array) get_option( 'wpsg_last_db_scan_results', array() );
$db_scan_time    = get_option( 'wpsg_last_db_scan_time', '' );
$active_floods   = (array) get_option( 'wpsg_active_floods', array() );
$cron_health     = (array) get_option( 'wpsg_cron_health_report', array() );

// Recent plugin changes (last 24h).
global $wpdb;
$yesterday      = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
$plugin_changes = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}wpsg_plugin_changes WHERE timestamp >= %s ORDER BY timestamp DESC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$yesterday
	),
	ARRAY_A
) ?? array();

// Log errors last 24h.
$log_errors = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}wpsg_error_log WHERE last_seen >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
	? __( 'Healthy', 'wp-site-guardian' )
	: ( $score >= 50 ? __( 'Needs Attention', 'wp-site-guardian' ) : __( 'Critical Issues', 'wp-site-guardian' ) );
?>
<div class="wrap wpsg-wrap">
	<h1 class="wpsg-page-title">
		<span class="dashicons dashicons-shield"></span>
		<?php esc_html_e( 'WP Site Guardian — Dashboard', 'wp-site-guardian' ); ?>
	</h1>

	<?php if ( ! wpsg_is_pro() ) : ?>
	<div class="wpsg-upsell-banner">
		<span class="dashicons dashicons-star-filled"></span>
		<strong><?php esc_html_e( 'Upgrade to Pro', 'wp-site-guardian' ); ?></strong> &mdash;
		<?php esc_html_e( 'Unlock automatic repairs, 404 flood alerts, plugin watchdog, email digests, and more.', 'wp-site-guardian' ); ?>
		<a href="<?php echo esc_url( WPSG_License::get_upgrade_url() ); ?>" class="button button-primary wpsg-upsell-btn" target="_blank" rel="noopener">
			<?php esc_html_e( 'Upgrade Now', 'wp-site-guardian' ); ?>
		</a>
	</div>
	<?php endif; ?>

	<!-- Health Score Card -->
	<div class="wpsg-health-card wpsg-health-<?php echo esc_attr( $score_class ); ?>">
		<div class="wpsg-health-score"><?php echo (int) $score; ?></div>
		<div class="wpsg-health-details">
			<h2><?php echo esc_html( $score_label ); ?></h2>
			<p><?php
			if ( $score >= 80 ) {
				esc_html_e( 'No critical issues detected. Your site is running smoothly.', 'wp-site-guardian' );
			} elseif ( $score >= 50 ) {
				esc_html_e( 'Some warnings detected. Review the panels below and address flagged items.', 'wp-site-guardian' );
			} else {
				esc_html_e( 'Critical issues require your immediate attention. Check the database and PHP panels below.', 'wp-site-guardian' );
			}
			?></p>
		</div>
		<div class="wpsg-health-actions">
			<button id="wpsg-run-scan" class="button button-primary">
				<span class="dashicons dashicons-search"></span>
				<?php esc_html_e( 'Run Full Scan Now', 'wp-site-guardian' ); ?>
			</button>
		</div>
	</div>

	<!-- Metric Cards -->
	<div class="wpsg-metric-grid">
		<div class="wpsg-metric-card">
			<span class="wpsg-metric-icon dashicons dashicons-database"></span>
			<div class="wpsg-metric-value"><?php echo count( $db_scan_results ); ?></div>
			<div class="wpsg-metric-label"><?php esc_html_e( 'DB Issues', 'wp-site-guardian' ); ?></div>
			<?php if ( $db_scan_time ) : ?>
			<div class="wpsg-metric-sub"><?php printf( /* translators: datetime */ esc_html__( 'Last scan: %s', 'wp-site-guardian' ), esc_html( $db_scan_time ) ); ?></div>
			<?php endif; ?>
		</div>

		<div class="wpsg-metric-card <?php echo ! empty( $active_floods ) ? 'wpsg-metric-alert' : ''; ?>">
			<span class="wpsg-metric-icon dashicons dashicons-warning"></span>
			<div class="wpsg-metric-value"><?php echo count( $active_floods ); ?></div>
			<div class="wpsg-metric-label"><?php esc_html_e( 'Active 404 Floods', 'wp-site-guardian' ); ?></div>
			<?php if ( ! wpsg_is_pro() ) : ?><div class="wpsg-metric-sub wpsg-pro-badge"><?php esc_html_e( 'Pro', 'wp-site-guardian' ); ?></div><?php endif; ?>
		</div>

		<div class="wpsg-metric-card">
			<span class="wpsg-metric-icon dashicons dashicons-plugins-checked"></span>
			<div class="wpsg-metric-value"><?php echo count( $plugin_changes ); ?></div>
			<div class="wpsg-metric-label"><?php esc_html_e( 'Plugin Changes (24h)', 'wp-site-guardian' ); ?></div>
			<?php if ( ! wpsg_is_pro() ) : ?><div class="wpsg-metric-sub wpsg-pro-badge"><?php esc_html_e( 'Pro', 'wp-site-guardian' ); ?></div><?php endif; ?>
		</div>

		<div class="wpsg-metric-card <?php echo (int) $log_errors > 0 ? 'wpsg-metric-warn' : ''; ?>">
			<span class="wpsg-metric-icon dashicons dashicons-editor-code"></span>
			<div class="wpsg-metric-value"><?php echo (int) $log_errors; ?></div>
			<div class="wpsg-metric-label"><?php esc_html_e( 'Log Errors (24h)', 'wp-site-guardian' ); ?></div>
			<?php if ( ! wpsg_is_pro() ) : ?><div class="wpsg-metric-sub wpsg-pro-badge"><?php esc_html_e( 'Pro', 'wp-site-guardian' ); ?></div><?php endif; ?>
		</div>
	</div>

	<!-- Recent Alerts -->
	<?php if ( ! empty( $active_floods ) || ! empty( $db_scan_results ) ) : ?>
	<div class="wpsg-panel">
		<h2><?php esc_html_e( 'Recent Alerts', 'wp-site-guardian' ); ?></h2>
		<table class="widefat striped wpsg-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Severity', 'wp-site-guardian' ); ?></th>
					<th><?php esc_html_e( 'Alert', 'wp-site-guardian' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-site-guardian' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( array_slice( $active_floods, 0, 5 ) as $ip => $flood ) : ?>
				<tr>
					<td><span class="wpsg-badge wpsg-badge-critical"><?php esc_html_e( 'Critical', 'wp-site-guardian' ); ?></span></td>
					<td><?php printf( /* translators: 1: IP, 2: count */ esc_html__( '404 Flood from %1$s (%2$d hits/hr)', 'wp-site-guardian' ), esc_html( $flood['ip'] ), (int) $flood['count'] ); ?></td>
					<td><button class="button button-small wpsg-dismiss-alert" data-alert-type="flood" data-alert-key="<?php echo esc_attr( $ip ); ?>"><?php esc_html_e( 'Dismiss', 'wp-site-guardian' ); ?></button></td>
				</tr>
				<?php endforeach; ?>
				<?php foreach ( array_slice( $db_scan_results, 0, 5 ) as $issue ) : ?>
				<tr>
					<td><span class="wpsg-badge wpsg-badge-<?php echo esc_attr( $issue['severity'] ?? 'info' ); ?>"><?php echo esc_html( ucfirst( $issue['severity'] ?? 'info' ) ); ?></span></td>
					<td><?php echo wp_kses_post( $issue['description'] ?? '' ); ?></td>
					<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpsg-database' ) ); ?>" class="button button-small"><?php esc_html_e( 'View', 'wp-site-guardian' ); ?></a></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<div id="wpsg-scan-result" class="wpsg-notice" style="display:none;"></div>
</div>
