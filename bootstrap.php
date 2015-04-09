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
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/record-order-country.php');
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/rates.php');
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/widgets.php');
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/preselect-country.php');
@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/premium.php');

// Though the code is separated, some pieces are inter-dependent; the order also matters. So, don't assume you can just change this arbitrarily.
$potential_classes_to_activate = array(
	'WC_EU_VAT_Compliance',
	'WC_EU_VAT_Compliance_VAT_Number',
	'WC_EU_VAT_Compliance_Record_Order_Country',
	'WC_EU_VAT_Compliance_Rates',
	'WC_EU_VAT_Country_PreSelect_Widget',
	'WC_EU_VAT_Compliance_Preselect_Country',
	'WC_EU_VAT_Compliance_Premium',
);

if (is_admin()) {
	@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/reports.php');
	@include_once(WC_EU_VAT_COMPLIANCE_DIR.'/control-centre.php');
	$potential_classes_to_activate[] = 'WC_EU_VAT_Compliance_Reports';
	$potential_classes_to_activate[] = 'WC_EU_VAT_Compliance_Control_Centre';
}

$classes_to_activate = apply_filters('woocommerce_eu_vat_compliance_classes', $potential_classes_to_activate);

if (!class_exists('WC_EU_VAT_Compliance')):
class WC_EU_VAT_Compliance {

	private $default_vat_matches = 'VAT, V.A.T, IVA, I.V.A., Value Added Tax, TVA, T.V.A.';
	public $wc;

	public $at_least_22 = true;
	public $settings;

	private $wcpdf_order_id;

	public $data_sources = array();

	public function __construct() {

		$this->data_sources = array(
			'HTTP_CF_IPCOUNTRY' => __('CloudFlare Geo-Location', 'wc_eu_vat_compliance'),
			'woocommerce' => __('WooCommerce 2.3+ built-in geo-location', 'wc_eu_vat_compliance'),
			'geoip_detect_get_info_from_ip_function_not_available' => __('MaxMind GeoIP database was not installed', 'wc_eu_vat_compliance'),
			'geoip_detect_get_info_from_ip' => __('MaxMind GeoIP database', 'wc_eu_vat_compliance'),
		);

		add_action('before_woocommerce_init', array($this, 'before_woocommerce_init'), 1, 1);
		add_action('plugins_loaded', array($this, 'plugins_loaded'));

		add_action( 'woocommerce_settings_tax_options_end', array($this, 'woocommerce_settings_tax_options_end'));
		add_action( 'woocommerce_update_options_tax', array( $this, 'woocommerce_update_options_tax'));

		add_action('woocommerce_checkout_process', array($this, 'woocommerce_checkout_process'));

		add_filter('network_admin_plugin_action_links', array($this, 'plugin_action_links'), 10, 2);
		add_filter('plugin_action_links', array($this, 'plugin_action_links'), 10, 2);

// 		add_option('woocommerce_eu_vat_compliance_vat_match', $this->default_vat_matches);

		add_action('wpo_wcpdf_process_template_order', array($this, 'wpo_wcpdf_process_template_order'), 10, 2);

		add_action('wpo_wcpdf_footer', array($this, 'wpo_wcpdf_footer'));

		add_action('woocommerce_admin_field_wceuvat_taxclasses', array($this, 'woocommerce_admin_field_wceuvat_taxclasses'));

		add_action('woocommerce_check_cart_items', array($this, 'woocommerce_check_cart_items'));
		add_action('woocommerce_checkout_process', array($this, 'woocommerce_check_cart_items'));

		if (file_exists(WC_EU_VAT_COMPLIANCE_DIR.'/updater/updater.php')) include_once(WC_EU_VAT_COMPLIANCE_DIR.'/updater/updater.php');

	}

