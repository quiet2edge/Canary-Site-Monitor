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
 * Respects the wpsg_keep_data option — if set to true, data is preserved.
 */
function wpsg_do_uninstall() {
	global $wpdb;

	// Check if we should preserve data.
	$keep_data = get_option( 'wpsg_keep_data', false );
	if ( $keep_data ) {
		return;
	}

	// Load the DB tables class.
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpsg-db-tables.php';

	if ( is_multisite() ) {
		$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			wpsg_delete_site_data();
			restore_current_blog();
		}

		// Network-level options.
		delete_site_option( 'wpsg_version' );
		delete_site_option( 'wpsg_network_active' );
	} else {
		wpsg_delete_site_data();
	}
}

/**
 * Delete all plugin data for the current site.
 *
 * @return void
 */
function wpsg_delete_site_data() {
	global $wpdb;

	// Drop custom tables.
	WPSG_DB_Tables::drop_tables();

	// Delete all wpsg_* options.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpsg\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

	// Clear all WPSG cron jobs.
	$cron_hooks = array(
		'wpsg_daily_digest',
		'wpsg_db_scan',
		'wpsg_plugin_watchdog',
		'wpsg_cron_health_check',
		'wpsg_ad_monitor',
	);

	foreach ( $cron_hooks as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}

	// Delete transients.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpsg_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wpsg_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
}

wpsg_do_uninstall();
