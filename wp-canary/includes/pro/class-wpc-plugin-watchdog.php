<?php
/**
 * Plugin Watchdog (Pro).
 *
 * @package WP_Site_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_Plugin_Watchdog
 *
 * Monitors plugin activations and deactivations, stores history,
 * and fires alerts on mass-deactivation events.
 */
class WPC_Plugin_Watchdog {

	/**
	 * Number of simultaneous deactivations that triggers an alert.
	 */
	const MASS_DEACTIVATION_THRESHOLD = 3;

	/**
	 * Called when a plugin is activated via WP hook.
	 *
	 * @param string $plugin Plugin slug (relative path).
	 * @return void
	 */
	public function on_plugin_activated( $plugin ) {
		$this->log_change( $plugin, 'activated' );
		$this->update_known_state();
	}

	/**
	 * Called when a plugin is deactivated via WP hook.
	 *
	 * @param string $plugin Plugin slug (relative path).
	 * @return void
	 */
	public function on_plugin_deactivated( $plugin ) {
		$this->log_change( $plugin, 'deactivated' );
		$this->update_known_state();
	}

	/**
	 * Run comparison check from cron.
	 *
	 * @return void
	 */
	public function run_cron_check() {
		update_option( 'wpc_last_cron_runs', array_merge(
			(array) get_option( 'wpc_last_cron_runs', array() ),
			array( 'wpc_plugin_watchdog' => current_time( 'mysql' ) )
		) );

		$current_plugins = (array) get_option( 'active_plugins', array() );
		$last_known      = (array) maybe_unserialize( get_option( 'wpc_last_known_plugins', array() ) );

		$activated   = array_diff( $current_plugins, $last_known );
		$deactivated = array_diff( $last_known, $current_plugins );

		foreach ( $activated as $plugin ) {
			$this->log_change( $plugin, 'activated', 'cron' );
		}

		foreach ( $deactivated as $plugin ) {
			$this->log_change( $plugin, 'deactivated', 'cron' );
		}

		if ( count( $deactivated ) >= self::MASS_DEACTIVATION_THRESHOLD ) {
			$this->send_mass_deactivation_alert( array_values( $deactivated ) );
		}

		$this->update_known_state( $current_plugins );
		$this->prune_old_records();
	}

	/**
	 * Log a single plugin change to the database.
	 *
	 * @param string $plugin       Plugin file slug.
	 * @param string $action       'activated' or 'deactivated'.
	 * @param string $triggered_by Optional override for trigger source.
	 * @return void
	 */
	private function log_change( $plugin, $action, $triggered_by = '' ) {
		global $wpdb;

		if ( empty( $triggered_by ) ) {
			$triggered_by = $this->detect_trigger();
		}

		$plugin_data = $this->get_plugin_data( $plugin );
		$ip          = $this->get_client_ip();

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wpc_plugin_changes',
			array(
				'blog_id'      => get_current_blog_id(),
				'timestamp'    => current_time( 'mysql' ),
				'action'       => $action,
				'plugin_slug'  => $plugin,
				'plugin_name'  => $plugin_data['name'],
				'triggered_by' => $triggered_by,
				'ip_address'   => $ip,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Update the wpc_last_known_plugins option to current state.
	 *
	 * @param array|null $plugins Optional pre-fetched plugin list.
	 * @return void
	 */
	private function update_known_state( $plugins = null ) {
		if ( null === $plugins ) {
			$plugins = (array) get_option( 'active_plugins', array() );
		}
		update_option( 'wpc_last_known_plugins', maybe_serialize( $plugins ) );
	}

	/**
	 * Detect what triggered the change.
	 *
	 * @return string
	 */
	private function detect_trigger() {
		if ( wp_doing_cron() ) {
			return 'cron';
		}

		$user = wp_get_current_user();
		if ( $user && $user->ID > 0 ) {
			return 'user:' . $user->user_login;
		}

		if ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
			return 'rest-api';
		}

		return 'unknown process';
	}

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated forwarded IPs.
				if ( strpos( $ip, ',' ) !== false ) {
					$parts = explode( ',', $ip );
					$ip    = trim( $parts[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}

	/**
	 * Get plugin display name from plugin file.
	 *
	 * @param string $plugin Plugin file slug (e.g. woocommerce/woocommerce.php).
	 * @return array { name: string }
	 */
	private function get_plugin_data( $plugin ) {
		$plugin_path = WP_PLUGIN_DIR . '/' . $plugin;
		if ( file_exists( $plugin_path ) ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$data = get_plugin_data( $plugin_path, false, false );
			return array( 'name' => $data['Name'] ?? $plugin );
		}
		return array( 'name' => $plugin );
	}

	/**
	 * Send an alert email for mass plugin deactivation.
	 *
	 * @param array $plugins List of deactivated plugin slugs.
	 * @return void
	 */
	private function send_mass_deactivation_alert( $plugins ) {
		$enabled = get_option( 'wpc_email_alerts_enabled', true );
		if ( ! $enabled ) {
			return;
		}

		$to      = get_option( 'wpc_alert_email', get_option( 'admin_email' ) );
		$subject = sprintf(
			/* translators: site name */
			__( '[WP Canary] Mass Plugin Deactivation Detected on %s', 'wp-canary' ),
			get_bloginfo( 'name' )
		);

		$plugin_list = implode( "\n- ", $plugins );
		$message     = sprintf(
			/* translators: 1: count, 2: site URL, 3: plugin list, 4: dashboard URL */
			__(
				"WP Canary has detected that %1\$d plugins were deactivated simultaneously on %2\$s.\n\nDeactivated plugins:\n- %3\$s\n\nThis may indicate a bulk deactivation event or a plugin conflict. Please review your site immediately.\n\nView details: %4\$s",
				'wp-canary'
			),
			count( $plugins ),
			home_url(),
			$plugin_list,
			admin_url( 'admin.php?page=wpc-plugin-watchdog' )
		);

		wp_mail( $to, $subject, $message );
	}

	/**
	 * Remove plugin change records older than 30 days.
	 *
	 * @return void
	 */
	private function prune_old_records() {
		global $wpdb;

		$retention = (int) get_option( 'wpc_data_retention_days', 30 );
		$cutoff    = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention} days" ) );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}wpc_plugin_changes WHERE timestamp < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cutoff
			)
		);
	}
}
