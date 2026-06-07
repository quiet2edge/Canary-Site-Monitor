<?php
/**
 * License / Freemius helper.
 *
 * @package WP_Site_Guardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPC_License
 *
 * Provides helper methods around the Freemius license state.
 */
class WPC_License {

	/**
	 * Return the Freemius account portal URL.
	 *
	 * When Freemius is not installed this returns a generic upgrade URL.
	 *
	 * @return string
	 */
	public static function get_account_url() {
		$fs = wpc_fs();
		if ( null !== $fs && method_exists( $fs, 'get_account_url' ) ) {
			return $fs->get_account_url();
		}
		return 'https://wpsiteguardian.com/account/';
	}

	/**
	 * Return the upgrade URL.
	 *
	 * @return string
	 */
	public static function get_upgrade_url() {
		$fs = wpc_fs();
		if ( null !== $fs && method_exists( $fs, 'get_upgrade_url' ) ) {
			return $fs->get_upgrade_url();
		}
		return 'https://wpsiteguardian.com/pricing/';
	}

	/**
	 * Return the plan label for display.
	 *
	 * @return string
	 */
	public static function get_plan_label() {
		return wpc_is_pro()
			? __( 'Pro', 'wp-canary' )
			: __( 'Free', 'wp-canary' );
	}
}
