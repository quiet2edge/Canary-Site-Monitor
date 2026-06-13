<?php
/**
 * Email Digest (Pro).
 *
 * @package WP_Canary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_Email_Digest
 *
 * Builds and sends the daily HTML diagnostic email.
 */
class WPC_Email_Digest {

	/**
	 * Send the daily digest email.
	 *
	 * @return bool Whether the email was sent.
	 */
	public function send_digest() {
		update_option( 'wpc_last_cron_runs', array_merge(
			(array) get_option( 'wpc_last_cron_runs', array() ),
			array( 'wpc_daily_digest' => current_time( 'mysql' ) )
		) );

		$enabled = get_option( 'wpc_digest_enabled', true );
		if ( ! $enabled ) {
			return false;
		}

		$to      = get_option( 'wpc_digest_email', get_option( 'admin_email' ) );
		$subject = sprintf(
			/* translators: 1: site name, 2: date */
			__( 'WP Canary Morning Report — %1$s — %2$s', 'wp-canary' ),
			get_bloginfo( 'name' ),
			( new DateTime( 'now', wp_timezone() ) )->format( 'F j, Y' )
		);

		$data    = $this->collect_data();
		$html    = $this->build_html( $data );

		add_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );
		$result = wp_mail( $to, $subject, $html );
		remove_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );

		return $result;
	}

	/**
	 * Content type filter for HTML email.
	 *
	 * @return string
	 */
	public function set_html_content_type() {
		return 'text/html';
	}

	/**
	 * Collect data from all monitoring modules.
	 *
	 * @return array
	 */
	private function collect_data() {
		$data = array();

		// DB scan results.
		$data['db_issues']   = (array) get_option( 'wpc_last_db_scan_results', array() );
		$data['db_scan_time'] = get_option( 'wpc_last_db_scan_time', '' );

		// Active floods.
		$data['active_floods'] = (array) get_option( 'wpc_active_floods', array() );

		// Cron health.
		$data['cron_health'] = (array) get_option( 'wpc_cron_health_report', array() );

		// PHP health (live).
		$php_monitor        = new WPC_PHP_Monitor();
		$data['php_health'] = $php_monitor->get_report();

		// Ad report.
		$data['ad_report'] = (array) get_option( 'wpc_ad_report', array() );

		// Plugin changes in past 24h.
		global $wpdb;
		$yesterday             = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
		$data['plugin_changes'] = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpc_plugin_changes WHERE timestamp >= %s ORDER BY timestamp DESC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$yesterday
			),
			ARRAY_A
		) ?? array();

		// Plugin health warnings.
		$plugin_health          = new WPC_Plugin_Health();
		$plugin_health_results  = $plugin_health->get_last_scan_results();
		$data['plugin_health']  = array();
		if ( ! empty( $plugin_health_results ) ) {
			foreach ( (array) ( $plugin_health_results['update_gaps'] ?? array() ) as $item ) {
				if ( in_array( $item['severity'] ?? '', array( 'critical', 'warning' ), true ) ) {
					$data['plugin_health'][] = array(
						'plugin_name' => $item['plugin_name'] ?? '',
						'message'     => $item['message'] ?? '',
						'severity'    => $item['severity'],
					);
				}
			}
			foreach ( (array) ( $plugin_health_results['license_health'] ?? array() ) as $item ) {
				if ( in_array( $item['severity'] ?? '', array( 'critical', 'warning' ), true ) ) {
					$data['plugin_health'][] = array(
						'plugin_name' => $item['plugin_name'] ?? '',
						'message'     => $item['message'] ?? '',
						'severity'    => $item['severity'],
					);
				}
			}
		}

		// Health score.
		$data['health_score'] = $this->calculate_health_score( $data );

		return $data;
	}

	/**
	 * Calculate site health score (0–100).
	 *
	 * @param array $data Collected data.
	 * @return int
	 */
	private function calculate_health_score( $data ) {
		$score = 100;

		// DB issues.
		$critical_db = count( array_filter( $data['db_issues'], function ( $i ) {
			return isset( $i['severity'] ) && 'critical' === $i['severity'];
		} ) );
		$score -= $critical_db * 15;

		// Active floods.
		$score -= count( $data['active_floods'] ) * 20;

		// Missed crons.
		$missed_crons = isset( $data['cron_health']['missed_count'] ) ? (int) $data['cron_health']['missed_count'] : 0;
		$score -= $missed_crons * 10;

		// Critical PHP errors.
		$critical_php = count( array_filter( $data['php_health']['flags'] ?? array(), function ( $f ) {
			return 'critical' === $f['severity'];
		} ) );
		$score -= $critical_php * 25;

		return max( 0, min( 100, $score ) );
	}

	/**
	 * Build the HTML email body.
	 *
	 * @param array $data Collected data.
	 * @return string
	 */
	private function build_html( $data ) {
		$site_name     = get_bloginfo( 'name' );
		$site_url      = home_url();
		$score         = $data['health_score'];
		$score_color   = $score >= 80 ? '#22863a' : ( $score >= 50 ? '#e36209' : '#cb2431' );
		$date_str      = ( new DateTime( 'now', wp_timezone() ) )->format( 'l, F j, Y' );

		$db_issues     = $data['db_issues'];
		$floods        = $data['active_floods'];
		$cron          = $data['cron_health'];
		$php           = $data['php_health'];
		$plugin_changes = $data['plugin_changes'];

		ob_start();
		?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $site_name ); ?> &mdash; WP Canary Report</title>
