<?php
/**
 * Fired during plugin activation.
 *
 * @package WP_Site_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_Activator
 *
 * Handles all logic that must run when the plugin is activated.
 */
class WPSG_Activator {

	/**
	 * Run activation logic.
	 *
	 * Creates custom database tables, sets default options,
	 * and schedules all cron jobs.
	 *
	 * @return void
	 */
	public static function activate() {
		require_once WPSG_PLUGIN_DIR . 'includes/class-wpsg-db-tables.php';

		if ( is_multisite() ) {
			self::activate_multisite();
		} else {
			self::activate_single_site();
		}

		// Set plugin version.
		update_option( 'wpsg_version', WPSG_VERSION );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Activate for a single-site install.
	 *
	 * @return void
	 */
	private static function activate_single_site() {
		WPSG_DB_Tables::create_tables();
		self::set_default_options();
		self::schedule_cron_jobs();
	}

	/**
	 * Activate for a multisite network.
	 *
	 * Creates tables and sets options for each blog.
	 *
	 * @return void
	 */
	private static function activate_multisite() {
		global $wpdb;

		$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			WPSG_DB_Tables::create_tables();
			self::set_default_options();
			self::schedule_cron_jobs();
			restore_current_blog();
		}

		// Network-level options.
		update_site_option( 'wpsg_version', WPSG_VERSION );
		update_site_option( 'wpsg_network_active', true );
	}

	/**
	 * Set default plugin options.
	 *
	 * Uses add_option so existing values are not overwritten.
	 *
	 * @return void
	 */
	private static function set_default_options() {
		$defaults = array(
			'wpsg_flood_threshold'       => 50,
			'wpsg_email_alerts_enabled'  => true,
			'wpsg_alert_email'           => get_option( 'admin_email' ),
			'wpsg_digest_enabled'        => true,
			'wpsg_digest_time'           => '07:00',
			'wpsg_digest_email'          => get_option( 'admin_email' ),
			'wpsg_data_retention_days'   => 30,
			'wpsg_debug_log_path'        => '',
			'wpsg_keep_data'             => false,
			'wpsg_last_known_plugins'    => maybe_serialize( get_option( 'active_plugins', array() ) ),
			'wpsg_last_db_scan_time'     => '',
			'wpsg_last_db_scan_results'  => array(),
		);

		foreach ( $defaults as $option_name => $default_value ) {
			add_option( $option_name, $default_value );
		}
	}

	/**
	 * Schedule all cron jobs.
	 *
	 * @return void
	 */
	private static function schedule_cron_jobs() {
		$site_timezone = wp_timezone();
		$now           = new DateTime( 'now', $site_timezone );

		// Daily digest at 7:00 AM site time.
		if ( ! wp_next_scheduled( 'wpsg_daily_digest' ) ) {
			$digest_time = new DateTime( 'today 07:00', $site_timezone );
			if ( $digest_time <= $now ) {
				$digest_time->modify( '+1 day' );
			}
			wp_schedule_event( $digest_time->getTimestamp(), 'daily', 'wpsg_daily_digest' );
		}

		// Daily DB scan.
		if ( ! wp_next_scheduled( 'wpsg_db_scan' ) ) {
			wp_schedule_event( time() + 60, 'daily', 'wpsg_db_scan' );
		}

		// Plugin watchdog every 15 minutes.
		if ( ! wp_next_scheduled( 'wpsg_plugin_watchdog' ) ) {
			wp_schedule_event( time() + 30, 'wpsg_every_15min', 'wpsg_plugin_watchdog' );
		}

		// Cron health check hourly.
		if ( ! wp_next_scheduled( 'wpsg_cron_health_check' ) ) {
			wp_schedule_event( time() + 120, 'hourly', 'wpsg_cron_health_check' );
		}

		// Ad monitor — runs hourly (fires analysis only during off-hours).
		if ( ! wp_next_scheduled( 'wpsg_ad_monitor' ) ) {
			wp_schedule_event( time() + 180, 'hourly', 'wpsg_ad_monitor' );
		}
	}
}
