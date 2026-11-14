<?php
/**
 * Diagnostics and help tab.
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'ABSPATH' ) || exit;

$sfrs_log = Sfrs_Diagnostics::get_log();
?>
<section class="sfrs-card">
	<h2><?php esc_html_e( 'Environment checks', 'smart-flat-rate-shipping-for-woocommerce' ); ?></h2>
	<table class="widefat striped sfrs-table">
		<tbody>
		<?php foreach ( Sfrs_Diagnostics::environment() as $sfrs_row ) : ?>
			<tr>
				<th scope="row"><?php echo esc_html( $sfrs_row['label'] ); ?></th>
				<td>
					<?php if ( $sfrs_row['ok'] ) : ?>
						<span class="sfrs-badge sfrs-badge--on"><span aria-hidden="true">&#10003;</span> <?php esc_html_e( 'OK', 'smart-flat-rate-shipping-for-woocommerce' ); ?></span>
					<?php else : ?>
						<span class="sfrs-badge sfrs-badge--no"><span aria-hidden="true">&#10007;</span> <?php esc_html_e( 'Needs attention', 'smart-flat-rate-shipping-for-woocommerce' ); ?></span>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( $sfrs_row['detail'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</section>

<section class="sfrs-card">
	<h2><?php esc_html_e( 'Recent live evaluations', 'smart-flat-rate-shipping-for-woocommerce' ); ?></h2>
	<?php if ( ! Sfrs_Settings::get( 'diagnostics_log' ) ) : ?>
		<p><?php esc_html_e( 'Logging is off. Enable "Record recent live evaluations" under Settings, then view the cart or checkout to see why each method matched or not. Use the Shipping simulator for what-if tests without touching a real cart.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p>
	<?php endif; ?>
	<?php if ( ! $sfrs_log ) : ?>
		<p><?php esc_html_e( 'No evaluations recorded.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p>
	<?php else : ?>
		<?php foreach ( $sfrs_log as $sfrs_entry ) : ?>
			<article class="sfrs-quote">
				<h3><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $sfrs_entry['time'] ) ); ?></h3>
				<p><?php echo esc_html( sprintf( /* translators: 1: quantity, 2: subtotal, 3: destination. */ __( 'Quantity %1$d, subtotal %2$s, destination %3$s.', 'smart-flat-rate-shipping-for-woocommerce' ), $sfrs_entry['qty'], sfrs_money_text( $sfrs_entry['subtotal'] ), trim( $sfrs_entry['country'] . ' ' . $sfrs_entry['state'] ) ?: __( 'not provided', 'smart-flat-rate-shipping-for-woocommerce' ) ) ); ?></p>
				<ul class="sfrs-checks">
				<?php foreach ( $sfrs_entry['methods'] as $sfrs_method ) : ?>
					<li class="<?php echo $sfrs_method['matched'] ? 'is-pass' : 'is-fail'; ?>">
						<span class="sfrs-mark"><span aria-hidden="true"><?php echo $sfrs_method['matched'] ? '&#10003;' : '&#10007;'; ?></span></span>
						<strong><?php echo esc_html( $sfrs_method['name'] ); ?></strong>:
						<?php
						echo esc_html( $sfrs_method['matched'] ? sprintf( /* translators: %s: amount. */ __( 'offered at %s', 'smart-flat-rate-shipping-for-woocommerce' ), sfrs_money_text( $sfrs_method['cost'] ) ) : __( 'not offered', 'smart-flat-rate-shipping-for-woocommerce' ) );
						if ( $sfrs_method['failed'] ) {
							echo ' &mdash; ' . esc_html( implode( '; ', $sfrs_method['failed'] ) );
						}
						?>
					</li>
				<?php endforeach; ?>
				</ul>
			</article>
		<?php endforeach; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sfrs_clear_log">
			<?php wp_nonce_field( 'sfrs_clear_log' ); ?>
			<button type="submit" class="button"><?php esc_html_e( 'Clear log', 'smart-flat-rate-shipping-for-woocommerce' ); ?></button>
		</form>
	<?php endif; ?>
</section>

<section class="sfrs-card">
	<h2><?php esc_html_e( 'How it works', 'smart-flat-rate-shipping-for-woocommerce' ); ?></h2>
	<ol>
		<li><?php esc_html_e( 'Create one or more methods under Shipping methods.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></li>
		<li><?php esc_html_e( 'Under WooCommerce > Settings > Shipping, open a shipping zone and add the "Smart Flat Rate" method once. Zones decide which customers can get these methods.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></li>
		<li><?php esc_html_e( 'At checkout, every enabled method whose conditions match is offered as its own option. Native WooCommerce methods in the zone are not changed.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></li>
	</ol>
	<h3><?php esc_html_e( 'Rule model', 'smart-flat-rate-shipping-for-woocommerce' ); ?></h3>
	<ul>
		<li><?php esc_html_e( 'Cost = base fee + per-item fee x chargeable units. If the eligible subtotal reaches the free-shipping threshold, the cost is 0.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></li>
		<li><?php esc_html_e( 'Conditions in a group are AND; groups are OR. A malformed or empty group never matches.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></li>
		<li><?php esc_html_e( 'Eligible items: for the first matching group, the items selected by its "contains any of" product/category conditions (all items if it has none). The free-shipping threshold is always measured on the eligible subtotal; per-item fees use eligible or all units depending on the method\'s "Units charged per item" setting.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></li>
		<li><?php esc_html_e( 'Subtotals exclude tax. Shipping packages are evaluated independently; each package uses only its own items and destination.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></li>
		<li><?php esc_html_e( 'Not supported: shipping-class, weight or coupon conditions, and splitting a cost between products.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></li>
	</ul>
</section>
