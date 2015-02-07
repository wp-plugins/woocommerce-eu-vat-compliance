<?php

if (!defined('WC_EU_VAT_COMPLIANCE_DIR')) die('No direct access');

/*

Function: pre-select the taxable country in the WooCommerce session, based on GeoIP lookup (or equivalent).
Also handles re-setting the taxable country via self-certification.

Also, provide a widget and shortcode to allow this to be over-ridden by the user (since GeoIP is not infallible)

[euvat_country_selector include_notaxes="true|false"]

The dropdown requires WC 2.2.9 or later to work.

*/

if (defined('WC_EU_VAT_NOCOUNTRYPRESELECT') && WC_EU_VAT_NOCOUNTRYPRESELECT) return;

if (class_exists('WC_EU_VAT_Compliance_Preselect_Country')) return;

class WC_EU_VAT_Compliance_Preselect_Country {

	public function __construct() {
		$this->compliance = WooCommerce_EU_VAT_Compliance();
		add_shortcode('euvat_country_selector', array($this, 'shortcode_euvat_country_selector'));
		add_action('widgets_init', array($this, 'widgets_init'));

		// WC 2.2.9+ only - this filter shows prices on the shop front-end
		add_filter('woocommerce_get_tax_location', array($this, 'woocommerce_customer_taxable_address'), 11);

		// WC 2.0 and later - this filter is used to set their taxable address when they check-out
		add_filter('woocommerce_customer_taxable_address', array($this, 'woocommerce_customer_taxable_address'), 11);

		add_filter('woocommerce_get_price_suffix', array($this, 'woocommerce_get_price_suffix'), 10, 2);

// 		add_action('woocommerce_init', array($this, 'woocommerce_init'));

		// This is hacky. To get the "taxes estimated for (country)" message on the shipping page to work, we use these two actions to hook and then unhook a filter
		if (!defined('WOOCOMMERCE_VERSION') || version_compare(WOOCOMMERCE_VERSION, '2.2.9', '>=')) {
			add_action('woocommerce_cart_totals_after_order_total', array($this, 'woocommerce_cart_totals_after_order_total'));
			add_action('woocommerce_after_cart_totals', array($this, 'woocommerce_after_cart_totals'));
		}

	}

	public function woocommerce_cart_totals_after_order_total() {
		add_filter('woocommerce_countries_base_country', array($this, 'woocommerce_countries_base_country'));
	}

	public function woocommerce_after_cart_totals() {
		remove_filter('woocommerce_countries_base_country', array($this, 'woocommerce_countries_base_country'));
	}

	public function woocommerce_countries_base_country($country) {
		if (!defined('WOOCOMMERCE_CART') || !WOOCOMMERCE_CART) return $country;

		$eu_vat_country = $this->get_preselect_country(false, true);

		return (!empty($eu_vat_country)) ? $eu_vat_country : $country;
	}

	public function widgets_init() {
		register_widget('WC_EU_VAT_Country_PreSelect_Widget');
	}

	public function price_display_replace_callback($matches) {

		if (empty($this->all_countries)) $this->all_countries = $this->compliance->wc->countries->countries;

		$country = $this->get_preselect_country(true);
		$country_name = isset($this->all_countries[$country]) ? $this->all_countries[$country] : '';

		if (!empty($this->suffixing_product) && is_a($this->suffixing_product, 'WC_Product')) {
			if (!$this->compliance->product_taxable_class_indicates_variable_eu_vat($this->suffixing_product)) {
				$country_name = '';
			}
		}

		$search = array(
			'{country}',
			'{country_with_brackets}',
		);
		$replace = array(
			$country_name,
			($country_name) ? '('.$country_name.')' : '',
		);

		return str_replace($search, $replace, $matches[1]);
	}

	// This filter only exists on WC 2.1 and later
	public function woocommerce_get_price_suffix($price_display_suffix, $product) {

		if ($price_display_suffix && preg_match('#\{iftax\}(.*)\{\/iftax\}#', $price_display_suffix, $matches)) {

			// Rounding is needed, otherwise you get an imprecise float (e.g. one can be d:14.199999999999999289457264239899814128875732421875, whilst the other is d:14.2017000000000006565414878423325717449188232421875)

			$decimals = absint( get_option( 'woocommerce_price_num_decimals' ) );
			$including_tax = round($product->get_price_including_tax(), $decimals);
			$excluding_tax = round($product->get_price_excluding_tax(), $decimals);

			if ($including_tax != $excluding_tax) {
				$this->suffixing_product = $product;
				$price_display_suffix = preg_replace_callback( '#\{iftax\}(.*)\{\/iftax\}#', array($this, 'price_display_replace_callback'), $price_display_suffix );

			} else {
				$price_display_suffix = preg_replace( '#\{iftax\}(.*)\{\/iftax\}#', '', $price_display_suffix );
			}

		}

		return $price_display_suffix;

	}

