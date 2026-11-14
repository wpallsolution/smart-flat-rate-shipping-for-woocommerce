<?php
/**
 * Evaluates rule conditions against a cart context. Pure PHP: no WooCommerce calls,
 * so the very same code serves the live shipping method, the simulator and the tests.
 *
 * Rule model: a rule has OR-ed groups; each group has AND-ed conditions.
 * A rule without any group has no conditions and always matches.
 *
 * Context shape:
 *   items       => list of [ product_id, variation_id, parent_id, qty, amount, categories[] ]
 *   destination => [ country, state, postcode ]
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Sfrs_Condition_Evaluator {

	/**
	 * Condition types with their labels and allowed operators.
	 *
	 * @return array
	 */
	public static function types() {
		$numeric = array(
			'gte' => __( 'is at least', 'smart-flat-rate-shipping-for-woocommerce' ),
			'gt'  => __( 'is more than', 'smart-flat-rate-shipping-for-woocommerce' ),
			'lte' => __( 'is at most', 'smart-flat-rate-shipping-for-woocommerce' ),
			'lt'  => __( 'is less than', 'smart-flat-rate-shipping-for-woocommerce' ),
		);
		$cart    = array(
			'any'  => __( 'contains any of', 'smart-flat-rate-shipping-for-woocommerce' ),
			'none' => __( 'contains none of', 'smart-flat-rate-shipping-for-woocommerce' ),
		);
		$loc     = array(
			'in'     => __( 'is one of', 'smart-flat-rate-shipping-for-woocommerce' ),
			'not_in' => __( 'is not one of', 'smart-flat-rate-shipping-for-woocommerce' ),
		);
		$types = array(
			'cart_subtotal' => array( 'label' => __( 'Cart subtotal', 'smart-flat-rate-shipping-for-woocommerce' ), 'ops' => $numeric ),
			'cart_qty'      => array( 'label' => __( 'Cart quantity', 'smart-flat-rate-shipping-for-woocommerce' ), 'ops' => $numeric ),
			'products'      => array( 'label' => __( 'Products', 'smart-flat-rate-shipping-for-woocommerce' ), 'ops' => $cart ),
			'categories'    => array( 'label' => __( 'Product categories', 'smart-flat-rate-shipping-for-woocommerce' ), 'ops' => $cart ),
			'country'       => array( 'label' => __( 'Destination country', 'smart-flat-rate-shipping-for-woocommerce' ), 'ops' => $loc ),
			'state'         => array( 'label' => __( 'Destination state / region', 'smart-flat-rate-shipping-for-woocommerce' ), 'ops' => $loc ),
			'postcode'      => array( 'label' => __( 'Destination postcode', 'smart-flat-rate-shipping-for-woocommerce' ), 'ops' => $loc ),
		);

		/**
		 * Filters the available condition types (used by add-ons).
		 *
		 * @param array $types type => [ label, ops ].
		 */
		return apply_filters( 'sfrs_condition_types', $types );
	}

	/**
	 * Validate a single postcode pattern.
	 * Supported: exact (380001), trailing wildcard (380*), numeric range (380001...380060).
	 *
	 * @param string $pattern Normalised pattern.
	 * @return bool
	 */
	public static function is_valid_postcode_pattern( $pattern ) {
		if ( preg_match( '/^(\d+)\.\.\.(\d+)$/', $pattern, $m ) ) {
			return (float) $m[1] <= (float) $m[2];
		}
		return (bool) preg_match( '/^[A-Z0-9]+\*?$/', $pattern );
	}

	/**
	 * Does a postcode match any of the patterns?
	 *
	 * @param string   $postcode Destination postcode (un-normalised).
	 * @param string[] $patterns Patterns.
	 * @return bool
	 */
	public static function postcode_matches( $postcode, array $patterns ) {
		$pc = sfrs_normalize_postcode( $postcode );
		if ( '' === $pc ) {
			return false;
		}
		foreach ( $patterns as $pattern ) {
			$pattern = sfrs_normalize_postcode( $pattern );
			if ( preg_match( '/^(\d+)\.\.\.(\d+)$/', $pattern, $m ) ) {
				if ( ctype_digit( $pc ) && (float) $pc >= (float) $m[1] && (float) $pc <= (float) $m[2] ) {
					return true;
				}
			} elseif ( '*' === substr( $pattern, -1 ) ) {
				$prefix = substr( $pattern, 0, -1 );
				if ( '' !== $prefix && 0 === strpos( $pc, $prefix ) ) {
					return true;
				}
			} elseif ( $pattern === $pc ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Evaluate a whole rule.
	 *
	 * @param array $rule Normalised rule.
	 * @param array $ctx  Cart context.
	 * @return array { matched, matched_group, groups[], eligible_items[], reason }
	 */
	public static function evaluate_rule( array $rule, array $ctx ) {
		$items  = isset( $ctx['items'] ) ? $ctx['items'] : array();
		$groups = isset( $rule['groups'] ) && is_array( $rule['groups'] ) ? array_values( $rule['groups'] ) : array();
		$out    = array(
			'matched'        => false,
			'matched_group'  => null,
			'groups'         => array(),
			'eligible_items' => $items,
			'reason'         => '',
		);

		if ( ! $groups ) {
			$out['matched'] = true;
			$out['reason']  = __( 'The method has no conditions, so it always matches.', 'smart-flat-rate-shipping-for-woocommerce' );
			return $out;
		}

		foreach ( $groups as $index => $group ) {
			$result = array( 'passed' => true, 'conditions' => array() );
			if ( ! is_array( $group ) || ! $group ) {
				// A malformed group never matches; it is not silently reinterpreted.
				$result['passed']       = false;
				$result['conditions'][] = array(
					'passed' => false,
					'label'  => __( 'Group', 'smart-flat-rate-shipping-for-woocommerce' ),
					'detail' => __( 'This condition group is empty or malformed.', 'smart-flat-rate-shipping-for-woocommerce' ),
				);
			} else {
				foreach ( $group as $condition ) {
					$check                  = self::evaluate_condition( is_array( $condition ) ? $condition : array(), $ctx );
					$result['conditions'][] = $check;
					if ( ! $check['passed'] ) {
						$result['passed'] = false;
					}
				}
			}
			$out['groups'][] = $result;

			if ( $result['passed'] && null === $out['matched_group'] ) {
				$out['matched']        = true;
				$out['matched_group']  = $index;
				$out['eligible_items'] = self::eligible_items( $group, $items );
			}
		}

		$out['reason'] = $out['matched']
			? sprintf(
				/* translators: %d: condition group number. */
				__( 'Condition group %d matched.', 'smart-flat-rate-shipping-for-woocommerce' ),
				(int) $out['matched_group'] + 1
			)
			: __( 'No condition group matched.', 'smart-flat-rate-shipping-for-woocommerce' );

		return $out;
	}

	/**
	 * Items that count as "eligible" for a matched group: those selected by its
	 * "contains any of" product/category conditions (union). If the group has none of
	 * those conditions, every cart item is eligible.
	 *
	 * @param array $group Conditions.
	 * @param array $items Cart items.
	 * @return array
	 */
	public static function eligible_items( array $group, array $items ) {
		$selectors = array();
		foreach ( $group as $condition ) {
			if ( isset( $condition['type'], $condition['op'] ) && in_array( $condition['type'], array( 'products', 'categories' ), true ) && 'any' === $condition['op'] ) {
				$selectors[] = $condition;
			}
		}
		if ( ! $selectors ) {
			return $items;
		}
		$eligible = array();
		foreach ( $items as $key => $item ) {
			foreach ( $selectors as $selector ) {
				if ( self::item_matches( $item, $selector ) ) {
					$eligible[ $key ] = $item;
					break;
				}
			}
		}
		return $eligible;
	}

	/**
	 * Does one cart item match a products/categories condition?
	 *
	 * @param array $item      Cart item.
	 * @param array $condition Condition.
	 * @return bool
	 */
	public static function item_matches( array $item, array $condition ) {
		$ids = isset( $condition['value'] ) && is_array( $condition['value'] ) ? array_map( 'intval', $condition['value'] ) : array();
		if ( 'products' === $condition['type'] ) {
			return in_array( (int) $item['product_id'], $ids, true )
				|| in_array( (int) $item['variation_id'], $ids, true )
				|| in_array( (int) $item['parent_id'], $ids, true );
		}
		return (bool) array_intersect( isset( $item['categories'] ) ? array_map( 'intval', $item['categories'] ) : array(), $ids );
	}

	/**
	 * Evaluate one condition.
	 *
	 * @param array $c   Condition.
	 * @param array $ctx Context.
	 * @return array { passed, label, detail }
	 */
	public static function evaluate_condition( array $c, array $ctx ) {
		$types = self::types();
		$type  = isset( $c['type'] ) ? $c['type'] : '';
		$op    = isset( $c['op'] ) ? $c['op'] : '';
		if ( ! isset( $types[ $type ] ) || ! isset( $types[ $type ]['ops'][ $op ] ) ) {
			return array(
				'passed' => false,
				'label'  => __( 'Condition', 'smart-flat-rate-shipping-for-woocommerce' ),
				'detail' => __( 'Unknown condition type or operator.', 'smart-flat-rate-shipping-for-woocommerce' ),
			);
		}
		$label = self::describe( $c );
		$items = isset( $ctx['items'] ) ? $ctx['items'] : array();
		$dest  = isset( $ctx['destination'] ) ? $ctx['destination'] : array();

		switch ( $type ) {
			case 'cart_subtotal':
				$actual = sfrs_round( array_sum( wp_list_pluck( $items, 'amount' ) ) );
				$target = sfrs_round( $c['value'] );
				return array(
					'passed' => self::compare( $actual, $op, $target ),
					'label'  => $label,
					'detail' => sprintf(
						/* translators: %s: amount. */
						__( 'Cart subtotal is %s.', 'smart-flat-rate-shipping-for-woocommerce' ),
						sfrs_money_text( $actual )
					),
				);

			case 'cart_qty':
				$actual = (int) array_sum( wp_list_pluck( $items, 'qty' ) );
				return array(
					'passed' => self::compare( $actual, $op, (int) $c['value'] ),
					'label'  => $label,
					/* translators: %d: quantity. */
					'detail' => sprintf( __( 'Cart quantity is %d.', 'smart-flat-rate-shipping-for-woocommerce' ), $actual ),
				);

			case 'products':
			case 'categories':
				$qty = 0;
				foreach ( $items as $item ) {
					if ( self::item_matches( $item, $c ) ) {
						$qty += (int) $item['qty'];
					}
				}
				$min = isset( $c['min_qty'] ) ? max( 1, (int) $c['min_qty'] ) : 1;
				if ( 'any' === $op ) {
					$passed = $qty >= $min;
					/* translators: 1: matching quantity, 2: required minimum quantity. */
					$detail = sprintf( __( 'Cart has %1$d matching item(s); at least %2$d required.', 'smart-flat-rate-shipping-for-woocommerce' ), $qty, $min );
				} else {
					$passed = 0 === $qty;
					/* translators: %d: matching quantity. */
					$detail = sprintf( __( 'Cart has %d matching item(s).', 'smart-flat-rate-shipping-for-woocommerce' ), $qty );
				}
				return array( 'passed' => $passed, 'label' => $label, 'detail' => $detail );

			case 'country':
			case 'state':
			case 'postcode':
				return self::evaluate_location( $c, $label, $dest );
		}

		/**
		 * Lets an add-on evaluate its own condition types.
		 *
		 * @param array|null $result { passed, detail } or null.
		 * @param array      $c      Condition.
		 * @param array      $ctx    Cart context.
		 */
		$custom = apply_filters( 'sfrs_evaluate_condition', null, $c, $ctx );
		if ( is_array( $custom ) && isset( $custom['passed'] ) ) {
			return array(
				'passed' => (bool) $custom['passed'],
				'label'  => $label,
				'detail' => isset( $custom['detail'] ) ? (string) $custom['detail'] : '',
			);
		}
		return array( 'passed' => false, 'label' => $label, 'detail' => '' );
	}

	/**
	 * Location conditions. An empty destination value never satisfies a location
	 * condition (neither "is one of" nor "is not one of"), so a rate is never shown
	 * to a customer whose address is not yet known.
	 *
	 * @param array  $c     Condition.
	 * @param string $label Label.
	 * @param array  $dest  Destination.
	 * @return array
	 */
	protected static function evaluate_location( array $c, $label, array $dest ) {
		$country  = isset( $dest['country'] ) ? strtoupper( (string) $dest['country'] ) : '';
		$state    = isset( $dest['state'] ) ? strtoupper( (string) $dest['state'] ) : '';
		$postcode = isset( $dest['postcode'] ) ? (string) $dest['postcode'] : '';
		$values   = isset( $c['value'] ) && is_array( $c['value'] ) ? $c['value'] : array();

		if ( 'country' === $c['type'] ) {
			$known = '' !== $country;
			$hit   = $known && in_array( $country, array_map( 'strtoupper', $values ), true );
			$shown = $country;
		} elseif ( 'state' === $c['type'] ) {
			$known = '' !== $country && '' !== $state;
			$hit   = $known && in_array( $country . ':' . $state, array_map( 'strtoupper', $values ), true );
			$shown = $country . ':' . $state;
		} else {
			$known = '' !== sfrs_normalize_postcode( $postcode );
			$hit   = $known && self::postcode_matches( $postcode, $values );
			$shown = sfrs_normalize_postcode( $postcode );
		}

		if ( ! $known ) {
			return array(
				'passed' => false,
				'label'  => $label,
				'detail' => __( 'The destination value is not provided yet, so the condition cannot be satisfied.', 'smart-flat-rate-shipping-for-woocommerce' ),
			);
		}
		return array(
			'passed' => 'in' === $c['op'] ? $hit : ! $hit,
			'label'  => $label,
			/* translators: %s: destination value. */
			'detail' => sprintf( __( 'Destination is %s.', 'smart-flat-rate-shipping-for-woocommerce' ), $shown ),
		);
	}

	/**
	 * Compare two numbers.
	 *
	 * @param float|int $actual Actual value.
	 * @param string    $op     gte|gt|lte|lt.
	 * @param float|int $target Target value.
	 * @return bool
	 */
	protected static function compare( $actual, $op, $target ) {
		switch ( $op ) {
			case 'gte':
				return $actual >= $target;
			case 'gt':
				return $actual > $target;
			case 'lte':
				return $actual <= $target;
			case 'lt':
				return $actual < $target;
		}
		return false;
	}

	/**
	 * Human readable one-line description of a condition.
	 *
	 * @param array $c Condition.
	 * @return string
	 */
	public static function describe( array $c ) {
		$types = self::types();
		if ( ! isset( $c['type'], $types[ $c['type'] ] ) ) {
			return __( 'Unknown condition', 'smart-flat-rate-shipping-for-woocommerce' );
		}
		$custom = apply_filters( 'sfrs_describe_condition', null, $c );
		if ( is_string( $custom ) ) {
			return $custom;
		}
		$op    = isset( $c['op'], $types[ $c['type'] ]['ops'][ $c['op'] ] ) ? $types[ $c['type'] ]['ops'][ $c['op'] ] : '?';
		$value = isset( $c['value'] ) ? $c['value'] : '';
		if ( 'cart_subtotal' === $c['type'] ) {
			$value = sfrs_money_text( (float) $value );
		} elseif ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}
		$text = $types[ $c['type'] ]['label'] . ' ' . $op . ' ' . $value;
		if ( in_array( $c['type'], array( 'products', 'categories' ), true ) && isset( $c['op'] ) && 'any' === $c['op'] && isset( $c['min_qty'] ) && (int) $c['min_qty'] > 1 ) {
			/* translators: %d: minimum quantity. */
			$text .= ' ' . sprintf( __( '(quantity at least %d)', 'smart-flat-rate-shipping-for-woocommerce' ), (int) $c['min_qty'] );
		}
		return $text;
	}
}
