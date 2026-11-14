<?php
/**
 * Administrator diagnostics: environment checks plus an optional, bounded, opt-in log
 * of live evaluations. The log stores no personal data (no postcode, no customer or
 * product names): only country/state, cart totals and per-method results.
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Sfrs_Diagnostics {

	const OPTION    = 'sfrs_diag_log';
	const MAX_ENTRIES = 20;

	/**
	 * Record one live evaluation (only called when logging is enabled).
	 *
	 * @param array $ctx    Context.
	 * @param array $quotes Quotes from the pricing engine.
	 */
	public static function record( array $ctx, array $quotes ) {
		$entry = array(
			'time'     => time(),
			'country'  => $ctx['destination']['country'],
			'state'    => $ctx['destination']['state'],
			'subtotal' => sfrs_round( array_sum( wp_list_pluck( $ctx['items'], 'amount' ) ) ),
			'qty'      => (int) array_sum( wp_list_pluck( $ctx['items'], 'qty' ) ),
			'methods'  => array(),
		);
		foreach ( $quotes as $quote ) {
			$method = array(
				'name'    => $quote['name'],
				'matched' => $quote['matched'],
				'cost'    => $quote['cost'],
				'reason'  => $quote['reason'],
				'failed'  => array(),
			);
			if ( ! empty( $quote['evaluation']['groups'] ) ) {
				foreach ( $quote['evaluation']['groups'] as $gi => $group ) {
					foreach ( $group['conditions'] as $condition ) {
						if ( ! $condition['passed'] ) {
							$method['failed'][] = sprintf( 'G%d: %s (%s)', $gi + 1, $condition['label'], $condition['detail'] );
						}
					}
				}
			}
			$entry['methods'][] = $method;
		}

		$log = self::get_log();
		// Skip identical consecutive evaluations (WooCommerce recalculates often).
		if ( $log ) {
			$last = $log[0];
			unset( $last['time'] );
			$new = $entry;
			unset( $new['time'] );
			if ( $last === $new ) {
				return;
			}
		}
		array_unshift( $log, $entry );
		update_option( self::OPTION, array_slice( $log, 0, self::MAX_ENTRIES ), false );
	}

	/**
	 * Stored entries, newest first.
	 *
	 * @return array
	 */
	public static function get_log() {
		$log = get_option( self::OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Clear the log.
	 */
	public static function clear() {
		delete_option( self::OPTION );
	}

	/**
	 * Zones in which the shipping method has been added.
	 *
	 * @return array[] Each: zone name, enabled.
	 */
	public static function zones_using_method() {
		$found = array();
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return $found;
		}
		$zones   = WC_Shipping_Zones::get_zones();
		$zones[] = array( 'zone_id' => 0 );
		foreach ( $zones as $zone_data ) {
			$zone = WC_Shipping_Zones::get_zone( $zone_data['zone_id'] );
			foreach ( $zone->get_shipping_methods() as $method ) {
				if ( 'sfrs_flat_rate' === $method->id ) {
					$found[] = array( 'zone' => $zone->get_zone_name(), 'enabled' => 'yes' === $method->enabled );
				}
			}
		}
		return $found;
	}

	/**
	 * Environment report rows for the Diagnostics page.
	 *
	 * @return array[] Each: label, ok (bool), detail.
	 */
	public static function environment() {
		$rows   = array();
		$rows[] = array(
			'label'  => __( 'WooCommerce', 'smart-flat-rate-shipping-for-woocommerce' ),
			'ok'     => defined( 'WC_VERSION' ) && version_compare( WC_VERSION, SFRS_MIN_WC, '>=' ),
			'detail' => defined( 'WC_VERSION' ) ? WC_VERSION : __( 'Not active', 'smart-flat-rate-shipping-for-woocommerce' ),
		);
		$rows[] = array(
			'label'  => __( 'Shipping enabled in WooCommerce', 'smart-flat-rate-shipping-for-woocommerce' ),
			'ok'     => function_exists( 'wc_shipping_enabled' ) && wc_shipping_enabled(),
			'detail' => '',
		);
		$zones  = self::zones_using_method();
		$rows[] = array(
			'label'  => __( 'Added to a shipping zone', 'smart-flat-rate-shipping-for-woocommerce' ),
			'ok'     => (bool) $zones,
			'detail' => $zones
				? implode( ', ', array_map( function ( $z ) {
					return $z['zone'] . ( $z['enabled'] ? '' : ' (' . __( 'disabled', 'smart-flat-rate-shipping-for-woocommerce' ) . ')' );
				}, $zones ) )
				: __( 'Not added yet. Add "Smart Flat Rate" to a zone under WooCommerce > Settings > Shipping, otherwise no rate is offered.', 'smart-flat-rate-shipping-for-woocommerce' ),
		);
		$rows[] = array(
			'label'  => __( 'Enabled methods', 'smart-flat-rate-shipping-for-woocommerce' ),
			'ok'     => (bool) Sfrs_Rules::get_active(),
			'detail' => (string) count( Sfrs_Rules::get_active() ),
		);
		return $rows;
	}
}
