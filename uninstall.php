<?php
/**
 * Uninstall policy: nothing is removed unless the administrator ticked
 * "Delete all methods and settings when the plugin is deleted" under Settings.
 * WooCommerce data (shipping zones, orders, products) is never touched.
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$sfrs_settings = get_option( 'sfrs_settings', array() );
if ( empty( $sfrs_settings['delete_on_uninstall'] ) ) {
	return;
}

$sfrs_ids = get_posts(
	array(
		'post_type'      => 'sfrs_rule',
		'post_status'    => 'any',
		'numberposts'    => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	)
);
foreach ( $sfrs_ids as $sfrs_id ) {
	wp_delete_post( $sfrs_id, true );
}
delete_option( 'sfrs_settings' );
delete_option( 'sfrs_db_version' );
delete_option( 'sfrs_diag_log' );
