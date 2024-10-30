<?php

/**
 * Handles responses from MaaliPay IPN.
 *
 * @package WooCommerce\Gateways\Payment
 * @version 3.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/class-wc-gateway-maalipay-response.php';

/**
 * Handles IPN (responses) from MaaliPay, to help in processing payments and transaction statuses.
 */
class WC_Gateway_Maalipay_IPN_Handler extends WC_Gateway_Maalipay_Response
{

    /**
     * Receiver email address to validate.
     *
     * @var string Receiver email address.
     */
    protected $receiver_email;

    /**
     * Constructor.
     *
     * @param bool   $sandbox Use sandbox or not.
     * @param string $receiver_email Email to receive IPN from.
     */
    public function __construct($sandbox = false, $receiver_email = '')
    {
        add_action('woocommerce_api_wc_gateway_maalipay', array($this, 'check_response'));
        add_action('validate_maalipay_ipn_request', array($this, 'valid_response'));

        $this->receiver_email = $receiver_email;
        $this->sandbox        = $sandbox;
    }
    /**
     * Check for MaaliPay IPN Response.
     */
    public function check_response()
    {
        if (!empty($_POST) && $this->validate_ipn()) { // WPCS: CSRF ok.
            /**
             * Get the response data posted back through the callback url (notify_url)
             *
             * https://example.com/?wc-api=WC_Gateway_Maalipay
             */
            /**
             * Returned API request from MaaliPay
                {
                    "merchant_reference": 00001 - Order_key,
                    "internal_reference": "internalRef1",
                    "currency": "UGX",
                    "amount": 500,
                    "transaction_status": "COMPLETED",
                    "message": "Transaction completed successfully"
                }
             */
            // $posted = wp_unslash($_POST); // WPCS: CSRF ok, input var ok.
            // $posted = wp_kses_post( wp_unslash( $_POST ) ); // WPCS: CSRF ok, input var ok.
            $posted = sanitize_post( wp_unslash( $_POST ) ); // WPCS: CSRF ok, input var ok.
            // Create a new action hook, to capture posted data and use it in processing the IPN
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
            do_action('validate_maalipay_ipn_request', $posted);
            exit;
        }

        wp_die('MaaliPay IPN Request Failed', 'MaaliPay IPN', array('response' => 500));
    }
    /**
     * Checks MaaliPay IPN validity.
     */
    public function validate_ipn()
    {
        WC_Gateway_Maalipay::log('Checking IPN response is valid');

        // Get received values from post data.
        // $maalipay_ipn = wp_unslash($_POST); // WPCS: CSRF ok, input var ok.
        $maalipay_ipn = sanitize_post( wp_unslash( $_POST ) ); // WPCS: CSRF ok, input var ok.
        // check whether an order with the returned order_id & order_key exists
        // for the same order_id
        $order = !empty($maalipay_ipn['merchant_reference']) ? $this->get_maalipay_order($maalipay_ipn['merchant_reference']) : false;
        if ($order) {
            WC_Gateway_Maalipay::log('Received valid response from MaaliPay IPN');
            return true;
        }

        return false;
    }
    /**
     * Processes payment transaction if the response is valid,
     *
     * using posted data returned from MaaliPay IPN
     *
     * @param  array $posted Post data after wp_unslash.
     */
    public function valid_response($posted)
    {
        $order = !empty($posted['merchant_reference']) ? $this->get_maalipay_order($posted['merchant_reference']) : false;

        if ($order) {

            // convert payment status to lowercase variable
            // expected statuses include COMPLETED, PENDING, FAILED OR CANCELLED
            $posted['transaction_status'] = strtolower($posted['transaction_status']);

            WC_Gateway_Maalipay::log('Found order #' . $order->get_id());
            WC_Gateway_Maalipay::log('Payment status: ' . $posted['transaction_status']);

            if (method_exists($this, 'payment_status_' . $posted['transaction_status'])) {
                call_user_func(array($this, 'payment_status_' . $posted['transaction_status']), $order, $posted);
            }
        }
    }
    /**
     * Check for a valid transaction type.
     *
     * @param string $txn_type Transaction type.
     */
    protected function validate_transaction_type($txn_type)
    {
        $accepted_types = array('purchase', 'subscription', 'send_money', 'web_accept');

        // if (strtolower($txn_type) !== $accepted_types) {
        if (!in_array(strtolower($txn_type), $accepted_types, true)) {
            WC_Gateway_Maalipay::log('Aborted! Invalid transaction type:' . $txn_type);
            exit;
        }
    }

    /**
     * Check currency from IPN matches the order.
     *
     * @param WC_Order $order    Order object.
     * @param string   $currency Currency code.
     */
    protected function validate_currency($order, $currency)
    {
        if ($order->get_currency() !== $currency) {
            WC_Gateway_Maalipay::log('Payment error: Currencies do not match (sent "' . $order->get_currency() . '" | returned "' . $currency . '")');

            /* translators: %s: currency code. */
            $order->update_status('on-hold', sprintf(__('Validation error: MaaliPay currencies do not match (code %s).', 'maalipot'), $currency));
            exit;
        }
    }

