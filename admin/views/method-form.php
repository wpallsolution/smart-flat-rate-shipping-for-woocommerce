<?php
/**
 * Add/edit form. Variables: $rule, $errors, $id, $action.
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'ABSPATH' ) || exit;

$sfrs_groups = is_array( $rule['groups'] ) ? array_values( $rule['groups'] ) : array();
?>
<?php if ( $errors ) : ?>
	<div class="notice inline notice-error sfrs-errors" role="alert" tabindex="-1">
		<p><strong><?php esc_html_e( 'The method was not saved. Please fix the following:', 'smart-flat-rate-shipping-for-woocommerce' ); ?></strong></p>
		<ul>
			<?php foreach ( $errors as $sfrs_error ) : ?>
				<li><?php echo esc_html( $sfrs_error ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sfrs-form" id="sfrs-rule-form" novalidate>
	<input type="hidden" name="action" value="sfrs_save_rule">
	<input type="hidden" name="rule_id" value="<?php echo esc_attr( $id ); ?>">
	<?php wp_nonce_field( 'sfrs_save_rule' ); ?>

	<section class="sfrs-card">
		<h2><?php esc_html_e( 'General', 'smart-flat-rate-shipping-for-woocommerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="sfrs-name"><?php esc_html_e( 'Internal name', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label></th>
				<td><input type="text" id="sfrs-name" name="name" class="regular-text" maxlength="120" value="<?php echo esc_attr( $rule['name'] ); ?>" required>
					<p class="description"><?php esc_html_e( 'Only you see this. Use it to tell methods apart.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><label for="sfrs-label"><?php esc_html_e( 'Customer-facing label', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label></th>
				<td><input type="text" id="sfrs-label" name="label" class="regular-text" maxlength="120" value="<?php echo esc_attr( $rule['label'] ); ?>" required>
					<p class="description"><?php esc_html_e( 'The shipping option name shown in the cart and checkout, e.g. "Standard shipping".', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><label for="sfrs-description"><?php esc_html_e( 'Customer-facing description', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label></th>
				<td><input type="text" id="sfrs-description" name="description" class="large-text" maxlength="300" value="<?php echo esc_attr( $rule['description'] ); ?>">
					<p class="description"><?php esc_html_e( 'Optional short text shown under the label in the classic cart and checkout. The block-based cart and checkout may not display it.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'smart-flat-rate-shipping-for-woocommerce' ); ?></th>
				<td><?php Sfrs_Admin::switch_field( 'enabled', $rule['enabled'], __( 'Enabled', 'smart-flat-rate-shipping-for-woocommerce' ), 'sfrs-enabled' ); ?>
					<p class="description"><?php esc_html_e( 'Disabled methods are never offered to customers.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p></td>
			</tr>
		</table>
	</section>

	<section class="sfrs-card">
		<h2><?php esc_html_e( 'Pricing', 'smart-flat-rate-shipping-for-woocommerce' ); ?></h2>
		<p class="description"><?php
			printf(
				/* translators: %s: currency symbol. */
				esc_html__( 'Amounts use the store currency (%s). Type numbers with "." as the decimal separator and no thousands separators, e.g. 1000.50.', 'smart-flat-rate-shipping-for-woocommerce' ),
				esc_html( function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '' )
			);
		?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="sfrs-base-fee"><?php esc_html_e( 'Base fee', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label></th>
				<td><input type="text" inputmode="decimal" id="sfrs-base-fee" name="base_fee" class="small-text" value="<?php echo esc_attr( $rule['base_fee'] ); ?>">
					<p class="description"><?php esc_html_e( 'The fixed shipping charge. Use 0 for a free-shipping method.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><label for="sfrs-per-item"><?php esc_html_e( 'Per-item fee', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label></th>
				<td><input type="text" inputmode="decimal" id="sfrs-per-item" name="per_item_fee" class="small-text" value="<?php echo esc_attr( $rule['per_item_fee'] ); ?>">
					<p class="description"><?php esc_html_e( 'Added once for every chargeable unit. Example: base 40 + per-item 10 with 3 units = 70. Variations count by their quantity. Leave 0 to disable.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><label for="sfrs-qty-scope"><?php esc_html_e( 'Units charged per item', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label></th>
				<td><select id="sfrs-qty-scope" name="qty_scope">
						<option value="cart" <?php selected( $rule['qty_scope'], 'cart' ); ?>><?php esc_html_e( 'All units in the cart', 'smart-flat-rate-shipping-for-woocommerce' ); ?></option>
						<option value="eligible" <?php selected( $rule['qty_scope'], 'eligible' ); ?>><?php esc_html_e( 'Only units of eligible products', 'smart-flat-rate-shipping-for-woocommerce' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Eligible products are those selected by the matching group\'s "contains any of" product or category conditions. If the group has none, every cart item is eligible.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><label for="sfrs-free"><?php esc_html_e( 'Free shipping from', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label></th>
				<td><input type="text" inputmode="decimal" id="sfrs-free" name="free_threshold" class="small-text" value="<?php echo esc_attr( $rule['free_threshold'] ); ?>" placeholder="<?php esc_attr_e( 'Off', 'smart-flat-rate-shipping-for-woocommerce' ); ?>">
					<p class="description"><?php
						$sfrs_after = 'before' !== Sfrs_Settings::get( 'subtotal_discounts' );
						echo esc_html(
							$sfrs_after
								? __( 'When the eligible subtotal is at least this amount the cost becomes 0. The subtotal excludes tax and is after coupon discounts (change this under Settings). Leave blank to disable; zero is not allowed.', 'smart-flat-rate-shipping-for-woocommerce' )
								: __( 'When the eligible subtotal is at least this amount the cost becomes 0. The subtotal excludes tax and is before coupon discounts (change this under Settings). Leave blank to disable; zero is not allowed.', 'smart-flat-rate-shipping-for-woocommerce' )
						);
					?></p></td>
			</tr>
			<tr>
				<th scope="row"><label for="sfrs-tax"><?php esc_html_e( 'Tax status', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label></th>
				<td><select id="sfrs-tax" name="tax_status">
						<option value="taxable" <?php selected( $rule['tax_status'], 'taxable' ); ?>><?php esc_html_e( 'Taxable', 'smart-flat-rate-shipping-for-woocommerce' ); ?></option>
						<option value="none" <?php selected( $rule['tax_status'], 'none' ); ?>><?php esc_html_e( 'None', 'smart-flat-rate-shipping-for-woocommerce' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Taxable applies your WooCommerce shipping tax class and rates, if taxes are enabled.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p></td>
			</tr>
			<?php do_action( 'sfrs_form_pricing_rows', $rule ); ?>
		</table>
	</section>

	<section class="sfrs-card">
		<h2><?php esc_html_e( 'Conditions', 'smart-flat-rate-shipping-for-woocommerce' ); ?></h2>
		<p class="description"><?php esc_html_e( 'The method is offered when at least one group matches. Inside a group, every condition must match (AND). Several groups work as OR. With no groups the method is always offered in the zones it is added to.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p>
		<ul class="sfrs-help-list description">
			<li><?php esc_html_e( 'Cart subtotal: whole-cart subtotal excluding tax (coupon handling follows Settings).', 'smart-flat-rate-shipping-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Products / categories: "contains any of" needs a matching item with the minimum quantity; "contains none of" needs no match. Subcategories count. A variation matches its own ID or its parent product.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Location: uses the customer\'s shipping address. If the value is not entered yet, location conditions do not match. States use COUNTRY:STATE codes (IN:GJ). Postcodes accept exact values, a trailing wildcard (380*) and numeric ranges (380001...380060).', 'smart-flat-rate-shipping-for-woocommerce' ); ?></li>
		</ul>

		<div id="sfrs-groups" data-groups>
			<?php
			$sfrs_g = 0;
			foreach ( $sfrs_groups as $sfrs_group ) {
				if ( $sfrs_g > 0 ) {
					echo '<p class="sfrs-or" aria-hidden="true"><span>' . esc_html__( 'OR', 'smart-flat-rate-shipping-for-woocommerce' ) . '</span></p>';
				}
				Sfrs_Admin::group_block( $sfrs_g, is_array( $sfrs_group ) ? $sfrs_group : array(), sprintf( /* translators: %d: group number. */ __( 'Group %d', 'smart-flat-rate-shipping-for-woocommerce' ), $sfrs_g + 1 ) );
				++$sfrs_g;
			}
			?>
		</div>
		<p><button type="button" class="button" data-action="add-group"><?php esc_html_e( 'Add condition group (OR)', 'smart-flat-rate-shipping-for-woocommerce' ); ?></button></p>

		<template id="sfrs-group-template"><?php Sfrs_Admin::group_block( '__G__', array( array() ), '' ); ?></template>
		<template id="sfrs-condition-template"><?php Sfrs_Admin::condition_row( '__G__', '__C__', array() ); ?></template>
	</section>

	<section class="sfrs-card">
		<h2><?php esc_html_e( 'Behavior', 'smart-flat-rate-shipping-for-woocommerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="sfrs-priority"><?php esc_html_e( 'Priority', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label></th>
				<td><input type="number" min="0" max="9999" step="1" id="sfrs-priority" name="priority" class="small-text" value="<?php echo esc_attr( $rule['priority'] ); ?>">
					<p class="description"><?php esc_html_e( 'Lower numbers are evaluated and added first (equal priorities: older method first). Methods are independent: all matching methods are offered, none hides another and their prices are never added together. WooCommerce or other plugins may still re-sort the options shown to customers.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p></td>
			</tr>
		</table>
	</section>

</form>
