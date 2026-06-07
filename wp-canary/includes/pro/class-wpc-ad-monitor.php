<?php
/**
 * Ad Monitor (Pro).
 *
 * @package WP_Site_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_Ad_Monitor
 *
 * Analyzes 404 logs for ad network bot traffic patterns and
 * checks for ad-script conflicts. Runs during off-hours.
 */
class WPC_Ad_Monitor {

	/**
	 * Ad-script to plugin map: script global => plugin name.
	 *
	 * @var array
	 */
	private $ad_scripts = array(
		'googletag' => 'Google Tag / DFP',
		'fbq'       => 'Facebook Pixel',
		'_mfq'      => 'Mouseflow',
		'uetq'      => 'Bing Universal Event Tracking',
	);

	/**
	 * Known conflicting plugin slugs for ad scripts.
	 *
	 * @var array
	 */
	private $known_conflicts = array(
		'header-footer-code-manager',
		'insert-headers-and-footers',
		'gtm4wp',
		'google-tag-manager-for-wordpress',
	);

	/**
	 * Run the ad monitor analysis.
	 * Only processes data during off-hours (midnight–6am site timezone).
	 *
	 * @return void
	 */
	public function run_analysis() {
		update_option( 'wpc_last_cron_runs', array_merge(
			(array) get_option( 'wpc_last_cron_runs', array() ),
			array( 'wpc_ad_monitor' => current_time( 'mysql' ) )
		) );

		$hour = (int) ( new DateTime( 'now', wp_timezone() ) )->format( 'G' );
		if ( $hour >= 6 && $hour < 24 ) {
			// Outside off-hours window — skip.
			return;
		}

		$report = array(
			'generated_at'       => current_time( 'mysql' ),
			'adsbot_anomalies'   => $this->check_adsbot_anomalies(),
			'hour_clustering'    => $this->check_unusual_hour_clustering(),
			'wp_cron_triggers'   => $this->check_ad_bot_cron_triggers(),
			'script_conflicts'   => $this->check_script_conflicts(),
			'summary'            => '',
		);

		$report['summary'] = $this->build_plain_english_summary( $report );

		update_option( 'wpc_ad_report', $report );
	}

