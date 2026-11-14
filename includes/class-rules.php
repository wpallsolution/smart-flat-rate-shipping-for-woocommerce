<?php
/**
 * Storage, validation and summaries for shipping rules ("methods").
 *
 * Storage: one private (non-public, no UI) custom post type entry per method, with
 * the configuration in a single versioned post-meta array. Rationale: WooCommerce
 * shipping-zone instances only hold flat key/value settings and are tied to one zone,
 * which cannot express multi-group conditions, simulation of disabled methods, or one
 * list ordered by priority across zones. A custom post type needs no custom tables,
 * gives stable IDs and pagination, and survives plugin deactivation.
 * WooCommerce still owns the shipping lifecycle: a single native shipping method
 * (Sfrs_Shipping_Method) is added to zones and emits one rate per matching rule.
 *
 * @package Smart_Flat_Rate_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Sfrs_Rules {

	const POST_TYPE = 'sfrs_rule';
	const SCHEMA    = 1;
	const MAX_GROUPS     = 10;
	const MAX_CONDITIONS = 10;
	const MAX_LIST       = 500;

	/**
	 * Request-level cache of active rules.
	 *
	 * @var array|null
	 */
	protected static $active = null;

	/**
	 * Register the private post type.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array( 'name' => __( 'Flat rate shipping methods', 'smart-flat-rate-shipping-for-woocommerce' ) ),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
				'can_export'          => true,
			)
		);
	}

	/**
	 * Default configuration.
	 *
	 * @return array
	 */
	public static function defaults() {
		$defaults = array(
			'id'             => 0,
			'name'           => '',
			'label'          => '',
			'description'    => '',
			'enabled'        => true,
			'priority'       => 10,
			'base_fee'       => '0',
			'per_item_fee'   => '0',
			'qty_scope'      => 'cart',
			'free_threshold' => '',
			'tax_status'     => 'taxable',
			'groups'         => array(),
		);
		return apply_filters( 'sfrs_rule_defaults', $defaults );
	}

	/**
	 * Fill in missing keys and coerce types of a stored configuration, so malformed or
	 * older saved data never causes warnings.
	 *
	 * @param mixed $raw Stored value.
	 * @param int   $id  Rule ID.
	 * @return array
	 */
	public static function normalize( $raw, $id = 0 ) {
		$c = array_merge( self::defaults(), is_array( $raw ) ? $raw : array() );
		$c['id']             = (int) $id;
		$c['name']           = (string) $c['name'];
		$c['label']          = (string) $c['label'];
		$c['description']    = (string) $c['description'];
		$c['enabled']        = ! empty( $c['enabled'] );
		$c['priority']       = (int) $c['priority'];
		$c['base_fee']       = is_numeric( $c['base_fee'] ) ? (string) $c['base_fee'] : '0';
		$c['per_item_fee']   = is_numeric( $c['per_item_fee'] ) ? (string) $c['per_item_fee'] : '0';
		$c['qty_scope']      = 'eligible' === $c['qty_scope'] ? 'eligible' : 'cart';
		$c['free_threshold'] = is_numeric( $c['free_threshold'] ) ? (string) $c['free_threshold'] : '';
		$c['tax_status']     = 'none' === $c['tax_status'] ? 'none' : 'taxable';
		$c['groups']         = is_array( $c['groups'] ) ? $c['groups'] : array();
		return $c;
	}

	/**
	 * Load one rule.
	 *
	 * @param int $id Post ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		$id   = absint( $id );
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$rule         = self::normalize( get_post_meta( $id, '_sfrs_config', true ), $id );
		$rule['name'] = $post->post_title;
		return $rule;
	}

	/**
	 * Enabled rules ordered by priority (lowest first) then ID. Cached per request.
	 *
	 * @return array[]
	 */
	public static function get_active() {
		if ( null !== self::$active ) {
			return self::$active;
		}
		$ids = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => 200,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'meta_key'         => '_sfrs_enabled', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => '1',             // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		$rules = array();
		foreach ( $ids as $id ) {
			$rule = self::get( $id );
			if ( $rule && $rule['enabled'] ) {
				$rules[] = $rule;
			}
		}
		usort(
			$rules,
			function ( $a, $b ) {
				return $a['priority'] === $b['priority'] ? $a['id'] - $b['id'] : $a['priority'] - $b['priority'];
			}
		);
		self::$active = $rules;
		return $rules;
	}

	/**
	 * Every rule (enabled or not) in priority order, for the simulator.
	 *
	 * @return array[]
	 */
	public static function get_all() {
		$ids   = get_posts(
			array(
				'post_type'     => self::POST_TYPE,
				'post_status'   => 'publish',
				'numberposts'   => 200,
				'fields'        => 'ids',
				'no_found_rows' => true,
			)
		);
		$rules = array_filter( array_map( array( __CLASS__, 'get' ), $ids ) );
		usort(
			$rules,
			function ( $a, $b ) {
				return $a['priority'] === $b['priority'] ? $a['id'] - $b['id'] : $a['priority'] - $b['priority'];
			}
		);
		return $rules;
	}

	/**
	 * Paginated list for the admin.
	 *
	 * @param int $page     Page.
	 * @param int $per_page Per page.
	 * @return array { rules, total, pages }
	 */
	public static function paginate( $page, $per_page = 20 ) {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'paged'          => max( 1, (int) $page ),
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
		$rules = array_filter( array_map( array( __CLASS__, 'get' ), $query->posts ) );
		return array( 'rules' => array_values( $rules ), 'total' => (int) $query->found_posts, 'pages' => (int) $query->max_num_pages );
	}

	/**
	 * Save a validated configuration.
	 *
	 * @param array $config Config from sanitize_request().
	 * @param int   $id     Existing ID, or 0 to create.
	 * @return int|WP_Error
	 */
	public static function save( array $config, $id = 0 ) {
		$id   = absint( $id );
		$post = array(
			'post_type'   => self::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => wp_slash( $config['name'] ),
		);
		if ( $id ) {
			$existing = get_post( $id );
			if ( ! $existing || self::POST_TYPE !== $existing->post_type ) {
				return new WP_Error( 'sfrs_not_found', __( 'That shipping method does not exist.', 'smart-flat-rate-shipping-for-woocommerce' ) );
			}
			$post['ID'] = $id;
			$id         = wp_update_post( $post, true );
		} else {
			$id = wp_insert_post( $post, true );
		}
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		unset( $config['id'] );
		update_post_meta( $id, '_sfrs_config', wp_slash( $config ) );
		update_post_meta( $id, '_sfrs_enabled', $config['enabled'] ? '1' : '0' );
		update_post_meta( $id, '_sfrs_schema', self::SCHEMA );
		self::flush();
		return (int) $id;
	}

	/**
	 * Delete a rule permanently.
	 *
	 * @param int $id ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		$post = get_post( absint( $id ) );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}
		$done = (bool) wp_delete_post( $post->ID, true );
		self::flush();
		return $done;
	}

	/**
	 * Duplicate a rule. The copy is disabled so it cannot unexpectedly appear at checkout.
	 *
	 * @param int $id Source ID.
	 * @return int|WP_Error
	 */
	public static function duplicate( $id ) {
		$rule = self::get( $id );
		if ( ! $rule ) {
			return new WP_Error( 'sfrs_not_found', __( 'That shipping method does not exist.', 'smart-flat-rate-shipping-for-woocommerce' ) );
		}
		unset( $rule['id'] );
		/* translators: %s: method name. */
		$rule['name']    = sprintf( __( '%s (copy)', 'smart-flat-rate-shipping-for-woocommerce' ), $rule['name'] );
		$rule['enabled'] = false;
		return self::save( $rule );
	}

	/**
	 * Clear request caches.
	 */
	public static function flush() {
		self::$active = null;
		self::invalidate_shipping_cache();
	}

	/**
	 * WooCommerce caches calculated rates per package in the customer session. Bump the
	 * shipping cache version so editing or deleting a method takes effect immediately
	 * instead of leaving customers with stale rates.
	 */
	public static function invalidate_shipping_cache() {
		if ( class_exists( 'WC_Cache_Helper' ) ) {
			WC_Cache_Helper::get_transient_version( 'shipping', true );
		}
	}

	/**
	 * Validate and sanitise the edit form. Never reinterprets a malformed rule: any
	 * problem is reported and the rule is not saved.
	 *
	 * @param array $src Unslashed request data.
	 * @return array { config, errors[] }
	 */
	public static function sanitize_request( array $src ) {
		$errors = array();
		$c      = self::defaults();

		$c['name'] = sanitize_text_field( isset( $src['name'] ) ? (string) $src['name'] : '' );
		if ( '' === $c['name'] ) {
			$errors[] = __( 'Enter an internal name for the method.', 'smart-flat-rate-shipping-for-woocommerce' );
		}
		$c['label'] = sanitize_text_field( isset( $src['label'] ) ? (string) $src['label'] : '' );
		if ( '' === $c['label'] ) {
			$errors[] = __( 'Enter the shipping label customers will see.', 'smart-flat-rate-shipping-for-woocommerce' );
		}
		foreach ( array( 'name', 'label' ) as $field ) {
			if ( function_exists( 'mb_strlen' ) && mb_strlen( $c[ $field ] ) > 120 ) {
				$errors[] = __( 'The name and the label must be 120 characters or fewer.', 'smart-flat-rate-shipping-for-woocommerce' );
				break;
			}
		}
		$c['description'] = sanitize_text_field( isset( $src['description'] ) ? (string) $src['description'] : '' );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $c['description'] ) > 300 ) {
			$errors[] = __( 'The description must be 300 characters or fewer.', 'smart-flat-rate-shipping-for-woocommerce' );
		}
		$c['enabled'] = ! empty( $src['enabled'] );

		$priority = isset( $src['priority'] ) ? trim( (string) $src['priority'] ) : '';
		if ( '' === $priority ) {
			$c['priority'] = 10;
		} elseif ( preg_match( '/^\d{1,4}$/', $priority ) ) {
			$c['priority'] = (int) $priority;
		} else {
			$c['priority'] = $priority;
			$errors[]      = __( 'Priority must be a whole number between 0 and 9999.', 'smart-flat-rate-shipping-for-woocommerce' );
		}

		$c['base_fee']     = self::money_field( $src, 'base_fee', __( 'Base fee', 'smart-flat-rate-shipping-for-woocommerce' ), true, $errors );
		$c['per_item_fee'] = self::money_field( $src, 'per_item_fee', __( 'Per-item fee', 'smart-flat-rate-shipping-for-woocommerce' ), true, $errors );

		$c['qty_scope'] = isset( $src['qty_scope'] ) && 'eligible' === $src['qty_scope'] ? 'eligible' : 'cart';

		$threshold = isset( $src['free_threshold'] ) ? trim( (string) $src['free_threshold'] ) : '';
		if ( '' === $threshold ) {
			$c['free_threshold'] = '';
		} else {
			$amount = sfrs_parse_amount( $threshold );
			if ( null === $amount || $amount < 0 ) {
				$c['free_threshold'] = $threshold;
				$errors[]            = __( 'Free-shipping threshold must be a number greater than zero, or left blank.', 'smart-flat-rate-shipping-for-woocommerce' );
			} elseif ( 0.0 === $amount ) {
				$c['free_threshold'] = $threshold;
				$errors[]            = __( 'Free-shipping threshold of zero is not allowed. Leave it blank to disable it, or set the method fee to zero for always-free shipping.', 'smart-flat-rate-shipping-for-woocommerce' );
			} else {
				$c['free_threshold'] = sfrs_store_amount( $amount );
			}
		}

		$c['tax_status'] = isset( $src['tax_status'] ) && 'none' === $src['tax_status'] ? 'none' : 'taxable';
		$c['groups']     = self::sanitize_groups( isset( $src['groups'] ) ? $src['groups'] : array(), $errors );

		/**
		 * Lets an add-on validate its own rule fields.
		 *
		 * @param array $result { config, errors[] }.
		 * @param array $src    Unslashed request data.
		 */
		return apply_filters( 'sfrs_sanitize_request', array( 'config' => $c, 'errors' => $errors ), $src );
	}

	/**
	 * Parse a money field.
	 *
	 * @param array  $src      Source.
	 * @param string $key      Field key.
	 * @param string $label    Field label for errors.
	 * @param bool   $blank_ok Blank means zero.
	 * @param array  $errors   Errors (by reference).
	 * @return string
	 */
	protected static function money_field( array $src, $key, $label, $blank_ok, array &$errors ) {
		$raw = isset( $src[ $key ] ) ? trim( (string) $src[ $key ] ) : '';
		if ( '' === $raw && $blank_ok ) {
			return '0';
		}
		$amount = sfrs_parse_amount( $raw );
		if ( null === $amount ) {
			/* translators: %s: field label. */
			$errors[] = sprintf( __( '%s must be a number. Use "." as the decimal separator and no thousands separators.', 'smart-flat-rate-shipping-for-woocommerce' ), $label );
			return $raw;
		}
		if ( $amount < 0 ) {
			/* translators: %s: field label. */
			$errors[] = sprintf( __( '%s cannot be negative.', 'smart-flat-rate-shipping-for-woocommerce' ), $label );
			return $raw;
		}
		return sfrs_store_amount( $amount );
	}

	/**
	 * Parse and validate condition groups.
	 *
	 * @param mixed $raw    Raw groups.
	 * @param array $errors Errors (by reference).
	 * @return array
	 */
	protected static function sanitize_groups( $raw, array &$errors ) {
		$out = array();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		if ( count( $raw ) > self::MAX_GROUPS ) {
			/* translators: %d: maximum. */
			$errors[] = sprintf( __( 'A method can have at most %d condition groups.', 'smart-flat-rate-shipping-for-woocommerce' ), self::MAX_GROUPS );
			$raw      = array_slice( $raw, 0, self::MAX_GROUPS, true );
		}
		$g = 0;
		foreach ( $raw as $conditions ) {
			++$g;
			if ( ! is_array( $conditions ) || ! $conditions ) {
				/* translators: %d: group number. */
				$errors[] = sprintf( __( 'Condition group %d is empty. Add a condition or remove the group.', 'smart-flat-rate-shipping-for-woocommerce' ), $g );
				$out[]    = array();
				continue;
			}
			if ( count( $conditions ) > self::MAX_CONDITIONS ) {
				/* translators: 1: group number, 2: maximum. */
				$errors[]   = sprintf( __( 'Condition group %1$d can have at most %2$d conditions.', 'smart-flat-rate-shipping-for-woocommerce' ), $g, self::MAX_CONDITIONS );
				$conditions = array_slice( $conditions, 0, self::MAX_CONDITIONS, true );
			}
			$group = array();
			$n     = 0;
			foreach ( $conditions as $condition ) {
				++$n;
				$condition = is_array( $condition ) ? $condition : array();
				$before    = count( $errors );
				$parsed    = self::sanitize_condition( $condition, $g, $n, $errors );
				if ( $parsed ) {
					$group[] = $parsed;
				} elseif ( count( $errors ) > $before ) {
					// Keep the invalid condition so the form shows it again next to its error.
					// It is never saved: any error blocks saving the whole method.
					$group[] = self::draft_condition( $condition );
				}
			}
			$out[] = $group;
		}
		return $out;
	}

	/**
	 * Parse and validate one condition.
	 *
	 * @param array $in     Raw condition.
	 * @param int   $g      Group number (for messages).
	 * @param int   $n      Condition number (for messages).
	 * @param array $errors Errors (by reference).
	 * @return array|null
	 */
	protected static function sanitize_condition( array $in, $g, $n, array &$errors ) {
		$types = Sfrs_Condition_Evaluator::types();
		$type  = isset( $in['type'] ) ? (string) $in['type'] : '';
		$op    = isset( $in['op'] ) ? (string) $in['op'] : '';
		/* translators: 1: group number, 2: condition number. */
		$where = sprintf( __( 'Group %1$d, condition %2$d:', 'smart-flat-rate-shipping-for-woocommerce' ), $g, $n );

		if ( ! isset( $types[ $type ] ) ) {
			$errors[] = $where . ' ' . __( 'choose a condition type.', 'smart-flat-rate-shipping-for-woocommerce' );
			return null;
		}
		if ( ! isset( $types[ $type ]['ops'][ $op ] ) ) {
			$errors[] = $where . ' ' . __( 'choose a valid operator.', 'smart-flat-rate-shipping-for-woocommerce' );
			return null;
		}
		$cond = array( 'type' => $type, 'op' => $op );

		switch ( $type ) {
			case 'cart_subtotal':
				$amount = sfrs_parse_amount( isset( $in['amount'] ) ? $in['amount'] : '' );
				if ( null === $amount || $amount < 0 ) {
					$errors[] = $where . ' ' . __( 'enter the subtotal as a number of zero or more.', 'smart-flat-rate-shipping-for-woocommerce' );
					return null;
				}
				$cond['value'] = sfrs_store_amount( $amount );
				break;

			case 'cart_qty':
				$number = isset( $in['number'] ) ? trim( (string) $in['number'] ) : '';
				if ( ! preg_match( '/^\d{1,6}$/', $number ) ) {
					$errors[] = $where . ' ' . __( 'enter the quantity as a whole number.', 'smart-flat-rate-shipping-for-woocommerce' );
					return null;
				}
				$cond['value'] = (int) $number;
				break;

			case 'products':
				$ids = self::id_list( isset( $in['products'] ) ? $in['products'] : array() );
				if ( ! $ids ) {
					$errors[] = $where . ' ' . __( 'select at least one product.', 'smart-flat-rate-shipping-for-woocommerce' );
					return null;
				}
				foreach ( $ids as $id ) {
					if ( ! in_array( get_post_type( $id ), array( 'product', 'product_variation' ), true ) ) {
						/* translators: %d: product ID. */
						$errors[] = $where . ' ' . sprintf( __( 'product %d does not exist.', 'smart-flat-rate-shipping-for-woocommerce' ), $id );
						return null;
					}
				}
				$cond['value'] = $ids;
				break;

			case 'categories':
				$ids = self::id_list( isset( $in['categories'] ) ? $in['categories'] : array() );
				if ( ! $ids ) {
					$errors[] = $where . ' ' . __( 'select at least one category.', 'smart-flat-rate-shipping-for-woocommerce' );
					return null;
				}
				foreach ( $ids as $id ) {
					$term = get_term( $id, 'product_cat' );
					if ( ! $term || is_wp_error( $term ) ) {
						/* translators: %d: category ID. */
						$errors[] = $where . ' ' . sprintf( __( 'category %d does not exist.', 'smart-flat-rate-shipping-for-woocommerce' ), $id );
						return null;
					}
				}
				$cond['value'] = $ids;
				break;

			case 'country':
				$codes     = isset( $in['countries'] ) && is_array( $in['countries'] ) ? array_unique( array_map( 'strtoupper', array_map( 'sanitize_text_field', $in['countries'] ) ) ) : array();
				$countries = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_countries() : array();
				if ( ! $codes ) {
					$errors[] = $where . ' ' . __( 'select at least one country.', 'smart-flat-rate-shipping-for-woocommerce' );
					return null;
				}
				foreach ( $codes as $code ) {
					if ( ! preg_match( '/^[A-Z]{2}$/', $code ) || ( $countries && ! isset( $countries[ $code ] ) ) ) {
						/* translators: %s: country code. */
						$errors[] = $where . ' ' . sprintf( __( '"%s" is not a valid country.', 'smart-flat-rate-shipping-for-woocommerce' ), $code );
						return null;
					}
				}
				$cond['value'] = array_values( $codes );
				break;

			case 'state':
				$list = array_map( 'strtoupper', sfrs_split_list( isset( $in['states'] ) ? $in['states'] : '' ) );
				if ( ! $list ) {
					$errors[] = $where . ' ' . __( 'enter at least one state, e.g. IN:GJ.', 'smart-flat-rate-shipping-for-woocommerce' );
					return null;
				}
				if ( count( $list ) > self::MAX_LIST ) {
					$errors[] = $where . ' ' . __( 'too many entries.', 'smart-flat-rate-shipping-for-woocommerce' );
					return null;
				}
				foreach ( $list as $entry ) {
					if ( ! preg_match( '/^[A-Z]{2}:[A-Z0-9_\-]{1,10}$/', $entry ) ) {
						/* translators: %s: entry. */
						$errors[] = $where . ' ' . sprintf( __( '"%s" is not valid. Use COUNTRY:STATE, e.g. IN:GJ or US:CA.', 'smart-flat-rate-shipping-for-woocommerce' ), $entry );
						return null;
					}
				}
				$cond['value'] = $list;
				break;

			case 'postcode':
				$list = array_map( 'sfrs_normalize_postcode', sfrs_split_list( isset( $in['postcodes'] ) ? $in['postcodes'] : '' ) );
				$list = array_values( array_unique( array_filter( $list ) ) );
				if ( ! $list ) {
					$errors[] = $where . ' ' . __( 'enter at least one postcode.', 'smart-flat-rate-shipping-for-woocommerce' );
					return null;
				}
				if ( count( $list ) > self::MAX_LIST ) {
					$errors[] = $where . ' ' . __( 'too many entries.', 'smart-flat-rate-shipping-for-woocommerce' );
					return null;
				}
				foreach ( $list as $entry ) {
					if ( ! Sfrs_Condition_Evaluator::is_valid_postcode_pattern( $entry ) ) {
						/* translators: %s: entry. */
						$errors[] = $where . ' ' . sprintf( __( '"%s" is not a valid postcode pattern. Use an exact postcode, a trailing wildcard (380*) or a numeric range (380001...380060).', 'smart-flat-rate-shipping-for-woocommerce' ), $entry );
						return null;
					}
				}
				$cond['value'] = $list;
				break;

			default:
				// Add-on condition types: the add-on validates and returns { value, ..., errors[] }.
				$custom = apply_filters( 'sfrs_sanitize_condition', null, $type, $op, $in );
				if ( ! is_array( $custom ) ) {
					$errors[] = $where . ' ' . __( 'this condition type is not available.', 'smart-flat-rate-shipping-for-woocommerce' );
					return null;
				}
				if ( ! empty( $custom['errors'] ) ) {
					foreach ( (array) $custom['errors'] as $message ) {
						$errors[] = $where . ' ' . $message;
					}
					return null;
				}
				unset( $custom['errors'] );
				$cond = array_merge( $cond, $custom );
		}

		if ( in_array( $type, array( 'products', 'categories' ), true ) ) {
			$min             = isset( $in['min_qty'] ) ? trim( (string) $in['min_qty'] ) : '';
			$cond['min_qty'] = 1;
			if ( 'any' === $op && '' !== $min ) {
				if ( ! preg_match( '/^\d{1,6}$/', $min ) || (int) $min < 1 ) {
					$errors[] = $where . ' ' . __( 'the minimum quantity must be a whole number of 1 or more.', 'smart-flat-rate-shipping-for-woocommerce' );
					return null;
				}
				$cond['min_qty'] = (int) $min;
			}
		}
		return $cond;
	}

	/**
	 * Re-displayable form of a condition that failed validation.
	 *
	 * @param array $in Raw condition.
	 * @return array
	 */
	protected static function draft_condition( array $in ) {
		$types = Sfrs_Condition_Evaluator::types();
		$type  = isset( $in['type'] ) && isset( $types[ $in['type'] ] ) ? $in['type'] : 'cart_subtotal';
		$op    = isset( $in['op'] ) && isset( $types[ $type ]['ops'][ $in['op'] ] ) ? $in['op'] : (string) key( $types[ $type ]['ops'] );
		$draft = array( 'type' => $type, 'op' => $op, 'value' => '' );
		switch ( $type ) {
			case 'cart_subtotal':
				$draft['value'] = isset( $in['amount'] ) ? (string) $in['amount'] : '';
				break;
			case 'cart_qty':
				$draft['value'] = isset( $in['number'] ) ? (string) $in['number'] : '';
				break;
			case 'products':
				$draft['value'] = self::id_list( isset( $in['products'] ) ? $in['products'] : array() );
				break;
			case 'categories':
				$draft['value'] = self::id_list( isset( $in['categories'] ) ? $in['categories'] : array() );
				break;
			case 'country':
				$draft['value'] = isset( $in['countries'] ) && is_array( $in['countries'] ) ? array_map( 'sanitize_text_field', $in['countries'] ) : array();
				break;
			case 'state':
				$draft['value'] = sfrs_split_list( isset( $in['states'] ) ? $in['states'] : '' );
				break;
			case 'postcode':
				$draft['value'] = sfrs_split_list( isset( $in['postcodes'] ) ? $in['postcodes'] : '' );
				break;
			default:
				$draft = apply_filters( 'sfrs_draft_condition', $draft, $type, $in );
		}
		if ( in_array( $type, array( 'products', 'categories' ), true ) ) {
			$draft['min_qty'] = isset( $in['min_qty'] ) ? sanitize_text_field( $in['min_qty'] ) : '1';
		}
		return $draft;
	}

	/**
	 * Clean a list of IDs.
	 *
	 * @param mixed $raw Raw list.
	 * @return int[]
	 */
	protected static function id_list( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		return array_slice( array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) ), 0, self::MAX_LIST );
	}

	/**
	 * Readable one-line summary of the whole rule's conditions.
	 *
	 * @param array $rule Rule.
	 * @return string
	 */
	public static function conditions_summary( array $rule ) {
		if ( empty( $rule['groups'] ) ) {
			return __( 'Always (no conditions)', 'smart-flat-rate-shipping-for-woocommerce' );
		}
		$parts = array();
		foreach ( $rule['groups'] as $group ) {
			if ( ! is_array( $group ) || ! $group ) {
				$parts[] = __( '(empty group, never matches)', 'smart-flat-rate-shipping-for-woocommerce' );
				continue;
			}
			$parts[] = implode( ' ' . __( 'AND', 'smart-flat-rate-shipping-for-woocommerce' ) . ' ', array_map( array( 'Sfrs_Condition_Evaluator', 'describe' ), $group ) );
		}
		return count( $parts ) > 1
			? '(' . implode( ') ' . __( 'OR', 'smart-flat-rate-shipping-for-woocommerce' ) . ' (', $parts ) . ')'
			: $parts[0];
	}

	/**
	 * Readable price summary.
	 *
	 * @param array $rule Rule.
	 * @return string
	 */
	public static function price_summary( array $rule ) {
		$text = sfrs_money_text( (float) $rule['base_fee'] );
		if ( (float) $rule['per_item_fee'] > 0 ) {
			/* translators: %s: amount. */
			$text .= ' + ' . sprintf( __( '%s per item', 'smart-flat-rate-shipping-for-woocommerce' ), sfrs_money_text( (float) $rule['per_item_fee'] ) );
		}
		if ( '' !== $rule['free_threshold'] ) {
			/* translators: %s: amount. */
			$text .= '; ' . sprintf( __( 'free from %s', 'smart-flat-rate-shipping-for-woocommerce' ), sfrs_money_text( (float) $rule['free_threshold'] ) );
		}
		return apply_filters( 'sfrs_price_summary', $text, $rule );
	}
}
