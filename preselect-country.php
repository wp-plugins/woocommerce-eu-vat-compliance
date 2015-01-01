<?php

if (!defined('WC_EU_VAT_COMPLIANCE_DIR')) die('No direct access');

/*

Function: pre-select the taxable country in the WooCommerce session, based on GeoIP lookup (or equivalent).
Also handles re-setting the taxable country via self-certification.

Also, provide a widget and shortcode to allow this to be over-ridden by the user (since GeoIP is not infallible)

[euvat_country_selector include_notaxes="true|false"]

The dropdown requires WC 2.2.9 or later to work.

*/

if (class_exists('WC_EU_VAT_Compliance_Preselect_Country')) return;

class WC_EU_VAT_Compliance_Preselect_Country {

	public function __construct() {
		$this->compliance = WooCommerce_EU_VAT_Compliance();
		add_shortcode('euvat_country_selector', array($this, 'shortcode_euvat_country_selector'));
		add_action('widgets_init', array($this, 'widgets_init'));

		// WC 2.2.9+ only - this filter shows prices on the shop front-end
		add_filter('woocommerce_get_tax_location', array($this, 'woocommerce_customer_taxable_address'));

		// WC 2.0 and later - this filter is used to set their taxable address when they check-out
		add_filter('woocommerce_customer_taxable_address', array($this, 'woocommerce_customer_taxable_address'));

		add_filter('woocommerce_get_price_suffix', array($this, 'woocommerce_get_price_suffix'), 10, 2);

// 		add_action('woocommerce_init', array($this, 'woocommerce_init'));

	}

	public function widgets_init() {
		register_widget('WC_EU_VAT_Country_PreSelect_Widget');
	}

	public function price_display_replace_callback($matches) {

		if (empty($this->all_countries)) $this->all_countries = $this->compliance->wc->countries->countries;

		$country = $this->get_preselect_country(true);

		$search = '{country}';
		$replace = isset($this->all_countries[$country]) ? $this->all_countries[$country] : '';

		return str_replace($search, $replace, $matches[1]);
	}

