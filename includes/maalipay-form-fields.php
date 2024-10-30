<?php
/**
 * Settings for MaaliPay Gateway.
 *
 * @package WooCommerce/Classes/Payment
 */

defined( 'ABSPATH' ) || exit;
/**
 * Initialise Gateway Settings Form Fields.
 */
function maalipay_form_fields() {
	return array(
	    'enabled' => array(
	        'title' => __( 'Enable/Disable', 'maalipot'),
	        'type' => 'checkbox',
	        'label' => __( 'Enable or Disable MaaliPay Payment Gateway', 'maalipot'),
	        'default' => 'no'
	    ),
	    'title' => array(
	        'title' => __( 'Title', 'maalipot'),
	        'type' => 'text',
	        'default' => __( 'MaaliPay', 'maalipot'),
	        'desc_tip' => true,
	        'description' => __( 'The title customers will see while on the checkout page.', 'maalipot')
	    ),
	    'description' => array(
	        'title' => __( 'Description', 'maalipot'),
	        'type' => 'textarea',
	        'default' => __( 'Please remit your payment to the shop to allow for the delivery to be made', 'maalipot'),
	        'desc_tip' => true,
	        'description' => __( 'The description customers will see while at the checkout page.', 'maalipot')
	    ),
	    'instructions' => array(
	        'title' => __( 'Instructions', 'maalipot'),
	        'type' => 'textarea',
	        'default' => __( 'Default instructions', 'maalipot'),
	        'desc_tip' => true,
	        'description' => __( 'Instructions for the thank you page and order email', 'maalipot')
	    ),
	    // 'enable_for_virtual' => array(
	    //     'title'   => __( 'Accept for virtual orders', 'maalipot' ),
	    //     'label'   => __( 'Accept COD if the order is virtual', 'maalipot' ),
	    //     'type'    => 'checkbox',
	    //     'default' => 'yes',
	    // ),
	    'email'                 => array(
			'title'       => __( 'MaaliPay email', 'maalipot' ),
			'type'        => 'email',
			'description' => __( 'Please enter your MaaliPay email address; this is needed in order to take payment.', 'maalipot' ),
			'default'     => get_option( 'admin_email' ),
			'desc_tip'    => true,
			'placeholder' => 'you@youremail.com',
		),
		'advanced'              => array(
			'title'       => __( 'Advanced Options', 'maalipot' ),
			'type'        => 'title',
			'description' => '',
		),
		'testmode'              => array(
			'title'       => __( 'MaaliPay sandbox', 'maalipot' ),
			'type'        => 'checkbox',
			'label'       => __( 'Enable MaaliPay sandbox', 'maalipot' ),
			'default'     => 'no',
			/* translators: %s: URL */
			'description' => sprintf( __( 'MaaliPay sandbox can be used to test payments. Sign up for an <a href="%s">account</a>.', 'maalipot' ), 'https://maalicard.com/maalipay' ),
		),
		'debug'                 => array(
			'title'       => __( 'Debug log', 'maalipot' ),
			'type'        => 'checkbox',
			'label'       => __( 'Enable logging', 'maalipot' ),
			'default'     => 'no',
			/* translators: %s: URL */
			'description' => sprintf( __( 'Log MaaliPay events, such as IPN requests, inside %s Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished.', 'maalipot' ), '<code>' . WC_Log_Handler_File::get_log_file_path( 'maalipay' ) . '</code>' ),
		),
		'api_details'           => array(
			'title'       => __( 'API Credentials', 'maalipot' ),
			'type'        => 'title',
			/* translators: %s: URL */
			'description' => sprintf( __( 'Enter your MaaliPay API credentials to process refunds via MaaliPay. Learn how to access your <a href="%s" target="_blank">MaaliPay API Credentials</a>.', 'maalipot' ), 'https://maalipay.maalicard.com' ),
		),
		'default_maalipot_id'          => array(
			'title'       => __( 'Default MaaliPot ID', 'maalipot' ),
			'type'        => 'number',
			'description' => __( 'Use this MaaliPot ID as a fallback for shops without an ID.', 'maalipot' ),
			'default'     => '',
			'desc_tip'    => true,
			'placeholder' => __( 'Required', 'maalipot' ),
		),
		'api_username'          => array(
			'title'       => __( 'Live API username', 'maalipot' ),
			'type'        => 'text',
			'description' => __( 'Get your API credentials from MaaliPay.', 'maalipot' ),
			'default'     => '',
			'desc_tip'    => true,
			'placeholder' => __( 'Optional', 'maalipot' ),
		),
		'api_password'          => array(
			'title'       => __( 'Live API password', 'maalipot' ),
			'type'        => 'password',
			'description' => __( 'Get your API credentials from MaaliPay.', 'maalipot' ),
			'default'     => '',
			'desc_tip'    => true,
			'placeholder' => __( 'Optional', 'maalipot' ),
		),
		'api_signature'         => array(
			'title'       => __( 'Live API signature', 'maalipot' ),
			'type'        => 'password',
			'description' => __( 'Get your API credentials from MaaliPay.', 'maalipot' ),
			'default'     => '',
			'desc_tip'    => true,
			'placeholder' => __( 'Optional', 'maalipot' ),
		),
		'sandbox_api_username'  => array(
			'title'       => __( 'Sandbox API username', 'maalipot' ),
			'type'        => 'text',
			'description' => __( 'Get your API credentials from MaaliPay.', 'maalipot' ),
			'default'     => '',
			'desc_tip'    => true,
			'placeholder' => __( 'Optional', 'maalipot' ),
		),
		'sandbox_api_password'  => array(
			'title'       => __( 'Sandbox API password', 'maalipot' ),
			'type'        => 'password',
			'description' => __( 'Get your API credentials from MaaliPay.', 'maalipot' ),
			'default'     => '',
			'desc_tip'    => true,
			'placeholder' => __( 'Optional', 'maalipot' ),
		),
		'sandbox_api_signature' => array(
			'title'       => __( 'Sandbox API signature', 'maalipot' ),
			'type'        => 'password',
			'description' => __( 'Get your API credentials from MaaliPay.', 'maalipot' ),
			'default'     => '',
			'desc_tip'    => true,
			'placeholder' => __( 'Optional', 'maalipot' ),
		),
		'extra_fields' => array(
			'title'       => __( 'Extra Options', 'maalipot' ),
			'type'        => 'title',
			'description' => '',
		),
		'order_status' => array(
			'title' => __( 'Default order status', 'maalipot' ),
			'type' => 'select',
			'options' => wc_get_order_statuses(),
			'default' => 'wc-pending',
			'description' 	=> __( 'The default order status if this gateway is used in payment, after checkout.', 'maalipot' ),
		),
	);
}