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
 * Class WPSG_Deactivator
 *
 * Clears scheduled cron jobs on deactivation.
 * Data is NOT removed here; see uninstall.php.
 */
class WPSG_Deactivator {

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
	 * Clear all WPSG-scheduled cron jobs.
	 *
	 * @return void
	 */
	private static function clear_cron_jobs() {
		$cron_hooks = array(
			'wpsg_daily_digest',
			'wpsg_db_scan',
			'wpsg_plugin_watchdog',
			'wpsg_cron_health_check',
			'wpsg_ad_monitor',
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
