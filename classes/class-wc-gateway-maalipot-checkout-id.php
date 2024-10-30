<?php
/**
 * Class WC_Gateway_Maalipot_Id file.
 *
 * @package WooCommerce\Gateways
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds and manages MaaliPay's MaaliPot ID to WooCommerce checkout page.
 */
class WC_Gateway_Maalipot_Id {

	/**
	 * Pointer to gateway making the request.
	 *
	 * @var WC_Gateway_Maalipay
	 */
	protected $gateway;

	/**
	 * Constructor.
	 *
	 * @param WC_Gateway_Maalipay $gateway MaaliPay gateway object.
	 */
	public function __construct( $gateway ) {
		$this->gateway    = $gateway;
		// $this->notify_url = WC()->api_request_url( 'WC_Gateway_Maalipay' );
		// activate the MaaliPot ID field
		add_filter('woocommerce_before_order_notes', array($this, 'add_maalipot_id_field'), 10, 1);
		// validate the form field
		add_action('woocommerce_checkout_process', array($this, 'validate_maalipot_id_field'));
		// add the field to order details in the database
		add_action('woocommerce_checkout_update_order_meta', array($this, 'save_maalipot_id_field'), 10, 1);
		// add field details to order details in the admin section
		add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'show_admin_maalipot_id_field_order'), 10, 1);
		// add field details to the customer's email notification
		add_action('woocommerce_email_after_order_table', array($this, 'show_admin_maalipot_id_field_email'), 20, 4);
		// hide maalipot field on the checkout page
		add_action( 'woocommerce_after_checkout_form', array($this, 'hide_maalipot_id_field'), 9999 );
	}

	/**
	 * Create the MaaliPot ID field
	 */
	public function add_maalipot_id_field($checkout) {
		// add the field
		woocommerce_form_field(
			'maalipot_id',
			array(
				'type' 			=> 'number',
				'class' 		=> array('form-row', 'form-row-wide'),
				'label' 		=> __('', 'maalicard'),
				'placeholder' 	=> $this->gateway->get_option('default_maalipot_id'),
				'required' 		=> true,
				'readonly' 		=>'readonly',
				// 'default' 		=> $saved_maalipot_id
				'default' 		=> $this->gateway->get_option('default_maalipot_id')
			),
			// set field value to the current shop's maalipot id
			// if the shop has no maalipot id, then set the value to the default id in WooCommerce settings
			$this->gateway->get_option('default_maalipot_id')
		);

	}

	public function validate_maalipot_id_field() {
		if(!$_POST['maalipot_id'] || intval($_POST['maalipot_id']) < 1) {
			wc_add_notice(__('<strong>MaaliPot ID</strong> is a required field', 'maalicard'), 'error');
		}
	}

	public function save_maalipot_id_field($order_id) {
		// $order_id = $order->get_id();
		if($_POST['maalipot_id'] && intval($_POST['maalipot_id']) > 0) {
			// update_post_meta($order_id, '_maalipot_id', esc_attr($_POST['maalipot_id']));
			$maalipot_id = sanitize_text_field( trim( $_POST['maalipot_id'] ) );
			update_post_meta( $order_id, '_maalipot_id', $maalipot_id );
		}
	}

	public function show_admin_maalipot_id_field_order($order) {
		$order_id = $order->get_id();
		if(get_post_meta($order_id, '_maalipot_id', true)) {
			echo '<p><strong>'. __('MaaliPot ID:', 'maalicard').'</strong><br />'.get_post_meta( esc_html__( $order_id, 'maalipot' ), '_maalipot_id', true).'</p>';
		}
	}

	public function show_admin_maalipot_id_field_email($order, $sent_to_admin, $plain_text, $email) {
		$order_id = $order->get_id();
		if(get_post_meta($order_id, '_maalipot_id', true)) {
			echo '<p><strong>'. __('MaaliPot ID:', 'maalicard').'</strong> '.get_post_meta( esc_html__( $order_id, 'maalipot' ), '_maalipot_id', true).'</p>';
		}
	}

	public function hide_maalipot_id_field() {

		wc_enqueue_js( "
	  		// jQuery('#maalipot_id').fadeOut();
	  		jQuery('#maalipot_id').hide();
		");

	}
}