	// This filter only exists on WC 2.1 and later
	public function woocommerce_get_price_suffix($price_display_suffix, $product) {

		if ($price_display_suffix && preg_match('#\{iftax\}(.*)\{\/iftax\}#', $price_display_suffix, $matches)) {

			$including_tax = $product->get_price_including_tax();
			$excluding_tax = $product->get_price_excluding_tax();

			if ($including_tax != $excluding_tax) {
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

		if (is_admin() && !defined('DOING_AJAX')) return $address;

		if (isset($this->compliance->wc->session) && is_object($this->compliance->wc->session)) {
			$eu_vat_state = $this->compliance->wc->session->get('eu_vat_state');
		} else {
			$eu_vat_state = '';
		}

		// Checkout/cart - get from session only
		if ( (function_exists('is_checkout') && is_checkout()) || (function_exists('is_cart') && is_cart()) || defined('WOOCOMMERCE_CHECKOUT') || defined('WOOCOMMERCE_CART') ) {
			$eu_vat_country = $this->get_preselect_country(false);

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
			'include_notaxes' => true,
			'classes' => '',
		), $atts, 'euvat_country_selector');

		$this->render_dropdown($atts['include_notaxes'], $atts['classes']);
	}

	public function render_dropdown($include_notaxes = true, $classes = '') {

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

		echo '</select><noscript><input type="submit" value="'.__('Change', 'wc_eu_vat_compliance').'"</noscript></form>';

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

				$('select.countrypreselect_chosencountry').change(function() {
					var chosen = $(this).val();
					var url = removeURLParameter(window.location.href, 'wc_country_preselect');
					if (url.indexOf('?') > -1){
						url += '&wc_country_preselect='+chosen;
					} else {
						url += '?&wc_country_preselect='+chosen;
					}
					window.location.href = url;
				});
			});
		</script>
ENDHERE;
	}

	public function get_preselect_country($allow_via_geoip = true) {

		// Priority: 1) Something set via _REQUEST 2) Something already set in the session 3) GeoIP country

		$countries = $this->compliance->wc->countries->countries;

		# Something set via _REQUEST?
		if (!empty($_REQUEST['wc_country_preselect'])) {
			$req_country = $_REQUEST['wc_country_preselect'];
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
						$this->compliance->wc->session->set('eu_vat_country', '');
						$this->compliance->wc->session->set('eu_vat_state', '');
					} else {
						$this->compliance->wc->session->set('eu_vat_country', $req_country);
						$this->compliance->wc->session->set('eu_vat_state', '');
					}
				}

				return $req_country;
			}
		}

		# Something already set in the session?
		$session_country = (isset($this->compliance->wc->session)) ? $this->compliance->wc->session->get('eu_vat_country') : '';
		#$eu_vat_state = $this->compliance->wc->session->get('eu_vat_state');

		if ('none' == $session_country || ($session_country && isset($countries[$session_country]))) {
			return $session_country;
		}

		# GeoIP country?
		if ($allow_via_geoip) {
			$country_info = $this->compliance->get_visitor_country_info();
			$geoip_country = (!empty($country_info['data'])) ? $country_info['data'] : '';

			if (isset($countries[$geoip_country])) {
				if (isset($this->compliance->wc->session)) {
					// Put in session, so that it will be retained on cart/checkout pages
					$this->compliance->wc->session->set('eu_vat_state', '');
					$this->compliance->wc->session->set('eu_vat_country', $geoip_country);
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

class WC_EU_VAT_Country_PreSelect_Widget extends WP_Widget {

	public function __construct() {
		$widget_ops = array('classname' => 'country_preselect', 'description' => __('Allow the visitor to set their taxation country (to show correct taxes)', 'wc_eu_vat_compliance') );
		
		$this->WP_Widget('WC_EU_VAT_Country_PreSelect_Widget', __('WooCommerce Tax Country Chooser', 'wc_eu_vat_compliance'), $widget_ops); 
	}

	public function widget( $args, $instance ) {
		extract($args);

		echo $before_widget;
		$title = empty($instance['title']) ? ' ' : apply_filters('widget_title', $instance['title']);
		
		if (!empty($title))
			echo $before_title . htmlspecialchars($title) . $after_title;;
		
		if (!empty($instance['explanation'])) echo '<div class="countrypreselect_explanation">'.$instance['explanation'].'</div>';

		$include_notaxes = !empty($instance['include_notaxes']);

		$preselect = WooCommerce_EU_VAT_Compliance('WC_EU_VAT_Compliance_Preselect_Country');
		$preselect->render_dropdown($include_notaxes);

		echo $after_widget;
	}

	// Back-end options
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '' ) );
		$title = empty($instance['title']) ? '' : $instance['title'];
		$explanation = empty($instance['explanation']) ? '' : $instance['explanation'];
		$include_notaxes = empty($instance['include_notaxes']) ? false : true;

		if (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.2.9', '<')) {
			echo '<p style="color: red">'.sprintf(__('Due to limitations in earlier versions, this widget requires WooCommerce %s or later, and will not work on your version (%s).', 'wc_eu_vat_compliance'), '2.2.9', WOOCOMMERCE_VERSION).'</p>';
		}

		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'wc_eu_vat_compliance');?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></label></p>

		<p><label for="<?php echo $this->get_field_id('explanation'); ?>"><?php _e('Explanatory text (HTML accepted):', 'wc_eu_vat_compliance');?> <textarea class="widefat" id="<?php echo $this->get_field_id('explanation'); ?>" name="<?php echo $this->get_field_name('explanation'); ?>"><?php echo htmlentities($explanation); ?></textarea> </label></p>

		<p><input id="<?php echo $this->get_field_id('include_notaxes'); ?>" name="<?php echo $this->get_field_name('include_notaxes'); ?>" type="checkbox" value="1" <?php if ($include_notaxes) echo ' checked="checked"';?>/><label for="<?php echo $this->get_field_id('include_notaxes'); ?>"><?php echo htmlspecialchars(__('Include option for the customer to show prices with no VAT.', 'wc_eu_vat_compliance'));?> </label></p>

		<?php

	}

	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = $new_instance['title'];
		$instance['explanation'] = $new_instance['explanation'];
		$instance['include_notaxes'] = (!empty($new_instance['include_notaxes'])) ? true : false;
		return $instance;
	}

}
