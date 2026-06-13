<?php
/**
 * Database repair (Pro).
 *
 * @package WP_Canary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_DB_Repair
 *
 * Performs targeted repairs on issues found by WPC_DB_Scanner.
 * Requires explicit admin action — never auto-runs.
 */
class WPC_DB_Repair {

	/**
	 * Attempt to repair a single issue.
	 *
	 * @param array $issue Issue array as returned by WPC_DB_Scanner::scan_all().
	 * @return array { success: bool, message: string }
	 */
	public static function repair_issue( $issue ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Insufficient permissions.', 'wp-canary' ),
			);
		}

		$type = isset( $issue['type'] ) ? $issue['type'] : '';

		switch ( $type ) {
			case 'missing_column':
				return self::repair_missing_column( $issue );

			case 'myisam_engine':
				return self::repair_engine( $issue );

			default:
				return array(
					'success' => false,
					'message' => sprintf(
						/* translators: issue type string */
						__( 'No automatic repair available for issue type: %s', 'wp-canary' ),
						esc_html( $type )
					),
				);
		}
	}

	/**
	 * Add a missing column to a table.
	 *
	 * @param array $issue Issue data.
	 * @return array
	 */
	private static function repair_missing_column( $issue ) {
		global $wpdb;

		$table_name    = isset( $issue['table'] ) ? $issue['table'] : '';
		$column        = isset( $issue['column'] ) ? $issue['column'] : '';
		$expected_type = isset( $issue['expected_type'] ) ? $issue['expected_type'] : 'text';

		if ( empty( $table_name ) || empty( $column ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid issue data — missing table or column name.', 'wp-canary' ),
			);
		}

		// Build the ALTER TABLE SQL.
		$sql = "ALTER TABLE `{$table_name}` ADD COLUMN `{$column}` {$expected_type}"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query( $sql );

		if ( false === $result ) {
			$message = sprintf(
				/* translators: 1: column name, 2: table name, 3: DB error */
				__( 'Failed to add column %1$s to %2$s. DB error: %3$s', 'wp-canary' ),
				$column,
				$table_name,
				$wpdb->last_error
			);
			self::log_repair( $issue, 'failed', $message );
			return array( 'success' => false, 'message' => $message );
		}

		$message = sprintf(
			/* translators: 1: column name, 2: table name */
			__( 'Successfully added column %1$s to %2$s.', 'wp-canary' ),
			$column,
			$table_name
		);
		self::log_repair( $issue, 'resolved', $message );
		return array( 'success' => true, 'message' => $message );
	}

	/**
	 * Convert a table's storage engine to InnoDB.
	 *
	 * @param array $issue Issue data.
	 * @return array
	 */
	private static function repair_engine( $issue ) {
		global $wpdb;

		$table_name = isset( $issue['table'] ) ? $issue['table'] : '';

		if ( empty( $table_name ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid issue data — missing table name.', 'wp-canary' ),
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query( "ALTER TABLE `{$table_name}` ENGINE=InnoDB" );

		if ( false === $result ) {
			$message = sprintf(
				/* translators: 1: table name, 2: DB error */
				__( 'Failed to convert %1$s to InnoDB. DB error: %2$s', 'wp-canary' ),
				$table_name,
				$wpdb->last_error
			);
			self::log_repair( $issue, 'failed', $message );
			return array( 'success' => false, 'message' => $message );
		}

		$message = sprintf(
			/* translators: table name */
			__( 'Successfully converted %s to InnoDB engine.', 'wp-canary' ),
			$table_name
		);
		self::log_repair( $issue, 'resolved', $message );
		return array( 'success' => true, 'message' => $message );
	}

	/**
	 * Convert a table's character set and collation to match the site default.
	 *
	 * Caller is responsible for verifying nonce (wpc_admin_nonce) and manage_options capability
	 * before invoking this method.
	 *
	 * @param string $table_name       Full table name (with prefix).
	 * @param string $target_collation Target collation e.g. utf8mb4_unicode_ci.
	 * @return true|WP_Error
	 */
	public static function repair_collation( $table_name, $target_collation ) {
		global $wpdb;

		$existing_tables = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! in_array( $table_name, $existing_tables, true ) ) {
			return new WP_Error( 'invalid_table', __( 'Table does not exist in this database.', 'wp-canary' ) );
		}

		if ( ! preg_match( '/^[a-z0-9_]+$/i', $target_collation ) ) {
			return new WP_Error( 'invalid_collation', __( 'Collation name contains invalid characters.', 'wp-canary' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query( "ALTER TABLE `{$table_name}` CONVERT TO CHARACTER SET utf8mb4 COLLATE {$target_collation}" );

		$issue = array(
			'table' => $table_name,
			'type'  => 'collation_mismatch',
		);

		if ( false === $result ) {
			$message = sprintf(
				/* translators: 1: table name, 2: DB error */
				__( 'Failed to convert collation on %1$s. DB error: %2$s', 'wp-canary' ),
				$table_name,
				$wpdb->last_error
			);
			self::log_repair( $issue, 'failed', $message );
			return new WP_Error( 'repair_failed', $message );
		}

		$message = sprintf(
			/* translators: 1: table name, 2: collation */
			__( 'Successfully converted %1$s to %2$s.', 'wp-canary' ),
			$table_name,
			$target_collation
		);
		self::log_repair( $issue, 'resolved', $message );
		return true;
	}

	/**
	 * Log the repair action to the wpc_db_issues table.
	 *
	 * @param array  $issue  Original issue array.
	 * @param string $status 'resolved' or 'failed'.
	 * @param string $detail Human-readable outcome.
	 * @return void
	 */
	private static function log_repair( $issue, $status, $detail ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpc_db_issues';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'blog_id'      => get_current_blog_id(),
				'table_name'   => isset( $issue['table'] ) ? $issue['table'] : '',
				'issue_type'   => isset( $issue['type'] ) ? $issue['type'] : '',
				'issue_detail' => $detail,
				'detected_at'  => current_time( 'mysql' ),
				'status'       => $status,
				'resolved_at'  => 'resolved' === $status ? current_time( 'mysql' ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