	// If EU VAT checkout is forbidden, then this function is where the work is done to prevent it
	public function woocommerce_check_cart_items() {

		// Taxes turned on on the store, and VAT-able orders not forbidden?
		if ('yes' != get_option('woocommerce_eu_vat_compliance_forbid_vatable_checkout', 'no') || 'yes' != get_option('woocommerce_calc_taxes')) return;
		$opts_classes = $this->get_euvat_tax_classes();

		$relevant_products_found = false;
		$cart = $this->wc->cart->get_cart();

		foreach ($cart as $item) {
			if (empty($item['data'])) continue;
			$_product = $item['data'];
			$tax_status = $_product->get_tax_status();
			if ('taxable' != $tax_status) continue;
			$tax_class = $_product->get_tax_class();
			if (empty($tax_class)) $tax_class = 'standard';
			if (in_array($tax_class, $opts_classes)) {
				$relevant_products_found = true;
				break;
			}
		}
		if (!$relevant_products_found) return;

		$taxable_address = $this->wc->customer->get_taxable_address();
		$eu_vat_countries = $this->get_european_union_vat_countries();

		if (empty($taxable_address[0]) || !in_array($taxable_address[0], $eu_vat_countries)) return;

		// If in cart, then warn - they still may select a different VAT country.
		$current_filter = current_filter();
		if ('woocommerce_checkout_process' != $current_filter) {
			// Cart: just warn
			echo "<p class=\"woocommerce-info\" id=\"wceuvat_notpossible\">".apply_filters('wceuvat_euvatcart_message', __('Depending on your country, it may not be possible to purchase all the items in this cart. This is because this store does not sell items liable to EU VAT to EU customers (due to the high costs of complying with EU VAT laws).', 'wc_eu_vat_compliance'))."</p>";
		} else {
			// Attempting to check-out: prevent
			$this->add_wc_error(
				apply_filters('wceuvat_euvatcheckoutforbidden_message', __('This order cannot be processed. Due to the high costs of complying with EU VAT laws, we do not sell items liable to EU VAT to EU customers.', 'wc_eu_vat_compliance'))
			);
		}
	}

	public function get_tax_classes() {
		$tax = new WC_Tax();
		// Does not exist on WC 2.0 (not checked 2.1)
		$tax_classes = (method_exists($tax, 'get_tax_classes')) ? $tax->get_tax_classes() : array_filter( array_map( 'trim', explode( "\n", get_option( 'woocommerce_tax_classes' ) ) ) );
		if (!is_array($tax_classes)) $tax_classes = array();

		$classes_by_title = array('standard' => __('Standard Rate', 'wc_eu_vat_compliance'));

		foreach ( $tax_classes as $class ) {
			$classes_by_title[sanitize_title($class)] = $class;
		}

		return $classes_by_title;
	}

	// Optional: pass an array of slugs of default tax classes, if you have one; otherwise, one will be obtained from self::get_tax_classes();
	public function get_euvat_tax_classes($default = false) {

		if (false === $default) {
			$default = array_keys($this->get_tax_classes());
		}

		// Apply a default value, for if this is not set (people upgrading)
		$opts_classes = get_option('woocommerce_eu_vat_compliance_tax_classes', $default);
		if (!is_array($opts_classes)) $opts_classes = $default;

		return $opts_classes;
	}

	// Input: either a tax class (string, slug) or a WC_Product
	public function product_taxable_class_indicates_variable_eu_vat($product_or_tax_class) {
		if (is_a($product_or_tax_class, 'WC_Product') && 'taxable' != $product_or_tax_class->get_tax_status()) return false;
		if (empty($this->eu_vat_classes)) $this->eu_vat_classes = $this->get_euvat_tax_classes();
		$tax_class = (is_a($product_or_tax_class, 'WC_Product')) ? $product_or_tax_class->get_tax_class() : $product_or_tax_class;
		// WC's handling of the 'default' tax class is rather ugly/non-intuitive - you need the secret knowledge of its name
		if (empty($tax_class)) $tax_class = 'standard';
		return (in_array($tax_class, $this->eu_vat_classes)) ? true : false;
	}


