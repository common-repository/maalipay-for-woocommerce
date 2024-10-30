<?php

/**
 * Class WC_Gateway_Maalipay_Request file.
 *
 * @package WooCommerce\Gateways\Payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates requests sent to MaaliPay payment platform.
 */
class WC_Gateway_Maalipay_Request {

	/**
	 * Stores line items to send to MaaliPay.
	 *
	 * @var array
	 */
	protected $line_items = array();

	/**
	 * Pointer to gateway making the request.
	 *
	 * @var WC_Gateway_Maalipay
	 */
	protected $gateway;

	/**
	 * Endpoint for requests from MaaliPay.
	 *
	 * @var string
	 */
	protected $notify_url;

	/**
	 * Endpoint for requests to MaaliPay.
	 *
	 * @var string
	 */
	protected $endpoint;


	/**
	 * Constructor.
	 *
	 * @param WC_Gateway_Maalipay $gateway MaaliPay gateway object.
	 */
	public function __construct( $gateway ) {
		$this->gateway    = $gateway;
		// @see https://docs.woocommerce.com/document/wc_api-the-woocommerce-api-callback/
		// http://yoursite.com/wc-api/CALLBACK/
		// $this->notify_url = home_url( '/wc-api/wc_gateway_maalipay', 'https' );
		$this->notify_url = WC()->api_request_url( 'WC_Gateway_Maalipay' );
	}
	/**
	 * Limit length of an arg.
	 *
	 * @param  string  $string Argument to limit.
	 * @param  integer $limit Limit size in characters.
	 * @return string
	 */
	protected function limit_length($string, $limit = 127) {
		$str_limit = $limit - 3;
		if(function_exists('mb_strimwidth')) {
			if(mb_strlen($string) > $limit) {
				$string = mb_strimwidth($string, 0, $str_limit) . '...';
			}
		} else {
			if(strlen($string) > $limit) {
				$string = substr($string, 0, $str_limit) . '...';
			}
		}
		return $string;
	}
	/**
	 * If the default request with line items is too long, generate a new one with only one line item.
	 *
	 * If URL is longer than 2,083 chars, ignore line items and send cart to MaaliPay as a single item.
	 * One item's name can only be 127 characters long, so the URL should not be longer than limit.
	 * URL character limit via:
	 * https://support.microsoft.com/en-us/help/208427/maximum-url-length-is-2-083-characters-in-internet-explorer.
	 *
	 * @param WC_Order $order Order to be sent to MaaliPay.
	 * @param array    $maalipay_args Arguments sent to MaaliPay in the request.
	 * @return array
	 */
	protected function fix_request_length( $order, $maalipay_args ) {
		$max_maalipay_length = 2083;
		$query_candidate   = http_build_query( $maalipay_args, '', '&' );

		if ( strlen( $this->endpoint . $query_candidate ) <= $max_maalipay_length ) {
			return $maalipay_args;
		}

		return apply_filters(
			'woocommerce_maalipay_args',
			array_merge(
				$this->get_transaction_args( $order ),
				$this->get_line_item_args( $order, true )
			),
			$order
		);

	}
	/**
	 * Get the MaaliPay request URL for an order.
	 *
	 * @param  WC_Order $order Order object.
	 * @param  bool     $sandbox Whether to use sandbox mode or not.
	 * @return string
	 */
	public function get_request_url( $order, $sandbox = false ) {
		$this->endpoint    = $sandbox ? 'https://sandbox.maalipay.maalicard.com/app/initiate-collection' : 'https://maalipay.maalicard.com/app/initiate-collection';
		// $maalipay_args       = $this->get_maalipay_args( $order );
		$maalipay_args       = $this->get_transaction_args( $order );
		// Append WooCommerce MaaliPay Partner Attribution ID. This should not be overridden for this gateway.
		// $maalipay_args['bn'] = 'WooCommerce_Cart';

		$args = array(
			'body' => $maalipay_args,
			'timeout' => 600,
			'data_format' => 'body',
		);
		// Set the API URL
		// $url = $this->endpoint;
		$url = 'https://maalipay.maalicard.com/app/initiate-collection';
		// USING WP REMOTE POST
		// $response = wp_remote_post($url, $args);
		// $url = esc_url_raw($url);
		// $args['user-agent'] = 'Plugin Demo: HTTP API; '. home_url();
		$response = wp_safe_remote_post( $url, $args );
		// $response = wp_remote_request($url, $args);
		// check results from the response
		if(is_wp_error( $response )) {
			$error_message = $response->get_error_message();
			// log message if there was an error and return false
			WC_Gateway_Maalipay::log( 'Something went wrong: ' . $error_message."\n\r", 'error');
			return false;
		} else {
			// response data
		    $resp_code 		= wp_remote_retrieve_response_code($response);
		    // $message 		= wp_remote_retrieve_response_message($response);
		    $resp_body 		= wp_remote_retrieve_body($response);
			// $headers 		= wp_remote_retrieve_headers($response);
			// $header_date    = wp_remote_retrieve_header($response, 'date');
			// $header_type    = wp_remote_retrieve_header($response, 'content-type');
			// $header_cache   = wp_remote_retrieve_header($response, 'cache-control');

			// get the response body and json_decode it
			/**
			 * returned data in json format
			 * {
			 * "code": 200,
			 * "status": "success",
			 * "message": "Request completed successfully.",
			 * "data": {
			 * 		"payment_link": "https://maalipay.maalicard.com/app/completepayment/3385g28bbkkevp1t15",
			 * 	}
			 * }
			 */
			$resp_data = json_decode($resp_body);
			// response can also be typecasted into an array
	        // $resp_data = (array)$resp_data;

			// log response body from the API
			WC_Gateway_Maalipay::log($resp_body."\n\r", 'info');

			// use the response code and status to retrieve the return url
			if(200 == $resp_code && 'success' == $resp_data->status) {
				return $resp_data->data->payment_link;
			} else {
				// if no return url, log an error message and return false
				WC_Gateway_Maalipay::log("Could not retrieve the return URL! Please contact the administrator.\n\r", 'error');
				return false;
			}
		}
	}
	/**
	 * Get transaction args for MaaliPay request, except for line item args.
	 *
	 * @param WC_Order $order Order object.
	 * @return array
	 */
	protected function get_transaction_args( $order ) {
		return
			array(
				/**
				 * set the right parameters to query the MaaliPay Payment API
				 */
				/*{
					"merchant_id": "121212",
					"first_name": "John",
					"last_name": "Doe",
					"email": "johndoe@email.com",
					"currency": "UGX",
					"amount": 500,
					"narration": "Test Collection from POST",
					"merchant_reference": "ref1",
					"callback_url": "https://www.callback.url"
				}*/
				'merchant_id' 	=> (isset($_POST['maalipot_id']) && is_numeric($_POST['maalipot_id'])) ? wp_kses_post(trim($_POST['maalipot_id'])) : $this->gateway->get_option('default_maalipot_id'),
				'first_name'    => $this->limit_length( $order->get_billing_first_name(), 32 ),
				'last_name'     => $this->limit_length( $order->get_billing_last_name(), 64 ),
				'email'         => $this->limit_length( $order->get_billing_email() ),
				// 'currency' 		=> 'UGX',
				'currency' 		=> get_woocommerce_currency(),
				'amount' 		=> WC()->cart->get_total(false),
				// 'amount' 		=> WC()->cart->get_cart_total(),
				// 'narration' 	=> 'Purchase via Catalogue Shoppers. Order ID: '.$order->get_id().'. Order Key:'.$order->get_order_key(),
				'narration' 	=> 'Order ID: '.$order->get_id(),
				// added for notification url (IPN)
				// 'payment_type' 	=> 'purchase',
				// 'custom'        => wp_json_encode(
				// 	array(
				// 		'order_id'  => $order->get_id(),
				// 		'order_key' => $order->get_order_key(),
				// 	)
				// ),
				// send reference in format order_id - order_key (wc_order_AhjrDmcl37ZOa)
				'merchant_reference' => $order->get_id(). ' - '.$order->get_order_key(),
				'callback_url'    => $this->limit_length($this->notify_url, 255),
			);
	}

