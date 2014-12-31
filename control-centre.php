<?php

if (!defined('WC_EU_VAT_COMPLIANCE_DIR')) die('No direct access');

// Purpose: provide a central location where all relevant features can be accessed

/*
Components to have:
- Flight-check
- Link to reports
- Link to settings (eventually: move settings)
- Link to tax rates
- Link to Premium
- Link to GeoIP settings, if needed + GeoIP status

- Switch plugin action link to point here

- Add FAQ link at the top, if/when there are some
*/

// TODO: Test some more on WC 2.0
// TODO: Link to documentation, when written

class WC_EU_VAT_Compliance_Control_Centre {

	public function __construct() {
		add_action('admin_menu', array($this, 'admin_menu'));
// 		add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
		add_filter('woocommerce_screen_ids', array($this, 'woocommerce_screen_ids'));
		add_action('wp_ajax_wc_eu_vat_cc', array($this, 'ajax'));
	}

	public function ajax() {

		if (empty($_POST) || empty($_POST['subaction']) || !isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wc_eu_vat_nonce')) die('Security check');

		if ('savesettings' == $_POST['subaction']) {

			if (empty($_POST['settings']) || !is_string($_POST['settings'])) die;

			parse_str($_POST['settings'], $posted_settings);

			$vat_settings = $this->get_settings_vat();
			$tax_settings = $this->get_settings_tax();

			$exchange_rate_providers = WooCommerce_EU_VAT_Compliance()->get_rate_providers();

			$exchange_rate_settings = $this->get_settings();

			if (!empty($exchange_rate_providers) && is_array($exchange_rate_providers)) {
				foreach ($exchange_rate_providers as $key => $provider) {
					$settings = method_exists($provider, 'settings_fields') ? $provider->settings_fields() : false;
					if (!is_string($settings) && !is_array($settings)) continue;
					if (is_array($settings)) {
						$exchange_rate_settings[] = $settings;
					}
				}
			}

			$all_settings = array_merge($vat_settings, $tax_settings, $exchange_rate_settings);

			$any_found = false;

			// Save settings
			// If this gets more complex, we should instead use WC_Admin_Settings::save_fields()
			foreach ($all_settings as $setting) {
				if (!is_array($setting) || empty($setting['id'])) continue;
				if ($setting['type'] == 'euvat_tax_options_section' || $setting['type'] == 'sectionend') continue;

				if (!isset($posted_settings[$setting['id']])) {
	// 				error_log("NOT FOUND: ".$setting['id']);
					continue;
				}

				$value = null;

	// error_log_v($posted_settings[$setting['id']]);
	// error_log($setting['id'].": ".$setting['type']);

				switch ($setting['type']) {
					case 'text';
					case 'radio';
					case 'select';
					$value = $posted_settings[$setting['id']];
					break;
					case 'textarea';
					$value = wp_kses_post( trim( $posted_settings[$setting['id']] ) );
					break;
					case 'checkbox';
					$value = empty($posted_settings[$setting['id']]) ? 'no' : 'yes';
					break;
				}

				if (!is_null($value)) {
					$any_found = true;
					update_option($setting['id'], $value);
				}

	// 			error_log($setting['id'].": ".serialize($value)." - GO");
			}

			if (!$any_found) {
				echo json_encode(array('result' => 'no options found'));
				die;
			}

			echo json_encode(array('result' => 'ok'));
		} elseif ('testprovider' == $_POST['subaction'] && !empty($_POST['key']) && !empty($_POST['tocurrency'])) {

			$providers = WooCommerce_EU_VAT_Compliance()->get_rate_providers();

			$to_currency = $_POST['tocurrency'];
			// Base currency
			$from_currency = get_option('woocommerce_currency');

			if (!is_array($providers) || empty($providers[$_POST['key']])) {
				echo json_encode(array('response' => 'Error: provider not found'));
				die;
			}

			$provider = $providers[$_POST['key']];

			$result = $provider->convert($from_currency, $to_currency, 10);

			$currency_code_options = get_woocommerce_currencies();

			$from_currency_label = $from_currency;
			if (isset($currency_code_options[$from_currency])) $from_currency_label = $currency_code_options[$from_currency]." - $from_currency";

			$to_currency_label = $to_currency;
			if (isset($currency_code_options[$to_currency])) $to_currency_label = $currency_code_options[$to_currency]." - $to_currency";

			if (false === $result) {
				echo json_encode(array('response' => __('Failed: The currency conversion failed. Please check the settings, that the chosen provider provides exchange rates for your chosen currencies, and the outgoing network connectivity from your webserver.', 'wc_eu_vat_compliance')));
				die;
			}

			echo json_encode(array('response' => sprintf(__('Success: %s currency units in your shop base currency (%s) are worth %s currency units in your chosen VAT reporting currency (%s)', 'wc_eu_vat_compliance'), '10.00', $from_currency_label, $result, $to_currency_label)));

		}

		die;

	}

	public function woocommerce_screen_ids($screen_ids) {
		if (!in_array('woocommerce_page_wc_eu_vat_compliance_cc', $screen_ids)) $screen_ids[] = 'woocommerce_page_wc_eu_vat_compliance_cc';
		return $screen_ids;
	}

	public function admin_menu() {
		add_submenu_page(
			'woocommerce',
			__('EU VAT Compliance', 'wc_eu_vat_compliance'),
			__('EU VAT Compliance', 'wc_eu_vat_compliance'),
			'manage_woocommerce',
			'wc_eu_vat_compliance_cc',
			array($this, 'settings_page')
		);
	}

	public function settings_page() {

		$tabs = apply_filters('wc_eu_vat_compliance_cc_tabs', array(
// 			'general' => __('General', 'wc_eu_vat_compliance'),
			'settings' => __('Settings', 'wc_eu_vat_compliance'),
			'readiness' => __('Readiness Report', 'wc_eu_vat_compliance'),
			'reports' => __('VAT Reports', 'wc_eu_vat_compliance'),
			'premium' => __('Premium', 'wc_eu_vat_compliance')
		));

		// TODO: Return to general
		$active_tab = !empty($_REQUEST['tab']) ? $_REQUEST['tab'] : 'settings';

		$this->compliance = WooCommerce_EU_VAT_Compliance();

		$version = $this->compliance->get_version();
		$premium = false;

		if (!$this->compliance->is_premium()) {
			// What to do here?
		} else {
			$premium = true;
			$version .= ' '.__('(premium)', 'wc_eu_vat_compliance');
		}

// .' - '.sprintf(__('version %s', 'wc_eu_vat_compliance'), $version);
		?>
		<h1><?php echo __('EU VAT Compliance', 'wc_eu_vat_compliance').' '.__('for WooCommerce', 'wc_eu_vat_compliance');?></h1>
		<a href="https://wordpress.org/support/plugin/woocommerce-eu-vat-compliance/"><?php _e('Support', 'wc_eu_vat_compliance');?></a> | 
		<?php if (!$premium) {
			?><a href="https://www.simbahosting.co.uk/s3/product/woocommerce-eu-vat-compliance/"><?php _e("Premium", 'wc_eu_vat_compliance');?></a> |
		<?php } ?>
		<a href="https://www.simbahosting.co.uk/s3/shop/"><?php _e('More plugins', 'wc_eu_vat_compliance');?></a> |
		<a href="http://updraftplus.com">UpdraftPlus WordPress Backups</a> | 
		<a href="http://david.dw-perspective.org.uk"><?php _e("Lead developer's homepage",'wc_eu_vat_compliance');?></a>
		<!--<a href="https://wordpress.org/plugins/woocommerce-eu-vat-compliance/faq/">FAQs</a> | -->
		- <?php _e('Version','wc_eu_vat_compliance');?>: <?php echo $version; ?>
		<br>

		<h2 class="nav-tab-wrapper" id="wceuvat_tabs" style="margin: 14px 0px;">
		<?php

		foreach ($tabs as $slug => $title) {
			?>
				<a class="nav-tab <?php if($slug == $active_tab) echo 'nav-tab-active'; ?>" href="#wceuvat-navtab-<?php echo $slug;?>-content" id="wceuvat-navtab-<?php echo $slug;?>"><?php echo $title;?></a>
			<?php
		}

		echo '</h2>';

		foreach ($tabs as $slug => $title) {
			echo "<div class=\"wceuvat-navtab-content\" id=\"wceuvat-navtab-".$slug."-content\"";
			if ($slug != $active_tab) echo ' style="display:none;"';
			echo ">";

			if (method_exists($this, 'render_tab_'.$slug)) call_user_func(array($this, 'render_tab_'.$slug));

			do_action('wc_eu_vat_compliance_cc_tab_'.$slug);

			echo "</div>";
		}

		add_action('admin_footer', array($this, 'admin_footer'));
		
	}

	private function render_tab_premium() {
		echo '<h2>'.__('Premium Version', 'wc_eu_vat_compliance').'</h2>';

		echo '<p><em><a href="https://www.simbahosting.co.uk/s3/product/woocommerce-eu-vat-compliance/">'.__('A premium version of this plugin is available at this link', 'wc_eu_vat_compliance').'</a></em></p>';

?>
<h3>Current additional features</h3>
<ul style="max-width: 800px;list-style-type: disc; list-style-position: inside;">


<li><strong>VAT-registered buyers can be exempted, and their numbers validated:</strong> a VAT number can be entered at the check-out, and it will be validated (via VIES). Qualifying customers can then be exempted from VAT on their purchase, and their information recorded. This feature is backwards-compatible with the old official WooCommerce "EU VAT Number" extension, so you will no longer need that plugin, and its data will be maintained. The customer's VAT number will be appended to the billing address where shown (e.g. order summary email, PDF invoices). An extra, configurable line specific to this situation can be added to the footer of the PDF invoice (when using the <a href="https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/">the free WooCommerce PDF invoices and packing slips plugin</a>).</li>

<li><strong>Optionally allow B2B sales only</strong> - for shop owners who wish to only make sales that are VAT-exempt (i.e. B2B sales only), you can require that any EU customers enter a valid EU VAT number at the check-out.</li>

<li><strong>CSV download:</strong> A CSV containing all orders with EU VAT data can be downloaded (including full compliance information).</li>

<li><strong>Non-contradictory evidences:</strong> require two non-contradictory evidences of location (if the customer address and GeoIP lookup contradict, then the customer will be asked to self-certify his location, by choosing between them).</li>

<li><strong>Show multiple currencies for VAT taxes on PDF invoices produced by <a href="https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/">the free WooCommerce PDF invoices and packing slips plugin</a>.</li>


</ul>
<?php

	}

// 	private function render_class_settings($name) {
// 		if (false == ($class = WooCommerce_EU_VAT_Compliance($name))) return false;
// 		if (empty($class->settings)) return false;
// 		woocommerce_admin_fields($class->settings);
// 		return true;
// 	}

	public function woocommerce_admin_field_euvat_tax_options_section($value) {
		if ( ! empty( $value['title'] ) ) {
			echo '<h3>' . esc_html( $value['title'] ) . '</h3>';
		}
		if ( ! empty( $value['desc'] ) ) {
			echo wpautop( wptexturize( wp_kses_post( $value['desc'] ) ) );
		}
		echo '<div>';
		echo '<table class="form-table">'. "\n\n";
		if ( ! empty( $value['id'] ) ) {
			do_action( 'woocommerce_settings_' . sanitize_title( $value['id'] ) );
		}
	}

	public function woocommerce_settings_euvat_vat_options_after() {
		echo '</div>';
	}

	public function woocommerce_settings_euvat_tax_options_after() {
		echo '</div>';
	}

	public function get_settings_vat() {
		$vat_settings = array(
			array( 'title' => __( 'WooCommerce VAT settings (new settings from the EU VAT compliance plugin)', 'wc_eu_vat_compliance' ), 'type' => 'euvat_tax_options_section', 'desc' => '', 'id' => 'euvat_vat_options' ),
		);

// Premium has a "force VAT display" option that is not yet implemented
// 		$get_from = array('WC_EU_VAT_Compliance', 'WC_EU_VAT_Compliance_VAT_Number', 'WC_EU_VAT_Compliance_Premium');
		$get_from = array('WC_EU_VAT_Compliance', 'WC_EU_VAT_Compliance_VAT_Number');

		foreach ($get_from as $name) {
			if (false == ($class = WooCommerce_EU_VAT_Compliance($name))) continue;
			if (empty($class->settings)) continue;
			$vat_settings = array_merge($vat_settings, $class->settings);
		}

		$vat_settings[] = array( 'type' => 'sectionend', 'id' => 'euvat_vat_options' );

		return $vat_settings;
	}

	public function get_settings_tax() {
		// From class-wc-settings-tax.php
		$tax_settings = array(

			array( 'title' => __( 'Other WooCommerce tax options potentially relevant for EU VAT compliance', 'wc_eu_vat_compliance' ), 'type' => 'euvat_tax_options_section','desc' => '', 'id' => 'euvat_tax_options' ),

			array(
				'title'   => __( 'Enable Taxes', 'woocommerce' ),
				'desc'    => __( 'Enable taxes and tax calculations', 'woocommerce' ),
				'id'      => 'woocommerce_calc_taxes',
				'default' => 'no',
				'type'    => 'checkbox'
			),

			array(
				'title'    => __( 'Prices Entered With Tax', 'woocommerce' ),
				'id'       => 'woocommerce_prices_include_tax',
				'default'  => 'no',
				'type'     => 'radio',
				'desc_tip' =>  __( 'This option is important as it will affect how you input prices. Changing it will not update existing products.', 'woocommerce' ),
				'options'  => array(
					'yes' => __( 'Yes, I will enter prices inclusive of tax', 'woocommerce' ),
					'no'  => __( 'No, I will enter prices exclusive of tax', 'woocommerce' )
				),
			),

			array(
				'title'    => __( 'Calculate Tax Based On:', 'woocommerce' ),
				'id'       => 'woocommerce_tax_based_on',
				'desc_tip' =>  __( 'This option determines which address is used to calculate tax.', 'woocommerce' ),
				'default'  => 'shipping',
				'type'     => 'select',
				'options'  => array(
					'shipping' => __( 'Customer shipping address', 'woocommerce' ),
					'billing'  => __( 'Customer billing address', 'woocommerce' ),
					'base'     => __( 'Shop base address', 'woocommerce' )
				),
			),

			array(
				'title'    => __( 'Default Customer Address:', 'woocommerce' ),
				'id'       => 'woocommerce_default_customer_address',
				'desc_tip' =>  __( 'This option determines the customers default address (before they input their own).', 'woocommerce' ),
				'default'  => 'base',
				'type'     => 'select',
				'options'  => array(
					''     => __( 'No address', 'woocommerce' ),
					'base' => __( 'Shop base address', 'woocommerce' ),
				),
			),

// 			array(
// 				'title'   => __( 'Additional Tax Classes', 'woocommerce' ),
// 				'desc'    => __( 'List additional tax classes below (1 per line). This is in addition to the default <code>Standard Rate</code>. Tax classes can be assigned to products.', 'woocommerce' ),
// 				'id'      => 'woocommerce_tax_classes',
// 				'css'     => 'width:100%; height: 65px;',
// 				'type'    => 'textarea',
// 				'default' => sprintf( __( 'Reduced Rate%sZero Rate', 'woocommerce' ), PHP_EOL )
// 			),

			array(
				'title'   => __( 'Display prices in the shop:', 'woocommerce' ),
				'id'      => 'woocommerce_tax_display_shop',
				'default' => 'excl',
				'type'    => 'select',
				'options' => array(
					'incl'   => __( 'Including tax', 'woocommerce' ),
					'excl'   => __( 'Excluding tax', 'woocommerce' ),
				)
			),

			array(
				'title'   => __( 'Price display suffix:', 'woocommerce' ),
				'id'      => 'woocommerce_price_display_suffix',
				'default' => '',
				'class' => 'widefat',
				'type'    => 'text',
				'desc'    => __( 'Define text to show after your product prices. This could be, for example, "inc. Vat" to explain your pricing. You can also have prices substituted here using one of the following: <code>{price_including_tax}, {price_excluding_tax}</code>. Content wrapped in-between <code>{iftax}</code> and <code>{/iftax}</code> will display only if there was tax; within that, <code>{country}</code> will be replaced by the name of the country used to calculate tax.', 'woocommerce' ),
			),

			array(
				'title'   => __( 'Display prices during cart/checkout:', 'woocommerce' ),
				'id'      => 'woocommerce_tax_display_cart',
				'default' => 'excl',
				'type'    => 'select',
				'options' => array(
					'incl'   => __( 'Including tax', 'woocommerce' ),
					'excl'   => __( 'Excluding tax', 'woocommerce' ),
				),
				'autoload'      => false
			),

			array(
				'title'   => __( 'Display tax totals:', 'woocommerce' ),
				'id'      => 'woocommerce_tax_total_display',
				'default' => 'itemized',
				'type'    => 'select',
				'options' => array(
					'single'     => __( 'As a single total', 'woocommerce' ),
					'itemized'   => __( 'Itemized', 'woocommerce' ),
				),
				'autoload' => false
			),

			array( 'type' => 'sectionend', 'id' => 'euvat_tax_options' ),

		);

		return $tax_settings;
	}

	private function render_tab_settings() {
		echo '<h2>'.__('Settings', 'wc_eu_vat_compliance').'</h2>';

		echo '<p><em>'.__('This control panel is under rapid development. Please make sure you keep this plugin up-to-date with any available updates that WordPress tells you are available.', 'wc_eu_vat_compliance').'</em></p>';

		echo '<p><em>'.__('Many settings below can also be found in other parts of your WordPress dashboard; they are brought together here also for convenience.', 'wc_eu_vat_compliance').'</em></p>';

		$tax_settings_link = (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.1', '<')) ? admin_url('admin.php?page=woocommerce_settings&tab=tax') : admin_url('admin.php?page=wc-settings&tab=tax');

// 		echo '<h3>'.__('Tax settings', 'wc_eu_vat_compliance').'</h3><p><a href="'.$tax_settings_link.'">'.__('Find these in the "Tax" section of the WooCommerce settings.', 'wc_eu_vat_compliance').'</a></p>';

		$register_actions = array('woocommerce_admin_field_euvat_tax_options_section', 'woocommerce_settings_euvat_tax_options_after', 'woocommerce_settings_euvat_vat_options_after');
		foreach ($register_actions as $action) {
			add_action($action, array($this, $action));
		}

// __('Find these in the "Tax" section of the WooCommerce settings.', 'wc_eu_vat_compliance')

		$vat_settings = $this->get_settings_vat();
		$tax_settings = $this->get_settings_tax();

		wp_enqueue_script('jquery-ui-accordion');

		echo '<div style="width:960px; margin-bottom: 8px;" id="wceuvat_settings_accordion">';

		// Needed for 2.0 (not for 2.2)
		if (!function_exists('woocommerce_admin_fields')) {
			$this->compliance->wc->admin_includes();
			if (!function_exists('woocommerce_admin_fields')) include_once(  $this->compliance->wc->plugin_path().'/admin/woocommerce-admin-settings.php' );
		}

		// VAT settings
		woocommerce_admin_fields($vat_settings);

		// Currency conversion
		echo '<h3>'.__('VAT reporting currency', 'wc_eu_vat_compliance').'</h3><div>';
		$this->currency_conversion_section();
		echo '</div>';

		// Other WC tax settings
		woocommerce_admin_fields($tax_settings);

		// Tax tables
		echo '<h3>'.__('Tax tables (set up tax rates for each country)', 'wc_eu_vat_compliance').'</h3>';

		echo '<div><h4>'.__('Standard-rate tax table', 'wc_eu_vat_compliance').'</h4><p><a href="'.$tax_settings_link.'&section=standard">'.__('Follow this link.', 'wc_eu_vat_compliance').'</a></p>';

		echo '<h4>'.__('Reduced-rate tax table', 'wc_eu_vat_compliance').'</h4><p><a href="'.$tax_settings_link.'&section=reduced-rate">'.__('Follow this link.', 'wc_eu_vat_compliance').'</a></p>';

		echo '</div>';

/*
TODO
GeoIP is not really a setting. We need a separate panel for checking that everything is working.

		echo '<h3>'.__('GeoIP configuration', 'wc_eu_vat_compliance').'</h3><div>';

		// TODO: Check status
		if (function_exists('geoip_detect_get_info_from_ip') && function_exists('geoip_detect_get_database_upload_filename')) {
			$geoip_settings_link = admin_url('tools.php?page=geoip-detect/geoip-detect.php');
			echo '<h3>'.__('GeoIP location - database state and testing', 'wc_eu_vat_compliance').'</h3><p><a href="'.$geoip_settings_link.'">'.__('Follow this link.', 'wc_eu_vat_compliance').'</a></p>';
		}


		echo '</div>';*/

		echo '</div>';

		$nonce = wp_create_nonce("wc_eu_vat_nonce");

		echo '<button style="margin-left: 4px;" id="wc_euvat_cc_settings_save" class="button button-primary">'.__('Save Settings', 'wc_eu_vat_compliance').'</button>
		<script>

			var wceuvat_query_leaving = false;

			window.onbeforeunload = function(e) {
				if (wceuvat_query_leaving) {
					var ask = "'.esc_js('You have unsaved settings.', 'wc_eu_vat_compliance').'";
					e.returnValue = ask;
					return ask;
				}
			}

			jQuery(document).ready(function($) {
				$("#wceuvat_settings_accordion").accordion({collapsible: true, active: false, animate: 100, heightStyle: "content" });
				$("#wceuvat_settings_accordion input, #wceuvat_settings_accordion textarea, #wceuvat_settings_accordion select").change(function() {
					wceuvat_query_leaving = true;
				});
				$("#wc_euvat_cc_settings_save").click(function() {
					$.blockUI({ message: "<h1>'.__('Saving...', 'wc_eu_vat_compliance').'</h1>" });


					// https://stackoverflow.com/questions/10147149/how-can-i-override-jquerys-serialize-to-include-unchecked-checkboxes
					var formData = $("#wceuvat_settings_accordion input, #wceuvat_settings_accordion textarea, #wceuvat_settings_accordion select").serialize();

					// include unchecked checkboxes. use filter to only include unchecked boxes.
					$.each($("#wceuvat_settings_accordion input[type=checkbox]")
					.filter(function(idx){
						return $(this).prop("checked") === false
					}),
					function(idx, el){
						// attach matched element names to the formData with a chosen value.
						var emptyVal = "0";
						formData += "&" + $(el).attr("name") + "=" + emptyVal;
					}
					);

					$.post(ajaxurl, {
						action: "wc_eu_vat_cc",
						subaction: "savesettings",
						settings: formData,
						_wpnonce: "'.$nonce.'"
					}, function(response) {
						try {
							resp = $.parseJSON(response);
							if (resp.result == "ok") {
// 								alert("'.esc_js(__('Settings Saved.', 'wc_eu_vat_compliance')).'");
								wceuvat_query_leaving = false;
							} else {
								alert("'.esc_js(__('Response:', 'wc_eu_vat_compliance')).' "+resp.result);
							}
						} catch(err) {
							alert("'.esc_js(__('Response:', 'wc_eu_vat_compliance')).' "+response);
							console.log(response);
							console.log(err);
						}
						$.unblockUI();
					});
				});
			});
		</script>
		<style type="text/css">
			#wceuvat_settings_accordion .ui-accordion-content, #wceuvat_settings_accordion .ui-widget-content, #wceuvat_settings_accordion h3 { background: transparent !important; }
			.ui-widget {font-family: inherit !important; }
		</style>';

		echo '';
// 		echo "</p>";

	}

	private function get_settings() {

		$base_currency = get_option('woocommerce_currency');
		$base_currency_symbol = get_woocommerce_currency_symbol($base_currency);

		$currency_code_options = get_woocommerce_currencies();

		$currency_label = $base_currency;
		if (isset($currency_code_options[$base_currency])) $currency_label = $currency_code_options[$base_currency]." ($base_currency)";

		foreach ( $currency_code_options as $code => $name ) {
			$currency_code_options[ $code ] = $name;
			$symbol = get_woocommerce_currency_symbol( $code );
			if ($symbol) $currency_code_options[$code] .= ' (' . get_woocommerce_currency_symbol( $code ) . ')';
		}

		$exchange_rate_providers = WooCommerce_EU_VAT_Compliance()->get_rate_providers();

		$exchange_rate_options = array();
		foreach ($exchange_rate_providers as $key => $provider) {
			$info = $provider->info();
			$exchange_rate_options[$key] = $info['title'];
		}

		return apply_filters('wc_euvat_compliance_exchange_settings', array(
			array(
				'title'    => __( 'Currency', 'woocommerce' ),
				'desc'     => __( "When an order is made, exchange rate information will be added to the order, allowing all amounts to be converted into the currency chosen here. This is necessary if orders may be made in a different currency than the currency you are required to report VAT in.", 'wc_eu_vat_compliance' ),
				'id'       => 'woocommerce_eu_vat_compliance_vat_recording_currency',
				'css'      => 'min-width:350px;',
				'default'  => $base_currency,
				'type'     => 'select',
				'class'    => 'chosen_select',
				'desc_tip' =>  true,
				'options'  => $currency_code_options
			),

			array(
				'title'    => __( 'Exchange rate provider', 'wc_eu_vat_compliance' ),
				'id'       => 'woocommerce_eu_vat_compliance_exchange_rate_provider',
				'css'      => 'min-width:350px;',
// 				'default'  => $base_currency,
				'type'     => 'select',
				'class'    => 'chosen_select',
				'desc_tip' =>  true,
				'options'  => $exchange_rate_options
			),
		));
	}

	public function currency_conversion_section() {

		$base_currency = get_option('woocommerce_currency');
		$base_currency_symbol = get_woocommerce_currency_symbol($base_currency);
		$currency_code_options = get_woocommerce_currencies();
		$currency_label = $base_currency;
		if (isset($currency_code_options[$base_currency])) $currency_label = $currency_code_options[$base_currency]." ($base_currency)";

		echo '<p>'.sprintf(__('Set the currency that you have to use when making VAT reports. If this is not the same as your base currency (%s), then when orders are placed, the exchange rate will be recorded as part of the order information, allowing accurate VAT reports to be made.', 'wc_eu_vat_compliance'), $currency_label).' '.__('If using a currency other than your base currency, then you must configure an exchange rate provider.', 'wc_eu_vat_compliance').'</p>';

		echo '<p>'.__('N.B. If you have a need for a specific provider, then please let us know.', 'wc_eu_vat_compliance').'</p>';

		echo '<table class="form-table">'. "\n\n";

		$currency_settings = $this->get_settings();

		woocommerce_admin_fields($currency_settings);

		echo '</table>';

		$exchange_rate_providers = WooCommerce_EU_VAT_Compliance()->get_rate_providers();

		foreach ($exchange_rate_providers as $key => $provider) {
			$settings = method_exists($provider, 'settings_fields') ? $provider->settings_fields() : false;
			if (!is_string($settings) && !is_array($settings)) continue;
			$info = $provider->info();
			echo '<div id="wceuvat-rate-provider_container_'.$key.'" class="wceuvat-rate-provider_container wceuvat-rate-provider_container_'.$key.'">';
			echo '<h4 style="padding-bottom:0px; margin-bottom:0px;">'.__('Configure exchange rate provider', 'wc_eu_vat_compliance').': '.htmlspecialchars($info['title']).'</h4>';
			echo '<p style="padding-top:0px; margin-top:0px;">'.htmlspecialchars($info['description']);
			if (!empty($info['url'])) echo ' <a href="'.$info['url'].'">'.__('Follow this link for more information.', 'wc_eu_vat_compliance').'</a>';
			echo '</p>';
			echo '<table class="form-table" style="">'. "\n\n";
			if (is_string($settings)) {
				echo "<tr><td>$settings</td></tr>";
			} elseif (is_array($settings)) {
				woocommerce_admin_fields($settings);
			}
			echo '</table>';
			echo "<div id=\"wc_eu_vat_test_provider_$key\"></div><button id=\"wc_eu_vat_test_provider_button_$key\" onclick=\"test_provider('".$key."')\" class=\"button wc_eu_vat_test_provider_button\">".__('Test Provider', 'wc_eu_vat_compliance')."</button>";
			echo '</div>';
		}

	}

	public function render_tab_readiness() {
		echo '<h2>'.__('EU VAT Compliance Readiness', 'wc_eu_vat_compliance').'</h2>';

		echo '<div style="width:960px;">';

		echo '<p><em>'.__('N.B. Items listed below are listed as suggestions only, and it is not claimed that all apply to every situation. Items listed do not constitute legal or financial advice. For all decisions on which settings are right for you in your location and setup, final responsibility is yours.', 'wc_eu_vat_compliance').'</em></p>';

		// 1420070400
		if (time() < strtotime('1 Jan 2015 00:00:00 GMT')) {
			echo '<p><strong><em>'.__('N.B. It is not yet the 1st of January 2015; so, you may not want to act on all the items mentioned below yet.', 'wc_eu_vat_compliance').'</em></strong></p>';
		}

		// TODO
		echo '<p>'.__('Please come back here after your next plugin update, to see what has been added - this feature is still under development. When done, it will advise you on which of your WooCommerce and other settings may need adjusting for EU VAT compliance.', 'wc_eu_vat_compliance').'</p>';

		if (!class_exists('WC_EU_VAT_Compliance_Readiness_Tests')) require_once(WC_EU_VAT_COMPLIANCE_DIR.'/readiness-tests.php');
		$test = new WC_EU_VAT_Compliance_Readiness_Tests();
		$results = $test->get_results();

		$result_descriptions = $test->result_descriptions();

		?>
		<table>
		<thead>
			<tr>
				<th style="text-align:left;"><?php _e('Test', 'wc_eu_vat_compliance');?></th>
				<th style="text-align:left; min-width:60px;"><?php _e('Result', 'wc_eu_vat_compliance');?></th>
				<th style="text-align:left;"><?php _e('Futher information', 'wc_eu_vat_compliance');?></th>
			</tr>
		</thead>
		<tbody>
		<?php

		foreach ($results as $id => $res) {
			if (!is_array($res)) continue;
			// result, label, info
			$row_bg = ($res['result'] == 'fail') ? 'color:red;' : ($res['result'] == 'warning' ? 'color:orange' : 'color:green;');
			?>
			<tr style="<?php echo $row_bg;?>">
				<td><?php echo $res['label'];?></td>
				<td><?php echo $result_descriptions[$res['result']];?></td>
				<td><?php echo $res['info'];?></td>
			</tr>
			<?php
		}

		?>
		</tbody>
		</table>
		<?php

		// TODO: Links to the other stuff

		echo '</div>';

	}

	public function admin_footer() {
		$text = esc_attr(__('N.B. The final country used may be modified according to your EU VAT settings.', 'wc_eu_vat_compliance'));
		$testing = esc_js(__('Testing...', 'wc_eu_vat_compliance'));
		$test = esc_js(__('Test Provider', 'wc_eu_vat_compliance'));
		$nonce = wp_create_nonce("wc_eu_vat_nonce");
		$response = esc_js(__('Response:', 'wc_eu_vat_compliance'));
		echo <<<ENDHERE
		<script>
			function test_provider(key) {
				jQuery('#wc_eu_vat_test_provider_button_'+key).html('$testing');
				jQuery.post(ajaxurl, {
					action: "wc_eu_vat_cc",
					subaction: "testprovider",
					tocurrency: jQuery('#woocommerce_eu_vat_compliance_vat_recording_currency').val(),
					key: key,
					_wpnonce: "$nonce"
				}, function(response) {
					try {
						resp = jQuery.parseJSON(response);
						jQuery('#wc_eu_vat_test_provider_'+key).html('<p>'+resp.response+'</p>');
					} catch(err) {
						alert('$response '+response);
						console.log(response);
						console.log(err);
					}
				});
				jQuery('#wc_eu_vat_test_provider_button_'+key).html('$test');
			}
			jQuery(document).ready(function($) {
				function show_correct_provider() {
					var provider = $('#woocommerce_eu_vat_compliance_exchange_rate_provider').val();
					$('.wceuvat-rate-provider_container').hide();
					$('#wceuvat-rate-provider_container_'+provider).show();
				}
				show_correct_provider();
				$('#woocommerce_eu_vat_compliance_exchange_rate_provider').change(function() {
					show_correct_provider();
				});
				$('#woocommerce_tax_based_on').after('<br><em>$text</em>');
				$('#wceuvat_tabs a.nav-tab').click(function() {
					$('#wceuvat_tabs a.nav-tab').removeClass('nav-tab-active');
					$(this).addClass('nav-tab-active');
					var id = $(this).attr('id');
					if ('wceuvat-navtab-' == id.substring(0, 15)) {
						$('div.wceuvat-navtab-content').hide();
						$('#wceuvat-navtab-'+id.substring(15)+'-content').show();
					}
					return false;
				});
			});
		</script>
ENDHERE;
	}

}
