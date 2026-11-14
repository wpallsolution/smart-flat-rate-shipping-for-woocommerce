<?php
/**
 * Shipping simulator tab. Read-only: no order, cart, stock, payment or e-mail is created.
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'ABSPATH' ) || exit;

$sfrs_result = null;
$sfrs_input  = array( 'lines' => array( array() ), 'country' => '', 'state' => '', 'postcode' => '' );

if ( isset( $_SERVER['REQUEST_METHOD'], $_POST['sfrs_simulate'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by Sfrs_Security::require_admin() below.
	Sfrs_Security::require_admin( 'sfrs_simulate' );
	$sfrs_input  = array_merge( $sfrs_input, wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- nonce verified above; every value is validated in Sfrs_Simulator::run().
	$sfrs_result = Sfrs_Simulator::run( $sfrs_input );
}
// "Fill sample data": prefill the form (read-only convenience, nothing is saved or run).
if ( ! $sfrs_result && isset( $_GET['sample'] ) && isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === $_SERVER['REQUEST_METHOD'] ) { // phpcs:ignore WordPress.Security.NonceVerification
	$sfrs_sample_ids = wc_get_products( array( 'limit' => 2, 'status' => 'publish', 'return' => 'ids', 'type' => array( 'simple', 'variable' ) ) );
	$sfrs_sample     = array();
	foreach ( $sfrs_sample_ids as $sfrs_n => $sfrs_sample_id ) {
		$sfrs_sample[] = array( 'id' => $sfrs_sample_id, 'qty' => 0 === $sfrs_n ? 4 : 1, 'price' => '' );
	}
	if ( $sfrs_sample ) {
		$sfrs_input['lines'] = $sfrs_sample;
	}
	$sfrs_base_country     = wc_get_base_location();
	$sfrs_input['country'] = $sfrs_base_country['country'];
	$sfrs_input['state']   = $sfrs_base_country['state'];
	$sfrs_input['postcode'] = '90001';
}
$sfrs_countries = WC()->countries->get_countries();
$sfrs_lines     = isset( $sfrs_input['lines'] ) && is_array( $sfrs_input['lines'] ) && $sfrs_input['lines'] ? array_values( $sfrs_input['lines'] ) : array( array() );

/**
 * Print one sample-cart row.
 *
 * @param int|string $sfrs_i    Index or placeholder.
 * @param array      $sfrs_row  Row data.
 */
