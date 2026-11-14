<?php
/**
 * Prices a rule for a cart context. This is the single entry point (quote) used by
 * both the live WooCommerce shipping method and the admin simulator.
 *
 * Pricing semantics:
 *   cost = base fee + per-item fee x chargeable quantity
 *   chargeable quantity = total cart quantity, or eligible quantity (rule setting)
 *   if free threshold is set and eligible subtotal >= threshold, cost = 0
 * Eligible items/subtotal: see Sfrs_Condition_Evaluator::eligible_items().
 * Subtotals exclude tax; whether coupon discounts are deducted is a global setting.
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Sfrs_Pricing_Engine {

	/**
	 * Evaluate and price one rule.
	 *
	 * @param array $rule Normalised rule (see Sfrs_Rules::normalize()).
	 * @param array $ctx  Cart context.
	 * @return array
	 */
	public static function quote( array $rule, array $ctx ) {
		$quote = array(
			'rule_id'    => isset( $rule['id'] ) ? (int) $rule['id'] : 0,
			'name'       => isset( $rule['name'] ) ? $rule['name'] : '',
			'label'      => isset( $rule['label'] ) ? $rule['label'] : '',
			'evaluated'  => false,
			'matched'    => false,
			'cost'       => null,
			'is_free'    => false,
			'evaluation' => null,
			'breakdown'  => array(),
			'reason'     => '',
		);

		if ( empty( $rule['enabled'] ) ) {
			$quote['reason'] = __( 'The method is disabled, so it was not evaluated.', 'smart-flat-rate-shipping-for-woocommerce' );
			return $quote;
		}
		if ( empty( $ctx['items'] ) ) {
			$quote['reason'] = __( 'The cart has no shippable items, so the method was not evaluated.', 'smart-flat-rate-shipping-for-woocommerce' );
			return $quote;
		}

		$evaluation          = Sfrs_Condition_Evaluator::evaluate_rule( $rule, $ctx );
		$quote['evaluated']  = true;
		$quote['evaluation'] = $evaluation;
		$quote['matched']    = $evaluation['matched'];
		$quote['reason']     = $evaluation['reason'];

		if ( $evaluation['matched'] ) {
			$price             = self::price( $rule, $evaluation['eligible_items'], $ctx );
			$quote['cost']     = $price['cost'];
			$quote['is_free']  = $price['is_free'];
			$quote['breakdown'] = $price['breakdown'];
		}
		return $quote;
	}

	/**
	 * Calculate the charge for a matched rule.
	 *
	 * @param array $rule     Rule.
	 * @param array $eligible Eligible items.
	 * @param array $ctx      Context.
	 * @return array { cost, is_free, breakdown[] }
	 */
	public static function price( array $rule, array $eligible, array $ctx ) {
		$cart_qty = (int) array_sum( wp_list_pluck( $ctx['items'], 'qty' ) );
		$elig_qty = (int) array_sum( wp_list_pluck( $eligible, 'qty' ) );
		$elig_sub = sfrs_round( array_sum( wp_list_pluck( $eligible, 'amount' ) ) );

		$base      = max( 0.0, (float) $rule['base_fee'] );
		$per_item  = max( 0.0, (float) $rule['per_item_fee'] );
		$scope     = 'eligible' === $rule['qty_scope'] ? 'eligible' : 'cart';
		$charge_qty = 'eligible' === $scope ? $elig_qty : $cart_qty;

		$cost      = sfrs_round( $base + $per_item * $charge_qty );
		$breakdown = array(
			/* translators: %s: amount. */
			sprintf( __( 'Base fee: %s', 'smart-flat-rate-shipping-for-woocommerce' ), sfrs_money_text( $base ) ),
		);
		if ( $per_item > 0 ) {
			$breakdown[] = sprintf(
				/* translators: 1: quantity, 2: per-item fee, 3: line total, 4: quantity scope label. */
				__( 'Per-item fee: %1$d x %2$s = %3$s (%4$s quantity)', 'smart-flat-rate-shipping-for-woocommerce' ),
				$charge_qty,
				sfrs_money_text( $per_item ),
				sfrs_money_text( $per_item * $charge_qty ),
				'eligible' === $scope ? __( 'eligible product', 'smart-flat-rate-shipping-for-woocommerce' ) : __( 'total cart', 'smart-flat-rate-shipping-for-woocommerce' )
			);
		}

		/**
		 * Lets an add-on adjust the cost before the free-shipping threshold is applied.
		 *
		 * @param array $adjust   { cost, breakdown[] }.
		 * @param array $rule     Rule.
		 * @param array $eligible Eligible items.
		 * @param array $ctx      Context.
		 * @param int   $qty      Chargeable quantity.
		 */
		$adjust    = apply_filters( 'sfrs_pricing_adjust', array( 'cost' => $cost, 'breakdown' => array() ), $rule, $eligible, $ctx, $charge_qty );
		$cost      = sfrs_round( max( 0.0, (float) $adjust['cost'] ) );
		$breakdown = array_merge( $breakdown, (array) $adjust['breakdown'] );

		$is_free   = false;
		$threshold = isset( $rule['free_threshold'] ) ? $rule['free_threshold'] : '';
		if ( '' !== $threshold && (float) $threshold > 0 ) {
			$target = sfrs_round( $threshold );
			$reached = $elig_sub >= $target;
			$breakdown[] = sprintf(
				/* translators: 1: eligible subtotal, 2: free shipping threshold, 3: result text. */
				__( 'Eligible subtotal %1$s vs free-shipping threshold %2$s: %3$s', 'smart-flat-rate-shipping-for-woocommerce' ),
				sfrs_money_text( $elig_sub ),
				sfrs_money_text( $target ),
				$reached ? __( 'reached, shipping is free', 'smart-flat-rate-shipping-for-woocommerce' ) : __( 'not reached', 'smart-flat-rate-shipping-for-woocommerce' )
			);
			if ( $reached ) {
				$cost    = 0.0;
				$is_free = true;
			}
		}

		$breakdown[] = sprintf(
			/* translators: %s: amount. */
			__( 'Final shipping cost: %s', 'smart-flat-rate-shipping-for-woocommerce' ),
			sfrs_money_text( $cost )
		);

		return array( 'cost' => (float) $cost, 'is_free' => $is_free, 'breakdown' => $breakdown );
	}
}
