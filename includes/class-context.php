<?php
/**
 * Builds the plain cart context consumed by the rule engine, either from a real
 * WooCommerce shipping package or from simulator input.
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Sfrs_Context {

	/**
	 * Category cache for the request: product id => category ids (with ancestors).
	 *
	 * @var array
	 */
	protected static $category_cache = array();

	/**
	 * Category IDs of a (parent) product, including all ancestor categories so that a
	 * rule on "Clothing" also matches products in "Clothing > Shirts".
	 *
	 * @param int $product_id Parent product ID.
	 * @return int[]
	 */
	public static function categories_for( $product_id ) {
		$product_id = (int) $product_id;
		if ( isset( self::$category_cache[ $product_id ] ) ) {
			return self::$category_cache[ $product_id ];
		}
		$ids   = array();
		$terms = get_the_terms( $product_id, 'product_cat' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$ids[] = (int) $term->term_id;
				foreach ( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) as $ancestor ) {
					$ids[] = (int) $ancestor;
				}
			}
		}
		self::$category_cache[ $product_id ] = array_values( array_unique( $ids ) );
		return self::$category_cache[ $product_id ];
	}

	/**
	 * Build a context from a WooCommerce shipping package.
	 *
	 * @param array $package Package passed to calculate_shipping().
	 * @return array
	 */
	public static function from_package( array $package ) {
		$before = 'before' === Sfrs_Settings::get( 'subtotal_discounts' );
		$items  = array();
		$contents = isset( $package['contents'] ) && is_array( $package['contents'] ) ? $package['contents'] : array();
		foreach ( $contents as $key => $values ) {
			if ( empty( $values['data'] ) || ! is_object( $values['data'] ) ) {
				continue;
			}
			$product_id   = (int) $values['product_id'];
			$variation_id = isset( $values['variation_id'] ) ? (int) $values['variation_id'] : 0;
			$line_total   = isset( $values['line_total'] ) ? (float) $values['line_total'] : 0.0;
			$line_sub     = isset( $values['line_subtotal'] ) ? (float) $values['line_subtotal'] : $line_total;
			$items[ $key ] = array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'parent_id'    => $product_id,
				'qty'          => max( 0, (int) $values['quantity'] ),
				'amount'       => $before ? $line_sub : $line_total,
				'categories'   => self::categories_for( $product_id ),
			);
		}
		$dest = isset( $package['destination'] ) && is_array( $package['destination'] ) ? $package['destination'] : array();
		$ctx = array(
			'items'       => $items,
			'destination' => array(
				'country'  => isset( $dest['country'] ) ? (string) $dest['country'] : '',
				'state'    => isset( $dest['state'] ) ? (string) $dest['state'] : '',
				'postcode' => isset( $dest['postcode'] ) ? (string) $dest['postcode'] : '',
			),
		);

		/**
		 * Lets an add-on enrich the cart context (weights, coupons, user, time...).
		 *
		 * @param array  $ctx    Context.
		 * @param string $source 'package' or 'simulation'.
		 * @param array  $input  The shipping package.
		 */
		return apply_filters( 'sfrs_context', $ctx, 'package', $package );
	}

	/**
	 * Build a context from simulator input. Every product ID is validated.
	 *
	 * @param array $lines Each: id, qty, price (optional unit price, '' = current price).
	 * @param array $dest  country, state, postcode.
	 * @return array|WP_Error
	 */
	public static function from_simulation( array $lines, array $dest ) {
		$items  = array();
		$errors = array();
		foreach ( $lines as $n => $line ) {
			$id  = isset( $line['id'] ) ? absint( $line['id'] ) : 0;
			$qty = isset( $line['qty'] ) ? absint( $line['qty'] ) : 0;
			if ( ! $id && 0 === $qty && ( ! isset( $line['price'] ) || '' === $line['price'] ) ) {
				continue; // Entirely blank row.
			}
			if ( ! $id || ! function_exists( 'wc_get_product' ) ) {
				/* translators: %d: row number. */
				$errors[] = sprintf( __( 'Row %d: choose a product.', 'smart-flat-rate-shipping-for-woocommerce' ), $n + 1 );
				continue;
			}
			$product = wc_get_product( $id );
			if ( ! $product ) {
				/* translators: %d: row number. */
				$errors[] = sprintf( __( 'Row %d: the selected product does not exist.', 'smart-flat-rate-shipping-for-woocommerce' ), $n + 1 );
				continue;
			}
			if ( $qty < 1 || $qty > 100000 ) {
				/* translators: %d: row number. */
				$errors[] = sprintf( __( 'Row %d: quantity must be between 1 and 100000.', 'smart-flat-rate-shipping-for-woocommerce' ), $n + 1 );
				continue;
			}
			$unit = isset( $line['price'] ) ? trim( (string) $line['price'] ) : '';
			if ( '' === $unit ) {
				$unit_price = (float) $product->get_price();
			} else {
				$unit_price = sfrs_parse_amount( $unit );
				if ( null === $unit_price || $unit_price < 0 ) {
					/* translators: %d: row number. */
					$errors[] = sprintf( __( 'Row %d: the price must be a number of zero or more.', 'smart-flat-rate-shipping-for-woocommerce' ), $n + 1 );
					continue;
				}
			}
			$is_variation = $product->is_type( 'variation' );
			$parent_id    = $is_variation ? (int) $product->get_parent_id() : (int) $product->get_id();
			$items[]      = array(
				'product_id'   => $parent_id,
				'variation_id' => $is_variation ? (int) $product->get_id() : 0,
				'parent_id'    => $parent_id,
				'qty'          => $qty,
				'amount'       => $unit_price * $qty,
				'categories'   => self::categories_for( $parent_id ),
			);
		}
		if ( ! $items && ! $errors ) {
			$errors[] = __( 'Add at least one product to the sample cart.', 'smart-flat-rate-shipping-for-woocommerce' );
		}
		if ( $errors ) {
			return new WP_Error( 'sfrs_simulation', implode( "\n", $errors ), $errors );
		}
		return array(
			'items'       => $items,
			'destination' => array(
				'country'  => strtoupper( isset( $dest['country'] ) ? (string) $dest['country'] : '' ),
				'state'    => strtoupper( isset( $dest['state'] ) ? (string) $dest['state'] : '' ),
				'postcode' => isset( $dest['postcode'] ) ? (string) $dest['postcode'] : '',
			),
		);
	}
}
