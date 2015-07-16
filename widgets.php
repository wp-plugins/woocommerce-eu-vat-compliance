<?php

if (!defined('WC_EU_VAT_COMPLIANCE_DIR')) die('No direct access');

/*
Function: provides widget (also used by shortcode)

Provide a widget and shortcode to allow this to be over-ridden by the user (since GeoIP is not infallible)

[euvat_country_selector include_notaxes="true|false"]

The dropdown requires WC 2.2.9 or later to work.
*/

if (class_exists('WC_EU_VAT_Compliance_Preselect_Country')) return;

if (!class_exists('WP_Widget')) require ABSPATH . WPINC . '/widgets.php';

class WC_EU_VAT_Country_PreSelect_Widget extends WP_Widget {

	public function __construct() {
		$widget_ops = array('classname' => 'country_preselect', 'description' => __('Allow the visitor to set their taxation country (to show correct taxes)', 'wc_eu_vat_compliance') );
		
		parent::__construct('WC_EU_VAT_Country_PreSelect_Widget', __('WooCommerce Tax Country Chooser', 'wc_eu_vat_compliance'), $widget_ops); 
	}

	public function widget( $args, $instance ) {
		extract($args);

		echo $before_widget;
		$title = empty($instance['title']) ? ' ' : apply_filters('widget_title', $instance['title']);
		
		if (!empty($title))
			echo $before_title . htmlspecialchars($title) . $after_title;;
		
		if (!empty($instance['explanation'])) echo '<div class="countrypreselect_explanation">'.$instance['explanation'].'</div>';

		$include_notaxes = !empty($instance['include_notaxes']) ? $instance['include_notaxes'] : false;

		$preselect = WooCommerce_EU_VAT_Compliance('WC_EU_VAT_Compliance_Preselect_Country');
		$preselect->render_dropdown($include_notaxes);

		echo $after_widget;
	}

	// Back-end options
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '' ) );
		$title = empty($instance['title']) ? '' : $instance['title'];
		$explanation = empty($instance['explanation']) ? '' : $instance['explanation'];
		$include_notaxes = empty($instance['include_notaxes']) ? false : $instance['include_notaxes'];

		if (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.2.9', '<')) {
			echo '<p style="color: red">'.sprintf(__('Due to limitations in earlier versions, this widget requires WooCommerce %s or later, and will not work on your version (%s).', 'wc_eu_vat_compliance'), '2.2.9', WOOCOMMERCE_VERSION).'</p>';
		}

		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'wc_eu_vat_compliance');?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></label></p>

		<p><label for="<?php echo $this->get_field_id('explanation'); ?>"><?php _e('Explanatory text (HTML accepted):', 'wc_eu_vat_compliance');?> <textarea class="widefat" id="<?php echo $this->get_field_id('explanation'); ?>" name="<?php echo $this->get_field_name('explanation'); ?>"><?php echo htmlentities($explanation); ?></textarea> </label></p>

		<p><input id="<?php echo $this->get_field_id('include_notaxes_nooption'); ?>" name="<?php echo $this->get_field_name('include_notaxes'); ?>" type="radio" value="0" <?php if ($include_notaxes == 0) echo ' checked="checked"';?>/><label for="<?php echo $this->get_field_id('include_notaxes_nooption'); ?>"><?php echo htmlspecialchars(__('Do not include a menu option for the customer to show prices with no VAT.', 'wc_eu_vat_compliance'));?> </label></p>

		<p><input id="<?php echo $this->get_field_id('include_notaxes_withmenu'); ?>" name="<?php echo $this->get_field_name('include_notaxes'); ?>" type="radio" value="1" <?php if ($include_notaxes == 1) echo ' checked="checked"';?>/><label for="<?php echo $this->get_field_id('include_notaxes_withmenu'); ?>"><?php echo htmlspecialchars(__('Include menu option for the customer to show prices with no VAT.', 'wc_eu_vat_compliance'));?> </label></p>

		<p><input id="<?php echo $this->get_field_id('include_notaxes_withcheckbox'); ?>" name="<?php echo $this->get_field_name('include_notaxes'); ?>" type="radio" value="2" <?php if ($include_notaxes == 2) echo ' checked="checked"';?>/><label for="<?php echo $this->get_field_id('include_notaxes_withcheckbox'); ?>"><?php echo htmlspecialchars(__('Include menu option and separate checkbox for the customer to show prices with no VAT.', 'wc_eu_vat_compliance'));?> </label></p>

		<?php

	}

	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = $new_instance['title'];
		$instance['explanation'] = $new_instance['explanation'];
		$instance['include_notaxes'] = (!empty($new_instance['include_notaxes'])) ? $new_instance['include_notaxes'] : false;
		return $instance;
	}

}
