<?php
/**
 * PHP Monitor (Pro).
 *
 * @package WP_Site_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_PHP_Monitor
 *
 * Reads PHP configuration limits and current usage, checks
 * debug.log for resource exhaustion events, and produces a health report.
 */
class WPC_PHP_Monitor {

	/**
	 * Generate a PHP health report.
	 *
	 * @return array
	 */
	public function get_report() {
		$limits  = $this->get_php_limits();
		$usage   = $this->get_memory_usage();
		$flags   = $this->check_flags( $limits, $usage );
		$log     = $this->check_debug_log();

		return array(
			'limits'          => $limits,
			'usage'           => $usage,
			'flags'           => array_merge( $flags, $log['flags'] ),
			'log_summary'     => $log['summary'],
			'recommendations' => $this->build_recommendations( $limits, $flags, $log ),
			'generated_at'    => current_time( 'mysql' ),
		);
	}

	/**
	 * Read key PHP configuration values.
	 *
	 * @return array
	 */
	private function get_php_limits() {
		return array(
			'memory_limit'        => ini_get( 'memory_limit' ),
			'max_execution_time'  => ini_get( 'max_execution_time' ),
			'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
			'post_max_size'       => ini_get( 'post_max_size' ),
			'max_input_vars'      => ini_get( 'max_input_vars' ),
			'php_version'         => PHP_VERSION,
		);
	}

	/**
	 * Get current and peak memory usage.
	 *
	 * @return array
	 */
	private function get_memory_usage() {
		$memory_limit_bytes = $this->parse_bytes( ini_get( 'memory_limit' ) );
		$current            = memory_get_usage( true );
		$peak               = memory_get_peak_usage( true );

		$peak_percent = $memory_limit_bytes > 0
			? round( ( $peak / $memory_limit_bytes ) * 100, 1 )
			: 0;

		return array(
			'current_bytes'    => $current,
			'current_human'    => $this->human_filesize( $current ),
			'peak_bytes'       => $peak,
			'peak_human'       => $this->human_filesize( $peak ),
			'limit_bytes'      => $memory_limit_bytes,
			'peak_percent'     => $peak_percent,
		);
	}

	/**
	 * Check live flags from PHP state.
	 *
	 * @param array $limits PHP limit values.
	 * @param array $usage  Memory usage data.
	 * @return array
	 */
	private function check_flags( $limits, $usage ) {
		$flags = array();

		// High peak memory usage.
		if ( $usage['peak_percent'] > 80 ) {
			$flags[] = array(
				'severity' => 'warning',
				'source'   => 'memory',
				'message'  => sprintf(
					/* translators: 1: peak usage, 2: limit */
					__( 'Peak memory usage is %1$s (%2$s%% of limit). Consider increasing memory_limit.', 'wp-canary' ),
					$usage['peak_human'],
					$usage['peak_percent']
				),
			);
		}

		// Very low execution time limit.
		$max_exec = (int) $limits['max_execution_time'];
		if ( $max_exec > 0 && $max_exec < 30 ) {
			$flags[] = array(
				'severity' => 'warning',
				'source'   => 'max_execution_time',
				'message'  => sprintf(
					/* translators: seconds */
					__( 'max_execution_time is set to %d seconds. This may cause timeouts on complex pages.', 'wp-canary' ),
					$max_exec
				),
			);
		}

		// Low max_input_vars.
		$max_vars = (int) $limits['max_input_vars'];
		if ( $max_vars > 0 && $max_vars < 1000 ) {
			$flags[] = array(
				'severity' => 'warning',
				'source'   => 'max_input_vars',
				'message'  => sprintf(
					/* translators: count */
					__( 'max_input_vars is set to %d. Complex forms or menus may be silently truncated.', 'wp-canary' ),
					$max_vars
				),
			);
		}

		return $flags;
	}