</head>
<body style="margin:0;padding:0;background:#f1f1f1;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#f1f1f1">
<tr><td align="center" style="padding:20px 0;">

<table width="600" border="0" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

  <!-- Header -->
  <tr><td bgcolor="#23282d" style="padding:24px 32px;">
    <table width="100%" border="0" cellpadding="0" cellspacing="0">
    <tr>
      <td>
        <span style="color:#ffffff;font-size:20px;font-weight:bold;">WP Canary</span><br>
        <span style="color:#aaaaaa;font-size:13px;"><?php echo esc_html( $date_str ); ?></span>
      </td>
      <td align="right">
        <span style="display:inline-block;background:<?php echo esc_attr( $score_color ); ?>;color:#ffffff;font-size:28px;font-weight:bold;padding:8px 16px;border-radius:4px;"><?php echo (int) $score; ?></span><br>
        <span style="color:#aaaaaa;font-size:11px;"><?php esc_html_e( 'Health Score', 'wp-canary' ); ?></span>
      </td>
    </tr>
    </table>
  </td></tr>

  <!-- Site info -->
  <tr><td style="padding:16px 32px;border-bottom:1px solid #eeeeee;">
    <span style="font-size:13px;color:#555;"><?php esc_html_e( 'Site:', 'wp-canary' ); ?> <strong><?php echo esc_html( $site_name ); ?></strong> &mdash; <a href="<?php echo esc_url( $site_url ); ?>" style="color:#0073aa;"><?php echo esc_url( $site_url ); ?></a></span>
  </td></tr>

  <!-- Plain-English Summary -->
  <tr><td style="padding:24px 32px;">
    <h2 style="margin:0 0 12px;font-size:16px;color:#23282d;"><?php esc_html_e( 'Summary', 'wp-canary' ); ?></h2>
    <?php echo wp_kses_post( $this->build_plain_summary( $data ) ); ?>
  </td></tr>

  <!-- DB Issues -->
  <?php if ( ! empty( $db_issues ) ) : ?>
  <tr><td style="padding:0 32px 24px;">
    <h3 style="margin:0 0 8px;font-size:14px;color:#cb2431;"><?php esc_html_e( 'Database Issues', 'wp-canary' ); ?></h3>
    <table width="100%" border="0" cellpadding="6" cellspacing="0" style="font-size:13px;border-collapse:collapse;">
    <?php foreach ( array_slice( $db_issues, 0, 5 ) as $issue ) : ?>
      <tr style="border-bottom:1px solid #f0f0f0;">
        <td style="color:<?php echo 'critical' === ( $issue['severity'] ?? '' ) ? '#cb2431' : '#e36209'; ?>;font-weight:bold;width:80px;"><?php echo esc_html( ucfirst( $issue['severity'] ?? 'info' ) ); ?></td>
        <td><?php echo wp_kses_post( $issue['description'] ?? '' ); ?></td>
      </tr>
    <?php endforeach; ?>
    </table>
    <p style="margin:8px 0 0;font-size:12px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpc-database' ) ); ?>" style="color:#0073aa;"><?php esc_html_e( 'View all database issues →', 'wp-canary' ); ?></a></p>
  </td></tr>
  <?php endif; ?>

  <!-- Active Floods -->
  <?php if ( ! empty( $floods ) ) : ?>
  <tr><td style="padding:0 32px 24px;">
    <h3 style="margin:0 0 8px;font-size:14px;color:#cb2431;"><?php esc_html_e( 'Active 404 Floods', 'wp-canary' ); ?></h3>
    <table width="100%" border="0" cellpadding="6" cellspacing="0" style="font-size:13px;border-collapse:collapse;">
    <?php foreach ( $floods as $flood ) : ?>
      <tr style="border-bottom:1px solid #f0f0f0;">
        <td><code><?php echo esc_html( $flood['ip'] ?? '' ); ?></code></td>
        <td><?php echo (int) ( $flood['count'] ?? 0 ); ?> <?php esc_html_e( '404s in 1 hour', 'wp-canary' ); ?></td>
        <?php if ( ! empty( $flood['db_stress'] ) ) : ?><td style="color:#cb2431;"><?php esc_html_e( 'DB stress detected', 'wp-canary' ); ?></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </table>
    <p style="margin:8px 0 0;font-size:12px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpc-404-monitor' ) ); ?>" style="color:#0073aa;"><?php esc_html_e( 'View 404 Monitor →', 'wp-canary' ); ?></a></p>
  </td></tr>
  <?php endif; ?>

  <!-- PHP Flags -->
  <?php if ( ! empty( $php['flags'] ) ) : ?>
  <tr><td style="padding:0 32px 24px;">
    <h3 style="margin:0 0 8px;font-size:14px;color:#e36209;"><?php esc_html_e( 'PHP Resource Flags', 'wp-canary' ); ?></h3>
    <?php foreach ( $php['flags'] as $flag ) : ?>
      <p style="margin:0 0 6px;font-size:13px;padding:6px 10px;background:<?php echo 'critical' === $flag['severity'] ? '#ffeef0' : '#fff8e1'; ?>;border-left:3px solid <?php echo 'critical' === $flag['severity'] ? '#cb2431' : '#e36209'; ?>;"><?php echo esc_html( $flag['message'] ); ?></p>
    <?php endforeach; ?>
  </td></tr>
  <?php endif; ?>

  <!-- Plugin Changes -->
  <?php if ( ! empty( $plugin_changes ) ) : ?>
  <tr><td style="padding:0 32px 24px;">
    <h3 style="margin:0 0 8px;font-size:14px;color:#23282d;"><?php esc_html_e( 'Plugin Changes (Last 24h)', 'wp-canary' ); ?></h3>
    <table width="100%" border="0" cellpadding="6" cellspacing="0" style="font-size:13px;border-collapse:collapse;">
    <?php foreach ( array_slice( $plugin_changes, 0, 10 ) as $change ) : ?>
      <tr style="border-bottom:1px solid #f0f0f0;">
        <td style="color:<?php echo 'activated' === $change['action'] ? '#22863a' : '#cb2431'; ?>;font-weight:bold;width:100px;"><?php echo esc_html( ucfirst( $change['action'] ) ); ?></td>
        <td><?php echo esc_html( $change['plugin_name'] ); ?></td>
        <td style="color:#888;"><?php echo esc_html( $change['triggered_by'] ); ?></td>
      </tr>
    <?php endforeach; ?>
    </table>
    <p style="margin:8px 0 0;font-size:12px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpc-plugin-watchdog' ) ); ?>" style="color:#0073aa;"><?php esc_html_e( 'View Plugin Watchdog →', 'wp-canary' ); ?></a></p>
  </td></tr>
  <?php endif; ?>

  <!-- Plugin Health Warnings -->
  <?php if ( ! empty( $data['plugin_health'] ) ) : ?>
  <tr><td style="padding:0 32px 24px;">
    <h3 style="margin:0 0 8px;font-size:14px;color:#e36209;"><?php esc_html_e( 'Plugin Health Warnings', 'wp-canary' ); ?></h3>
    <table width="100%" border="0" cellpadding="6" cellspacing="0" style="font-size:13px;border-collapse:collapse;">
    <?php foreach ( $data['plugin_health'] as $ph ) : ?>
      <tr style="border-bottom:1px solid #f0f0f0;">
        <td style="font-weight:bold;width:160px;"><?php echo esc_html( $ph['plugin_name'] ); ?></td>
        <td><?php echo esc_html( $ph['message'] ); ?></td>
      </tr>
    <?php endforeach; ?>
    </table>
    <p style="margin:8px 0 0;font-size:12px;"><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpc-plugin-health' ) ); ?>" style="color:#0073aa;"><?php esc_html_e( 'View Plugin Health →', 'wp-canary' ); ?></a></p>
  </td></tr>
  <?php endif; ?>

  <!-- Footer -->
  <tr><td bgcolor="#f8f8f8" style="padding:16px 32px;border-top:1px solid #eeeeee;">
    <p style="margin:0;font-size:12px;color:#888;"><?php esc_html_e( 'You are receiving this because you\'re the site administrator. ', 'wp-canary' ); ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpc-reports' ) ); ?>" style="color:#0073aa;"><?php esc_html_e( 'Manage email settings', 'wp-canary' ); ?></a>.</p>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Build a plain-English paragraph summary.
	 *
	 * @param array $data Report data.
	 * @return string HTML
	 */
	private function build_plain_summary( $data ) {
		$score = $data['health_score'];
		$lines = array();

		if ( $score >= 80 ) {
			$lines[] = '<p style="color:#22863a;font-size:14px;">' . __( '<strong>Your site is healthy.</strong> No critical issues were found in the last 24 hours.', 'wp-canary' ) . '</p>';
		} elseif ( $score >= 50 ) {
			$lines[] = '<p style="color:#e36209;font-size:14px;">' . __( '<strong>Your site has some warnings.</strong> Review the details below and take action where recommended.', 'wp-canary' ) . '</p>';
		} else {
			$lines[] = '<p style="color:#cb2431;font-size:14px;">' . __( '<strong>Your site needs attention.</strong> One or more critical issues require immediate action.', 'wp-canary' ) . '</p>';
		}

		$critical_db = count( array_filter( $data['db_issues'], function ( $i ) {
			return isset( $i['severity'] ) && 'critical' === $i['severity'];
		} ) );
		if ( $critical_db > 0 ) {
			$lines[] = '<p style="font-size:13px;color:#555;">' . sprintf(
				/* translators: count */
				__( '&bull; <strong>%d critical database issue(s)</strong> detected. Plugin data may be corrupted or inaccessible.', 'wp-canary' ),
				$critical_db
			) . '</p>';
		}

		if ( ! empty( $data['active_floods'] ) ) {
			$lines[] = '<p style="font-size:13px;color:#555;">' . sprintf(
				/* translators: count */
				__( '&bull; <strong>%d active 404 flood(s)</strong> detected. These may be affecting site performance.', 'wp-canary' ),
				count( $data['active_floods'] )
			) . '</p>';
		}

		return implode( '', $lines );
	}
}
