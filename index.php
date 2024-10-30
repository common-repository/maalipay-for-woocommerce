<?php
/**
 * Plugin Name: MaaliPay for WooCommerce
 * Plugin URI: http://maalicard.com/maalipay
 * Description: Provides a Maalicard WooCommerce Payment Gateway.
 * Version: 1.0.5
 * Author: Maalicard
 * Author URI: https://maalicard.com/
 * Text Domain: maalipot
 * Domain Path: /i18n/languages/
 * License: 1.0.0
 * License URL: https://www.gnu.org/licenses/gpl-2.0.txt
 * Requires PHP: 5.6
 * Requires at least: 5.4.0
 * WC requires at least: 4.5
 * WC tested up to: 5.4.1
 *
 * @package WooCommerce\Gateways\Payment
 *
 * Copyright (c) 2021 MaaliCard
 */

defined( 'ABSPATH' ) || exit;

use Automattic\Jetpack\Constants;

// first check if WooCommerce is active
if(!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;

// hook the plugin function to WP plugins once activated
add_action('plugins_loaded', 'maalipay_payment_init', 10);

/**
 * Initialize the Maalicard WooCommerce Payment Gateway.
 *
 * Provides Maalicard Payment options for buyers and integrates with WooCommerce payment options.
 *
 * @return      Mixed
 * @version     1.0.0
 * @package     WooCommerce\Gateways\Payment
 */
function maalipay_payment_init() {
	/**
	 * Check whether WooCommerce Payment class exist
	 */
	if(class_exists('WC_Payment_Gateway')) {
		/**
		 * Maalicard WooCommerce Payment Gateway
		 *
		 * Provides a Maalicard WooCommerce Payment Gateway, with options for clients.
		 *
		 * @class       WC_Gateway_Maalipay
		 * @extends     WC_Payment_Gateway
		 * @version     1.0.0
		 * @package     WooCommerce/Classes/Payment
		 */
		class WC_Gateway_Maalipay extends WC_Payment_Gateway
		{
		    /**
		     * Whether or not logging is enabled
		     *
		     * @var bool
		     */
		    public static $log_enabled = false;

		    /**
		     * Logger instance
		     *
		     * @var WC_Logger
		     */
		    public static $log = false;
		    /**
		     * Default order status for all transactions
		     *
		     * @var string
		     */
		    private $order_status;

		    /**
		     * Constructor for the gateway.
		     */
		    public function __construct() {
		        // Setup general properties.
		        $this->setup_properties();
		        // load form fields for gateway settings page in the admin section
		        $this->init_form_fields();
		        // Load the settings from WC_Settings_API
		        $this->init_settings();
		        // Define admin settings variables.
		        $this->title        = $this->get_option( 'title' );
		        $this->description  = $this->get_option( 'description' );
		        $this->instructions = $this->get_option( 'instructions', $this->description );
		        // added for test mode (sandbox)
		        $this->testmode       = 'yes' === $this->get_option( 'testmode', 'no' );
		        $this->debug          = 'yes' === $this->get_option( 'debug', 'no' );
		        $this->email          = $this->get_option( 'email' );
		        $this->receiver_email = $this->get_option( 'receiver_email', $this->email );
		        // $this->identity_token = $this->get_option( 'identity_token' );
				// to use merchant id instead of receiver email
				$this->merchant_id = $this->get_option('default_maalipot_id');
		        $this->order_status   = $this->get_option('order_status');
		        self::$log_enabled    = $this->debug;

		        if($this->testmode) {
		            /* translators: %s: Link to MaaliPay sandbox testing guide page */
		            $this->description .= ' ' . sprintf( __( '<br /><br />SANDBOX ENABLED! You can use sandbox testing accounts only. See the <a href="%s">MaaliPay Sandbox Testing Guide</a> for more details.', 'maalipot' ), 'https://maalipay.maalicard.com/' );
		            $this->description  = trim( $this->description );
		        }

		        // then hook/save this payment id to WC admin options/settings
		        add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
		        // add_action('woocommerce_thank_you_'.$this->id, array($this, 'thank_you_page'));
		        // custom function to process order statuses
		        add_action('woocommerce_order_status_processing', array($this, 'capture_payment'));
		        add_action('woocommerce_order_status_completed', array($this, 'capture_payment'));
		        // add custom scripts for admin settings page
		        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
		        // verify whether the plugin is valid for use
		        if(!$this->is_valid_for_use()) {
		            $this->enabled = 'no';
		        } else {
					// activate the MaaliPot ID field on WooCommerce checkout page
					include_once dirname( __FILE__ ) . '/classes/class-wc-gateway-maalipot-checkout-id.php';
					new WC_Gateway_Maalipot_Id( $this );

		            // load IPN (Instant Payment Notifications) class
		            // and instantiate it, to help in processing payments
		            include_once dirname( __FILE__ ) . '/classes/class-wc-gateway-maalipay-ipn-handler.php';
		            new WC_Gateway_Maalipay_IPN_Handler( $this->testmode, $this->receiver_email );

		        }
		        // if plugin is enabled, then load custom thank you notice
		        if('yes' === $this->enabled) {
		            add_filter('woocommerce_thankyou_order_received_text', array($this, 'order_received_text'), 10, 2);
		        }
		    }
		    /**
		     * Setup general properties for the gateway.
		     */
		    protected function setup_properties() {
		        $this->id   = 'maalicard_payment';
		        // $this->icon = apply_filters('woocommerce_maalicard_icon', plugins_url('/assets/images/maalipay-icon.jpg', __FILE__ ) );
		        $this->order_button_text = __( 'Proceed to MaaliPay', 'maalipot' );
		        $this->method_title = __( 'MaaliPay', 'maalipot');
		        $this->method_description = __( 'MaaliPay Standard payment gateway redirects customers to MaaliPay to process their payment.', 'maalipot');
		        $this->supports = array(
		            'products',
		            // 'refunds',
		        );
		        $this->has_fields = false;
		    }
		    /**
		     * Initialize the gateway form fields in admin settings
		     *
		     * Options that will show in admin on this gateway settings page and make use of the WC Settings API.
		     *
		     */
		    public function init_form_fields() {
		        // include the settings for MaaliPay gateway
		        include_once dirname( __FILE__ ) . '/includes/maalipay-form-fields.php';
		        $this->form_fields = maalipay_form_fields();
		    }
		    /**
		     * Return whether or not this gateway still requires setup to function.
		     *
		     * When this gateway is toggled on via AJAX, if this returns true a
		     * redirect will occur to the settings page instead.
		     *
		     * @since 3.4.0
		     * @return bool
		     */
		    public function needs_setup() {
		        return !is_email($this->email);
		    }
		    /**
		     * Check if this gateway is available in the user's country based on currency.
		     *
		     * @return bool
		     */
		    public function is_valid_for_use() {
		        return in_array(
		            get_woocommerce_currency(),
		            apply_filters(
		                'woocommerce_maalipay_supported_currencies',
		                // array( 'AUD', 'BRL', 'CAD', 'MXN', 'NZD', 'HKD', 'SGD', 'USD', 'EUR', 'JPY', 'TRY', 'NOK', 'CZK', 'DKK', 'HUF', 'ILS', 'MYR', 'PHP', 'PLN', 'SEK', 'CHF', 'TWD', 'THB', 'GBP', 'RMB', 'RUB', 'INR', 'UGX')
		                array( 'UGX' )
		            ),
		            true
		        );
		    }
		    /**
		     * Logging method.
		     *
		     * @param string $message Log message.
		     * @param string $level Optional. Default 'info'. Possible values:
		     *                      emergency | alert | critical | error | warning | notice | info | debug.
		     */
		    public static function log($message, $level = 'info') {
		        if(self::$log_enabled) {
		            if(empty(self::$log)) {
		                self::$log = wc_get_logger();
		            }
		            self::$log->log($level, $message, array('source'=>'maalipay'));
		        }
		    }
		    /**
		     * Admin Panel Options.
		     * - Options for bits like 'title' and availability on a country-by-country basis.
		     *
		     * @since 1.0.0
		     */
		    public function admin_options() {
		        if($this->is_valid_for_use()) {
					?>
					<div id="poststuff">
						<div id="post-body" class="metabox-holder columns-2">
							<div id="post-body-content">
								<?php
					            	parent::admin_options();
								?>
							</div>
							<div id="postbox-container-1" class="postbox-container">
			                    <div id="side-sortables" class="meta-box-sortables ui-sortable">
			                        <div class="postbox ">
			                            <div class="handlediv" title="Click to toggle"><br></div>
			                            <h3 class="hndle"><span><i class="dashicons dashicons-editor-help"></i>&nbsp;&nbsp;Plugin Support</span></h3>
			                            <div class="inside">
			                                <div class="support-widget">
			                                    <p>
			                                    <img style="width: 70%;margin: 0 auto;position: relative;display: inherit;" src="<?php echo plugins_url('/assets/images/maalipay-logo.png', __FILE__ ); ?>">
			                                    <br/>
			                                    Got an issue, a question or idea?</p>
			                                    <ul>
			                                        <li>» <a href="https://maalicard.com/contact" target="_blank">Support Request</a></li>
			                                        <li>» <a href="https://maalicard.com/" target="_blank">Developer's services.</a></li>
			                                        <li>» <a href="https://maalicard.com/" target="_blank">Developer's Portfolio</a></li>
			                                    </ul>

			                                </div>
			                            </div>
			                        </div>
			                        <!--<div class="postbox rss-postbox">
										<div class="handlediv" title="Click to toggle"><br></div>
										<h3 class="hndle"><span><i class="wordpress-icon"></i>&nbsp;&nbsp;MaaliCard Blog</span></h3>
										<div class="inside">
											<div class="rss-widget">
												<?php
													// wp_widget_rss_output(array(
													// 	'url' => 'https://maalicard.com/feed',
													// 	'title' => 'MaaliCard Blog',
													// 	'items' => 3,
													// 	'show_summary' => 0,
													// 	'show_author' => 0,
													// 	'show_date' => 1,
													// ));
												?>
											</div>
										</div>
									</div>-->
			                    </div>
			                </div>
						</div>
					</div>
					<div class="clearfix"></div>
					<?php
		        } else {
		            ?>
		            <div class="inline error">
		                <p>
		                    <strong><?php esc_html_e( 'Gateway disabled', 'maalipot' ); ?></strong>: <?php esc_html_e( 'MaaliPay does not support your default currency.', 'maalipot' ); ?>
		                </p>
		            </div>
		            <?php
		        }
		    }
		    /**
		     * Processes and saves options.
		     * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
		     *
		     * @return bool was anything saved?
		     */
		    public function process_admin_options() {
		        $saved = parent::process_admin_options();

		        // Maybe clear logs.
		        if('yes' !== $this->get_option('debug', 'no')) {
		            if(empty(self::$log)) {
		                self::$log = wc_get_logger();
		            }
		            self::$log->clear('maalipay');
		        }

		        return $saved;
		    }
		    /**
		     * Load admin scripts.
		     *
		     * @since 3.3.0
		     */
		    public function admin_scripts() {
		        $screen    = get_current_screen();
		        $screen_id = $screen ? $screen->id : '';

		        if ( 'woocommerce_page_wc-settings' !== $screen_id ) {
		            return;
		        }

		        $suffix  = Constants::is_true( 'SCRIPT_DEBUG' ) ? '' : '.min';
		        $version = Constants::get_constant( 'WC_VERSION' );

		        wp_enqueue_script( 'woocommerce_maalipay_admin', plugins_url('/assets/scripts/maalipay-admin'.$suffix.'.js', __FILE__), array(), $version, true );
		        // enqueue admin styles
    			wp_enqueue_style( 'woocommerce_maalipay_admin_styles', plugins_url('/assets/styles/maalipay-admin.css', __FILE__), array(), $version);
		    }
		    /**
		     * Get gateway icon.
		     *
		     * @return string
		     */
		    public function get_icon() {
		        // We need a base country for the link to work, bail if in the unlikely event no country is set.
		        $base_country = WC()->countries->get_base_country();
		        if(empty($base_country)) {
		            return '';
		        }
		        $icon_html = '';
		        $icon      = (array)$this->get_icon_image($base_country);

		        foreach($icon as $i) {
		            $icon_html .= '<img src="' . esc_attr($i) . '" alt="' . esc_attr__('MaaliPay acceptance mark', 'maalipot') . '" title="' . esc_attr__('Accepted by MaaliPay', 'maalipot') . '" />&nbsp;';
		        }

		        $icon_html .= sprintf( '<a href="%1$s" class="about_maalipay" title="'.esc_attr__('What is MaaliPay?', 'maalipot').'" onclick="javascript:window.open(\'%1$s\',\'WIMaaliPay\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=800, height=700\'); return false;"><i class="fa fa-question-circle"></i></a>', esc_url($this->get_icon_url($base_country)) );

		        return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
		    }
		    /**
		     * Get the link for an icon based on country.
		     *
		     * @param  string $country Country two letter code.
		     * @return string
		     */
		    protected function get_icon_url( $country ) {
		        $url           = 'https://www.maalicard.com/';
		        $home_countries = array( 'UG', 'KE', 'TZ', 'RW' );
		        $countries     = array( 'DZ', 'AU', 'BH', 'BQ', 'BW', 'CA', 'CN', 'CW', 'FI', 'FR', 'DE', 'GR', 'HK', 'ID', 'JO', 'KE', 'KW', 'LU', 'MY', 'MA', 'OM', 'PH', 'PL', 'PT', 'QA', 'IE', 'RU', 'BL', 'SX', 'MF', 'SA', 'SG', 'SK', 'KR', 'SS', 'TW', 'TH', 'AE', 'GB', 'US', 'VN' );

		        if ( in_array( $country, $home_countries, true ) ) {
		            return $url . '';
		        } elseif ( in_array( $country, $countries, true ) ) {
		            return $url . '';
		        } else {
		            return $url;
		        }
		    }

		    /**
		     * Get MaaliPay images for a country.
		     *
		     * @param string $country Country code.
		     * @return array of image URLs
		     */
		    protected function get_icon_image( $country ) {
		    	$icon = [];
		        switch ( $country ) {
		            case 'US':
		            case 'NZ':
		            case 'CZ':
		            case 'HU':
		            case 'MY':
						$icon = array(
							plugins_url('/assets/images/maalipay-icon.jpg', __FILE__ )
						);
		                break;
		            default:
		                $icon = array(
		                	plugins_url('/assets/images/maalipay-icon.jpg', __FILE__ ),
		                	plugins_url('/assets/images/mtn-logo.jpg', __FILE__ ),
		                	plugins_url('/assets/images/airtel-logo.jpg', __FILE__ ),
		                	plugins_url('/assets/images/visa-icon.jpg', __FILE__ ),
		                	plugins_url('/assets/images/mastercard-icon.jpg', __FILE__ )
		                );
		                break;
		        }
		        return apply_filters('woocommerce_maalicard_icon', $icon);
		    }
		    /**
		     * Process the payment and return the redirect url as a payment link.
		     *
		     * @param  int $order_id Order ID.
		     * @return array
		     */
		    public function process_payment( $order_id ) {
		        // include the payment gateway request processing class
		        include_once dirname(__FILE__).'/classes/class-wc-gateway-maalipay-request.php';
		        // get details of the current order, using its order_id
		        $order = wc_get_order( $order_id );
		        // check whether the total charge is more than zero
		        // and then use this payment gateway
		        if($order->get_total() > 0) {
		            // instatiate and process the payment gateway request class
		            // and use it to get the redirect url, for payment processing
		            $maalipay_request = new WC_Gateway_MaaliPay_Request( $this );
		            // set the payment processing url to redirect to
		            $redirect_url = $maalipay_request->get_request_url( $order, $this->testmode );
		            // only process order and empty cart if the returned url valid
		            if(filter_var($redirect_url, FILTER_VALIDATE_URL) == true) {
		                // Mark as on-hold (we're awaiting for the payment).
		                // $order->update_status('on-hold',  __('Awaiting payment', 'maalipot'));
		                $order->update_status($this->order_status, __( 'Awaiting payment', 'maalipot' ));
		                // then reduce the stock
		                // wc_reduce_stock_levels($order_id);
		                // $order->reduce_order_stock();
		                // and empy the cart
		                // WC()->cart->empty_cart();
		                // $woocommerce->cart->empty_cart();
		            }
		        // if the total charge is not more than zero
		        // then just complete the transaction, without using the payment gateway
		        } else {
		            // process the payment to 'processing' or 'completed' status,
		            // reduce stock levels,
		            // record sales for products,
		            // and date of payment.
		            $order->payment_complete();
		            // default redirect url
		            $redirect_url = $this->get_return_url( $order );
		        }
		        // redirect to appropriate url
		        return array(
		            'result'   => 'success',
		            'redirect' => $redirect_url,
		        );
		    }

		    public function thank_you_page() {
		        if( $this->instructions ){
		            echo wpautop( esc_html( $this->instructions ) );
		        }
		    }
		    /**
		     * Get the transaction URL.
		     *
		     * @param  WC_Order $order Order object.
		     * @return string
		     */
		    public function get_transaction_url( $order ) {
		        if ( $this->testmode ) {
		            $this->view_transaction_url = 'https://www.sandbox.maalipay.maalicard.com';
		        } else {
		            $this->view_transaction_url = 'https://maalipay.maalicard.com';
		        }
		        return parent::get_transaction_url( $order );
		    }
		    /**
		     * Custom MaaliPay order received text.
		     *
		     * @since 3.9.0
		     * @param string   $text Default text.
		     * @param WC_Order $order Order data.
		     * @return string
		     */
		    public function order_received_text( $text, $order ) {
		        if ( $order && $this->id === $order->get_payment_method() ) {
		            return esc_html__('Thank you for your payment. Your transaction has been completed, and a receipt for your purchase has been emailed to you. You can log into your MaaliPay account to view transaction details.', 'maalipot');
		        }

		        return $text;
		    }
		}
	}
}

/**
 * Adds Maalipay payment gateway class to WooCommerce usable payment gateways
 * @param array $gateways. Array of already existing payment gateways
 * @return array $gateways. Array of payment gateways with MaaliPay included
 */
function add_maalipay_payment_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_Maalipay';

    return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'add_maalipay_payment_gateway');

/**
 * Handle IPN requests for the legacy maalipay gateway by calling gateways manually if needed.
 *
 * @access public
 */
// function woocommerce_legacy_maalipay_ipn() {
// 	if ( ! empty( $_GET['maalipayListener'] ) && 'maalipay_standard_IPN' === $_GET['maalipayListener'] ) {
// 		WC()->payment_gateways();
// 		do_action( 'woocommerce_api_wc_gateway_maalipay' );
// 	}
// }
// add_action( 'init', 'woocommerce_legacy_maalipay_ipn' );
