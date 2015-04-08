<?php

if (!defined('WC_EU_VAT_COMPLIANCE_DIR')) die('No direct access');

// Function: record the GeoIP information for the order, at order time. This module uses either the CloudFlare header (if available - https://support.cloudflare.com/hc/en-us/articles/200168236-What-does-CloudFlare-IP-Geolocation-do-), or the geo-location class built-in to WC 2.3+, or requires http://wordpress.org/plugins/geoip-detect/. Or, you can hook into it and use something else. It will always record something, even if the something is the information that nothing could be worked out.

// The information is stored as order meta, with key: update_post_meta

if (class_exists('WC_EU_VAT_Compliance_Record_Order_Country')) return;
class WC_EU_VAT_Compliance_Record_Order_Country {

	private $wc;

	public function __construct() {
		add_action('woocommerce_checkout_update_order_meta', array($this, 'woocommerce_checkout_update_order_meta'));

//		add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'woocommerce_admin_order_data_after_shipping_address'));

		add_action('woocommerce_checkout_order_processed', array($this, 'woocommerce_checkout_order_processed'));

		add_action('add_meta_boxes_shop_order', array($this, 'add_meta_boxes_shop_order'));

		$this->compliance = WooCommerce_EU_VAT_Compliance();

	}

	public function add_meta_boxes_shop_order() {
		add_meta_box('wc_eu_vat_vat_meta',
			__('EU VAT compliance information', 'wc_eu_vat_compliance'),
			array($this, 'meta_box_shop_order'),
			'shop_order',
			'side',
			'default'
		);
	}

	public function meta_box_shop_order() {
		global $post;
		$this->print_order_vat_info($post->ID);
	}

	public function woocommerce_checkout_update_order_meta($order_id) {

		// Note: whilst this records the country via GeoIP resolution, that does not indicate which tax WooCommerce applies - that will be determined by the user's WooCommerce settings. The GeoIP data is recorded for compliance purposes.

		// Record the information about the customer's location in the order meta
		$compliance = WooCommerce_EU_VAT_Compliance();

		$country_info = $compliance->get_visitor_country_info();
		$country_info['taxable_address'] = $this->get_taxable_address();

		update_post_meta($order_id, 'vat_compliance_country_info', apply_filters('wc_eu_vat_compliance_meta_country_info', $country_info));

		// Record the current conversion rates in the order meta
		$this->record_conversion_rates_and_other_meta($order_id);

	}

	private function get_taxable_address() {

		$compliance = WooCommerce_EU_VAT_Compliance();
		if (function_exists('WC') && version_compare(WC()->version, '2.3', '>=')) {
			$tax = new WC_Tax;
		} else {
			$tax = $compliance->wc->cart->tax;
		}
		$customer = $compliance->wc->customer;

		if (method_exists($tax, 'get_tax_location')) {
			$taxable_address = $tax->get_tax_location();
		} elseif (method_exists($customer, 'get_taxable_address')) {
			$taxable_address = $customer->get_taxable_address();
		} else {
			$taxable_address = array();
		}

		return $taxable_address;
	}

