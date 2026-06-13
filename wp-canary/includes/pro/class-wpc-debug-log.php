<?php
/**
 * Debug Log reader (Pro).
 *
 * @package WP_Canary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_Debug_Log
 *
 * Reads and analyzes wp-content/debug.log, filtering noise,
 * grouping duplicates, and identifying responsible plugins.
 */
class WPC_Debug_Log {

	/**
	 * Maximum lines to read from the end of the log file.
	 */
	const MAX_LINES = 5000;

	/**
	 * Patterns that represent harmless / low-value notices (regex).
	 *
	 * @var array
	 */
	private $noise_patterns = array(
		'/PHP Deprecated.*textdomain/i',
		'/Creating default object from empty value/i',
		'/Function .* is deprecated/i',
		'/Undefined index.*wp-includes/i',
		'/Undefined index.*wp-admin/i',
	);

	/**
	 * Patterns that indicate critical problems.
	 *
	 * @var array
	 */
	private $critical_patterns = array(
		'Fatal error',
		'Maximum execution time',
		'Allowed memory size',
		'Call to undefined function',
		"Table '\\S+' doesn't exist",
	);

	/**
	 * Parse the debug log and return structured entries.
	 *
	 * @return array Array of entry arrays, or [ 'error' => message ] on failure.
	 */
	public function parse() {
		$log_path = $this->get_log_path();

		if ( ! file_exists( $log_path ) || ! is_readable( $log_path ) ) {
			return array(
				'error' => sprintf(
					/* translators: file path */
					__( 'Debug log not found or not readable: %s', 'wp-canary' ),
					esc_html( $log_path )
				),
				'entries' => array(),
			);
		}

		$lines   = $this->read_tail( $log_path, self::MAX_LINES );
		$entries = $this->process_lines( $lines );

		return array(
			'log_path'   => $log_path,
			'lines_read' => count( $lines ),
			'entries'    => $entries,
		);
	}

	/**
	 * Get the path to the debug log file.
	 *
	 * @return string
	 */
	private function get_log_path() {
		$override = get_option( 'wpc_debug_log_path', '' );
		if ( ! empty( $override ) ) {
			return $override;
		}
		return WP_CONTENT_DIR . '/debug.log';
	}

	/**
	 * Read the last N lines of a file efficiently.
	 *
	 * @param string $path  File path.
	 * @param int    $lines Number of lines.
	 * @return array
	 */
	private function read_tail( $path, $lines ) {
		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $content ) {
			return array();
		}

		$all_lines = explode( "\n", $content );
		return array_slice( $all_lines, -$lines );
	}

	/**
	 * Process raw log lines into structured entries.
	 *
	 * @param array $lines Raw log lines.
	 * @return array
	 */
	private function process_lines( $lines ) {
		$raw_entries = array();

		// Pattern to match PHP error log lines.
		$line_regex = '/^\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2} [^\]]+)\] (PHP\s+\w+(?:\s+\w+)?): (.+) in (.+) on line (\d+)/';
		$generic_regex = '/^\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2} [^\]]+)\] (PHP\s+\w+(?:\s+\w+)?): (.+)$/';

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			// Filter noise.
			if ( $this->is_noise( $line ) ) {
				continue;
			}

			if ( preg_match( $line_regex, $line, $m ) ) {
				$raw_entries[] = array(
					'timestamp' => $m[1],
					'type'      => trim( $m[2] ),
					'message'   => trim( $m[3] ),
					'file'      => trim( $m[4] ),
					'line'      => (int) $m[5],
				);
			} elseif ( preg_match( $generic_regex, $line, $m ) ) {
				$raw_entries[] = array(
					'timestamp' => $m[1],
					'type'      => trim( $m[2] ),
					'message'   => trim( $m[3] ),
					'file'      => '',
					'line'      => 0,
				);
			}
		}

		return $this->group_and_enrich( $raw_entries );
	}

	/**
	 * Group identical errors and enrich with plugin attribution and severity.
	 *
	 * @param array $raw_entries Parsed log entries.
	 * @return array
	 */
	private function group_and_enrich( $raw_entries ) {
		$grouped = array();

		foreach ( $raw_entries as $entry ) {
			$key = md5( $entry['message'] . $entry['file'] . $entry['line'] );

			if ( isset( $grouped[ $key ] ) ) {
				$grouped[ $key ]['count']++;
				$grouped[ $key ]['last_seen'] = $entry['timestamp'];
			} else {
				$grouped[ $key ] = array(
					'severity'   => $this->determine_severity( $entry ),
					'message'    => $entry['message'],
					'type'       => $entry['type'],
					'file'       => $entry['file'],
					'line'       => $entry['line'],
					'plugin'     => $this->identify_plugin( $entry['file'] ),
					'count'      => 1,
					'first_seen' => $entry['timestamp'],
					'last_seen'  => $entry['timestamp'],
				);
			}
		}

		// Sort by severity then count.
		$entries = array_values( $grouped );
		usort( $entries, function ( $a, $b ) {
			$sev_order = array( 'critical' => 0, 'warning' => 1, 'info' => 2 );
			$sev_diff  = ( $sev_order[ $a['severity'] ] ?? 2 ) - ( $sev_order[ $b['severity'] ] ?? 2 );
			if ( 0 !== $sev_diff ) {
				return $sev_diff;
			}
			return $b['count'] - $a['count'];
		} );

		return $entries;
	}

	/**
	 * Determine severity for a log entry.
	 *
	 * @param array $entry Parsed entry.
	 * @return string 'critical'|'warning'|'info'
	 */
	private function determine_severity( $entry ) {
		foreach ( $this->critical_patterns as $pattern ) {
			if ( false !== stripos( $entry['message'], $pattern )
				|| false !== stripos( $entry['type'], $pattern ) ) {
				return 'critical';
			}
		}

		if ( false !== stripos( $entry['type'], 'Fatal' )
			|| false !== stripos( $entry['type'], 'Error' ) ) {
			return 'critical';
		}

		if ( false !== stripos( $entry['type'], 'Warning' )
			|| false !== stripos( $entry['type'], 'Notice' ) ) {
			return 'warning';
		}

		return 'info';
	}

	/**
	 * Determine which plugin is responsible based on the file path.
	 *
	 * @param string $file_path File path from the log entry.
	 * @return string Plugin slug or empty string.
	 */
	private function identify_plugin( $file_path ) {
		if ( empty( $file_path ) ) {
			return '';
		}

		$plugins_dir = WP_PLUGIN_DIR;
		$pos = strpos( $file_path, $plugins_dir );

		if ( false === $pos ) {
			// Try relative path.
			$pos = strpos( $file_path, 'wp-content/plugins/' );
			if ( false === $pos ) {
				return '';
			}
			$relative = substr( $file_path, $pos + strlen( 'wp-content/plugins/' ) );
		} else {
			$relative = substr( $file_path, $pos + strlen( $plugins_dir ) + 1 );
		}

		$parts = explode( '/', ltrim( $relative, '/' ) );
		return $parts[0] ?? '';
	}

	/**
	 * Check if a line matches a noise pattern.
	 *
	 * @param string $line Log line.
	 * @return bool
	 */
	private function is_noise( $line ) {
		foreach ( $this->noise_patterns as $pattern ) {
			if ( preg_match( $pattern, $line ) ) {
				return true;
			}
		}
		return false;
	}
}
