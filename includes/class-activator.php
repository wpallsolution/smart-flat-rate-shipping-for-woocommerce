<?php
/**
 * Activation, deactivation and versioned data migrations.
 *
 * Deactivation deliberately removes nothing: shipping configurations are kept.
 * Removal on uninstall is governed by the "delete data" setting (see uninstall.php).
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Sfrs_Activator {

	const VERSION_OPTION = 'sfrs_db_version';

	/**
	 * Activation hook.
	 */
	public static function activate() {
		Sfrs_Rules::register_post_type();
		// Autoloaded on purpose: tiny, and read on every shipping calculation.
		add_option( Sfrs_Settings::OPTION, Sfrs_Settings::defaults(), '', true );
		self::maybe_upgrade();
	}

	/**
	 * Run pending migrations. Each migration is idempotent and keyed by the version
	 * it upgrades to; configurations are never discarded.
	 */
	public static function maybe_upgrade() {
		$installed = get_option( self::VERSION_OPTION, '0' );
		if ( version_compare( $installed, SFRS_VERSION, '>=' ) ) {
			return;
		}
		$migrations = array(
			// '1.1.0' => array( __CLASS__, 'migrate_1_1_0' ),
		);
		foreach ( $migrations as $version => $callback ) {
			if ( version_compare( $installed, $version, '<' ) ) {
				call_user_func( $callback );
			}
		}
		update_option( self::VERSION_OPTION, SFRS_VERSION, false );
	}
}