	/**
	 * Get MaaliPay Args for passing to PP.
	 *
	 * @param  WC_Order $order Order object.
	 * @return array
	 */
	protected function get_maalipay_args( $order ) {
		WC_Gateway_Maalipay::log( 'Generating payment form for order ' . $order->get_order_number() . '. Notify URL: ' . $this->notify_url );

		$force_one_line_item = apply_filters( 'woocommerce_maalipay_force_one_line_item', false, $order );

		if ( ( wc_tax_enabled() && wc_prices_include_tax() ) || ! $this->line_items_valid( $order ) ) {
			$force_one_line_item = true;
		}

		$maalipay_args = apply_filters(
			'woocommerce_maalipay_args',
			array_merge(
				$this->get_transaction_args( $order ),
				$this->get_line_item_args( $order, $force_one_line_item )
			),
			$order
		);

		return $this->fix_request_length( $order, $maalipay_args );
	}

	/**
	 * Get phone number args for MaaliPay request.
	 *
	 * @param  WC_Order $order Order object.
	 * @return array
	 */
	protected function get_phone_number_args( $order ) {
		$phone_number = wc_sanitize_phone_number( $order->get_billing_phone() );

		if ( in_array( $order->get_billing_country(), array( 'US', 'CA' ), true ) ) {
			$phone_number = ltrim( $phone_number, '+1' );
			$phone_args   = array(
				'night_phone_a' => substr( $phone_number, 0, 3 ),
				'night_phone_b' => substr( $phone_number, 3, 3 ),
				'night_phone_c' => substr( $phone_number, 6, 4 ),
			);
		} else {
			$calling_code = WC()->countries->get_country_calling_code( $order->get_billing_country() );
			$calling_code = is_array( $calling_code ) ? $calling_code[0] : $calling_code;

			if ( $calling_code ) {
				$phone_number = str_replace( $calling_code, '', preg_replace( '/^0/', '', $order->get_billing_phone() ) );
			}

			$phone_args = array(
				'night_phone_a' => $calling_code,
				'night_phone_b' => $phone_number,
			);
		}
		return $phone_args;
	}

