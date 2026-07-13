<?php
/**
 * Input validation helpers.
 *
 * Everything in this class exists to make sure request data never reaches
 * a DDL statement, a filesystem read, or a spreadsheet cell unchecked.
 *
 * @package WP_Canary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPC_Validate {

	private static $schema_map = null;

	private static function schema_map() {
		if ( null !== self::$schema_map ) {
			return self::$schema_map;
		}
		$map = array();
		if ( class_exists( 'WPC_DB_Scanner' ) ) {
			$scanner = new WPC_DB_Scanner();
			if ( method_exists( $scanner, 'get_known_schemas' ) ) {
				foreach ( (array) $scanner->get_known_schemas() as $tables ) {
					foreach ( (array) $tables as $table => $columns ) {
						$map[ $table ] = (array) $columns;
					}
				}
			}
		}
		self::$schema_map = $map;
		return $map;
	}

	public static function is_identifier( $name ) {
		return is_string( $name ) && (bool) preg_match( '/^[A-Za-z0-9_]{1,64}$/', $name );
	}

	public static function is_own_table( $table ) {
		global $wpdb;
		if ( ! is_string( $table ) || '' === $table ) {
			return false;
		}
		$prefix = $wpdb->prefix;
		if ( 0 !== strpos( $table, $prefix ) ) {
			return false;
		}
		$bare = substr( $table, strlen( $prefix ) );
		if ( ! self::is_identifier( $bare ) ) {
			return false;
		}
		$existing = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		return in_array( $table, (array) $existing, true );
	}

	public static function repair_issue( $issue ) {
		global $wpdb;
		$type  = isset( $issue['type'] ) ? (string) $issue['type'] : '';
		$table = isset( $issue['table'] ) ? (string) $issue['table'] : '';
		if ( ! in_array( $type, array( 'missing_column', 'myisam_engine' ), true ) ) {
			return new WP_Error( 'wpc_bad_type', __( 'Unsupported repair type.', 'wp-canary' ) );
		}
		if ( ! self::is_own_table( $table ) ) {
			return new WP_Error( 'wpc_bad_table', __( 'Unknown table, or the table does not belong to this site.', 'wp-canary' ) );
		}
		if ( 'myisam_engine' === $type ) {
			return array( 'type' => 'myisam_engine', 'table' => $table );
		}
		$column = isset( $issue['column'] ) ? (string) $issue['column'] : '';
		if ( ! self::is_identifier( $column ) ) {
			return new WP_Error( 'wpc_bad_column', __( 'Invalid column name.', 'wp-canary' ) );
		}
		$bare   = substr( $table, strlen( $wpdb->prefix ) );
		$schema = self::schema_map();
		if ( ! isset( $schema[ $bare ] ) ) {
			return new WP_Error( 'wpc_unknown_schema', __( 'WP Canary can only repair tables it has a known schema for.', 'wp-canary' ) );
		}
		if ( ! isset( $schema[ $bare ][ $column ] ) ) {
			return new WP_Error( 'wpc_unknown_column', __( 'That column is not part of the known schema for this table.', 'wp-canary' ) );
		}
		$expected_type = $schema[ $bare ][ $column ];
		if ( ! preg_match( '/^[A-Za-z0-9_]+(\(\d+(,\d+)?\))?$/', $expected_type ) ) {
			return new WP_Error( 'wpc_bad_type_def', __( 'Malformed column type in schema registry.', 'wp-canary' ) );
		}
		return array( 'type' => 'missing_column', 'table' => $table, 'column' => $column, 'expected_type' => $expected_type );
	}

	public static function is_collation( $collation ) {
		global $wpdb;
		if ( ! is_string( $collation ) || ! preg_match( '/^[A-Za-z0-9_]{1,64}$/', $collation ) ) {
			return false;
		}
		$supported = $wpdb->get_col( 'SHOW COLLATION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		return in_array( $collation, (array) $supported, true );
	}

	public static function log_path( $path ) {
		if ( ! is_string( $path ) || '' === trim( $path ) ) {
			return '';
		}
		$path = wp_normalize_path( trim( $path ) );
		if ( false !== strpos( $path, '..' ) ) {
			return '';
		}
		if ( ! preg_match( '/\.log$/i', $path ) ) {
			return '';
		}
		$base = wp_normalize_path( realpath( WP_CONTENT_DIR ) );
		$real = realpath( $path );
		if ( false === $real ) {
			$parent = realpath( dirname( $path ) );
			if ( false === $parent ) {
				return '';
			}
			$candidate = wp_normalize_path( $parent );
		} else {
			$candidate = wp_normalize_path( $real );
		}
		if ( ! $base || 0 !== strpos( $candidate, $base ) ) {
			return '';
		}
		return $path;
	}

	public static function csv_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && preg_match( '/^[=+\-@\t\r]/', $value ) ) {
			$value = "'" . $value;
		}
		return '"' . str_replace( '"', '""', $value ) . '"';
	}
}