	// In WC 2.2.9, there is a filter woocommerce_get_tax_location which may a better one to use, depending on the purpose (needs verifying)
	public function woocommerce_customer_taxable_address($address) {

		$country = isset($address[0]) ? $address[0] : '';
// 		$state = $address[1];
// 		$postcode = $address[2];
// 		$city = $address[3];

		if (is_admin() && (!defined('DOING_AJAX') || !DOING_AJAX)) return $address;

		if (isset($this->compliance->wc->session) && is_object($this->compliance->wc->session)) {
			# Value set by check-out logic
			$eu_vat_state = $this->compliance->wc->session->get('eu_vat_state_checkout');
		} else {
			$eu_vat_state = '';
		}

		if ( (function_exists('is_checkout') && is_checkout()) || (function_exists('is_cart') && is_cart()) || defined('WOOCOMMERCE_CHECKOUT') || defined('WOOCOMMERCE_CART') ) {

			// Processing of checkout form activity - get from session only

			$allow_from_widget = (!defined('WOOCOMMERCE_CHECKOUT') || !WOOCOMMERCE_CHECKOUT) ? true : false;

			$eu_vat_country = $this->get_preselect_country(false, $allow_from_widget);
			if (!empty($eu_vat_country) && $country != $eu_vat_country) {
				return array($eu_vat_country, $eu_vat_state, '', '');
			}
			return $address;
		}

		$eu_vat_country = $this->get_preselect_country(true);
		if (!empty($eu_vat_country) && $country != $eu_vat_country) {
			return array($eu_vat_country, $eu_vat_state, '', '');
		}

		return $address;

	}

// 	public function woocommerce_init() {
// 		$country = $this->get_preselect_country(true);
// 	}

	public function shortcode_euvat_country_selector($atts) {
		$atts = shortcode_atts(array(
			'include_notaxes' => 1,
			'classes' => '',
		), $atts, 'euvat_country_selector');

		$this->render_dropdown($atts['include_notaxes'], $atts['classes']);
	}

	public function render_dropdown($include_notaxes = 1, $classes = '') {

		static $index_count = 0;
		$index_count++;

		$all_countries = $this->compliance->wc->countries->countries;

		$url = remove_query_arg('wc_country_preselect');

		echo '<form action="'.esc_attr($url).'"><select name="wc_country_preselect" class="countrypreselect_chosencountry '.$classes.'">';

		$selected_country = $this->get_preselect_country();

		if ($include_notaxes) {
			$selected = ('none' == $selected_country) ? ' selected="selected"' : '';
			$label = apply_filters('wc_country_preselect_notaxes_label', __('Show prices without VAT', 'wc_eu_vat_compliance'));
			echo '<option value="none"'.$selected.'>'.htmlspecialchars($label).'</option>';
		}

		foreach ($all_countries as $code => $label) {
			$selected = ($code == $selected_country) ? ' selected="selected"' : '';
			echo '<option value="'.$code.'"'.$selected.'>'.$label.'</option>';
		}

		echo '</select>';

		if ($include_notaxes == 2) {
			$id = 'wc_country_preselect_withoutvat_checkbox_'.$index_count;
			echo '<div class="wc_country_preselect_withoutvat"><input id="'.$id.'" type="checkbox" class="wc_country_preselect_withoutvat_checkbox" '.(('none' == $selected_country) ? 'checked="checked"' : '').'> <label for="'.$id.'">'.apply_filters('wceuvat_showpriceswithoutvat_msg', __('Show prices without VAT', 'wc_eu_vat_compliance')).'</label></div>';
		}

		echo '<noscript><input type="submit" value="'.__('Change', 'wc_eu_vat_compliance').'"</noscript>';

		echo '</form>';

		add_action('wp_footer', array($this, 'wp_footer'));

	}