	/**
	 * Scan the debug log for memory and timeout error patterns.
	 *
	 * @return array { flags: array, summary: array }
	 */
	private function check_debug_log() {
		$flags   = array();
		$summary = array( 'memory_errors' => 0, 'timeout_errors' => 0 );

		$log_path = WP_CONTENT_DIR . '/debug.log';
		$override = get_option( 'wpc_debug_log_path', '' );
		if ( ! empty( $override ) ) {
			$log_path = $override;
		}

		if ( ! file_exists( $log_path ) || ! is_readable( $log_path ) ) {
			return array( 'flags' => $flags, 'summary' => $summary );
		}

		$content = file_get_contents( $log_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $content ) {
			return array( 'flags' => $flags, 'summary' => $summary );
		}

		$memory_count  = preg_match_all( '/Allowed memory size/i', $content );
		$timeout_count = preg_match_all( '/Maximum execution time/i', $content );

		$summary['memory_errors']  = $memory_count ?: 0;
		$summary['timeout_errors'] = $timeout_count ?: 0;

		if ( $memory_count > 0 ) {
			$flags[] = array(
				'severity' => 'critical',
				'source'   => 'debug_log',
				'message'  => sprintf(
					/* translators: count */
					__( 'Found %d "Allowed memory size" error(s) in debug.log. Your site is running out of memory.', 'wp-canary' ),
					$memory_count
				),
			);
		}

		if ( $timeout_count > 0 ) {
			$flags[] = array(
				'severity' => 'critical',
				'source'   => 'debug_log',
				'message'  => sprintf(
					/* translators: count */
					__( 'Found %d "Maximum execution time" error(s) in debug.log. Scripts are timing out.', 'wp-canary' ),
					$timeout_count
				),
			);
		}

		return array( 'flags' => $flags, 'summary' => $summary );
	}

	/**
	 * Build human-readable recommendations from the report data.
	 *
	 * @param array $limits PHP limits.
	 * @param array $flags  Runtime flags.
	 * @param array $log    Debug log data.
	 * @return array
	 */
	private function build_recommendations( $limits, $flags, $log ) {
		$recs = array();

		$has_memory_flag = false;
		foreach ( $flags as $flag ) {
			if ( 'memory' === $flag['source'] || 'debug_log' === $flag['source'] ) {
				$has_memory_flag = true;
			}
		}

		if ( $has_memory_flag ) {
			$current = $limits['memory_limit'];
			$recs[]  = sprintf(
				/* translators: current memory limit */
				__( 'Increase memory_limit in wp-config.php or php.ini. Current value: %s. Recommended: 256M or higher.', 'wp-canary' ),
				$current
			);
		}

		if ( $log['summary']['timeout_errors'] > 0 ) {
			$recs[] = __( 'Increase max_execution_time to at least 60 seconds, or optimize slow queries causing timeouts.', 'wp-canary' );
		}

		if ( empty( $recs ) ) {
			$recs[] = __( 'PHP resource limits look healthy. No immediate action required.', 'wp-canary' );
		}

		return $recs;
	}

	/**
	 * Parse PHP shorthand byte notation to bytes.
	 *
	 * @param string $val Value like '256M', '1G', '512K'.
	 * @return int
	 */
	private function parse_bytes( $val ) {
		$val  = trim( $val );
		$last = strtolower( $val[ strlen( $val ) - 1 ] );
		$num  = (int) $val;

		switch ( $last ) {
			case 'g':
				$num *= 1024 * 1024 * 1024;
				break;
			case 'm':
				$num *= 1024 * 1024;
				break;
			case 'k':
				$num *= 1024;
				break;
		}

		return $num;
	}

	/**
	 * Format bytes as human-readable string.
	 *
	 * @param int $bytes Byte count.
	 * @return string
	 */
	private function human_filesize( $bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB' );
		$i     = 0;
		while ( $bytes >= 1024 && $i < count( $units ) - 1 ) {
			$bytes /= 1024;
			$i++;
		}
		return round( $bytes, 1 ) . ' ' . $units[ $i ];
	}
}
