<?php

if (!defined('WC_EU_VAT_COMPLIANCE_DIR')) die('No direct access');

class WC_EU_VAT_Compliance_Readiness_Tests {

	public function result_descriptions() {
		return array(
			'pass' => __('Passed', 'wc_eu_vat_compliance'),
			'fail' => __('Failed', 'wc_eu_vat_compliance'),
		);
	}

	public function __construct() {
		$this->tests = array(
			'woo_minver' => __('WooCommerce version', 'wc_eu_vat_compliance'),
			'tax_based_on' => __('Tax based upon', 'wc_eu_vat_compliance'),
			'coupons_before_tax' => __('Coupons apply before tax', 'wc_eu_vat_compliance'),
			'tax_enabled' => __('Store has tax enabled', 'wc_eu_vat_compliance'),
		);
// 			'' => __('', 'wc_eu_vat_compliance'),
	}

	public function get_results() {

		$results = array();

		foreach ($this->tests as $test => $label) {
			if (!method_exists($this, $test)) continue;
			$res = call_user_func(array($this, $test));
			// label, result, info
			if (isset($res['result'])) {
				$results[$test] = $res;
				$results[$test]['label'] = $label;
			}
		}

		return $results;

	}

	protected function res($result, $info) {
		if ($result) {
			$rescode = 'pass';
		} else {
			$rescode = 'fail';
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

	protected function tax_enabled() {
		$woocommerce_calc_taxes = get_option('woocommerce_calc_taxes');
		return $this->res('yes' == $woocommerce_calc_taxes, __('Taxes need to be enabled in the WooCommerce tax settings.', 'wc_eu_vat_compliance'));
	}

}