	/**
	 * Get shipping args for MaaliPay request.
	 *
	 * @param  WC_Order $order Order object.
	 * @return array
	 */
	protected function get_shipping_args( $order ) {
		$shipping_args = array();
		if ( $order->needs_shipping_address() ) {
			$shipping_args['address_override'] = $this->gateway->get_option( 'address_override' ) === 'yes' ? 1 : 0;
			$shipping_args['no_shipping']      = 0;
			if ( 'yes' === $this->gateway->get_option( 'send_shipping' ) ) {
				// If we are sending shipping, send shipping address instead of billing.
				$shipping_args['first_name'] = $this->limit_length( $order->get_shipping_first_name(), 32 );
				$shipping_args['last_name']  = $this->limit_length( $order->get_shipping_last_name(), 64 );
				$shipping_args['address1']   = $this->limit_length( $order->get_shipping_address_1(), 100 );
				$shipping_args['address2']   = $this->limit_length( $order->get_shipping_address_2(), 100 );
				$shipping_args['city']       = $this->limit_length( $order->get_shipping_city(), 40 );
				$shipping_args['state']      = $this->get_maalipay_state( $order->get_shipping_country(), $order->get_shipping_state() );
				$shipping_args['country']    = $this->limit_length( $order->get_shipping_country(), 2 );
				$shipping_args['zip']        = $this->limit_length( wc_format_postcode( $order->get_shipping_postcode(), $order->get_shipping_country() ), 32 );
			}
		} else {
			$shipping_args['no_shipping'] = 1;
		}
		return $shipping_args;
	}

	/**
	 * Get shipping cost line item args for MaaliPay request.
	 *
	 * @param  WC_Order $order Order object.
	 * @param  bool     $force_one_line_item Whether one line item was forced by validation or URL length.
	 * @return array
	 */
	protected function get_shipping_cost_line_item( $order, $force_one_line_item ) {
		$line_item_args = array();
		$shipping_total = $order->get_shipping_total();
		if ( $force_one_line_item ) {
			$shipping_total += $order->get_shipping_tax();
		}

		// Add shipping costs. MaaliPay ignores anything over 5 digits (999.99 is the max).
		// We also check that shipping is not the **only** cost as MaaliPay won't allow payment
		// if the items have no cost.
		if ( $order->get_shipping_total() > 0 && $order->get_shipping_total() < 999.99 && $this->number_format( $order->get_shipping_total() + $order->get_shipping_tax(), $order ) !== $this->number_format( $order->get_total(), $order ) ) {
			$line_item_args['shipping_1'] = $this->number_format( $shipping_total, $order );
		} elseif ( $order->get_shipping_total() > 0 ) {
			/* translators: %s: Order shipping method */
			$this->add_line_item( sprintf( __( 'Shipping via %s', 'woocommerce' ), $order->get_shipping_method() ), 1, $this->number_format( $shipping_total, $order ) );
		}

		return $line_item_args;
	}

