<?php
/**
 * Plugin Health module (Pro).
 *
 * @package WP_Canary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_Plugin_Health
 *
 * Detects update gaps, missing licenses, and multi-factor risk conditions
 * across installed plugins.
 */
class WPC_Plugin_Health {

	/**
	 * Main entry point — runs all sub-checks and returns combined results.
	 *
	 * @return array
	 */
	public function run_scan() {
		$results = array(
			'update_gaps'      => $this->check_update_gaps(),
			'license_health'   => $this->check_license_health(),
			'risk_assessments' => array(),
			'scanned_at'       => current_time( 'mysql' ),
		);

		// Build risk assessments by correlating findings across checks.
		$all_plugins = array_unique(
			array_merge(
				array_column( $results['update_gaps'], 'plugin_file' ),
				array_column( $results['license_health'], 'plugin_file' )
			)
		);

		foreach ( $all_plugins as $plugin_file ) {
			$assessment = $this->get_risk_assessment( $plugin_file, $results );
			if ( $assessment ) {
				$results['risk_assessments'][] = array(
					'plugin_file' => $plugin_file,
					'message'     => $assessment,
				);
			}
		}

		$this->save_scan_results( $results );
		return $results;
	}

	/**
	 * Detect plugins with large update gaps or version compatibility issues.
	 *
	 * @return array
	 */
	public function check_update_gaps() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins       = get_plugins();
		$update_data   = get_site_transient( 'update_plugins' );
		$current_wp    = get_bloginfo( 'version' );
		$wp_major      = (float) $current_wp;
		$issues        = array();

		foreach ( $plugins as $plugin_file => $plugin_data ) {
			$plugin_name = $plugin_data['Name'];
			$tested_up_to = isset( $plugin_data['Tested up to'] ) ? $plugin_data['Tested up to'] : '';
			$last_updated = '';
			$days_since   = null;

			// Prefer the cached plugin API info when available.
			$api_transient_key = 'wpc_plugin_api_' . md5( $plugin_file );
			$api_info          = get_transient( $api_transient_key );

			if ( ! empty( $api_info ) && ! empty( $api_info['last_updated'] ) ) {
				$last_updated = $api_info['last_updated'];
			} elseif (
				isset( $update_data->checked )
				&& isset( $update_data->response[ $plugin_file ] )
				&& isset( $update_data->response[ $plugin_file ]->last_updated )
			) {
				$last_updated = $update_data->response[ $plugin_file ]->last_updated;
			} elseif (
				isset( $update_data->no_update[ $plugin_file ] )
				&& isset( $update_data->no_update[ $plugin_file ]->last_updated )
			) {
				$last_updated = $update_data->no_update[ $plugin_file ]->last_updated;
			}

			if ( $last_updated ) {
				$updated_ts  = strtotime( $last_updated );
				$days_since  = (int) floor( ( time() - $updated_ts ) / DAY_IN_SECONDS );
			}

			$severity       = '';
			$message_parts  = array();
			$wp_version_gap = null;

			if ( null !== $days_since ) {
				if ( $days_since >= 180 ) {
					$severity        = 'critical';
					$message_parts[] = sprintf(
						__( '%s has not been updated in %d days — it may be abandoned or incompatible with your WordPress version.', 'wp-canary' ),
						$plugin_name,
						$days_since
					);
				} elseif ( $days_since >= 90 ) {
					$severity        = 'warning';
					$message_parts[] = sprintf(
						__( '%s has not been updated in %d days.', 'wp-canary' ),
						$plugin_name,
						$days_since
					);
				}
			}

			if ( $tested_up_to ) {
				$tested_major   = (float) $tested_up_to;
				$wp_version_gap = round( $wp_major - $tested_major, 1 );

				if ( $wp_version_gap >= 2 ) {
					$severity        = $severity ?: 'warning';
					$message_parts[] = sprintf(
						__( '%s was last tested with WordPress %s — your site runs %s (%.1f major versions behind).', 'wp-canary' ),
						$plugin_name,
						$tested_up_to,
						$current_wp,
						$wp_version_gap
					);
				}
			}

			if ( ! $severity ) {
				continue;
			}

			$issues[] = array(
				'plugin_file'       => $plugin_file,
				'plugin_name'       => $plugin_name,
				'last_updated'      => $last_updated,
				'days_since_update' => $days_since,
				'tested_up_to'      => $tested_up_to,
				'wp_version_gap'    => $wp_version_gap,
				'severity'          => $severity,
				'message'           => implode( ' ', $message_parts ),
			);
		}

