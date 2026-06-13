<?php
/**
 * Admin UI controller.
 *
 * @package WP_Canary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_Admin
 *
 * Registers menus, enqueues assets, and handles AJAX requests.
 */
class WPC_Admin {

	/**
	 * Slug for the top-level menu page.
	 */
	const MENU_SLUG = 'wp-canary';

	/**
	 * Add the plugin admin menu and submenus.
	 *
	 * @return void
	 */
	public function add_plugin_admin_menu() {
		$capability = is_multisite() ? 'manage_network_options' : 'manage_options';

		add_menu_page(
			__( 'WP Canary', 'wp-canary' ),
			__( 'WP Canary', 'wp-canary' ),
			$capability,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-shield',
			80
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'wp-canary' ),
			__( 'Dashboard', 'wp-canary' ),
			$capability,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Database', 'wp-canary' ),
			__( 'Database', 'wp-canary' ),
			$capability,
			'wpc-database',
			array( $this, 'render_database' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( '404 Monitor', 'wp-canary' ),
			__( '404 Monitor', 'wp-canary' ),
			$capability,
			'wpc-404-monitor',
			array( $this, 'render_404_monitor' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Plugin Watchdog', 'wp-canary' ),
			__( 'Plugin Watchdog', 'wp-canary' ),
			$capability,
			'wpc-plugin-watchdog',
			array( $this, 'render_plugin_watchdog' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Plugin Health', 'wp-canary' ),
			__( 'Plugin Health', 'wp-canary' ),
			$capability,
			'wpc-plugin-health',
			array( $this, 'render_plugin_health' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Reports', 'wp-canary' ),
			__( 'Reports', 'wp-canary' ),
			$capability,
			'wpc-reports',
			array( $this, 'render_reports' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'wp-canary' ),
			__( 'Settings', 'wp-canary' ),
			$capability,
			'wpc-settings',
			array( $this, 'render_settings' )
		);

		// Hidden page — not shown in menu, only reached via activation redirect.
		add_submenu_page(
			null,
			__( 'Getting Started', 'wp-canary' ),
			__( 'Getting Started', 'wp-canary' ),
			$capability,
			'wpc-getting-started',
			array( $this, 'render_getting_started' )
		);
	}

	/**
	 * Redirect to Getting Started page after activation.
	 *
	 * @return void
	 */
	public function activation_redirect() {
		if ( ! get_transient( 'wpc_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'wpc_activation_redirect' );

		// Don't redirect during bulk activation or in network admin.
		if ( isset( $_GET['activate-multi'] ) || is_network_admin() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wpc-getting-started' ) );
		exit;
	}

	/**
	 * Enqueue admin scripts and styles (only on WP Canary pages).
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_scripts( $hook_suffix ) {
		$wpc_pages = array(
			'toplevel_page_wp-canary',
			'wp-canary_page_wpc-database',
			'wp-canary_page_wpc-404-monitor',
			'wp-canary_page_wpc-plugin-watchdog',
			'wp-canary_page_wpc-plugin-health',
			'wp-canary_page_wpc-reports',
			'wp-canary_page_wpc-settings',
		);

		if ( ! in_array( $hook_suffix, $wpc_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'wpc-admin',
			WPC_PLUGIN_URL . 'admin/css/wpc-admin.css',
			array(),
			WPC_VERSION
		);

		wp_enqueue_script(
			'wpc-admin',
			WPC_PLUGIN_URL . 'admin/js/wpc-admin.js',
			array( 'jquery' ),
			WPC_VERSION,
			true
		);

		wp_localize_script(
			'wpc-admin',
			'wpcData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpc_admin_nonce' ),
				'i18n'    => array(
					'scanning'        => __( 'Scanning…', 'wp-canary' ),
					'scan_complete'   => __( 'Scan complete.', 'wp-canary' ),
					'repair_confirm'  => __( 'Are you sure? This will ALTER your database table. Always have a backup before proceeding.', 'wp-canary' ),
					'repairing'       => __( 'Repairing…', 'wp-canary' ),
					'repair_done'     => __( 'Repair complete.', 'wp-canary' ),
					'sending'         => __( 'Sending…', 'wp-canary' ),
					'sent'            => __( 'Test digest sent.', 'wp-canary' ),
					'error'           => __( 'An error occurred. Please try again.', 'wp-canary' ),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Page renderers.
	// -------------------------------------------------------------------------

	/**
	 * Render the dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		require_once WPC_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render the database page.
	 *
	 * @return void
	 */
	public function render_database() {
		require_once WPC_PLUGIN_DIR . 'admin/views/database.php';
	}

	/**
	 * Render the 404 monitor page.
	 *
	 * @return void
	 */
	public function render_404_monitor() {
		require_once WPC_PLUGIN_DIR . 'admin/views/monitor-404.php';
	}

	/**
	 * Render the plugin watchdog page.
	 *
	 * @return void
	 */
	public function render_plugin_watchdog() {
		require_once WPC_PLUGIN_DIR . 'admin/views/plugin-watchdog.php';
	}

	/**
	 * Render the Plugin Health page.
	 *
	 * @return void
	 */
	public function render_plugin_health() {
		require_once WPC_PLUGIN_DIR . 'admin/views/plugin-health.php';
	}

	/**
	 * Render the reports page.
	 *
	 * @return void
	 */
	public function render_reports() {
		require_once WPC_PLUGIN_DIR . 'admin/views/reports.php';
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings() {
		// Handle settings save.
		if ( isset( $_POST['wpc_settings_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpc_settings_nonce'] ) ), 'wpc_save_settings' )
			&& current_user_can( 'manage_options' ) ) {
			$this->save_settings();
		}
		require_once WPC_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Render the Getting Started page.
	 *
	 * @return void
	 */
	public function render_getting_started() {
		require_once WPC_PLUGIN_DIR . 'admin/views/getting-started.php';
	}

	// -------------------------------------------------------------------------
	// Settings save.
	// -------------------------------------------------------------------------

	/**
	 * Save settings from the settings form.
	 *
	 * @return void
	 */
	private function save_settings() {
		$fields = array(
			'wpc_flood_threshold'      => 'intval',
			'wpc_email_alerts_enabled' => 'boolval',
			'wpc_alert_email'          => 'sanitize_email',
			'wpc_digest_enabled'       => 'boolval',
			'wpc_digest_time'          => 'sanitize_text_field',
			'wpc_digest_email'         => 'sanitize_email',
			'wpc_data_retention_days'  => 'intval',
			'wpc_debug_log_path'       => 'sanitize_text_field',
			'wpc_keep_data'            => 'boolval',
		);

		foreach ( $fields as $key => $sanitizer ) {
			if ( isset( $_POST[ $key ] ) ) {
				$value = call_user_func( $sanitizer, wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				update_option( $key, $value );
			} elseif ( in_array( $sanitizer, array( 'boolval' ), true ) ) {
				// Unchecked checkboxes are not submitted.
				update_option( $key, false );
			}
		}

		add_settings_error( 'wpc_settings', 'wpc_settings_saved', __( 'Settings saved.', 'wp-canary' ), 'updated' );
	}

	// -------------------------------------------------------------------------
	// AJAX handlers.
	// -------------------------------------------------------------------------

	/**
	 * AJAX: run a database scan.
	 *
	 * @return void
	 */
	public function ajax_run_db_scan() {
		check_ajax_referer( 'wpc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-canary' ) ) );
		}

		$scanner = new WPC_DB_Scanner();
		$results = $scanner->scan_all();

		update_option( 'wpc_last_db_scan_results', $results );
		update_option( 'wpc_last_db_scan_time', current_time( 'mysql' ) );

		wp_send_json_success(
			array(
				'issues'   => $results,
				'count'    => count( $results ),
				'scan_time' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * AJAX: repair a table issue (Pro only).
	 *
	 * @return void
	 */
	public function ajax_repair_table() {
		check_ajax_referer( 'wpc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-canary' ) ) );
		}

		if ( ! wpc_is_pro() ) {
			wp_send_json_error( array( 'message' => __( 'Repairs require WP Canary Pro.', 'wp-canary' ) ) );
		}

		$issue = isset( $_POST['issue'] ) ? (array) $_POST['issue'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( empty( $issue ) ) {
			wp_send_json_error( array( 'message' => __( 'No issue data provided.', 'wp-canary' ) ) );
		}

		// Sanitize issue array.
		$clean_issue = array(
			'type'          => isset( $issue['type'] ) ? sanitize_text_field( $issue['type'] ) : '',
			'table'         => isset( $issue['table'] ) ? sanitize_text_field( $issue['table'] ) : '',
			'column'        => isset( $issue['column'] ) ? sanitize_text_field( $issue['column'] ) : '',
			'expected_type' => isset( $issue['expected_type'] ) ? sanitize_text_field( $issue['expected_type'] ) : '',
		);

		$result = WPC_DB_Repair::repair_issue( $clean_issue );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	/**
	 * AJAX: dismiss an alert.
	 *
	 * @return void
	 */
	public function ajax_dismiss_alert() {
		check_ajax_referer( 'wpc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-canary' ) ) );
		}

		$alert_type = isset( $_POST['alert_type'] ) ? sanitize_text_field( wp_unslash( $_POST['alert_type'] ) ) : '';
		$alert_key  = isset( $_POST['alert_key'] ) ? sanitize_text_field( wp_unslash( $_POST['alert_key'] ) ) : '';

		if ( 'flood' === $alert_type && ! empty( $alert_key ) ) {
			$active_floods = (array) get_option( 'wpc_active_floods', array() );
			unset( $active_floods[ $alert_key ] );
			update_option( 'wpc_active_floods', $active_floods );
		}

		wp_send_json_success( array( 'message' => __( 'Alert dismissed.', 'wp-canary' ) ) );
	}

	/**
	 * AJAX: export log as CSV.
	 *
	 * @return void
	 */
	public function ajax_export_log() {
		check_ajax_referer( 'wpc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-canary' ) );
		}

		global $wpdb;

		$log_type = isset( $_POST['log_type'] ) ? sanitize_text_field( wp_unslash( $_POST['log_type'] ) ) : '404';

		switch ( $log_type ) {
			case 'plugins':
				$table = $wpdb->prefix . 'wpc_plugin_changes';
				break;
			case 'db':
				$table = $wpdb->prefix . 'wpc_db_issues';
				break;
			default:
				$table = $wpdb->prefix . 'wpc_404_log';
		}

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 10000', $table ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( empty( $rows ) ) {
			wp_send_json_error( array( 'message' => __( 'No data to export.', 'wp-canary' ) ) );
		}

		// Build CSV.
		$headers = array_keys( $rows[0] );
		$csv     = implode( ',', array_map( 'esc_html', $headers ) ) . "\n";
		foreach ( $rows as $row ) {
			$csv .= implode( ',', array_map( function ( $v ) {
				return '"' . str_replace( '"', '""', $v ) . '"';
			}, $row ) ) . "\n";
		}

		wp_send_json_success( array( 'csv' => $csv, 'filename' => "wpc-{$log_type}-log-" . gmdate( 'Y-m-d' ) . '.csv' ) );
	}

