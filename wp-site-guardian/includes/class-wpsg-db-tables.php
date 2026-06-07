<?php
/**
 * Creates and manages custom database tables.
 *
 * @package WP_Site_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_DB_Tables
 *
 * Defines and creates all custom tables used by WP Site Guardian.
 */
class WPSG_DB_Tables {

	/**
	 * Create all custom database tables using dbDelta.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		// Table 1: plugin_changes.
		$sql_plugin_changes = "CREATE TABLE {$prefix}wpsg_plugin_changes (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			blog_id bigint(20) unsigned NOT NULL DEFAULT 1,
			timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			action varchar(20) NOT NULL DEFAULT '',
			plugin_slug varchar(255) NOT NULL DEFAULT '',
			plugin_name varchar(255) NOT NULL DEFAULT '',
			triggered_by varchar(100) NOT NULL DEFAULT '',
			ip_address varchar(45) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY blog_id (blog_id),
			KEY timestamp (timestamp),
			KEY action (action)
		) $charset_collate;";

		// Table 2: 404_log.
		$sql_404_log = "CREATE TABLE {$prefix}wpsg_404_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			blog_id bigint(20) unsigned NOT NULL DEFAULT 1,
			timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			request_uri text NOT NULL,
			ip_address varchar(45) NOT NULL DEFAULT '',
			user_agent text NOT NULL,
			referer text NOT NULL,
			is_bot tinyint(1) NOT NULL DEFAULT 0,
			bot_name varchar(100) NOT NULL DEFAULT '',
			bot_category varchar(50) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY blog_id (blog_id),
			KEY timestamp (timestamp),
			KEY ip_address (ip_address),
			KEY is_bot (is_bot)
		) $charset_collate;";

		// Table 3: db_issues.
		$sql_db_issues = "CREATE TABLE {$prefix}wpsg_db_issues (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			blog_id bigint(20) unsigned NOT NULL DEFAULT 1,
			table_name varchar(255) NOT NULL DEFAULT '',
			issue_type varchar(50) NOT NULL DEFAULT '',
			issue_detail text NOT NULL,
			detected_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			status varchar(20) NOT NULL DEFAULT 'open',
			resolved_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY blog_id (blog_id),
			KEY table_name (table_name),
			KEY status (status),
			KEY detected_at (detected_at)
		) $charset_collate;";

		// Table 4: error_log.
		$sql_error_log = "CREATE TABLE {$prefix}wpsg_error_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			blog_id bigint(20) unsigned NOT NULL DEFAULT 1,
			timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			error_type varchar(50) NOT NULL DEFAULT '',
			error_message text NOT NULL,
			file_path text NOT NULL,
			line_number int(11) NOT NULL DEFAULT 0,
			plugin_slug varchar(255) NOT NULL DEFAULT '',
			occurrence_count int(11) NOT NULL DEFAULT 1,
			first_seen datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			last_seen datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY blog_id (blog_id),
			KEY error_type (error_type),
			KEY plugin_slug (plugin_slug),
			KEY last_seen (last_seen)
		) $charset_collate;";

		dbDelta( $sql_plugin_changes );
		dbDelta( $sql_404_log );
		dbDelta( $sql_db_issues );
		dbDelta( $sql_error_log );

		update_option( 'wpsg_db_version', WPSG_VERSION );
	}

	/**
	 * Drop all custom tables.
	 *
	 * Called from uninstall.php only.
	 *
	 * @return void
	 */
	public static function drop_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'wpsg_plugin_changes',
			$wpdb->prefix . 'wpsg_404_log',
			$wpdb->prefix . 'wpsg_db_issues',
			$wpdb->prefix . 'wpsg_error_log',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Get the list of all WPSG table names for the current blog.
	 *
	 * @return array
	 */
	public static function get_table_names() {
		global $wpdb;

		return array(
			'plugin_changes' => $wpdb->prefix . 'wpsg_plugin_changes',
			'log_404'        => $wpdb->prefix . 'wpsg_404_log',
			'db_issues'      => $wpdb->prefix . 'wpsg_db_issues',
			'error_log'      => $wpdb->prefix . 'wpsg_error_log',
		);
	}
}
