<?php
/**
 * Registers all hooks and loads all modules for the plugin.
 *
 * @package WP_Site_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_Loader
 *
 * Responsible for requiring all class files and registering
 * WordPress hooks that power the plugin.
 */
class WPC_Loader {

	/**
	 * Array of actions to register.
	 *
	 * @var array
	 */
	protected $actions = array();

	/**
	 * Array of filters to register.
	 *
	 * @var array
	 */
	protected $filters = array();

	/**
	 * Constructor: load all required files.
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->register_hooks();
	}

	/**
	 * Load all class files.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		// Core includes always loaded.
		require_once WPC_PLUGIN_DIR . 'includes/class-wpc-db-tables.php';
		require_once WPC_PLUGIN_DIR . 'includes/class-wpc-license.php';
		require_once WPC_PLUGIN_DIR . 'includes/free/class-wpc-db-scanner.php';

		// Admin-only classes.
		if ( is_admin() || wp_doing_ajax() ) {
			require_once WPC_PLUGIN_DIR . 'admin/class-wpc-admin.php';
		}

		// Pro modules.
		if ( wpc_is_pro() ) {
			require_once WPC_PLUGIN_DIR . 'includes/pro/class-wpc-db-repair.php';
			require_once WPC_PLUGIN_DIR . 'includes/pro/class-wpc-plugin-watchdog.php';
			require_once WPC_PLUGIN_DIR . 'includes/pro/class-wpc-404-monitor.php';
			require_once WPC_PLUGIN_DIR . 'includes/pro/class-wpc-ad-monitor.php';
			require_once WPC_PLUGIN_DIR . 'includes/pro/class-wpc-debug-log.php';
			require_once WPC_PLUGIN_DIR . 'includes/pro/class-wpc-email-digest.php';
			require_once WPC_PLUGIN_DIR . 'includes/pro/class-wpc-php-monitor.php';
			require_once WPC_PLUGIN_DIR . 'includes/pro/class-wpc-cron-health.php';
		}
	}

	/**
	 * Register all WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		// Cron schedules.
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) );

		// Admin menu and AJAX.
		if ( is_admin() || wp_doing_ajax() ) {
			$admin = new WPC_Admin();
			add_action( 'admin_menu', array( $admin, 'add_plugin_admin_menu' ) );
			add_action( 'network_admin_menu', array( $admin, 'add_plugin_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_scripts' ) );
			add_action( 'admin_init', array( $admin, 'activation_redirect' ) );
			add_action( 'wp_ajax_wpc_run_db_scan', array( $admin, 'ajax_run_db_scan' ) );
			add_action( 'wp_ajax_wpc_repair_table', array( $admin, 'ajax_repair_table' ) );
			add_action( 'wp_ajax_wpc_dismiss_alert', array( $admin, 'ajax_dismiss_alert' ) );
			add_action( 'wp_ajax_wpc_export_log', array( $admin, 'ajax_export_log' ) );
			add_action( 'wp_ajax_wpc_send_test_digest', array( $admin, 'ajax_send_test_digest' ) );
		}

		// DB scan cron.
		add_action( 'wpc_db_scan', array( $this, 'run_scheduled_db_scan' ) );

		// Pro-only hooks.
		if ( wpc_is_pro() ) {
			// 404 monitor on non-admin front-end.
			if ( ! is_admin() ) {
				$monitor_404 = new WPC_404_Monitor();
				add_action( 'wp', array( $monitor_404, 'maybe_log_404' ) );
			}

			// Plugin watchdog hooks.
			$watchdog = new WPC_Plugin_Watchdog();
			add_action( 'activated_plugin', array( $watchdog, 'on_plugin_activated' ), 10, 1 );
			add_action( 'deactivated_plugin', array( $watchdog, 'on_plugin_deactivated' ), 10, 1 );
			add_action( 'wpc_plugin_watchdog', array( $watchdog, 'run_cron_check' ) );

			// Email digest cron.
			$digest = new WPC_Email_Digest();
			add_action( 'wpc_daily_digest', array( $digest, 'send_digest' ) );

			// Ad monitor cron.
			$ad_monitor = new WPC_Ad_Monitor();
			add_action( 'wpc_ad_monitor', array( $ad_monitor, 'run_analysis' ) );

			// Cron health check.
			$cron_health = new WPC_Cron_Health();
			add_action( 'wpc_cron_health_check', array( $cron_health, 'run_check' ) );
		}
	}

	/**
	 * Add custom cron schedules.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function register_cron_schedules( $schedules ) {
		$schedules['wpc_every_15min'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 Minutes', 'wp-canary' ),
		);
		$schedules['wpc_every_30min'] = array(
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 30 Minutes', 'wp-canary' ),
		);
		return $schedules;
	}

	/**
	 * Run the scheduled DB scan.
	 *
	 * @return void
	 */
	public function run_scheduled_db_scan() {
		$scanner = new WPC_DB_Scanner();
		$results = $scanner->scan_all();
		update_option( 'wpc_last_db_scan_results', $results );
		update_option( 'wpc_last_db_scan_time', current_time( 'mysql' ) );
	}

	/**
	 * Execute all registered hooks.
	 *
	 * @return void
	 */
	public function run() {
		// All hooks are registered in the constructor via register_hooks().
		// This method exists as the canonical entry point.
		do_action( 'wpc_loaded' );
	}
}
