<?php
/**
 * Admin-only shipping simulator. Runs every method through the same
 * Sfrs_Pricing_Engine::quote() used at checkout. It only reads data: it never creates
 * orders or carts, never touches stock, payments or e-mails, and makes no HTTP requests.
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Sfrs_Simulator {

	/**
	 * Run a simulation.
	 *
	 * @param array $input Unslashed input: lines[], country, state, postcode.
	 * @return array|WP_Error { context, quotes[] }
	 */
	public static function run( array $input ) {
		if ( ! Sfrs_Security::can_manage() ) {
			return new WP_Error( 'sfrs_forbidden', __( 'You do not have permission to use the simulator.', 'smart-flat-rate-shipping-for-woocommerce' ) );
		}
		$lines = isset( $input['lines'] ) && is_array( $input['lines'] ) ? array_values( $input['lines'] ) : array();
		if ( count( $lines ) > 50 ) {
			return new WP_Error( 'sfrs_simulation', __( 'The sample cart can have at most 50 rows.', 'smart-flat-rate-shipping-for-woocommerce' ) );
		}
		$dest = array(
			'country'  => isset( $input['country'] ) ? sanitize_text_field( $input['country'] ) : '',
			'state'    => isset( $input['state'] ) ? sanitize_text_field( $input['state'] ) : '',
			'postcode' => isset( $input['postcode'] ) ? sanitize_text_field( $input['postcode'] ) : '',
		);
		$ctx = Sfrs_Context::from_simulation( $lines, $dest );
		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}
		$ctx = apply_filters( 'sfrs_context', $ctx, 'simulation', $input );
		$quotes = array();
		foreach ( Sfrs_Rules::get_all() as $rule ) {
			$quotes[] = Sfrs_Pricing_Engine::quote( $rule, $ctx );
		}
		return array( 'context' => $ctx, 'quotes' => $quotes );
	}
}
