<?php
/**
 * Database scanner (Free tier).
 *
 * @package WP_Site_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_DB_Scanner
 *
 * Scans the WordPress database for structural issues, missing tables,
 * and known plugin schema problems. Read-only — no repairs performed here.
 */
class WPSG_DB_Scanner {

	/**
	 * Severity constants.
	 */
	const SEV_CRITICAL = 'critical';
	const SEV_WARNING  = 'warning';
	const SEV_INFO     = 'info';

	/**
	 * Known plugin schemas.
	 * Format: plugin_prefix => [ table_suffix => [ column => type ] ]
	 *
	 * @var array
	 */
	private $known_schemas = array();

	/**
	 * Constructor — build schema registry.
	 */
	public function __construct() {
		$this->build_schema_registry();
	}

	/**
	 * Build the known-schema registry.
	 *
	 * @return void
	 */
	private function build_schema_registry() {
		$this->known_schemas = array(

			// WooCommerce.
			'woocommerce' => array(
				'woocommerce_order_items'    => array(
					'order_item_id'   => 'bigint',
					'order_item_name' => 'text',
					'order_item_type' => 'varchar(200)',
					'order_id'        => 'bigint',
				),
				'woocommerce_order_itemmeta' => array(
					'meta_id'       => 'bigint',
					'order_item_id' => 'bigint',
					'meta_key'      => 'varchar(255)',
					'meta_value'    => 'longtext',
				),
				'woocommerce_sessions'       => array(
					'session_id'     => 'bigint',
					'session_key'    => 'char(32)',
					'session_value'  => 'longtext',
					'session_expiry' => 'bigint',
				),
			),

			// Yoast SEO.
			'yoast' => array(
				'yoast_indexable'           => array(
					'id'                  => 'int',
					'object_id'           => 'int',
					'object_sub_type'     => 'varchar(32)',
					'object_type'         => 'varchar(32)',
					'permalink'           => 'longtext',
					'title'               => 'text',
					'description'         => 'mediumtext',
					'is_robots_noindex'   => 'tinyint',
					'is_robots_nofollow'  => 'tinyint',
				),
				'yoast_indexable_hierarchy' => array(
					'indexable_id' => 'int',
					'ancestor_id'  => 'int',
					'depth'        => 'int',
				),
			),

			// Redirection plugin.
			'redirection' => array(
				'redirection_items'  => array(
					'id'          => 'int',
					'url'         => 'varchar(255)',
					'action_type' => 'varchar(20)',
					'action_code' => 'int',
					'action_data' => 'varchar(2048)',
					'last_count'  => 'int',
					'last_access' => 'datetime',
					'group_id'    => 'int',
					'status'      => 'varchar(20)',
					'regex'       => 'tinyint',
				),
				'redirection_groups' => array(
					'id'        => 'int',
					'name'      => 'varchar(100)',
					'status'    => 'varchar(20)',
					'module_id' => 'int',
				),
				'redirection_logs'   => array(
					'id'          => 'bigint',
					'created_at'  => 'datetime',
					'url'         => 'varchar(255)',
					'sent_to'     => 'varchar(255)',
					'agent'       => 'varchar(255)',
					'referrer'    => 'varchar(255)',
					'group_id'    => 'int',
					'redirect_id' => 'int',
					'ip'          => 'varchar(45)',
				),
			),

			// CF7 / Flamingo.
			'flamingo' => array(
				'flamingo_inbound'     => array(
					'ID'             => 'bigint',
					'post_author'    => 'bigint',
					'post_date'      => 'datetime',
					'post_content'   => 'longtext',
					'post_title'     => 'text',
					'post_excerpt'   => 'text',
					'post_status'    => 'varchar(20)',
					'comment_status' => 'varchar(20)',
					'ping_status'    => 'varchar(20)',
					'post_name'      => 'varchar(200)',
					'post_modified'  => 'datetime',
					'post_type'      => 'varchar(20)',
				),
				'flamingo_inboundmeta' => array(
					'meta_id'   => 'bigint',
					'post_id'   => 'bigint',
					'meta_key'  => 'varchar(255)',
					'meta_value' => 'longtext',
				),
			),
		);
	}