	/**
	 * Get line item args for MaaliPay request as a single line item.
	 *
	 * @param  WC_Order $order Order object.
	 * @return array
	 */
	protected function get_line_item_args_single_item( $order ) {
		$this->delete_line_items();

		$all_items_name = $this->get_order_item_names( $order );
		$this->add_line_item( $all_items_name ? $all_items_name : __( 'Order', 'woocommerce' ), 1, $this->number_format( $order->get_total() - $this->round( $order->get_shipping_total() + $order->get_shipping_tax(), $order ), $order ), $order->get_order_number() );
		$line_item_args = $this->get_shipping_cost_line_item( $order, true );

		return array_merge( $line_item_args, $this->get_line_items() );
	}

	/**
	 * Get line item args for MaaliPay request.
	 *
	 * @param  WC_Order $order Order object.
	 * @param  bool     $force_one_line_item Create only one item for this order.
	 * @return array
	 */
	protected function get_line_item_args( $order, $force_one_line_item = false ) {
		$line_item_args = array();

		if ( $force_one_line_item ) {
			/**
			 * Send order as a single item.
			 *
			 * For shipping, we longer use shipping_1 because MaaliPay ignores it if *any* shipping rules are within MaaliPay, and MaaliPay ignores anything over 5 digits (999.99 is the max).
			 */
			$line_item_args = $this->get_line_item_args_single_item( $order );
		} else {
			/**
			 * Passing a line item per product if supported.
			 */
			$this->prepare_line_items( $order );
			$line_item_args['tax_cart'] = $this->number_format( $order->get_total_tax(), $order );

			if ( $order->get_total_discount() > 0 ) {
				$line_item_args['discount_amount_cart'] = $this->number_format( $this->round( $order->get_total_discount(), $order ), $order );
			}

			$line_item_args = array_merge( $line_item_args, $this->get_shipping_cost_line_item( $order, false ) );
			$line_item_args = array_merge( $line_item_args, $this->get_line_items() );

		}

		return $line_item_args;
	}

	/**
	 * Get order item names as a string.
	 *
	 * @param  WC_Order $order Order object.
	 * @return string
	 */
	protected function get_order_item_names( $order ) {
		$item_names = array();

		foreach ( $order->get_items() as $item ) {
			$item_name = $item->get_name();
			$item_meta = wp_strip_all_tags(
				wc_display_item_meta(
					$item,
					array(
						'before'    => '',
						'separator' => ', ',
						'after'     => '',
						'echo'      => false,
						'autop'     => false,
					)
				)
			);

			if ( $item_meta ) {
				$item_name .= ' (' . $item_meta . ')';
			}

			$item_names[] = $item_name . ' x ' . $item->get_quantity();
		}

		return apply_filters( 'woocommerce_maalipay_get_order_item_names', implode( ', ', $item_names ), $order );
	}

	/**
	 * Get order item names as a string.
	 *
	 * @param  WC_Order      $order Order object.
	 * @param  WC_Order_Item $item Order item object.
	 * @return string
	 */
	protected function get_order_item_name( $order, $item ) {
		$item_name = $item->get_name();
		$item_meta = wp_strip_all_tags(
			wc_display_item_meta(
				$item,
				array(
					'before'    => '',
					'separator' => ', ',
					'after'     => '',
					'echo'      => false,
					'autop'     => false,
				)
			)
		);

		if ( $item_meta ) {
			$item_name .= ' (' . $item_meta . ')';
		}

		return apply_filters( 'woocommerce_maalipay_get_order_item_name', $item_name, $order, $item );
	}

	/**
	 * Return all line items.
	 */
	protected function get_line_items() {
		return $this->line_items;
	}

	/**
	 * Remove all line items.
	 */
	protected function delete_line_items() {
		$this->line_items = array();
	}

