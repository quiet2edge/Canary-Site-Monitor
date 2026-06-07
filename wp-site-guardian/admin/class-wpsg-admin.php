<?php
/**
 * Admin UI controller.
 *
 * @package WP_Site_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_Admin
 *
 * Registers menus, enqueues assets, and handles AJAX requests.
 */
class WPSG_Admin {

	/**
	 * Slug for the top-level menu page.
	 */
	const MENU_SLUG = 'wp-site-guardian';

	/**
	 * Add the plugin admin menu and submenus.
	 *
	 * @return void
	 */
	public function add_plugin_admin_menu() {
		$capability = is_multisite() ? 'manage_network_options' : 'manage_options';

		add_menu_page(
			__( 'Site Guardian', 'wp-site-guardian' ),
			__( 'Site Guardian', 'wp-site-guardian' ),
			$capability,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-shield',
			80
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'wp-site-guardian' ),
			__( 'Dashboard', 'wp-site-guardian' ),
			$capability,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Database', 'wp-site-guardian' ),
			__( 'Database', 'wp-site-guardian' ),
			$capability,
			'wpsg-database',
			array( $this, 'render_database' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( '404 Monitor', 'wp-site-guardian' ),
			__( '404 Monitor', 'wp-site-guardian' ),
			$capability,
			'wpsg-404-monitor',
			array( $this, 'render_404_monitor' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Plugin Watchdog', 'wp-site-guardian' ),
			__( 'Plugin Watchdog', 'wp-site-guardian' ),
			$capability,
			'wpsg-plugin-watchdog',
			array( $this, 'render_plugin_watchdog' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Reports', 'wp-site-guardian' ),
			__( 'Reports', 'wp-site-guardian' ),
			$capability,
			'wpsg-reports',
			array( $this, 'render_reports' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'wp-site-guardian' ),
			__( 'Settings', 'wp-site-guardian' ),
			$capability,
			'wpsg-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Enqueue admin scripts and styles (only on WPSG pages).
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_scripts( $hook_suffix ) {
		$wpsg_pages = array(
			'toplevel_page_wp-site-guardian',
			'site-guardian_page_wpsg-database',
			'site-guardian_page_wpsg-404-monitor',
			'site-guardian_page_wpsg-plugin-watchdog',
			'site-guardian_page_wpsg-reports',
			'site-guardian_page_wpsg-settings',
		);

		if ( ! in_array( $hook_suffix, $wpsg_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'wpsg-admin',
			WPSG_PLUGIN_URL . 'admin/css/wpsg-admin.css',
			array(),
			WPSG_VERSION
		);

		wp_enqueue_script(
			'wpsg-admin',
			WPSG_PLUGIN_URL . 'admin/js/wpsg-admin.js',
			array( 'jquery' ),
			WPSG_VERSION,
			true
		);

		wp_localize_script(
			'wpsg-admin',
			'wpsgData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpsg_admin_nonce' ),
				'i18n'    => array(
					'scanning'        => __( 'Scanning…', 'wp-site-guardian' ),
					'scan_complete'   => __( 'Scan complete.', 'wp-site-guardian' ),
					'repair_confirm'  => __( 'Are you sure? This will ALTER your database table. Always have a backup before proceeding.', 'wp-site-guardian' ),
					'repairing'       => __( 'Repairing…', 'wp-site-guardian' ),
					'repair_done'     => __( 'Repair complete.', 'wp-site-guardian' ),
					'sending'         => __( 'Sending…', 'wp-site-guardian' ),
					'sent'            => __( 'Test digest sent.', 'wp-site-guardian' ),
					'error'           => __( 'An error occurred. Please try again.', 'wp-site-guardian' ),
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
		require_once WPSG_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render the database page.
	 *
	 * @return void
	 */
	public function render_database() {
		require_once WPSG_PLUGIN_DIR . 'admin/views/database.php';
	}

	/**
	 * Render the 404 monitor page.
	 *
	 * @return void
	 */
	public function render_404_monitor() {
		require_once WPSG_PLUGIN_DIR . 'admin/views/monitor-404.php';
	}

	/**
	 * Render the plugin watchdog page.
	 *
	 * @return void
	 */
	public function render_plugin_watchdog() {
		require_once WPSG_PLUGIN_DIR . 'admin/views/plugin-watchdog.php';
	}

	/**
	 * Render the reports page.
	 *
	 * @return void
	 */
	public function render_reports() {
		require_once WPSG_PLUGIN_DIR . 'admin/views/reports.php';
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings() {
		// Handle settings save.
		if ( isset( $_POST['wpsg_settings_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpsg_settings_nonce'] ) ), 'wpsg_save_settings' )
			&& current_user_can( 'manage_options' ) ) {
			$this->save_settings();
		}
		require_once WPSG_PLUGIN_DIR . 'admin/views/settings.php';
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
			'wpsg_flood_threshold'      => 'intval',
			'wpsg_email_alerts_enabled' => 'boolval',
			'wpsg_alert_email'          => 'sanitize_email',
			'wpsg_digest_enabled'       => 'boolval',
			'wpsg_digest_time'          => 'sanitize_text_field',
			'wpsg_digest_email'         => 'sanitize_email',
			'wpsg_data_retention_days'  => 'intval',
			'wpsg_debug_log_path'       => 'sanitize_text_field',
			'wpsg_keep_data'            => 'boolval',
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

		add_settings_error( 'wpsg_settings', 'wpsg_settings_saved', __( 'Settings saved.', 'wp-site-guardian' ), 'updated' );
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
		check_ajax_referer( 'wpsg_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-site-guardian' ) ) );
		}

		$scanner = new WPSG_DB_Scanner();
		$results = $scanner->scan_all();

		update_option( 'wpsg_last_db_scan_results', $results );
		update_option( 'wpsg_last_db_scan_time', current_time( 'mysql' ) );

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
		check_ajax_referer( 'wpsg_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-site-guardian' ) ) );
		}

		if ( ! wpsg_is_pro() ) {
			wp_send_json_error( array( 'message' => __( 'Repairs require WP Site Guardian Pro.', 'wp-site-guardian' ) ) );
		}

		$issue = isset( $_POST['issue'] ) ? (array) $_POST['issue'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( empty( $issue ) ) {
			wp_send_json_error( array( 'message' => __( 'No issue data provided.', 'wp-site-guardian' ) ) );
		}

		// Sanitize issue array.
		$clean_issue = array(
			'type'          => isset( $issue['type'] ) ? sanitize_text_field( $issue['type'] ) : '',
			'table'         => isset( $issue['table'] ) ? sanitize_text_field( $issue['table'] ) : '',
			'column'        => isset( $issue['column'] ) ? sanitize_text_field( $issue['column'] ) : '',
			'expected_type' => isset( $issue['expected_type'] ) ? sanitize_text_field( $issue['expected_type'] ) : '',
		);

		$result = WPSG_DB_Repair::repair_issue( $clean_issue );

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
		check_ajax_referer( 'wpsg_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-site-guardian' ) ) );
		}

		$alert_type = isset( $_POST['alert_type'] ) ? sanitize_text_field( wp_unslash( $_POST['alert_type'] ) ) : '';
		$alert_key  = isset( $_POST['alert_key'] ) ? sanitize_text_field( wp_unslash( $_POST['alert_key'] ) ) : '';

		if ( 'flood' === $alert_type && ! empty( $alert_key ) ) {
			$active_floods = (array) get_option( 'wpsg_active_floods', array() );
			unset( $active_floods[ $alert_key ] );
			update_option( 'wpsg_active_floods', $active_floods );
		}

		wp_send_json_success( array( 'message' => __( 'Alert dismissed.', 'wp-site-guardian' ) ) );
	}

	/**
	 * AJAX: export log as CSV.
	 *
	 * @return void
	 */
	public function ajax_export_log() {
		check_ajax_referer( 'wpsg_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-site-guardian' ) );
		}

		global $wpdb;

		$log_type = isset( $_POST['log_type'] ) ? sanitize_text_field( wp_unslash( $_POST['log_type'] ) ) : '404';

		switch ( $log_type ) {
			case 'plugins':
				$table = $wpdb->prefix . 'wpsg_plugin_changes';
				break;
			case 'db':
				$table = $wpdb->prefix . 'wpsg_db_issues';
				break;
			default:
				$table = $wpdb->prefix . 'wpsg_404_log';
		}

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 10000', $table ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( empty( $rows ) ) {
			wp_send_json_error( array( 'message' => __( 'No data to export.', 'wp-site-guardian' ) ) );
		}

		// Build CSV.
		$headers = array_keys( $rows[0] );
		$csv     = implode( ',', array_map( 'esc_html', $headers ) ) . "\n";
		foreach ( $rows as $row ) {
			$csv .= implode( ',', array_map( function ( $v ) {
				return '"' . str_replace( '"', '""', $v ) . '"';
			}, $row ) ) . "\n";
		}

		wp_send_json_success( array( 'csv' => $csv, 'filename' => "wpsg-{$log_type}-log-" . gmdate( 'Y-m-d' ) . '.csv' ) );
	}

	/**
	 * AJAX: send a test digest email.
	 *
	 * @return void
	 */
	public function ajax_send_test_digest() {
		check_ajax_referer( 'wpsg_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-site-guardian' ) ) );
		}

		if ( ! wpsg_is_pro() ) {
			wp_send_json_error( array( 'message' => __( 'Email digest requires WP Site Guardian Pro.', 'wp-site-guardian' ) ) );
		}

		$digest = new WPSG_Email_Digest();
		$sent   = $digest->send_digest();

		if ( $sent ) {
			wp_send_json_success( array( 'message' => __( 'Test digest sent successfully.', 'wp-site-guardian' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to send test digest. Check your site\'s email configuration.', 'wp-site-guardian' ) ) );
		}
	}
}
