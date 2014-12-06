<?php
/*
Plugin Name: WooCommerce EU VAT Compliance
Plugin URI: https://www.simbahosting.co.uk/shop/woocommerce-eu-vat-compliance
Description: Provides features to assist WooCommerce with EU VAT compliance
Version: 1.0
Author: David Anderson
Author URI: http://www.simbahosting.co.uk/s3/shop/
Requires at least: 3.1
Tested up to: 4.0
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Copyright: 2014- David Anderson
Portions licenced under the GPL v3 from WooThemes
*/

$active_plugins = (array) get_option( 'active_plugins', array() );
if (is_multisite()) $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));

if (!in_array('woocommerce/woocommerce.php', $active_plugins ) && !array_key_exists('woocommerce/woocommerce.php', $active_plugins)) return;

define('WC_EU_VAT_COMPLIANCE_DIR', dirname(__FILE__));

// This plugin performs various distinct functions. So, we have separated the code accordingly.
// Not all of these files may be present, depending on a) whether this is the free or premium version b) whether I've written the feature yet
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/vat-number.php');
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/reports.php');
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/record-order-country.php');
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/rates.php');
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/premium.php');

// Though the code is separated, some pieces are inter-dependent; the order also matters. So, don't assume you can just change this arbitrarily.
$classes_to_activate = apply_filters('woocommerce_eu_vat_compliance_classes', array(
	'WC_EU_VAT_Compliance',
	'WC_EU_VAT_Compliance_VAT_Number',
	'WC_EU_VAT_Compliance_Reports',
	'WC_EU_VAT_Compliance_Record_Order_Country',
	'WC_EU_VAT_Compliance_Rates',
	'WC_EU_VAT_Compliance_Premium',
));

class WC_EU_VAT_Compliance {

	private $default_vat_matches = 'VAT, V.A.T, IVA, I.V.A., Value Added Tax';
	public $wc;

	// Returns normalised data
	public function get_vat_matches($format = 'array') {
		$matches = get_option('woocommerce_eu_vat_compliance_vat_match', $this->default_vat_matches);
		if (!is_array($matches)) $matches = $this->default_vat_matches;
		$arr = array_map('trim', explode(',', $matches));
		if ('regex' == $format) {
			$ret = '#(';
			foreach ($arr as $str) {
				$ret .= ($ret == '#(') ? preg_quote($str) : '|'.preg_quote($str);
			}
			$ret .= ')#i';
			return $ret;
		} elseif ('html-printable' == $format) {
			$ret = '';
			foreach ($arr as $str) {
				$ret .= ($ret == '') ? htmlspecialchars($str) : ', '.htmlspecialchars($str);
			}
			return $ret;
		}
		return $arr;
	}

	public function __construct() {
		add_action('plugins_loaded', array($this, 'plugins_loaded'));

		add_action( 'woocommerce_settings_tax_options_end', array($this, 'woocommerce_settings_tax_options_end'));
		add_action( 'woocommerce_update_options_tax', array( $this, 'woocommerce_update_options_tax'));

		$this->settings = array(
			array(
				'name' 		=> __( 'Phrase matches used to identify VAT taxes', 'wc_eu_vat_compliance' ),
				'desc' 		=> __( 'A comma-separated (optional spaces) list of strings (phrases) used to identify taxes which are EU VAT taxes. One of these strings must be used in your tax name labels (i.e. the names used in your tax tables) if you wish the tax to be identified as EU VAT.', 'wc_eu_vat_compliance' ),
				'id' 		=> 'woocommerce_eu_vat_compliance_vat_match',
				'type' 		=> 'text',
				'default'		=> $this->default_vat_matches
			)
		);

	}

	public function woocommerce_settings_tax_options_end() {
		woocommerce_admin_fields($this->settings);
	}

	public function woocommerce_update_options_tax() {
		woocommerce_update_options($this->settings);
	}

	public function plugins_loaded() {
		load_plugin_textdomain('wc_eu_vat_compliance', false, WC_EU_VAT_COMPLIANCE_DIR.'/languages');
		if (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.1', '<')) {
			global $woocommerce;
			$this->wc = $woocommerce;
		} elseif (function_exists('WC')) {
			$this->wc = WC();
		}
	}
}

global $woocommerce_eu_vat_compliance_classes;
$woocommerce_eu_vat_compliance_classes = array();
foreach ($classes_to_activate as $cl) {
	if (class_exists($cl)) {
		$woocommerce_eu_vat_compliance_classes[$cl] = new $cl;
	}
}

function WooCommerce_EU_VAT_Compliance() {
	global $woocommerce_eu_vat_compliance_classes;
	return $woocommerce_eu_vat_compliance_classes['WC_EU_VAT_Compliance'];
}