	/**
	 * AJAX: run the Plugin Health scan (Pro only).
	 *
	 * @return void
	 */
	public function ajax_run_plugin_health_scan() {
		check_ajax_referer( 'wpc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-canary' ) ) );
		}

		if ( ! wpc_is_pro() ) {
			wp_send_json_error( array( 'message' => __( 'Plugin Health requires WP Canary Pro.', 'wp-canary' ) ) );
		}

		$health  = new WPC_Plugin_Health();
		$results = $health->run_scan();

		wp_send_json_success(
			array(
				'message'    => __( 'Scan complete.', 'wp-canary' ),
				'scanned_at' => $results['scanned_at'],
			)
		);
	}

	/**
	 * AJAX: send a test digest email.
	 *
	 * @return void
	 */
	public function ajax_send_test_digest() {
		check_ajax_referer( 'wpc_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-canary' ) ) );
		}

		if ( ! wpc_is_pro() ) {
			wp_send_json_error( array( 'message' => __( 'Email digest requires WP Canary Pro.', 'wp-canary' ) ) );
		}

		$digest = new WPC_Email_Digest();
		$sent   = $digest->send_digest();

		if ( $sent ) {
			wp_send_json_success( array( 'message' => __( 'Test digest sent successfully.', 'wp-canary' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to send test digest. Check your site\'s email configuration.', 'wp-canary' ) ) );
		}
	}
}
