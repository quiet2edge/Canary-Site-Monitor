<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WP_Site_Guardian
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Main uninstall routine.
 *
 * Respects the wpc_keep_data option — if set to true, data is preserved.
 */
function wpc_do_uninstall() {
	global $wpdb;

	// Check if we should preserve data.
	$keep_data = get_option( 'wpc_keep_data', false );
	if ( $keep_data ) {
		return;
	}

	// Load the DB tables class.
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpc-db-tables.php';

	if ( is_multisite() ) {
		$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			wpc_delete_site_data();
			restore_current_blog();
		}

		// Network-level options.
		delete_site_option( 'wpc_version' );
		delete_site_option( 'wpc_network_active' );
	} else {
		wpc_delete_site_data();
	}
}

/**
 * Delete all plugin data for the current site.
 *
 * @return void
 */
function wpc_delete_site_data() {
	global $wpdb;

	// Drop custom tables.
	WPC_DB_Tables::drop_tables();

	// Delete all wpc_* options.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpc_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

	// Clear all WPC cron jobs.
	$cron_hooks = array(
		'wpc_daily_digest',
		'wpc_db_scan',
		'wpc_plugin_watchdog',
		'wpc_cron_health_check',
		'wpc_ad_monitor',
	);

	foreach ( $cron_hooks as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}

	// Delete transients.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpc_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wpc_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
}

wpc_do_uninstall();
