<?php
/**
 * Plugin Name:       Smart Flat Rate Shipping for WooCommerce
 * Description:       Flat-rate shipping methods with fixed and per-item fees, free-shipping thresholds, product, category and location conditions, a simulator and diagnostics.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            hplugin
 * Author URI:        https://profiles.wordpress.org/hplugin/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       smart-flat-rate-shipping-for-woocommerce
 * WC requires at least: 8.0
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'ABSPATH' ) || exit;

define( 'SFRS_VERSION', '1.0.1' );
define( 'SFRS_MIN_WC', '8.0' );
define( 'SFRS_FILE', __FILE__ );
define( 'SFRS_PATH', plugin_dir_path( __FILE__ ) );
define( 'SFRS_URL', plugin_dir_url( __FILE__ ) );

require_once SFRS_PATH . 'includes/helpers.php';
require_once SFRS_PATH . 'includes/class-security.php';
require_once SFRS_PATH . 'includes/class-settings.php';
require_once SFRS_PATH . 'includes/class-condition-evaluator.php';
require_once SFRS_PATH . 'includes/class-pricing-engine.php';
require_once SFRS_PATH . 'includes/class-context.php';
require_once SFRS_PATH . 'includes/class-rules.php';
require_once SFRS_PATH . 'includes/class-diagnostics.php';
require_once SFRS_PATH . 'includes/class-simulator.php';
require_once SFRS_PATH . 'includes/class-activator.php';
require_once SFRS_PATH . 'includes/class-plugin.php';
if ( is_admin() ) {
	require_once SFRS_PATH . 'includes/class-admin.php';
}

register_activation_hook( __FILE__, array( 'Sfrs_Activator', 'activate' ) );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SFRS_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', SFRS_FILE, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'Sfrs_Plugin', 'init' ), 20 );