	/**
	 * Run a full scan and return all found issues.
	 *
	 * @return array
	 */
	public function scan_all() {
		global $wpdb;

		$issues = array();

		// Get all current table names.
		$existing_tables = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// Scan known plugin schemas.
		$issues = array_merge( $issues, $this->scan_known_schemas( $existing_tables ) );

		// General scan of all tables.
		$issues = array_merge( $issues, $this->scan_general( $existing_tables ) );

		return $issues;
	}

	/**
	 * Scan known plugin schemas for structural issues.
	 *
	 * @param array $existing_tables List of existing table names.
	 * @return array
	 */
	private function scan_known_schemas( $existing_tables ) {
		global $wpdb;
		$issues = array();
		$prefix = $wpdb->prefix;

		foreach ( $this->known_schemas as $plugin_name => $tables ) {
			foreach ( $tables as $table_suffix => $expected_columns ) {
				$table_name = $prefix . $table_suffix;

				// Check if table exists.
				if ( ! in_array( $table_name, $existing_tables, true ) ) {
					// Only flag as issue if the plugin is actually active.
					if ( $this->is_plugin_active( $plugin_name ) ) {
						$issues[] = array(
							'severity'    => self::SEV_CRITICAL,
							'table'       => $table_name,
							'type'        => 'missing_table',
							'description' => sprintf(
								/* translators: 1: table name, 2: plugin name */
								__( 'Table %1$s is missing. This table is required by %2$s.', 'wp-site-guardian' ),
								'<code>' . esc_html( $table_name ) . '</code>',
								esc_html( ucfirst( $plugin_name ) )
							),
							'plugin'      => $plugin_name,
						);
					}
					continue;
				}

				// Describe the table.
				$columns = $this->describe_table( $table_name );
				if ( empty( $columns ) ) {
					continue;
				}

				$column_names = array_column( $columns, 'Field' );

				foreach ( $expected_columns as $col_name => $expected_type ) {
					if ( ! in_array( $col_name, $column_names, true ) ) {
						$issues[] = array(
							'severity'       => self::SEV_CRITICAL,
							'table'          => $table_name,
							'type'           => 'missing_column',
							'column'         => $col_name,
							'expected_type'  => $expected_type,
							'description'    => sprintf(
								/* translators: 1: column name, 2: table name */
								__( 'Column %1$s is missing from table %2$s.', 'wp-site-guardian' ),
								'<code>' . esc_html( $col_name ) . '</code>',
								'<code>' . esc_html( $table_name ) . '</code>'
							),
							'plugin'         => $plugin_name,
						);
						continue;
					}

					// Check for type mismatch (loose comparison — just check base type).
					$actual_col = $this->find_column( $columns, $col_name );
					if ( $actual_col && ! $this->types_compatible( $actual_col['Type'], $expected_type ) ) {
						$issues[] = array(
							'severity'      => self::SEV_WARNING,
							'table'         => $table_name,
							'type'          => 'type_mismatch',
							'column'        => $col_name,
							'expected_type' => $expected_type,
							'actual_type'   => $actual_col['Type'],
							'description'   => sprintf(
								/* translators: 1: column, 2: table, 3: expected type, 4: actual type */
								__( 'Column %1$s in %2$s has type %3$s but expected %4$s.', 'wp-site-guardian' ),
								'<code>' . esc_html( $col_name ) . '</code>',
								'<code>' . esc_html( $table_name ) . '</code>',
								'<code>' . esc_html( $expected_type ) . '</code>',
								'<code>' . esc_html( $actual_col['Type'] ) . '</code>'
							),
							'plugin'        => $plugin_name,
						);
					}
				}

				// Check if active-plugin table is empty (potential data loss).
				if ( $this->is_plugin_active( $plugin_name ) ) {
					$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					if ( 0 === $count ) {
						$issues[] = array(
							'severity'    => self::SEV_INFO,
							'table'       => $table_name,
							'type'        => 'empty_table',
							'description' => sprintf(
								/* translators: 1: table name, 2: plugin name */
								__( 'Table %1$s (%2$s) has no rows. This may indicate missing data.', 'wp-site-guardian' ),
								'<code>' . esc_html( $table_name ) . '</code>',
								esc_html( ucfirst( $plugin_name ) )
							),
							'plugin'      => $plugin_name,
						);
					}
				}
			}
		}

		return $issues;
	}

