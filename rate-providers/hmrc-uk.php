<?php

if (!defined('WC_EU_VAT_COMPLIANCE_DIR')) die('No direct access.');

// Purpose: Official HMRC exchange rates: https://www.gov.uk/government/collections/exchange-rates-for-customs-and-vat

// Methods: info(), convert($from_currency, $to_currency, $amount, $the_time = false), settings_fields(), test()

class WC_EU_VAT_Compliance_Rate_Provider_hmrc_uk {

	# Currently, https just directs back to http
	const GETBASE = 'http://www.hmrc.gov.uk/softwaredevelopers/rates/';
	const CURRENCY = 'GBP';
	const INFOURL = 'https://www.gov.uk/government/collections/exchange-rates-for-customs-and-vat';

	# The rates change monthly, so it is pointless to keep the transient longer than that
	const TRANSIENT_EXPIRY = 2678400;

	public function info() {
		return array(
			'title' => __('HM Revenue & Customs (UK)', 'wc_eu_vat_compliance'),
			'url' => self::INFOURL,
			'description' => __('Official exchange rates from HM Revenue & Customs (UK).', 'wc_eu_vat_compliance')
		);
	}

	public function convert($from_currency, $to_currency, $amount, $the_time = false) {
		if (empty($amount)) return 0;
		if ($from_currency == $to_currency) return $amount;

		// Get the value of 1 GBP
		$rate = $this->get_current_conversion_rate($to_currency, $the_time);

		if ('GBP' == $from_currency) {
			return $amount * $rate;
		}

		if (false === $rate) return false;

		$rate2 = $this->get_current_conversion_rate($from_currency, $the_time);

		if (0 == $rate2) return false;

		if ('GBP' == $to_currency) {
			return $amount / $rate2;
		}

		return $amount * ($rate / $rate2);

	}

	public function settings_fields() {
		return sprintf(__('Using this rates provider requires no configuration. ', 'wc_eu_vat_compliance'), self::INFOURL);
	}

	private function populate_rates_parsed_xml($mon, $yer) {

		$current_rates = get_site_transient("hmrc_convert_rates_$mon$yer");

		if (empty($current_rates)) {
			$url = self::GETBASE."exrates-monthly-$mon$yer.xml";
			$fetched = wp_remote_get($url);
			if (!is_wp_error($url)) {
				if (!empty($fetched['response']) || $fetched['response']['code'] < 300) $xml = $fetched['body'];
			}
			if (empty($xml) && false != ($on_disk_file = apply_filters('wc_eu_vat_hmrc_rates_file', false, $mon, $yer)) && file_exists($on_disk_file)) {
				$xml = file_get_contents($on_disk_file);
			}
		}

		if (empty($xml) && empty($current_rates)) return false;

		if (empty($current_rates)) {
			set_site_transient("hmrc_convert_rates_$mon$yer", $xml, self::TRANSIENT_EXPIRY);
			$current_rates = $xml;
		}

		$parsed = simplexml_load_string($current_rates);

		return $parsed;

	}

	public function get_current_conversion_rate($currency, $the_time = false) {

		if ('GBP' == $currency) return 1;

		if (false == $the_time) $the_time = time();

		$mon = date('m', $the_time);
		$yer = date('y', $the_time);

		// Approx. 30 characters long
		$convert_key = "hmrc_convert_rate_".self::CURRENCY."_".$currency."_$mon$yer";

		$value = get_site_transient($convert_key);

		if (!empty($value)) return $value;

		$parsed = $this->populate_rates_parsed_xml($mon, $yer);

		if (empty($parsed)) return false;

		foreach ($parsed as $cur) {
			if (isset($cur->currencyCode) && $currency == $cur->currencyCode && isset($cur->rateNew)) {
				set_site_transient($convert_key, (float)$cur->rateNew, self::TRANSIENT_EXPIRY);
				return (float)$cur->rateNew;
			}
		}

		return false;
	}
}
