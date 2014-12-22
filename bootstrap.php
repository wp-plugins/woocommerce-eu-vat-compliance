<?php

// Purpose boot-strap plugin. Also contains the main class.

if (!defined('ABSPATH')) die('Access denied.');

if (class_exists('WC_EU_VAT_Compliance')) return;

define('WC_EU_VAT_COMPLIANCE_DIR', dirname(__FILE__));
define('WC_EU_VAT_COMPLIANCE_URL', plugins_url('', __FILE__));

$active_plugins = (array) get_option( 'active_plugins', array() );
if (is_multisite()) $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));

if (!in_array('woocommerce/woocommerce.php', $active_plugins ) && !array_key_exists('woocommerce/woocommerce.php', $active_plugins)) return;

// This plugin performs various distinct functions. So, we have separated the code accordingly.
// Not all of these files may be present, depending on a) whether this is the free or premium version b) whether I've written the feature yet
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/vat-number.php');
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/reports.php');
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/record-order-country.php');
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/rates.php');
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/premium.php');
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/control-centre.php');

// Though the code is separated, some pieces are inter-dependent; the order also matters. So, don't assume you can just change this arbitrarily.
$classes_to_activate = apply_filters('woocommerce_eu_vat_compliance_classes', array(
	'WC_EU_VAT_Compliance',
	'WC_EU_VAT_Compliance_VAT_Number',
	'WC_EU_VAT_Compliance_Reports',
	'WC_EU_VAT_Compliance_Record_Order_Country',
	'WC_EU_VAT_Compliance_Rates',
	'WC_EU_VAT_Compliance_Premium',
	'WC_EU_VAT_Control_Centre'
));

if (!class_exists('WC_EU_VAT_Compliance')):
class WC_EU_VAT_Compliance {

	private $default_vat_matches = 'VAT, V.A.T, IVA, I.V.A., Value Added Tax';
	public $wc;

	public function __construct() {
		add_action('plugins_loaded', array($this, 'plugins_loaded'));

		add_action( 'woocommerce_settings_tax_options_end', array($this, 'woocommerce_settings_tax_options_end'));
		add_action( 'woocommerce_update_options_tax', array( $this, 'woocommerce_update_options_tax'));

		add_action('woocommerce_checkout_process', array($this, 'woocommerce_checkout_process'));

		add_filter('network_admin_plugin_action_links', array($this, 'plugin_action_links'), 10, 2);
		add_filter('plugin_action_links', array($this, 'plugin_action_links'), 10, 2);

		add_option('woocommerce_eu_vat_compliance_vat_match', $this->default_vat_matches);

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

	public function woocommerce_checkout_process() {

		return;

		$classes = get_option('woocommerce_eu_vat_compliance_restricted_classes');
		if (empty($classes) || !is_array($classes)) return;

		// TODO: Finish this
		$relevant_products_found = false;
		$cart = $this->wc->cart->get_cart();
		foreach ($cart as $item) {
			$_product = $item['data'];
			$shipping_class = $_product->get_shipping_class_id();
			if (!empty($shipping_class) && in_array($shipping_class, $classes)) {
				$relevant_products_found = true;
				break;
			}
		}
		if (!$relevant_products_found) return;

		# TODO: Check the country. Call $this->add_wc_error() if checkout needs to be halted.
		# TODO: Also put up a warning at the cart stage. That is done via simply echoing:
		/*
e.g.
echo "<p class=\"woocommerce-info\" id=\"openinghours-notpossible\">".apply_filters('openinghours_frontendtext_currentlyclosedinfo', __('We are currently closed; but you will be able to choose a time for later delivery.', 'openinghours'))."</p>";
		*/

	}

	public function enqueue_jquery_ui_style() {
		global $wp_scripts;
		$jquery_version = isset( $wp_scripts->registered['jquery-ui-core']->ver ) ? $wp_scripts->registered['jquery-ui-core']->ver : '1.9.2';
		wp_enqueue_style( 'jquery-ui-style', '//ajax.googleapis.com/ajax/libs/jqueryui/' . $jquery_version . '/themes/smoothness/jquery-ui.css', array(), WC_VERSION );
	}

	public function get_version() {

		if (!empty($this->version)) return $this->version;

		$file = (file_exists(WC_EU_VAT_COMPLIANCE_DIR.'/eu-vat-compliance-premium.php')) ? WC_EU_VAT_COMPLIANCE_DIR.'/eu-vat-compliance-premium.php' : WC_EU_VAT_COMPLIANCE_DIR.'/eu-vat-compliance.php';

		if ($fp = fopen($file, 'r')) {
			$file_data = fread($fp, 1024);
			if (preg_match("/Version: ([\d\.]+)(\r|\n)/", $file_data, $matches)) {
				$this->version = $matches[1];
			}
			fclose($fp);
		}

		return $this->version;
	}

	public function add_wc_error($msg) {
		if (function_exists('wc_add_notice')) {
			wc_add_notice($msg, 'error');
		} else {
			# For pre-2.1
			$this->wc->add_error($msg);
		}
	}

	// Returns normalised data
	public function get_vat_matches($format = 'array') {
		$matches = get_option('woocommerce_eu_vat_compliance_vat_match', $this->default_vat_matches);
		if (!is_string($matches) || empty($matches)) $matches = $this->default_vat_matches;
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
		} elseif ('sqlregex' == $format) {
			$ret = '';
			foreach ($arr as $str) {
				$ret .= ($ret == '') ? esc_sql($str) : '|'.esc_sql($str);
			}
			return $ret;
		}
		return $arr;
	}

