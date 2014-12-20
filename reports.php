<?php

if (!defined('WC_EU_VAT_COMPLIANCE_DIR')) die('No direct access');

// Purpose: provide a report on VAT paid, to help with EU VAT compliance

// TODO: Need an option to include VAT calculations from before when the plugin was installed ... ?
// TODO: WC 2.2
// TODO: Move relevant multi-currency bits into premium, if relevant. (Probably all can stay here).
// TODO: Testing (already done?): create a second tax for a country - can we detect that?
// TODO: Download links

class WC_EU_VAT_Compliance_Reports {

	public function __construct() {
		add_filter('admin_init', array($this, 'admin_init'));
		add_action('wc_eu_vat_compliance_cc_tab_reports', array($this, 'wc_eu_vat_compliance_cc_tab_reports'));
	}

	// Hook into control centre
	public function wc_eu_vat_compliance_cc_tab_reports() {
		echo '<h2>'.__('EU VAT Report', 'wc_eu_vat_compliance').'</h2>';
		$this->wc_eu_vat_compliance_report();
	}

	public function admin_init() {
		if (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.1', '<')) {
			add_filter('woocommerce_reports_charts', array($this, 'eu_vat_report_wc20'));
		} else {
			add_filter('woocommerce_admin_reports', array($this, 'eu_vat_report'));
		}
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
		$reports['orders']['reports']['eu_vat_report'] = array(
			'title'       => __('EU VAT Report', 'wc_eu_vat_compliance'),
			'description' => '',
			'hide_title'  => false,
			'callback'    => array($this, 'wc_eu_vat_compliance_report')
		);
		return $reports;
	}

	private function get_report_sql($page_start, $page_size, $start_date, $end_date) {

		global $table_prefix, $wpdb;

		$tax_based_on_extra = '';
		$tax_based_on = get_option( 'woocommerce_tax_based_on' );
		if ('billing' == $tax_based_on) {
			$tax_based_on_extra = ", '_billing_country'";
		} elseif ('shipping' == $tax_based_on) {
			$tax_based_on_extra = ", '_shipping_country'";
		}

// TODO: seems redundant: AND (term.slug IN ('completed', 'processing', 'on-hold', 'pending', 'refunded', 'cancelled', 'failed'))

		if (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.2', '<')) {

			$sql = "SELECT
					orders.ID
					,orders.post_date
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
					AND (term.slug IN ('completed', 'processing', 'on-hold', 'pending', 'refunded', 'cancelled', 'failed'))
					AND orders.post_date >= '$start_date 00:00:00'
					AND orders.post_date <= '$end_date 23:59:59'
					AND order_meta.meta_key IN ('_billing_state', '_billing_country', '_order_currency', '_order_tax', '_order_tax_base_currency', '_order_total', '_order_total_base_currency', 'vat_compliance_country_info', 'vat_compliance_vat_paid', 'Valid EU VAT Number', 'VAT Number', 'VAT number validated' $tax_based_on_extra)
				ORDER BY
					orders.ID desc
					,order_meta.meta_key
				LIMIT $page_start, $page_size
			";

		} else {
			$sql = null;
		}

		if (!$sql) return false;

		return $sql;
	}

