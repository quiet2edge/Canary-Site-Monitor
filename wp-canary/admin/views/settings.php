<?php
/**
 * Settings view.
 *
 * @package WP_Canary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

settings_errors( 'wpc_settings' );

// Handle "Delete All Data" action.
if ( isset( $_POST['wpc_delete_data_nonce'] )
	&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpc_delete_data_nonce'] ) ), 'wpc_delete_data' )
	&& current_user_can( 'manage_options' )
	&& isset( $_POST['wpc_confirm_delete'] )
	&& '1' === $_POST['wpc_confirm_delete'] ) {

	global $wpdb;

	// Drop custom tables.
	require_once WPC_PLUGIN_DIR . 'includes/class-wpc-db-tables.php';
	WPC_DB_Tables::drop_tables();

	// Delete all wpc_ options.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpc_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All WP Canary data has been deleted.', 'wp-canary' ) . '</p></div>';
}
?>
<div class="wrap wpc-wrap">
	<h1 class="wpc-page-title">
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'Settings', 'wp-canary' ); ?>
	</h1>

	<form method="post">
		<?php wp_nonce_field( 'wpc_save_settings', 'wpc_settings_nonce' ); ?>

		<div class="wpc-panel">
			<h2><?php esc_html_e( '404 Monitoring', 'wp-canary' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wpc_flood_threshold"><?php esc_html_e( 'Flood Threshold (per hour)', 'wp-canary' ); ?></label></th>
					<td>
						<input type="number" name="wpc_flood_threshold" id="wpc_flood_threshold" value="<?php echo (int) get_option( 'wpc_flood_threshold', 50 ); ?>" min="1" max="10000" class="small-text">
						<p class="description"><?php esc_html_e( 'Alert when a single IP generates this many 404s in a rolling one-hour window.', 'wp-canary' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="wpc-panel">
			<h2><?php esc_html_e( 'Email Alerts', 'wp-canary' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wpc_email_alerts_enabled"><?php esc_html_e( 'Enable Alerts', 'wp-canary' ); ?></label></th>
					<td><input type="checkbox" name="wpc_email_alerts_enabled" id="wpc_email_alerts_enabled" value="1" <?php checked( get_option( 'wpc_email_alerts_enabled', true ) ); ?>></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpc_alert_email"><?php esc_html_e( 'Alert Email', 'wp-canary' ); ?></label></th>
					<td><input type="email" name="wpc_alert_email" id="wpc_alert_email" value="<?php echo esc_attr( get_option( 'wpc_alert_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text"></td>
				</tr>
			</table>
		</div>

		<div class="wpc-panel">
			<h2><?php esc_html_e( 'Debug Log', 'wp-canary' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wpc_debug_log_path"><?php esc_html_e( 'Log Path Override', 'wp-canary' ); ?></label></th>
					<td>
						<input type="text" name="wpc_debug_log_path" id="wpc_debug_log_path" value="<?php echo esc_attr( get_option( 'wpc_debug_log_path', '' ) ); ?>" class="large-text" placeholder="<?php echo esc_attr( WP_CONTENT_DIR . '/debug.log' ); ?>">
						<p class="description"><?php esc_html_e( 'Leave blank to use the default wp-content/debug.log path.', 'wp-canary' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="wpc-panel">
			<h2><?php esc_html_e( 'Data Retention', 'wp-canary' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wpc_data_retention_days"><?php esc_html_e( 'Retention Period (days)', 'wp-canary' ); ?></label></th>
					<td>
						<input type="number" name="wpc_data_retention_days" id="wpc_data_retention_days" value="<?php echo (int) get_option( 'wpc_data_retention_days', 30 ); ?>" min="1" max="365" class="small-text">
						<p class="description"><?php esc_html_e( 'Logs older than this many days will be automatically pruned.', 'wp-canary' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpc_keep_data"><?php esc_html_e( 'Keep Data on Uninstall', 'wp-canary' ); ?></label></th>
					<td>
						<input type="checkbox" name="wpc_keep_data" id="wpc_keep_data" value="1" <?php checked( get_option( 'wpc_keep_data', false ) ); ?>>
						<p class="description"><?php esc_html_e( 'If checked, all plugin data will be preserved when the plugin is uninstalled.', 'wp-canary' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'wp-canary' ); ?></button>
		</p>
	</form>

	<!-- License Section -->
	<div class="wpc-panel">
		<h2><?php esc_html_e( 'License', 'wp-canary' ); ?></h2>
		<p>
			<?php esc_html_e( 'Plan:', 'wp-canary' ); ?>
			<strong><?php echo esc_html( WPC_License::get_plan_label() ); ?></strong>
		</p>
		<?php if ( wpc_is_pro() ) : ?>
		<p><a href="<?php echo esc_url( WPC_License::get_account_url() ); ?>" class="button"><?php esc_html_e( 'Manage License', 'wp-canary' ); ?></a></p>
		<?php else : ?>
		<p><a href="<?php echo esc_url( WPC_License::get_upgrade_url() ); ?>" class="button button-primary"><?php esc_html_e( 'Upgrade to Pro', 'wp-canary' ); ?></a></p>
		<?php endif; ?>
	</div>

	<!-- Danger Zone -->
	<div class="wpc-panel wpc-panel-danger">
		<h2><?php esc_html_e( 'Danger Zone', 'wp-canary' ); ?></h2>
		<p><?php esc_html_e( 'Permanently delete all WP Canary logs, settings, and custom database tables. This cannot be undone.', 'wp-canary' ); ?></p>
		<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Are you absolutely sure? All WP Canary data will be permanently deleted.', 'wp-canary' ) ); ?>');">
			<?php wp_nonce_field( 'wpc_delete_data', 'wpc_delete_data_nonce' ); ?>
			<input type="hidden" name="wpc_confirm_delete" value="1">
			<button type="submit" class="button button-secondary wpc-btn-danger"><?php esc_html_e( 'Delete All Data', 'wp-canary' ); ?></button>
		</form>
	</div>
</div>
