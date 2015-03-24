<?php

if (!defined('WC_EU_VAT_COMPLIANCE_DIR')) die('No direct access');

// Purpose: provide a report on VAT paid, to help with EU VAT compliance

// TODO: Test refunds.

// TODO: Report needs to have option to display only MOSS taxes, or display all VAT - or, just do MOSS only, and tell them to go to the traditional WC tax report for other taxes (but what about currency conversions)

// TODO: Option as to which exchange rate to use (whether stored rate, or over-ride with an end-of-quarter rate from named provider).

class WC_EU_VAT_Compliance_Reports {

	// Public: is used in the CSV download code
	public $reporting_currency = '';
	public $last_rate_used = 1;
	private $fallback_conversion_rates = array();
	private $conversion_provider;

	public function __construct() {
		add_action('admin_init', array($this, 'admin_init'));
		add_action('wc_eu_vat_compliance_cc_tab_reports', array($this, 'wc_eu_vat_compliance_cc_tab_reports'));
// 		add_action('wc_eu_vat_report_begin', array($this, 'wc_eu_vat_report_begin'), 10, 2);
	}

	// Hook into control centre
	public function wc_eu_vat_compliance_cc_tab_reports() {
		echo '<h2>'.__('EU VAT Report', 'wc_eu_vat_compliance').'</h2>';
		$this->wc_eu_vat_compliance_report();
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

	// WC 2.2+ only
	private function get_items_data($start_date, $end_date, $status) {

		if (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.2', '<')) return false;

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
					if (empty($r->ID) || empty($r->k) || empty($r->v) || empty($r->oi)) continue;
					
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
						if (empty($current_line_tax_data['total'])) continue;
					}

					if (false !== $current_total && is_array($current_line_tax_data)) {
						$total = $current_line_tax_data['total'];
						foreach ($total as $tax_rate_id => $item_amount) {
							if (!isset($final_results[$tax_rate_id])) $final_results[$tax_rate_id] = 0;
							$final_results[$tax_rate_id] += $current_total;
						}
					}

				}
			} else {
				$fetch_more = false;
			}
			// Parse results further
		}

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

	// $print_as_csv will print and return nothing
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

		$hide_sidebar = true;

// 		echo '<p><strong>'.__('Notes:', 'wc_eu_vat_compliance').'</strong></p>';
		echo "<ul style=\"list-style-type: disc; list-style-position: inside;\">";
		echo '<li>'.__('The report below indicates the taxes actually charged on orders, when they were processed: it does not take into account later alterations manually made to order data.', 'wc_eu_vat_compliance').'</li>';

		$csv_message = apply_filters('wc_eu_vat_compliance_csv_message', '<a href="https://www.simbahosting.co.uk/s3/product/woocommerce-eu-vat-compliance/">'.__('Downloading all orders with VAT data in CSV format is a feature of the Premium version of this plugin.', 'wc_eu_vat_compliance').'</a>');

		echo "<li>$csv_message</li>";

// 		echo '<li>'.__('', 'wc_eu_vat_compliance').'</li>';
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

		<?php

		include(WooCommerce_EU_VAT_Compliance()->wc->plugin_path() . '/includes/admin/views/html-report-by-date.php');

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

// echo print_r_pre($results);