    /**
     * Checks whether payment amount from IPN matches the order.
     *
     * @param WC_Order $order  Order object.
     * @param int      $amount Amount to validate.
     */
    protected function validate_amount($order, $amount)
    {
        if (number_format($order->get_total(), 2, '.', '') !== number_format($amount, 2, '.', '')) {
            WC_Gateway_Maalipay::log('Payment error: Amounts do not match (gross ' . $amount . ')');

            /* translators: %s: Amount. */
            $order->update_status('on-hold', sprintf(__('Validation error: MaaliPay amounts do not match (gross %s).', 'maalipot'), $amount));
            exit;
        }
    }

    /**
     * Check receiver email from MaaliPay. If the receiver email in the IPN is different than what is stored in.
     * WooCommerce -> Settings -> Checkout -> MaaliPay, it will log an error about it.
     *
     * @param WC_Order $order          Order object.
     * @param string   $receiver_email Email to validate.
     */
    protected function validate_receiver_email($order, $receiver_email)
    {
        if (strcasecmp(trim($receiver_email), trim($this->receiver_email)) !== 0) {
            WC_Gateway_Maalipay::log("IPN Response is for another account: {$receiver_email}. Your email is {$this->receiver_email}");

            /* translators: %s: email address . */
            $order->update_status('on-hold', sprintf(__('Validation error: MaaliPay IPN response from a different email address (%s).', 'maalipot'), $receiver_email));
            exit;
        }
    }

    /**
     * Handles a completed payment.
     *
     * @param WC_Order $order  Order object.
     * @param array    $posted Posted data.
     */
    protected function payment_status_completed($order, $posted)
    {
        if ($order->has_status(wc_get_is_paid_statuses())) {
            WC_Gateway_Maalipay::log('Aborting, Order #' . $order->get_id() . ' is already complete.');
            exit;
        }
        // validate the transaction type returned
        // $this->validate_transaction_type($posted['txn_type']);
        // validate the currency returned
        $this->validate_currency($order, $posted['currency']);
        // validate the amount returned
        $this->validate_amount($order, $posted['amount']);
        // validate receiver's email address returned
        // $this->validate_receiver_email($order, $posted['receiver_email']);
        // update/save transaction meta data
        $this->save_maalipay_meta_data($order, $posted);

        // process transaction accordingly
        if ('completed' === $posted['transaction_status']) {
            if ($order->has_status('cancelled')) {
                $this->payment_status_paid_cancelled_order($order, $posted);
            }

            if (!empty($posted['mc_fee'])) {
                $order->add_meta_data('MaaliPay Transaction Fee', wc_clean($posted['mc_fee']));
            }

            $this->payment_complete($order, (!empty($posted['internal_reference']) ? wc_clean($posted['internal_reference']) : ''), __('IPN payment completed', 'maalipot'));
        } else {
            if ('authorization' === $posted['pending_reason']) {
                $this->payment_on_hold($order, __('Payment authorized. Change payment status to processing or complete to capture funds.', 'maalipot'));
            } else {
                /* translators: %s: pending reason. */
                $this->payment_on_hold($order, sprintf(__('Payment pending (%s).', 'maalipot'), $posted['pending_reason']));
            }
        }
    }

    /**
     * Handles a pending payment.
     *
     * @param WC_Order $order  Order object.
     * @param array    $posted Posted data.
     */
    protected function payment_status_pending($order, $posted)
    {
        $this->payment_status_completed($order, $posted);
    }

    /**
     * Handle a failed payment.
     *
     * @param WC_Order $order  Order object.
     * @param array    $posted Posted data.
     */
    protected function payment_status_failed($order, $posted)
    {
        /* translators: %s: payment status. */
        $order->update_status('failed', sprintf(__('Payment %s via IPN.', 'maalipot'), wc_clean($posted['transaction_status'])));
    }

    /**
     * Handle a denied payment.
     *
     * @param WC_Order $order  Order object.
     * @param array    $posted Posted data.
     */
    protected function payment_status_denied($order, $posted)
    {
        $this->payment_status_failed($order, $posted);
    }

    /**
     * Handle an expired payment.
     *
     * @param WC_Order $order  Order object.
     * @param array    $posted Posted data.
     */
    protected function payment_status_expired($order, $posted)
    {
        $this->payment_status_failed($order, $posted);
    }

    /**
     * Handle a voided payment.
     *
     * @param WC_Order $order  Order object.
     * @param array    $posted Posted data.
     */
    protected function payment_status_voided($order, $posted)
    {
        $this->payment_status_failed($order, $posted);
    }

