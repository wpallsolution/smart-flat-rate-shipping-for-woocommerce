<?php
/**
 * Admin screens: methods list, add/edit form, settings, simulator, diagnostics.
 * Every state-changing action goes through Sfrs_Security (capability + nonce).
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Sfrs_Admin {

	const SLUG = 'sfrs';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_sfrs_save_rule', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_sfrs_delete_rule', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_sfrs_duplicate_rule', array( __CLASS__, 'handle_duplicate' ) );
		add_action( 'admin_post_sfrs_clear_log', array( __CLASS__, 'handle_clear_log' ) );
		add_filter( 'woocommerce_screen_ids', array( __CLASS__, 'screen_ids' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SFRS_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Settings link on the plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Shipping methods', 'smart-flat-rate-shipping-for-woocommerce' ) . '</a>' );
		return $links;
	}

	/**
	 * Admin page URL.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Let WooCommerce load its admin styles/scripts (selectWoo, product search) here.
	 *
	 * @param array $ids Screen IDs.
	 * @return array
	 */
	public static function screen_ids( $ids ) {
		$ids[] = 'woocommerce_page_' . self::SLUG;
		return $ids;
	}

	/**
	 * Menu under WooCommerce.
	 */
	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Smart Flat Rate Shipping', 'smart-flat-rate-shipping-for-woocommerce' ),
			__( 'Smart Flat Rate', 'smart-flat-rate-shipping-for-woocommerce' ),
			Sfrs_Security::capability(),
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Admin assets: only on our own screen.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function assets( $hook ) {
		if ( 'woocommerce_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_style( 'sfrs-admin', SFRS_URL . 'admin/css/admin.css', array(), SFRS_VERSION );
		wp_enqueue_script( 'sfrs-admin', SFRS_URL . 'admin/js/admin.js', array( 'jquery', 'selectWoo', 'wc-enhanced-select' ), SFRS_VERSION, true );

		$types = array();
		foreach ( Sfrs_Condition_Evaluator::types() as $key => $type ) {
			$types[ $key ] = array( 'label' => $type['label'], 'ops' => $type['ops'] );
		}
		wp_localize_script(
			'sfrs-admin',
			'sfrsAdmin',
			array(
				'types' => $types,
				'i18n'  => array(
					'confirmDelete'  => __( 'Delete this shipping method permanently? This cannot be undone.', 'smart-flat-rate-shipping-for-woocommerce' ),
					'confirmGroup'   => __( 'Remove this condition group?', 'smart-flat-rate-shipping-for-woocommerce' ),
					'removeCond'     => __( 'Remove condition', 'smart-flat-rate-shipping-for-woocommerce' ),
					'orSeparator'    => __( 'OR', 'smart-flat-rate-shipping-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Tabs.
	 *
	 * @return array
	 */
	protected static function tabs() {
		$tabs = array(
			'methods'     => __( 'Shipping methods', 'smart-flat-rate-shipping-for-woocommerce' ),
			'settings'    => __( 'Settings', 'smart-flat-rate-shipping-for-woocommerce' ),
			'simulator'   => __( 'Test shipping (simulator)', 'smart-flat-rate-shipping-for-woocommerce' ),
			'diagnostics' => __( 'Diagnostics & help', 'smart-flat-rate-shipping-for-woocommerce' ),
		);

		/**
		 * Lets an add-on add admin tabs. Render them on the sfrs_render_tab_{tab} action.
		 *
		 * @param array $tabs slug => label.
		 */
		return apply_filters( 'sfrs_admin_tabs', $tabs );
	}

	/**
	 * Router.
	 */
	public static function render() {
		if ( ! Sfrs_Security::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'smart-flat-rate-shipping-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'methods'; // phpcs:ignore WordPress.Security.NonceVerification
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( self::tabs()[ $tab ] ) ) {
			$tab = 'methods';
		}
		$editing = 'methods' === $tab && in_array( $action, array( 'add', 'edit' ), true );

		// Header and footer actions. Save buttons live outside the <form> and submit it via the form attribute.
		$save = null;
		if ( $editing ) {
			$save = array( 'form' => 'sfrs-rule-form', 'label' => __( 'Save method', 'smart-flat-rate-shipping-for-woocommerce' ) );
		} elseif ( 'settings' === $tab ) {
			$save = array( 'form' => 'sfrs-settings-form', 'label' => __( 'Save settings', 'smart-flat-rate-shipping-for-woocommerce' ) );
		}

		// Notices from other plugins are moved after .wp-header-end, i.e. above the panel.
		echo '<div class="wrap sfrs-wrap"><h1 class="screen-reader-text">' . esc_html__( 'Smart Flat Rate Shipping', 'smart-flat-rate-shipping-for-woocommerce' ) . '</h1><hr class="wp-header-end">';
		echo '<div class="sfrs-app">';

		$tabs  = self::tabs();
		$crumb = $tabs[ $tab ];
		if ( $editing ) {
			$crumb = 'add' === $action ? __( 'Add method', 'smart-flat-rate-shipping-for-woocommerce' ) : __( 'Edit method', 'smart-flat-rate-shipping-for-woocommerce' );
		}

		echo '<header class="sfrs-app__header"><div class="sfrs-app__brand"><span class="sfrs-app__logo" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" focusable="false"><path d="M3 5a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v2h3.2c.3 0 .6.1.8.4l2.5 3.1c.3.3.5.8.5 1.2V16a1 1 0 0 1-1 1h-.6a2.9 2.9 0 0 1-5.8 0H9.4a2.9 2.9 0 0 1-5.8 0H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm13 4v3h4.3l-1.9-3H16zM6.5 15.2a1.3 1.3 0 1 0 0 2.6 1.3 1.3 0 0 0 0-2.6zm11 0a1.3 1.3 0 1 0 0 2.6 1.3 1.3 0 0 0 0-2.6z"/></svg></span><div><span class="sfrs-app__title">' . esc_html__( 'Smart Flat Rate Shipping', 'smart-flat-rate-shipping-for-woocommerce' ) . '</span><span class="sfrs-app__crumb">' . esc_html( $crumb ) . '</span></div></div><div class="sfrs-app__actions">';
		if ( $editing ) {
			echo '<a class="button" href="' . esc_url( self::url() ) . '">' . esc_html__( 'Back to methods', 'smart-flat-rate-shipping-for-woocommerce' ) . '</a> ';
		} elseif ( 'methods' === $tab ) {
			echo '<a class="button button-primary" href="' . esc_url( self::url( array( 'action' => 'add' ) ) ) . '">' . esc_html__( 'Add method', 'smart-flat-rate-shipping-for-woocommerce' ) . '</a>';
		}
		if ( $save ) {
			echo '<button type="submit" class="button button-primary" form="' . esc_attr( $save['form'] ) . '">' . esc_html( $save['label'] ) . '</button>';
		}
		echo '</div></header>';

		echo '<div class="sfrs-app__body"><nav class="sfrs-nav" aria-label="' . esc_attr__( 'Sections', 'smart-flat-rate-shipping-for-woocommerce' ) . '">';
		foreach ( self::tabs() as $key => $label ) {
			printf(
				'<a href="%s" class="sfrs-tab%s"%s>%s</a>',
				esc_url( self::url( 'methods' === $key ? array() : array( 'tab' => $key ) ) ),
				$key === $tab ? ' is-active' : '',
				$key === $tab ? ' aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</nav><main class="sfrs-app__main">';

		self::notices();

		if ( 'methods' === $tab ) {
			if ( in_array( $action, array( 'add', 'edit' ), true ) ) {
				self::render_form( $action );
			} else {
				self::render_list();
			}
		} else {
			if ( in_array( $tab, array( 'settings', 'simulator', 'diagnostics' ), true ) ) {
				include SFRS_PATH . 'admin/views/' . $tab . '.php';
			} else {
				do_action( 'sfrs_render_tab_' . $tab );
			}
		}
		echo '</main></div>';

		echo '<footer class="sfrs-app__footer"><div class="sfrs-app__meta"><strong>' . esc_html__( 'Smart Flat Rate Shipping', 'smart-flat-rate-shipping-for-woocommerce' ) . '</strong> <span class="sfrs-app__version">' . esc_html( 'v' . SFRS_VERSION ) . '</span>';
		echo '<span class="sfrs-app__links"><a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ) . '">' . esc_html__( 'Shipping zones', 'smart-flat-rate-shipping-for-woocommerce' ) . '</a><a href="' . esc_url( self::url( array( 'tab' => 'simulator' ) ) ) . '">' . esc_html__( 'Test rates', 'smart-flat-rate-shipping-for-woocommerce' ) . '</a></span></div>';
		if ( $save ) {
			echo '<div class="sfrs-app__actions">';
			if ( $editing ) {
				echo '<a class="button" href="' . esc_url( self::url() ) . '">' . esc_html__( 'Cancel', 'smart-flat-rate-shipping-for-woocommerce' ) . '</a>';
			}
			echo '<button type="submit" class="button button-primary" form="' . esc_attr( $save['form'] ) . '">' . esc_html( $save['label'] ) . '</button></div>';
		}
		echo '</footer></div></div>';
	}

	/**
	 * A labelled on/off switch. It is a real checkbox (keyboard and screen-reader friendly);
	 * the Yes/No text inside the track means state is never conveyed by colour alone.
	 *
	 * @param string $name    Field name.
	 * @param bool   $checked Current state.
	 * @param string $label   Visible label.
	 * @param string $id      Element id.
	 */
	public static function switch_field( $name, $checked, $label, $id ) {
		?>
		<label class="sfrs-switch" for="<?php echo esc_attr( $id ); ?>">
			<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?>>
			<span class="sfrs-switch__track" aria-hidden="true">
				<span class="sfrs-switch__on"><?php esc_html_e( 'Yes', 'smart-flat-rate-shipping-for-woocommerce' ); ?></span>
				<span class="sfrs-switch__off"><?php esc_html_e( 'No', 'smart-flat-rate-shipping-for-woocommerce' ); ?></span>
				<span class="sfrs-switch__knob"></span>
			</span>
			<span class="sfrs-switch__text"><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	/**
	 * Result notices.
	 */
	protected static function notices() {
		$msg   = isset( $_GET['sfrs_msg'] ) ? sanitize_key( wp_unslash( $_GET['sfrs_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$texts = array(
			'saved'      => __( 'Shipping method saved.', 'smart-flat-rate-shipping-for-woocommerce' ),
			'deleted'    => __( 'Shipping method deleted.', 'smart-flat-rate-shipping-for-woocommerce' ),
			'duplicated' => __( 'Shipping method duplicated. The copy is disabled until you enable it.', 'smart-flat-rate-shipping-for-woocommerce' ),
			'cleared'    => __( 'Diagnostics log cleared.', 'smart-flat-rate-shipping-for-woocommerce' ),
			'notfound'   => __( 'That shipping method does not exist.', 'smart-flat-rate-shipping-for-woocommerce' ),
		);
		if ( isset( $texts[ $msg ] ) ) {
			printf( '<div class="notice inline notice-%s is-dismissible"><p>%s</p></div>', 'notfound' === $msg ? 'error' : 'success', esc_html( $texts[ $msg ] ) );
		}
	}

	/**
	 * Methods list.
	 */
	protected static function render_list() {
		$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$data = Sfrs_Rules::paginate( $page );
		include SFRS_PATH . 'admin/views/methods.php';
	}

	/**
	 * Add/edit form.
	 *
	 * @param string $action add|edit.
	 */
	protected static function render_form( $action ) {
		$id     = isset( $_GET['rule_id'] ) ? absint( $_GET['rule_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$errors = array();
		$rule   = Sfrs_Rules::defaults();

		if ( 'edit' === $action ) {
			$stored = Sfrs_Rules::get( $id );
			if ( ! $stored ) {
				wp_safe_redirect( self::url( array( 'sfrs_msg' => 'notfound' ) ) );
				exit;
			}
			$rule = $stored;
		}

		$key    = 'sfrs_form_' . get_current_user_id();
		$failed = get_transient( $key );
		if ( is_array( $failed ) && (int) $failed['id'] === $id ) {
			$rule   = array_merge( $rule, $failed['config'] );
			$errors = $failed['errors'];
		}
		delete_transient( $key );

		include SFRS_PATH . 'admin/views/method-form.php';
	}

	/**
	 * Save handler.
	 */
	public static function handle_save() {
		Sfrs_Security::require_admin( 'sfrs_save_rule' );
		$id     = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in Sfrs_Security::require_admin().
		$parsed = Sfrs_Rules::sanitize_request( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- nonce verified above; every field is validated in Sfrs_Rules::sanitize_request().
		if ( $id && ! Sfrs_Rules::get( $id ) ) {
			wp_safe_redirect( self::url( array( 'sfrs_msg' => 'notfound' ) ) );
			exit;
		}
		$back = $id ? self::url( array( 'action' => 'edit', 'rule_id' => $id ) ) : self::url( array( 'action' => 'add' ) );

		if ( $parsed['errors'] ) {
			set_transient( 'sfrs_form_' . get_current_user_id(), array( 'id' => $id, 'config' => $parsed['config'], 'errors' => $parsed['errors'] ), 5 * MINUTE_IN_SECONDS );
			wp_safe_redirect( $back );
			exit;
		}
		$saved = Sfrs_Rules::save( $parsed['config'], $id );
		if ( is_wp_error( $saved ) ) {
			set_transient( 'sfrs_form_' . get_current_user_id(), array( 'id' => $id, 'config' => $parsed['config'], 'errors' => array( $saved->get_error_message() ) ), 5 * MINUTE_IN_SECONDS );
			wp_safe_redirect( $back );
			exit;
		}
		wp_safe_redirect( self::url( array( 'action' => 'edit', 'rule_id' => $saved, 'sfrs_msg' => 'saved' ) ) );
		exit;
	}

	/**
	 * Delete handler (POST + nonce).
	 */
	public static function handle_delete() {
		$id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the ID only selects which nonce action to check on the next line.
		Sfrs_Security::require_admin( 'sfrs_delete_rule_' . $id );
		$done = Sfrs_Rules::delete( $id );
		wp_safe_redirect( self::url( array( 'sfrs_msg' => $done ? 'deleted' : 'notfound' ) ) );
		exit;
	}

	/**
	 * Duplicate handler (POST + nonce).
	 */
	public static function handle_duplicate() {
		$id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the ID only selects which nonce action to check on the next line.
		Sfrs_Security::require_admin( 'sfrs_duplicate_rule_' . $id );
		$new = Sfrs_Rules::duplicate( $id );
		if ( is_wp_error( $new ) ) {
			wp_safe_redirect( self::url( array( 'sfrs_msg' => 'notfound' ) ) );
		} else {
			wp_safe_redirect( self::url( array( 'sfrs_msg' => 'duplicated' ) ) );
		}
		exit;
	}

	/**
	 * Clear diagnostics log handler.
	 */
	public static function handle_clear_log() {
		Sfrs_Security::require_admin( 'sfrs_clear_log' );
		Sfrs_Diagnostics::clear();
		wp_safe_redirect( self::url( array( 'tab' => 'diagnostics', 'sfrs_msg' => 'cleared' ) ) );
		exit;
	}

	/**
	 * Render one condition row (also used, with placeholders, for the JS template).
	 *
	 * @param string|int $g    Group index or placeholder.
	 * @param string|int $c    Condition index or placeholder.
	 * @param array      $cond Condition data.
	 */
	public static function condition_row( $g, $c, array $cond ) {
		$types = Sfrs_Condition_Evaluator::types();
		$type  = isset( $cond['type'], $types[ $cond['type'] ] ) ? $cond['type'] : 'cart_subtotal';
		$op    = isset( $cond['op'] ) ? $cond['op'] : '';
		$value = isset( $cond['value'] ) ? $cond['value'] : '';
		$name  = 'groups[' . $g . '][' . $c . ']';
		$uid   = 'sfrs-c-' . $g . '-' . $c;
		?>
		<div class="sfrs-condition" data-condition>
			<div class="sfrs-condition__main">
				<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>-type"><?php esc_html_e( 'Condition type', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label>
				<select id="<?php echo esc_attr( $uid ); ?>-type" name="<?php echo esc_attr( $name ); ?>[type]" data-role="type">
					<?php foreach ( $types as $key => $t ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $type, $key ); ?>><?php echo esc_html( $t['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>-op"><?php esc_html_e( 'Operator', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label>
				<select id="<?php echo esc_attr( $uid ); ?>-op" name="<?php echo esc_attr( $name ); ?>[op]" data-role="op">
					<?php foreach ( $types[ $type ]['ops'] as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $op, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<span class="sfrs-value" data-for="cart_subtotal" <?php echo 'cart_subtotal' === $type ? '' : 'hidden'; ?>>
					<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>-amount"><?php esc_html_e( 'Subtotal amount', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label>
					<input type="text" inputmode="decimal" class="small-text" id="<?php echo esc_attr( $uid ); ?>-amount" name="<?php echo esc_attr( $name ); ?>[amount]" value="<?php echo esc_attr( 'cart_subtotal' === $type ? (string) $value : '' ); ?>" placeholder="0.00">
				</span>
				<span class="sfrs-value" data-for="cart_qty" <?php echo 'cart_qty' === $type ? '' : 'hidden'; ?>>
					<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>-number"><?php esc_html_e( 'Quantity', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label>
					<input type="number" min="0" step="1" class="small-text" id="<?php echo esc_attr( $uid ); ?>-number" name="<?php echo esc_attr( $name ); ?>[number]" value="<?php echo esc_attr( 'cart_qty' === $type ? (string) $value : '' ); ?>">
				</span>
				<span class="sfrs-value sfrs-value--wide" data-for="products" <?php echo 'products' === $type ? '' : 'hidden'; ?>>
					<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>-products"><?php esc_html_e( 'Products', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label>
					<select id="<?php echo esc_attr( $uid ); ?>-products" class="wc-product-search" multiple="multiple" style="width:100%" name="<?php echo esc_attr( $name ); ?>[products][]" data-placeholder="<?php esc_attr_e( 'Search for a product or variation…', 'smart-flat-rate-shipping-for-woocommerce' ); ?>" data-action="woocommerce_json_search_products_and_variations">
						<?php
						if ( 'products' === $type && is_array( $value ) ) {
							foreach ( $value as $pid ) {
								$product = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
								$text    = $product ? wp_strip_all_tags( $product->get_formatted_name() ) : sprintf( /* translators: %d: ID. */ __( '#%d (deleted)', 'smart-flat-rate-shipping-for-woocommerce' ), $pid );
								echo '<option value="' . esc_attr( $pid ) . '" selected="selected">' . esc_html( $text ) . '</option>';
							}
						}
						?>
					</select>
				</span>
				<span class="sfrs-value sfrs-value--wide" data-for="categories" <?php echo 'categories' === $type ? '' : 'hidden'; ?>>
					<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>-categories"><?php esc_html_e( 'Categories', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label>
					<select id="<?php echo esc_attr( $uid ); ?>-categories" class="wc-enhanced-select" multiple="multiple" style="width:100%" name="<?php echo esc_attr( $name ); ?>[categories][]" data-placeholder="<?php esc_attr_e( 'Select categories…', 'smart-flat-rate-shipping-for-woocommerce' ); ?>">
						<?php
						$selected = 'categories' === $type && is_array( $value ) ? array_map( 'intval', $value ) : array();
						foreach ( self::category_options() as $term_id => $term_name ) {
							echo '<option value="' . esc_attr( $term_id ) . '"' . selected( in_array( $term_id, $selected, true ), true, false ) . '>' . esc_html( $term_name ) . '</option>';
						}
						foreach ( array_diff( $selected, array_keys( self::category_options() ) ) as $gone ) {
							/* translators: %d: ID. */
							echo '<option value="' . esc_attr( $gone ) . '" selected="selected">' . esc_html( sprintf( __( '#%d (deleted)', 'smart-flat-rate-shipping-for-woocommerce' ), $gone ) ) . '</option>';
						}
						?>
					</select>
				</span>
				<span class="sfrs-value sfrs-value--min" data-for-min="products,categories" <?php echo in_array( $type, array( 'products', 'categories' ), true ) && 'none' !== $op ? '' : 'hidden'; ?>>
					<label for="<?php echo esc_attr( $uid ); ?>-min"><?php esc_html_e( 'Min. quantity', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label>
					<input type="number" min="1" step="1" class="small-text" id="<?php echo esc_attr( $uid ); ?>-min" name="<?php echo esc_attr( $name ); ?>[min_qty]" value="<?php echo esc_attr( isset( $cond['min_qty'] ) ? (string) $cond['min_qty'] : '1' ); ?>">
				</span>
				<span class="sfrs-value sfrs-value--wide" data-for="country" <?php echo 'country' === $type ? '' : 'hidden'; ?>>
					<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>-countries"><?php esc_html_e( 'Countries', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label>
					<select id="<?php echo esc_attr( $uid ); ?>-countries" class="wc-enhanced-select" multiple="multiple" style="width:100%" name="<?php echo esc_attr( $name ); ?>[countries][]" data-placeholder="<?php esc_attr_e( 'Select countries…', 'smart-flat-rate-shipping-for-woocommerce' ); ?>">
						<?php
						$chosen = 'country' === $type && is_array( $value ) ? $value : array();
						foreach ( WC()->countries->get_countries() as $code => $country_name ) {
							echo '<option value="' . esc_attr( $code ) . '"' . selected( in_array( $code, $chosen, true ), true, false ) . '>' . esc_html( $country_name ) . '</option>';
						}
						?>
					</select>
				</span>
				<span class="sfrs-value sfrs-value--wide" data-for="state" <?php echo 'state' === $type ? '' : 'hidden'; ?>>
					<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>-states"><?php esc_html_e( 'States', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label>
					<textarea id="<?php echo esc_attr( $uid ); ?>-states" rows="2" name="<?php echo esc_attr( $name ); ?>[states]" placeholder="IN:GJ, IN:MH"><?php echo esc_textarea( 'state' === $type && is_array( $value ) ? implode( ', ', $value ) : '' ); ?></textarea>
				</span>
				<span class="sfrs-value sfrs-value--wide" data-for="postcode" <?php echo 'postcode' === $type ? '' : 'hidden'; ?>>
					<label class="screen-reader-text" for="<?php echo esc_attr( $uid ); ?>-postcodes"><?php esc_html_e( 'Postcodes', 'smart-flat-rate-shipping-for-woocommerce' ); ?></label>
					<textarea id="<?php echo esc_attr( $uid ); ?>-postcodes" rows="2" name="<?php echo esc_attr( $name ); ?>[postcodes]" placeholder="380001...380060, 3900*, 395007"><?php echo esc_textarea( 'postcode' === $type && is_array( $value ) ? implode( ', ', $value ) : '' ); ?></textarea>
				</span>
				<?php
				/**
				 * Add-on condition value inputs. Wrap them in a span with data-for="{type}".
				 *
				 * @param string $type  Current type.
				 * @param string $op    Current operator.
				 * @param mixed  $value Current value.
				 * @param array  $cond  Whole condition.
				 * @param string $name  Field name prefix, e.g. groups[0][1].
				 * @param string $uid   Unique id prefix.
				 */
				do_action( 'sfrs_condition_value_fields', $type, $op, $value, $cond, $name, $uid );
				?>
			</div>
			<button type="button" class="button-link sfrs-remove-condition" data-action="remove-condition"><span aria-hidden="true">&times;</span><span class="screen-reader-text"><?php esc_html_e( 'Remove condition', 'smart-flat-rate-shipping-for-woocommerce' ); ?></span></button>
		</div>
		<?php
	}

	/**
	 * Render a group (also used with placeholders for the JS template).
	 *
	 * @param string|int $g          Group index or placeholder.
	 * @param array      $conditions Conditions.
	 * @param string     $number     Visible group number.
	 */
	public static function group_block( $g, array $conditions, $number ) {
		?>
		<fieldset class="sfrs-group" data-group>
			<legend class="sfrs-group__title"><span data-group-number><?php echo esc_html( $number ); ?></span></legend>
			<div class="sfrs-conditions" data-conditions>
				<?php
				$c = 0;
				foreach ( $conditions as $cond ) {
					self::condition_row( $g, $c, is_array( $cond ) ? $cond : array() );
					++$c;
				}
				?>
			</div>
			<p class="sfrs-group__actions">
				<button type="button" class="button" data-action="add-condition"><?php esc_html_e( 'Add condition (AND)', 'smart-flat-rate-shipping-for-woocommerce' ); ?></button>
				<button type="button" class="button-link button-link-delete" data-action="remove-group"><?php esc_html_e( 'Remove group', 'smart-flat-rate-shipping-for-woocommerce' ); ?></button>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Category options: id => hierarchical name.
	 *
	 * @return array
	 */
	public static function category_options() {
		static $options = null;
		if ( null !== $options ) {
			return $options;
		}
		$options = array();
		$terms   = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name', 'number' => 1000 ) );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$ancestors = array_reverse( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) );
				$path      = array();
				foreach ( $ancestors as $ancestor ) {
					$parent = get_term( $ancestor, 'product_cat' );
					if ( $parent && ! is_wp_error( $parent ) ) {
						$path[] = $parent->name;
					}
				}
				$path[]                       = $term->name;
				$options[ (int) $term->term_id ] = html_entity_decode( implode( ' › ', $path ), ENT_QUOTES, 'UTF-8' );
			}
		}
		asort( $options );
		return $options;
	}
}
