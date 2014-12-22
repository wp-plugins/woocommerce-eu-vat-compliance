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

// TODO: Test on WC 2.0
// TODO: Link to documentation, when written

class WC_EU_VAT_Control_Centre {

	public function __construct() {
		add_action('admin_menu', array($this, 'admin_menu'));
// 		add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
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
			'reports' => __('Reports', 'wc_eu_vat_compliance'),
			'premium' => __('Premium', 'wc_eu_vat_compliance')
		));

		// TODO: Return to general
		$active_tab = !empty($_REQUEST['tab']) ? $_REQUEST['tab'] : 'settings';

		$compliance = WooCommerce_EU_VAT_Compliance();

		$version = $compliance->get_version();
		$premium = false;

		if (!$compliance->is_premium()) {
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
<li><strong>VAT-registered buyers can be exempted, and their numbers validated:</strong> a VAT number can be entered at the check-out, and it will be validated (via VIES). Qualifying customers can then be exempted from VAT on their purchase, and their information recorded. This feature is backwards-compatible with the old official WooCommerce "EU VAT Number" extension, so you will no longer need that plugin, and its data will be maintained.</li>

<!-- <li><strong>Forbid EU sales (feature not yet released)</strong> - for shop owners for whom EU VAT compliance is too burdensome, this feature will allow you to forbid EU customers who would be liable to VAT (i.e. those without a VAT number) to purchase.</li> -->

<li><strong>Non-contradictory evidences:</strong> require two non-contradictory evidences of location (if the customer address and GeoIP lookup contradict, then the customer will be asked to self-certify his location, by choosing between them).</li>

<li><strong>CSV download:</strong> A CSV containing all orders with EU VAT data can be downloaded (including full compliance information).</li>

<li><strong>Multi-currency compatible:</strong> if you are using the <a href="http://aelia.co/shop/currency-switcher-woocommerce/">"WooCommerce currency switcher"</a> plugin to sell in multiple currencies, then this plugin will maintain and provide its data for each order in both your shop's base currency and the order currency (if it differs).</li>
</ul>
<?php

	}

	private function render_tab_general() {
		echo '<h2>'.__('Current status', 'wc_eu_vat_compliance').'</h2>';
		// 1420070400
		if (time() < strtotime('1 Jan 2015 00:00:00 GMT')) {
			echo '<p><strong><em>'.__('N.B. It is not yet the 1st of January 2015; so, you may not want to act on all the items mentioned below yet.', 'wc_eu_vat_compliance').'</em></strong></p>';
		}

// 		if (!class_exists('WC_EU_VAT_Compliance_Readiness_Tests')) require_once(WC_EU_VAT_COMPLIANCE_DIR.'/readiness-tests.php');
// 		$test = new WC_EU_VAT_Compliance_Readiness_Tests();
// 		$results = $test->get_results();

// 		foreach ($results as $res) {
// 		}

		// TODO: Links to the other stuff
	}

	

	private function render_tab_settings() {
		echo '<h2>'.__('Settings', 'wc_eu_vat_compliance').'</h2>';

		echo '<p><em>'.__('This control panel is under rapid development. Please make sure you keep this plugin up-to-date with any available updates that WordPress tells you are available.', 'wc_eu_vat_compliance').'</em></p>';

		$tax_settings_link = (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.1', '<')) ? admin_url('admin.php?page=woocommerce_settings&tab=tax') : admin_url('admin.php?page=wc-settings&tab=tax');

		echo '<h3>'.__('Tax settings', 'wc_eu_vat_compliance').'</h3><p><a href="'.$tax_settings_link.'">'.__('Find these in the "Tax" section of the WooCommerce settings.', 'wc_eu_vat_compliance').'</a></p>';

		// TODO: Check status
		if (function_exists('geoip_detect_get_info_from_ip') && function_exists('geoip_detect_get_database_upload_filename')) {
			$geoip_settings_link = admin_url('tools.php?page=geoip-detect/geoip-detect.php');
			echo '<h3>'.__('GeoIP location - database state and testing', 'wc_eu_vat_compliance').'</h3><p><a href="'.$geoip_settings_link.'">'.__('Follow this link.', 'wc_eu_vat_compliance').'</a></p>';
		}


		echo '<h3>'.__('Set up standard-rate tax tables', 'wc_eu_vat_compliance').'</h3><p><a href="'.$tax_settings_link.'&section=standard">'.__('Follow this link.', 'wc_eu_vat_compliance').'</a></p>';

		echo '<h3>'.__('Set up reduced-rate tax tables', 'wc_eu_vat_compliance').'</h3><p><a href="'.$tax_settings_link.'&section=reduced-rate">'.__('Follow this link.', 'wc_eu_vat_compliance').'</a></p>';

	}

	public function admin_footer() {
		echo <<<ENDHERE
		<script>
			jQuery(document).ready(function() {
				jQuery('#wceuvat_tabs a.nav-tab').click(function() {
					jQuery('#wceuvat_tabs a.nav-tab').removeClass('nav-tab-active');
					jQuery(this).addClass('nav-tab-active');
					var id = jQuery(this).attr('id');
					if ('wceuvat-navtab-' == id.substring(0, 15)) {
						jQuery('div.wceuvat-navtab-content').hide();
						jQuery('#wceuvat-navtab-'+id.substring(15)+'-content').show();
					}
					return false;
				});
			});
		</script>
ENDHERE;
	}

}
