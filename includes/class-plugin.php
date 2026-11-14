<?php
/**
 * Plugin bootstrap: wires the post type, the WooCommerce shipping method and admin.
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Sfrs_Plugin {

	/**
	 * Boot (called on plugins_loaded).
	 */
	public static function init() {
		if ( ! self::environment_ok() ) {
			add_action( 'admin_notices', array( __CLASS__, 'environment_notice' ) );
			return;
		}

		Sfrs_Activator::maybe_upgrade();
		add_action( 'init', array( 'Sfrs_Rules', 'register_post_type' ) );
		add_action( 'init', array( 'Sfrs_Settings', 'register' ) );
		add_action( 'woocommerce_shipping_init', array( __CLASS__, 'load_shipping_method' ) );
		add_filter( 'woocommerce_shipping_methods', array( __CLASS__, 'register_shipping_method' ) );
		add_filter( 'woocommerce_cart_shipping_method_full_label', array( __CLASS__, 'append_description' ), 10, 2 );

		if ( is_admin() ) {
			Sfrs_Admin::init();
		}
	}

	/**
	 * Is a supported WooCommerce active?
	 *
	 * @return bool
	 */
	public static function environment_ok() {
		return class_exists( 'WooCommerce' ) && defined( 'WC_VERSION' ) && version_compare( WC_VERSION, SFRS_MIN_WC, '>=' );
	}

	/**
	 * Admin notice when WooCommerce is missing or too old.
	 */
	public static function environment_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: minimum WooCommerce version. */
					__( 'Smart Flat Rate Shipping requires WooCommerce %s or newer to be installed and active. It is inactive until then; your saved shipping methods are kept.', 'smart-flat-rate-shipping-for-woocommerce' ),
					SFRS_MIN_WC
				)
			)
		);
	}

	/**
	 * Load the shipping method class once WC_Shipping_Method is available.
	 */
	public static function load_shipping_method() {
		require_once SFRS_PATH . 'includes/class-shipping-method.php';
	}

	/**
	 * Register the method with WooCommerce.
	 *
	 * @param array $methods Methods.
	 * @return array
	 */
	public static function register_shipping_method( $methods ) {
		$methods['sfrs_flat_rate'] = 'Sfrs_Shipping_Method';
		return $methods;
	}

	/**
	 * Show a method's customer-facing description under its label in the classic cart
	 * and checkout. The text is escaped.
	 *
	 * @param string           $label  Label HTML.
	 * @param WC_Shipping_Rate $method Rate.
	 * @return string
	 */
	public static function append_description( $label, $method ) {
		if ( ! $method instanceof WC_Shipping_Rate ) {
			return $label;
		}
		$meta = $method->get_meta_data();
		if ( ! empty( $meta['description'] ) && ! empty( $meta['sfrs_rule_id'] ) ) {
			$label .= '<small class="sfrs-description" style="display:block">' . esc_html( $meta['description'] ) . '</small>';
		}
		return $label;
	}
}
