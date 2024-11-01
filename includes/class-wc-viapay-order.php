<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Viapay_Order
{
	public function __construct() {
		add_filter( 'viapay_order_handle' , array( $this, 'get_order_handle' ), 10, 3 );
		add_filter( 'viapay_get_order' , array( $this, 'get_orderid_by_handle' ), 10, 2 );
	}

	/**
	 * Get Viapay Order Handle.
	 *
	 * @param string $handle
	 * @param mixed $order_id
	 * @param WC_Order $order
	 *
	 * @return mixed|string
	 */
	public function get_order_handle( $handle, $order_id, $order ) {
		$meta_key = '_viapay_order';
		$handle = get_post_meta( $order->get_id(), $meta_key, TRUE );
		if ( empty( $handle ) ) {
			$handle = $this->generateHandleByOrderId($order->get_id());
			update_post_meta( $order->get_id(), $meta_key, $handle );
		}

		return $handle;
	}

	/**
	 * Get Order Id by Viapay Order Handle.
	 *
	 * @param int|null $order_id
	 * @param string $handle
	 *
	 * @return bool|mixed
	 */
	public function get_orderid_by_handle( $order_id, $handle ) {
		global $wpdb;

		$meta_key = '_viapay_order';
		$query = "
			SELECT post_id FROM {$wpdb->prefix}postmeta 
			LEFT JOIN {$wpdb->prefix}posts ON ({$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id)
			WHERE meta_key = %s AND meta_value = %s;";
		$sql = $wpdb->prepare( $query, $meta_key, $handle );
		$order_id = $wpdb->get_var( $sql );
		if ( ! $order_id ) {
			return false;
		}

		return $order_id;
	}

	/**
	 * Generate order handle, by order id
	 * 
	 * @param string $order_id
     * @return string
	 */
	private function generateHandleByOrderId($order_id) {
		$salt = '';
				
		$settings = get_option( 'woocommerce_viapay_checkout_settings' );
		if (!empty($settings)) {
			if (isset($settings['test_mode'])) {
				if ($settings['test_mode'] == 'yes') {					
					$salt = base64_encode(get_home_url());
					$salt = preg_replace( '/[^a-z0-9 ]/i', '',$salt).'-';
				}
			}
		}		
						
		$handle = 'order-' . $salt. $order_id;
		return $handle;
	}
}

new WC_Viapay_Order();

