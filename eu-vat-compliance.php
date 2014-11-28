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
if (is_multisite()) $active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );

if (!in_array('woocommerce/woocommerce.php', $active_plugins ) && !array_key_exists('woocommerce/woocommerce.php', $active_plugins)) return;

define('WC_EU_VAT_COMPLIANCE_DIR', dirname(__FILE__));

// This plugin performs various conceptually distinct functions. So, we have separated the code accordingly.
// Not all of these files may be present, depending on a) whether this is the free or premium version b) whether I've yet written them
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/vat-number.php');
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/sales-reports.php');
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/record-order-country.php');
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/vat-rates.php');

// Though the code is separated, some pieces are inter-dependent. So, don't assume you can just change this arbitrarily.
$classes_to_activate = apply_filters('woocommerce_eu_vat_compliance_classes', array(
	'WC_EU_VAT_Compliance_VAT_Number',
	'WC_EU_VAT_Compliance_Sales_Reports',
	'WC_EU_VAT_Compliance_Record_Order_Country',
	'WC_EU_VAT_Compliance_VAT_Rates'
));

$woocommerce_eu_vat_compliance_classes = array();
foreach ($classes_to_activate as $cl) {
	if (class_exists($cl)) {
		$woocommerce_eu_vat_compliance_classes[$cl] = new $cl;
	}
}

add_action('plugins_loaded', 'eu_vat_compliance_plugins_loaded');
function eu_vat_compliance_plugins_loaded() {
	load_plugin_textdomain('wc_eu_vat_compliance', false, WC_EU_VAT_COMPLIANCE_DIR.'/languages');
}