	public function get_order($order_id) {
		if (function_exists('wc_get_order')) {
			$order = wc_get_order($order_id);
		} else {
			$order = new WC_Order();
			$get_order = $order->get_order($order_id);
		}
		return $order;
	}

	// Pass in a WC_Order object, or an order number
	public function get_vat_paid($order, $allow_quick = false, $set_on_quick = false) {

		if (!is_a($order, 'WC_Order') && is_numeric($order)) {
			$order = $this->get_order($order);
		}

		$post_id = (isset($order->post)) ? $order->post->ID : $order->id;

		if ($allow_quick) {
			$vat_paid = get_post_meta($post_id, 'vat_compliance_vat_paid', true);
			if (!empty($vat_paid)) {
				return maybe_unserialize($vat_paid);
			}
		}

		$taxes = $order->get_taxes();

		if (!is_array($taxes)) return false;
		if (empty($taxes)) return 0;

		// Get an array of matches
		$vat_strings = $this->get_vat_matches('regex');

		// Not get_woocommerce_currency(), as currency switcher plugins filter that.
		$base_currency = get_option('woocommerce_currency');
		// WC 2.0 did not store order currencies.
		$currency = method_exists($order, 'get_order_currency') ? $order->get_order_currency() : $base_currency;

		$vat_total = 0;
		$vat_shipping_total = 0;
		$vat_total_base_currency = 0;
		$vat_shipping_total_base_currency = 0;
		$base_currency_totals_are_reliable = true;

		foreach ($taxes as $tax) {
			if (!is_array($tax) || !isset($tax['label'])) continue;
			if (!preg_match($vat_strings, $tax['label'])) continue;

			if (!empty($tax['tax_amount'])) $vat_total += $tax['tax_amount'];
			if (!empty($tax['shipping_tax_amount'])) $vat_shipping_total += $tax['shipping_tax_amount'];


			if ($currency != $base_currency) {
				if (empty($tax['tax_amount_base_currency'])) {
					// This will be wrong, of course, unless your conversion rate is 1:1
					if (!empty($tax['tax_amount'])) $vat_total_base_currency += $tax['tax_amount'];
					if (!empty($tax['shipping_tax_amount'])) $vat_shipping_total_base_currency += $tax['shipping_tax_amount'];
					$base_currency_totals_are_reliable = false;
				} else {
					if (!empty($tax['tax_amount'])) $vat_total_base_currency += $tax['tax_amount_base_currency'];
					if (!empty($tax['shipping_tax_amount'])) $vat_shipping_total_base_currency += $tax['shipping_tax_amount_base_currency'];
				}
			} else {
				$vat_total_base_currency = $vat_total;
				$vat_shipping_total_base_currency = $vat_shipping_total;
			}

		}

		// We may as well return the kitchen sink, since we've spent the cycles on getting it.
		$vat_paid = apply_filters('wc_eu_vat_compliance_get_vat_paid', array(
			'items_total' => $vat_total,
			'shipping_total' => $vat_shipping_total,
			'total' => $vat_total + $vat_shipping_total,
			'currency' => $currency,
			'base_currency' => $base_currency,
			'items_total_base_currency' => $vat_total_base_currency,
			'shipping_total_base_currency' => $vat_shipping_total_base_currency,
			'total_base_currency' => $vat_total_base_currency + $vat_shipping_total_base_currency,
			'base_currency_totals_are_reliable' => $base_currency_totals_are_reliable
		), $order, $taxes, $currency, $base_currency);

/*
e.g. (and remember, there may be other elements which are not VAT).

Array
(
    [62] => Array
        (
            [name] => GB-VAT (UNITED KINGDOM)-1
            [type] => tax
            [item_meta] => Array
                (
                    [rate_id] => Array
                        (
                            [0] => 28
                        )

                    [label] => Array
                        (
                            [0] => VAT (United Kingdom)
                        )

                    [compound] => Array
                        (
                            [0] => 1
                        )

                    [tax_amount_base_currency] => Array
                        (
                            [0] => 2
                        )

                    [tax_amount] => Array
                        (
                            [0] => 3.134
                        )

                    [shipping_tax_amount_base_currency] => Array
                        (
                            [0] => 2.8
                        )

                    [shipping_tax_amount] => Array
                        (
                            [0] => 4.39
                        )

                )

            [rate_id] => 28
            [label] => VAT (United Kingdom)
            [compound] => 1
            [tax_amount_base_currency] => 2
            [tax_amount] => 3.134
            [shipping_tax_amount_base_currency] => 2.8
            [shipping_tax_amount] => 4.39
        )


*/
		if ($set_on_quick) {
			update_post_meta($post_id, 'vat_compliance_vat_paid', apply_filters('wc_eu_vat_compliance_vat_paid', $vat_paid, $order));
		}

		return $vat_paid;

	}