	public function record_conversion_rates_and_other_meta($order_id) {

		$compliance = WooCommerce_EU_VAT_Compliance();
		$order = $compliance->get_order($order_id);

		// Record order number; see: http://docs.woothemes.com/document/sequential-order-numbers/
		update_post_meta($order_id, 'order_time_order_number', $order->get_order_number());

		$conversion_provider = get_option('woocommerce_eu_vat_compliance_exchange_rate_provider', 'ecb');

		$providers = $compliance->get_rate_providers();
		if (!is_array($providers) || !isset($providers[$conversion_provider])) return;
		$provider = $providers[$conversion_provider];

		$record_currencies = apply_filters('wc_eu_vat_vat_recording_currencies', get_option('woocommerce_eu_vat_compliance_vat_recording_currency'));
		if (empty($record_currencies)) $record_currencies = array();
		if (!is_array($record_currencies)) $record_currencies = array($record_currencies);

		$order_time = strtotime($order->order_date);
		if (method_exists($order, 'get_order_currency')) {
			$order_currency = $order->get_order_currency();
		} else {
			$order_currency = get_option('woocommerce_currency');
		}

		$conversion_rates = array('meta' => array('order_currency' => $order_currency), 'rates' => array());

		foreach ($record_currencies as $vat_currency) {
			if (!is_string($vat_currency) || $order_currency == $vat_currency) continue;
			// Returns the conversion for 1 unit of the order currency.
			$result = $provider->convert($order_currency, $vat_currency, 1);
			// Legacy
// 			if ($result) update_post_meta($order_id, 'wceuvat_conversion_rate_'.$order_currency.'_'.$vat_currency, $result);
			if ($result) {
				$conversion_rates['rates'][$vat_currency] = $result;
				$conversion_rates['meta']['provider'] = $conversion_provider;
			}
		}

		update_post_meta($order_id, 'wceuvat_conversion_rates', $conversion_rates);
	}


	public function woocommerce_checkout_order_processed($order_id) {
		$this->record_meta_vat_paid($order_id);
	}

	public function record_meta_vat_paid($order_id) {
		$compliance = WooCommerce_EU_VAT_Compliance();
		$vat_paid = $compliance->get_vat_paid($order_id);
		$order = $compliance->get_order($order_id);
		$post_id = (isset($order->post)) ? $order->post->ID : $order->id;
		update_post_meta($post_id, 'vat_compliance_vat_paid', apply_filters('wc_eu_vat_compliance_vat_paid', $vat_paid, $order));
	}

