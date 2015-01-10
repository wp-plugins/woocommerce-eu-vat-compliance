<?php

if (!defined('WC_EU_VAT_COMPLIANCE_DIR')) die('No direct access.');

// Purpose: Official Danish National Bank exchange rates

// Methods: info(), convert($from_currency, $to_currency, $amount, $the_time = false), settings_fields(), test()

if (!class_exists('WC_EU_VAT_Compliance_Rate_Provider_base_xml')) require_once(WC_EU_VAT_COMPLIANCE_DIR.'/rate-providers/base-xml.php');

// Conditional execution to deal with bugs on some old PHP versions with classes that extend classes not known until execution time
if (1==1):
class WC_EU_VAT_Compliance_Rate_Provider_danishnb extends WC_EU_VAT_Compliance_Rate_Provider_base_xml {

	# Currently, https just directs back to http
	protected $getbase = 'https://www.nationalbanken.dk/_vti_bin/DN/DataService.svc/CurrencyRatesXML?lang=en';

	protected $rate_base_currency = 'DKK';

	# The rates change daily, and are published at approx 3pm CET. Setting something less than 9 hours ensures that we will get the latest rates before the end of the day. 21600 = 6 hours.
	protected $force_refresh_rates_every = 21600;

	protected $key = 'danishnb';

	public function info() {
		return array(
			'title' => __('Danish National Bank', 'wc_eu_vat_compliance'),
			'url' => 'https://www.nationalbanken.dk/en/statistics/exchange_rates/Pages/Default.aspx',
			'description' => __('Official exchange rates from the Danish National Bank.', 'wc_eu_vat_compliance')
		);
	}

	public function get_current_conversion_rate_from_time($currency, $the_time = false) {

		$parsed = $this->populate_rates_parsed_xml($the_time);
		if (empty($parsed)) return false;

		if (is_object($parsed) && isset($parsed->dailyrates) && isset($parsed->dailyrates->currency)) {
			foreach ($parsed->dailyrates->currency as $cur){
				if (isset($cur['code']) && $currency == strtoupper($cur['code']) && isset($cur['rate'])) {
					$rate = (float)$cur['rate'];
					return (0 == $rate) ? false : 1/$rate;
				}
			} 
		}
		return false;
	}

}
endif;
