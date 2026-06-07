<?php
/**
 * Reports view.
 *
 * @package WP_Site_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle form save.
if ( isset( $_POST['wpsg_reports_nonce'] )
	&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpsg_reports_nonce'] ) ), 'wpsg_save_reports' )
	&& current_user_can( 'manage_options' ) ) {

	if ( isset( $_POST['wpsg_digest_email'] ) ) {
		update_option( 'wpsg_digest_email', sanitize_email( wp_unslash( $_POST['wpsg_digest_email'] ) ) );
	}
	if ( isset( $_POST['wpsg_digest_time'] ) ) {
		update_option( 'wpsg_digest_time', sanitize_text_field( wp_unslash( $_POST['wpsg_digest_time'] ) ) );
	}
	update_option( 'wpsg_digest_enabled', isset( $_POST['wpsg_digest_enabled'] ) ? true : false );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Report settings saved.', 'wp-site-guardian' ) . '</p></div>';
}
?>
<div class="wrap wpsg-wrap">
	<h1 class="wpsg-page-title">
		<span class="dashicons dashicons-email-alt"></span>
		<?php esc_html_e( 'Reports', 'wp-site-guardian' ); ?>
	</h1>

	<!-- Email Digest Settings -->
	<div class="wpsg-panel">
		<h2><?php esc_html_e( 'Daily Email Digest', 'wp-site-guardian' ); ?></h2>

		<?php if ( ! wpsg_is_pro() ) : ?>
		<div class="wpsg-upsell-inline">
			<?php esc_html_e( 'Daily email digests require WP Site Guardian Pro.', 'wp-site-guardian' ); ?>
			<a href="<?php echo esc_url( WPSG_License::get_upgrade_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade', 'wp-site-guardian' ); ?></a>
		</div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'wpsg_save_reports', 'wpsg_reports_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wpsg_digest_enabled"><?php esc_html_e( 'Enable Daily Digest', 'wp-site-guardian' ); ?></label></th>
					<td><input type="checkbox" name="wpsg_digest_enabled" id="wpsg_digest_enabled" value="1" <?php checked( get_option( 'wpsg_digest_enabled', true ) ); ?> <?php disabled( ! wpsg_is_pro() ); ?>></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpsg_digest_email"><?php esc_html_e( 'Recipient Email', 'wp-site-guardian' ); ?></label></th>
					<td><input type="email" name="wpsg_digest_email" id="wpsg_digest_email" value="<?php echo esc_attr( get_option( 'wpsg_digest_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text" <?php disabled( ! wpsg_is_pro() ); ?>></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpsg_digest_time"><?php esc_html_e( 'Send Time (site timezone)', 'wp-site-guardian' ); ?></label></th>
					<td>
						<input type="time" name="wpsg_digest_time" id="wpsg_digest_time" value="<?php echo esc_attr( get_option( 'wpsg_digest_time', '07:00' ) ); ?>" <?php disabled( ! wpsg_is_pro() ); ?>>
						<p class="description"><?php printf( /* translators: timezone */ esc_html__( 'Site timezone: %s', 'wp-site-guardian' ), esc_html( wp_timezone_string() ) ); ?></p>
					</td>
				</tr>
			</table>
			<?php if ( wpsg_is_pro() ) : ?>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'wp-site-guardian' ); ?></button>
				<button type="button" id="wpsg-send-test-digest" class="button"><?php esc_html_e( 'Send Test Digest', 'wp-site-guardian' ); ?></button>
			</p>
			<?php endif; ?>
		</form>
		<div id="wpsg-digest-result" class="wpsg-notice" style="display:none;"></div>
	</div>

	<!-- Scan Schedule -->
	<div class="wpsg-panel">
		<h2><?php esc_html_e( 'Scan Schedule', 'wp-site-guardian' ); ?></h2>
		<table class="widefat striped wpsg-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Job', 'wp-site-guardian' ); ?></th>
					<th><?php esc_html_e( 'Schedule', 'wp-site-guardian' ); ?></th>
					<th><?php esc_html_e( 'Next Run', 'wp-site-guardian' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			$cron_hooks = array(
				'wpsg_db_scan'          => __( 'Database Scan', 'wp-site-guardian' ),
				'wpsg_plugin_watchdog'  => __( 'Plugin Watchdog', 'wp-site-guardian' ),
				'wpsg_daily_digest'     => __( 'Daily Digest Email', 'wp-site-guardian' ),
				'wpsg_cron_health_check' => __( 'Cron Health Check', 'wp-site-guardian' ),
				'wpsg_ad_monitor'       => __( 'Ad Monitor', 'wp-site-guardian' ),
			);
			foreach ( $cron_hooks as $hook => $label ) :
				$next = wp_next_scheduled( $hook );
			?>
				<tr>
					<td><?php echo esc_html( $label ); ?></td>
					<td><?php echo esc_html( $hook ); ?></td>
					<td><?php echo $next ? esc_html( gmdate( 'Y-m-d H:i:s', $next ) . ' UTC' ) : '<em>' . esc_html__( 'Not scheduled', 'wp-site-guardian' ) . '</em>'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<!-- Log Export -->
	<div class="wpsg-panel">
		<h2><?php esc_html_e( 'Export Logs', 'wp-site-guardian' ); ?></h2>
		<p>
			<button class="button wpsg-export-log" data-log-type="404"><?php esc_html_e( 'Export 404 Log (CSV)', 'wp-site-guardian' ); ?></button>
			<button class="button wpsg-export-log" data-log-type="plugins" style="margin-left:8px;"><?php esc_html_e( 'Export Plugin Changes (CSV)', 'wp-site-guardian' ); ?></button>
			<button class="button wpsg-export-log" data-log-type="db" style="margin-left:8px;"><?php esc_html_e( 'Export DB Issues (CSV)', 'wp-site-guardian' ); ?></button>
		</p>
	</div>
</div>
