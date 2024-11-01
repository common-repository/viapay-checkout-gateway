<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class WC_Payment_Gateway_Viapay extends WC_Payment_Gateway
	implements WC_Payment_Gateway_Viapay_Interface
{
	/**
	 * Settle Type options
	 */
	const SETTLE_VIRTUAL = 'online_virtual';
	const SETTLE_PHYSICAL = 'physical';
	const SETTLE_RECURRING = 'recurring';
	const SETTLE_FEE = 'fee';

	/**
	 * Server domain for API request
	 */
	const SERVER_DOMAIN = 'reepay.com';

	/**
	 * Common API Key for testing
	 */
	const COMMON_PRIVATE_KEY_TEST = 'priv_93394db3e8fc30b076d8b5cab7542dc7';	

    /**
     * array for currencies that have different minor units that 100
     * key is currency value is minor units
     * for currencies that doesn't have minor units, value must be 1
     *
     * @var string[]
     */
	private $currency_minor_units = ['ISK' => 1];

	/**
	 * Get parent settings
	 *
	 * @return array
	 */
	public function get_parent_settings() {
		// Load registration data, if found		
		$registration_gateway_settings = get_option( 'woocommerce_viapay_gateway_settings', array());		                       		
		$default_test_private_key = (isset($registration_gateway_settings['private_key_test']))?$registration_gateway_settings['private_key_test']:self::COMMON_PRIVATE_KEY_TEST;		
		if (empty($this->private_key_test) || ($this->private_key_test == self::COMMON_PRIVATE_KEY_TEST)) {
			if (!empty($default_test_private_key)) {
				$this->private_key_test = $default_test_private_key;
			}
		}
		$default_live_private_key = (isset($registration_gateway_settings['private_key']))?$registration_gateway_settings['private_key']:null;
		if (empty($this->private_key)) {
			if (!empty($default_live_private_key)) {
				$this->private_key = $default_live_private_key;
			}
		}

		// Get setting from parent method
		$settings = get_option( 'woocommerce_viapay_checkout_settings' );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}		
		
		return array_merge( array(
			'enabled'                 => 'yes',
			'private_key'             => $this->private_key,
			'private_key_test'        => $this->private_key_test,
			'test_mode'               => $this->test_mode,
			'payment_type'            => $this->payment_type,
			'payment_methods'         => $this->payment_methods,
			'settle'                  => $this->settle,
			'language'                => $this->language,
			'country'                 => $this->country,
			'save_cc'                 => $this->save_cc,
			'debug'                   => $this->debug,
			'logos'                   => $this->logos,
			'logo_height'             => $this->logo_height,
			'skip_order_lines'        => $this->skip_order_lines,
			'enable_order_autocancel' => $this->enable_order_autocancel
		), $settings );
	}

	/**
	 * Check is Capture possible
	 *
	 * @param WC_Order|int $order
	 * @param bool         $amount
	 *
	 * @return bool
	 */
	public function can_capture( $order, $amount = FALSE ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( $order->get_payment_method() !== $this->id ) {
			return false;
		}

		if ( ! $amount ) {
			$amount = $order->get_total();
		}

		try {
			$result = $this->get_invoice_data( $order );
		} catch (Exception $e) {
			return false;
		}
		
		$authorizedAmount = $result['authorized_amount'];
		$settledAmount = $result['settled_amount'];
		//$refundedAmount = $result['refunded_amount'];

		return (
			( $result['state'] === 'authorized' ) || 
			( $result['state'] === 'settled' && $authorizedAmount >= $settledAmount + $amount  )
		);
	}

	/**
	 * Check is Cancel possible
	 *
	 * @param WC_Order|int $order
	 *
	 * @return bool
	 */
	public function can_cancel( $order ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( $order->get_payment_method() !== $this->id ) {
			return false;
		}

		try {
			$result = $this->get_invoice_data( $order );
		} catch (Exception $e) {
			return false;
		}

		// return $result['state'] === 'authorized' || ( $result['state'] === "settled" && $result["settled_amount"] < $result["authorized_amount"] );
		// can only cancel payments when the state is authorized (partly void is not supported yet)
		return ( $result['state'] === 'authorized' );
	}

	/**
	 * @param \WC_Order $order
	 * @param bool      $amount
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function can_refund( $order, $amount = FALSE ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}
		
		// Check if hte order is cancelled - if so - then return as nothing has happened
		if ( $order->get_meta( '_viapay_order_cancelled', true ) === "1" ) {
			return false;
		}

		if ( $order->get_payment_method() !== $this->id ) {
			return false;
		}

		if ( ! $amount ) {
			$amount = $order->get_total();
		}

		try {
			$result = $this->get_invoice_data( $order );
		} catch (Exception $e) {
			return false;
		}

		return $result['state'] === 'settled';
	}

	/**
	 * Capture
	 *
	 * @param WC_Order|int $order
	 * @param bool         $amount
	 *
	 * @throws \Exception
	 * @return void
	 */
	public function capture_payment( $order, $amount = FALSE ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}
		
		// Check if the order is cancelled - if so - then return as nothing has happened
		if ( $order->get_meta( '_viapay_order_cancelled', true ) === "1" ) {
			return;
		}

		if ( ! $amount ) {
			$amount = $order->get_total();
		}

		if ( ! $this->can_capture( $order, $amount ) ) {
			throw new Exception( 'Payment can\'t be captured.' );
		}

		$this->viapay_settle( $order, $amount );
	}

	/**
	 * Cancel
	 *
	 * @param WC_Order|int $order
	 *
	 * @throws \Exception
	 * @return void
	 */
	public function cancel_payment( $order ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}
		
		//
		// Check if hte order is cancelled - if so - then return as nothing has happened
		//
		if ( $order->get_meta( '_viapay_order_cancelled', true ) === "1" ) {
			return;
		}

		if ( ! $this->can_cancel( $order ) ) {
			throw new Exception( 'Payment can\'t be cancelled.' );
		}

		$this->viapay_cancel( $order );
	}

	/**
	 * Refund
	 *
	 * @param WC_Order|int $order
	 * @param bool         $amount
	 * @param string       $reason
	 *
	 * @throws \Exception
	 * @return void
	 */
	public function refund_payment( $order, $amount = false, $reason = '' ) {
	   if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}
		
		// Check if the order is cancelled - if so - then return as nothing has happened
		if ( $order->get_meta( '_viapay_order_cancelled', true ) === "1" ) {
			return;
		}

		if ( ! $this->can_refund( $order, $amount ) ) {
			throw new Exception( 'Payment can\'t be refunded.' );
		}

		$this->viapay_refund( $order, $amount, $reason );
	}

	/**
	 * Assign payment token to order.
	 *
	 * @param WC_Order $order
	 * @param WC_Payment_Token_Viapay|int $token
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public static function assign_payment_token( $order, $token ) {
		if ( is_numeric( $token ) ) {
			$token = new WC_Payment_Token_Viapay( $token );
		} elseif ( ! $token instanceof WC_Payment_Token_Viapay ) {
			throw new Exception( 'Invalid token parameter' );
		}

		if ( $token->get_id() ) {
			// Delete tokens if exist
			delete_post_meta( $order->get_id(), '_payment_tokens' );

			// Reload order
			$order = wc_get_order( $order->get_id() );

			// Add payment token
			$order->add_payment_token( $token );

			update_post_meta( $order->get_id(), '_viapay_token_id', $token->get_id() );
			update_post_meta( $order->get_id(), '_viapay_token', $token->get_token() );
		}
	}

	/**
	 * Save Payment Token
	 *
	 * @param WC_Order $order
	 * @param string $viapay_token
	 *
	 * @return bool|WC_Payment_Token_Viapay
	 *
	 * @throws Exception
	 */
	protected function viapay_save_token( $order, $viapay_token )
	{
		// Check if token is exists in WooCommerce
		$token = self::get_payment_token( $viapay_token );
		if ( ! $token ) {
			// Create Payment Token
			$token = $this->add_payment_token( $order, $viapay_token );
		}

		// Assign token to order
		self::assign_payment_token( $order, $token );

		return $token;
	}

	/**
	 * Add Payment Token.
	 *
	 * @param WC_Order $order
	 * @param string $viapay_token
	 *
	 * @return bool|WC_Payment_Token_Viapay
	 * @throws Exception
	 */
	public function add_payment_token( $order, $viapay_token ) {
		// Create Payment Token
		$customer_handle = $this->get_customer_handle_order( $order->get_id() );
		$source = $this->get_viapay_cards( $customer_handle, $viapay_token );
		if ( ! $source ) {
			throw new Exception('Unable to retrieve customer payment methods');
		}

		$expiryDate = explode( '-', $source['exp_date'] );

		// Initialize Token
		$token = new WC_Payment_Token_Viapay();
		$token->set_gateway_id( $this->id );
		$token->set_token( $viapay_token );
		$token->set_last4( substr( $source['masked_card'], -4 ) );
		$token->set_expiry_year( 2000 + $expiryDate[1] );
		$token->set_expiry_month( $expiryDate[0] );
		$token->set_card_type( $source['card_type'] );
		$token->set_user_id( $order->get_customer_id() );
		$token->set_masked_card( $source['masked_card'] );

		// Save Credit Card
		if ( ! $token->save() ) {
			throw new Exception( __( 'There was a problem adding the card.', 'viapay-checkout-gateway' ) );
		}

		update_post_meta( $order->get_id(), '_viapay_source', $source );
		$this->log( sprintf( '%s::%s Payment token #%s created for %s',
			__CLASS__,
			__METHOD__,
			$token->get_id(),
			$source['masked_card']
		) );

		return $token;
	}

	/**
	 * Get payment token.
	 * @deprecated
	 *
	 * @param WC_Order $order
	 *
	 * @return WC_Payment_Token_Viapay|false
	 */
	public static function retrieve_payment_token_order( $order ) {
		try {
			$tokens = $order->get_payment_tokens();
		} catch ( Exception $e ) {
			return false;
		}

		foreach ( $tokens as $token_id ) {
			try {
				$token = new WC_Payment_Token_Viapay( $token_id );
			} catch ( Exception $e ) {
				return false;
			}

			if ( ! $token->get_id() ) {
				continue;
			}

			if ( ! in_array( $token->get_gateway_id(), WC_ViapayCheckout::PAYMENT_METHODS, true ) ) {
				continue;
			}

			return $token;
		}

		return false;
	}

	/**
	 * Get payment token.
	 *
	 * @param WC_Order $order
	 *
	 * @return WC_Payment_Token_Viapay|false
	 */
	public static function get_payment_token_order( $order ) {
		$viapay_token = get_post_meta( $order->get_id(), '_viapay_token', true );
		if ( empty( $viapay_token ) ) {
			return false;
		}

		return self::get_payment_token( $viapay_token );
	}

	/**
	 * Get Payment Token by Token string.
	 *
	 * @param string $viapay_token
	 *
	 * @return WC_Payment_Token_Viapay|false
	 */
	public static function get_payment_token( $viapay_token ) {
		global $wpdb;

		$query = "SELECT token_id FROM {$wpdb->prefix}woocommerce_payment_tokens WHERE token = '%s';";
		$token_id = $wpdb->get_var( $wpdb->prepare( $query, $viapay_token ) );
		if ( ! $token_id ) {
			return false;
		}

		return WC_Payment_Tokens::get( $token_id );
	}

	/**
	 * Request
	 * @param $method
	 * @param $url
	 * @param array $params
	 * @return array|mixed|object
	 * @throws Exception
	 */
	public function request($method, $url, $params = array()) {
		$start = microtime(true);
		if ( $this->debug === 'yes' ) {
			$this->log( sprintf('Request: %s %s %s', $method, $url, json_encode( $params, JSON_PRETTY_PRINT ) ) );
		}

		$key = $this->test_mode === 'yes' ? $this->private_key_test : $this->private_key;
		$method = strtoupper($method);

		$username = $key;
      	$password = '';
      	$auth = base64_encode( $username . ':' . $password ); 		

		$headers = [
			//'Accept' => 'application/json',
			'Content-Type' => 'application/json',
			'User-Agent' => 'Viapay',
			'Authorization' => 'Basic '.$auth
		];

		$args = array(
			'method' => $method,            
			'headers' => $headers,
			'httpversion' => '1.1',
			'sslverify' => false,
			'timeout' => 60
		);

		if (!empty($params)) {
			$args['body'] = json_encode($params);
		}

		switch ($method) {
		case 'GET':          
			$data = wp_remote_get($url, $args);                    
			break;

		case 'POST':
		case 'PUT':  
		default:          
			$data = wp_remote_post($url, $args);
			break;   
		}    

		if (is_wp_error($data)) {        
			$error_msg = $data->get_error_message();   
			throw new Exception($error_msg); 
		} else if (is_array($data)) { 
			$code = null;
			$http_code = null;
			if (isset($data['response'])) {
				if (isset($data['response']['code'])) {
				$http_code = (int) $data['response']['code'];
				$code = (int) ($http_code / 100);
				}
			}
			
			$body = wp_remote_retrieve_body( $data );

			if ( $this->debug === 'yes' && $code) {
				$time = microtime(true) - $start;
				$this->log( sprintf( '[%.4F] HTTP Code: %s. Response: %s', $time, $http_code, $body ) );
			}
			
			switch ($code) {
				case 1:
					$error_msg = sprintf('Invalid HTTP Code: %s', $http_code);
					throw new Exception($error_msg);
				break;
				case 2:
				case 3:
					return json_decode($body, true);				
				break;
				case 4:
				case 5:				
					if ( mb_strpos( $body, 'Request rate limit exceeded', 0, 'UTF-8' ) !== false ) {
						global $request_retry;
						if ($request_retry) {
							throw new Exception( 'Viapay: Request rate limit exceeded' );
						}

						sleep(10);
						$request_retry = true;
						$result = $this->request($method, $url, $params);
						$request_retry = false;

						return  $result;
					}
					
					$error_obj = json_decode($body, true);
					if (!empty($error_obj)) {				
						$error_obj_str = print_r($error_obj, true);
						$error_msg = sprintf('Error #%s : %s', $error_obj['code'], $error_obj['error'] );
					} else {
						$error_msg = sprintf('API Error (request): %s. HTTP Code: %s', $body, $http_code);	
					}				

					if (($http_code == 422)&&($this->test_mode === 'yes')) {
						$params_str = print_r($error_obj, true);
						$error_msg = "The selected payment method is not available when test mode is enabled. You can use it only with a live account.";
					}				

					throw new Exception($error_msg);

					break;
				default:			  	
					if (!empty($code)) {
						$error_msg = sprintf('Invalid HTTP Code: %s', $http_code); 	
						throw new Exception($error_msg);				  
					} else {
						$error_msg = 'Invalid HTTP Code.';
						throw new Exception($error_msg);
					}            
					break;
			}        
		} else {
			$error_msg = 'Unknown error - Please try again.';
			throw new Exception($error_msg);
		}    			
	}

	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level   Optional. Default 'info'.
	 *     emergency|alert|critical|error|warning|notice|info|debug
	 *
	 * @see WC_Log_Levels
	 *
	 * @return void
	 */
	protected function log( $message, $level = 'info' ) {
		// Is Enabled
		if ( $this->debug !== 'yes' ) {
			return;
		}

		// Get Logger instance
		$logger = wc_get_logger();

		// Write message to log
		if ( ! is_string( $message ) ) {
			$message = var_export( $message, TRUE );
		}

		$logger->log( $level, $message, array(
			'source'  => $this->id,
			'_legacy' => TRUE
		) );
	}

	/**
	 * Process Refund
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund
	 * a passed in amount.
	 *
	 * @param  int    $order_id
	 * @param  float  $amount
	 * @param  string $reason
	 *
	 * @return  bool|wp_error True or false based on success, or a WP_Error object
	 */
	public function process_refund( $order_id, $amount = NULL, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return FALSE;
		}

		// Full Refund
		if ( is_null( $amount ) ) {
			$amount = $order->get_total();
		}

		try {
			$this->refund_payment( $order, $amount, $reason );

			return TRUE;
		} catch ( \Exception $e ) {
			return new WP_Error( 'refund', $e->getMessage() );
		}
	}

	/**
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public function get_order_items($order) {
		$pricesIncludeTax = wc_prices_include_tax();

		$items = [];
		foreach ( $order->get_items() as $order_item ) {
			/** @var WC_Order_Item_Product $order_item */
			$price        = $order->get_line_subtotal( $order_item, FALSE, FALSE );
			$priceWithTax = $order->get_line_subtotal( $order_item, TRUE, FALSE );
			$tax          = $priceWithTax - $price;
			$taxPercent   = ( $tax > 0 ) ? round( 100 / ( $price / $tax ) ) : 0;
			$unitPrice    = round( ( $pricesIncludeTax ? $priceWithTax : $price ) / $order_item->get_quantity(), 2 );

			$items[] = array(
				'ordertext' => $order_item->get_name(),
				'quantity'  => $order_item->get_quantity(),
				'amount'    => $this->prepare_amount($unitPrice, $order->get_currency()),
				'vat'       => round($taxPercent / 100, 2),
				'amount_incl_vat' => $pricesIncludeTax
			);
		}

		// Add Shipping Line
		if ( (float) $order->get_shipping_total() > 0 ) {
			$shipping        = $order->get_shipping_total();
			$tax             = $order->get_shipping_tax();
			$shippingWithTax = $shipping + $tax;
			$taxPercent      = ( $tax > 0 ) ? round( 100 / ( $shipping / $tax ) ) : 0;

			$items[] = array(
				'ordertext' => $order->get_shipping_method(),
				'quantity'  => 1,
				'amount'    => $this->prepare_amount($pricesIncludeTax ? $shippingWithTax : $shipping, $order->get_currency()),
				'vat'       => round($taxPercent / 100, 2),
				'amount_incl_vat' => $pricesIncludeTax
			);
		}

		// Add fee lines
		foreach ( $order->get_fees() as $order_fee ) {
			/** @var WC_Order_Item_Fee $order_fee */
			$fee        = $order_fee->get_total();
			$tax        = $order_fee->get_total_tax();
			$feeWithTax = $fee + $tax;
			$taxPercent = ( $tax > 0 ) ? round( 100 / ( $fee / $tax ) ) : 0;

			$items[] = array(
				'ordertext' => $order_fee->get_name(),
				'quantity'  => 1,
				'amount'    => $this->prepare_amount( $pricesIncludeTax ? $feeWithTax : $fee, $order->get_currency()),
				'vat'       => round($taxPercent / 100, 2),
				'amount_incl_vat' => $pricesIncludeTax
			);
		}

		// Add discount line
		if ( $order->get_total_discount( FALSE ) > 0 ) {
			$discount        = $order->get_total_discount( TRUE );
			$discountWithTax = $order->get_total_discount( FALSE );
			$tax             = $discountWithTax - $discount;
			$taxPercent      = ( $tax > 0 ) ? round( 100 / ( $discount / $tax ) ) : 0;

			$items[] = array(
				'ordertext' => __( 'Discount', 'viapay-checkout-gateway' ),
				'quantity'  => 1,
				'amount'    => round(-1 * $this->prepare_amount($pricesIncludeTax ? $discountWithTax : $discount, $order->get_currency())),
				'vat'       => round($taxPercent / 100, 2),
				'amount_incl_vat' => $pricesIncludeTax
			);
		}

		// Add "Gift Up!" discount
		if ( defined( 'GIFTUP_ORDER_META_CODE_KEY' ) &&
		     defined( 'GIFTUP_ORDER_META_REQUESTED_BALANCE_KEY' )
		) {
			if ( $order->meta_exists(GIFTUP_ORDER_META_CODE_KEY) ) {
				$code              = $order->get_meta( GIFTUP_ORDER_META_CODE_KEY );
				$requested_balance = $order->get_meta( GIFTUP_ORDER_META_REQUESTED_BALANCE_KEY );

				if ( $requested_balance > 0 ) {
					$items[] = array(
						'ordertext' => sprintf( __( 'Gift card (%s)', 'viapay-checkout-gateway' ), $code ),
						'quantity'  => 1,
						'amount'    =>  $this->prepare_amount(-1 * $requested_balance, $order->get_currency()),
						'vat'       => 0,
						'amount_incl_vat' => $pricesIncludeTax
					);
				}
			}
		}

		return $items;
	}

	/**
	 * Calculate the amount to be settled instantly based by the order items.
	 *
	 * @param WC_Order $order - is the WooCommerce order object
	 * @return stdClass
	 */
	public function calculate_instant_settle( $order ) {
		$onlineVirtual = false;
		$recurring = false;
		$physical = false;
		$total = 0;
		$debug = [];

		// Now walk through the order-lines and check per order if it is virtual, downloadable, recurring or physical
		foreach ( $order->get_items() as $item ) {
			/** @var WC_Order_Item_Product $order_item */
			/** @var WC_Product $product */
			$product = $item->get_product();
			$priceWithTax = $order->get_line_subtotal( $item, true, false );

			if ( in_array(self::SETTLE_PHYSICAL, $this->settle, true ) &&
			     ( ! self::wcs_is_subscription_product( $product ) &&
			       $product->needs_shipping() &&
			       ! $product->is_downloadable() )
			) {
				$debug[] = [
					'product' => $product->get_id(),
					'name'    => $item->get_name(),
					'price'   => $priceWithTax,
					'type'    => self::SETTLE_PHYSICAL
				];

				$physical = true;
				$total += $priceWithTax;

				continue;
			} elseif ( in_array(self::SETTLE_VIRTUAL, $this->settle, true ) &&
			     ( ! self::wcs_is_subscription_product( $product ) &&
			       ( $product->is_virtual() || $product->is_downloadable() ) )
			) {
				$debug[] = [
					'product' => $product->get_id(),
					'name'    => $item->get_name(),
					'price'   => $priceWithTax,
					'type'    => self::SETTLE_VIRTUAL
				];

				$onlineVirtual = true;
				$total += $priceWithTax;

				continue;
			} elseif ( in_array(self::SETTLE_RECURRING, $this->settle, true ) &&
			     self::wcs_is_subscription_product( $product )
			) {
				$debug[] = [
					'product' => $product->get_id(),
					'name'    => $item->get_name(),
					'price'   => $priceWithTax,
					'type'    => self::SETTLE_RECURRING
				];

				$recurring = true;
				$total += $priceWithTax;

				continue;
			}
		}

		// Add Shipping Total
		if ( in_array(self::SETTLE_PHYSICAL, $this->settle ) ) {
			if ( (float) $order->get_shipping_total() > 0 ) {
				$shipping = (float) $order->get_shipping_total();
				$tax = (float) $order->get_shipping_tax();
				$total += ($shipping + $tax);

				$debug[] = [
					'product' => $order->get_shipping_method(),
					'price' => ($shipping + $tax),
					'type' => self::SETTLE_PHYSICAL
				];

				$physical = true;
			}
		}

		// Add fees
		if ( in_array(self::SETTLE_FEE, $this->settle ) ) {
			foreach ( $order->get_fees() as $order_fee ) {
				/** @var WC_Order_Item_Fee $order_fee */
				$fee        = (float) $order_fee->get_total();
				$tax        = (float) $order_fee->get_total_tax();
				$total += ($fee + $tax);

				$debug[] = [
					'product' => $order_fee->get_name(),
					'price' => ($fee + $tax),
					'type' => self::SETTLE_FEE
				];
			}
		}

		// Add discounts
		if ( $order->get_total_discount( false ) > 0 ) {
			$discountWithTax = (float) $order->get_total_discount( false );
			$total -= $discountWithTax;

			$debug[] = [
				'product' => 'discount',
				'price' => -1 * $discountWithTax,
				'type' => 'discount'
			];

			if ($total < 0) {
				$total = 0;
			}
		}

		$result = new stdClass();
		$result->is_instant_settle = $onlineVirtual || $physical || $recurring;
		$result->settle_amount = $total;
		$result->debug = $debug;
		$result->settings = $this->settle;

		return $result;
	}

	/**
	 * Settle a payment instantly.
	 *
	 * @param WC_Order $order
	 *
	 * @return void
	 */
	public function process_instant_settle( $order ) {
		if ( ! empty ( $order->get_meta('_is_instant_settled' ) ) ) {
			return;
		}

		// Calculate if the order is to be instant settled
		$instant_settle = $this->calculate_instant_settle( $order );
		$toSettle = $instant_settle->settle_amount;
		$this->log( sprintf( '%s::%s instant-settle-array calculated %s', __CLASS__, __METHOD__, var_export( $instant_settle, true ) ) );

		if ( $toSettle >= 0.001 ) {
			try {
				$this->viapay_settle( $order, $toSettle );

				$order->add_meta_data('_is_instant_settled', '1');
				$order->save_meta_data();
			} catch ( Exception $e ) {
				$this->log( sprintf( '%s::%s Error: %s', __CLASS__, __METHOD__, $e->getMessage() ) );
			}
		}
	}

	/**
	 * Get Customer Cards from Viapay
	 * @param string $customer_handle
	 * @param string|null $viapay_token
	 *
	 * @return array|false
	 * @throws Exception
	 */
	public function get_viapay_cards($customer_handle, $viapay_token = null) {
		$request_url = $this->getURL('customer');
		$result = $this->request( 'GET', $request_url . '/'. $customer_handle . '/payment_method' );
		if ( ! isset( $result['cards'] ) ) {
			throw new Exception('Unable to retrieve customer payment methods');
		}

		if ( ! $viapay_token ) {
			return $result['cards'];
		}

		$cards = $result['cards'];
		foreach ($cards as $card) {
			if ( $card['id'] === $viapay_token && $card['state'] === 'active' ) {
				return $card;
			}
		}

		return false;
	}

	/**
	 * Checks an order to see if it contains a subscription.
	 * @see wcs_order_contains_subscription()
	 *
	 * @param WC_Order $order
	 *
	 * @return bool
	 */
	public static function order_contains_subscription( $order ) {
		if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {
			return FALSE;
		}

		return wcs_order_contains_subscription( $order );
	}

	/**
	 * Checks if there's Subscription Product.
	 *
	 * @param WC_Product $product
	 *
	 * @return bool
	 */
	public static function wcs_is_subscription_product( $product ) {
		return class_exists( 'WC_Subscriptions_Product', false ) &&
		       WC_Subscriptions_Product::is_subscription( $product );
	}

	/**
	 * WC Subscriptions: Is Payment Change
	 * @return bool
	 */
	public static function wcs_is_payment_change() {
		return class_exists( 'WC_Subscriptions_Change_Payment_Gateway', FALSE ) &&
			   WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment;
	}

	/**
	 * Check is Cart have Subscription Products
	 * @return bool
	 */
	public static function wcs_cart_have_subscription() {
		if ( ! class_exists( 'WC_Subscriptions_Product' ) ) {
			return false;
		}

		// Check is Recurring Payment
		$cart = WC()->cart->get_cart();
		foreach ( $cart as $key => $item ) {
			if ( is_object( $item['data'] ) && WC_Subscriptions_Product::is_subscription( $item['data'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get Customer handle by User ID.
	 *
	 * @param $user_id
	 *
	 * @return string
	 */
	public function get_customer_handle( $user_id ) {

		if ( ! $user_id ) {
			// Workaround: Allow to pay exist orders by guests
			if ( isset( $_GET['pay_for_order'], $_GET['key'] ) ) {
				if ( $order_id = wc_get_order_id_by_order_key( wc_clean($_GET['key']) ) ) {
					$order = wc_get_order( $order_id );

					// Get customer handle by order
					$handle = $this->get_customer_handle_online( $order );
					if ( $handle ) {
						return $handle;
					}
				}
            }
		}

		$handle = get_user_meta( $user_id, 'viapay_customer_id', TRUE );
		if ( empty( $handle ) ) {
			$handle = 'customer-' . $user_id;
			update_user_meta( $user_id, 'viapay_customer_id', $handle );
		}

		return $handle;
	}

	/**
	 * Get Customer handle by Order ID.
	 *
	 * @param $order_id
	 *
	 * @return string
	 */
	public function get_customer_handle_order( $order_id ) {
		$order = wc_get_order( $order_id );

        $handle = $this->get_customer_handle_online( $order );

        if(empty($handle)) {
            if( $order->get_customer_id() > 0 ) {
                $handle = 'customer-' . $order->get_customer_id();
            }
        }

        $order->add_meta_data( '_viapay_customer', $handle );
        $order->save_meta_data();


        return $handle;
	}

	/**
	 * Get Customer handle by order online.
	 *
	 * @param WC_Order $order
	 *
	 * @return false|string
	 */
	public function get_customer_handle_online( $order ) {
		// Get customer handle by order
		$handle = $this->get_order_handle( $order );

		$result = get_transient( 'viapay_invoice_' . $handle );

		if ( ! $result ) {
			try {
				$result = $this->get_invoice_by_handle( wc_clean( $handle ) );
				set_transient( 'viapay_invoice_' . $handle, $result, 5 * MINUTE_IN_SECONDS );
			} catch (Exception $e) {

				return null;
			}
		}

		if ( is_array( $result ) && isset( $result['customer'] ) ) {
			return $result['customer'];
		}

		return null;
	}

	/**
	 * @param $handle
	 *
	 * @return bool|int
	 */
	public function get_userid_by_handle( $handle ) {
		if ( strpos( $handle, 'guest-' ) !== false ) {
			return 0;
		}

		$users = get_users( array(
			'meta_key' => 'viapay_customer_id',
			'meta_value' => $handle,
			'number' => 1,
			'count_total' => false
		) );
		if ( count( $users ) > 0 ) {
			$user = array_shift( $users );
			return $user->ID;
		}

		return false;
	}

	/**
	 * Get Language
	 * @return string
	 */
	public function get_language() {
		if ( ! empty( $this->language ) ) {
			return $this->language;
		}

		$locale = get_locale();
		if ( in_array(
			$locale,
			array('en_US', 'da_DK', 'sv_SE', 'no_NO', 'de_DE', 'es_ES', 'fr_FR', 'it_IT', 'nl_NL')
		) ) {
			return $locale;
		}

		return 'en_US';
	}

	/**
	 * Process the result of Charge request.
	 *
	 * @param WC_Order $order
	 * @param array $result
	 *
	 * @throws Exception
	 */
	public function process_charge_result( $order, array $result )
	{
		// @todo Check $result['processing']
		// @todo Check $result['authorized_amount']
		// @todo Check state $result['state']

		// Check results
		switch ( $result['state'] ) {
			case 'pending':
				// @todo
				break;
			case 'authorized':
				WC_Viapay_Order_Statuses::set_authorized_status(
					$order,
					sprintf(
						__( 'Payment has been authorized. Amount: %s. Transaction: %s', 'viapay-checkout-gateway' ),
						wc_price( $this->make_initial_amount( $result['amount'], $order->get_currency())),
						$result['transaction']
					),
					$result['transaction']
				);

				// Settle an authorized payment instantly if possible
				$this->process_instant_settle( $order );

				break;
			case 'settled':
				update_post_meta( $order->get_id(), '_viapay_capture_transaction', $result['transaction'] );

				WC_Viapay_Order_Statuses::set_settled_status(
					$order,
					sprintf(
						__( 'Payment has been settled. Amount: %s. Transaction: %s', 'viapay-checkout-gateway' ),
						wc_price($this->make_initial_amount($result['amount'], $order->get_currency())),
						$result['transaction']
					),
					$result['transaction']
				);

				break;
			case 'cancelled':
				update_post_meta( $order->get_id(), '_viapay_cancel_transaction', $result['transaction'] );

				if ( ! $order->has_status('cancelled') ) {
					$order->update_status( 'cancelled', __( 'Payment has been cancelled.', 'viapay-checkout-gateway' ) );
				} else {
					$order->add_order_note( __( 'Payment has been cancelled.', 'viapay-checkout-gateway' ) );
				}

				break;
			case 'failed':
				throw new Exception( 'Cancelled' );
			default:
				throw new Exception( 'Generic error' );
		}
	}

	/**
	 * Get Invoice data of Order.
	 *
	 * @param WC_Order $order
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_invoice_data( $order ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( $order->get_payment_method() !== $this->id ) {
			throw new Exception('Unable to get invoice data.');
		}

		$order_data = $this->get_invoice_by_handle( $this->get_order_handle( $order ) );

		return array_merge( array(
			'authorized_amount' => 0,
			'settled_amount'    => 0,
			'refunded_amount'   => 0
		), $order_data );
	}

	/**
	 * Get Invoice data by handle.
	 *
	 * @param string $handle
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_invoice_by_handle( $handle ) {
		try {
			$request_url = $this->getURL('invoice');
			$result = $this->request( 'GET', $request_url. '/' . $handle );
			//$this->log( sprintf( '%s::%s Invoice data %s', __CLASS__, __METHOD__, var_export( $result, true ) ) );

			return $result;
		} catch (Exception $e) {
			//$this->log( sprintf( '%s::%s API Error: %s', __CLASS__, __METHOD__, var_export( $e->getMessage(), true ) ) );

			throw $e;
		}
	}

	/**
	 * Charge payment.
	 *
	 * @param WC_Order $order
	 * @param string $token
	 * @param float|null $amount
	 *
	 * @return mixed Returns true if success
	 */
	public function viapay_charge( $order, $token, $amount = null )
	{
		// @todo Use order lines instead of amount
		try {
			$params = [
				'handle' => $this->get_order_handle( $order ),
				'amount' => $this->prepare_amount($amount, $order->get_currency()) ,
				'currency' => $order->get_currency(),
				'source' => $token,
				// 'settle' => $instantSettle,
				'recurring' => $this->order_contains_subscription( $order ),
				'customer' => [
					'test' => $this->test_mode === 'yes',
					'handle' => $this->get_customer_handle_order( $order->get_id() ),
					'email' => $order->get_billing_email(),
					'address' => $order->get_billing_address_1(),
					'address2' => $order->get_billing_address_2(),
					'city' => $order->get_billing_city(),
					'country' => $order->get_billing_country(),
					'phone' => $order->get_billing_phone(),
					'company' => $order->get_billing_company(),
					'vat' => '',
					'first_name' => $order->get_billing_first_name(),
					'last_name' => $order->get_billing_last_name(),
					'postal_code' => $order->get_billing_postcode()
				],
				//'order_lines' => $this->get_order_items( $order ),
				'billing_address' => [
					'attention' => '',
					'email' => $order->get_billing_email(),
					'address' => $order->get_billing_address_1(),
					'address2' => $order->get_billing_address_2(),
					'city' => $order->get_billing_city(),
					'country' => $order->get_billing_country(),
					'phone' => $order->get_billing_phone(),
					'company' => $order->get_billing_company(),
					'vat' => '',
					'first_name' => $order->get_billing_first_name(),
					'last_name' => $order->get_billing_last_name(),
					'postal_code' => $order->get_billing_postcode(),
					'state_or_province' => $order->get_billing_state()
				],
			];

			if ($order->needs_shipping_address()) {
				$params['shipping_address'] = [
					'attention' => '',
					'email' => $order->get_billing_email(),
					'address' => $order->get_shipping_address_1(),
					'address2' => $order->get_shipping_address_2(),
					'city' => $order->get_shipping_city(),
					'country' => $order->get_shipping_country(),
					'phone' => $order->get_billing_phone(),
					'company' => $order->get_shipping_company(),
					'vat' => '',
					'first_name' => $order->get_shipping_first_name(),
					'last_name' => $order->get_shipping_last_name(),
					'postal_code' => $order->get_shipping_postcode(),
					'state_or_province' => $order->get_shipping_state()
				];
			}
			$request_url = $this->getURL('charge');
			$result = $this->request('POST', $request_url, $params);
			$this->log( sprintf( '%s::%s Charge: %s', __CLASS__, __METHOD__, var_export( $result, true) ) );

			$this->process_charge_result( $order, $result );
		} catch (Exception $e) {
			if ( mb_strpos( $e->getMessage(), 'Invoice already settled', 0, 'UTF-8') !== false ) {
				$order->payment_complete();
				$order->add_order_note( __( 'Transaction is already settled.', 'viapay-checkout-gateway' ) );

				return true;
			}

			$order->update_status( 'failed' );
			$order->add_order_note(
				sprintf( __( 'Failed to charge "%s". Error: %s. Token ID: %s', 'viapay-checkout-gateway' ),
					wc_price( $amount ),
					$e->getMessage(),
					$token
				)
			);

			return $e->getMessage();
		}

		return true;
	}

	/**
	 * Settle the payment online.
	 *
	 * @param WC_Order $order
	 * @param float|int|null $amount
	 *
	 * @return void
	 * @throws Exception
	 */
	public function viapay_settle( $order, $amount = null ) {
		$handle = $this->get_order_handle( $order );
		if ( empty( $handle ) ) {
			throw new Exception( 'Unable to get order handle' );
		}

		if ( ! $amount ) {
			$amount = $this->calculate_instant_settle( $order );
		}

		if ( $amount > 0 ) {
			try {
				$request_url = $this->getURL('charge');
				$result = $this->request( 'POST', $request_url. '/'. $handle  . '/settle', array (
					'amount' => $this->prepare_amount($amount, $order->get_currency())
				));

				$this->log( sprintf( '%s::%s Settle Charge: %s', __CLASS__, __METHOD__, var_export( $result, true) ) );

				if ( 'failed' === $result['state'] ) {
					throw new Exception( 'Settle has been failed.' );
				}
			} catch (Exception $e) {
				// Workaround: Check if the invoice has been settled before to prevent "Invoice already settled"
				if ( mb_strpos( $e->getMessage(), 'Invoice already settled', 0, 'UTF-8') !== false ) {
					return;
				}

				$this->log( sprintf( '%s::%s API Error: %s', __CLASS__, __METHOD__, var_export( $e->getMessage(), true ) ) );

				// need to be shown on admin notices
                $error = sprintf( __( 'Failed to settle "%s". Error: %s.', 'viapay-checkout-gateway' ),
                    wc_price( $amount ),
                    $this->extract_api_error($e->getMessage())
                );


                set_transient( 'viapay_api_action_error', $error, MINUTE_IN_SECONDS / 2);


				$order->add_order_note( sprintf( __( 'Failed to settle "%s". Error: %s.', 'viapay-checkout-gateway' ),
					wc_price( $amount ),
                    $this->extract_api_error($e->getMessage())
				) );

				return;
			}

			// @todo Check $result['processing']
			// @todo Check $result['authorized_amount']
			// @todo Check state $result['state']

			// Set transaction Id
			$order->set_transaction_id( $result['transaction'] );
			$order->save();

			update_post_meta( $order->get_id(), '_viapay_capture_transaction', $result['transaction']);


			$success = sprintf(
                __( 'Payment has been settled. Amount: %s. Transaction: %s', 'viapay-checkout-gateway' ),
                wc_price( $amount ),
                $result['transaction']
            );

			// Add order note
			$order->add_order_note(
                $success
			);

            set_transient( 'viapay_api_action_success', $success, MINUTE_IN_SECONDS / 2);

			// Check the amount and change the order status to settled if needs
			try {
				$result = $this->get_invoice_data( $order );

				if ( $result['authorized_amount'] === $result['settled_amount'] ) {
					WC_Viapay_Order_Statuses::set_settled_status( $order );
				}
			} catch (Exception $e) {
				// Silence is golden
			}
		}
	}

	/**
	 * Cancel the payment online.
	 *
	 * @param WC_Order $order
	 *
	 * @throws Exception
	 */
	public function viapay_cancel( $order ) {
		$handle = $this->get_order_handle( $order );
		if ( empty( $handle ) ) {
			throw new Exception( 'Unable to get order handle' );
		}

		try {
			$request_url = $this->getURL('charge');
			$result = $this->request( 'POST', $request_url. '/' . $handle  . '/cancel' );
		} catch ( Exception $e ) {

		    $error = sprintf( __( 'Failed to cancel the payment. Error: %s.', 'viapay-checkout-gateway' ),
                $this->extract_api_error($e->getMessage())
            );

            $order->add_order_note( $error);

            set_transient( 'viapay_api_action_error', $error, MINUTE_IN_SECONDS / 2);

            exit();
		}

		update_post_meta( $order->get_id(), '_viapay_cancel_transaction', $result['transaction'] );
		update_post_meta( $order->get_id(), '_transaction_id', $result['transaction'] );

		if ( ! $order->has_status('cancelled') ) {
			$success = __( 'Payment has been cancelled.', 'viapay-checkout-gateway' );
		    $order->update_status( 'cancelled', __( 'Payment has been cancelled.', 'viapay-checkout-gateway' ) );

            $order->add_order_note( $success );
            set_transient( 'viapay_api_action_success', $success, MINUTE_IN_SECONDS / 2);
		}

	}

	/**
	 * Refund the payment online.
	 *
	 * @param WC_Order $order
	 * @param float|int|null $amount
	 * @param string|null $reason
	 *
	 * @return void
	 * @throws Exception
	 */
	public function viapay_refund( $order, $amount = null, $reason = null ) {
		$handle = $this->get_order_handle( $order );
		if ( empty( $handle ) ) {
			throw new Exception( 'Unable to get order handle' );
		}

		if ( ! $amount ) {
			$amount = $order->get_total();
		}

		try {
			$params = [
				'invoice' => $handle,
				'amount' => $this->prepare_amount($amount, $order->get_currency()),
			];
			$request_url = $this->getURL('refund');
			$result = $this->request( 'POST', $request_url, $params );
		} catch ( Exception $e ) {

  		    $error = sprintf( __( 'Failed to refund "%s". Error: %s.', 'viapay-checkout-gateway' ),
                wc_price( $amount ),
                $this->extract_api_error($e->getMessage()));


            set_transient( 'viapay_api_action_error', $error, MINUTE_IN_SECONDS / 2);


            $this->log( sprintf( '%s::%s API Error: %s', __CLASS__, __METHOD__, var_export( $e->getMessage(), true ) ) );


            $order->add_order_note( $error );


            if ('woocommerce_refund_line_items' == trim(sanitize_text_field($_POST['action']))) {
                throw new Exception($this->extract_api_error($e->getMessage()));
            }

            return;
        }

		// Save Credit Note ID
		$credit_note_ids = get_post_meta( $order->get_id(), '_viapay_credit_note_ids', TRUE );
		if ( ! is_array( $credit_note_ids ) ) {
			$credit_note_ids = array();
		}

		array_push($credit_note_ids, $result['credit_note_id']);

		update_post_meta( $order->get_id(), '_viapay_credit_note_ids', $credit_note_ids );

        $success = sprintf( __( 'Refunded: %s. Credit Note Id #%s. Reason: %s', 'viapay-checkout-gateway' ),
            wc_price( $amount ),
            $result['credit_note_id'],
            $reason
        );

		$order->add_order_note( $success );

        set_transient( 'viapay_api_action_success', $success, MINUTE_IN_SECONDS / 2);

	}

	/**
	 * Converts a Viapay card_type into a logo.
	 *
	 * @param string $card_type is the Viapay card type
	 *
	 * @return string the logo
	 */
	public function get_logo( $card_type ) {
		switch ( $card_type ) {
			case 'visa':
				$image = 'visa.png';
				break;
			case 'mc':
				$image = 'mastercard.png';
				break;
			case 'dankort':
			case 'visa_dk':
				$image = 'dankort.png';
				break;
			case 'ffk':
				$image = 'forbrugsforeningen.png';
				break;
			case 'visa_elec':
				$image = 'visa-electron.png';
				break;
			case 'maestro':
				$image = 'maestro.png';
				break;
			case 'amex':
				$image = 'american-express.png';
				break;
			case 'diners':
				$image = 'diners.png';
				break;
			case 'discover':
				$image = 'discover.png';
				break;
			case 'jcb':
				$image = 'jcb.png';
				break;
			case 'mobilepay':
				$image = 'mobilepay.png';
				break;
			case 'viabill':
				$image = 'viabill.png';
				break;
			case 'klarna_pay_later':
			case 'klarna_pay_now':
				$image = 'klarna.png';
				break;
			case 'resurs':
				$image = 'resurs.png';
				break;
			case 'china_union_pay':
				$image = 'cup.png';
				break;
			case 'paypal':
				$image = 'paypal.png';
				break;
			case 'applepay':
				$image = 'applepay.png';
				break;
            case 'googlepay':
                $image = 'googlepay.png';
                break;
            case 'vipps':
                $image = 'vipps.png';
                break;
      		default:
				//$image = 'viapay.png';
				// Use an image of payment method
				$logos = $this->logos;
				$logo = array_shift( $logos );

				return untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../../assets/images/' . $logo . '.png';
		}

		return untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../../assets/images/' . $image;
	}

	/**
	 * Get Order By Viapay Order Handle.
	 *
	 * @param string $handle
	 *
	 * @return false|WC_Order
	 * @throws Exception
	 */
	public function get_order_by_handle( $handle ) {
		global $wpdb;

		$query = "
			SELECT post_id FROM {$wpdb->prefix}postmeta 
			LEFT JOIN {$wpdb->prefix}posts ON ({$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id)
			WHERE meta_key = %s AND meta_value = %s;";
		$sql = $wpdb->prepare( $query, '_viapay_order', $handle );
		$order_id = $wpdb->get_var( $sql );
		if ( ! $order_id ) {
			throw new Exception( sprintf( 'Invoice #%s isn\'t exists in store.', $handle ) );
		}

		// Get Order
		clean_post_cache( $order_id );
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			throw new Exception( sprintf( 'Order #%s isn\'t exists in store.', $order_id ) );
		}

		return $order;
	}

	/**
	 * Get Viapay Order Handle.
	 *
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	public function get_order_handle( $order ) {
		$handle = $order->get_meta( '_viapay_order' );
		if ( empty( $handle ) ) {			
			$handle = $this->generateHandleByOrderId($order->get_order_number());
			$order->update_meta_data( '_viapay_order', $handle );
			$order->save_meta_data();
		}

		return $handle;
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

    /**
     * Extracting error from returning json string from api in case of api error
     *
     * @param $error_message
     * @return mixed|null
     */
	private function extract_api_error($error_message) {
        preg_match('/{.*}/',$error_message, $matches);
        if(isset($matches[0])) {
            $json_error_description = json_decode($matches[0], true);
            return isset( $json_error_description['error'] ) ? $json_error_description['error'] : null;
        }
    }

    /**
     * @param $amount
     * @param null $currency
     * @return int
     */
    protected function prepare_amount($amount, $currency = null) {
        $multiplier = $this->get_currency_multiplier($currency);
        $amount = $amount * $multiplier;
        return round($amount);
    }

    /**
     * convert amount from gateway to initial amount
     *
     * @param $amount
     * @param $currency
     * @return int
     */
    public function make_initial_amount($amount, $currency)
    {
        $denominator = $this->get_currency_multiplier($currency);
        return  $amount / $denominator;
    }

    /**
     * get count of minor units fof currency
     * @param string $currency
     * @return int
     */
    private function get_currency_multiplier($currency) {
        return array_key_exists($currency, $this->currency_minor_units) ?
            $this->currency_minor_units[$currency] : 100;
    }
}
