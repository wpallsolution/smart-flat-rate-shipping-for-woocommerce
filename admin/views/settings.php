<?php
/**
 * Settings tab.
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'ABSPATH' ) || exit;

$sfrs_opt = Sfrs_Settings::OPTION;
?>
<form method="post" action="options.php" class="sfrs-form" id="sfrs-settings-form">
	<?php settings_fields( Sfrs_Settings::GROUP ); ?>
	<section class="sfrs-card">
		<h2><?php esc_html_e( 'Settings', 'smart-flat-rate-shipping-for-woocommerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="sfrs-discounts"><?php esc_html_e( 'Subtotal and coupons', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label></th>
				<td><select id="sfrs-discounts" name="<?php echo esc_attr( $sfrs_opt ); ?>[subtotal_discounts]">
						<option value="after" <?php selected( Sfrs_Settings::get( 'subtotal_discounts' ), 'after' ); ?>><?php esc_html_e( 'After coupon discounts (excluding tax)', 'smart-flat-rate-shipping-for-woocommerce' ); ?></option>
						<option value="before" <?php selected( Sfrs_Settings::get( 'subtotal_discounts' ), 'before' ); ?>><?php esc_html_e( 'Before coupon discounts (excluding tax)', 'smart-flat-rate-shipping-for-woocommerce' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Applies to the cart subtotal condition and the free-shipping threshold. Subtotals never include tax, so the comparison basis is the same however your prices are entered.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Diagnostics log', 'smart-flat-rate-shipping-for-woocommerce' ); ?></th>
				<td><?php Sfrs_Admin::switch_field( $sfrs_opt . '[diagnostics_log]', (bool) Sfrs_Settings::get( 'diagnostics_log' ), __( 'Record recent live evaluations', 'smart-flat-rate-shipping-for-woocommerce' ), 'sfrs-diag' ); ?>
					<p class="description"><?php esc_html_e( 'Off by default. Keeps only the 20 most recent distinct evaluations and stores no postcode, customer or product details. Turn it off when you are done.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Uninstall', 'smart-flat-rate-shipping-for-woocommerce' ); ?></th>
				<td><?php Sfrs_Admin::switch_field( $sfrs_opt . '[delete_on_uninstall]', (bool) Sfrs_Settings::get( 'delete_on_uninstall' ), __( 'Delete all methods and settings when the plugin is deleted', 'smart-flat-rate-shipping-for-woocommerce' ), 'sfrs-uninstall' ); ?>
					<p class="description"><?php esc_html_e( 'Off by default: deactivating or deleting the plugin keeps your methods. WooCommerce data (zones, orders) is never touched.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p></td>
			</tr>
		</table>
	</section>
</form>
