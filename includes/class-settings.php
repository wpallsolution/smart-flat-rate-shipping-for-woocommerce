<?php
/**
 * Global plugin settings (WordPress Settings API). Only options with real behaviour.
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Sfrs_Settings {

	const OPTION = 'sfrs_settings';
	const GROUP  = 'sfrs_settings_group';

	/**
	 * Request-level cache.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'subtotal_discounts'  => 'after', // after|before: deduct coupon discounts from subtotals.
			'diagnostics_log'     => 0,       // Record the last evaluations of the live method.
			'delete_on_uninstall' => 0,       // Remove methods and settings when the plugin is deleted.
		);
	}

	/**
	 * Read a setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		}
		return isset( self::$cache[ $key ] ) ? self::$cache[ $key ] : null;
	}

	/**
	 * Forget the request-level cache (the option changed).
	 */
	public static function reset_cache() {
		self::$cache = null;
	}

	/**
	 * Register with the Settings API.
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'reset_cache' ), 5 );
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'reset_cache' ), 5 );
		add_action( 'update_option_' . self::OPTION, array( 'Sfrs_Rules', 'invalidate_shipping_cache' ) );
		add_filter( 'option_page_capability_' . self::GROUP, array( 'Sfrs_Security', 'capability' ) );
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'subtotal_discounts'  => isset( $input['subtotal_discounts'] ) && 'before' === $input['subtotal_discounts'] ? 'before' : 'after',
			'diagnostics_log'     => empty( $input['diagnostics_log'] ) ? 0 : 1,
			'delete_on_uninstall' => empty( $input['delete_on_uninstall'] ) ? 0 : 1,
		);
	}
}
