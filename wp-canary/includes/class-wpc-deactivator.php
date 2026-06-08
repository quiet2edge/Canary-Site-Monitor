<?php
/**
 * Fired during plugin deactivation.
 *
 * @package WP_Site_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_Deactivator
 *
 * Clears scheduled cron jobs on deactivation.
 * Data is NOT removed here; see uninstall.php.
 */
class WPC_Deactivator {

	/**
	 * Run deactivation logic.
	 *
	 * @return void
	 */
	public static function deactivate() {
		if ( is_multisite() ) {
			self::deactivate_multisite();
		} else {
			self::clear_cron_jobs();
		}

		flush_rewrite_rules();
	}

	/**
	 * Deactivate on all sites in the network.
	 *
	 * @return void
	 */
	private static function deactivate_multisite() {
		global $wpdb;

		$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			self::clear_cron_jobs();
			restore_current_blog();
		}
	}

	/**
	 * Clear all WPC-scheduled cron jobs.
	 *
	 * @return void
	 */
	private static function clear_cron_jobs() {
		$cron_hooks = array(
			'wpc_daily_digest',
			'wpc_db_scan',
			'wpc_plugin_watchdog',
			'wpc_cron_health_check',
			'wpc_ad_monitor',
			'wpc_plugin_health_scan',
		);

		foreach ( $cron_hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
			// Clear all instances to be safe.
			wp_clear_scheduled_hook( $hook );
		}
	}
}