	public function plugin_action_links($links, $file) {
		if (is_array($links) && strpos($file, basename(WC_EU_VAT_COMPLIANCE_DIR).'/eu-vat-compliance') !== false) {
			$page = (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.1', '<')) ? 'woocommerce_settings' : 'wc-settings';
			$settings_link = '<a href="'.admin_url('admin.php').'?page='.$page.'&tab=tax">'.__("WooCommerce Tax Settings", "wc_eu_vat_compliance").'</a>';
			array_unshift($links, $settings_link);
			if (false === strpos($file, 'premium')) {
				$settings_link = '<a href="https://www.simbahosting.co.uk/s3/product/woocommerce-eu-vat-compliance/">'.__("Premium Version", "wc_eu_vat_compliance").'</a>';
				array_unshift($links, $settings_link);
			}
		}
		return $links;
	}

	public function woocommerce_settings_tax_options_end() {
		woocommerce_admin_fields($this->settings);
	}

	public function woocommerce_update_options_tax() {
		woocommerce_update_options($this->settings);
	}

	// From WC 2.2
	public function order_status_to_text($status) {
		$order_statuses = array(
			'wc-pending'    => _x( 'Pending Payment', 'Order status', 'woocommerce' ),
			'wc-processing' => _x( 'Processing', 'Order status', 'woocommerce' ),
			'wc-on-hold'    => _x( 'On Hold', 'Order status', 'woocommerce' ),
			'wc-completed'  => _x( 'Completed', 'Order status', 'woocommerce' ),
			'wc-cancelled'  => _x( 'Cancelled', 'Order status', 'woocommerce' ),
			'wc-refunded'   => _x( 'Refunded', 'Order status', 'woocommerce' ),
			'wc-failed'     => _x( 'Failed', 'Order status', 'woocommerce' ),
		);
		$order_statuses = apply_filters( 'wc_order_statuses', $order_statuses );

		if ($status === true) return $order_statuses;

		if (substr($status, 0, 3) != 'wc-') $status = 'wc-'.$status;
		return (isset($order_statuses[$status])) ? $order_statuses[$status] : __('Unknown', 'wc_eu_vat_compliance').' ('.substr($status, 3).')';
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

	public function admin_notice_no_geoip_database() {
		echo '<div class="error">';
		echo '<h4 style="margin: 1em 0 0 0">'.__('GeoIP database not found', 'wc_eu_vat_compliance').'</h4><p>';
		echo __('You have the GeoIP plugin installed, but it has not yet downloaded its database. This is needed for country detection to work.', 'wc_eu_vat_compliance');
		echo '<a href="'.admin_url('tools.php?page=geoip-detect/geoip-detect.php').'"> '.__('Follow this link and press the Update Now button to download it', 'wcpreselectdefaultcountry').'</a>';
		echo '</p></div>';
	}

	public function admin_notice_no_geoip_plugin() {
		echo '<div class="error">';
		echo '<h4 style="margin: 1em 0 0 0">'.__('Required Plugin Not Found', 'wc_eu_vat_compliance').'</h4><p>';
		echo __('For the WooCommerce EU VAT compliance module to be able to record the country that a customer is ordering from, the (free) GeoIP Detection plugin must be installed and activated.', 'wc_eu_vat_compliance').' ';

		if (current_user_can('install_plugins')) {
			if (!file_exists(WP_PLUGIN_DIR.'/geoip-detect')) {
				echo '<a href="'.wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=geoip-detect'), 'install-plugin_geoip-detect').'">'.__('Follow this link to install it', 'wc_eu_vat_compliance').'</a>';
			} elseif (file_exists(WP_PLUGIN_DIR.'/geoip-detect/geoip-detect.php')) {
				echo '<a href="'.esc_url(wp_nonce_url(self_admin_url('plugins.php?action=activate&plugin=geoip-detect/geoip-detect.php'), 'activate-plugin_geoip-detect/geoip-detect.php')).'">'.__('Follow this link to activate it.', 'wc_eu_vat_compliance').'</a>';
			}
		}
		echo '</p></div>';
	}

	public function is_premium() {
		$premium = WooCommerce_EU_VAT_Compliance('WC_EU_VAT_Compliance_Premium');
		return is_object($premium);
	}
}
endif;

if (!function_exists('WooCommerce_EU_VAT_Compliance')):
function WooCommerce_EU_VAT_Compliance($class = 'WC_EU_VAT_Compliance') {
	global $woocommerce_eu_vat_compliance_classes;
	return (!empty($woocommerce_eu_vat_compliance_classes[$class]) && is_object($woocommerce_eu_vat_compliance_classes[$class])) ? $woocommerce_eu_vat_compliance_classes[$class] : false;
}
endif;

global $woocommerce_eu_vat_compliance_classes;
$woocommerce_eu_vat_compliance_classes = array();
foreach ($classes_to_activate as $cl) {
	if (class_exists($cl) && (empty($woocommerce_eu_vat_compliance_classes[$cl]))) {
		$woocommerce_eu_vat_compliance_classes[$cl] = new $cl;
	}
}