	// Show recorded information on the admin page
	private function print_order_vat_info($post_id) {

		$compliance = WooCommerce_EU_VAT_Compliance();

// 		$post_id = (isset($order->post)) ? $order->post->ID : $order->id;
		$order = $compliance->get_order($post_id);
		$country_info = get_post_meta($post_id, 'vat_compliance_country_info', true);

		echo '<p id="wc_eu_vat_compliance_countryinfo">';

//		echo '<strong>'.__("EU VAT Compliance Information", 'wc_eu_vat_compliance').':</strong><br>';

		if (empty($country_info) || !is_array($country_info)) {
			echo '<em>'.__('No further information recorded (the EU VAT Compliance plugin was not active when this order was made).', 'wc_eu_vat_compliance').'</em>';
			if (function_exists('geoip_detect_get_info_from_ip') && $ip = get_post_meta($post_id, '_customer_ip_address', true)) {
				$country_info = $compliance->construct_country_info($ip);
				if (!empty($country_info) && is_array($country_info)) {
					echo ' '.__("The following information is based upon looking up the customer's IP address now.", 'wc_eu_vat_compliance');
				}
			}
			echo '<br>';
		}

		// Relevant function: get_woocommerce_currency_symbol($currency = '')
		$vat_paid = $compliance->get_vat_paid($order, true, true);
	
		if (is_array($vat_paid)) {

			$order_currency = isset($vat_paid['currency']) ? $vat_paid['currency'] : (method_exists($order, 'get_order_currency') ? $order->get_order_currency() : get_option('woocommerce_currency'));

			// This should not be possible - but, it is best to err on the side of caution
			if (!isset($vat_paid['by_rates'])) $vat_paid['by_rates'] = array(array('items_total' => $vat_paid['items_total'], 'is_variable_eu_vat' => 1, 'shipping_total' => $vat_paid['shipping_total'], 'rate' => '??', 'name' => __('VAT', 'wc_eu_vat_compliance')));

			// What currencies is VAT meant to be reported in?
			$conversion_rates =  get_post_meta($post_id, 'wceuvat_conversion_rates', true);

			if (is_array($conversion_rates) && isset($conversion_rates['rates'])) {
				$conversion_currencies = array_keys($conversion_rates['rates']);
				if (count($conversion_rates['rates']) > 0) $conversion_provider_key = isset($conversion_rates['meta']['provider']) ? $conversion_rates['meta']['provider'] : '??';
			} else {
				$conversion_currencies = array();
				# Convert from legacy format - only existed for 2 days from 24-Dec-2014; can be removed later.
				$record_currencies = apply_filters('wc_eu_vat_vat_recording_currencies', get_option('woocommerce_eu_vat_compliance_vat_recording_currency'));
				if (empty($record_currencies)) $record_currencies = array();
				if (!is_array($record_currencies)) $record_currencies = array($record_currencies);
				if (count($record_currencies) == 1) {
					$try_currency = array_shift($record_currencies);
					$conversion_rate = get_post_meta($post_id, 'wceuvat_conversion_rate_'.$order_currency.'_'.$try_currency, true);
					if (!empty($conversion_rate)) {
						$conversion_provider_key = '??';
						$conversion_rates = array('order_currency' => $order_currency, 'rates' => array($try_currency => $conversion_rate));
						$conversion_currencies = array($try_currency);
						update_post_meta($post_id, 'wceuvat_conversion_rates', $conversion_rates);
					}
				}
			}

			// TODO: Handle rounding as WC does

			// A default - redundant
// 			if (empty($conversion_currencies)) $conversion_currencies = array($order_currency);

			if (!in_array($order_currency, $conversion_currencies)) $conversion_currencies[] = $order_currency;

			# Show the recorded currency conversion rate(s)
			$currency_title = '';
			if (isset($conversion_rates['rates']) && is_array($conversion_rates['rates'])) {
				foreach ($conversion_rates['rates'] as $cur => $rate) {
					$currency_title .= sprintf("1 unit %s = %s units %s\n", $order_currency, $rate, $cur);
				}
			}

			$items_total = false;
			$shipping_total = false;
			$total_total = false;

			$refunded_items_total = false;
			$refunded_shipping_total = false;
			$refunded_shipping_tax_total = false;
			$refunded_total_total = false;

			// Any tax refunds?
			if ($compliance->at_least_22) {
				// This method only exists on WC 2.3+
				$total_tax_refunded = (method_exists($order, 'get_total_tax_refunded')) ? $order->get_total_tax_refunded() : $this->get_total_tax_refunded($order->id);
				if ($total_tax_refunded > 0) {
					$any_tax_refunds_exist = true;
				}
			}

			foreach ($vat_paid['by_rates'] as $rate_id => $vat) {

				$refunded_item_amount = 0;
				$refunded_shipping_amount = 0;
				$refunded_tax_amount = 0;
				$refunded_shipping_tax_amount = 0;

				if (!empty($any_tax_refunds_exist)) {
					// This loop is adapted from WC_Order::get_total_tax_refunded_by_rate_id() (WC 2.3+)
					foreach ( $order->get_refunds() as $refund ) {
						foreach ( $refund->get_items( 'tax' ) as $refunded_item ) {
							if ( isset( $refunded_item['rate_id'] ) && $refunded_item['rate_id'] == $rate_id ) {
								$refunded_tax_amount += abs( $refunded_item['tax_amount'] );
								$refunded_shipping_tax_amount += abs( $refunded_item['shipping_tax_amount'] );
							}
						}
						foreach ( $refund->get_items( 'shipping' ) as $refunded_item ) {
							if (!isset($refunded_item['taxes'])) continue;
							$tax_data = maybe_unserialize($refunded_item['taxes']);
							// Was the current tax rate ID used on this item?
							if ( !empty( $tax_data[$rate_id] )) {
								// Minus, because we want to end up with a positive amount, so that all the $refunded_ variables are consistent.
								$refunded_shipping_amount -= $refunded_item['cost'];
								// Don't add it again here - it's already added above
// 								$refunded_shipping_tax_amount -= $tax_data[$rate_id];
							}
						}
						foreach ( $refund->get_items() as $refunded_item ) {
							if (!isset($refunded_item['line_tax_data'])) continue;
							$tax_data = maybe_unserialize($refunded_item['line_tax_data']);
							// Was the current tax rate ID used on this item?
							if ( !empty( $tax_data['total'][$rate_id] )) {
								// Minus, because we want to end up with a positive amount, so that all the $refunded_ variables are consistent.
								$refunded_item_amount -= $refunded_item['line_total'];
							}
						}
// 						if ($refunded_item_amount >0 || $refunded_tax_amount>0 || $refunded_shipping_amount || $refunded_shipping_tax_amount) {
// 							var_dump($refunded_item_amount);
// 							var_dump($refunded_tax_amount);
// 							var_dump($refunded_shipping_amount);
// 							var_dump($refunded_shipping_tax_amount);
// 						}
					}
				}

				$items_total += $vat['items_total'];
				$shipping_total += $vat['shipping_total'];
				$total_total += $vat['items_total'] + $vat['shipping_total'];

				$refunded_items_total += $refunded_item_amount;
				$refunded_shipping_total += $refunded_shipping_amount;
				$refunded_shipping_tax_total += $refunded_shipping_tax_amount;
				$refunded_total_total += $refunded_tax_amount + $refunded_shipping_tax_amount;

				$items = $compliance->get_amount_in_conversion_currencies($vat['items_total'], $conversion_currencies, $conversion_rates, $order_currency);

				$shipping = $compliance->get_amount_in_conversion_currencies($vat['shipping_total'], $conversion_currencies, $conversion_rates, $order_currency);
				$total = $compliance->get_amount_in_conversion_currencies($vat['items_total']+$vat['shipping_total'], $conversion_currencies, $conversion_rates, $order_currency);

				// When it is not set, we have legacy data format (pre 1.7.0), where all VAT-able items were assumed to be digital
				if (isset($vat['is_variable_eu_vat']) && !$vat['is_variable_eu_vat']) {
					$extra_title = '<em>'._x('(Traditional VAT)', 'Traditional VAT = VAT that does not vary by country under the new digital regulations; i.e. the VAT still charged on physical goods until 1 Jan 2016', 'wc_eu_vat_compliance').'</em><br>';
				} else {
					$extra_title = '';
				}

				echo '<strong>'.$vat['name'].' ('.sprintf('%0.2f', $vat['rate']).' %)</strong><br>'.$extra_title;

				echo __('Items', 'wc_eu_vat_compliance').': '.$items.'<br>';
				if ($refunded_tax_amount) {
					$refunded_taxes = $compliance->get_amount_in_conversion_currencies($refunded_tax_amount*-1, $conversion_currencies, $conversion_rates, $order_currency);
					echo __('Items refunded', 'wc_eu_vat_compliance').': '.$refunded_taxes;
					echo '<br>';
				}

				echo __('Shipping', 'wc_eu_vat_compliance').': '.$shipping.'<br>';
				if ($refunded_shipping_tax_amount) {
					echo __('Shipping refunded', 'wc_eu_vat_compliance').': '.$compliance->get_amount_in_conversion_currencies($refunded_shipping_tax_amount*-1, $conversion_currencies, $conversion_rates, $order_currency).'';
					echo '<br>';
				}

				if ($refunded_tax_amount || $refunded_shipping_tax_amount) {
					$total_after_refunds = $vat['items_total']+$vat['shipping_total'] - ($refunded_tax_amount + $refunded_shipping_tax_amount);
					$total_after_refunds_converted = $compliance->get_amount_in_conversion_currencies($total_after_refunds, $conversion_currencies, $conversion_rates, $order_currency);
					echo __('Total (including refunds)', 'wc_eu_vat_compliance').': '.$total_after_refunds_converted;
					echo '<br>';
				} else {
					echo __('Total', 'wc_eu_vat_compliance').': '.$total.'<br>';
				}

			}

			if (count($vat_paid['by_rates']) > 1) {

				$items = $compliance->get_amount_in_conversion_currencies(($items_total === false) ? $vat_paid['items_total'] : $items_total, $conversion_currencies, $conversion_rates, $order_currency);

				$shipping = $compliance->get_amount_in_conversion_currencies(($shipping_total === false) ? $vat_paid['shipping_total'] : $shipping_total, $conversion_currencies, $conversion_rates, $order_currency);

				$total = $compliance->get_amount_in_conversion_currencies(($total_total === false) ? $vat_paid['total'] : $total_total, $conversion_currencies, $conversion_rates, $order_currency);

				echo '<strong>'.__('All VAT charges', 'wc_eu_vat_compliance').'</strong><br>';
				echo __('Items', 'wc_eu_vat_compliance').': '.$items.'<br>';
				echo __('Shipping', 'wc_eu_vat_compliance').': '.$shipping.'<br>';
				if ($refunded_total_total) {
					echo __('Net total', 'wc_eu_vat_compliance').': '.$total.'<br>';
					echo __('Refund total', 'wc_eu_vat_compliance').': '.$compliance->get_amount_in_conversion_currencies($refunded_total_total*-1, $conversion_currencies, $conversion_rates, $order_currency).'<br>';
					$grand_total = $total_total - $refunded_total_total;
					echo __('Grand total', 'wc_eu_vat_compliance').': '.$compliance->get_amount_in_conversion_currencies($grand_total, $conversion_currencies, $conversion_rates, $order_currency).'<br>';
				} else {
					echo __('Total', 'wc_eu_vat_compliance').': '.$total.'<br>';
				}
			}

	
// 			if (!in_array($order_currency, $conversion_currencies)) $paid_in_order_currency = get_woocommerce_currency_symbol($vat_paid['currency']).' '.sprintf('%.02f', $vat_paid['total']);

			// Allow filtering - since for some shops using a multi-currency plugin, the VAT currency is neither the base nor necessarily the purchase currency.
// 			echo apply_filters('wc_eu_vat_compliance_show_vat_paid', $paid, $vat_paid);

			$valid_eu_vat_number = get_post_meta($post_id, 'Valid EU VAT Number', true);
			$vat_number_validated = get_post_meta($post_id, 'VAT number validated', true);
			$vat_number = get_post_meta($post_id, 'VAT Number', true);

			if ($valid_eu_vat_number && $vat_number_validated && 0 == $vat_paid['total']) {
				echo '<br>'.sprintf(__('Validated VAT number: %s', 'wc_eu_vat_compliance'), $vat_number);
			}

			$conversion_provider = $compliance->get_rate_providers($conversion_provider_key);
			if (!empty($conversion_provider_key) && !empty($conversion_provider)) {
				$provider_info = $conversion_provider->info();
				$provider_title = isset($provider_info['title']) ? $provider_info['title'] : $conversion_provider_key;
				echo '<p><strong title="'.esc_attr($currency_title).'">'.__('Currency conversion source:', 'wc_eu_vat_compliance').'</strong><br>';
				if (!empty($provider_info['url'])) echo '<a href="'.esc_attr($provider_info['url']).'">';
				echo htmlspecialchars($provider_title);
				if (!empty($provider_info['url'])) echo '</a>';
				echo '</p>';
			} else {
				echo '<br>';
			}
		} else {
			echo __("VAT paid:", 'wc_eu_vat_compliance').' '.__('Unknown', 'wc_eu_vat_compliance')."<br>";
		}

/*
	array (size=9)
	'items_total' => float 3.134
	'shipping_total' => float 4.39
	'total' => float 7.524
	'currency' => string 'USD' (length=3)
	'base_currency' => string 'GBP' (length=3)
	'items_total_base_currency' => int 2
	'shipping_total_base_currency' => float 2.8
	'total_base_currency' => float 4.8
*/
		if (!empty($country_info) && is_array($country_info)) {

			$country_code = !empty($country_info['data']) ? $country_info['data'] : __('Unknown', 'wc_eu_vat_compliance');

			$source = !empty($country_info['source']) ? $country_info['source'] :  __('Unknown', 'wc_eu_vat_compliance');

			$source_description = (isset($compliance->data_sources[$source])) ? $compliance->data_sources[$source] : __('Unknown', 'wc_eu_vat_compliance');

			echo '<span title="'.esc_attr(__('Raw information:', 'wc_eu_vat_compliance').': '.print_r($country_info, true)).'">';

			$countries = $compliance->wc->countries->countries;

			$country_name = isset($countries[$country_code]) ? $countries[$country_code] : '??';

			$taxable_address = (empty($country_info['taxable_address'])) ?  __('Unknown', 'wc_eu_vat_compliance') : $country_info['taxable_address'];

			echo '<span title="'.esc_attr(print_r($taxable_address, true)).'">'.__('Country used to calculate tax:', 'wc_eu_vat_compliance').' ';

			$calculated_country_code = (empty($taxable_address[0])) ? __('Unknown', 'wc_eu_vat_compliance') : $taxable_address[0];

			$calculated_country_name = isset($countries[$calculated_country_code]) ? $countries[$calculated_country_code] : '??';

			echo "$calculated_country_name ($calculated_country_code)";

			echo "</span><br>";

			echo __('IP Country:', 'wc_eu_vat_compliance')." $country_name ($country_code)<br>";
			echo '<span title="'.esc_attr($source).'">'.__('Source:', 'wc_eu_vat_compliance')." ".htmlspecialchars($source_description)."</span><br>";

			echo '</span>';
/*
e.g.

array (size=3)
  'source' => string 'geoip_detect_get_info_from_ip' (length=29)
  'data' => string 'GB' (length=2)
  'meta' => 
    array (size=2)
      'ip' => string '::1' (length=3)
      'info' => 
        object(geoiprecord)[483]
          public 'country_code' => string 'GB' (length=2)
          public 'country_code3' => string 'GBR' (length=3)
          public 'country_name' => string 'United Kingdom' (length=14)
          public 'region' => string 'C3' (length=2)
          public 'city' => string 'Ely' (length=3)
          public 'postal_code' => string 'CB6' (length=3)
          public 'latitude' => float 52.4641
          public 'longitude' => float 0.2902
          public 'area_code' => null
          public 'dma_code' => null
          public 'metro_code' => null
          public 'continent_code' => string 'EU' (length=2)
          public 'region_name' => string 'Cambridgeshire' (length=14)
          public 'timezone' => string 'Europe/London' (length=13)

*/
		}

		// $time
		echo "</p>";

	}

	// This is for WC 2.2 - the method was not added until WC 2.3 (though the data exists) - the below copies over the method from 2.3
	// http://docs.woothemes.com/wc-apidocs/source-class-WC_Order.html#61-80
	private function get_total_tax_refunded($order_id) {
		global $wpdb;

		$total = $wpdb->get_var( $wpdb->prepare( "
		SELECT SUM( order_itemmeta.meta_value )
		FROM {$wpdb->prefix}woocommerce_order_itemmeta AS order_itemmeta
		INNER JOIN $wpdb->posts AS posts ON ( posts.post_type = 'shop_order_refund' AND posts.post_parent = %d )
		INNER JOIN {$wpdb->prefix}woocommerce_order_items AS order_items ON ( order_items.order_id = posts.ID AND order_items.order_item_type = 'tax' )
		WHERE order_itemmeta.order_item_id = order_items.order_item_id
		AND order_itemmeta.meta_key IN ('tax_amount', 'shipping_tax_amount')
		", $order_id ) );

		return abs( $total );
	}

}
