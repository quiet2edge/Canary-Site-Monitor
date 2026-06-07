<?php
/**
 * Settings view.
 *
 * @package WP_Site_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

settings_errors( 'wpsg_settings' );

// Handle "Delete All Data" action.
if ( isset( $_POST['wpsg_delete_data_nonce'] )
	&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpsg_delete_data_nonce'] ) ), 'wpsg_delete_data' )
	&& current_user_can( 'manage_options' )
	&& isset( $_POST['wpsg_confirm_delete'] )
	&& '1' === $_POST['wpsg_confirm_delete'] ) {

	global $wpdb;

	// Drop custom tables.
	require_once WPSG_PLUGIN_DIR . 'includes/class-wpsg-db-tables.php';
	WPSG_DB_Tables::drop_tables();

	// Delete all wpsg_ options.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpsg_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All WP Site Guardian data has been deleted.', 'wp-site-guardian' ) . '</p></div>';
}
?>
<div class="wrap wpsg-wrap">
	<h1 class="wpsg-page-title">
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'Settings', 'wp-site-guardian' ); ?>
	</h1>

	<form method="post">
		<?php wp_nonce_field( 'wpsg_save_settings', 'wpsg_settings_nonce' ); ?>

		<div class="wpsg-panel">
			<h2><?php esc_html_e( '404 Monitoring', 'wp-site-guardian' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wpsg_flood_threshold"><?php esc_html_e( 'Flood Threshold (per hour)', 'wp-site-guardian' ); ?></label></th>
					<td>
						<input type="number" name="wpsg_flood_threshold" id="wpsg_flood_threshold" value="<?php echo (int) get_option( 'wpsg_flood_threshold', 50 ); ?>" min="1" max="10000" class="small-text">
						<p class="description"><?php esc_html_e( 'Alert when a single IP generates this many 404s in a rolling one-hour window.', 'wp-site-guardian' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="wpsg-panel">
			<h2><?php esc_html_e( 'Email Alerts', 'wp-site-guardian' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wpsg_email_alerts_enabled"><?php esc_html_e( 'Enable Alerts', 'wp-site-guardian' ); ?></label></th>
					<td><input type="checkbox" name="wpsg_email_alerts_enabled" id="wpsg_email_alerts_enabled" value="1" <?php checked( get_option( 'wpsg_email_alerts_enabled', true ) ); ?>></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpsg_alert_email"><?php esc_html_e( 'Alert Email', 'wp-site-guardian' ); ?></label></th>
					<td><input type="email" name="wpsg_alert_email" id="wpsg_alert_email" value="<?php echo esc_attr( get_option( 'wpsg_alert_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text"></td>
				</tr>
			</table>
		</div>

		<div class="wpsg-panel">
			<h2><?php esc_html_e( 'Debug Log', 'wp-site-guardian' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wpsg_debug_log_path"><?php esc_html_e( 'Log Path Override', 'wp-site-guardian' ); ?></label></th>
					<td>
						<input type="text" name="wpsg_debug_log_path" id="wpsg_debug_log_path" value="<?php echo esc_attr( get_option( 'wpsg_debug_log_path', '' ) ); ?>" class="large-text" placeholder="<?php echo esc_attr( WP_CONTENT_DIR . '/debug.log' ); ?>">
						<p class="description"><?php esc_html_e( 'Leave blank to use the default wp-content/debug.log path.', 'wp-site-guardian' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="wpsg-panel">
			<h2><?php esc_html_e( 'Data Retention', 'wp-site-guardian' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wpsg_data_retention_days"><?php esc_html_e( 'Retention Period (days)', 'wp-site-guardian' ); ?></label></th>
					<td>
						<input type="number" name="wpsg_data_retention_days" id="wpsg_data_retention_days" value="<?php echo (int) get_option( 'wpsg_data_retention_days', 30 ); ?>" min="1" max="365" class="small-text">
						<p class="description"><?php esc_html_e( 'Logs older than this many days will be automatically pruned.', 'wp-site-guardian' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpsg_keep_data"><?php esc_html_e( 'Keep Data on Uninstall', 'wp-site-guardian' ); ?></label></th>
					<td>
						<input type="checkbox" name="wpsg_keep_data" id="wpsg_keep_data" value="1" <?php checked( get_option( 'wpsg_keep_data', false ) ); ?>>
						<p class="description"><?php esc_html_e( 'If checked, all plugin data will be preserved when the plugin is uninstalled.', 'wp-site-guardian' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'wp-site-guardian' ); ?></button>
		</p>
	</form>

	<!-- License Section -->
	<div class="wpsg-panel">
		<h2><?php esc_html_e( 'License', 'wp-site-guardian' ); ?></h2>
		<p>
			<?php esc_html_e( 'Plan:', 'wp-site-guardian' ); ?>
			<strong><?php echo esc_html( WPSG_License::get_plan_label() ); ?></strong>
		</p>
		<?php if ( wpsg_is_pro() ) : ?>
		<p><a href="<?php echo esc_url( WPSG_License::get_account_url() ); ?>" class="button" target="_blank" rel="noopener"><?php esc_html_e( 'Manage License', 'wp-site-guardian' ); ?></a></p>
		<?php else : ?>
		<p><a href="<?php echo esc_url( WPSG_License::get_upgrade_url() ); ?>" class="button button-primary" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade to Pro', 'wp-site-guardian' ); ?></a></p>
		<?php endif; ?>
	</div>

	<!-- Danger Zone -->
	<div class="wpsg-panel wpsg-panel-danger">
		<h2><?php esc_html_e( 'Danger Zone', 'wp-site-guardian' ); ?></h2>
		<p><?php esc_html_e( 'Permanently delete all WP Site Guardian logs, settings, and custom database tables. This cannot be undone.', 'wp-site-guardian' ); ?></p>
		<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Are you absolutely sure? All WP Site Guardian data will be permanently deleted.', 'wp-site-guardian' ) ); ?>');">
			<?php wp_nonce_field( 'wpsg_delete_data', 'wpsg_delete_data_nonce' ); ?>
			<input type="hidden" name="wpsg_confirm_delete" value="1">
			<button type="submit" class="button button-secondary wpsg-btn-danger"><?php esc_html_e( 'Delete All Data', 'wp-site-guardian' ); ?></button>
		</form>
	</div>
</div>
