<?php
/**
 * Cron Health Checker (Pro).
 *
 * @package WP_Site_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_Cron_Health
 *
 * Audits scheduled cron events, detects missed runs, and recommends
 * server-side cron when appropriate.
 */
class WPC_Cron_Health {

	/**
	 * Run the cron health check and store results.
	 *
	 * @return void
	 */
	public function run_check() {
		// Record that this cron ran.
		$runs = (array) get_option( 'wpc_last_cron_runs', array() );
		$runs['wpc_cron_health_check'] = current_time( 'mysql' );
		update_option( 'wpc_last_cron_runs', $runs );

		$report = $this->generate_report();
		update_option( 'wpc_cron_health_report', $report );
	}

	/**
	 * Generate a cron health report.
	 *
	 * @return array
	 */
	public function generate_report() {
		$cron_array = _get_cron_array();
		$now        = time();
		$events     = array();
		$last_runs  = (array) get_option( 'wpc_last_cron_runs', array() );

		if ( ! is_array( $cron_array ) ) {
			$cron_array = array();
		}

		// Build a flat list of all cron events.
		$flat = array();
		foreach ( $cron_array as $timestamp => $hooks ) {
			foreach ( $hooks as $hook => $hook_events ) {
				foreach ( $hook_events as $key => $event ) {
					$flat[] = array(
						'hook'      => $hook,
						'next_run'  => (int) $timestamp,
						'schedule'  => isset( $event['schedule'] ) ? $event['schedule'] : false,
						'interval'  => isset( $event['interval'] ) ? (int) $event['interval'] : 0,
					);
				}
			}
		}

		foreach ( $flat as $event ) {
			$hook      = $event['hook'];
			$schedule  = $event['schedule'];
			$interval  = $event['interval'];
			$next_run  = $event['next_run'];
			$last_run  = isset( $last_runs[ $hook ] ) ? strtotime( $last_runs[ $hook ] ) : null;

			// Determine status.
			$status    = 'ok';
			$overdue_by = $next_run - $now;

			if ( $next_run < $now ) {
				$overdue_seconds = $now - $next_run;
				// Missed: more than 2x the interval overdue.
				if ( $interval > 0 && $overdue_seconds > ( 2 * $interval ) ) {
					$status = 'missed';
				} elseif ( $overdue_seconds > 300 ) {
					// Late: more than 5 minutes past due.
					$status = 'late';
				}
			}

			$events[] = array(
				'cron_hook' => $hook,
				'schedule'  => $schedule ? $schedule : __( 'single event', 'wp-canary' ),
				'interval'  => $interval,
				'last_run'  => null !== $last_run ? gmdate( 'Y-m-d H:i:s', $last_run ) : __( 'Unknown', 'wp-canary' ),
				'next_run'  => gmdate( 'Y-m-d H:i:s', $next_run ),
				'status'    => $status,
				'overdue_by' => max( 0, $now - $next_run ),
			);
		}

		return array(
			'generated_at'       => current_time( 'mysql' ),
			'using_server_cron'  => ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ),
			'total_events'       => count( $events ),
			'missed_count'       => count( array_filter( $events, function ( $e ) { return 'missed' === $e['status']; } ) ),
			'late_count'         => count( array_filter( $events, function ( $e ) { return 'late' === $e['status']; } ) ),
			'events'             => $events,
			'recommendation'     => $this->build_recommendation(),
		);
	}

	/**
	 * Build a server-cron recommendation if applicable.
	 *
	 * @return string
	 */
	private function build_recommendation() {
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			return __( 'Server-side cron is configured. WP-Cron is disabled — this is the recommended setup for production sites.', 'wp-canary' );
		}

		// Heuristic: check if wpc cron ran in the past hour.
		$last_runs = (array) get_option( 'wpc_last_cron_runs', array() );
		$recent    = false;
		foreach ( $last_runs as $hook => $time ) {
			if ( strtotime( $time ) > ( time() - HOUR_IN_SECONDS ) ) {
				$recent = true;
				break;
			}
		}

		if ( ! $recent ) {
			return __( 'No cron jobs ran in the past hour. If your site has low traffic, WP-Cron may be unreliable. Consider setting up a real server cron job: add "*/5 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1" to your crontab, and define(\'DISABLE_WP_CRON\', true) in wp-config.php.', 'wp-canary' );
		}

		return __( 'WP-Cron is running normally. For high-traffic sites, consider server-side cron for better reliability.', 'wp-canary' );
	}
}