	/**
	 * Check for AdsBot-Google hitting pages outside the sitemap.
	 *
	 * @return array
	 */
	private function check_adsbot_anomalies() {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT request_uri, COUNT(*) as hits
				 FROM {$wpdb->prefix}wpc_404_log
				 WHERE bot_name = 'AdsBot-Google'
				   AND timestamp >= %s
				 GROUP BY request_uri
				 ORDER BY hits DESC
				 LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since
			),
			ARRAY_A
		) ?? array();

		$anomalies = array();
		foreach ( $rows as $row ) {
			$uri = $row['request_uri'];
			// Flag if URI looks like a landing page (no file extension, not wp-admin).
			if ( ! preg_match( '/\.\w{2,5}$/', $uri ) && strpos( $uri, 'wp-admin' ) === false ) {
				$anomalies[] = array(
					'uri'  => $uri,
					'hits' => (int) $row['hits'],
					'note' => __( 'AdsBot-Google crawled this URL and received a 404. This may waste ad crawl budget.', 'wp-canary' ),
				);
			}
		}

		return $anomalies;
	}

	/**
	 * Detect unusual clustering of 404s during midnight–4am.
	 *
	 * @return array
	 */
	private function check_unusual_hour_clustering() {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT HOUR(timestamp) as hour_of_day, COUNT(*) as hit_count
				 FROM {$wpdb->prefix}wpc_404_log
				 WHERE is_bot = 1 AND timestamp >= %s
				 GROUP BY hour_of_day
				 ORDER BY hour_of_day ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since
			),
			ARRAY_A
		) ?? array();

		$midnight_count = 0;
		$total_count    = 0;

		foreach ( $rows as $row ) {
			$total_count += (int) $row['hit_count'];
			if ( (int) $row['hour_of_day'] >= 0 && (int) $row['hour_of_day'] <= 4 ) {
				$midnight_count += (int) $row['hit_count'];
			}
		}

		$percentage = $total_count > 0 ? round( ( $midnight_count / $total_count ) * 100 ) : 0;

		return array(
			'midnight_to_4am_hits' => $midnight_count,
			'total_bot_hits'       => $total_count,
			'percentage'           => $percentage,
			'unusual'              => $percentage > 60,
			'hourly_breakdown'     => $rows,
		);
	}

	/**
	 * Check for ad bots appearing to trigger WP-Cron.
	 *
	 * @return array
	 */
	private function check_ad_bot_cron_triggers() {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

		// Look for referer containing wp-cron or UA = WP-Cron.
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT timestamp, ip_address, request_uri, user_agent, referer
				 FROM {$wpdb->prefix}wpc_404_log
				 WHERE (referer LIKE %s OR user_agent LIKE %s)
				   AND timestamp >= %s
				 LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'%wp-cron%',
				'%WP-Cron%',
				$since
			),
			ARRAY_A
		) ?? array();

		return array(
			'events' => $rows,
			'count'  => count( $rows ),
			'note'   => count( $rows ) > 0
				? __( 'Ad bot activity may be triggering WP-Cron unnecessarily, causing extra server load.', 'wp-canary' )
				: '',
		);
	}

	/**
	 * Check active plugins for known ad-script conflicts.
	 *
	 * @return array
	 */
	private function check_script_conflicts() {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$found          = array();

		foreach ( $this->known_conflicts as $slug ) {
			foreach ( $active_plugins as $plugin_file ) {
				if ( strpos( $plugin_file, $slug ) !== false ) {
					$found[] = array(
						'plugin_file' => $plugin_file,
						'note'        => sprintf(
							/* translators: plugin name */
							__( 'Plugin %s is known to load multiple ad-related scripts, which may cause tag-firing conflicts.', 'wp-canary' ),
							esc_html( $plugin_file )
						),
					);
				}
			}
		}

		return $found;
	}

	/**
	 * Build a plain-English summary from the analysis.
	 *
	 * @param array $report Full report data.
	 * @return string
	 */
	private function build_plain_english_summary( $report ) {
		$lines = array();

		$adsbot = $report['adsbot_anomalies'];
		if ( ! empty( $adsbot ) ) {
			$lines[] = sprintf(
				/* translators: count */
				__( 'Google\'s AdsBot is hitting %d page(s) that return 404 errors. This wastes your ad crawl budget and may affect ad serving.', 'wp-canary' ),
				count( $adsbot )
			);
		}

		$cluster = $report['hour_clustering'];
		if ( isset( $cluster['unusual'] ) && $cluster['unusual'] ) {
			$lines[] = sprintf(
				/* translators: percentage */
				__( '%d%% of bot 404s occur between midnight and 4am. This unusual clustering may indicate aggressive scraping or misconfigured ad crawlers.', 'wp-canary' ),
				$cluster['percentage']
			);
		}

		$cron = $report['wp_cron_triggers'];
		if ( $cron['count'] > 0 ) {
			$lines[] = sprintf(
				/* translators: count */
				__( 'Ad bot traffic appears to be triggering WP-Cron %d time(s) in the past week, which adds unnecessary server load.', 'wp-canary' ),
				$cron['count']
			);
		}

		$conflicts = $report['script_conflicts'];
		if ( ! empty( $conflicts ) ) {
			$lines[] = sprintf(
				/* translators: count */
				__( '%d active plugin(s) are known to cause ad-script conflicts. This can cause duplicate tag firing or broken ad tracking.', 'wp-canary' ),
				count( $conflicts )
			);
		}

		if ( empty( $lines ) ) {
			return __( 'No ad interference issues detected in the past 7 days. Your advertising setup appears healthy.', 'wp-canary' );
		}

		return implode( "\n\n", $lines );
	}
}