	private function get_report_results($start_date, $end_date, $remove_non_eu_countries = true) {

		global $wpdb;

		$sql_vat_matches = WooCommerce_EU_VAT_Compliance()->get_vat_matches('sqlregex');

		$eu_countries = WooCommerce_EU_VAT_Compliance()->wc->countries->get_european_union_countries();

		$page = 0;
		$page_size = 1000;

		$fetch_more = true;

		$normalised_results = array();

		$tax_based_on = get_option( 'woocommerce_tax_based_on' );

		while ($fetch_more) {
			$page_start = $page_size * $page;
			$results_this_time = 0;
			$sql = $this->get_report_sql($page_start, $page_size, $start_date, $end_date);
			if (empty($sql)) break;

			$results = $wpdb->get_results($sql);
			if (!empty($results)) {
				$page++;
				foreach ($results as $res) {
					if (empty($res->ID)) continue;
					$order_id = $res->ID;
					$order_status = $res->order_status;
					if (empty($normalised_results[$order_status][$order_id])) {
						$normalised_results[$order_status][$order_id] = array('date_gmt' => $res->post_date_gmt);
					}
					switch ($res->meta_key) {
						case 'vat_compliance_country_info';
							$cinfo = maybe_unserialize($res->meta_value);
							$vat_country = (empty($cinfo['taxable_address'])) ? '??' : $cinfo['taxable_address'];
							if (!empty($vat_country[0])) {
								if ($remove_non_eu_countries && !in_array($vat_country[0], $eu_countries)) {
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
						case 'VAT Number';
							$normalised_results[$order_status][$order_id]['vatno'] = $res->meta_value;
						break;
						case 'VAT number validated';
							$normalised_results[$order_status][$order_id]['vatno_validated'] = $res->meta_value;
						break;
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
						$res['taxable_country'] = isset($res['_billing_country']) ? : '';
						break;
						case 'shipping' :
						$res['taxable_country'] = isset($res['_shipping_country']) ? : '';
						break;
						default:
						unset($normalised_results[$order_status][$order_id]);
						break;
					}
					unset($res['_billing_country']);
					unset($res['_shipping_country']);
				}

				if (!isset($res['vat_paid'])) {
					$normalised_results[$order_status][$order_id]['vat_paid'] = WooCommerce_EU_VAT_Compliance()->get_vat_paid($order_id, true, true);
				}

				// N.B. Use of empty() means that those with zero VAT are also excluded at this point
				if (empty($res['vat_paid'])) {
					unset($normalised_results[$order_status][$order_id]);
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

		echo '<p><strong>'.__('Notes:', 'wc_eu_vat_compliance').'</strong></p>';
		echo "<ul style=\"max-width: 800px;list-style-type: disc; list-style-position: inside;\">";
		echo '<li>'.__('This is the first release of reporting; more features (including downloading comprehensive order-by-order data, and making the tables sortable) are being worked on.', 'wc_eu_vat_compliance').'</li>';
		echo '<li>'.__('When you change which order statuses are shown, the totals will automatically update. You can use this to retrieve information about your refunds. If you change dates, you will need to press the "Go" button.').'</li>';
		echo '<li>'.__('The report below indicates the taxes actually charged on orders, when they were processed: it does not .', 'wc_eu_vat_compliance').'</li>';
		echo '<li>'.__('The sales total below includes shipping, if there was any; similarly, the VAT shown includes VAT charged upon shipping, if there was any.', 'wc_eu_vat_compliance').'</li>';
// 		echo '<li>'.__('', 'wc_eu_vat_compliance').'</li>';
		echo "</ul>";


				?>

		<form id="wceuvat_report_form" method="post" style="padding-bottom:8px;">
			<?php
				$print_fields = array('page', 'tab', 'report', 'chart');
				foreach ($print_fields as $field) {
					if (isset($_REQUEST[$field])) {
						echo '<input type="hidden" name="'.$field.'" value="'.$_REQUEST[$field].'">'."\n";
					}
				}

				do_action('wc_eu_vat_report_begin');

			?>

			<p>

			<?php
				_e('Statuses:', 'wc_eu_vat_compliance');

				$statuses = WooCommerce_EU_VAT_Compliance()->order_status_to_text(true);

				foreach ($statuses as $label => $text) {

					$use_label = (substr($label, 0, 3) == 'wc-') ? substr($label, 3) : $label;
					$checked = !isset($_POST['wceuvat_go']) ? ' checked="checked"' : ((is_array($_POST['order_statuses']) && in_array($use_label, $_POST['order_statuses'])) ? ' checked="checked"' : '');

					echo "\n".'<input type="checkbox"'.$checked.' class="wceuvat_report_status" name="order_statuses[]" id="order_status_'.$use_label.'" value="'.$use_label.'"><label for="order_status_'.$use_label.'" style="margin-right: 10px;">'.$text.'</label> ';
				}

			?>

			</p>

		</form>

		<?php
		global $wpdb;

		$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
		$end_date    = isset($_POST['end_date']) ? $_POST['end_date'] : '';

// TODO: Need to actually tell the user what the default is. And think if this is a sensible default.
		if (! $start_date )
			$start_date = date('Y-01-01', current_time('timestamp'));
		if (! $end_date )
			$end_date = date('Y-m-d', current_time('timestamp'));

// TODO: Test. Test refunds.

		if (defined('WOOCOMMERCE_VERSION') && !version_compare(WOOCOMMERCE_VERSION, '2.2', '<')) {
			echo '<strong>'.__('Currently, reports are only supported on WooCommerce versions earlier than the 2.2 series. This is being worked on!', 'wc_eu_vat_compliance').'</strong>';
			return;
		}


		$results = $this->get_report_results($start_date, $end_date);

		if ($wpdb->last_error) {
			echo htmlspecialchars($wpdb->last_error);
			return;
		}

		// Further processing. Need to index by country and then by currency
		$tabulated_results = array();

		$base_currency = get_option('woocommerce_currency');
		$base_currency_symbol = get_woocommerce_currency_symbol($base_currency);

		foreach ($results as $order_status => $result_set) {
			foreach ($result_set as $res) {
				if (!is_array($res) || empty($res['taxable_country']) || empty($res['vat_paid']) || !is_array($res['vat_paid']) || empty($res['vat_paid']['total'])) continue;

				$order_currency = (isset($res['_order_currency'])) ? $res['_order_currency'] : $base_currency;
				$country = $res['taxable_country'];

				# VAT
				if (empty($tabulated_results[$order_status][$country][$order_currency]['vat'])) $tabulated_results[$order_status][$country][$order_currency]['vat'] = 0;
				$tabulated_results[$order_status][$country][$order_currency]['vat'] += $res['vat_paid']['total'];

				# Sales (net)
				if (empty($tabulated_results[$order_status][$country][$order_currency]['sales'])) $tabulated_results[$order_status][$country][$order_currency]['sales'] = 0;
				$tabulated_results[$order_status][$country][$order_currency]['sales'] += $res['_order_total'];


				if (!empty($res['_order_total_base_currency'])) {
					$base_add = $res['vat_paid']['total_base_currency'];
					$base_add_sales = $res['_order_total_base_currency'];
				} else {
					$base_add = $res['vat_paid']['total'];
					$base_add_sales = $res['_order_total'];
				}

				# N.B. We later rely on the fact that the base_currency key is placed on at the end (saves us having to sort to make it so)

				# VAT (base currency)
				if (empty($tabulated_results[$order_status][$country][$order_currency]['vat_base'])) $tabulated_results[$order_status][$country][$order_currency]['vat_base'] = 0;
				$tabulated_results[$order_status][$country][$order_currency]['vat_base'] += $base_add;

				# Sales (net) (base currency)
				if (empty($tabulated_results[$order_status][$country][$order_currency]['sales_base'])) $tabulated_results[$order_status][$country][$order_currency]['sales_base'] = 0;
				$tabulated_results[$order_status][$country][$order_currency]['sales_base'] += $base_add_sales;
			}
		}

		$rates = WooCommerce_EU_VAT_Compliance('WC_EU_VAT_Compliance_Rates');

// TODO: Button to download this table as CSV.

// TODO: Make this table sortable (saves us having to output multiple table styles);

		$this->report_table_header();

		$eu_total = 0;
		// TODO: Split by currency (but sort by country)

		$compliance =  WooCommerce_EU_VAT_Compliance();

		$countries = $compliance->wc->countries;

		$all_countries = $countries->countries;
		$eu_countries = $countries->get_european_union_countries();

// 		var_dump($tabulated_results);

		$total_vat = array();
		$total_sales = array();
		$total_sales_total = array();

		$total_vat_base = 0;
		$total_sales_base = 0;
		$total_sales_total_base = 0;

		foreach ($tabulated_results as $order_status => $results) {
			$status_text = $compliance->order_status_to_text($order_status);

// 			$refund_factor = ($order_status == 'refunded' || $order_status == 'wc-refunded') ? -1 : 1;

			foreach ($results as $country => $res) {
// 				$currencies_done = array();


				foreach ($res as $currency => $totals) {

					if (!isset($total_vat[$currency])) $total_vat[$currency] = 0;
					if (!isset($total_sales[$currency])) $total_sales[$currency] = 0;
					if (!isset($total_sales_total[$currency])) $total_sales_total[$currency] = 0;

// 					// Don't double-print the base currency
// 					if ($currency == 'base_currency' && in_array($base_currency, $currencies_done)) continue;
// 					if ($currency == 'base_currency') $currency = $base_currency;
// 					$currencies_done[] = $currency;
// 			$eu_total += $value->sale_total;

// Useful:  get_woocommerce_currency_symbol();
					$country_label = isset($all_countries[$country]) ? $all_countries[$country] : __('Unknown', 'wc_eu_vat_compliance').' ('.$country.')';

					$standard_rate = $rates->get_vat_rate_for_country($country, 'standard_rate');
					$reduced_rate = $rates->get_vat_rate_for_country($country, 'reduced_rate');

					$vat_amount = get_woocommerce_currency_symbol($currency).' '.sprintf('%.02f', $totals['vat']);
					$sales_total_amount = get_woocommerce_currency_symbol($currency).' '.sprintf('%.02f', $totals['sales']);
					$sales_amount = get_woocommerce_currency_symbol($currency).' '.sprintf('%.02f', $totals['sales']-$totals['vat']);

					$total_vat[$currency] += $totals['vat'];
					$total_sales[$currency] += $totals['sales']-$totals['vat'];
					$total_sales_total[$currency] += $totals['sales'];

					$total_vat_base += $totals['vat_base'];
					$total_sales_base += $totals['sales_base']-$totals['vat_base'];
					$total_sales_total_base += $totals['sales_base'];

					if ($currency != $base_currency) {

						if (isset($totals['vat_base'])) {
							$vat_amount .= " ($base_currency_symbol ".sprintf('%.02f', $totals['vat_base']).")";
						}

						if (isset($totals['sales_base'])) {
							$sales_amount .= " ($base_currency_symbol ".sprintf('%.02f', $totals['sales_base']-(isset($totals['vat_base']) ? $totals['vat_base'] : 0)).")";

							if (isset($totals['vat_base'])) {
								$sales_total_amount .= " ($base_currency_symbol ".sprintf('%.02f', $totals['sales_base']).")";
							}

						}

					}

					?>
					<tr data-currency="<?php echo $currency;?>" data-vat="<?php printf('%.02f', $totals['vat']);?>" data-sales="<?php printf('%.02f', $totals['sales']);?>" class="statusrow status-<?php echo $order_status;?> currency-<?php echo $currency;?>">
						<td><?php echo $status_text;?></td>
						<td><?php echo $country_label; ?></td>
						<td><?php echo sprintf('%.1f', $standard_rate);?> %</td>
						<td><?php echo sprintf('%.1f', $reduced_rate);?> %</td>
						<td><?php echo $sales_amount;?></td>
						<td><?php echo $vat_amount; ?></td>
						<td><?php echo $sales_total_amount; ?></td>
					</tr>
					<?php

				}

			}
		}

		$currencies = array();

		foreach (array_keys($total_vat) as $currency) {
			$currencies[$currency] = get_woocommerce_currency_symbol($currency);
		?>
		<tr class="wc_eu_vat_compliance_total" id="wc_eu_vat_compliance_total_<?php echo $currency;?>">
			<td><strong><?php echo __('Grand Total', 'wc_eu_vat_compliance').'<br>('.$currency.' '.__('orders', 'wc_eu_vat_compliance').')';?></strong></td>
			<td>-</td>
			<td>-</td>
			<td>-</td>
			<td><strong><?php echo get_woocommerce_currency_symbol($currency).' '.sprintf('%.02f', $total_sales[$currency]); ?></strong></td>
			<td><strong><?php echo get_woocommerce_currency_symbol($currency).' '.sprintf('%.02f', $total_vat[$currency]); ?></strong></td>
			<td><strong><?php echo get_woocommerce_currency_symbol($currency).' '.sprintf('%.02f', $total_sales_total[$currency]); ?></strong></td>
		</tr>
		<?php
		}

		if (!in_array($base_currency, array_keys($total_vat))) {
			$currencies[$base_currency] = $base_currency_symbol;
		?>
		<tr class="wc_eu_vat_compliance_total" id="wc_eu_vat_compliance_<?php echo $base_currency; ?>">
			<td><strong><?php echo __('Grand Total', 'wc_eu_vat_compliance').'<br>('.$base_currency.' '.__('orders', 'wc_eu_vat_compliance').')';?></strong></td>
			<td>-</td>
			<td>-</td>
			<td>-</td>
			<td><strong><?php echo $base_currency_symbol.' '.sprintf('%.2f', $total_sales_base); ?></strong></td>
			<td><strong><?php echo $base_currency_symbol.' '.sprintf('%.2f', $total_vat_base); ?></strong></td>
			<td><strong><?php echo $base_currency_symbol.' '.sprintf('%.2f', $total_sales_total_base); ?></strong></td>
		</tr>
		<?php
		}

		$this->report_table_footer($base_currency, $currencies);
		?>

		<?php
	}

	private function report_table_footer($base_currency, $currencies) {
	?>
		</tbody>
		</table>
		<script>
			jQuery(document).ready(function() {
				var base_currency = '<?php echo esc_js($base_currency, $currencies);?>';
				var currency_symbols = { <?php $first_one = true; foreach ($currencies as $cur => $sym) { if (!$first_one) echo ", "; else { $first_one = false; }; echo "$cur: '$sym'";}; ?> };
				function update_table() {
					// Hide them all, then selectively re-show
					jQuery('#wc_eu_vat_compliance_report tbody tr.statusrow').hide();
					// Get the checked statuses
					var total_vat = {};
					var total_sales = {};
					jQuery('#wceuvat_report_form input.wceuvat_report_status').each(function(ind, item) {
						var status_id = jQuery(item).attr('id');
						if (status_id.substring(0, 13) == 'order_status_' && jQuery(item).prop('checked')) {
							var row_items = jQuery('#wc_eu_vat_compliance_report tbody tr.status-'+status_id.substring(13));
							jQuery(row_items).show();
							jQuery(row_items).each(function(cind, citem) {
								var currency = jQuery(citem).data('currency');
								if ('' != currency) {
									var vat = parseFloat(jQuery(citem).data('vat'));
									var sales = parseFloat(jQuery(citem).data('sales'));
									if (!total_vat.hasOwnProperty(currency)) { total_vat[currency] = 0; }
									if (!total_sales.hasOwnProperty(currency)) { total_sales[currency] = 0; }
									total_vat[currency] += vat;
									total_sales[currency] += sales;
								};
							});
						};
					});
					if (!total_vat.hasOwnProperty(base_currency)) {
						total_vat[base_currency] = 0;
						total_sales[base_currency] = 0;
					}
					// Rebuild totals
					jQuery('#wc_eu_vat_compliance_report tr.wc_eu_vat_compliance_total').remove();
					jQuery.each(total_vat, function(index, item) {
						var net_sales = total_sales[index] - total_vat[index];
						jQuery('#wc_eu_vat_compliance_report tbody').append('\
		<tr class="wc_eu_vat_compliance_total" id="wc_eu_vat_compliance_'+index+'">\
			<td><strong><?php echo __('Grand Total', 'wc_eu_vat_compliance');?><br>('+index+' <?php _e('orders', 'wc_eu_vat_compliance');?>)</strong></td>\
			<td>-</td>\
			<td>-</td>\
			<td>-</td>\
			<td><strong>'+currency_symbols[index]+' '+parseFloat(net_sales).toFixed(2)+'</strong></td>\
			<td><strong>'+currency_symbols[index]+' '+parseFloat(total_vat[index]).toFixed(2)+'</strong></td>\
			<td><strong>'+currency_symbols[index]+' '+parseFloat(total_sales[index]).toFixed(2)+'</strong></td>\
		</tr>\
						');
					});
				};

				update_table();

				jQuery('#wceuvat_report_form .wceuvat_report_status').change(function() {
					update_table();
				});
			});
		</script>
	<?php
	}

	private function report_table_header() {
	?>
		<table class="widefat" id="wc_eu_vat_compliance_report">
		<thead>
			<tr>
				<th><?php _e('Order Status', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('Country', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('VAT rate (standard)', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('VAT rate (reduced)', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('Sales (without VAT)', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('VAT', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('Total paid', 'wc_eu_vat_compliance');?></th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<th><?php _e('Order Status', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('Country', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('VAT rate (standard)', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('VAT rate (reduced)', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('Sales (without VAT)', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('VAT', 'wc_eu_vat_compliance');?></th>
				<th><?php _e('Total paid', 'wc_eu_vat_compliance');?></th>
			</tr>
		</tfoot>
		<tbody>
	<?php
	}

}