<?php
/**
 * Plugin Name:       WP Site Guardian
 * Plugin URI:        https://wpsiteguardian.com
 * Description:       Proactive WordPress diagnostic plugin that detects, explains, and fixes site-breaking issues before they cause downtime.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            WP Site Guardian
 * Author URI:        https://wpsiteguardian.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-site-guardian
 * Domain Path:       /languages
 * Network:           true
 *
 * @package WP_Site_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'WPSG_VERSION', '1.0.0' );
define( 'WPSG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPSG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPSG_PLUGIN_FILE', __FILE__ );

/**
 * Freemius bootstrap stub.
 *
 * Returns the Freemius instance if the SDK is available,
 * otherwise returns null.
 *
 * @return Freemius|null
 */
function wpsg_fs() {
	global $wpsg_fs;

	if ( isset( $wpsg_fs ) ) {
		return $wpsg_fs;
	}

	$sdk_path = WPSG_PLUGIN_DIR . 'freemius/start.php';

	if ( file_exists( $sdk_path ) ) {
		if ( ! function_exists( 'fs_dynamic_init' ) ) {
			require_once $sdk_path;
		}

		$wpsg_fs = fs_dynamic_init(
			array(
				'id'             => '0000',
				'slug'           => 'wp-site-guardian',
				'type'           => 'plugin',
				'public_key'     => 'pk_REPLACE_WITH_YOUR_PUBLIC_KEY',
				'is_premium'     => false,
				'has_addons'     => false,
				'has_paid_plans' => true,
				'menu'           => array(
					'slug'        => 'wp-site-guardian',
					'first-path'  => 'admin.php?page=wp-site-guardian',
					'account'     => true,
					'contact'     => false,
					'support'     => false,
				),
			)
		);
	}

	return $wpsg_fs;
}

/**
 * Helper to check if the current site is running the Pro plan.
 *
 * When the Freemius SDK is not installed (development / free install)
 * this always returns false so the plugin degrades gracefully.
 *
 * @return bool
 */
function wpsg_is_pro() {
	$fs = wpsg_fs();

	if ( null === $fs ) {
		// SDK not installed — check a manual option set during testing.
		return (bool) get_option( 'wpsg_pro_override', false );
	}

	return $fs->is__premium_only() || ! $fs->is_free_plan();
}

// Load core files.
require_once WPSG_PLUGIN_DIR . 'includes/class-wpsg-activator.php';
require_once WPSG_PLUGIN_DIR . 'includes/class-wpsg-deactivator.php';
require_once WPSG_PLUGIN_DIR . 'includes/class-wpsg-loader.php';

/**
 * Activation hook callback.
 */
function wpsg_activate() {
	WPSG_Activator::activate();
}

/**
 * Deactivation hook callback.
 */
function wpsg_deactivate() {
	WPSG_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'wpsg_activate' );
register_deactivation_hook( __FILE__, 'wpsg_deactivate' );

/**
 * Begins plugin execution.
 *
 * @return void
 */
function wpsg_run() {
	$plugin = new WPSG_Loader();
	$plugin->run();
}

wpsg_run();