    /**
     * When a user cancelled order is marked paid.
     *
     * @param WC_Order $order  Order object.
     * @param array    $posted Posted data.
     */
    protected function payment_status_paid_cancelled_order($order, $posted)
    {
        $this->send_ipn_email_notification(
            /* translators: %s: order link. */
            sprintf(__('Payment for cancelled order %s received', 'maalipot'), '<a class="link" href="' . esc_url($order->get_edit_order_url()) . '">' . $order->get_order_number() . '</a>'),
            /* translators: %s: order ID. */
            sprintf(__('Order #%s has been marked paid by MaaliPay IPN, but was previously cancelled. Admin handling required.', 'maalipot'), $order->get_order_number())
        );
    }

    /**
     * Handle a refunded order.
     *
     * @param WC_Order $order  Order object.
     * @param array    $posted Posted data.
     */
    protected function payment_status_refunded($order, $posted)
    {
        // Only handle full refunds, not partial.
        if ($order->get_total() === wc_format_decimal($posted['mc_gross'] * -1, wc_get_price_decimals())) {

            /* translators: %s: payment status. */
            $order->update_status('refunded', sprintf(__('Payment %s via IPN.', 'maalipot'), strtolower($posted['transaction_status'])));

            $this->send_ipn_email_notification(
                /* translators: %s: order link. */
                sprintf(__('Payment for order %s refunded', 'maalipot'), '<a class="link" href="' . esc_url($order->get_edit_order_url()) . '">' . $order->get_order_number() . '</a>'),
                /* translators: %1$s: order ID, %2$s: reason code. */
                sprintf(__('Order #%1$s has been marked as refunded - MaaliPay reason code: %2$s', 'maalipot'), $order->get_order_number(), $posted['reason_code'])
            );
        }
    }

    /**
     * Handle a reversal.
     *
     * @param WC_Order $order  Order object.
     * @param array    $posted Posted data.
     */
    protected function payment_status_reversed($order, $posted)
    {
        /* translators: %s: payment status. */
        $order->update_status('on-hold', sprintf(__('Payment %s via IPN.', 'maalipot'), wc_clean($posted['transaction_status'])));

        $this->send_ipn_email_notification(
            /* translators: %s: order link. */
            sprintf(__('Payment for order %s reversed', 'maalipot'), '<a class="link" href="' . esc_url($order->get_edit_order_url()) . '">' . $order->get_order_number() . '</a>'),
            /* translators: %1$s: order ID, %2$s: reason code. */
            sprintf(__('Order #%1$s has been marked on-hold due to a reversal - MaaliPay reason code: %2$s', 'maalipot'), $order->get_order_number(), wc_clean($posted['reason_code']))
        );
    }

    /**
     * Handle a cancelled reversal.
     *
     * @param WC_Order $order  Order object.
     * @param array    $posted Posted data.
     */
    protected function payment_status_canceled_reversal($order, $posted)
    {
        $this->send_ipn_email_notification(
            /* translators: %s: order link. */
            sprintf(__('Reversal cancelled for order #%s', 'maalipot'), $order->get_order_number()),
            /* translators: %1$s: order ID, %2$s: order link. */
            sprintf(__('Order #%1$s has had a reversal cancelled. Please check the status of payment and update the order status accordingly here: %2$s', 'maalipot'), $order->get_order_number(), esc_url($order->get_edit_order_url()))
        );
    }

    /**
     * Save important data from the IPN to the order.
     *
     * @param WC_Order $order  Order object.
     * @param array    $posted Posted data.
     */
    protected function save_maalipay_meta_data($order, $posted)
    {
        if (!empty($posted['payment_type'])) {
            update_post_meta($order->get_id(), 'Payment type', wc_clean($posted['payment_type']));
        }
        if (!empty($posted['internal_reference'])) {
            update_post_meta($order->get_id(), '_transaction_id', wc_clean($posted['internal_reference']));
        }
        if (!empty($posted['transaction_status'])) {
            update_post_meta($order->get_id(), '_maalipay_status', wc_clean($posted['transaction_status']));
        }
    }

    /**
     * Send a notification to the user handling orders.
     *
     * @param string $subject Email subject.
     * @param string $message Email message.
     */
    protected function send_ipn_email_notification($subject, $message)
    {
        $new_order_settings = get_option('woocommerce_new_order_settings', array());
        $mailer             = WC()->mailer();
        $message            = $mailer->wrap_message($subject, $message);

        $woocommerce_maalipay_settings = get_option('woocommerce_maalipay_settings');
        if (!empty($woocommerce_maalipay_settings['ipn_notification']) && 'no' === $woocommerce_maalipay_settings['ipn_notification']) {
            return;
        }

        $mailer->send(!empty($new_order_settings['recipient']) ? $new_order_settings['recipient'] : get_option('admin_email'), strip_tags($subject), $message);
    }
}
