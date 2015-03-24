<?php

if (!defined('WC_EU_VAT_COMPLIANCE_DIR')) die('No direct access.');

// Purpose: Official HMRC exchange rates: https://www.gov.uk/government/collections/exchange-rates-for-customs-and-vat

// Methods: info(), convert($from_currency, $to_currency, $amount, $the_time = false), settings_fields()

// Classes extending this one need to implement: info(), get_current_conversion_rate_from_time() or get_current_conversion_rate(), get_leaf()

// Note that the rate provider key when extending this class should be kept short, as it is used in option names
abstract class WC_EU_VAT_Compliance_Rate_Provider_base_xml {

	# Default to hourly
	protected $force_refresh_rates_every = 3600;

	public function convert($from_currency, $to_currency, $amount, $the_time = false) {
		if (empty($amount)) return 0;
		if ($from_currency == $to_currency) return $amount;

		// Get the value of 1 unit of base currency
		$rate = $this->get_current_conversion_rate($to_currency, $the_time);

		if ($this->rate_base_currency == $from_currency) {
			return $amount * $rate;
		}

		if (false === $rate) return false;

		$rate2 = $this->get_current_conversion_rate($from_currency, $the_time);

		if (0 == $rate2) return false;

		if ($this->rate_base_currency == $to_currency) {
			return $amount / $rate2;
		}

		return $amount * ($rate / $rate2);
	}

	public function settings_fields() {
		$info = $this->info();
		return sprintf(__('Using this rates provider requires no configuration. ', 'wc_eu_vat_compliance'), $info['url']);
	}

	protected function get_leaf($the_time) {
		return '';
	}

	// This function is intended to always return something, if possible - in the worst-case scenario, we fall back on whatever rates we got last, even if out-of-date. This is because people need *some* exchange rate to be recorded, and relying on the online services being up when a transient expires is too risky.
	protected function populate_rates_parsed_xml($the_time) {

		$last_updated_option_name = "wcev_xml_".$this->key."_last_updated";
		$last_data_option_name = "wcev_xml_".$this->key."_last_data";

		$xml_last_updated = get_site_option($last_updated_option_name);
		if (empty($xml_last_updated)) $xml_last_updated = 0;
		$xml_last_data = get_site_option($last_data_option_name);

		$last_updated_month = gmdate('m', $xml_last_updated);
		// N.B. This assumes that $the_time is in the current month - which is currently a correct assumption ($the_time is only for possible future expansion)
		$this_month = gmdate('m', $the_time);

		if (empty($xml_last_data) || (!empty($this->force_refresh_on_new_month) && $last_updated_month != $this_month) || $xml_last_updated + $this->force_refresh_rates_every <= time()) {

			$url_base = $this->getbase;
			$url = $this->get_leaf($the_time);
			if (is_string($url)) $url = array($url);

			foreach ($url as $u) {
				if (!empty($new_xml)) continue;
				$fetched = wp_remote_get($url_base.$u);
				if (!is_wp_error($fetched)) {
					if (!empty($fetched['response']) || $fetched['response']['code'] < 300) {
						$new_xml = $fetched['body'];
						if (strpos($new_xml, '<!DOCTYPE HTML') !== false) unset($new_xml);
					}
				}
			}
			if (empty($new_xml)) {
				// Try yesterday, in case we have a timezone issue, or data not yet uploaded, etc.
				$backup_url = $this->get_leaf($the_time - 86400);
				if (is_string($backup_url)) $backup_url = array($backup_url);
// 				if ($url != $backup_url) {
					// Always try again, in case the failure was transient
					foreach ($backup_url as $u) {
						if (!empty($new_xml)) continue;
						$fetched = wp_remote_get($url_base.$u);
						if (!is_wp_error($fetched)) {
							if (!empty($fetched['response']) || $fetched['response']['code'] < 300) $new_xml = $fetched['body'];
							if (isset($new_xml) && strpos($new_xml, '<!DOCTYPE HTML') !== false) {
								unset($new_xml);
							}
						}
					}
// 				}
			}

			if (empty($new_xml) && false != ($on_disk_file = apply_filters('wc_eu_vat_'.$this->key.'_file', false, $the_time)) && file_exists($on_disk_file)) {
				$new_xml = file_get_contents($on_disk_file);
			}
		}

		if (empty($new_xml) && empty($xml_last_data)) return false;

		if (!empty($new_xml)) {
			# Does it parse?
			$new_data = simplexml_load_string($new_xml);
			if ($new_data) {
				update_site_option($last_data_option_name, $new_xml);
				update_site_option($last_updated_option_name, time());
				return $new_data;
			}
		}

		return simplexml_load_string($xml_last_data);

	}

	protected function get_current_conversion_rate($currency, $the_time = false) {
		if ($this->rate_base_currency == $currency) return 1;
		if (false == $the_time) $the_time = time();

		return $this->get_current_conversion_rate_from_time($currency, $the_time);
	}


}
