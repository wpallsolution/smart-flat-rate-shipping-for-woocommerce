<?php
/**
 * Central capability and nonce checks for every state-changing admin action.
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Sfrs_Security {

	/**
	 * Capability required to manage the plugin.
	 *
	 * @return string
	 */
	public static function capability() {
		return 'manage_woocommerce';
	}

	/**
	 * Can the current user manage the plugin?
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( self::capability() );
	}

	/**
	 * Abort the request unless the user is authorised and the nonce is valid.
	 *
	 * @param string $action     Nonce action.
	 * @param string $query_arg  Nonce field name.
	 */
	public static function require_admin( $action, $query_arg = '_wpnonce' ) {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'smart-flat-rate-shipping-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
		if ( ! check_admin_referer( $action, $query_arg ) ) {
			wp_die( esc_html__( 'The security check failed. Please go back and try again.', 'smart-flat-rate-shipping-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
	}
}