	/**
	 * General scan of all tables.
	 *
	 * @param array $existing_tables List of existing table names.
	 * @return array
	 */
	private function scan_general( $existing_tables ) {
		global $wpdb;
		$issues  = array();
		$week_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

		foreach ( $existing_tables as $table_name ) {
			// Get table status.
			$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			if ( ! $status ) {
				continue;
			}

			// Check for MyISAM engine.
			if ( isset( $status->Engine ) && 'MyISAM' === $status->Engine ) {
				$issues[] = array(
					'severity'    => self::SEV_WARNING,
					'table'       => $table_name,
					'type'        => 'myisam_engine',
					'description' => sprintf(
						/* translators: 1: table name */
						__( 'Table %1$s uses the MyISAM storage engine. Consider converting to InnoDB for better reliability and crash recovery.', 'wp-site-guardian' ),
						'<code>' . esc_html( $table_name ) . '</code>'
					),
				);
			}

			// Check for orphaned empty tables (created > 7 days ago, 0 rows).
			$create_time = isset( $status->Create_time ) ? $status->Create_time : null;
			$rows        = isset( $status->Rows ) ? (int) $status->Rows : -1;

			if ( $create_time && $create_time < $week_ago && 0 === $rows ) {
				$issues[] = array(
					'severity'    => self::SEV_INFO,
					'table'       => $table_name,
					'type'        => 'orphaned_table',
					'description' => sprintf(
						/* translators: 1: table name */
						__( 'Table %1$s is empty and was created more than 7 days ago. It may be an orphaned table from a removed plugin.', 'wp-site-guardian' ),
						'<code>' . esc_html( $table_name ) . '</code>'
					),
				);
			}
		}

		return $issues;
	}

	/**
	 * Describe a table and return its column definitions.
	 *
	 * @param string $table_name Full table name.
	 * @return array
	 */
	private function describe_table( $table_name ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( 'DESCRIBE %i', $table_name ), ARRAY_A ) ?? array();
	}

	/**
	 * Find a column definition by name.
	 *
	 * @param array  $columns   Result of DESCRIBE.
	 * @param string $col_name  Column name to find.
	 * @return array|null
	 */
	private function find_column( $columns, $col_name ) {
		foreach ( $columns as $col ) {
			if ( isset( $col['Field'] ) && $col['Field'] === $col_name ) {
				return $col;
			}
		}
		return null;
	}

	/**
	 * Loosely compare two column types.
	 *
	 * Strips display widths and compares base type names.
	 *
	 * @param string $actual   Actual type from DB.
	 * @param string $expected Expected type from schema.
	 * @return bool
	 */
	private function types_compatible( $actual, $expected ) {
		$normalize = function ( $type ) {
			// Strip display width and unsigned modifiers for base comparison.
			$type = strtolower( $type );
			$type = preg_replace( '/\(\d+\)/', '', $type );
			$type = trim( str_replace( array( 'unsigned', 'signed' ), '', $type ) );
			// Map aliases.
			$aliases = array(
				'int'     => 'int',
				'integer' => 'int',
				'bigint'  => 'bigint',
				'tinyint' => 'tinyint',
			);
			return $aliases[ $type ] ?? $type;
		};

		return $normalize( $actual ) === $normalize( $expected );
	}

	/**
	 * Heuristic check if a known plugin is active.
	 *
	 * @param string $plugin_id Internal plugin identifier.
	 * @return bool
	 */
	private function is_plugin_active( $plugin_id ) {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		$map = array(
			'woocommerce' => 'woocommerce/woocommerce.php',
			'yoast'       => 'wordpress-seo/wp-seo.php',
			'redirection' => 'redirection/redirection.php',
			'flamingo'    => 'flamingo/flamingo.php',
		);

		if ( ! isset( $map[ $plugin_id ] ) ) {
			return false;
		}

		return in_array( $map[ $plugin_id ], $active_plugins, true );
	}

	/**
	 * Return the known schema for a specific table.
	 * Used by the repair class to build ALTER TABLE statements.
	 *
	 * @param string $table_suffix Table suffix (without prefix).
	 * @return array Associative array of column => type.
	 */
	public function get_schema_for_table( $table_suffix ) {
		foreach ( $this->known_schemas as $plugin => $tables ) {
			if ( isset( $tables[ $table_suffix ] ) ) {
				return $tables[ $table_suffix ];
			}
		}
		return array();
	}
}