$sfrs_render_row = function ( $sfrs_i, $sfrs_row ) {
	$sfrs_id      = isset( $sfrs_row['id'] ) ? absint( $sfrs_row['id'] ) : 0;
	$sfrs_product = $sfrs_id && function_exists( 'wc_get_product' ) ? wc_get_product( $sfrs_id ) : null;
	?>
	<tr data-line>
		<td>
			<label class="screen-reader-text" for="sfrs-line-<?php echo esc_attr( $sfrs_i ); ?>-id"><?php esc_html_e( 'Product or variation', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label>
			<select id="sfrs-line-<?php echo esc_attr( $sfrs_i ); ?>-id" class="wc-product-search" style="width:100%" name="lines[<?php echo esc_attr( $sfrs_i ); ?>][id]" data-placeholder="<?php esc_attr_e( 'Search for a product or variation…', 'smart-flat-rate-shipping-for-woocommerce' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true">
				<?php if ( $sfrs_product ) : ?>
					<option value="<?php echo esc_attr( $sfrs_id ); ?>" selected="selected"><?php echo esc_html( wp_strip_all_tags( $sfrs_product->get_formatted_name() ) ); ?></option>
				<?php endif; ?>
			</select>
		</td>
		<td><label class="screen-reader-text" for="sfrs-line-<?php echo esc_attr( $sfrs_i ); ?>-qty"><?php esc_html_e( 'Quantity', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label>
			<input type="number" min="1" step="1" class="small-text" id="sfrs-line-<?php echo esc_attr( $sfrs_i ); ?>-qty" name="lines[<?php echo esc_attr( $sfrs_i ); ?>][qty]" value="<?php echo esc_attr( isset( $sfrs_row['qty'] ) ? $sfrs_row['qty'] : '1' ); ?>"></td>
		<td><label class="screen-reader-text" for="sfrs-line-<?php echo esc_attr( $sfrs_i ); ?>-price"><?php esc_html_e( 'Unit price (excluding tax)', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label>
			<input type="text" inputmode="decimal" class="small-text" id="sfrs-line-<?php echo esc_attr( $sfrs_i ); ?>-price" name="lines[<?php echo esc_attr( $sfrs_i ); ?>][price]" value="<?php echo esc_attr( isset( $sfrs_row['price'] ) ? $sfrs_row['price'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Current', 'smart-flat-rate-shipping-for-woocommerce' ); ?>"></td>
		<td><button type="button" class="button-link button-link-delete" data-action="remove-line"><?php esc_html_e( 'Remove', 'smart-flat-rate-shipping-for-woocommerce' ); ?></button></td>
	</tr>
	<?php
};
?>
<h2><?php esc_html_e( 'Test shipping rates', 'smart-flat-rate-shipping-for-woocommerce' ); ?></h2>
<div class="notice notice-info inline"><p><?php esc_html_e( 'Simulated results use the same rule engine as checkout, but they are not a real checkout: nothing is added to a cart, no order is created, no stock changes and nothing is charged. Coupons are not simulated; enter the discounted price in the price field if you need to test them.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p></div>

<?php if ( is_wp_error( $sfrs_result ) ) : ?>
	<div class="notice inline notice-error" role="alert"><ul><?php foreach ( (array) ( $sfrs_result->get_error_data() ? $sfrs_result->get_error_data() : array( $sfrs_result->get_error_message() ) ) as $sfrs_msg ) : ?><li><?php echo esc_html( $sfrs_msg ); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<p><a class="button" href="<?php echo esc_url( Sfrs_Admin::url( array( 'tab' => 'simulator', 'sample' => '1' ) ) ); ?>"><?php esc_html_e( 'Fill sample data', 'smart-flat-rate-shipping-for-woocommerce' ); ?></a>
	<span class="description"><?php esc_html_e( 'Prefills the form with two products from your shop and a sample destination. Nothing is saved until you press "Simulate shipping".', 'smart-flat-rate-shipping-for-woocommerce' ); ?></span></p>

<form method="post" action="<?php echo esc_url( Sfrs_Admin::url( array( 'tab' => 'simulator' ) ) ); ?>" class="sfrs-form">
	<?php wp_nonce_field( 'sfrs_simulate' ); ?>
	<input type="hidden" name="sfrs_simulate" value="1">
	<section class="sfrs-card">
		<h2><?php esc_html_e( 'Sample cart', 'smart-flat-rate-shipping-for-woocommerce' ); ?></h2>
		<table class="widefat sfrs-lines">
			<thead><tr>
				<th scope="col"><?php esc_html_e( 'Product or variation', 'smart-flat-rate-shipping-for-woocommerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Quantity', 'smart-flat-rate-shipping-for-woocommerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Unit price (excl. tax)', 'smart-flat-rate-shipping-for-woocommerce' ); ?></th>
				<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'smart-flat-rate-shipping-for-woocommerce' ); ?></span></th>
			</tr></thead>
			<tbody data-lines>
				<?php foreach ( $sfrs_lines as $sfrs_i => $sfrs_line ) { $sfrs_render_row( $sfrs_i, is_array( $sfrs_line ) ? $sfrs_line : array() ); } ?>
			</tbody>
		</table>
		<p><button type="button" class="button" data-action="add-line"><?php esc_html_e( 'Add product', 'smart-flat-rate-shipping-for-woocommerce' ); ?></button></p>
		<template id="sfrs-line-template"><table><tbody><?php $sfrs_render_row( '__I__', array() ); ?></tbody></table></template>
		<p class="description"><?php esc_html_e( 'Categories come from the selected products. Leave the price blank to use the current product price.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p>
	</section>
	<section class="sfrs-card">
		<h2><?php esc_html_e( 'Destination', 'smart-flat-rate-shipping-for-woocommerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><label for="sfrs-sim-country"><?php esc_html_e( 'Country', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label></th>
				<td><select id="sfrs-sim-country" name="country" class="wc-enhanced-select" style="width:300px" data-allow_clear="true" data-placeholder="<?php esc_attr_e( 'No country', 'smart-flat-rate-shipping-for-woocommerce' ); ?>">
					<option value=""></option>
					<?php foreach ( $sfrs_countries as $sfrs_code => $sfrs_name ) : ?>
						<option value="<?php echo esc_attr( $sfrs_code ); ?>" <?php selected( isset( $sfrs_input['country'] ) ? $sfrs_input['country'] : '', $sfrs_code ); ?>><?php echo esc_html( $sfrs_name ); ?></option>
					<?php endforeach; ?>
				</select></td></tr>
			<tr><th scope="row"><label for="sfrs-sim-state"><?php esc_html_e( 'State / region code', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label></th>
				<td><input type="text" id="sfrs-sim-state" name="state" class="small-text" value="<?php echo esc_attr( isset( $sfrs_input['state'] ) ? $sfrs_input['state'] : '' ); ?>" placeholder="GJ"></td></tr>
			<tr><th scope="row"><label for="sfrs-sim-postcode"><?php esc_html_e( 'Postcode', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label></th>
				<td><input type="text" id="sfrs-sim-postcode" name="postcode" class="regular-text" value="<?php echo esc_attr( isset( $sfrs_input['postcode'] ) ? $sfrs_input['postcode'] : '' ); ?>"></td></tr>
			<?php do_action( 'sfrs_simulator_fields', $sfrs_input ); ?>
		</table>
	</section>
	<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Simulate shipping', 'smart-flat-rate-shipping-for-woocommerce' ); ?></button></p>
</form>

<?php if ( is_array( $sfrs_result ) ) : ?>
	<section class="sfrs-card sfrs-result" aria-live="polite">
		<h2><?php esc_html_e( 'Simulated result', 'smart-flat-rate-shipping-for-woocommerce' ); ?> <span class="sfrs-badge sfrs-badge--off"><?php esc_html_e( 'Simulation, not a real checkout', 'smart-flat-rate-shipping-for-woocommerce' ); ?></span></h2>
		<h3><?php esc_html_e( 'Input summary', 'smart-flat-rate-shipping-for-woocommerce' ); ?></h3>
		<?php
		$sfrs_ctx = $sfrs_result['context'];
		?>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: quantity, 2: subtotal, 3: destination. */
					__( 'Quantity %1$d, subtotal %2$s (excluding tax), destination: %3$s.', 'smart-flat-rate-shipping-for-woocommerce' ),
					array_sum( wp_list_pluck( $sfrs_ctx['items'], 'qty' ) ),
					sfrs_money_text( array_sum( wp_list_pluck( $sfrs_ctx['items'], 'amount' ) ) ),
					trim( implode( ' / ', array_filter( array( $sfrs_ctx['destination']['country'], $sfrs_ctx['destination']['state'], $sfrs_ctx['destination']['postcode'] ) ) ) ) ?: __( 'not provided', 'smart-flat-rate-shipping-for-woocommerce' )
				)
			);
			?>
		</p>
		<?php if ( ! $sfrs_result['quotes'] ) : ?>
			<p><?php esc_html_e( 'No shipping methods are configured yet.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p>
		<?php endif; ?>
		<?php foreach ( $sfrs_result['quotes'] as $sfrs_quote ) : ?>
			<article class="sfrs-quote <?php echo $sfrs_quote['matched'] ? 'is-match' : 'is-nomatch'; ?>">
				<h3>
					<?php echo esc_html( $sfrs_quote['name'] ); ?>
					<?php if ( ! $sfrs_quote['evaluated'] ) : ?>
						<span class="sfrs-badge sfrs-badge--off"><span aria-hidden="true">&#8211;</span> <?php esc_html_e( 'Not evaluated', 'smart-flat-rate-shipping-for-woocommerce' ); ?></span>
					<?php elseif ( $sfrs_quote['matched'] ) : ?>
						<span class="sfrs-badge sfrs-badge--on"><span aria-hidden="true">&#10003;</span> <?php esc_html_e( 'Matches', 'smart-flat-rate-shipping-for-woocommerce' ); ?></span>
					<?php else : ?>
						<span class="sfrs-badge sfrs-badge--no"><span aria-hidden="true">&#10007;</span> <?php esc_html_e( 'Does not match', 'smart-flat-rate-shipping-for-woocommerce' ); ?></span>
					<?php endif; ?>
				</h3>
				<p><?php echo esc_html( $sfrs_quote['reason'] ); ?></p>
				<?php if ( $sfrs_quote['matched'] ) : ?>
					<p class="sfrs-quote__cost"><?php esc_html_e( 'Calculated rate:', 'smart-flat-rate-shipping-for-woocommerce' ); ?> <strong><?php echo esc_html( $sfrs_quote['is_free'] || 0.0 === (float) $sfrs_quote['cost'] ? __( 'Free', 'smart-flat-rate-shipping-for-woocommerce' ) . ' (' . sfrs_money_text( 0 ) . ')' : sfrs_money_text( $sfrs_quote['cost'] ) ); ?></strong>
						<span class="description">(<?php echo esc_html( $sfrs_quote['label'] ); ?>)</span></p>
					<ul><?php foreach ( $sfrs_quote['breakdown'] as $sfrs_line ) : ?><li><?php echo esc_html( $sfrs_line ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
				<?php if ( ! empty( $sfrs_quote['evaluation']['groups'] ) ) : ?>
					<?php foreach ( $sfrs_quote['evaluation']['groups'] as $sfrs_gi => $sfrs_group ) : ?>
						<p class="sfrs-group-result"><strong><?php echo esc_html( sprintf( /* translators: %d: group number. */ __( 'Group %d', 'smart-flat-rate-shipping-for-woocommerce' ), $sfrs_gi + 1 ) ); ?>:</strong>
							<?php echo esc_html( $sfrs_group['passed'] ? __( 'passed', 'smart-flat-rate-shipping-for-woocommerce' ) : __( 'failed', 'smart-flat-rate-shipping-for-woocommerce' ) ); ?></p>
						<ul class="sfrs-checks">
							<?php foreach ( $sfrs_group['conditions'] as $sfrs_check ) : ?>
								<li class="<?php echo $sfrs_check['passed'] ? 'is-pass' : 'is-fail'; ?>">
									<span class="sfrs-mark"><span aria-hidden="true"><?php echo $sfrs_check['passed'] ? '&#10003;' : '&#10007;'; ?></span><span class="screen-reader-text"><?php echo esc_html( $sfrs_check['passed'] ? __( 'Passed:', 'smart-flat-rate-shipping-for-woocommerce' ) : __( 'Failed:', 'smart-flat-rate-shipping-for-woocommerce' ) ); ?></span></span>
									<?php echo esc_html( $sfrs_check['label'] ); ?> &mdash; <?php echo esc_html( $sfrs_check['detail'] ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endforeach; ?>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</section>
<?php endif; ?>
