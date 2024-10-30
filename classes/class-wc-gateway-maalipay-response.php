<?php

/**
 * Class WC_Gateway_Maalipay_Response file.
 *
 * @package WooCommerce\Gateways\Payment
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Gateway_Maalipay_Response abstract.
 */
abstract class WC_Gateway_Maalipay_Response
{

    /**
     * Sandbox mode
     *
     * @var bool
     */
    protected $sandbox = false;

    /**
     * Get the order from the MaaliPay 'merchant_reference' variable.
     *
     * @param  string $refernce String Data passed back by MaaliPay.
     * @return bool|WC_Order object
     */
    protected function get_maalipay_order($refernce)
    {
        // returned merchant_reference is in the format 'order_id - order_key'
        // as set in the get_transaction_args() method of the gateway request class
        // $string = '1043 - wc_order_AhjrDmcl37ZOa';
        if(!$refernce || NULL == $refernce) return false;
        // generate an array to split the items
        $data = explode('-', $refernce);
        // if either of id or key is missing
        if(count($data) < 2) {
            // Missing values.
            WC_Gateway_Maalipay::log('Either Order ID or key is missing in "merchant_reference".', 'error');
            return false;
        }
        // pair the array values with their right key names (order_id & order_key)
        $paired = [];
        foreach($data as $key => $value) {
            if($key == 0) { $id = 'order_id'; } else { $id = 'order_key'; }
            $paired[$id] = trim($value);
        }
        // encode the paired values into a JSON object
        $json = wp_json_encode($paired);
        // We have the data in the correct format, so get the order.
        $custom = json_decode($json);
        if ($custom && is_object($custom)) {
            $order_id  = $custom->order_id;
            $order_key = $custom->order_key;
        } else {
            // Nothing was found.
            WC_Gateway_Maalipay::log('Order ID and key were not found in "merchant_reference".', 'error');
            return false;
        }


        $order = wc_get_order($order_id);

        if (!$order) {
            // We have an invalid $order_id, probably because invoice_prefix has changed.
            $order_id = wc_get_order_id_by_order_key($order_key);
            $order    = wc_get_order($order_id);
        }

        if (!$order || !hash_equals($order->get_order_key(), $order_key)) {
            WC_Gateway_Maalipay::log('Order Keys do not match.', 'error');
            return false;
        }

        return $order;
    }

    /**
     * Complete order, add transaction ID and note.
     *
     * @param  WC_Order $order Order object.
     * @param  string   $txn_id Transaction ID.
     * @param  string   $note Payment note.
     */
    protected function payment_complete($order, $txn_id = '', $note = '')
    {
        if (!$order->has_status(array('processing', 'completed'))) {
            $order->add_order_note($note);
            $order->payment_complete($txn_id);
            WC()->cart->empty_cart();
        }
    }

    /**
     * Hold order and add note.
     *
     * @param  WC_Order $order Order object.
     * @param  string   $reason Reason why the payment is on hold.
     */
    protected function payment_on_hold($order, $reason = '')
    {
        $order->update_status('on-hold', $reason);
        WC()->cart->empty_cart();
    }
}
