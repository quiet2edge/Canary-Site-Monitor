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
	 * Whether the site has connected/opted in to Freemius.
	 *
	 * The Account page is only registered for sites that are
	 * connected, so we must not link to it otherwise.
	 *
	 * @return bool
	 */
	public static function is_connected() {
		return wpc_fs()->is_registered();
	}

	/**
	 * Return the URL to connect/opt in to Freemius.
	 *
	 * @return string
	 */
	public static function get_connect_url() {
		return wpc_fs()->get_reconnect_url();
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
