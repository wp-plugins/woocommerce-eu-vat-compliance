<?php

if (!defined('WC_EU_VAT_COMPLIANCE_DIR')) die('No direct access');

// Purpose: provide a report on VAT paid, to help with EU VAT compliance

class WC_EU_VAT_Compliance_Reports {

	// Public: is used in the CSV download code
	public $reporting_currency = '';
	public $last_rate_used = 1;
	private $fallback_conversion_rates = array();
	private $conversion_provider;
	private $at_least_22 = false;
	private $pre_wc22_order_parsed = false;

	public $start_date;
	public $end_date;

	public function __construct() {
		add_action('plugins_loaded', array($this, 'plugins_loaded'));
		add_action('admin_init', array($this, 'admin_init'));
		add_action('wc_eu_vat_compliance_cc_tab_reports', array($this, 'wc_eu_vat_compliance_cc_tab_reports'));
// 		add_action('wc_eu_vat_report_begin', array($this, 'wc_eu_vat_report_begin'), 10, 2);
	}

	// Hook into control centre
	public function wc_eu_vat_compliance_cc_tab_reports() {
		echo '<h2>'.__('EU VAT Report', 'wc_eu_vat_compliance').'</h2>';
		$this->wc_eu_vat_compliance_report();
	}

	public function plugins_loaded() {
		$this->at_least_22 = (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.2', '>=')) ? true : false;
	}

/*
	// Date range selection feature
	public function wc_eu_vat_report_begin($start_date, $end_date) {

		// N.B. WooCommerce takes care of attaching the datepicker to the inputs. (Not supported on WC 2.0)
		wp_enqueue_script( 'jquery-ui-datepicker', array( 'jquery' ), 1, true );

		_e('Date range:', 'wc_eu_vat_compliance');
		?>
		<input type="text" size="9" placeholder="yyyy-mm-dd" value="<?php if ( ! empty( $start_date ) ) echo esc_attr( $start_date ); ?>" name="start_date" class="range_datepicker from">
		<?php echo ' '.__('to', 'wc_eu_vat_compliance').' '; ?>
		<input type="text" size="9" placeholder="yyyy-mm-dd" value="<?php if ( ! empty( $end_date ) ) echo esc_attr( $end_date ); ?>" name="end_date" class="range_datepicker to">

		<input type="submit" name="wceuvat_go" class="button" style="height: 24px; line-height: 15px; margin-left: 6px;	" value="<?php _e('Update', 'wc_eu_vat_compliance' ); ?>">

		<?php
	}
*/

	public function admin_init() {

		if (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.1', '<')) {
			add_filter('woocommerce_reports_charts', array($this, 'eu_vat_report_wc20'));
		} else {
			add_filter('woocommerce_admin_reports', array($this, 'eu_vat_report'));
		}

	}

	public function admin_footer() {
		// Leave it as it is for now
		return;
		?>
		<style>
			.woocommerce-reports-wide .postbox {
				background-color: transparent;
			}
		</style>
		<?php
	}

	public function eu_vat_report_wc20($charts) {
		$charts['sales']['charts']['eu_vat_report'] = array(
			'title'       => __('EU VAT Report', 'wc_eu_vat_compliance'),
			'description' => '',
			'function'    => array($this, 'wc_eu_vat_compliance_report')
		);
		return $charts;
	}

	public function eu_vat_report($reports) {
		if (isset($reports['taxes'])) {
			$reports['taxes']['reports']['eu_vat_report'] = array(
				'title'       => __('EU VAT Report', 'wc_eu_vat_compliance'),
				'description' => '',
				'hide_title'  => false,
				'callback'    => array($this, 'wc_eu_vat_compliance_report')
			);
		}
		return $reports;
	}

	private function get_items_data($start_date, $end_date, $status) {

		// WC 2.2+ only
		if (!$this->at_least_22) return array();

		global $wpdb;

		$fetch_more = true;
		$page = 0;
		$page_size = 1000;

		$found_items = array();
		$final_results = array();

		while ($fetch_more) {
			$page_start = $page_size * $page;
			$results_this_time = 0;
			$sql = $this->get_items_sql($page_start, $page_size, $start_date, $end_date, $status);
			if (empty($sql)) break;

			$results = $wpdb->get_results($sql);
			if (!empty($results)) {
				$page++;

				foreach ($results as $r) {
					// Don't check on empty($r->v) - this causes orders 100% discounted (_line_total = 0) to be detected as non-WC-2.2 orders, as $current_total then never gets off (bool)false.
					if (empty($r->ID) || empty($r->k) || empty($r->oi)) continue;

					$current_order_id = $r->ID;
					$current_order_item_id = $r->oi;
					if (!isset($found_items[$current_order_id][$current_order_item_id])) {
						$current_total = false;
						$current_line_tax_data = false;
						$found_items[$current_order_id][$current_order_item_id] = true;
					}

					if ('_line_total' == $r->k) {
						$current_total = $r->v;
					} elseif ('_line_tax_data' == $r->k) {
						$current_line_tax_data = maybe_unserialize($r->v);
						// Don't skip - we want to know that some data was there (detecting pre-WC2.2 orders)
// 						if (empty($current_line_tax_data['total'])) continue;
					}

					if (false !== $current_total && is_array($current_line_tax_data)) {
						$total = $current_line_tax_data['total'];
						if (empty($total)) {
							// Record something - it's used to confirm that all orders had data, later
							if (!isset($final_results[$current_order_id])) $final_results[$current_order_id] = array();
						} else {
							foreach ($total as $tax_rate_id => $item_amount) {
	// 							if (!isset($final_results[$tax_rate_id])) $final_results[$tax_rate_id] = 0;
	// 							$final_results[$tax_rate_id] += $current_total;
								// Needs to still be broken down by ID so that it can then be linked back to country
								if (!isset($final_results[$current_order_id][$tax_rate_id])) $final_results[$current_order_id][$tax_rate_id] = 0;
								$final_results[$current_order_id][$tax_rate_id] += $current_total;
							}
						}
					}

				}
			} else {
				$fetch_more = false;
			}
			// Parse results further
			foreach ($found_items as $order_id => $order_items) {
				if (!isset($final_results[$order_id])) {
					$this->pre_wc22_order_parsed = true;
				}
			}
		}

		return $final_results;

	}

	// WC 2.2+ only (the _line_tax_data itemmeta only exists here)
	private function get_items_sql($page_start, $page_size, $start_date, $end_date, $status) {

		global $table_prefix, $wpdb;

		// '_order_tax_base_currency', '_order_total_base_currency', 
// 			,item_meta.meta_key
		$sql = "SELECT
			orders.ID
			,items.order_item_id AS oi
			,item_meta.meta_key AS k
			,item_meta.meta_value AS v
		FROM
			".$wpdb->posts." AS orders
		LEFT JOIN
			${table_prefix}woocommerce_order_items AS items ON
				(orders.id = items.order_id)
		LEFT JOIN
			${table_prefix}woocommerce_order_itemmeta AS item_meta ON
				(item_meta.order_item_id = items.order_item_id)
		WHERE
			(orders.post_type = 'shop_order')
			AND orders.post_status = 'wc-$status'
			AND orders.post_date >= '$start_date 00:00:00'
			AND orders.post_date <= '$end_date 23:59:59'
			AND items.order_item_type = 'line_item'
			AND item_meta.meta_key IN('_line_tax_data', '_line_total')
		ORDER BY oi ASC
		LIMIT $page_start, $page_size
		";

		if (!$sql) return false;

		return $sql;
	}

	// WC 2.2+ only (the _line_tax_data itemmeta only exists here, and order refunds were a new feature in 2.2)
	private function get_refunds_sql($page_start, $page_size, $start_date, $end_date, $order_status = false) {

		global $table_prefix, $wpdb;

// , '_refunded_item_id'

		// '_order_tax_base_currency', '_order_total_base_currency', 
// 			,item_meta.meta_key
// 			orders.ID
// 			,items.order_item_type AS ty

		$status_extra = ($order_status !== false) ? "\t\t\tAND orders.post_status = 'wc-$order_status'" : '';

		$sql = "SELECT
			orders.post_parent AS id
			,items.order_item_id AS oid
			,item_meta.meta_key AS k
			,item_meta.meta_value AS v
		FROM
			".$wpdb->posts." AS orders
		LEFT JOIN
			${table_prefix}woocommerce_order_items AS items ON
				(orders.id = items.order_id)
		LEFT JOIN
			${table_prefix}woocommerce_order_itemmeta AS item_meta ON
				(item_meta.order_item_id = items.order_item_id)
		WHERE
			(orders.post_type = 'shop_order_refund')
			AND orders.post_date >= '$start_date 00:00:00'
			$status_extra
			AND orders.post_date <= '$end_date 23:59:59'
			AND item_meta.meta_key IN('tax_amount', 'shipping_tax_amount', 'rate_id')
			AND items.order_item_type IN('tax')
			AND item_meta.meta_value != '0'
		ORDER BY oid ASC, v ASC
		";

		if ($page_start !== false && $page_size !== false) $sql .= "		LIMIT $page_start, $page_size";

		if (!$sql) return false;

		return $sql;
	}

	private function get_report_sql($page_start, $page_size, $start_date, $end_date, $tax_based_on_extra, $select_extra) {

		global $table_prefix, $wpdb;

		// Redundant, unless there are other statuses; and incompatible with plugins adding other statuses: AND (term.slug IN ('completed', 'processing', 'on-hold', 'pending', 'refunded', 'cancelled', 'failed'))
		if (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.2', '<')) {

		//'_order_tax_base_currency', '_order_total_base_currency',

			$sql = "SELECT
					orders.ID
					$select_extra
					,orders.post_date_gmt
					,order_meta.meta_key
					,order_meta.meta_value
					,term.slug AS order_status
				FROM
					".$wpdb->posts." AS orders
				LEFT JOIN
					".$wpdb->postmeta." AS order_meta ON
						(order_meta.post_id = orders.ID)
				LEFT JOIN
					".$wpdb->term_relationships." AS rel ON
						(rel.object_ID = orders.ID)
				LEFT JOIN
					".$wpdb->term_taxonomy." AS taxonomy ON
						(taxonomy.term_taxonomy_id = rel.term_taxonomy_id)
				LEFT JOIN
					".$wpdb->terms." AS term ON
						(term.term_id = taxonomy.term_id)
				WHERE
					(orders.post_type = 'shop_order')
					AND (orders.post_status = 'publish')
					AND (taxonomy.taxonomy = 'shop_order_status')
					AND orders.post_date >= '$start_date 00:00:00'
					AND orders.post_date <= '$end_date 23:59:59'
					AND order_meta.meta_key IN ('_billing_state', '_billing_country', '_order_currency', '_order_tax',  '_order_total', 'vat_compliance_country_info', 'vat_compliance_vat_paid', 'Valid EU VAT Number', 'VAT Number', 'VAT number validated', 'order_time_order_number' $tax_based_on_extra)
				ORDER BY
					orders.ID desc
					,order_meta.meta_key
				LIMIT $page_start, $page_size
			";
		} else {
				// '_order_tax_base_currency', '_order_total_base_currency', 
				$sql = "SELECT
					orders.ID
					$select_extra
					,orders.post_date_gmt
					,order_meta.meta_key
					,order_meta.meta_value
					,orders.post_status AS order_status
				FROM
					".$wpdb->posts." AS orders
				LEFT JOIN
					".$wpdb->postmeta." AS order_meta ON
						(order_meta.post_id = orders.ID)
				WHERE
					(orders.post_type = 'shop_order')
					AND orders.post_date >= '$start_date 00:00:00'
					AND orders.post_date <= '$end_date 23:59:59'
					AND order_meta.meta_key IN ('_billing_state', '_billing_country', '_order_currency', '_order_tax', '_order_total', 'vat_compliance_country_info', 'vat_compliance_vat_paid', 'Valid EU VAT Number', 'VAT Number', 'VAT number validated', 'order_time_order_number', 'wceuvat_conversion_rates' $tax_based_on_extra)
				ORDER BY
					orders.ID desc
					,order_meta.meta_key
				LIMIT $page_start, $page_size
			";
		}

		if (!$sql) return false;

		return $sql;
	}

	// We assume that the total number of refunds won't be enough to cause memory problems - so, we just get them all and then filter them afterwards
	// Returns an array of arrays of arrays: keys: $order_id -> $tax_rate_id -> (string)"items_vat"|"shipping_vat" -> (numeric)amount - or, in combined format, the last array is dropped out and you just get a total amount.
	public function get_refund_report_results($start_date, $end_date, $combined_format = false, $order_status = false) {

		// Refunds don't exist before WC 2.2
		if (!$this->at_least_22) return array();

		global $wpdb;

		$compliance = WooCommerce_EU_VAT_Compliance();

		$normalised_results = array();

		$sql = $this->get_refunds_sql(false, false, $start_date, $end_date, $order_status);

		if (!$sql) return array();

		$results = $wpdb->get_results($sql);
		if (!is_array($results)) return array();

		$current_order_item_id = false;

		// This forces the loop to go round oen more time, so that the last object in the DB results gets processed
		$res_terminator = new stdClass;
		$res_terminator->oid = -1;
		$res_terminator->id = -1;
		$res_terminator->v = false;
		$res_terminator->k = false;
		$results[] = $res_terminator;

		$default_result = ($combined_format) ? 0 : array('items_vat' => 0, 'shipping_vat' => 0);

		// The search results are sorted by order item ID (oid) and then by meta_key. We rely on both these facts in the following loop.

		foreach ($results as $res) {
			$order_id = $res->id;
			$order_item_id = $res->oid;
			$meta_value = $res->v;
			$meta_key = $res->k;

			if ($current_order_item_id !== $order_item_id) {
				if ($current_order_item_id !== false) {
					// Process previous record
					if (false !== $current_rate_id) {
						if (false != $current_tax_amount) {
							if (!isset($normalised_results[$current_order_id][$current_rate_id])) $normalised_results[$current_order_id][$current_rate_id] = $default_result;
							if ($combined_format) {
								$normalised_results[$current_order_id][$current_rate_id] += $current_tax_amount;
							} else {
								$normalised_results[$current_order_id][$current_rate_id]['items_vat'] += $current_tax_amount;
							}
						}
						if (false != $current_shipping_tax_amount) {
							if (!isset($normalised_results[$current_order_id][$current_rate_id])) $normalised_results[$current_order_id][$current_rate_id] = $default_result;
							if ($combined_format) {
								$normalised_results[$current_order_id][$current_rate_id] += $current_shipping_tax_amount;
							} else {
								$normalised_results[$current_order_id][$current_rate_id]['shipping_vat'] += $current_shipping_tax_amount;
							}
						}
					}
				}

				// Reset other values for the new item
				$current_order_item_id = $order_item_id;
				$current_order_id = $order_id;
				$current_rate_id = false;
				$current_tax_amount = false;
				$current_shipping_tax_amount = false;

			}

			if ('rate_id' == $meta_key) {
				$current_rate_id = $meta_value;
			} elseif ('tax_amount' == $meta_key) {
				$current_tax_amount = $meta_value;
			} elseif ('shipping_tax_amount' == $meta_key) {
				$current_shipping_tax_amount = $meta_value;
			}

		}

		return $normalised_results;

	}

	public function get_report_results($start_date, $end_date, $remove_non_eu_countries = true, $print_as_csv = false) {
		global $wpdb;

		$compliance = WooCommerce_EU_VAT_Compliance();

		$sql_vat_matches = $compliance->get_vat_matches('sqlregex');

		$eu_countries = $compliance->get_european_union_vat_countries();

		$page = 0;
		$page_size = 1000;
		$fetch_more = true;

		$normalised_results = array();

		$tax_based_on = get_option('woocommerce_tax_based_on');

		if ($print_as_csv) {
			$tax_based_on_extra = ", '_billing_country', '_shipping_country', '_customer_ip_address'";
			$select_extra = ',orders.post_date';
		} else {
			$select_extra = '';
			if ('billing' == $tax_based_on) {
				$tax_based_on_extra = ", '_billing_country'";
			} elseif ('shipping' == $tax_based_on) {
				$tax_based_on_extra = ", '_shipping_country'";
			}
		}

		while ($fetch_more) {
			$page_start = $page_size * $page;
			$results_this_time = 0;
			$sql = $this->get_report_sql($page_start, $page_size, $start_date, $end_date, $tax_based_on_extra, $select_extra);
			if (empty($sql)) break;

			$results = $wpdb->get_results($sql);

			$remove_order_id = false;

			if (!empty($results)) {
				$page++;
				foreach ($results as $res) {
					if (empty($res->ID)) continue;
					$order_id = $res->ID;
					$order_status = $res->order_status;
					$order_status = (substr($order_status, 0, 3) == 'wc-') ? substr($order_status, 3) : $order_status;
					if (empty($normalised_results[$order_status][$order_id])) {
						$normalised_results[$order_status][$order_id] = array('date_gmt' => $res->post_date_gmt);
						if ($print_as_csv) $normalised_results[$order_status][$order_id]['date'] = $res->post_date;
					}
					switch ($res->meta_key) {
						case 'vat_compliance_country_info';
							$cinfo = maybe_unserialize($res->meta_value);
							if ($print_as_csv) $normalised_results[$order_status][$order_id]['vat_compliance_country_info'] = $cinfo;
							$vat_country = (empty($cinfo['taxable_address'])) ? '??' : $cinfo['taxable_address'];
							if (!empty($vat_country[0])) {
								if ($remove_non_eu_countries && !in_array($vat_country[0], $eu_countries)) {
									$remove_order_id = $order_id;
									unset($normalised_results[$order_status][$order_id]);
									continue;
								}
								$normalised_results[$order_status][$order_id]['taxable_country'] = $vat_country[0];
							}
							if (!empty($vat_country[1])) $normalised_results[$order_status][$order_id]['taxable_state'] = $vat_country[1];
						break;
						case 'vat_compliance_vat_paid';
							$vat_paid = maybe_unserialize($res->meta_value);
							if (is_array($vat_paid)) {
								// Trying to minimise memory usage for large shops
								unset($vat_paid['currency']);
// 								unset($vat_paid['items_total']);
// 								unset($vat_paid['items_total_base_currency']);
// 								unset($vat_paid['shipping_total']);
// 								unset($vat_paid['shipping_total_base_currency']);
							}
							$normalised_results[$order_status][$order_id]['vat_paid'] = $vat_paid;
						break;
						case '_billing_country';
						case '_shipping_country';
						case '_order_total';
						case '_order_total_base_currency';
						case '_order_currency';
							$normalised_results[$order_status][$order_id][$res->meta_key] = $res->meta_value;
						break;
						case 'Valid EU VAT Number';
							$normalised_results[$order_status][$order_id]['vatno_valid'] = $res->meta_value;
						break;
						case 'order_time_order_number';
							$normalised_results[$order_status][$order_id]['order_number'] = $res->meta_value;
						break;
						case 'VAT Number';
							$normalised_results[$order_status][$order_id]['vatno'] = $res->meta_value;
						break;
						case 'VAT number validated';
							$normalised_results[$order_status][$order_id]['vatno_validated'] = $res->meta_value;
						break;
						case 'wceuvat_conversion_rates';
							$rates = maybe_unserialize($res->meta_value);
							$normalised_results[$order_status][$order_id]['conversion_rates'] = isset($rates['rates']) ? $rates['rates'] : array();
						case '_customer_ip_address';
							if ($print_as_csv) $normalised_results[$order_status][$order_id][$res->meta_key] = $res->meta_value;
						break;
					}

					if ($remove_order_id === $order_id) {
						unset($normalised_results[$order_status][$order_id]);
					}

				}

			} else {
				$fetch_more = false;
			}
			// Parse results;
		}

		// Loop again, to make sure that we've got the VAT paid recorded.
		foreach ($normalised_results as $order_status => $orders) {
			foreach ($orders as $order_id => $res) {
				if (empty($res['taxable_country'])) {
					// Legacy orders
					switch ( $tax_based_on ) {
						case 'billing' :
						$res['taxable_country'] = isset($res['_billing_country']) ? $res['_billing_country'] : '';
						break;
						case 'shipping' :
						$res['taxable_country'] = isset($res['_shipping_country']) ? $res['_shipping_country'] : '';
						break;
						default:
						unset($normalised_results[$order_status][$order_id]);
						break;
					}
					if (!$print_as_csv) {
						unset($res['_billing_country']);
						unset($res['_shipping_country']);
					}
				}

				if (!isset($res['vat_paid'])) {
					// This is not good for performance
// 					$normalised_results[$order_status][$order_id]['vat_paid'] = WooCommerce_EU_VAT_Compliance()->get_vat_paid($order_id, true, true, true);
				}

				// N.B. Use of empty() means that those with zero VAT are also excluded at this point
				if (empty($res['vat_paid'])) {
					unset($normalised_results[$order_status][$order_id]);
				} elseif (!isset($res['order_number'])) {
					// This will be database-intensive, the first time, if they had a lot of orders before this bit of meta began to be recorded at order time (plugin version 1.7.2)
					$order = $compliance->get_order($order_id);
					$order_number = $order->get_order_number();
					$normalised_results[$order_status][$order_id]['order_number'] = $order_number;
					update_post_meta($order_id, 'order_time_order_number', $order_number);
				}
			}
		}

		/* Interesting keys:
			_order_currency
			_order_shipping_tax
			_order_shipping_tax_base_currency
			_order_tax
			_order_tax_base_currency
			_order_total
			_order_total_base_currency
			vat_compliance_country_info
			Valid EU VAT Number (true)
			VAT Number
			VAT number validated (true)
		*/

		return $normalised_results;

	}

	public function wc_eu_vat_compliance_report() {

		$ranges = $this->get_report_ranges();
		$current_range = !empty($_GET['range']) ? sanitize_text_field($_GET['range']) : 'quarter';

		if(!in_array($current_range, array_merge(array_keys($ranges), array('custom')))) {
			$current_range = 'quarter';
		}

		$this->calculate_current_range($current_range);

		// This variable is used by the included WC file below, so do not remove on account of its apparent non-use.
		$hide_sidebar = true;

		echo "<ul style=\"list-style-type: disc; list-style-position: inside;\">";
		echo '<li>'.__('The report below indicates the taxes actually charged on orders, when they were processed: it does not take into account later alterations manually made to order data.', 'wc_eu_vat_compliance').'</li>';

		$csv_message = apply_filters('wc_eu_vat_compliance_csv_message', '<a href="https://www.simbahosting.co.uk/s3/product/woocommerce-eu-vat-compliance/">'.__('Downloading all orders with VAT data in CSV format is a feature of the Premium version of this plugin.', 'wc_eu_vat_compliance').'</a>');

		echo "<li>$csv_message</li>";

		if ($this->at_least_22) {
			echo "<li>".__('The refund column in the table and CSV download is calculated from WooCommerce refunds.', 'wc_eu_vat_compliance').' <a href="#" onclick="jQuery(this).hide(); jQuery(\'#wceuvat_refunds_moreexplanation\').fadeIn(); return false;">'.ucfirst(__('more information', 'wc_eu_vat_compliance')).'...</a>'.'<span id="wceuvat_refunds_moreexplanation" style="display:none;"> '.__('These can be complete or partial refunds, and are separate to whether or not you have marked the order status as "refunded"', 'wc_eu_vat_compliance').' (<a href="http://docs.woothemes.com/document/woocommerce-refunds/">'.__('more information', 'wc_eu_vat_compliance').'</a>). '.__('Note that the refund column only includes refunds made within the chosen date range.', 'wc_eu_vat_compliance')." ".__('i.e. This is a true VAT report for the chosen period.', 'wc_eu_vat_compliance')." ".__('If you want to download data that includes refunds made at any time, then the best option is to choose a date range up until the current time, download the data by CSV, and perform spreadsheet calculations on the rows whose order date matches the period you are interested in.', 'wc_eu_vat_compliance')."</span></li>";
		}

		echo "</ul>";

		$script = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? 'jquery.tablesorter.js' : 'jquery.tablesorter.min.js';
		$widgets_script = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? 'jquery.tablesorter.widgets.js' : 'jquery.tablesorter.widgets.min.js';

		wp_register_script('jquery-tablesorter', WC_EU_VAT_COMPLIANCE_URL.'/js/'.$script, array('jquery'), '2.17.8', true);
		wp_register_script('jquery-tablesorter-widgets', WC_EU_VAT_COMPLIANCE_URL.'/js/'.$widgets_script, array('jquery-tablesorter'), '2.17.8', true);

		wp_enqueue_style( 'tablesorter-style-jui', WC_EU_VAT_COMPLIANCE_URL.'/css/tablesorter-theme.jui.css', array(), '2.17.8');
		wp_enqueue_script('jquery-tablesorter-widgets');

		?>

		<form id="wceuvat_report_form" method="post" style="padding-bottom:8px;">
			<?php
				$print_fields = array('page', 'tab', 'report', 'chart');
				foreach ($print_fields as $field) {
					if (isset($_REQUEST[$field])) {
						if ('tab' == $field) $printed_tab = true;
						echo '<input type="hidden" name="'.$field.'" value="'.$_REQUEST[$field].'">'."\n";
					}
				}

				if (empty($printed_tab)) {
					echo '<input type="hidden" name="tab" value="reports">'."\n";
				} else {
					echo '<input type="hidden" name="tab" value="taxes">'."\n";
				}

// 				$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
// 				$end_date    = isset($_POST['end_date']) ? $_POST['end_date'] : '';

				if (empty($this->start_date))
					$this->start_date = strtotime(date('Y-01-01', current_time('timestamp')));
				if (empty($this->end_date))
					$this->end_date = strtotime(date('Y-m-d 23:59:59', current_time('timestamp')));

// 				do_action('wc_eu_vat_report_begin', $this->start_date, $this->end_date);
				wp_enqueue_script( 'jquery-ui-datepicker', array( 'jquery' ), 1, true );

			?>

			<p>

			<?php
				_e('Include statuses (updates instantly):', 'wc_eu_vat_compliance');

				$statuses = WooCommerce_EU_VAT_Compliance()->order_status_to_text(true);

				$default_statuses = array('wc-processing', 'wc-completed');

				foreach ($statuses as $label => $text) {

					$use_label = (substr($label, 0, 3) == 'wc-') ? substr($label, 3) : $label;
					$checked = (!isset($_REQUEST['wceuvat_go']) && !isset($_REQUEST['range'])) ? (in_array($label, $default_statuses) ? ' checked="checked"' : '') : ((isset($_REQUEST['order_statuses']) && is_array($_REQUEST['order_statuses']) && in_array($use_label, $_REQUEST['order_statuses'])) ? ' checked="checked"' : '');

					echo "\n".'<input type="checkbox"'.$checked.' class="wceuvat_report_status" name="order_statuses[]" id="order_status_'.$use_label.'" value="'.$use_label.'"><label for="order_status_'.$use_label.'" style="margin-right: 10px;">'.$text.'</label> ';
				}

			?>

			</p>

		</form>

		<div style="max-width:1160px;">

		<?php
		include(WooCommerce_EU_VAT_Compliance()->wc->plugin_path() . '/includes/admin/views/html-report-by-date.php');

		echo '</div>';

	}

	// This function from Diego Zanella
	/**
	 * Get the current range and calculate the start and end dates
	 * @param  string $current_range
	 */
	public function calculate_current_range($current_range) {
		$this->chart_groupby = 'month';
		switch ($current_range) {
			case 'quarter_before_previous':
				$month = date('m', strtotime('-6 MONTH', current_time('timestamp')));
				$year  = date('Y', strtotime('-6 MONTH', current_time('timestamp')));
			break;
			case 'previous_quarter':
				$month = date('m', strtotime('-3 MONTH', current_time('timestamp')));
				$year  = date('Y', strtotime('-3 MONTH', current_time('timestamp')));
			break;
			case 'quarter':
				$month = date('m', current_time('timestamp'));
				$year  = date('Y', current_time('timestamp'));
			break;
			default:
				$start_date = isset($_REQUEST['start_date']) ? $_REQUEST['start_date'] : date('Y-01-01', current_time('timestamp'));
				$this->start_date = strtotime($start_date);
				$end_date = isset($_REQUEST['end_date']) ? $_REQUEST['end_date'] : date('Y-m-d', 86400+current_time('timestamp'));
				$this->end_date = strtotime($end_date);
// 				parent::calculate_current_range($current_range);
				return;
			break;
		}

		if($month <= 3) {
			$this->start_date = strtotime($year . '-01-01');
			$this->end_date = strtotime(date('Y-m-t', strtotime($year . '-03-01')));
		}
		elseif($month > 3 && $month <= 6) {
			$this->start_date = strtotime($year . '-04-01');
			$this->end_date = strtotime(date('Y-m-t', strtotime($year . '-06-01')));
		}
		elseif($month > 6 && $month <= 9) {
			$this->start_date = strtotime($year . '-07-01');
			$this->end_date = strtotime(date('Y-m-t', strtotime($year . '-09-01')));
		}
		elseif($month > 9) {
			$this->start_date = strtotime($year . '-10-01');
			$this->end_date = strtotime(date('Y-m-t', strtotime($year . '-12-01')));
		}
	}

	// This function from Diego Zanella
	/**
	 * Returns an array of ranges that are used to produce the reports.
	 *
	 * @return array
	 */
	protected function get_report_ranges() {
		$ranges = array();

		$current_time = current_time('timestamp');
		$label_fmt = _x('Q%d %d', 'Q for quarter (date); e.g. Q1 2014', 'wc_eu_vat_compliance');

		// Current quarter
		$quarter = ceil(date('m', $current_time) / 3);
		$year = date('Y');
		$ranges['quarter'] = sprintf($label_fmt, $quarter, $year);

		// Quarter before this one
		$month = date('m', strtotime('-3 MONTH', $current_time));
		$year  = date('Y', strtotime('-3 MONTH', $current_time));
		$quarter = ceil($month / 3);
		$ranges['previous_quarter'] = sprintf($label_fmt, $quarter, $year);

		// Two quarters ago
		$month = date('m', strtotime('-6 MONTH', $current_time));
		$year  = date('Y', strtotime('-6 MONTH', $current_time));
		$quarter = ceil($month / 3);
		$ranges['quarter_before_previous'] = sprintf($label_fmt, $quarter, $year);

		return array_reverse($ranges);
	}

	public function get_export_button() {
		do_action('wc_eu_vat_compliance_csv_export_button');
	}

	public function get_chart_legend() {
		return array();
	}

	public function get_chart_widgets() {
		return array();
	}

	public function initialise_rate_provider() {
		$compliance =  WooCommerce_EU_VAT_Compliance();
		$providers = $compliance->get_rate_providers();
		$conversion_provider = get_option('woocommerce_eu_vat_compliance_exchange_rate_provider', 'ecb');

		if (!is_array($providers) || !isset($providers[$conversion_provider])) throw new Exception('Conversion provider not found: '.$conversion_provider);

		$this->conversion_provider = $providers[$conversion_provider];
	}

	// This is called by woocommerce/includes/admin/views/html-report-by-date.php
	public function get_main_chart() {

		$start_date = date('Y-m-d', $this->start_date);
		$end_date = date('Y-m-d', $this->end_date);

		global $wpdb;
		$compliance =  WooCommerce_EU_VAT_Compliance();

		$results = $this->get_report_results($start_date, $end_date);

		if ($wpdb->last_error) {
			echo htmlspecialchars($wpdb->last_error);
			return;
		}

		// Further processing. Need to do currency conversions and index by country
		$tabulated_results = array();

		$base_currency = get_option('woocommerce_currency');
		$base_currency_symbol = get_woocommerce_currency_symbol($base_currency);
		$eu_countries = $compliance->get_european_union_vat_countries();

		$this->initialise_rate_provider();

		$this->reporting_currency = apply_filters('wc_eu_vat_vat_reporting_currency', get_option('woocommerce_eu_vat_compliance_vat_recording_currency'));
		if (empty($this->reporting_currency)) $this->reporting_currency = $base_currency;

		$reporting_currency_symbol = get_woocommerce_currency_symbol($this->reporting_currency);

		// We need to make sure that the outer foreach() loop does go round for each status, because otherwise refunds on orders made in different accounting periods may be missed
		// These have the wc- prefix.
		$all_possible_statuses = $compliance->order_status_to_text(true);
		foreach ($all_possible_statuses as $wc_status => $status_text) {
			$order_status = substr($wc_status, 3);
			if (!isset($results[$order_status])) $results[$order_status] = array();
		}

		foreach ($results as $order_status => $result_set) {

			// This returns an array of arrays; keys = order IDs; second key = tax rate IDs, values = total amount of orders taxed at these rates
			// N.B. The "total" column potentially has no meaning when totaling item totals, as a single item may have attracted multiple taxes (theoretically). Note also that the totals are *for orders with VAT*.
			$get_items_data = $this->get_items_data($start_date, $end_date, $order_status);

			// Refunds data is keyed by ID, and then by tax-rate. This isn't maximally efficient for the reports table, but since we are not expecting tens of thousands of refunds, this should have no significant performance or memory impact.
			$refunds_data = $this->get_refund_report_results($start_date, $end_date, true, $order_status);

			// We need to make sure that refunds still get processed when they are from a different account period (i.e. when the order is not in the results set)
			foreach ($refunds_data as $order_id => $refunds_by_rate) {
				if (empty($result_set[$order_id])) {
					// Though this taxes the database more, it should be a very rare occurrence
					// Can use wc_get_order() : there are no refunds until WC 2.2

					$refunded_order = $compliance->get_order($order_id);

					$post_id = (isset($refunded_order->post)) ? $refunded_order->post->ID : $refunded_order->id;
					$rates = get_post_meta($post_id, 'wceuvat_conversion_rates', true);
					$cinfo = get_post_meta($post_id, 'vat_compliance_country_info', true);
					$vat_compliance_vat_paid = get_post_meta($post_id, 'vat_compliance_vat_paid', true);

					$by_rates = array();
					foreach ($refunds_by_rate as $tax_rate_id => $tax_refunded) {
						if (isset($vat_compliance_vat_paid['by_rates'][$tax_rate_id])) {
							$by_rates[$tax_rate_id] = array(
								'is_variable_eu_vat' => isset($vat_compliance_vat_paid['by_rates'][$tax_rate_id]) ? $vat_compliance_vat_paid['by_rates'][$tax_rate_id] : true,
								'items_total' => 0,
								'shipping_total' => 0,
								'rate' => $vat_compliance_vat_paid['by_rates'][$tax_rate_id]['rate'],
								'name' => $vat_compliance_vat_paid['by_rates'][$tax_rate_id]['name'],
							);
						}
					}

					$result_set[$order_id] = array(
						'vat_paid' => array('total' => 0, 'by_rates' => $by_rates),
						'_order_currency' => $refunded_order->get_order_currency(),
					);

					$vat_country = (empty($cinfo['taxable_address'])) ? '??' : $cinfo['taxable_address'];
					if (!empty($vat_country[0])) {
						if (in_array($vat_country[0], $eu_countries)) {
							$result_set[$order_id]['taxable_country'] = $vat_country[0];
						}
					}

					if (is_array($rates) && isset($rates['rates'])) $result_set[$order_id]['conversion_rates'] = $rates['rates'];

				}
			}

			foreach ($result_set as $order_id => $res) {

				// Don't test empty($res['vat_paid']['total']), as this can cause refunds to be not included
				if (!is_array($res) || empty($res['taxable_country']) || empty($res['vat_paid']) || !is_array($res['vat_paid']) || !isset($res['vat_paid']['total'])) continue;

				$order_currency = (isset($res['_order_currency'])) ? $res['_order_currency'] : $base_currency;
				$country = $res['taxable_country'];

				$conversion_rates = isset($res['conversion_rates']) ? $res['conversion_rates'] : array();
				// Convert the 'vat_paid' array so that its values in the reporting currency, according to the conversion rates stored with the order

				$get_items_data_for_order = (isset($get_items_data[$order_id])) ? $get_items_data[$order_id] : array();
				$refunds_data_for_order = (isset($refunds_data[$order_id])) ? $refunds_data[$order_id] : array();

				list($res_converted, $converted_items_data_for_order, $converted_refunds_data_for_order) = $this->get_converted_order_data($res, $order_currency, $conversion_rates, $get_items_data_for_order, $refunds_data_for_order);

				$vat_paid = $res_converted['vat_paid'];

				$by_rate = array();
				if (isset($vat_paid['by_rates'])) {
					foreach ($vat_paid['by_rates'] as $tax_rate_id => $rinfo) {

						$rate = sprintf('%0.2f', $rinfo['rate']);
						$rate_key = $rate;
						// !isset means 'legacy - data produced before the plugin set this field: assume it is variable, because at that point the plugin did not officially support mixed shops with non-variable VAT'
						if (!isset($rinfo['is_variable_eu_vat']) || !empty($rinfo['is_variable_eu_vat'])) {
							$rate_key = 'V-'.$rate_key;
						}

						if (!isset($by_rate[$rate_key])) $by_rate[$rate_key] = array('vat' => 0, 'vat_shipping' => 0, 'sales' => 0, 'vat_refunded' => 0);
						$by_rate[$rate_key]['vat'] += $rinfo['items_total']+$rinfo['shipping_total'];
						$by_rate[$rate_key]['vat_shipping'] += $rinfo['shipping_total'];

						// Add sales from items totals
						if (isset($converted_items_data_for_order[$tax_rate_id])) {
							$by_rate[$rate_key]['sales'] += $converted_items_data_for_order[$tax_rate_id];
						}

						// Add refunds data
						// If no VAT was paid at this rate in the accounting period, then that means that the order itself can't have been in this accounting period - and so, the "missing order" detector above will add the necessary blank data. Thus, this code path will be active
						if (isset($converted_refunds_data_for_order[$tax_rate_id])) {
							$by_rate[$rate_key]['vat_refunded'] += $converted_refunds_data_for_order[$tax_rate_id];
						}
					}

				} else {
					// Legacy: no "by_rates" plugin versions also only allowed variable VAT
					$rate_key = 'V-'.__('Unknown', 'wc_eu_vat_compliance');
					if (!isset($by_rate[$rate_key])) $by_rate[$rate_key] = array('vat' => 0, 'vat_shipping' => 0, 'sales' => 0);
					$by_rate[$rate_key]['vat'] += $vat_paid['total'];
					$by_rate[$rate_key]['vat_shipping'] += $vat_paid['shipping_total'];

					foreach ($converted_items_data_for_order as $tax_rate_id => $sales_amount) {
						$by_rate[$rate_key]['sales'] += $sales_amount;
					}

					foreach ($converted_refunds_data_for_order as $tax_rate_id => $refund_amount) {
						$by_rate[$rate_key]['vat_refunded'] += $refund_amount;
					}
				}

				foreach ($by_rate as $rate_key => $rate_data) {
					# VAT
					if (empty($tabulated_results[$order_status][$country][$rate_key]['vat'])) $tabulated_results[$order_status][$country][$rate_key]['vat'] = 0;
					$tabulated_results[$order_status][$country][$rate_key]['vat'] += $rate_data['vat'];

					# VAT (shipping)
					if (empty($tabulated_results[$order_status][$country][$rate_key]['vat_shipping'])) $tabulated_results[$order_status][$country][$rate_key]['vat_shipping'] = 0;
					$tabulated_results[$order_status][$country][$rate_key]['vat_shipping'] += $rate_data['vat_shipping'];
					
					# Items total, using the data got from the (current) order_itemmeta and order_items tables
					if (empty($tabulated_results[$order_status][$country][$rate_key]['sales'])) $tabulated_results[$order_status][$country][$rate_key]['sales'] = 0;
					$tabulated_results[$order_status][$country][$rate_key]['sales'] += $rate_data['sales'];

					# Refunds total, using the data got from the (current) order_itemmeta and order_items tables
					if (empty($tabulated_results[$order_status][$country][$rate_key]['vat_refunded'])) $tabulated_results[$order_status][$country][$rate_key]['vat_refunded'] = 0;
					$tabulated_results[$order_status][$country][$rate_key]['vat_refunded'] += $rate_data['vat_refunded'];
				}

				// Below is incorrect - it does not separate out by rate. The correct code is above.
				# Sales (net)
				// To do this (i.e. item sales per-VAT-rate), involves interrogating the order_itemmeta and order_items tables.
// 				if (empty($tabulated_results[$order_status][$country]['sales'])) $tabulated_results[$order_status][$country]['sales'] = 0;
// 				$tabulated_results[$order_status][$country]['sales'] += $res_converted['_order_total'];

			}
		}

		// Remove the 'sales' column if there are items with no line tax data (i.e. pre-WC 2.2 sales) OR (better?) display a warning about the data being incomplete.
		if ($this->at_least_22) {
			if ($this->pre_wc22_order_parsed) {
				?>
				<p>
				<span style="font-weight:bold; color:red;"><?php _e('Note:', 'wc_eu_vat_compliance');?></span> <?php echo __('The selected time period contains orders made under WooCommerce 2.1 or earlier.', 'wc_eu_vat_compliance').' '.__('These WooCommerce versions did not record the data used to display the "Items" column, which is therefore incomplete and has been hidden.', 'wc_eu_vat_compliance');?> <a href="#" onclick="jQuery('.wceuvat_itemsdata').slideDown(); wceuvat_itemsdata_show=true; jQuery(this).parent().remove(); return false;"><?php _e('Show', 'wc_eu_vat_compliance');?></a>
				</p>
				<script>
				var wceuvat_itemsdata_show = false;
				jQuery(document).ready(function($){
					$('.wceuvat_itemsdata').hide();
				});
				</script>
				<?php
			} else {
			?>
			<script>
				var wceuvat_itemsdata_show = true;
			</script>
			<?php
			}
		} else {
			?>
			<script>
				var wceuvat_itemsdata_show = false;
				jQuery(document).ready(function($){
					$('.wceuvat_itemsdata').hide();
				});
			</script>
			<?php
		}

		$this->report_table_header();

		$eu_total = 0;

		$countries = $compliance->wc->countries;
		$all_countries = $countries->countries;

		$total_vat_items = 0;
		$total_vat_shipping = 0;
		$total_vat_refunds = 0;
		$total_vat = 0;

		$total_items = 0;
		$total_sales = 0;

// 		$tbody = '';

		// var_dump($tabulated_results); return;

		foreach ($tabulated_results as $order_status => $results) {
			$status_text = $compliance->order_status_to_text($order_status);

			foreach ($results as $country => $per_rate_totals) {
				foreach ($per_rate_totals as $rate_key => $totals) {

					$country_label = isset($all_countries[$country]) ? $all_countries[$country] : __('Unknown', 'wc_eu_vat_compliance').' ('.$country.')';
					$country_label = '<span title="'.$country.'">'.$country_label.'</span>';

					$vat_items_amount = $compliance->round_amount($totals['vat']-$totals['vat_shipping']);
					$vat_shipping_amount = $compliance->round_amount($totals['vat_shipping']);
					$vat_total_amount = $compliance->round_amount($totals['vat']+$totals['vat_refunded']);
					$vat_refund_amount = $compliance->round_amount($totals['vat_refunded']);

					$items_amount = $compliance->round_amount($totals['sales']);
// 					$items_amount = $reporting_currency_symbol.' '.sprintf('%.02f', $totals['sales']-$totals['vat']);

					$total_vat += $vat_total_amount;
					$total_vat_items += $vat_items_amount;
// 					$total_items += $totals['sales']-$totals['vat'];
					$total_items += $items_amount;
					$total_vat_shipping += $vat_shipping_amount;
					$total_vat_refunds += $vat_refund_amount;

// 					$total_sales += $totals['sales'];

					if (preg_match('/^(V-)?([\d\.]+)$/', $rate_key, $matches)) {
						$vat_rate_label = str_replace('.00', '.0', $matches[2].'%');
						if (empty($matches[1])) {
							$vat_rate_label .= '<span title="'.esc_attr(__('Fixed - i.e., traditional non-variable VAT', 'wc_eu_vat_compliance')).'"> ('.__('fixed', 'wc_eu_vat_compliance').')</span>';
						}
					} else {
						$vat_rate_label = htmlspecialchars($rate_key);
					}

				if ($this->at_least_22) {
					$extra_col_items = '<td class="wceuvat_itemsdata">'.$reporting_currency_symbol.' '.$items_amount.'</td>';
					$extra_col_refunds = '<td class="wceuvat_refundsdata">'.$reporting_currency_symbol.' '.$vat_refund_amount.'</td>';
				} else {
					$extra_col_items = '';
					$extra_col_refunds = '';
				}

//data-items=\"".sprintf('%.05f', $totals['sales']-$totals['vat'])."\"
					echo "<tr data-vat-items=\"".$compliance->round_amount($vat_items_amount)."\"data-vat-refunds=\"".$compliance->round_amount($vat_refund_amount)."\" data-vat-shipping=\"".$compliance->round_amount($vat_shipping_amount)."\" data-items=\"".$compliance->round_amount($items_amount)."\" class=\"statusrow status-$order_status\">
						<td>$status_text</td>
						<td>$country_label</td>".$extra_col_items."
						<td>$vat_rate_label</td>
						<td>$reporting_currency_symbol $vat_items_amount</td>
						<td>$reporting_currency_symbol $vat_shipping_amount</td>".$extra_col_refunds."
						<td>$reporting_currency_symbol $vat_total_amount</td>
					</tr>";
//						<td>$items_amount</td>

				}
			}
		}

// 		echo $tbody;


			echo '</tbody>';

/* 				<td><strong><?php echo $reporting_currency_symbol.' '.sprintf('%.2f', $total_items); ?></strong></td> */

			?>
			<tr class="wc_eu_vat_compliance_totals" id="wc_eu_vat_compliance_total">
				<td><strong><?php echo __('Grand Total', 'wc_eu_vat_compliance');?></strong></td>
				<td>-</td>
				<?php if ($this->at_least_22) { ?>
					<td><strong><?php echo $reporting_currency_symbol.' '.sprintf('%.2f', $total_items); ?></strong></td>
				<?php } ?>
				<td>-</td>
				<td><strong><?php echo $reporting_currency_symbol.' '.sprintf('%.2f', $total_vat_items); ?></strong></td>
				<td><strong><?php echo $reporting_currency_symbol.' '.sprintf('%.2f', $total_vat_shipping); ?></strong></td>
				<?php if ($this->at_least_22) { ?>
					<td><strong><?php echo $reporting_currency_symbol.' '.sprintf('%.2f', $total_vat_refunds); ?></strong></td>
				<?php } ?>
				<td><strong><?php echo $reporting_currency_symbol.' '.sprintf('%.2f', $total_vat); ?></strong></td>
			</tr>
			<?php

		$this->report_table_footer($reporting_currency_symbol);

		add_action('admin_footer', array($this, 'admin_footer'));
	}

	public function get_converted_refunds_data($refunds_for_order, $order_currency, $conversion_rates) {

		if (!is_array($refunds_for_order)) return $refunds_for_order;

		if (isset($conversion_rates[$this->reporting_currency])) {
			$use_rate = $conversion_rates[$this->reporting_currency];
		} elseif (isset($this->fallback_conversion_rates[$order_currency])) {
			$use_rate = $this->fallback_conversion_rates[$order_currency];
		} else {
			// Returns the conversion for 1 unit of the order currency.
			$use_rate = $this->conversion_provider->convert($order_currency, $this->reporting_currency, 1);
			$this->fallback_conversion_rates[$order_currency] = $use_rate;
		}

		foreach ($refunds_for_order as $tax_rate_id => $refunded_amount) {
			$refunds_for_order[$tax_rate_id] = $refunded_amount * $use_rate;
		}
		
		return $refunds_for_order;

	}

	// This takes one or two arrays of order data, and converts the amounts in them to the requested currency
	// public: used also in the CSV download
	public function get_converted_order_data($raw, $order_currency, $conversion_rates, $get_items_data_for_order = array(), $refunds_data_for_order = array()) {

		if (isset($conversion_rates[$this->reporting_currency])) {
			$use_rate = $conversion_rates[$this->reporting_currency];
		} elseif (isset($this->fallback_conversion_rates[$order_currency])) {
			$use_rate = $this->fallback_conversion_rates[$order_currency];
		} else {
			// Returns the conversion for 1 unit of the order currency.
			$use_rate = $this->conversion_provider->convert($order_currency, $this->reporting_currency, 1);
			$this->fallback_conversion_rates[$order_currency] = $use_rate;
		}
		$this->last_rate_used = $use_rate;

		if (isset($raw['_order_total'])) {
			$raw['_order_total'] = $raw['_order_total'] * $use_rate;
		}

		$convert_keys = array('items_total', 'shipping_total', 'total');
		foreach ($convert_keys as $key) {
			if (isset($raw['vat_paid'][$key])) {
				$raw['vat_paid'][$key] = $raw['vat_paid'][$key] * $use_rate;
			}
		}
		if (isset($raw['vat_paid']['by_rates'])) {
			foreach ($raw['vat_paid']['by_rates'] as $rate_id => $rate) {
				foreach ($convert_keys as $key) {
					if (isset($rate[$key])) {
						$raw['vat_paid']['by_rates'][$rate_id][$key] = $raw['vat_paid']['by_rates'][$rate_id][$key] * $use_rate;
					}
				}
			}
		}

		foreach ($get_items_data_for_order as $tax_rate_id => $amount) {
			$get_items_data_for_order[$tax_rate_id] = $amount * $use_rate;
		}

		foreach ($refunds_data_for_order as $tax_rate_id => $amount) {
			$refunds_data_for_order[$tax_rate_id] = $amount * $use_rate;
		}

		return array($raw, $get_items_data_for_order, $refunds_data_for_order);
	}

	private function report_table_footer($reporting_currency_symbol) {

		WooCommerce_EU_VAT_Compliance()->enqueue_jquery_ui_style();

		?>
		</tbody>
		</table>
		<script>
			jQuery(document).ready(function() {

				var currency_symbol = '<?php echo esc_js($reporting_currency_symbol); ?>';
				var tablesorter_created = 0;

				// This function updates the table based on what order statuses were chosen; it also copies the order status checkboxes into the form in the table, so that they are retained when that form is submitted.
				function update_table() {

// 					try {
// 						if (tablesorter_created) {
// // 							jQuery('#wc_eu_vat_compliance_report').tablesorter.destroy();
// 						}
// 					} catch (e) {
// 						console.log(e);
// 					}

					// Hide them all, then selectively re-show
					jQuery('#wc_eu_vat_compliance_report tbody tr.statusrow').hide();
					// Get the checked statuses
					var total_vat_items = 0;
					var total_vat_shipping = 0;
					var total_vat = 0;
					var total_vat_refunds = 0;
					var total_items = 0;
					jQuery('.stats_range input[name="order_statuses[]"]').remove();
					jQuery('#wceuvat_report_form input.wceuvat_report_status').each(function(ind, item) {
						var status_id = jQuery(item).attr('id');
						if (status_id.substring(0, 13) == 'order_status_' && jQuery(item).prop('checked')) {
							var status_label = status_id.substring(13);
							jQuery('.stats_range form').append('<input class="wceuvat_report_status_hidden" type="hidden" name="order_statuses[]" value="'+status_label+'">');
							var row_items = jQuery('#wc_eu_vat_compliance_report tbody tr.status-'+status_label);
							jQuery(row_items).show();
							jQuery(row_items).each(function(cind, citem) {
								var items = parseFloat(jQuery(citem).data('items'));
								var vat_items = parseFloat(jQuery(citem).data('vat-items'));
								var vat_shipping = parseFloat(jQuery(citem).data('vat-shipping'));
								var vat_refunds = parseFloat(jQuery(citem).data('vat-refunds'));
								var vat = vat_items + vat_shipping;
								total_items += items;
								total_vat += vat;
								total_vat += vat_refunds;
								total_vat_items += vat_items;
								total_vat_shipping += vat_shipping;
								total_vat_refunds += vat_refunds;
// 								total_items += items;
							});
						};
					});

					// Rebuild totals
					jQuery('.wc_eu_vat_compliance_totals').remove();
					jQuery('#wc_eu_vat_compliance_report').append('<tbody class="avoid-sort wc_eu_vat_compliance_totals"></tbody>');

					jQuery('#wc_eu_vat_compliance_report tbody.wc_eu_vat_compliance_totals').append('\
		<tr class="wc_eu_vat_compliance_total" id="wc_eu_vat_compliance_total">\
			<td><strong><?php echo __('Grand Total', 'wc_eu_vat_compliance');?></strong></td>\
			<td>-</td>\
			<?php
				if ($this->at_least_22) {
					echo "<td class=\"wceuvat_itemsdata\"><strong>'+currency_symbol+' '+parseFloat(total_items).toFixed(2)+'</strong></td>\\";
				}
			?>
			<td>-</td>\
			<td><strong>'+currency_symbol+' '+parseFloat(total_vat_items).toFixed(2)+'</strong></td>\
			<td><strong>'+currency_symbol+' '+parseFloat(total_vat_shipping).toFixed(2)+'</strong></td>\
			<?php
				if ($this->at_least_22) {
					echo "<td class=\"wceuvat_refundsdata\"><strong>'+currency_symbol+' '+parseFloat(total_vat_refunds).toFixed(2)+'</strong></td>\\";
				}
			?>
			<td><strong>'+currency_symbol+' '+parseFloat(total_vat).toFixed(2)+'</strong></td>\
		</tr>\
					');
// 			<td><strong>'+currency_symbol+' '+parseFloat(total_items).toFixed(2)+'</strong></td>\

					if (typeof wceuvat_itemsdata_show != 'undefined' && wceuvat_itemsdata_show) {
						jQuery('.wceuvat_itemsdata').show();
					} else {
						jQuery('.wceuvat_itemsdata').hide();
					}

					if (!tablesorter_created) {
						jQuery('#wc_eu_vat_compliance_report').tablesorter({
							cssInfoBlock : "avoid-sort",
							theme: 'jui',
							headerTemplate : '{content} {icon}', // needed to add icon for jui theme
							widgets : ['uitheme'],
						});
						tablesorter_created = 1;
					}

				};

				update_table();

				jQuery('#wceuvat_report_form .wceuvat_report_status').change(function() {
					update_table();
				});
				<?php
					$base_url = admin_url('admin.php?page='.$_REQUEST['page']);
					if ('wc_eu_vat_compliance_cc' == $_REQUEST['page']) $base_url .= '&tab=reports';
				?>
				jQuery('.stats_range li a').click(function(e) {
					var href = jQuery(this).attr('href');
					var get_range = href.match(/range=([_A-Za-z0-9]+)/);

					if (get_range instanceof Array) {
						var range = get_range[1];
						var newhref = '<?php echo esc_js($base_url);?>&range='+range;
// 						e.preventDefault();
						var st_id = 0;
						jQuery('#wceuvat_report_form input.wceuvat_report_status').each(function(ind, item) {
							var status_id = jQuery(item).attr('id');
							if (status_id.substring(0, 13) == 'order_status_' && jQuery(item).prop('checked')) {
								var status_label = status_id.substring(13);
								newhref += '&order_statuses['+st_id+']='+status_label;
								st_id++;
							}
						});
						// This feels hacky, but appears to be acceptable
						jQuery(this).attr('href', newhref);
					}
				});
			});
		</script>
	<?php
	}

	private function report_table_header() {
/* 				<th><?php _e('Items (before VAT)', 'wc_eu_vat_compliance');?></th> */
	?>
		<table class="widefat" id="wc_eu_vat_compliance_report">
		<thead>
			<tr>
				<th><?php _e('Order Status', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('Country', 'wc_eu_vat_compliance');?></th>
				<?php if ($this->at_least_22) { ?>
					<th class="wceuvat_itemsdata"><?php _e('Items (before VAT)', 'wc_eu_vat_compliance');?></th>
				<?php } ?>
				<th><?php _e('VAT rate', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('VAT (items)', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('VAT (shipping)', 'wc_eu_vat_compliance');?></th>
				<?php if ($this->at_least_22) { ?>
					<th class="wceuvat_refundsdata" title="<?php echo esc_attr(__("N.B. This column shows (only) amounts that were refunded using WooCommerce's refunds feature within the chosen date range - whether the WooCommerce order status is 'refunded' or not, and independently of whether the order that the refund corresponds to is within the same date range.", 'wc_eu_vat_compliance'));?>"><?php _e('VAT refunded', 'wc_eu_vat_compliance');?></th>
				<?php } ?>
				<th><?php _e('Total VAT', 'wc_eu_vat_compliance');?></th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<th><?php _e('Order Status', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('Country', 'wc_eu_vat_compliance');?></th>
				<?php if ($this->at_least_22) { ?>
					<th class="wceuvat_itemsdata"><?php _e('Items (before VAT)', 'wc_eu_vat_compliance');?></th>
				<?php } ?>
				<th><?php _e('VAT rate', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('VAT (items)', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('VAT (shipping)', 'wc_eu_vat_compliance');?></th>
				<?php if ($this->at_least_22) { ?>
					<th class="wceuvat_refundsdata" title="<?php echo esc_attr(__("N.B. This column shows (only) amounts that were refunded using WooCommerce's refunds feature - whether the WooCommerce order status is 'refunded' or not, and independently of whether the order that the refund corresponds to is within the same date range.", 'wc_eu_vat_compliance'));?>"><?php _e('VAT refunded', 'wc_eu_vat_compliance');?></th>
				<?php } ?>
				<th><?php _e('Total VAT', 'wc_eu_vat_compliance');?></th>
			</tr>
		</tfoot>
		<tbody>
	<?php
	}

}