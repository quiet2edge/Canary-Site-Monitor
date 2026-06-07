<?php
/**
 * Plugin Name:       WP Canary
 * Plugin URI:        https://wpsiteguardian.com
 * Description:       Proactive WordPress diagnostic plugin that detects, explains, and fixes site-breaking issues before they cause downtime.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            WP Canary
 * Author URI:        https://wpsiteguardian.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-canary
 * Domain Path:       /languages
 * Network:           true
 *
 * @package WP_Site_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'WPC_VERSION', '1.0.0' );
define( 'WPC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPC_PLUGIN_FILE', __FILE__ );

/**
 * Freemius bootstrap stub.
 *
 * Returns the Freemius instance if the SDK is available,
 * otherwise returns null.
 *
 * @return Freemius|null
 */
function wpc_fs() {
	global $wpc_fs;

	if ( isset( $wpc_fs ) ) {
		return $wpc_fs;
	}

	$sdk_path = WPC_PLUGIN_DIR . 'freemius/start.php';

	if ( file_exists( $sdk_path ) ) {
		if ( ! function_exists( 'fs_dynamic_init' ) ) {
			require_once $sdk_path;
		}

		$wpc_fs = fs_dynamic_init(
			array(
				'id'             => '0000',
				'slug'           => 'wp-canary',
				'type'           => 'plugin',
				'public_key'     => 'pk_REPLACE_WITH_YOUR_PUBLIC_KEY',
				'is_premium'     => false,
				'has_addons'     => false,
				'has_paid_plans' => true,
				'menu'           => array(
					'slug'        => 'wp-canary',
					'first-path'  => 'admin.php?page=wp-canary',
					'account'     => true,
					'contact'     => false,
					'support'     => false,
				),
			)
		);
	}

	return $wpc_fs;
}

/**
 * Helper to check if the current site is running the Pro plan.
 *
 * When the Freemius SDK is not installed (development / free install)
 * this always returns false so the plugin degrades gracefully.
 *
 * @return bool
 */
function wpc_is_pro() {
	$fs = wpc_fs();

	if ( null === $fs ) {
		// SDK not installed — check a manual option set during testing.
		return (bool) get_option( 'wpc_pro_override', false );
	}

	return $fs->is__premium_only() || ! $fs->is_free_plan();
}

// Load core files.
require_once WPC_PLUGIN_DIR . 'includes/class-wpc-activator.php';
require_once WPC_PLUGIN_DIR . 'includes/class-wpc-deactivator.php';
require_once WPC_PLUGIN_DIR . 'includes/class-wpc-loader.php';

/**
 * Activation hook callback.
 */
function wpc_activate() {
	WPC_Activator::activate();
}

/**
 * Deactivation hook callback.
 */
function wpc_deactivate() {
	WPC_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'wpc_activate' );
register_deactivation_hook( __FILE__, 'wpc_deactivate' );

/**
 * Begins plugin execution.
 *
 * @return void
 */
function wpc_run() {
	$plugin = new WPC_Loader();
	$plugin->run();
}

wpc_run();
