<?php
/**
 * Reports view.
 *
 * @package WP_Canary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle form save.
if ( isset( $_POST['wpc_reports_nonce'] )
	&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpc_reports_nonce'] ) ), 'wpc_save_reports' )
	&& current_user_can( 'manage_options' ) ) {

	if ( isset( $_POST['wpc_digest_email'] ) ) {
		update_option( 'wpc_digest_email', sanitize_email( wp_unslash( $_POST['wpc_digest_email'] ) ) );
	}
	if ( isset( $_POST['wpc_digest_time'] ) ) {
		update_option( 'wpc_digest_time', sanitize_text_field( wp_unslash( $_POST['wpc_digest_time'] ) ) );
	}
	update_option( 'wpc_digest_enabled', isset( $_POST['wpc_digest_enabled'] ) ? true : false );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Report settings saved.', 'wp-canary' ) . '</p></div>';
}
?>
<div class="wrap wpc-wrap">
	<h1 class="wpc-page-title">
		<span class="dashicons dashicons-email-alt"></span>
		<?php esc_html_e( 'Reports', 'wp-canary' ); ?>
	</h1>

	<!-- Email Digest Settings -->
	<div class="wpc-panel">
		<h2><?php esc_html_e( 'Daily Email Digest', 'wp-canary' ); ?></h2>

		<?php if ( ! wpc_is_pro() ) : ?>
		<div class="wpc-upsell-inline">
			<?php esc_html_e( 'Daily email digests require WP Canary Pro.', 'wp-canary' ); ?>
			<a href="<?php echo esc_url( WPC_License::get_upgrade_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade', 'wp-canary' ); ?></a>
		</div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'wpc_save_reports', 'wpc_reports_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wpc_digest_enabled"><?php esc_html_e( 'Enable Daily Digest', 'wp-canary' ); ?></label></th>
					<td><input type="checkbox" name="wpc_digest_enabled" id="wpc_digest_enabled" value="1" <?php checked( get_option( 'wpc_digest_enabled', true ) ); ?> <?php disabled( ! wpc_is_pro() ); ?>></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpc_digest_email"><?php esc_html_e( 'Recipient Email', 'wp-canary' ); ?></label></th>
					<td><input type="email" name="wpc_digest_email" id="wpc_digest_email" value="<?php echo esc_attr( get_option( 'wpc_digest_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text" <?php disabled( ! wpc_is_pro() ); ?>></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpc_digest_time"><?php esc_html_e( 'Send Time (site timezone)', 'wp-canary' ); ?></label></th>
					<td>
						<input type="time" name="wpc_digest_time" id="wpc_digest_time" value="<?php echo esc_attr( get_option( 'wpc_digest_time', '07:00' ) ); ?>" <?php disabled( ! wpc_is_pro() ); ?>>
						<p class="description"><?php printf( /* translators: timezone */ esc_html__( 'Site timezone: %s', 'wp-canary' ), esc_html( wp_timezone_string() ) ); ?></p>
					</td>
				</tr>
			</table>
			<?php if ( wpc_is_pro() ) : ?>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'wp-canary' ); ?></button>
				<button type="button" id="wpc-send-test-digest" class="button"><?php esc_html_e( 'Send Test Digest', 'wp-canary' ); ?></button>
			</p>
			<?php endif; ?>
		</form>
		<div id="wpc-digest-result" class="wpc-notice" style="display:none;"></div>
	</div>

	<!-- Scan Schedule -->
	<div class="wpc-panel">
		<h2><?php esc_html_e( 'Scan Schedule', 'wp-canary' ); ?></h2>
		<table class="widefat striped wpc-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Job', 'wp-canary' ); ?></th>
					<th><?php esc_html_e( 'Schedule', 'wp-canary' ); ?></th>
					<th><?php esc_html_e( 'Next Run', 'wp-canary' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			$cron_hooks = array(
				'wpc_db_scan'          => __( 'Database Scan', 'wp-canary' ),
				'wpc_plugin_watchdog'  => __( 'Plugin Watchdog', 'wp-canary' ),
				'wpc_daily_digest'     => __( 'Daily Digest Email', 'wp-canary' ),
				'wpc_cron_health_check' => __( 'Cron Health Check', 'wp-canary' ),
				'wpc_ad_monitor'       => __( 'Ad Monitor', 'wp-canary' ),
			);
			foreach ( $cron_hooks as $hook => $label ) :
				$next = wp_next_scheduled( $hook );
			?>
				<tr>
					<td><?php echo esc_html( $label ); ?></td>
					<td><?php echo esc_html( $hook ); ?></td>
					<td><?php echo $next ? esc_html( gmdate( 'Y-m-d H:i:s', $next ) . ' UTC' ) : '<em>' . esc_html__( 'Not scheduled', 'wp-canary' ) . '</em>'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<!-- Log Export -->
	<div class="wpc-panel">
		<h2><?php esc_html_e( 'Export Logs', 'wp-canary' ); ?></h2>
		<p>
			<button class="button wpc-export-log" data-log-type="404"><?php esc_html_e( 'Export 404 Log (CSV)', 'wp-canary' ); ?></button>
			<button class="button wpc-export-log" data-log-type="plugins" style="margin-left:8px;"><?php esc_html_e( 'Export Plugin Changes (CSV)', 'wp-canary' ); ?></button>
			<button class="button wpc-export-log" data-log-type="db" style="margin-left:8px;"><?php esc_html_e( 'Export DB Issues (CSV)', 'wp-canary' ); ?></button>
		</p>
	</div>
</div>
