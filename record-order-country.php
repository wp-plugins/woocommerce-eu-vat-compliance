<?php

if (!defined('WC_EU_VAT_COMPLIANCE_DIR')) die('No direct access');

// Function: record the GeoIP information for the order, at order time. This module uses either the CloudFlare header (if available - https://support.cloudflare.com/hc/en-us/articles/200168236-What-does-CloudFlare-IP-Geolocation-do-), or requires http://wordpress.org/plugins/geoip-detect/. Or, you can hook into it and use something else. It will always record something, even if the something is the information that nothing could be worked out.

// The information is stored as order meta, with key: update_post_meta

if (class_exists('WC_EU_VAT_Compliance_Record_Order_Country')) return;
class WC_EU_VAT_Compliance_Record_Order_Country {

	private $wc;

	public function __construct() {
		add_action('woocommerce_checkout_update_order_meta', array($this, 'woocommerce_checkout_update_order_meta'));
		add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'woocommerce_admin_order_data_after_shipping_address'));

		// Display a notice if there's no means of detecting the country
		add_action('plugins_loaded', array($this, 'plugins_loaded'));

		$this->compliance = WooCommerce_EU_VAT_Compliance();

		$this->data_sources = array(
			'HTTP_CF_IPCOUNTRY' => __('CloudFlare Geo-Location', 'wc_eu_vat_compliance'),
			'geoip_detect_get_info_from_ip_function_not_available' => __('MaxMind GeoIP database was not installed', 'wc_eu_vat_compliance'),
			'geoip_detect_get_info_from_ip' => __('MaxMind GeoIP database', 'wc_eu_vat_compliance'),
		);

	}

	public function woocommerce_checkout_update_order_meta($order_id) {
		// Note: whilst this records the country via GeoIP resolution, that does not indicate which tax WooCommerce applies - that will be determined by the user's WooCommerce settings. The GeoIP data is recorded for compliance purposes.

		$country_info = $this->get_visitor_country_info();

		$taxable_address = WooCommerce_EU_VAT_Compliance()->wc->customer->get_taxable_address();

		$country_info['taxable_address'] = $taxable_address;

		update_post_meta($order_id, 'vat_compliance_country_info', apply_filters('wc_eu_vat_compliance_meta_country_info', $country_info));
	}

	private function get_vat_paid($order) {

		$taxes = $order->get_taxes();

		if (!is_array($taxes)) return false;
		if (empty($taxes)) return 0;

		// Get an array of matches
		$vat_strings = $this->compliance->get_vat_matches('regex');

		// Not get_woocommerce_currency(), as currency switcher plugins filter that.
		$base_currency = get_option('woocommerce_currency');
		// WC 2.0 did not store order currencies.
		$currency = method_exists($order, 'get_order_currency') ? $order->get_order_currency() : $base_currency;

		$vat_total = 0;
		$vat_shipping_total = 0;
		$vat_total_base_currency = 0;
		$vat_shipping_total_base_currency = 0;
		$base_currency_totals_are_reliable = true;

		foreach ($taxes as $tax) {
			if (!is_array($tax) || !isset($tax['label'])) continue;
			if (!preg_match($vat_strings, $tax['label'])) continue;

			if (!empty($tax['tax_amount'])) $vat_total += $tax['tax_amount'];
			if (!empty($tax['shipping_tax_amount'])) $vat_shipping_total += $tax['shipping_tax_amount'];

			if ($currency != $base_currency) {
				if (empty($tax['tax_amount_base_currency'])) {
					// This will be wrong, of course, unless your conversion rate is 1:1
					if (!empty($tax['tax_amount'])) $vat_total_base_currency += $tax['tax_amount'];
					if (!empty($tax['shipping_tax_amount'])) $vat_shipping_total_base_currency += $tax['shipping_tax_amount'];
					$base_currency_totals_are_reliable = false;
				} else {
					if (!empty($tax['tax_amount'])) $vat_total_base_currency += $tax['tax_amount_base_currency'];
					if (!empty($tax['shipping_tax_amount'])) $vat_shipping_total_base_currency += $tax['shipping_tax_amount_base_currency'];
				}
			} else {
				$vat_total_base_currency = $vat_total;
				$vat_shipping_total_base_currency = $vat_shipping_total;
			}

		}

		// We may as well return the kitchen sink, since we've spent the cycles on getting it.
		return apply_filters('wc_eu_vat_compliance_get_vat_paid', array(
			'items_total' => $vat_total,
			'shipping_total' => $vat_shipping_total,
			'total' => $vat_total + $vat_shipping_total,
			'currency' => $currency,
			'base_currency' => $base_currency,
			'items_total_base_currency' => $vat_total_base_currency,
			'shipping_total_base_currency' => $vat_shipping_total_base_currency,
			'total_base_currency' => $vat_total_base_currency + $vat_shipping_total_base_currency,
			'base_currency_totals_are_reliable' => $base_currency_totals_are_reliable
		), $order, $taxes, $currency, $base_currency);

/*
e.g. (and remember, there may be other elements which are not VAT).

Array
(
    [62] => Array
        (
            [name] => GB-VAT (UNITED KINGDOM)-1
            [type] => tax
            [item_meta] => Array
                (
                    [rate_id] => Array
                        (
                            [0] => 28
                        )

                    [label] => Array
                        (
                            [0] => VAT (United Kingdom)
                        )

                    [compound] => Array
                        (
                            [0] => 1
                        )

                    [tax_amount_base_currency] => Array
                        (
                            [0] => 2
                        )

                    [tax_amount] => Array
                        (
                            [0] => 3.134
                        )

                    [shipping_tax_amount_base_currency] => Array
                        (
                            [0] => 2.8
                        )

                    [shipping_tax_amount] => Array
                        (
                            [0] => 4.39
                        )

                )

            [rate_id] => 28
            [label] => VAT (United Kingdom)
            [compound] => 1
            [tax_amount_base_currency] => 2
            [tax_amount] => 3.134
            [shipping_tax_amount_base_currency] => 2.8
            [shipping_tax_amount] => 4.39
        )


*/

	}

	// Show recorded information on the admin page
	public function woocommerce_admin_order_data_after_shipping_address($order) {

		$post_id = (isset($order->post)) ? $order->post->ID : $order->id;
		$country_info = get_post_meta($post_id, 'vat_compliance_country_info', true);
		echo '<p id="wc_eu_vat_compliance_countryinfo">';

		echo '<strong>'.__("EU VAT Compliance Information", 'wc_eu_vat_compliance').':</strong><br>';

		if (empty($country_info) || !is_array($country_info)) {
			echo '<em>'.__('No further information recorded (the EU VAT Compliance plugin was not active when this order was made).', 'wc_eu_vat_compliance').'</em>';
			if (function_exists('geoip_detect_get_info_from_ip') && $ip = get_post_meta($post_id, '_customer_ip_address', true)) {
				$country_info = $this->construct_country_info($ip);
				if (!empty($country_info) && is_array($country_info)) {
					echo ' '.__("The following information is based upon looking up the customer's IP address now.", 'wc_eu_vat_compliance');
				}
			}
			echo '<br>';
		}

		// Relevant function: get_woocommerce_currency_symbol($currency = '')
		$vat_paid = $this->get_vat_paid($order);

		if (is_array($vat_paid)) {
			echo __("VAT paid:", 'wc_eu_vat_compliance').' ';

			$paid = get_woocommerce_currency_symbol($vat_paid['currency']).' '.sprintf('%.02f', $vat_paid['total']);

			// Allow filtering - since for some shops using a multi-currency plugin, the VAT currency is neither the base nor necessarily the purchase currency.
			echo apply_filters('wc_eu_vat_compliance_show_vat_paid', $paid, $vat_paid);

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

			$source_description = (isset($this->data_sources[$source])) ? $this->data_sources[$source] : __('Unknown', 'wc_eu_vat_compliance');

			echo '<span title="'.esc_attr(__('Raw information:', 'wc_eu_vat_compliance').': '.print_r($country_info, true)).'">';

			$countries = WooCommerce_EU_VAT_Compliance()->wc->countries->countries;

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

	public function plugins_loaded() {
		if (!empty($_SERVER["HTTP_CF_IPCOUNTRY"]) || !is_admin() || !current_user_can('manage_options')) return;

		if (!function_exists('geoip_detect_get_info_from_ip')) {
			if (empty($_REQUEST['action']) || ('install-plugin' != $_REQUEST['action'] && 'activate' != $_REQUEST['action'])) add_action('admin_notices', array($this, 'admin_notice_no_geoip_plugin'));
		}

		if (function_exists('geoip_detect_get_database_upload_filename')) {
			$filename = geoip_detect_get_database_upload_filename();
			if (!file_exists($filename)) add_action('admin_notices', array($this, 'admin_notice_no_geoip_database'));
		}
	}

	public function admin_notice_no_geoip_database() {
		echo '<div class="error">';
		echo '<h4 style="margin: 1em 0 0 0">'.__('GeoIP database not found', 'wc_eu_vat_compliance').'</h4><p>';
		echo __('You have the GeoIP plugin installed, but it has not yet downloaded its database. This is needed for country pre-selection to work.', 'wc_eu_vat_compliance');
		echo '<a href="'.admin_url('tools.php?page=geoip-detect/geoip-detect.php').'"> '.__('Follow this link and press the Update Now button to download it', 'wcpreselectdefaultcountry').'</a>';
		echo '</p></div>';
	}

	public function admin_notice_no_geoip_plugin() {
		echo '<div class="error">';
		echo '<h4 style="margin: 1em 0 0 0">'.__('Required Plugin Not Found', 'wc_eu_vat_compliance').'</h4><p>';
		echo __('For the WooCommerce EU VAT compliance module to be able to record the country that a customer is ordering from, the (free) GeoIP Detection plugin must be installed and activated.', 'wc_eu_vat_compliance').' ';

		if (current_user_can('install_plugins')) {
			if (!file_exists(WP_PLUGIN_DIR.'/geoip-detect')) {
				echo '<a href="'.wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=geoip-detect'), 'install-plugin_geoip-detect').'">'.__('Follow this link to install it', 'wc_eu_vat_compliance').'</a>';
			} elseif (file_exists(WP_PLUGIN_DIR.'/geoip-detect/geoip-detect.php')) {
				echo '<a href="'.esc_url(wp_nonce_url(self_admin_url('plugins.php?action=activate&plugin=geoip-detect/geoip-detect.php'), 'activate-plugin_geoip-detect/geoip-detect.php')).'">'.__('Follow this link to activate it.', 'wc_eu_vat_compliance').'</a>';
			}
		}

		echo '</p></div>';
	}

	// Here's where the hard work is done - where we get the information on the visitor's country and how it was discerned
	// Returns an array
	public function get_visitor_country_info() {

		$ip = $this->get_visitor_ip_address();
		$info = null;

		// If CloudFlare has already done the hard work, return their result (which is probably more accurate)
		if (!empty($_SERVER["HTTP_CF_IPCOUNTRY"])) {
			$info = null;
			$country_info = array(
				'source' => 'HTTP_CF_IPCOUNTRY',
				'data' => $_SERVER["HTTP_CF_IPCOUNTRY"]
			);
		} elseif (!function_exists('geoip_detect_get_info_from_ip')) {
			$country_info = array(
				'source' => 'geoip_detect_get_info_from_ip_function_not_available',
				'data' => false
			);
		}

		// Get the GeoIP info even if CloudFlare has a country - store it
		if (function_exists('geoip_detect_get_info_from_ip')) {
			if (isset($country_info)) {
				$country_info_geoip = $this->construct_country_info($ip);
				if (is_array($country_info_geoip) && isset($country_info_geoip['meta'])) $country_info['meta'] = $country_info_geoip['meta'];
			} else {
				$country_info = $this->construct_country_info($ip);
			}

		}

		return apply_filters('wc_eu_vat_compliance_get_visitor_country_info', $country_info, $info, $ip);
	}

	// Make sure that function_exists('geoip_detect_get_info_from_ip') before calling this
	private function construct_country_info($ip) {
		$info = geoip_detect_get_info_from_ip($ip);
		if (!is_object($info) || empty($info->country_code)) {
			$country_info = array(
				'source' => 'geoip_detect_get_info_from_ip',
				'data' => false,
				'meta' => array('ip' => $ip, 'reason' => 'geoip_detect_get_info_from_ip failed')
			);
		} else {
			$country_info = array(
				'source' => 'geoip_detect_get_info_from_ip',
				'data' => $info->country_code,
				'meta' => array('ip' => $ip, 'info' => $info)
			);
		}
		return $country_info;
	}

	# Function adapted from Aelia Currency Switcher under the GPLv3 (http://dev.pathtoenlightenment.net)
	private function get_visitor_ip_address() {

		$forwarded_for = !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];

		// Field HTTP_X_FORWARDED_FOR may contain multiple addresses, separated by a
		// comma. The first one is the real client, followed by intermediate proxy
		// servers

		$ff = explode(',', $forwarded_for);

		$forwarded_for = array_shift($ff);

		$visitor_ip = trim($forwarded_for);

		# The filter makes it easier to test without having to visit another country. ;-)
		return apply_filters('wc_eu_vat_compliance_visitor_ip', $visitor_ip, $forwarded_for);
	}


}