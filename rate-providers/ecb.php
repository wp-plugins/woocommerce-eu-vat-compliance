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

	# The rates change daily, and are published at approx 3pm CET. Setting something less than 9 hours ensures that we will get the latest rates before the end of the day. 21600 = 6 hours.
	protected $force_refresh_rates_every = 21600;

	protected $key = 'ecb';

	public function info() {
		return array(
			'title' => __('European Central Bank', 'wc_eu_vat_compliance'),
			'url' => 'https://www.ecb.europa.eu/stats/exchange/eurofxref/html/index.en.html',
			'description' => __('Official exchange rates from the European Central Bank (ECB).', 'wc_eu_vat_compliance')
		);
	}

	public function get_current_conversion_rate_from_time($currency, $the_time = false) {

		$parsed = $this->populate_rates_parsed_xml($the_time);
		if (empty($parsed)) return (!empty($value)) ? $value : false;

		if (is_object($parsed) && isset($parsed->Cube) && isset($parsed->Cube->Cube) && isset($parsed->Cube->Cube->Cube)) {
			foreach ($parsed->Cube->Cube->Cube as $cur){
				if (isset($cur['currency']) && $currency == $cur['currency'] && isset($cur['rate'])) {
					return (float)$cur['rate'];
				}
			} 
		}
		return false;
	}

}
endif;
