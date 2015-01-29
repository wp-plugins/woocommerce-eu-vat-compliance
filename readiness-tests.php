<?php

if (!defined('WC_EU_VAT_COMPLIANCE_DIR')) die('No direct access');

class WC_EU_VAT_Compliance_Readiness_Tests {

	public function result_descriptions() {
		return array(
			'pass' => __('Passed', 'wc_eu_vat_compliance'),
			'fail' => __('Failed', 'wc_eu_vat_compliance'),
			'unknown' => __('Uncertain', 'wc_eu_vat_compliance'),
		);
	}

	public function __construct() {
		$this->tests = array(
			'woo_minver' => __('WooCommerce version', 'wc_eu_vat_compliance'),
			'tax_based_on' => __('Tax based upon', 'wc_eu_vat_compliance'),
			'coupons_before_tax' => __('Coupons apply before tax', 'wc_eu_vat_compliance'),
			'tax_enabled' => __('Store has tax enabled', 'wc_eu_vat_compliance'),
			'rates_remote_fetch' => __('Current rates can be fetched from network', 'wc_eu_vat_compliance'),
			'rates_exist_and_up_to_date' => __('VAT rates are up-to-date', 'wc_eu_vat_compliance'),
		);

		$this->compliance = WooCommerce_EU_VAT_Compliance();

		if (!$this->compliance->is_premium()) {
			$this->tests['subscriptions_plugin_on_free_version'] = __('Support for the WooCommerce Subscriptions extension', 'wc_eu_vat_compliance');
		}

		$this->rates_class = WooCommerce_EU_VAT_Compliance('WC_EU_VAT_Compliance_Rates');
		$this->european_union_vat_countries = $this->compliance->get_european_union_vat_countries();
// 			'' => __('', 'wc_eu_vat_compliance'),
	}

	public function get_results() {

		$results = array();

		foreach ($this->tests as $test => $label) {
			if (!method_exists($this, $test)) continue;
			$res = call_user_func(array($this, $test));
			// label, result, info
			if (is_wp_error($res)) {
				$res = $this->res(false, $res->get_error_message());
			}
			if (isset($res['result'])) {
				$results[$test] = $res;
				$results[$test]['label'] = $label;
			}
		}

		return $results;
	}

	protected function res($result, $info) {
		if (is_bool($result)) {
			if ($result) {
				$rescode = 'pass';
			} else {
				$rescode = 'fail';
			}
		} else {
			$rescode = 'unknown';
		}
		return array(
			'result' => $rescode,
			'info' => $info
		);
	}