// var_dump($results['on-hold'][174]);
// return;

		$this->initialise_rate_provider();

		$this->reporting_currency = apply_filters('wc_eu_vat_vat_reporting_currency', get_option('woocommerce_eu_vat_compliance_vat_recording_currency'));
		if (empty($this->reporting_currency)) $this->reporting_currency = $base_currency;

		$reporting_currency_symbol = get_woocommerce_currency_symbol($this->reporting_currency);

		foreach ($results as $order_status => $result_set) {
			foreach ($result_set as $res) {
				if (!is_array($res) || empty($res['taxable_country']) || empty($res['vat_paid']) || !is_array($res['vat_paid']) || empty($res['vat_paid']['total'])) continue;

				$order_currency = (isset($res['_order_currency'])) ? $res['_order_currency'] : $base_currency;
				$country = $res['taxable_country'];

				$conversion_rates = isset($res['conversion_rates']) ? $res['conversion_rates'] : array();
				// Convert the 'vat_paid' array so that its values in the reporting currency, according to the conversion rates stored with the order
				$res_converted = $this->get_converted_vat_paid($res, $order_currency, $conversion_rates);
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

						if (!isset($by_rate[$rate_key])) $by_rate[$rate_key] = array('vat' => 0, 'vat_shipping' => 0);
						$by_rate[$rate_key]['vat'] += $rinfo['items_total']+$rinfo['shipping_total'];
						$by_rate[$rate_key]['vat_shipping'] += $rinfo['shipping_total'];

					}
				} else {
					// Legacy: no "by_rates" plugin versions also only allowed variable VAT
					$rate_key = 'V-'.__('Unknown', 'wc_eu_vat_compliance');
					if (!isset($by_rate[$rate_key])) $by_rate[$rate_key] = array('vat' => 0, 'vat_shipping' => 0);
					$by_rate[$rate_key]['vat'] += $vat_paid['total'];
					$by_rate[$rate_key]['vat_shipping'] += $vat_paid['shipping_total'];
				}

				foreach ($by_rate as $rate_key => $rate_data) {
					# VAT
					if (empty($tabulated_results[$order_status][$country][$rate_key]['vat'])) $tabulated_results[$order_status][$country][$rate_key]['vat'] = 0;
					$tabulated_results[$order_status][$country][$rate_key]['vat'] += $rate_data['vat'];

					# VAT (shipping)
					if (empty($tabulated_results[$order_status][$country][$rate_key]['vat_shipping'])) $tabulated_results[$order_status][$country][$rate_key]['vat_shipping'] = 0;
					$tabulated_results[$order_status][$country][$rate_key]['vat_shipping'] += $rate_data['vat_shipping'];
					
					# TODO: Items total, using the order_itemmeta and order_items tables
				}

				# Sales (net)
				// To do this (i.e. item sales per-VAT-rate), involves interrogating the order_itemmeta and order_items tables.
// 				if (empty($tabulated_results[$order_status][$country]['sales'])) $tabulated_results[$order_status][$country]['sales'] = 0;
// 				$tabulated_results[$order_status][$country]['sales'] += $res_converted['_order_total'];

			}
		}

		$this->report_table_header();

		$eu_total = 0;

		$countries = $compliance->wc->countries;
		$all_countries = $countries->countries;
		$eu_countries = $compliance->get_european_union_vat_countries();

		$total_vat_items = 0;
		$total_vat_shipping = 0;
		$total_vat = 0;

		$total_items = 0;
		$total_sales = 0;

// 		$tbody = '';

