<?php
/**
 * Plugin Name:       WP Canary
 * Plugin URI:        https://wpsiteguardian.com
 * Description:       Proactive WordPress diagnostic plugin that detects, explains, and fixes site-breaking issues before they cause downtime.
 * Version:           1.1.0
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
 * @package WP_Canary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'wpc_fs' ) ) {
	// Premium version being activated while free version is active —
	// hand off to Freemius so it can auto-deactivate the free version.
	wpc_fs()->set_basename( true, __FILE__ );
} else {
	/**
	 * DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE
	 * `function_exists` CALL ABOVE TO PROPERLY WORK.
	 */
	if ( ! function_exists( 'wpc_fs' ) ) {

		// Plugin constants.
		define( 'WPC_VERSION', '1.1.0' );
		define( 'WPC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		define( 'WPC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		define( 'WPC_PLUGIN_FILE', __FILE__ );

		/**
		 * Freemius SDK bootstrap.
		 *
		 * @return Freemius
		 */
		function wpc_fs() {
			global $wpc_fs;

			if ( ! isset( $wpc_fs ) ) {
				// Activate multisite network integration.
				if ( ! defined( 'WP_FS__PRODUCT_31489_MULTISITE' ) ) {
					define( 'WP_FS__PRODUCT_31489_MULTISITE', true );
				}

				// Include Freemius SDK.
				require_once dirname( __FILE__ ) . '/freemius/start.php';

				$wpc_fs = fs_dynamic_init( array(
					'id'                  => '31489',
					'slug'                => 'wp-canary---because-you-shouldnt-wake-up-to-a-broken-site',
					'type'                => 'plugin',
					'public_key'          => 'pk_e3610dadf66440da415f8363dffac',
					'is_premium'          => true,
					'premium_suffix'      => 'Professional - WP Canary',
					'has_premium_version' => true,
					'has_addons'          => false,
					'has_paid_plans'      => true,
					'is_org_compliant'    => true,
					// Automatically removed in the free version. If you're not using the
					// auto-generated free version, delete this line before uploading to wp.org.
					'wp_org_gatekeeper'   => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
					'trial'               => array(
						'days'               => 3,
						'is_require_payment' => true,
					),
					'menu'                => array(
						'slug'       => 'wp-canary',
						'first-path' => 'admin.php?page=wpc-getting-started',
						'support'    => false,
						'network'    => is_multisite(),
					),
				) );
			}

			return $wpc_fs;
		}

		// Init Freemius.
		wpc_fs();
		// Signal that SDK was initiated.
		do_action( 'wpc_fs_loaded' );
	}

	/**
	 * Helper to check if the current site is running the Pro plan.
	 *
	 * @return bool
	 */
	function wpc_is_pro() {
		$fs = wpc_fs();
		return $fs->is__premium_only() || ! $fs->is_free_plan();
	}

	// Load core files.
	require_once WPC_PLUGIN_DIR . 'includes/class-wpc-activator.php';
	require_once WPC_PLUGIN_DIR . 'includes/class-wpc-deactivator.php';
	require_once WPC_PLUGIN_DIR . 'includes/class-wpc-uninstaller.php';
	require_once WPC_PLUGIN_DIR . 'includes/class-wpc-loader.php';

	// Uninstall cleanup, handled via Freemius.
	wpc_fs()->add_action( 'after_uninstall', array( 'WPC_Uninstaller', 'uninstall' ) );

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
}