		return $issues;
	}

	/**
	 * Check license key options for known premium plugins.
	 *
	 * @return array
	 */
	public function check_license_health() {
		$known_premium = array(
			'rank-math-pro/rank-math-pro.php'                                      => array(
				'name'   => 'Rank Math Pro',
				'option' => 'rank_math_pro_license_key',
			),
			'wp-rocket/wp-rocket.php'                                              => array(
				'name'   => 'WP Rocket',
				'option' => 'wp_rocket_settings',
			),
			'elementor-pro/elementor-pro.php'                                      => array(
				'name'   => 'Elementor Pro',
				'option' => 'elementor_pro_license_key',
			),
			'gravityforms/gravityforms.php'                                        => array(
				'name'   => 'Gravity Forms',
				'option' => 'rg_gforms_key',
			),
			'advanced-custom-fields-pro/acf.php'                                   => array(
				'name'   => 'ACF Pro',
				'option' => 'acf_pro_license',
			),
			'wpml-multilingual-cms/plugin.php'                                     => array(
				'name'   => 'WPML',
				'option' => 'icl_sitekey',
			),
			'woocommerce-subscriptions/woocommerce-subscriptions.php'              => array(
				'name'   => 'WooCommerce Subscriptions',
				'option' => 'woocommerce_subscriptions_license_key',
			),
		);

		$active_plugins = (array) get_option( 'active_plugins', array() );
		$issues         = array();

		foreach ( $known_premium as $plugin_file => $info ) {
			if ( ! in_array( $plugin_file, $active_plugins, true ) ) {
				continue;
			}

			$license_value = get_option( $info['option'], '' );
			$has_license   = ! empty( $license_value );

			if ( $has_license && is_array( $license_value ) ) {
				// wp_rocket_settings stores an array; check for a key inside it.
				$has_license = ! empty( $license_value['license'] ) || ! empty( $license_value['key'] );
			}

			if ( $has_license ) {
				continue;
			}

			$issues[] = array(
				'plugin_file'    => $plugin_file,
				'plugin_name'    => $info['name'],
				'license_status' => 'missing',
				'severity'       => 'warning',
				'message'        => sprintf(
					__( '%s: License key not found — plugin may not receive updates.', 'wp-canary' ),
					$info['name']
				),
			);
		}

		return $issues;
	}

	/**
	 * Return a plain-English combined risk sentence when multiple factors align.
	 *
	 * When only one risk factor is present this returns an empty string — the
	 * individual check messages handle single-factor cases.
	 *
	 * @param string $plugin_file Plugin file key (e.g. rank-math-pro/rank-math-pro.php).
	 * @param array  $results     Already-computed scan results to avoid redundant work.
	 * @return string
	 */
	public function get_risk_assessment( $plugin_file, $results = array() ) {
		if ( empty( $results ) ) {
			$results = $this->get_last_scan_results();
		}

		$update_gap   = null;
		$license_miss = null;
		$db_issue     = false;

		foreach ( (array) ( $results['update_gaps'] ?? array() ) as $item ) {
			if ( isset( $item['plugin_file'] ) && $item['plugin_file'] === $plugin_file ) {
				$update_gap = $item;
				break;
			}
		}

		foreach ( (array) ( $results['license_health'] ?? array() ) as $item ) {
			if ( isset( $item['plugin_file'] ) && $item['plugin_file'] === $plugin_file ) {
				$license_miss = $item;
				break;
			}
		}

		// Cross-reference DB collation issues for plugin-owned tables.
		$db_scan = (array) get_option( 'wpc_last_db_scan_results', array() );
		$slug    = dirname( $plugin_file );
		foreach ( $db_scan as $issue ) {
			if (
				isset( $issue['type'], $issue['table'] ) &&
				'collation_mismatch' === $issue['type'] &&
				false !== strpos( $issue['table'], $slug )
			) {
				$db_issue = true;
				break;
			}
		}

		$risk_count = ( $update_gap ? 1 : 0 ) + ( $license_miss ? 1 : 0 ) + ( $db_issue ? 1 : 0 );

		if ( $risk_count < 2 ) {
			return '';
		}

		$plugin_name = $update_gap ? $update_gap['plugin_name'] : basename( dirname( $plugin_file ) );
		$parts       = array();

		if ( $update_gap ) {
			$months = $update_gap['days_since_update'] ? round( $update_gap['days_since_update'] / 30 ) : null;
			if ( $months ) {
				$parts[] = sprintf(
					/* translators: 1: plugin name, 2: months */
					__( '%1$s has not been updated in %2$d months', 'wp-canary' ),
					$plugin_name,
					$months
				);
			}
		}

		if ( $license_miss ) {
			$parts[] = __( 'its license key is missing', 'wp-canary' );
		}

		if ( $db_issue ) {
			$parts[] = __( 'its database tables have a collation mismatch', 'wp-canary' );
		}

		if ( empty( $parts ) ) {
			return '';
		}

		$sentence = implode( __( ' and ', 'wp-canary' ), $parts );

		return sprintf(
			/* translators: combined risk factors sentence */
			__( '%s — this is likely causing site instability.', 'wp-canary' ),
			ucfirst( $sentence )
		);
	}

	/**
	 * Retrieve cached scan results.
	 *
	 * @return array
	 */
	public function get_last_scan_results() {
		return (array) get_option( 'wpc_plugin_health_results', array() );
	}

	/**
	 * Persist scan results with a timestamp.
	 *
	 * @param array $results Scan results array.
	 * @return void
	 */
	public function save_scan_results( $results ) {
		$results['saved_at'] = current_time( 'mysql' );
		update_option( 'wpc_plugin_health_results', $results );
	}
}
