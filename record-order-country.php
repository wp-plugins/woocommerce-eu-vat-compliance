<?php

if (!defined('WC_EU_VAT_COMPLIANCE_DIR')) die('No direct access');

// Function: record the GeoIP information for the order, at order time. This module uses either the CloudFlare header (if available - https://support.cloudflare.com/hc/en-us/articles/200168236-What-does-CloudFlare-IP-Geolocation-do-), or requires http://wordpress.org/plugins/geoip-detect/. Or, you can hook into it and use something else. It will always record something, even if the something is the information that nothing could be worked out.

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

		$compliance = WooCommerce_EU_VAT_Compliance();

		$country_info = $compliance->get_visitor_country_info();

		$tax = $compliance->wc->cart->tax;
		if (method_exists($tax, 'get_tax_location')) {
			$taxable_address = $tax->get_tax_location();
		} else {
			$taxable_address = $tax->get_taxable_address();
		}

		$country_info['taxable_address'] = $taxable_address;
		update_post_meta($order_id, 'vat_compliance_country_info', apply_filters('wc_eu_vat_compliance_meta_country_info', $country_info));

		$this->record_conversion_rates($order_id);

	}

	public function record_conversion_rates($order_id) {
		$compliance = WooCommerce_EU_VAT_Compliance();

		$conversion_provider = get_option('woocommerce_eu_vat_compliance_exchange_rate_provider');

		$providers = $compliance->get_rate_providers();
		if (!is_array($providers) || !isset($providers[$conversion_provider])) return;
		$provider = $providers[$conversion_provider];

		$record_currencies = apply_filters('wc_eu_vat_vat_recording_currencies', get_option('woocommerce_eu_vat_compliance_vat_recording_currency'));
		if (empty($record_currencies)) $record_currencies = array();
		if (!is_array($record_currencies)) $record_currencies = array($record_currencies);

		$order = $compliance->get_order($order_id);
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
			if ($result) $conversion_rates['rates'][$vat_currency] = $result;
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
			if (!isset($vat_paid['by_rates'])) $vat_paid['by_rates'] = array(array('items_total' => $vat_paid['items_total'], 'shipping_total' => $vat_paid['shipping_total'], 'rate' => '??', 'name' => __('VAT', 'wc_eu_vat_compliance')));

			// What currencies is VAT meant to be reported in?
			$conversion_rates =  get_post_meta($post_id, 'wceuvat_conversion_rates', true);

			if (is_array($conversion_rates) && isset($conversion_rates['rates'])) {
				$conversion_currencies = array_keys($conversion_rates['rates']);
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
						$conversion_rates = array('order_currency' => $order_currency, 'rates' => array($try_currency => $conversion_rate));
						$conversion_currencies = array($try_currency);
						update_post_meta($post_id, 'wceuvat_conversion_rates', $conversion_rates);
					}
				}
			}

			// A default - redundant
// 			if (empty($conversion_currencies)) $conversion_currencies = array($order_currency);

			if (!in_array($order_currency, $conversion_currencies)) $conversion_currencies[] = $order_currency;

			foreach ($vat_paid['by_rates'] as $vat) {

				$items = $compliance->get_amount_in_conversion_currencies($vat['items_total'], $conversion_currencies, $conversion_rates, $order_currency);

				$shipping = $compliance->get_amount_in_conversion_currencies($vat['shipping_total'], $conversion_currencies, $conversion_rates, $order_currency);

				$total = $compliance->get_amount_in_conversion_currencies($vat['items_total']+$vat['shipping_total'], $conversion_currencies, $conversion_rates, $order_currency);

				echo '<strong>'.$vat['name'].' ('.sprintf('%0.2f', $vat['rate']).' %)</strong><br>';
				echo __('Items', 'wc_eu_vat_compliance').': '.$items.'<br>';
				echo __('Shipping', 'wc_eu_vat_compliance').': '.$shipping.'<br>';
				echo __('Total', 'wc_eu_vat_compliance').': '.$total.'<br>';

			}

			if (count($vat_paid['by_rates']) > 1) {
				$items = $compliance->get_amount_in_conversion_currencies($vat_paid['items_total'], $conversion_currencies, $conversion_rates, $order_currency);

				$shipping = $compliance->get_amount_in_conversion_currencies($vat_paid['shipping_total'], $conversion_currencies, $conversion_rates, $order_currency);

				$total = $compliance->get_amount_in_conversion_currencies($vat_paid['total'], $conversion_currencies, $conversion_rates, $order_currency);

				echo '<strong>'.__('All VAT charges', 'wc_eu_vat_compliance').'</strong><br>';
				echo __('Items', 'wc_eu_vat_compliance').': '.$items.'<br>';
				echo __('Shipping', 'wc_eu_vat_compliance').': '.$shipping.'<br>';
				echo __('Total', 'wc_eu_vat_compliance').': '.$total.'<br>';
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

			echo '<br>';
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
	'base_currency_totals_are_reliable' => boolean true
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

}