	public function wp_footer() {

		// Ensure we print once per page only
		static $already_printed;
		if (!empty($already_printed)) return;
		$already_printed = true;

		echo <<<ENDHERE
		<script>
			jQuery(document).ready(function($) {

				// https://stackoverflow.com/questions/1634748/how-can-i-delete-a-query-string-parameter-in-javascript
				function removeURLParameter(url, parameter) {
					//prefer to use l.search if you have a location/link object
					var urlparts= url.split('?');   
					if (urlparts.length>=2) {

						var prefix= encodeURIComponent(parameter)+'=';
						var pars= urlparts[1].split(/[&;]/g);

						//reverse iteration as may be destructive
						for (var i= pars.length; i-- > 0;) {    
							//idiom for string.startsWith
							if (pars[i].lastIndexOf(prefix, 0) !== -1) {  
								pars.splice(i, 1);
							}
						}

						url= urlparts[0]+'?'+pars.join('&');
						return url;
					} else {
						return url;
					}
				}

				var previously_chosen = '';

				$('.wc_country_preselect_withoutvat_checkbox').click(function() {
					var chosen = $(this).is(':checked');
					var selector = $(this).parents('form').find('select.countrypreselect_chosencountry');
					var none_exists_on_menu = $(selector).find('option[value="none"]').length;
					if (chosen) {
						if (none_exists_on_menu) {
// 							$(selector).val('none');
						}
						reload_page_with_country('none');
					} else {
						if (none_exists_on_menu) { $(selector).val('none'); }
						country = $(selector).val();
						if ('none' != country) { reload_page_with_country(country); }
					}
				});

				function reload_page_with_country(chosen) {
					var url = removeURLParameter(document.location.href.match(/(^[^#]*)/)[0], 'wc_country_preselect');
					if (url.indexOf('?') > -1){
						url += '&wc_country_preselect='+chosen;
					} else {
						url += '?&wc_country_preselect='+chosen;
					}
					window.location.href = url;
				}

				$('select.countrypreselect_chosencountry').change(function() {
					var chosen = $(this).val();
					reload_page_with_country(chosen);
				});
			});
		</script>
ENDHERE;
	}

	public function get_preselect_country($allow_via_geoip = true, $allow_from_widget = true, $allow_from_request = true, $allow_from_session = true) {
// 		$allow_via_session = true;

		// Priority: 1) Something set via _REQUEST 2) Something already set in the session 3) GeoIP country

		$countries = $this->compliance->wc->countries->countries;

// 		if (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && 'woocommerce_update_order_review' == $_POST['action']) $allow_via_session = false;
		# Something set via _REQUEST? or _POST from shipping page calculator?
		if ($allow_from_request && (!empty($_REQUEST['wc_country_preselect']) || !empty($_POST['calc_shipping_country']))) {
			$req_country = (!empty($_POST['calc_shipping_country'])) ? $_POST['calc_shipping_country'] : $_REQUEST['wc_country_preselect'];

			if ('none' == $req_country || isset($countries[$req_country])) {

				if (isset($this->compliance->wc->customer)) {
					$customer = $this->compliance->wc->customer;
					// Set shipping/billing countries, so that the choice persists until the checkout
					if (is_a($customer, 'WC_Customer')) {
						$customer->set_country($req_country);
						$customer->set_shipping_country($req_country);
					}
				}

				if (isset($this->compliance->wc->session)) {
					if ('none' == $req_country) {
						$this->compliance->wc->session->set('eu_vat_country_widget', '');
						$this->compliance->wc->session->set('eu_vat_state_widget', '');
					} else {
						$this->compliance->wc->session->set('eu_vat_country_widget', $req_country);
						$this->compliance->wc->session->set('eu_vat_state_widget', '');
					}
				}

				return $req_country;
			}
		}

		# Something set in the session (via the widget)?
		if ($allow_from_widget) {
			$session_widget_country = (isset($this->compliance->wc->session)) ? $this->compliance->wc->session->get('eu_vat_country_widget') : '';
			#$eu_vat_state = $this->compliance->wc->session->get('eu_vat_state_widget');

			if ('none' == $session_widget_country || ($session_widget_country && isset($countries[$session_widget_country]))) return $session_widget_country;
		}

		if ($allow_from_session) {
			# Something already set in the session (via the checkout)?
			$session_country = (isset($this->compliance->wc->session)) ? $this->compliance->wc->session->get('eu_vat_country_checkout') : '';
			#$eu_vat_state = $this->compliance->wc->session->get('eu_vat_state_checkout');

			if ('none' == $session_country || ($session_country && isset($countries[$session_country]))) return $session_country;
		}

		# GeoIP country?
		if ($allow_via_geoip) {
			$country_info = $this->compliance->get_visitor_country_info();
			$geoip_country = (!empty($country_info['data'])) ? $country_info['data'] : '';

			if (isset($countries[$geoip_country])) {
				if (isset($this->compliance->wc->session)) {
					// Put in session, so that it will be retained on cart/checkout pages
					$this->compliance->wc->session->set('eu_vat_state_widget', '');
					$this->compliance->wc->session->set('eu_vat_country_widget', $geoip_country);
				}
				return $geoip_country;
			}
		}

		$woo_country = isset($this->compliance->wc->customer) ? $this->compliance->wc->customer->get_country() : $this->compliance->wc->countries->get_base_country();

		if ($woo_country) return $woo_country;

		# No default
		return false;

	}

}