	/**
	 * Check if the order has valid line items to use for MaaliPay request.
	 *
	 * The line items are invalid in case of mismatch in totals or if any amount < 0.
	 *
	 * @param WC_Order $order Order to be examined.
	 * @return bool
	 */
	protected function line_items_valid( $order ) {
		$negative_item_amount = false;
		$calculated_total     = 0;

		// Products.
		foreach ( $order->get_items( array( 'line_item', 'fee' ) ) as $item ) {
			if ( 'fee' === $item['type'] ) {
				$item_line_total   = $this->number_format( $item['line_total'], $order );
				$calculated_total += $item_line_total;
			} else {
				$item_line_total   = $this->number_format( $order->get_item_subtotal( $item, false ), $order );
				$calculated_total += $item_line_total * $item->get_quantity();
			}

			if ( $item_line_total < 0 ) {
				$negative_item_amount = true;
			}
		}
		$mismatched_totals = $this->number_format( $calculated_total + $order->get_total_tax() + $this->round( $order->get_shipping_total(), $order ) - $this->round( $order->get_total_discount(), $order ), $order ) !== $this->number_format( $order->get_total(), $order );
		return ! $negative_item_amount && ! $mismatched_totals;
	}

	/**
	 * Get line items to send to MaaliPay.
	 *
	 * @param  WC_Order $order Order object.
	 */
	protected function prepare_line_items( $order ) {
		$this->delete_line_items();

		// Products.
		foreach ( $order->get_items( array( 'line_item', 'fee' ) ) as $item ) {
			if ( 'fee' === $item['type'] ) {
				$item_line_total = $this->number_format( $item['line_total'], $order );
				$this->add_line_item( $item->get_name(), 1, $item_line_total );
			} else {
				$product         = $item->get_product();
				$sku             = $product ? $product->get_sku() : '';
				$item_line_total = $this->number_format( $order->get_item_subtotal( $item, false ), $order );
				$this->add_line_item( $this->get_order_item_name( $order, $item ), $item->get_quantity(), $item_line_total, $sku );
			}
		}
	}

	/**
	 * Add MaaliPay Line Item.
	 *
	 * @param  string $item_name Item name.
	 * @param  int    $quantity Item quantity.
	 * @param  float  $amount Amount.
	 * @param  string $item_number Item number.
	 */
	protected function add_line_item( $item_name, $quantity = 1, $amount = 0.0, $item_number = '' ) {
		$index = ( count( $this->line_items ) / 4 ) + 1;

		$item = apply_filters(
			'woocommerce_maalipay_line_item',
			array(
				'item_name'   => html_entity_decode( wc_trim_string( $item_name ? wp_strip_all_tags( $item_name ) : __( 'Item', 'woocommerce' ), 127 ), ENT_NOQUOTES, 'UTF-8' ),
				'quantity'    => (int) $quantity,
				'amount'      => wc_float_to_string( (float) $amount ),
				'item_number' => $item_number,
			),
			$item_name,
			$quantity,
			$amount,
			$item_number
		);

		$this->line_items[ 'item_name_' . $index ]   = $this->limit_length( $item['item_name'], 127 );
		$this->line_items[ 'quantity_' . $index ]    = $item['quantity'];
		$this->line_items[ 'amount_' . $index ]      = $item['amount'];
		$this->line_items[ 'item_number_' . $index ] = $this->limit_length( $item['item_number'], 127 );
	}

	/**
	 * Get the state to send to MaaliPay.
	 *
	 * @param  string $cc Country two letter code.
	 * @param  string $state State code.
	 * @return string
	 */
	protected function get_maalipay_state( $cc, $state ) {
		if ( 'US' === $cc ) {
			return $state;
		}

		$states = WC()->countries->get_states( $cc );

		if ( isset( $states[ $state ] ) ) {
			return $states[ $state ];
		}

		return $state;
	}

	/**
	 * Check if currency has decimals.
	 *
	 * @param  string $currency Currency to check.
	 * @return bool
	 */
	protected function currency_has_decimals( $currency ) {
		if ( in_array( $currency, array( 'HUF', 'JPY', 'TWD' ), true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Round prices.
	 *
	 * @param  double   $price Price to round.
	 * @param  WC_Order $order Order object.
	 * @return double
	 */
	protected function round( $price, $order ) {
		$precision = 2;

		if ( ! $this->currency_has_decimals( $order->get_currency() ) ) {
			$precision = 0;
		}

		return round( $price, $precision );
	}

	/**
	 * Format prices.
	 *
	 * @param  float|int $price Price to format.
	 * @param  WC_Order  $order Order object.
	 * @return string
	 */
	protected function number_format( $price, $order ) {
		$decimals = 2;

		if ( ! $this->currency_has_decimals( $order->get_currency() ) ) {
			$decimals = 0;
		}

		return number_format( $price, $decimals, '.', '' );
	}
}
