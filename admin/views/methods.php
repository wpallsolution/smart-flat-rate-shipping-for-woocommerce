<?php
/**
 * Shipping methods list. Variables: $data (rules, total, pages), $page.
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( ! $data['rules'] ) : ?>
	<div class="sfrs-card sfrs-empty">
		<h2><?php esc_html_e( 'No shipping methods yet', 'smart-flat-rate-shipping-for-woocommerce' ); ?></h2>
		<p><?php esc_html_e( 'Create a method with a fee and optional conditions. Then add "Smart Flat Rate" to a shipping zone under WooCommerce > Settings > Shipping so customers can receive it.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p>
		<p><a class="button button-primary" href="<?php echo esc_url( Sfrs_Admin::url( array( 'action' => 'add' ) ) ); ?>"><?php esc_html_e( 'Add your first method', 'smart-flat-rate-shipping-for-woocommerce' ); ?></a></p>
	</div>
<?php else : ?>
	<table class="wp-list-table widefat fixed striped sfrs-table">
		<thead>
			<tr>
				<th scope="col" class="column-primary"><?php esc_html_e( 'Method', 'smart-flat-rate-shipping-for-woocommerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Customer label', 'smart-flat-rate-shipping-for-woocommerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Amount', 'smart-flat-rate-shipping-for-woocommerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'smart-flat-rate-shipping-for-woocommerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Priority', 'smart-flat-rate-shipping-for-woocommerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Conditions', 'smart-flat-rate-shipping-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $data['rules'] as $sfrs_rule ) : ?>
			<tr>
				<td class="column-primary" data-colname="<?php esc_attr_e( 'Method', 'smart-flat-rate-shipping-for-woocommerce' ); ?>">
					<strong><a href="<?php echo esc_url( Sfrs_Admin::url( array( 'action' => 'edit', 'rule_id' => $sfrs_rule['id'] ) ) ); ?>"><?php echo esc_html( $sfrs_rule['name'] ); ?></a></strong>
					<div class="sfrs-row-actions">
						<a href="<?php echo esc_url( Sfrs_Admin::url( array( 'action' => 'edit', 'rule_id' => $sfrs_rule['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'smart-flat-rate-shipping-for-woocommerce' ); ?></a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sfrs-inline-form">
							<input type="hidden" name="action" value="sfrs_duplicate_rule">
							<input type="hidden" name="rule_id" value="<?php echo esc_attr( $sfrs_rule['id'] ); ?>">
							<?php wp_nonce_field( 'sfrs_duplicate_rule_' . $sfrs_rule['id'] ); ?>
							<button type="submit" class="button-link"><?php esc_html_e( 'Duplicate', 'smart-flat-rate-shipping-for-woocommerce' ); ?></button>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sfrs-inline-form" data-confirm="delete">
							<input type="hidden" name="action" value="sfrs_delete_rule">
							<input type="hidden" name="rule_id" value="<?php echo esc_attr( $sfrs_rule['id'] ); ?>">
							<?php wp_nonce_field( 'sfrs_delete_rule_' . $sfrs_rule['id'] ); ?>
							<button type="submit" class="button-link button-link-delete"><?php esc_html_e( 'Delete', 'smart-flat-rate-shipping-for-woocommerce' ); ?></button>
						</form>
					</div>
				</td>
				<td data-colname="<?php esc_attr_e( 'Customer label', 'smart-flat-rate-shipping-for-woocommerce' ); ?>"><?php echo esc_html( $sfrs_rule['label'] ); ?></td>
				<td data-colname="<?php esc_attr_e( 'Amount', 'smart-flat-rate-shipping-for-woocommerce' ); ?>"><?php echo esc_html( Sfrs_Rules::price_summary( $sfrs_rule ) ); ?></td>
				<td data-colname="<?php esc_attr_e( 'Status', 'smart-flat-rate-shipping-for-woocommerce' ); ?>">
					<?php if ( $sfrs_rule['enabled'] ) : ?>
						<span class="sfrs-badge sfrs-badge--on"><span aria-hidden="true">&#10003;</span> <?php esc_html_e( 'Enabled', 'smart-flat-rate-shipping-for-woocommerce' ); ?></span>
					<?php else : ?>
						<span class="sfrs-badge sfrs-badge--off"><span aria-hidden="true">&#8211;</span> <?php esc_html_e( 'Disabled', 'smart-flat-rate-shipping-for-woocommerce' ); ?></span>
					<?php endif; ?>
				</td>
				<td data-colname="<?php esc_attr_e( 'Priority', 'smart-flat-rate-shipping-for-woocommerce' ); ?>"><?php echo esc_html( $sfrs_rule['priority'] ); ?></td>
				<td data-colname="<?php esc_attr_e( 'Conditions', 'smart-flat-rate-shipping-for-woocommerce' ); ?>"><?php echo esc_html( Sfrs_Rules::conditions_summary( $sfrs_rule ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ( $data['pages'] > 1 ) : ?>
		<div class="tablenav bottom"><div class="tablenav-pages">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%', Sfrs_Admin::url() ),
						'format'    => '',
						'current'   => $page,
						'total'     => $data['pages'],
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
					)
				)
			);
			?>
		</div></div>
	<?php endif; ?>
	<p class="description"><?php esc_html_e( 'Lower priority numbers are evaluated first. Every enabled method is evaluated independently and each matching method is offered to the customer as its own shipping option.', 'smart-flat-rate-shipping-for-woocommerce' ); ?></p>
<?php endif; ?>
