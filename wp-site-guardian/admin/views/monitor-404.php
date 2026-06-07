<?php
/**
 * 404 Monitor view.
 *
 * @package WP_Site_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$active_floods = (array) get_option( 'wpsg_active_floods', array() );
$threshold     = (int) get_option( 'wpsg_flood_threshold', 50 );

// Recent 404 log (last 200 rows).
$logs = array();
$ip_summary = array();

if ( wpsg_is_pro() ) {
	$logs = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wpsg_404_log WHERE blog_id = %d ORDER BY timestamp DESC LIMIT 200", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			get_current_blog_id()
		),
		ARRAY_A
	) ?? array();

	// Per-IP summary.
	$ip_summary = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT ip_address, COUNT(*) as hit_count, MAX(timestamp) as last_seen, SUM(is_bot) as bot_hits
			 FROM {$wpdb->prefix}wpsg_404_log
			 WHERE blog_id = %d AND timestamp >= %s
			 GROUP BY ip_address
			 ORDER BY hit_count DESC
			 LIMIT 30", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			get_current_blog_id(),
			gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
		),
		ARRAY_A
	) ?? array();
}

// Hourly breakdown for the mini chart (last 24h).
$hourly = array();
if ( wpsg_is_pro() ) {
	$hourly_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT HOUR(timestamp) as h, COUNT(*) as cnt
			 FROM {$wpdb->prefix}wpsg_404_log
			 WHERE blog_id = %d AND timestamp >= %s
			 GROUP BY h ORDER BY h ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			get_current_blog_id(),
			gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) )
		),
		ARRAY_A
	) ?? array();
	foreach ( $hourly_rows as $row ) {
		$hourly[ (int) $row['h'] ] = (int) $row['cnt'];
	}
}
$max_hourly = $hourly ? max( $hourly ) : 1;
?>
<div class="wrap wpsg-wrap">
	<h1 class="wpsg-page-title">
		<span class="dashicons dashicons-search"></span>
		<?php esc_html_e( '404 Monitor', 'wp-site-guardian' ); ?>
	</h1>

	<?php if ( ! wpsg_is_pro() ) : ?>
	<div class="wpsg-upsell-banner">
		<?php esc_html_e( '404 monitoring requires WP Site Guardian Pro.', 'wp-site-guardian' ); ?>
		<a href="<?php echo esc_url( WPSG_License::get_upgrade_url() ); ?>" class="button button-primary wpsg-upsell-btn" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade to Pro', 'wp-site-guardian' ); ?></a>
	</div>
	<?php endif; ?>

	<!-- Flood Alerts -->
	<?php if ( ! empty( $active_floods ) ) : ?>
	<div class="wpsg-panel wpsg-panel-alert">
		<h2>⚠ <?php printf( /* translators: count */ esc_html__( 'Active Flood Alerts (%d)', 'wp-site-guardian' ), count( $active_floods ) ); ?></h2>
		<?php foreach ( $active_floods as $ip => $flood ) : ?>
		<div class="wpsg-flood-alert">
			<strong><?php echo esc_html( $flood['ip'] ?? $ip ); ?></strong> —
			<?php printf( /* translators: count */ esc_html__( '%d requests in the last hour', 'wp-site-guardian' ), (int) ( $flood['count'] ?? 0 ) ); ?>
			<?php if ( ! empty( $flood['db_stress'] ) ) : ?> <span class="wpsg-badge wpsg-badge-critical"><?php esc_html_e( 'DB Stress', 'wp-site-guardian' ); ?></span><?php endif; ?>
			<button class="button button-small wpsg-dismiss-alert" data-alert-type="flood" data-alert-key="<?php echo esc_attr( $ip ); ?>"><?php esc_html_e( 'Dismiss', 'wp-site-guardian' ); ?></button>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<!-- Threshold Setting -->
	<div class="wpsg-panel">
		<h2><?php esc_html_e( 'Flood Threshold', 'wp-site-guardian' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'wpsg_save_settings', 'wpsg_settings_nonce' ); ?>
			<label>
				<?php esc_html_e( 'Alert when an IP exceeds', 'wp-site-guardian' ); ?>
				<input type="number" name="wpsg_flood_threshold" value="<?php echo (int) $threshold; ?>" min="1" max="10000" style="width:80px;">
				<?php esc_html_e( '404 requests per hour.', 'wp-site-guardian' ); ?>
			</label>
			<button type="submit" class="button button-secondary" style="margin-left:8px;"><?php esc_html_e( 'Save', 'wp-site-guardian' ); ?></button>
		</form>
	</div>

	<!-- Hourly 404 Chart (CSS only) -->
	<?php if ( ! empty( $hourly ) ) : ?>
	<div class="wpsg-panel">
		<h2><?php esc_html_e( '404s by Hour (Last 24h)', 'wp-site-guardian' ); ?></h2>
		<div class="wpsg-bar-chart">
			<?php for ( $h = 0; $h < 24; $h++ ) :
				$cnt  = $hourly[ $h ] ?? 0;
				$pct  = $max_hourly > 0 ? round( ( $cnt / $max_hourly ) * 100 ) : 0;
			?>
			<div class="wpsg-bar-wrap" title="<?php printf( /* translators: 1: hour, 2: count */ esc_attr__( '%1$d:00 — %2$d hits', 'wp-site-guardian' ), $h, $cnt ); ?>">
				<div class="wpsg-bar" style="height:<?php echo (int) $pct; ?>%;"></div>
				<div class="wpsg-bar-label"><?php echo (int) $h; ?></div>
			</div>
			<?php endfor; ?>
		</div>
	</div>
	<?php endif; ?>

	<!-- Per-IP Summary -->
	<?php if ( ! empty( $ip_summary ) ) : ?>
	<div class="wpsg-panel">
		<h2><?php esc_html_e( 'Top IPs (Last 7 Days)', 'wp-site-guardian' ); ?></h2>
		<table class="widefat striped wpsg-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'IP Address', 'wp-site-guardian' ); ?></th>
					<th><?php esc_html_e( 'Total Hits', 'wp-site-guardian' ); ?></th>
					<th><?php esc_html_e( 'Bot Hits', 'wp-site-guardian' ); ?></th>
					<th><?php esc_html_e( 'Last Seen', 'wp-site-guardian' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-site-guardian' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $ip_summary as $row ) :
				$is_flood = (int) $row['hit_count'] >= $threshold;
			?>
				<tr class="<?php echo $is_flood ? 'wpsg-row-alert' : ''; ?>">
					<td><code><?php echo esc_html( $row['ip_address'] ); ?></code></td>
					<td><?php echo (int) $row['hit_count']; ?></td>
					<td><?php echo (int) $row['bot_hits']; ?></td>
					<td><?php echo esc_html( $row['last_seen'] ); ?></td>
					<td>
						<a href="https://www.abuseipdb.com/check/<?php echo esc_attr( $row['ip_address'] ); ?>" class="button button-small" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Check IP', 'wp-site-guardian' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<!-- Raw Log -->
	<?php if ( ! empty( $logs ) ) : ?>
	<div class="wpsg-panel">
		<h2><?php esc_html_e( 'Recent 404 Requests', 'wp-site-guardian' ); ?></h2>
		<table class="widefat striped wpsg-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'wp-site-guardian' ); ?></th>
					<th><?php esc_html_e( 'IP', 'wp-site-guardian' ); ?></th>
					<th><?php esc_html_e( 'URL', 'wp-site-guardian' ); ?></th>
					<th><?php esc_html_e( 'Bot', 'wp-site-guardian' ); ?></th>
					<th><?php esc_html_e( 'User Agent', 'wp-site-guardian' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $logs as $log ) : ?>
				<tr>
					<td><?php echo esc_html( $log['timestamp'] ); ?></td>
					<td><code><?php echo esc_html( $log['ip_address'] ); ?></code></td>
					<td><code title="<?php echo esc_attr( $log['request_uri'] ); ?>"><?php echo esc_html( mb_strimwidth( $log['request_uri'], 0, 60, '…' ) ); ?></code></td>
					<td>
						<?php if ( $log['is_bot'] ) : ?>
						<span class="wpsg-badge wpsg-badge-warning"><?php echo esc_html( $log['bot_name'] ); ?></span>
						<?php else : ?>
						<span class="wpsg-na">&mdash;</span>
						<?php endif; ?>
					</td>
					<td title="<?php echo esc_attr( $log['user_agent'] ); ?>"><?php echo esc_html( mb_strimwidth( $log['user_agent'], 0, 50, '…' ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p style="margin-top:8px;">
			<button class="button wpsg-export-log" data-log-type="404"><?php esc_html_e( 'Export as CSV', 'wp-site-guardian' ); ?></button>
		</p>
	</div>
	<?php endif; ?>
</div>
