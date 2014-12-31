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
			'woo_minver' => __('WooCommerce version check', 'wc_eu_vat_compliance'),
			'tax_based_on' => __('Tax based upon', 'wc_eu_vat_compliance'),
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
			$info = __('Tax calculations must be based on either the customer billing or shipping address.', 'wc_eu_vat_compliance').' '.__('They cannot be based pon the shop base address.', 'wc_eu_vat_compliance');
		}
		return $this->res($result, $info);
	}

}