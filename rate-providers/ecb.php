<?php

if (!defined('WC_EU_VAT_COMPLIANCE_DIR')) die('No direct access.');

// Purpose: Official European Central Bank exchange rates: https://www.ecb.europa.eu/stats/exchange/eurofxref/html/index.en.html

// Methods: info(), convert($from_currency, $to_currency, $amount, $the_time = false), settings_fields(), test()

if (!class_exists('WC_EU_VAT_Compliance_Rate_Provider_base_xml')) require_once(WC_EU_VAT_COMPLIANCE_DIR.'/rate-providers/base-xml.php');

// Conditional execution to deal with bugs on some old PHP versions with classes that extend classes not known until execution time
if (1==1):
class WC_EU_VAT_Compliance_Rate_Provider_ecb extends WC_EU_VAT_Compliance_Rate_Provider_base_xml {

	# Currently, https just directs back to http
	protected $getbase = 'http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

	protected $rate_base_currency = 'EUR';
	# The rates change daily, so it is pointless to keep the transient longer than that.
	protected $transient_expiry = 86400;

	protected $key = 'ecb';

	public function info() {
		return array(
			'title' => __('European Central Bank', 'wc_eu_vat_compliance'),
			'url' => 'https://www.ecb.europa.eu/stats/exchange/eurofxref/html/index.en.html',
			'description' => __('Official exchange rates from the European Central Bank (ECB).', 'wc_eu_vat_compliance')
		);
	}

	public function get_current_conversion_rate_from_time($currency, $the_time = false) {

		$mon = gmdate('m', $the_time);
		$yer = gmdate('y', $the_time);
		$day = gmdate('d', $the_time);
		$day_yesterday = gmdate('d', $the_time-86400);

		// Approx. 35 characters long
		$convert_key = "wcev_xml_".$this->key."_rate_".$this->rate_base_currency."_".$currency."_$day$mon$yer";

		$value = get_site_transient($convert_key);
		if (!empty($value)) return $value;

		$convert_key_yesterday = "wcev_xml_".$this->key."_rate_".$this->rate_base_currency."_".$currency."_".$day_yesterday."$mon$yer";
		$value = get_site_transient($convert_key_yesterday);

		$parsed = $this->populate_rates_parsed_xml($the_time, true);
		if (empty($parsed)) return (!empty($value)) ? $value : false;

		if (is_object($parsed) && isset($parsed->Cube) && isset($parsed->Cube->Cube) && isset($parsed->Cube->Cube->Cube)) {
			foreach ($parsed->Cube->Cube->Cube as $cur){
				if (isset($cur['currency']) && $currency == $cur['currency'] && isset($cur['rate'])) {
					set_site_transient($convert_key, (float)$cur['rate'], $this->transient_expiry);
					return (float)$cur['rate'];
				}
			} 
		}
		return false;
	}

}
endif;
