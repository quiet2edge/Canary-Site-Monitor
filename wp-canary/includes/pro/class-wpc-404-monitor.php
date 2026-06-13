<?php
/**
 * 404 Monitor (Pro).
 *
 * @package WP_Canary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_404_Monitor
 *
 * Logs 404 requests, detects bot traffic, and identifies flood events.
 */
class WPC_404_Monitor {

	/**
	 * Bot signature map: regex pattern => [ name, category ].
	 *
	 * @var array
	 */
	private $bot_signatures = array(
		'/Googlebot(?!-Mobile|-Image)/i'  => array( 'Googlebot', 'Google' ),
		'/Googlebot-Mobile/i'             => array( 'Googlebot-Mobile', 'Google' ),
		'/Googlebot-Image/i'              => array( 'Googlebot-Image', 'Google' ),
		'/AdsBot-Google/i'                => array( 'AdsBot-Google', 'Google' ),
		'/APIs-Google/i'                  => array( 'APIs-Google', 'Google' ),
		'/facebookexternalhit/i'          => array( 'facebookexternalhit', 'Meta' ),
		'/Meta-ExternalAgent/i'           => array( 'Meta-ExternalAgent', 'Meta' ),
		'/Facebot/i'                      => array( 'Facebot', 'Meta' ),
		'/bingbot/i'                      => array( 'bingbot', 'Microsoft' ),
		'/BingPreview/i'                  => array( 'BingPreview', 'Microsoft' ),
		'/AdIdxBot/i'                     => array( 'AdIdxBot', 'Microsoft' ),
		'/MicrosoftPreview/i'             => array( 'MicrosoftPreview', 'Microsoft' ),
		'/(?:bot|crawl|spider|slurp)/i'   => array( 'Generic Bot', 'Generic' ),
	);

	/**
	 * Log a 404 request if is_404() is true.
	 *
	 * @return void
	 */
	public function maybe_log_404() {
		if ( ! is_404() ) {
			return;
		}

		global $wpdb;

		$user_agent  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$referer     = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$ip          = $this->get_client_ip();
		$bot_info    = $this->detect_bot( $user_agent );
		$blog_id     = get_current_blog_id();

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wpc_404_log',
			array(
				'blog_id'      => $blog_id,
				'timestamp'    => current_time( 'mysql' ),
				'request_uri'  => $request_uri,
				'ip_address'   => $ip,
				'user_agent'   => $user_agent,
				'referer'      => $referer,
				'is_bot'       => $bot_info['is_bot'] ? 1 : 0,
				'bot_name'     => $bot_info['name'],
				'bot_category' => $bot_info['category'],
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		// Check for flood.
		$this->maybe_flag_flood( $ip, $blog_id );
	}

	/**
	 * Detect whether a user agent belongs to a known bot.
	 *
	 * @param string $user_agent Raw user agent string.
	 * @return array { is_bot: bool, name: string, category: string }
	 */
	public function detect_bot( $user_agent ) {
		if ( empty( $user_agent ) ) {
			return array( 'is_bot' => false, 'name' => '', 'category' => '' );
		}

		foreach ( $this->bot_signatures as $pattern => $info ) {
			if ( preg_match( $pattern, $user_agent ) ) {
				return array(
					'is_bot'   => true,
					'name'     => $info[0],
					'category' => $info[1],
				);
			}
		}

		return array( 'is_bot' => false, 'name' => '', 'category' => '' );
	}

	/**
	 * Check if the IP is flooding — more than threshold 404s in rolling 1 hour.
	 * If flooding, send alert and optionally flag DB stress correlation.
	 *
	 * @param string $ip      Client IP address.
	 * @param int    $blog_id Current blog ID.
	 * @return void
	 */
	private function maybe_flag_flood( $ip, $blog_id ) {
		global $wpdb;

		$threshold = (int) get_option( 'wpc_flood_threshold', 50 );
		$one_hour_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) );

		$count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpc_404_log WHERE ip_address = %s AND blog_id = %d AND timestamp >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$ip,
				$blog_id,
				$one_hour_ago
			)
		);

		if ( $count < $threshold ) {
			return;
		}

		// Only fire the alert once per flood window — check if we already alerted.
		$flood_key = 'wpc_flood_alert_' . md5( $ip . gmdate( 'Y-m-d-H' ) );
		if ( get_transient( $flood_key ) ) {
			return;
		}
		set_transient( $flood_key, 1, HOUR_IN_SECONDS );

		// Flag DB stress correlation.
		$db_stress = ! empty( $wpdb->last_error );

		$this->send_flood_alert( $ip, $count, $db_stress );

		// Store active flood in options for dashboard display.
		$active_floods = (array) get_option( 'wpc_active_floods', array() );
		$active_floods[ $ip ] = array(
			'ip'        => $ip,
			'count'     => $count,
			'db_stress' => $db_stress,
			'started'   => current_time( 'mysql' ),
		);
		update_option( 'wpc_active_floods', $active_floods );
	}

	/**
	 * Send flood alert email.
	 *
	 * @param string $ip       Flooding IP.
	 * @param int    $count    Number of 404s in the window.
	 * @param bool   $db_stress Whether DB stress is detected.
	 * @return void
	 */
	private function send_flood_alert( $ip, $count, $db_stress ) {
		$enabled = get_option( 'wpc_email_alerts_enabled', true );
		if ( ! $enabled ) {
			return;
		}

		$to      = get_option( 'wpc_alert_email', get_option( 'admin_email' ) );
		$subject = sprintf(
			/* translators: site name */
			__( '[WP Canary] 404 Flood Detected on %s', 'wp-canary' ),
			get_bloginfo( 'name' )
		);

		$db_note = $db_stress
			? __( "\n\nWARNING: A database error was also detected in the same hour — this may indicate DB stress caused by the flood.", 'wp-canary' )
			: '';

		$message = sprintf(
			/* translators: 1: IP address, 2: count, 3: site, 4: DB note, 5: dashboard URL */
			__(
				"WP Canary has detected a 404 flood from IP %1\$s (%2\$d requests in the past hour) on %3\$s.%4\$s\n\nView details and take action: %5\$s",
				'wp-canary'
			),
			$ip,
			$count,
			home_url(),
			$db_note,
			admin_url( 'admin.php?page=wpc-404-monitor' )
		);

		wp_mail( $to, $subject, $message );
	}

	/**
	 * Get client IP.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
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
	 * Get a per-IP summary from the 404 log for the past N days.
	 *
	 * @param int $days Number of days to look back.
	 * @return array
	 */
	public function get_ip_summary( $days = 7 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT ip_address, COUNT(*) as hit_count, MAX(timestamp) as last_seen,
				        SUM(is_bot) as bot_hits, bot_name, bot_category
				 FROM {$wpdb->prefix}wpc_404_log
				 WHERE timestamp >= %s AND blog_id = %d
				 GROUP BY ip_address
				 ORDER BY hit_count DESC
				 LIMIT 100", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since,
				get_current_blog_id()
			),
			ARRAY_A
		) ?? array();
	}
}