	public function woocommerce_admin_field_wceuvat_taxclasses() {

		$tax_classes = $this->get_tax_classes();
		$opts_classes = $this->get_euvat_tax_classes(array_diff(array_keys($tax_classes), array('zero-rate')));

		$settings_link = (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.1', '<')) ? admin_url('admin.php?page=woocommerce_settings&tab=tax') : admin_url('admin.php?page=wc-settings&tab=tax');

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
			<label><?php _e('Relevant tax classes', 'wc_eu_vat_compliance');?></label>
			</th>
			<td>
				<p><em><?php echo __('Indicate all the WooCommerce tax classes for which variable-by-country EU VAT is charged.', 'wc_eu_vat_compliance').' <a href="'.esc_attr($settings_link).'">'.__('To create additional tax classes, go to the WooCommerce tax settings.', 'wc_eu_vat_compliance').'</a> '.__('Products which are not in one of these tax classes will be excluded from per-country VAT calculations recorded by this plugin (though they may still have traditional EU VAT charged if you have configured them to do so - i.e., the purpose of this setting is to allow you to have a shop selling mixed goods).', 'wc_eu_vat_compliance');?></em></p>
					<?php
						foreach ($tax_classes as $slug => $label) {
							$checked = (in_array($slug, $opts_classes) || in_array('all$all', $opts_classes)) ? ' checked="checked"' : '';
							echo '<input type="checkbox"'.$checked.' id="woocommerce_eu_vat_compliance_tax_classes_'.$slug.'" name="woocommerce_eu_vat_compliance_tax_classes[]" value="'.$slug.'"> <label for="woocommerce_eu_vat_compliance_tax_classes_'.$slug.'">'.htmlspecialchars($label).'</label><br>';
						}
					?>
			</td>
		</tr>
		<?php
	}

	public function wpo_wcpdf_footer($footer) {

		$valid_eu_vat_number = null;
		$vat_number_validated = null;
		$vat_number = null;
		$vat_paid = array();
		$new_footer = $footer;
		$text = '';
		$order = null;

		$order_id = $this->wcpdf_order_id;

		if (!empty($order_id)) {

			$order = $this->get_order($order_id);

			if (is_a($order, 'WC_Order')) {

				$post_id = (isset($order->post)) ? $order->post->ID : $order->id;

				$vat_paid = $this->get_vat_paid($order, true, true);

				$valid_eu_vat_number = get_post_meta($post_id, 'Valid EU VAT Number', true);
				$vat_number_validated = get_post_meta($post_id, 'VAT number validated', true);
				$vat_number = get_post_meta($post_id, 'VAT Number', true);

				// !empty used, because this is only for non-zero VAT
				if (is_array($vat_paid) && !empty($vat_paid['total'])) {
					$text = get_option('woocommerce_eu_vat_compliance_pdf_footer_b2c');

					if (!empty($text)) {
						$new_footer = wpautop( wptexturize( $text ) ) . $footer;
					}
				}

			}

		}

		return apply_filters('wc_euvat_compliance_wpo_wcpdf_footer', $new_footer, $footer, $text, $vat_paid, $vat_number, $valid_eu_vat_number, $vat_number_validated, $order);
	}

	public function wpo_wcpdf_process_template_order($template_id, $order_id) {
		$this->wcpdf_order_id = $order_id;
	}

	public function get_european_union_vat_countries() {
		$eu_countries = $this->wc->countries->get_european_union_countries();
		$extra_countries = array('MC', 'IM');
		return array_merge($eu_countries, $extra_countries);
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

	// This function is for output - it will add on conversions into the indicate currencies
	public function get_amount_in_conversion_currencies($amount, $conversion_currencies, $conversion_rates, $order_currency, $paid = false) {
		foreach ($conversion_currencies as $currency) {
			$rate = ($currency == $order_currency) ? 1 : (isset($conversion_rates['rates'][$currency]) ? $conversion_rates['rates'][$currency] : '??');

			if ('??' == $rate) continue;

			if ($paid !== false) {
				$paid .= ' / ';
			} else {
				$paid = '';
			}
			$paid .= get_woocommerce_currency_symbol($currency).' '.sprintf('%.02f', $amount * $rate);
		}
		return $paid;
	}

	// Pass in a WC_Order object, or an order number
	public function get_vat_paid($order, $allow_quick = false, $set_on_quick = false, $quick_only = false) {

		if (!is_a($order, 'WC_Order') && is_numeric($order)) {
			$order = $this->get_order($order);
		}

		$post_id = (isset($order->post)) ? $order->post->ID : $order->id;

		if ($allow_quick) {
			if (!empty($this->vat_paid_post_id) && $this->vat_paid_post_id == $post_id && !empty($this->vat_paid_info)) {
				$vat_paid = $this->vat_paid_info;
			} else {
				$vat_paid = get_post_meta($post_id, 'vat_compliance_vat_paid', true);
			}
			if (!empty($vat_paid)) {
				$vat_paid = maybe_unserialize($vat_paid);
				// If by_rates is not set, then we need to update the version of the data by including that data asap
				if (isset($vat_paid['by_rates'])) return $vat_paid;
			}
			if ($quick_only) return false;
		}

// This is the wrong approach. What we actually need to do is to take the rate ID, and see what table that comes from. Tables are 1:1 in relationship with classes; thus, certain rate IDs just don't count.
// 		$items = $order->get_items();
// 		if (empty($items)) return false;
// 
// 		foreach ($items as $item) {
// 			if (!is_array($item)) continue;
// 			$tax_class = (empty($item['tax_class'])) ? 'standard' : $item['tax_class'];
// 			if (!$this->product_taxable_class_indicates_variable_eu_vat($tax_class)) {
// 				// New-style EU VAT does not apply to this product - do something
// 				
// 			}
// 		}

		$taxes = $order->get_taxes();

		if (!is_array($taxes)) return false;
		if (empty($taxes)) $taxes = array();

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

		// Add extra information
		$taxes = $this->add_tax_rates_details($taxes);

		$by_rates = array();

		// Some ammendments here in versions 1.5.5+ inspired by Diego Zanella
		foreach ($taxes as $tax) {
			if (!is_array($tax) || !isset($tax['label']) || !preg_match($vat_strings, $tax['label'])) continue;

			$tax_rate_class = empty($tax['tax_rate_class']) ? 'standard' : $tax['tax_rate_class'];

			$is_variable_eu_vat = $this->product_taxable_class_indicates_variable_eu_vat($tax_rate_class);

			$tax_rate_id = $tax['rate_id'];

			if(!isset($by_rates[$tax_rate_id])) {
				$by_rates[$tax_rate_id] = array(
					'is_variable_eu_vat' => $is_variable_eu_vat,
					'items_total' => 0,
					'shipping_total' => 0,
				);
				$by_rates[$tax_rate_id]['rate'] = $tax['tax_rate'];
				$by_rates[$tax_rate_id]['name'] = $tax['tax_rate_name'];
			}

			if (!empty($tax['tax_amount'])) $by_rates[$tax_rate_id]['items_total'] += $tax['tax_amount'];
			if (!empty($tax['shipping_tax_amount'])) $by_rates[$tax_rate_id]['shipping_total'] += $tax['shipping_tax_amount'];

			if ($is_variable_eu_vat) {
				if (!empty($tax['tax_amount'])) $vat_total += $tax['tax_amount'];
				if (!empty($tax['shipping_tax_amount'])) $vat_shipping_total += $tax['shipping_tax_amount'];

				// TODO: Remove all base_currency stuff from here - instead, we are using conversions at reporting time
				if ($currency != $base_currency) {
					if (empty($tax['tax_amount_base_currency'])) {
						// This will be wrong, of course, unless your conversion rate is 1:1
						if (!empty($tax['tax_amount'])) $vat_total_base_currency += $tax['tax_amount'];
						if (!empty($tax['shipping_tax_amount'])) $vat_shipping_total_base_currency += $tax['shipping_tax_amount'];
					} else {
						if (!empty($tax['tax_amount'])) $vat_total_base_currency += $tax['tax_amount_base_currency'];
						if (!empty($tax['shipping_tax_amount'])) $vat_shipping_total_base_currency += $tax['shipping_tax_amount_base_currency'];
					}
				} else {
					$vat_total_base_currency = $vat_total;
					$vat_shipping_total_base_currency = $vat_shipping_total;
				}
			}
		}

		// We may as well return the kitchen sink, since we've spent the cycles on getting it.
		$vat_paid = apply_filters('wc_eu_vat_compliance_get_vat_paid', array(
			'by_rates' => $by_rates,
			'items_total' => $vat_total,
			'shipping_total' => $vat_shipping_total,
			'total' => $vat_total + $vat_shipping_total,
			'currency' => $currency,
			'base_currency' => $base_currency,
			'items_total_base_currency' => $vat_total_base_currency,
			'shipping_total_base_currency' => $vat_shipping_total_base_currency,
			'total_base_currency' => $vat_total_base_currency + $vat_shipping_total_base_currency,
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

		$this->vat_paid_post_id = $post_id;
		$this->vat_paid_info = $vat_paid;

		return $vat_paid;

	}

	// This is here as a funnel that can be changed in future, without needing to adapt everywhere that calls it
	public function round_amount($amount) {
		return round($amount, 2);
	}

	// This function lightly adapted from the work of Diego Zanella
	protected function add_tax_rates_details($taxes) {
		global $wpdb, $table_prefix;

		if(empty($taxes) || !is_array($taxes)) return $taxes;

		$tax_rate_ids = array();
		foreach($taxes as $order_tax_id => $tax) {
			// Keep track of which tax ID corresponds to which ID within the order.
			// This information will be used to add the new information to the correct
			// elements in the $taxes array
			$tax_rate_ids[(int)$tax['rate_id']] = $order_tax_id;
		}

// No reason to record these here
// 				,TR.tax_rate_country
// 				,TR.tax_rate_state
		$SQL = "
			SELECT
				TR.tax_rate_id
				,TR.tax_rate
				,TR.tax_rate_class
				,TR.tax_rate_name
			FROM
				".$table_prefix."woocommerce_tax_rates TR
			WHERE
				(TR.tax_rate_id IN (%s))
		";
		// We cannot use $wpdb::prepare(). We need the result of the implode()
		// call to be injected as is, while the prepare() method would wrap it in quotes.
		$SQL = sprintf($SQL, implode(',', array_keys($tax_rate_ids)));

		// Populate the original tax array with the tax details
		$tax_rates_info = $wpdb->get_results($SQL, ARRAY_A);
		foreach ($tax_rates_info as $tax_rate_info) {
			// Find to which item the details belong, amongst the order taxes
			$order_tax_id = (int)$tax_rate_ids[$tax_rate_info['tax_rate_id']];
			$taxes[$order_tax_id]['tax_rate'] = $tax_rate_info['tax_rate'];
			$taxes[$order_tax_id]['tax_rate_name'] = $tax_rate_info['tax_rate_name'];
			$taxes[$order_tax_id]['tax_rate_class'] = $tax_rate_info['tax_rate_class'];
// 			$taxes[$order_tax_id]['tax_rate_country'] = $tax_rate_info['tax_rate_country'];
// 			$taxes[$order_tax_id]['tax_rate_state'] = $tax_rate_info['tax_rate_state'];

			// Attach the tax information to the original array, for convenience
			$taxes[$order_tax_id]['tax_info'] = $tax_rate_info;
		}

		return $taxes;
	}

	public function get_rate_providers($just_this_one = false) {
		$provider_dirs = apply_filters('wc_eu_vat_rate_provider_dirs', array(WC_EU_VAT_COMPLIANCE_DIR.'/rate-providers'));
		$classes = array();
		foreach ($provider_dirs as $dir) {
			$providers = apply_filters('wc_eu_vat_rate_providers_from_dir', false, $dir);
			if (false === $providers) {
				$providers = scandir($dir);
				foreach ($providers as $k => $file) {
					if ('.' == $file || '..' == $file || '.php' != strtolower(substr($file, -4, 4)) || 'base-' == strtolower(substr($file, 0, 5)) || !is_file($dir.'/'.$file)) unset($providers[$k]);
				}
			}
			foreach ($providers as $file) {
				$key = str_replace('-', '_', sanitize_title(basename(strtolower($file), '.php')));
				$class_name = 'WC_EU_VAT_Compliance_Rate_Provider_'.$key;
				if (!class_exists($class_name)) include_once($dir.'/'.$file);
				if (class_exists($class_name)) $classes[$key] = new $class_name;
			}
		}
		if ($just_this_one) {
			return (isset($classes[$just_this_one])) ? $classes[$just_this_one] : false;
		}
		return $classes;
	}

	public function plugin_action_links($links, $file) {
		if (is_array($links) && strpos($file, basename(WC_EU_VAT_COMPLIANCE_DIR).'/eu-vat-compliance') !== false) {
// 			$page = (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.1', '<')) ? 'woocommerce_settings' : 'wc-settings';
// &tab=tax
			$page = 'wc_eu_vat_compliance_cc';
			$settings_link = '<a href="'.admin_url('admin.php').'?page='.$page.'">'.__("EU VAT Compliance Dashboard", "wc_eu_vat_compliance").'</a>';
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
		if ( isset( $_POST['woocommerce_eu_vat_compliance_vat_match'] ) ) woocommerce_update_options($this->settings);
	}

	// From WC 2.2
	public function order_status_to_text($status) {
		$order_statuses = array(
			'wc-pending'    => _x( 'Pending Payment', 'Order status', 'wc_eu_vat_compliance' ),
			'wc-processing' => _x( 'Processing', 'Order status', 'wc_eu_vat_compliance' ),
			'wc-on-hold'    => _x( 'On Hold', 'Order status', 'wc_eu_vat_compliance' ),
			'wc-completed'  => _x( 'Completed', 'Order status', 'wc_eu_vat_compliance' ),
			'wc-cancelled'  => _x( 'Cancelled', 'Order status', 'wc_eu_vat_compliance' ),
			'wc-refunded'   => _x( 'Refunded', 'Order status', 'wc_eu_vat_compliance' ),
			'wc-failed'     => _x( 'Failed', 'Order status', 'wc_eu_vat_compliance' ),
		);
		$order_statuses = apply_filters( 'wc_order_statuses', $order_statuses );

		if ($status === true) return $order_statuses;

		if (substr($status, 0, 3) != 'wc-') $status = 'wc-'.$status;
		return (isset($order_statuses[$status])) ? $order_statuses[$status] : __('Unknown', 'wc_eu_vat_compliance').' ('.substr($status, 3).')';
	}

	public function before_woocommerce_init() {
		if (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.1', '<')) {
			global $woocommerce;
			$this->wc = $woocommerce;
		} elseif (function_exists('WC')) {
			$this->wc = WC();
		}
	}

	public function plugins_loaded() {

		$this->at_least_22 = (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.2', '<')) ? false : true;

		load_plugin_textdomain('wc_eu_vat_compliance', false, basename(WC_EU_VAT_COMPLIANCE_DIR).'/languages');

		$this->settings = array(array(
			'name' 		=> __( "Forbid EU VAT checkout", 'wc_eu_vat_compliance' ),
			'desc' 		=> __( "If this option is selected, then <strong>all</strong> orders by EU customers (whether consumer or business) which contain goods subject to variable EU VAT (whether the customer is exempt or not) will be forbidden.", 'wc_eu_vat_compliance').' ',
			'desc_tip' 	=> __('This feature is intended only for sellers who wish to avoid issues from EU variable VAT regulations entirely, by not selling any qualifying goods to EU customers (even ones who are potentially VAT exempt).', 'wc_eu_vat_compliance' ).' '.__("Check-out will be forbidden if the cart contains any goods from the relevant tax classes indicated below, and if the customer's VAT country is part of the EU.", 'wc_eu_vat_compliance'),
			'id' 		=> 'woocommerce_eu_vat_compliance_forbid_vatable_checkout',
			'type' 		=> 'checkbox',
			'default'		=> 'no'
		));

		$this->settings[] = array(
			'name' 		=> __( 'Phrase matches used to identify VAT', 'wc_eu_vat_compliance' ),
			'desc' 		=> __( 'A comma-separated (optional spaces) list of strings (phrases) used to identify taxes which are EU VAT taxes. One of these strings must be used in your tax name labels (i.e. the names used in your tax tables) if you wish the tax to be identified as EU VAT.', 'wc_eu_vat_compliance' ),
			'id' 		=> 'woocommerce_eu_vat_compliance_vat_match',
			'type' 		=> 'text',
			'default'		=> $this->default_vat_matches
		);

		$this->settings[] = array(
			'name' 		=> __("Relevant tax classes", 'wc_eu_vat_compliance' ),
			'desc' 		=> __("Select all tax classes which are used in your store for products sold under EU Digital VAT regulations", 'wc_eu_vat_compliance' ),
			'id' 		=> 'woocommerce_eu_vat_compliance_tax_classes',
			'type' 		=> 'wceuvat_taxclasses',
			'default'		=> 'yes'
		);

# TODO
// 		if (!defined('WOOCOMMERCE_VERSION') || version_compare(WOOCOMMERCE_VERSION, '2.2.9', '>=')) {
// 			$this->settings[] = array(
// 				'name' 		=> __( "Show prices based on visitor's GeoIP-detected country", 'wc_eu_vat_compliance' ),
// 				'desc' 		=> __( "If this option is selected, then tax calculations will take into account the visitor's apparent country, without them needing to select a country at the cart of checkout.", 'wc_eu_vat_compliance' ),
// 				'id' 		=> 'woocommerce_eu_vat_compliance_preselect_country',
// 				'type' 		=> 'checkbox',
// 				'default'		=> 'yes'
// 			);
// 		}

		$this->settings[] = array(
			'name' 		=> __( 'Invoice footer text (B2C)', 'wc_eu_vat_compliance' ),
			'desc' 		=> __( "Text to prepend to the footer of your PDF invoice for transactions with VAT paid and non-zero (for supported PDF invoicing plugins)", 'wc_eu_vat_compliance' ),
			'id' 		=> 'woocommerce_eu_vat_compliance_pdf_footer_b2c',
			'type' 		=> 'textarea',
			'css'		=> 'width:100%; height: 100px;'
		);

		if (!empty($_SERVER["HTTP_CF_IPCOUNTRY"]) || class_exists('WC_Geolocation') || !is_admin() || !current_user_can('manage_options')) return;

		if (!function_exists('geoip_detect_get_info_from_ip')) {
			if (empty($_REQUEST['action']) || ('install-plugin' != $_REQUEST['action'] && 'activate' != $_REQUEST['action'])) add_action('admin_notices', array($this, 'admin_notice_no_geoip_plugin'));
		}

		if (function_exists('geoip_detect_get_database_upload_filename')) {
			$filename = geoip_detect_get_database_upload_filename();
			if (!file_exists($filename)) add_action('admin_notices', array($this, 'admin_notice_no_geoip_database'));
		}
	}

	# Function adapted from Aelia Currency Switcher under the GPLv3 (http://aelia.co)
	private function get_visitor_ip_address() {

		$forwarded_for = !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];

		// Field HTTP_X_FORWARDED_FOR may contain multiple addresses, separated by a
		// comma. The first one is the real client, followed by intermediate proxy
		// servers

		$ff = explode(',', $forwarded_for);

		$forwarded_for = array_shift($ff);

		$visitor_ip = trim($forwarded_for);

		# The filter makes it easier to test without having to visit another country. ;-)
		return apply_filters('wc_eu_vat_compliance_visitor_ip', $visitor_ip, $forwarded_for);
	}

	// Here's where the hard work is done - where we get the information on the visitor's country and how it was discerned
	// Returns an array
	public function get_visitor_country_info() {

		$ip = $this->get_visitor_ip_address();
		$info = null;

		// If CloudFlare has already done the hard work, return their result (which is probably more accurate)
		if (!empty($_SERVER["HTTP_CF_IPCOUNTRY"])) {
			$info = null;
			$country_info = array(
				'source' => 'HTTP_CF_IPCOUNTRY',
				'data' => $_SERVER["HTTP_CF_IPCOUNTRY"]
			);
		} elseif (class_exists('WC_Geolocation') && ($data = WC_Geolocation::geolocate_ip()) && is_array($data) && isset($data['country'])) {
			$info = null;
			$country_info = array(
				'source' => 'woocommerce',
				'data' => $data['country']
			);
		} elseif (!function_exists('geoip_detect_get_info_from_ip')) {
			$country_info = array(
				'source' => 'geoip_detect_get_info_from_ip_function_not_available',
				'data' => false
			);
		}

		// Get the GeoIP info even if CloudFlare has a country - store it
		if (function_exists('geoip_detect_get_info_from_ip')) {
			if (isset($country_info)) {
				$country_info_geoip = $this->construct_country_info($ip);
				if (is_array($country_info_geoip) && isset($country_info_geoip['meta'])) $country_info['meta'] = $country_info_geoip['meta'];
			} else {
				$country_info = $this->construct_country_info($ip);
			}

		}

		return apply_filters('wc_eu_vat_compliance_get_visitor_country_info', $country_info, $info, $ip);
	}

	// Make sure that function_exists('geoip_detect_get_info_from_ip') before calling this
	public function construct_country_info($ip) {
		$info = geoip_detect_get_info_from_ip($ip);
		if (!is_object($info) || empty($info->country_code)) {
			$country_info = array(
				'source' => 'geoip_detect_get_info_from_ip',
				'data' => false,
				'meta' => array('ip' => $ip, 'reason' => 'geoip_detect_get_info_from_ip failed')
			);
		} else {
			$country_info = array(
				'source' => 'geoip_detect_get_info_from_ip',
				'data' => $info->country_code,
				'meta' => array('ip' => $ip, 'info' => $info)
			);
		}
		return $country_info;
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
// 				echo '<a href="'.wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=geoip-detect'), 'install-plugin_geoip-detect').'">'.__('Follow this link to install it', 'wc_eu_vat_compliance').'</a>';
				echo '<a href="https://github.com/yellowtree/wp-geoip-detect/releases">'.__('Follow this link to get it', 'wc_eu_vat_compliance').'</a>';
			} elseif (file_exists(WP_PLUGIN_DIR.'/geoip-detect/geoip-detect.php')) {
				echo '<a href="'.esc_url(wp_nonce_url(self_admin_url('plugins.php?action=activate&plugin=geoip-detect/geoip-detect.php'), 'activate-plugin_geoip-detect/geoip-detect.php')).'">'.__('Follow this link to activate it.', 'wc_eu_vat_compliance').'</a>';
			}
		}
		echo '</p></div>';
	}

	public function is_premium() {
		return is_object(WooCommerce_EU_VAT_Compliance('WC_EU_VAT_Compliance_Premium'));
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