// var_dump($tabulated_results);
// return;


		foreach ($tabulated_results as $order_status => $results) {
			$status_text = $compliance->order_status_to_text($order_status);

			// TODO: Not yet used. Use it to display, on WC2.2+ only, the data on item sales that generated the corresponding VAT amounts
			// This returns an array; keys = tax rate IDs, values = total amount of orders taxed at these rates
			// N.B. The "total" column has no meaning when totaling those totals, as a single item may have attracted multiple taxes (theoretically)
			$get_items_data = $this->get_items_data($start_date, $end_date, $order_status);

			foreach ($results as $country => $per_rate_totals) {
				foreach ($per_rate_totals as $rate_key => $totals) {

					$country_label = isset($all_countries[$country]) ? $all_countries[$country] : __('Unknown', 'wc_eu_vat_compliance').' ('.$country.')';
					$country_label = '<span title="'.$country.'">'.$country_label.'</span>';

					$vat_items_amount = $compliance->round_amount($totals['vat']-$totals['vat_shipping']);
					$vat_shipping_amount = $compliance->round_amount($totals['vat_shipping']);
					$vat_total_amount = $compliance->round_amount($totals['vat']);

// 					$total_amount = $reporting_currency_symbol.' '.sprintf('%.02f', $totals['sales']);
// 					$items_amount = $reporting_currency_symbol.' '.sprintf('%.02f', $totals['sales']-$totals['vat']);

					$total_vat += $vat_total_amount;
					$total_vat_items += $vat_items_amount;
// 					$total_items += $totals['sales']-$totals['vat'];
					$total_vat_shipping += $vat_shipping_amount;
// 					$total_sales += $totals['sales'];

					if (preg_match('/^(V-)?([\d\.]+)$/', $rate_key, $matches)) {
						$vat_rate_label = str_replace('.00', '.0', $matches[2].'%');
						if (empty($matches[1])) {
							$vat_rate_label .= '<span title="'.esc_attr(__('Fixed - i.e., traditional non-variable VAT', 'wc_eu_vat_compliance')).'"> ('.__('fixed', 'wc_eu_vat_compliance').')</span>';
						}
					} else {
						$vat_rate_label = htmlspecialchars($rate_key);
					}

//data-items=\"".sprintf('%.05f', $totals['sales']-$totals['vat'])."\"
					echo "<tr data-vat-items=\"".$compliance->round_amount($vat_items_amount)."\"  data-vat-shipping=\"".$compliance->round_amount($vat_shipping_amount)."\" class=\"statusrow status-$order_status\">
						<td>$status_text</td>
						<td>$country_label</td>
						<td>$vat_rate_label</td>
						<td>$reporting_currency_symbol $vat_items_amount</td>
						<td>$reporting_currency_symbol $vat_shipping_amount</td>
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
				<td>-</td>
				<td><strong><?php echo $reporting_currency_symbol.' '.sprintf('%.2f', $total_vat_items); ?></strong></td>
				<td><strong><?php echo $reporting_currency_symbol.' '.sprintf('%.2f', $total_vat_shipping); ?></strong></td>
				<td><strong><?php echo $reporting_currency_symbol.' '.sprintf('%.2f', $total_vat); ?></strong></td>
			</tr>
			<?php

		$this->report_table_footer($reporting_currency_symbol);

		add_action('admin_footer', array($this, 'admin_footer'));
	}

	// public: used also in the CSV download
	public function get_converted_vat_paid($raw, $order_currency, $conversion_rates) {

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

		return $raw;
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
// 								var items = parseFloat(jQuery(citem).data('items'));
								var vat_items = parseFloat(jQuery(citem).data('vat-items'));
								var vat_shipping = parseFloat(jQuery(citem).data('vat-shipping'));
								var vat = vat_items + vat_shipping;
								total_vat += vat;
								total_vat_items += vat_items;
								total_vat_shipping += vat_shipping;
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
			<td>-</td>\
			<td><strong>'+currency_symbol+' '+parseFloat(total_vat_items).toFixed(2)+'</strong></td>\
			<td><strong>'+currency_symbol+' '+parseFloat(total_vat_shipping).toFixed(2)+'</strong></td>\
			<td><strong>'+currency_symbol+' '+parseFloat(total_vat).toFixed(2)+'</strong></td>\
		</tr>\
					');
// 			<td><strong>'+currency_symbol+' '+parseFloat(total_items).toFixed(2)+'</strong></td>\

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
/* 				<th><?php _e('Items (without VAT)', 'wc_eu_vat_compliance');?></th> */
	?>
		<table class="widefat" id="wc_eu_vat_compliance_report">
		<thead>
			<tr>
				<th><?php _e('Order Status', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('Country', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('VAT rate', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('VAT on items', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('VAT on shipping', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('Total VAT', 'wc_eu_vat_compliance');?></th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<th><?php _e('Order Status', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('Country', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('VAT rate', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('VAT on items', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('VAT on shipping', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('Total VAT', 'wc_eu_vat_compliance');?></th>
			</tr>
		</tfoot>
		<tbody>
	<?php
	}

}