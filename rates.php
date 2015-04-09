<?php

if (!defined('WC_EU_VAT_COMPLIANCE_DIR')) die('No direct access');

// Purpose: have up-to-date VAT rates

if (class_exists('WC_EU_VAT_Compliance_Rates')) return;
class WC_EU_VAT_Compliance_Rates {

	private $rates = array();

	private $known_rates;
	private $which_rate = 'standard_rate';

	private $sources = array(
		'https://wceuvatcompliance.s3.amazonaws.com/rates.json',
		'https://euvatrates.com/rates.json',
		'http://wceuvatcompliance.s3.amazonaws.com/rates.json',
		'http://euvatrates.com/rates.json',
	);

	private $wc;

	public function __construct() {
		add_action('admin_init', array($this, 'admin_init'));
	}

	public function admin_init() {

		$this->known_rates = array(
			'standard_rate' => __('Standard Rate', 'wc_eu_vat_compliance'),
			'reduced_rate' => __('Reduced Rate', 'wc_eu_vat_compliance'),
		);

		global $pagenow;
		// wp-admin/admin.php?page=wc-settings&tab=tax&s=standard
		if ('admin.php' == $pagenow && !empty($_REQUEST['page']) && ('woocommerce_settings' == $_REQUEST['page'] || 'wc-settings' == $_REQUEST['page']) && !empty($_REQUEST['tab']) && 'tax' == $_REQUEST['tab'] && !empty($_REQUEST['section'])) {

			$this->which_rate = 'standard_rate';
			add_action('admin_footer', array($this, 'admin_footer'));
// 			if ('standard' == $_REQUEST['section']) {
// 			} else
			if ('reduced-rate' == $_REQUEST['section']) {
				$this->which_rate = 'reduced_rate';
			}
		}
		if (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.1', '<')) {
			global $woocommerce;
			$this->wc = $woocommerce;
		} elseif (function_exists('WC')) {
			$this->wc = WC();
		}
	}

