<?php
/**
 * Plugin Watchdog view.
 *
 * @package WP_Site_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$changes = array();
if ( wpsg_is_pro() ) {
	$changes = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}wpsg_plugin_changes WHERE blog_id = %d ORDER BY timestamp DESC LIMIT 200", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			get_current_blog_id()
		),
		ARRAY_A
	) ?? array();
}

// Detect mass deactivation events (>= 3 deactivations in same minute).
$mass_events = array();
if ( ! empty( $changes ) ) {
	$by_minute = array();
	foreach ( $changes as $c ) {
		if ( 'deactivated' === $c['action'] ) {
			$minute = substr( $c['timestamp'], 0, 16 );
			$by_minute[ $minute ][] = $c;
		}
	}
	foreach ( $by_minute as $minute => $events ) {
		if ( count( $events ) >= 3 ) {
			$mass_events[ $minute ] = $events;
		}
	}
}

// Current active plugins with their last-changed date.
$active_plugins = (array) get_option( 'active_plugins', array() );
if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
?>
<div class="wrap wpsg-wrap">
	<h1 class="wpsg-page-title">
		<span class="dashicons dashicons-plugins-checked"></span>
		<?php esc_html_e( 'Plugin Watchdog', 'wp-site-guardian' ); ?>
	</h1>

	<?php if ( ! wpsg_is_pro() ) : ?>
	<div class="wpsg-upsell-banner">
		<?php esc_html_e( 'Plugin Watchdog requires WP Site Guardian Pro.', 'wp-site-guardian' ); ?>
		<a href="<?php echo esc_url( WPSG_License::get_upgrade_url() ); ?>" class="button button-primary wpsg-upsell-btn" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade to Pro', 'wp-site-guardian' ); ?></a>
	</div>
	<?php endif; ?>

	<?php if ( ! empty( $mass_events ) ) : ?>
	<div class="wpsg-panel wpsg-panel-alert">
		<h2>⚠ <?php printf( /* translators: count */ esc_html__( 'Mass Deactivation Events Detected (%d)', 'wp-site-guardian' ), count( $mass_events ) ); ?></h2>
		<?php foreach ( $mass_events as $minute => $events ) : ?>
		<p>
			<strong><?php echo esc_html( $minute ); ?></strong> —
			<?php printf( /* translators: count */ esc_html__( '%d plugins deactivated simultaneously:', 'wp-site-guardian' ), count( $events ) ); ?>
			<?php echo esc_html( implode( ', ', array_column( $events, 'plugin_name' ) ) ); ?>
		</p>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<!-- Timeline -->
	<?php if ( ! empty( $changes ) ) : ?>
	<div class="wpsg-panel">
		<h2><?php esc_html_e( 'Plugin Change Timeline (Last 30 Days)', 'wp-site-guardian' ); ?></h2>
		<table class="widefat striped wpsg-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'wp-site-guardian' ); ?></th>
					<th><?php esc_html_e( 'Action', 'wp-site-guardian' ); ?></th>
					<th><?php esc_html_e( 'Plugin', 'wp-site-guardian' ); ?></th>
					<th><?php esc_html_e( 'Triggered By', 'wp-site-guardian' ); ?></th>
					<th><?php esc_html_e( 'IP', 'wp-site-guardian' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $changes as $change ) :
				$is_mass = false;
				foreach ( $mass_events as $minute => $events ) {
					if ( substr( $change['timestamp'], 0, 16 ) === $minute && 'deactivated' === $change['action'] ) {
						$is_mass = true;
						break;
					}
				}
			?>
				<tr class="<?php echo $is_mass ? 'wpsg-row-alert' : ''; ?>">
					<td><?php echo esc_html( $change['timestamp'] ); ?></td>
					<td>
						<span class="wpsg-badge <?php echo 'activated' === $change['action'] ? 'wpsg-badge-ok' : 'wpsg-badge-critical'; ?>">
							<?php echo esc_html( ucfirst( $change['action'] ) ); ?>
						</span>
						<?php if ( $is_mass ) : ?><span class="wpsg-badge wpsg-badge-warning"><?php esc_html_e( 'Mass Event', 'wp-site-guardian' ); ?></span><?php endif; ?>
					</td>
					<td><?php echo esc_html( $change['plugin_name'] ); ?></td>
					<td><?php echo esc_html( $change['triggered_by'] ); ?></td>
					<td><code><?php echo esc_html( $change['ip_address'] ); ?></code></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p style="margin-top:8px;">
			<button class="button wpsg-export-log" data-log-type="plugins"><?php esc_html_e( 'Export as CSV', 'wp-site-guardian' ); ?></button>
		</p>
	</div>
	<?php elseif ( wpsg_is_pro() ) : ?>
	<div class="wpsg-panel">
		<p><?php esc_html_e( 'No plugin changes recorded yet. Changes will appear here after the next plugin activation or deactivation.', 'wp-site-guardian' ); ?></p>
	</div>
	<?php endif; ?>

	<!-- Current Plugins -->
	<div class="wpsg-panel">
		<h2><?php printf( /* translators: count */ esc_html__( 'Currently Active Plugins (%d)', 'wp-site-guardian' ), count( $active_plugins ) ); ?></h2>
		<table class="widefat striped wpsg-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Plugin', 'wp-site-guardian' ); ?></th>
					<th><?php esc_html_e( 'File', 'wp-site-guardian' ); ?></th>
					<th><?php esc_html_e( 'Version', 'wp-site-guardian' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $active_plugins as $plugin_file ) :
				$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
				$data        = file_exists( $plugin_path ) ? get_plugin_data( $plugin_path, false, false ) : array();
			?>
				<tr>
					<td><?php echo esc_html( $data['Name'] ?? $plugin_file ); ?></td>
					<td><code><?php echo esc_html( $plugin_file ); ?></code></td>
					<td><?php echo esc_html( $data['Version'] ?? '—' ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<!-- Alert Settings -->
	<div class="wpsg-panel">
		<h2><?php esc_html_e( 'Alert Settings', 'wp-site-guardian' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'wpsg_save_settings', 'wpsg_settings_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wpsg_email_alerts_enabled"><?php esc_html_e( 'Email Alerts', 'wp-site-guardian' ); ?></label></th>
					<td><input type="checkbox" name="wpsg_email_alerts_enabled" id="wpsg_email_alerts_enabled" value="1" <?php checked( get_option( 'wpsg_email_alerts_enabled', true ) ); ?>></td>
				</tr>
				<tr>
					<th scope="row"><label for="wpsg_alert_email"><?php esc_html_e( 'Alert Email', 'wp-site-guardian' ); ?></label></th>
					<td><input type="email" name="wpsg_alert_email" id="wpsg_alert_email" value="<?php echo esc_attr( get_option( 'wpsg_alert_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text"></td>
				</tr>
			</table>
			<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'wp-site-guardian' ); ?></button></p>
		</form>
	</div>
</div>