	protected function coupons_before_tax() {
		if (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.3.0', '>=')) return $this->res(true, __('WooCommerce 2.3 removed the problematic "apply coupon after tax" option.', 'wc_eu_vat_compliance'));

		$page = 0;
		$page_size = 100;
		$fetch_more = true;

		$problematic_coupons = array();

		global $wpdb;
		$today_mysqldate = date('Y-m-d');

		while ($fetch_more) {

			$offset = $page*$page_size;

			$coupons = get_posts(array(
				'post_type' => 'shop_coupon',
				'meta_key' => 'apply_before_tax',
				'meta_value' => 'no',
				'posts_per_page' => $page_size,
				'offset' => $offset)
			);

			if (empty($coupons) || is_wp_error($coupons)) $fetch_more = false;

			$check_ids = '';
			$coupon_titles = array();
			if (is_array($coupons)) {
				foreach ($coupons as $coupon) {
					if ($check_ids) $check_ids .= ',';
					$check_ids .= $coupon->ID;
					$coupon_titles[$coupon->ID] = $coupon->post_title;
				}
			}

			if ($check_ids) {
				$sql = "SELECT post_id FROM ".$wpdb->postmeta." WHERE post_id IN ($check_ids) AND meta_key='expiry_date' AND meta_value > '$today_mysqldate'";
				$results = $wpdb->get_results($sql, OBJECT_K);
				if (is_array($results)) {
					foreach ($results as $id => $res) {
						$problematic_coupons[] = $coupon_titles[$id];
					}
				}
			}

			$page++;
		}

		if (!empty($problematic_coupons)) {
			return $this->res(false, __('The following currently-valid coupons use the "apply coupon after tax" option (which leads to non-compliant VAT invoices): ', 'wc_eu_vat_compliance').implode(', ', $problematic_coupons));
		} else {
			return $this->res(true, __('You have no currently-valid coupons which use the "apply coupon after tax" option (which leads to non-compliant VAT invoices)', 'wc_eu_vat_compliance'));
		}

	}

	protected function woo_minver() {
		$result = (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.2.9', '>='));
		if ($result) {
			$info = sprintf(__('Your WooCommerce version (%s) is high enough to support all features of this plugin.', 'wc_eu_vat_compliance'), WOOCOMMERCE_VERSION);
		} else {
			$info = sprintf(__('Your WooCommerce version (%s) is lower than %s - as a result, all features are supported, except for the ability to allow the customer to see exact taxes for their location before the cart or checkout.', 'wc_eu_vat_compliance'), WOOCOMMERCE_VERSION, '2.2.9');
		}
		return $this->res($result, $info);
	}

	protected function tax_based_on() {
		$tax_based_on = get_option( 'woocommerce_tax_based_on' );
		if ($tax_based_on == 'shipping' || $tax_based_on == 'billing') {
			$result = true;
			$info = __('Tax calculations must be based on either the customer billing or shipping address.', 'wc_eu_vat_compliance');
		} else {
			$result = false;
			$info = __('Tax calculations must be based on either the customer billing or shipping address.', 'wc_eu_vat_compliance').' '.__('They cannot be based upon the shop base address.', 'wc_eu_vat_compliance');
		}
		return $this->res($result, $info);
	}

	// TODO: Test for whether base country settings are consistent (if we charge no VAT to base country, then... etc.)
	// get_option( 'woocommerce_eu_vat_compliance_deduct_in_base' ) == 'yes' )
	// $compliance->wc->countries->get_base_country()

// TODO: This check needs to only check rate tables that are in the configured tax classes (or does it? Even traditional VAT should have the right rate).
	protected function rates_exist_and_up_to_date() {
		$has_rate_remaining_countries = $this->european_union_vat_countries;
		$countries_with_apparently_wrong_rates = array();
		$base_country = $this->compliance->wc->countries->get_base_country();

		$rates = $this->rates_class->get_vat_rates();
		$info = '';

		$result = false;
		if (empty($rates)) {
			$info = __('Could not get any VAT rate information.', 'wc_eu_vat_compliance');
		} else {
			global $wpdb, $table_prefix;
			$tax_rate_classes = get_option('woocommerce_tax_classes');
			$sql = "SELECT tax_rate_country, tax_rate, tax_rate_class FROM ".$table_prefix."woocommerce_tax_rates WHERE tax_rate_state=''";
			# Get an array of objects
			$results = $wpdb->get_results($sql);
			if (!is_array($results)) {
				return $results;
			} else {
				foreach ($results as $res) {
					$tax_rate_country = $res->tax_rate_country;
					$tax_rate = $res->tax_rate;

					if (($key = array_search($tax_rate_country, $has_rate_remaining_countries)) !== false) {
						unset($has_rate_remaining_countries[$key]);
					}

					if (empty($tax_rate_country) || '*' == $tax_rate_country || !isset($rates[$tax_rate_country]) || !is_array($rates[$tax_rate_country])) continue;
					$found_rate = false;
					foreach ($rates[$tax_rate_country] as $label => $rate) {
						# N.B. Not all attribute/values are rates; but, all the numerical ones are
						if (is_numeric($rate) && $rate == $tax_rate) {
							$found_rate = true;
							break;
						}
					}
					if (!$found_rate) $countries_with_apparently_wrong_rates[$tax_rate_country] = $tax_rate;
				}
			}
		}

		if (count($countries_with_apparently_wrong_rates) > 0) {
			$info = __('The following countries have tax rates set in a tax table, that were not found as any current VAT rate:', 'wc_eu_vat_compliance').' ';
			$first = true;
			foreach ($countries_with_apparently_wrong_rates as $country => $rate) {
				if ($first) { $first = false; } else { $info .= ', '; }
				$info .= "$country (".round($rate, 2)." %)";
			}
			$info .= '.';
		} else {
			if (count($results) > 0) {
				$result = true;
				$info = __('All countries had at least one tax table in which a current VAT rate entry was found.', 'wc_eu_vat_compliance');
			} else {
				$info = __('No tax rates at all were found in your WooCommerce tax tables. Have you set any up yet?', 'wc_eu_vat_compliance');
			}
		}

		if (count($has_rate_remaining_countries) > 0) {
			if (1 == count($has_rate_remaining_countries) && in_array($base_country, $has_rate_remaining_countries)) {
				if ($result) $result = 'unknown';
				$info .= ' '.sprintf(__('Your base country (%s) has no tax rate set in any tax rate table; but, perhaps this was intentional.', 'wc_eu_vat_compliance'), $base_country);
			} else {
				$result = false;
				$info .= ' '.__('These countries have no tax rate set in any tax rate table:', 'wc_eu_vat_compliance').' '.implode(', ', $has_rate_remaining_countries);
			}
		}

		return $this->res($result, $info);
	}

	protected function rates_remote_fetch() {
		$rates = $this->rates_class->fetch_remote_vat_rates();
		$info = __('Testing ability to fetch current VAT rates from the network.', 'wc_eu_vat_compliance');
		if (empty($rates)) $info .= ' '.__('If this fails, then check (with your web hosting company) the network connectivity from your webserver.', 'wc_eu_vat_compliance');
		return $this->res(!empty($rates), $info);
	}
	
	protected function tax_enabled() {
		$woocommerce_calc_taxes = get_option('woocommerce_calc_taxes');
		return $this->res('yes' == $woocommerce_calc_taxes, __('Taxes need to be enabled in the WooCommerce tax settings.', 'wc_eu_vat_compliance'));
	}

	protected function subscriptions_plugin_on_free_version() {

		$active_plugins = (array) get_option( 'active_plugins', array() );
		if (is_multisite()) $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));

		// Return just true: better not to report a non-event
		if (!in_array('woocommerce-subscriptions/woocommerce-subscriptions.php', $active_plugins ) || array_key_exists('woocommerce-subscriptions/woocommerce-subscriptions.php', $active_plugins)) return true;

		return $this->res(false, sprintf(__('The %s plugin is active, but support for subscription orders is not part of the free version of the EU VAT Compliance plugin. New orders created via subscriptions will not have VAT compliance information attached.', 'wc_eu_vat_compliance'), __('WooCommerce subscriptions', 'wc_eu_vat_compliance')));

	}

}