	// Only fires on the correct page
	public function admin_footer() {
		$get_rates = $this->get_vat_rates();
		$rates = (is_array($get_rates)) ? $get_rates : array();

// 		$rate_description = ($this->which_rate == 'reduced_rate') ? __('Add / Update EU VAT Rates (Reduced)', 'wc_eu_vat_compliance') : __('Add / Update EU VAT Rates (Standard)', 'wc_eu_vat_compliance');
		$rate_description = __('Add / Update EU VAT Rates', 'wc_eu_vat_compliance');

		?>

		<script type="text/javascript">
			jQuery(document).ready(function($) {

				var rates = <?php echo json_encode($rates);?>;

				var availableCountries = [<?php
					$countries = array();
					foreach ( $this->wc->countries->get_allowed_countries() as $value => $label )
						$countries[] = '{ label: "' . $label . '", value: "' . $value . '" }';
					echo implode( ', ', $countries );
				?>];

				var availableStates = [<?php
					$countries = array();
					foreach ( $this->wc->countries->get_allowed_country_states() as $value => $label )
						foreach ( $label as $code => $state )
							$countries[] = '{ label: "' . $state . '", value: "' . $code . '" }';
					echo implode( ', ', $countries );
				?>];

				function wc_eu_vat_compliance_addrow(iso, rate, name) {
					// From WC_Settings_Tax::output_tax_rates (class-wc-settings-tax.php)
					var $tbody = jQuery('.wc_tax_rates').find('tbody');
					var size = $tbody.find('tr').size();
					var possible_existing_lines = $tbody.find('tr');
					var was_updated = false;
					jQuery.each(possible_existing_lines, function (ind, line) {
						var p_iso = jQuery(line).find('td.country input:first').val();
						if ('' == p_iso || p_iso != iso) { return; }
// 						var p_rate = jQuery(line).find('.wc_input_country_rate');
						var p_state = jQuery(line).find('td.state input:first').val();
						var p_postcode = jQuery(line).find('td.postcode input:first').val();
						var p_city = jQuery(line).find('td.city input:first').val();
						if (p_iso == iso && (typeof p_state == 'undefined' || p_state == '') && (typeof p_postcode == 'undefined' || p_postcode == '') && (typeof p_city == 'undefined' || p_city == '')) {
							jQuery(line).find('td.rate input:first').val(rate);
							// Since the VAT amount is in the name, update that too
							jQuery(line).find('td.name input:first').val(name);
							was_updated = true;
							return;
						}
					});
					if (true == was_updated) return;

					<?php
					if (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.3', '<')) {
						echo "var newkey = '[new]['+size+']';\n";
					} else {
						// Style changed in WC 2.3
						echo "var newkey = '[new-'+size+']';\n";
					}
					?>

					var code = '<tr class="new">\
							<td class="sort">&nbsp;</td>\
							<td class="country" width="8%">\
								<input type="text" placeholder="*" name="tax_rate_country'+newkey+'" class="wc_input_country_iso" value="'+iso+'"/>\
							</td>\
							<td class="state" width="8%">\
								<input type="text" placeholder="*" name="tax_rate_state'+newkey+'" />\
							</td>\
							<td class="postcode">\
								<input type="text" placeholder="*" name="tax_rate_postcode'+newkey+'" />\
							</td>\
							<td class="city">\
								<input type="text" placeholder="*" name="tax_rate_city'+newkey+'" />\
							</td>\
							<td class="rate" width="8%">\
								<input type="number" step="any" min="0" placeholder="0" name="tax_rate'+newkey+'" value="'+rate+'" />\
							</td>\
							<td class="name" width="8%">\
								<input type="text" name="tax_rate_name'+newkey+'" value="'+name+'" />\
							</td>\
							<td class="priority" width="8%">\
								<input type="number" step="1" min="1" value="1" name="tax_rate_priority'+newkey+'" />\
							</td>\
							<td class="compound" width="8%">\
								<input type="checkbox" class="checkbox" name="tax_rate_compound'+newkey+'" checked="checked" />\
							</td>\
							<td class="apply_to_shipping" width="8%">\
								<input type="checkbox" class="checkbox" name="tax_rate_shipping'+newkey+'" checked="checked" />\
							</td>\
						</tr>';

					if ( $tbody.find('tr.current').size() > 0 ) {
						$tbody.find('tr.current').after( code );
					} else {
						$tbody.append( code );
					}

					jQuery( "td.country input" ).autocomplete({
						source: availableCountries,
						minLength: 3
					});

					jQuery( "td.state input" ).autocomplete({
						source: availableStates,
						minLength: 3
					});

					return false;
				}

				<?php
					$selector = (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.1', '<')) ? 'a.remove' : 'a.remove_tax_rates';
					$vat_descr_info = esc_attr(__('Note: for any tax you enter below to be recognised as VAT for EU VAT purposes, its name will need to contain one of the following words or phrases:', 'wc_eu_vat_compliance')).' '.WooCommerce_EU_VAT_Compliance()->get_vat_matches('html-printable').'. <a href="?page='.$_REQUEST['page'].'&tab=tax">'.esc_attr(__('You can configure this list in the tax options.', 'wc_eu_vat_compliance')).'<a>';
				?>

				var known_rates = [ "<?php echo implode('", "', array_keys($this->known_rates)); ?>" ];
				var known_rate_descriptions = [ "<?php echo implode('", "', array_values($this->known_rates)); ?>" ];

				var $foot = $('table.wc_tax_rates tfoot <?php echo $selector;?>').first();
				$foot.after('<a href="#" id="euvatcompliance-updaterates" class="button euvatcompliance-updaterates"><?php echo esc_js($rate_description);?></a>');

				var rate_selector = '<select id="euvatcompliance-whichrate">';
				for (i = 0; i < known_rates.length; i++) {
					rate_selector += '<option value="'+known_rates[i]+'">'+known_rate_descriptions[i]+'</option>';
				} 
				rate_selector = rate_selector + '</select>';

				$foot.after('<?php echo esc_js(__('Use rates:', 'wc_eu_vat_compliance')); ?> '+rate_selector);

				$('table.wc_tax_rates').first().before('<p><em><?php echo $vat_descr_info; ?></em></p>');

				$('table.wc_tax_rates').on('click', '.euvatcompliance-updaterates', function() {

					var which_rate = $('#euvatcompliance-whichrate').val();
					if (typeof which_rate == 'undefined' || '' == which_rate) { which_rate = '<?php echo $this->which_rate;?>'; }

					$.each(rates, function(iso, country) {
						var rate = country.standard_rate;
						if (which_rate == 'reduced_rate') {
							var reduced_rate = country.reduced_rate;
							if (typeof reduced_rate != 'boolean') { rate = reduced_rate; }
						}
						// VAT-compliant invoices must show the rate
						var name = 'VAT ('+rate.toString()+'%)';
// 						var name = 'VAT ('+country.country+')';
// 						if (which_rate == 'reduced_rate') {
// 							name = name + ' (<?php echo esc_attr(__('reduced rate', 'wc_eu_vat_compliance'));?>)';
// 						}
						wc_eu_vat_compliance_addrow(iso, rate.toString(), name)
					});
					return false;
				});
			});
		</script>
		<?php
	}

	// Convert from ISO 3166-1 country code to country VAT code
	// https://en.wikipedia.org/wiki/ISO_3166-1#Current_codes
	// http://ec.europa.eu/taxation_customs/resources/documents/taxation/vat/how_vat_works/rates/vat_rates_en.pdf
	public function get_vat_code( $country ) {
		$country_code = $country;

		// Deal with exceptions
		switch ( $country ) {
			case 'GR' :
				$country_code = 'EL';
			break;
			case 'IM' :
			case 'GB' :
				$country_code = 'UK';
			break;
			case 'MC' :
				$country_code = 'FR';
			break;
		}

		return $country_code;
	}

	public function get_iso_code( $country ) {
		$iso_code = $country;

		// Deal with exceptions
		switch ( $country ) {
			case 'EL' :
				$iso_code = 'GR';
			break;
			case 'UK' :
				$iso_code = 'GB';
			break;
// 			case 'FR' :
// 				$iso_code = 'MC';
			break;
		}

		return $iso_code;
	}

	// Takes an EU country code (see get_vat_code())
	// Available rates: standard_rate, reduced_rate (super_reduced_rate, parking_rate)
	public function get_vat_rate_for_country($country_code, $rate = 'standard_rate') {
		$rates = $this->get_vat_rates();
		if (empty($rates) || !is_array($rates) || !isset($rates[$country_code])) return false;
		if (!isset($rates[$country_code][$rate])) return false;
		return $rates[$country_code][$rate];
	}

	public function fetch_remote_vat_rates() {
		$new_rates = false;
		foreach ($this->sources as $url) {
			$get = wp_remote_get($url, array(
				'timeout' => 5,
			));
			if (is_wp_error($get) || !is_array($get)) continue;
			if (!isset($get['response']) || !isset($get['response']['code'])) continue;
			if ($get['response']['code'] >= 300 || $get['response']['code'] < 200 || empty($get['body'])) continue;
			$rates = json_decode($get['body'], true);
			if (empty($rates) || !isset($rates['rates'])) continue;
			$new_rates = $rates['rates'];
			break;
		}
		return $new_rates;
	}

	public function get_vat_rates($use_transient = true) {
		if (!empty($this->rates)) return $this->rates;
		$rates = ($use_transient) ? get_site_transient('wc_euvatrates_rates_byiso') : false;
		if (is_array($rates) && !empty($rates)) {
			$new_rates = $rates;
		} else {
			$this->rates = false;
			$new_rates = $this->fetch_remote_vat_rates();
		}
		if (empty($new_rates) && (false != ($rates_from_file = file_get_contents(WC_EU_VAT_COMPLIANCE_DIR.'/data/rates.json')))) {
			$rates = json_decode($rates_from_file, true);
			if (!empty($rates) && isset($rates['rates'])) $new_rates = $rates['rates'];
		}

		// The array we return should use ISO country codes
		if (!empty($new_rates)) {
			$corrected_rates = array();
			foreach ($new_rates as $country => $rate) {
				$iso = $this->get_iso_code($country);
				$corrected_rates[$iso] = $rate;
			}
			// Add in Monaco
			if (isset($corrected_rates['FR'])) $corrected_rates['MC'] = $corrected_rates['FR'];
			// Add the Isle of Man
			if (isset($corrected_rates['GB'])) {
				$corrected_rates['IM'] = $corrected_rates['GB'];
				$corrected_rates['IM']['country'] = __( 'Isle of Man', 'wc_eu_vat_compliance' );
			}
			set_site_transient('wc_euvatrates_rates_byiso', $corrected_rates, 43200);
			$this->rates = $corrected_rates;
		}
		return $this->rates;
	}

}
