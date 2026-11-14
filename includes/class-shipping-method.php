<?php
/**
 * The WooCommerce shipping method. Add "Smart Flat Rate" to a shipping zone; it
 * returns one rate per matching method configured under WooCommerce > Smart Flat Rate.
 *
 * Loaded on the woocommerce_shipping_init action (WC_Shipping_Method must exist).
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Sfrs_Shipping_Method extends WC_Shipping_Method {

	/**
	 * Package hash => instance ID that already produced rates in this request. Guards
	 * against duplicate options when the method is added twice to one zone.
	 *
	 * @var array
	 */
	protected static $served = array();

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Zone instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'sfrs_flat_rate';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Smart Flat Rate', 'smart-flat-rate-shipping-for-woocommerce' );
		$this->method_description = __( 'Offers the shipping methods configured under WooCommerce > Smart Flat Rate. Add this method to a shipping zone once.', 'smart-flat-rate-shipping-for-woocommerce' );
		$this->supports           = array( 'shipping-zones', 'instance-settings' );
		$this->init();
	}

	/**
	 * Settings.
	 */
	public function init() {
		$this->instance_form_fields = array(
			'title' => array(
				'title'       => __( 'Title in this zone', 'smart-flat-rate-shipping-for-woocommerce' ),
				'type'        => 'text',
				'default'     => __( 'Smart Flat Rate', 'smart-flat-rate-shipping-for-woocommerce' ),
				'description' => __( 'Only shown in the shipping zone list. Customers see the label of each method instead.', 'smart-flat-rate-shipping-for-woocommerce' ),
				'desc_tip'    => true,
			),
		);
		$this->init_settings();
		$this->title = $this->get_option( 'title', __( 'Smart Flat Rate', 'smart-flat-rate-shipping-for-woocommerce' ) );
	}

	/**
	 * Calculate shipping for one package. Called by WooCommerce for each package
	 * whose destination matches this method's zone.
	 *
	 * @param array $package Package.
	 */
	public function calculate_shipping( $package = array() ) {
		$ctx = Sfrs_Context::from_package( $package );
		if ( empty( $ctx['items'] ) ) {
			return;
		}

		$hash = md5( wp_json_encode( array( array_keys( $ctx['items'] ), $ctx['destination'] ) ) );
		if ( isset( self::$served[ $hash ] ) && self::$served[ $hash ] !== $this->get_instance_id() ) {
			return; // Another instance of this method already served this package.
		}
		self::$served[ $hash ] = $this->get_instance_id();

		$quotes = array();
		foreach ( Sfrs_Rules::get_active() as $rule ) {
			$quote    = Sfrs_Pricing_Engine::quote( $rule, $ctx );
			$quotes[] = $quote;
			if ( ! $quote['matched'] ) {
				continue;
			}
			$args = array(
				'id'        => $this->get_rate_id( (string) $rule['id'] ),
				'label'     => $rule['label'],
				'cost'      => $quote['cost'],
				'package'   => $package,
				'meta_data' => array( 'sfrs_rule_id' => $rule['id'] ),
			);
			if ( '' !== $rule['description'] ) {
				$args['meta_data']['description'] = $rule['description'];
			}
			if ( 'none' === $rule['tax_status'] ) {
				$args['taxes'] = false;
			}
			$this->add_rate( $args );

			// WooCommerce 9.2+ keeps a description on the rate itself, and the Checkout Block shows that one.
			// The classic checkout keeps using the meta data above (see Sfrs_Plugin::append_description).
			$rate_id = $args['id'];
			if ( '' !== $rule['description'] && isset( $this->rates[ $rate_id ] ) && is_callable( array( $this->rates[ $rate_id ], 'set_description' ) ) ) {
				$this->rates[ $rate_id ]->set_description( $rule['description'] );
			}
		}

		if ( Sfrs_Settings::get( 'diagnostics_log' ) ) {
			Sfrs_Diagnostics::record( $ctx, $quotes );
		}
	}
}
