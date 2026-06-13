<?php
/**
 * License / Freemius helper.
 *
 * @package WP_Canary
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
		return wpc_fs()->get_account_url();
	}

	/**
	 * Return the upgrade URL.
	 *
	 * @return string
	 */
	public static function get_upgrade_url() {
		return wpc_fs()->get_upgrade_url();
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
