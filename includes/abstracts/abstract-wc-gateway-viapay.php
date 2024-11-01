<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

abstract class WC_Gateway_Viapay extends WC_Payment_Gateway_Viapay {
	const METHOD_WINDOW = 'WINDOW';
	const METHOD_OVERLAY = 'OVERLAY';

	/**
	 * Test Mode
	 * @var string
	 */
	public $test_mode = 'yes';

	/**
	 * @var string
	 */
	public $private_key;

	/**
	 * @var
	 */
	public $private_key_test = self::COMMON_PRIVATE_KEY_TEST;

	/**
	 * @var string
	 */

	public $public_key;

	/**
	 * @var string
	 */
	
	public $server_base_url = self::SERVER_DOMAIN;

	/**
	 * Settle
	 * @var string
	 */
	public $settle = array(
		self::SETTLE_VIRTUAL,
		self::SETTLE_PHYSICAL,
		self::SETTLE_RECURRING,
		self::SETTLE_FEE
	);

	/**
	 * Debug Mode
	 * @var string
	 */
	public $debug = 'yes';

	/**
	 * Language
	 * @var string
	 */
	public $language = 'en_US';

	/**
	 * Country
	 * @var string
	 */
	public $country = 'us';

	/**
	 * Logos
	 * @var array
	 */
	public $logos = array(
		'dankort',
		'visa',
		'mastercard',
		'visa-electron',
		'maestro',
		'mobilepay',
		'viabill',
		'applepay',
		'paypal_logo',
		'klarna-pay-later',
		'klarna-pay-now',
		'klarna',
		'resursbank'
	);

	/**
	 * Payment Type
	 * @var string
	 */
	public $payment_type = 'OVERLAY';

	/**
	 * Save CC
	 * @var string
	 */
	public $save_cc = 'yes';

	/**
	 * Logo Height
	 * @var string
	 */
	public $logo_height = '';

	/**
	 * Skip order lines to Viapay and use order totals instead
	 */
	public $skip_order_lines = 'no';

	/**
	 * If automatically cancel inpaid orders should be ignored
	 */
	public $enable_order_autocancel = 'no';

	/**
	 * Email address for notification about failed webhooks
	 * @var string
	 */
	public $failed_webhooks_email = '';

	/**
	 * If webhooks have been configured
	 * @var string
	 */
	public $is_webhook_configured = 'no';

	/**
	 * Payment methods.
	 *
	 * @var array|null
	 */
	public $payment_methods = null;	
	
	/**
	 * Init
	 */
	public function __construct() {

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

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();		

		// Define user set variables
		$this->enabled                  = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
		$this->title                    = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->description              = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->private_key              = isset( $this->settings['private_key'] ) ? $this->settings['private_key'] : $this->private_key;
		$this->private_key_test         = isset( $this->settings['private_key_test'] ) ? $this->settings['private_key_test'] : $this->private_key_test;
		$this->test_mode                = isset( $this->settings['test_mode'] ) ? $this->settings['test_mode'] : $this->test_mode;
		$this->settle                   = isset( $this->settings['settle'] ) ? $this->settings['settle'] : $this->settle;
		$this->language                 = isset( $this->settings['language'] ) ? $this->settings['language'] : $this->language;		
		$this->country                  = isset( $this->settings['country'] ) ? $this->settings['country'] : $this->country;		
		$this->save_cc                  = isset( $this->settings['save_cc'] ) ? $this->settings['save_cc'] : $this->save_cc;
		$this->debug                    = isset( $this->settings['debug'] ) ? $this->settings['debug'] : $this->debug;
		$this->logos                    = isset( $this->settings['logos'] ) ? $this->settings['logos'] : $this->logos;
		$this->payment_type             = isset( $this->settings['payment_type'] ) ? $this->settings['payment_type'] : $this->payment_type;
		$this->skip_order_lines         = isset( $this->settings['skip_order_lines'] ) ? $this->settings['skip_order_lines'] : $this->skip_order_lines;
		$this->enable_order_autocancel  = isset( $this->settings['enable_order_autocancel'] ) ? $this->settings['enable_order_autocancel'] : $this->enable_order_autocancel;	

		if (!is_array($this->settle)) {
			$this->settle = array();
		}		

		add_action( 'admin_notices', array( $this, 'admin_notice_warning'));

        add_action('admin_notices', array($this, 'notice_viapay_api_action'));

		// JS Scrips
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_' . strtolower( __CLASS__ ), array(
			$this,
			'return_handler'
		) );

		// Payment confirmation
		add_action( 'the_post', array( &$this, 'payment_confirm' ) );
	}

    /**
     * add notifictions in admin for viapay api actions
     */
    function notice_viapay_api_action() {
        if ($this->enabled === 'yes') {
            $error = get_transient('viapay_api_action_error');
            $success = get_transient( 'viapay_api_action_success');

            if(!empty( $error )) {
                echo "<div class='error notice is-dismissible'>
                    <p> {$error}</p>
                  </div>";
            }

            if(!empty( $success )) {
                echo "<div class='notice notice-success is-dismissible'>
                    <p> {$success}</p>
                  </div>";
            }
        }
        set_transient('viapay_api_action_error', '', 1);
        set_transient( 'viapay_api_action_success', '', 1);
	}

	/**
	 * Admin notice warning
	 */
	public function admin_notice_warning() {
		$checkout_settings = get_option('woocommerce_viapay_checkout_settings', null);
		if (empty($checkout_settings)) return;
		if ( $this->enabled === 'yes' && ! is_ssl() ) {
			$message = __( 'Viapay is enabled, but a SSL certificate is not detected. Your checkout may not be secure! Please ensure your server has a valid', 'viapay-checkout-gateway' );
			$message_href = __( 'SSL certificate', 'viapay-checkout-gateway' );
			$url = 'https://en.wikipedia.org/wiki/Transport_Layer_Security';
			printf( '<div class="notice notice-warning is-dismissible"><p>%1$s <a href="%2$s" target="_blank">%3$s</a></p></div>',
				esc_html( $message ),
				esc_url( $url ),
				esc_html( $message_href )
			); 
		}		
	}		

	/**
	 * Return the gateway's icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		$html = '';
		$logos = array_filter( (array) $this->logos, 'strlen' );
		if ( count( $logos ) > 0 ) {
			$html = '<ul class="viapay-logos">';
			foreach ( $logos as $logo ) {
				$html .= '<li class="viapay-logo">';
				$html .= '<img src="' . esc_url( plugins_url( '/assets/images/' . $logo . '.png', dirname( __FILE__ ) . '/../../../' ) ) . '" alt="' . esc_attr( sprintf( __( 'Pay with %s on Viapay', 'viapay-checkout-gateway' ), $this->get_title() ) ). '" />';
				$html .= '</li>';
			}
			$html .= '</ul>';
		}

		return apply_filters( 'woocommerce_gateway_icon', $html, $this->id );
	}

	/**
	 * payment_scripts function.
	 *
	 * Outputs scripts used for payment
	 *
	 * @return void
	 */
	public function payment_scripts() {
		if ( ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}

		if ( is_order_received_page() ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$request_url = $this->getURL('checkout.js');
		wp_enqueue_script( 'viapay-checkout', $request_url, array(), false, false );
		wp_register_script( 'wc-gateway-viapay-checkout', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/../../assets/js/checkout' . $suffix . '.js', array(
			'jquery',
			'wc-checkout',
			'viapay-checkout',
		), false, true );

		// Localize the script with new data
		$translation_array = array(
			'payment_type' => $this->payment_type,
			'public_key' => $this->public_key,
			'language' => substr( $this->get_language(), 0, 2 ),
			'buttonText' => __( 'Pay', 'viapay-checkout-gateway' ),
			'recurring' => true,
			'nonce' => wp_create_nonce( 'viapay' ),
			'ajax_url' => admin_url( 'admin-ajax.php' ),

		);
		wp_localize_script( 'wc-gateway-viapay-checkout', 'WC_Gateway_Viapay_Checkout', $translation_array );

		// Enqueued script with localized data.
		wp_enqueue_script( 'wc-gateway-viapay-checkout' );
	}

	/**
	 * Return the URL using the current base URL.
	 * @return string
	 */
	public function getURL($target) {
		$url = '';
		switch ($target) {
			case 'webhook_settings':
				$url = 'https://api.'.$this->server_base_url.'/v1/account/webhook_settings';								
				break;
			case 'recurring':				
				$url = 'https://checkout-api.'.$this->server_base_url.'/v1/session/recurring';									   
				break;
			case 'checkout-charge':
				$url = 'https://checkout-api.'.$this->server_base_url.'/v1/session/charge';	
				break;			
			case 'charge':
				$url = 'https://api.'.$this->server_base_url.'/v1/charge';
				break;	
			case 'customer':
				$url = 'https://api.'.$this->server_base_url.'/v1/customer';
				break;
			case 'invoice':
				$url = 'https://api.'.$this->server_base_url.'/v1/invoice';
				break;
			case 'refund':
				$url = 'https://api.'.$this->server_base_url.'/v1/refund';	
				break;				
			case 'privkey':
				$url = 'https://api.'.$this->server_base_url.'/v1/account/privkey';
				break;
			case 'checkout.js':
				$url = 'https://checkout.'.$this->server_base_url.'/checkout.js';
				break;
		}
		return $url;
	}

	/**
	 * If There are no payment fields show the description if set.
	 * @return void
	 */
	public function payment_fields() {
		wc_get_template(
			'checkout/payment-fields.php',
			array(
				'gateway' => $this,
			),
			'',
			dirname( __FILE__ ) . '/../../templates/'
		);
	}

	/**
	 * Validate frontend fields.
	 *
	 * Validate payment fields on the frontend.
	 *
	 * @return bool
	 */
	public function validate_fields() {
		return true;
	}


	/**
	 * Process Payment
	 *
	 * @param int $order_id
	 *
	 * @return array|false
	 */
	public function process_payment( $order_id ) {
	    $order           = wc_get_order( $order_id );
		$token_id        = isset( $_POST['wc-' . $this->id . '-payment-token'] ) ? wc_clean( $_POST['wc-' . $this->id . '-payment-token'] ) : 'new';
		$maybe_save_card = isset( $_POST['wc-' . $this->id . '-new-payment-method'] ) && (bool) wc_clean($_POST['wc-' . $this->id . '-new-payment-method']);

		if ( 'yes' !== $this->save_cc ) {
			$token_id = 'new';
			$maybe_save_card = false;
		}

		// Switch of Payment Method
		if ( self::wcs_is_payment_change() ) {

			$customer_handle = $this->get_customer_handle_order( $order_id );

			if ( absint( $token_id ) > 0 ) {
				$token = new WC_Payment_Token_Viapay( $token_id );
				if ( ! $token->get_id() ) {
					wc_add_notice( __( 'Failed to load token.', 'viapay-checkout-gateway' ), 'error' );

					return false;
				}

				// Check access
				if ( $token->get_user_id() !== $order->get_user_id() ) {
					wc_add_notice( __( 'Access denied.', 'viapay-checkout-gateway' ), 'error' );

					return false;
				}

				// Replace token
				try {
					self::assign_payment_token( $order, $token );
				} catch ( Exception $e ) {
					$order->add_order_note( $e->getMessage() );

					return array(
						'result'   => 'failure',
						'message' => $e->getMessage()
					);
				}

				// Add note
				$order->add_order_note( sprintf( __( 'Payment method changed to "%s"', 'viapay-checkout-gateway' ), $token->get_display_name() ) );

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			} else {
				// Add new Card
				$params = [
					'locale'> $this->get_language(),
					'button_text' => __( 'Add card', 'viapay-checkout-gateway' ),
					'create_customer' => [
						'test' => $this->test_mode === 'yes',
						'handle' => $customer_handle,
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
					'accept_url' => add_query_arg(
						array(
							'action' => 'viapay_finalize',
							'key' => $order->get_order_key()
						),
						admin_url( 'admin-ajax.php' )
					),
					'cancel_url' => $order->get_cancel_order_url()
				];

				if ( $this->payment_methods && count( $this->payment_methods ) > 0 ) {
					$params['payment_methods'] = $this->payment_methods;
				}

				$request_url = $this->getURL('recurring');
				$result = $this->request('POST', $request_url, $params);
				$this->log( sprintf( '%s::%s Result %s', __CLASS__, __METHOD__, var_export( $result, true ) ) );

				return array(
					'result'   => 'success',
					'redirect' => $result['url']
				);
			}
		}

		// Try to charge with saved token
		if ( absint( $token_id ) > 0 ) {
			$token = new WC_Payment_Token_Viapay( $token_id );
			if ( ! $token->get_id() ) {
				wc_add_notice( __( 'Failed to load token.', 'viapay-checkout-gateway' ), 'error' );

				return false;
			}

			// Check access
			if ( $token->get_user_id() !== $order->get_user_id() ) {
				wc_add_notice( __( 'Access denied.', 'viapay-checkout-gateway' ), 'error' );

				return false;
			}

			if ( abs( $order->get_total() ) < 0.01 ) {
				// Don't charge payment if zero amount
				$order->payment_complete();
			} else {
				// Charge payment
				if ( true !== ( $result = $this->viapay_charge( $order, $token->get_token(), $order->get_total() ) ) ) {
					wc_add_notice( $result, 'error' );

					return false;
				}

				// Settle the charge
				$this->process_instant_settle( $order );
			}

			try {
				self::assign_payment_token( $order, $token->get_id() );
			} catch ( Exception $e ) {
				$order->add_order_note( $e->getMessage() );
			}

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);
		}

		// "Save Card" flag
		update_post_meta( $order->get_id(), '_viapay_maybe_save_card', $maybe_save_card );

		// Get Customer reference
		$customer_handle = $this->get_customer_handle_order( $order->get_id() );

		// If here's Subscription or zero payment
		if ( abs( $order->get_total() ) < 0.01 ) {
			$params = [
				'locale'> $this->get_language(),
				'button_text' => __( 'Pay', 'viapay-checkout-gateway' ),
				'create_customer' => [
					'test' => $this->test_mode === 'yes',
					'handle' => $customer_handle,
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
				'accept_url' => $this->get_return_url( $order ),
				'cancel_url' => $order->get_cancel_order_url()
			];

			if ( $this->payment_methods && count( $this->payment_methods ) > 0 ) {
				$params['payment_methods'] = $this->payment_methods;
			}

			$request_url = $this->getURL('recurring');
			$result = $this->request('POST', $request_url, $params);
			$this->log( sprintf( '%s::%s Result %s', __CLASS__, __METHOD__, var_export( $result, true ) ) );

			return array(
				'result'             => 'success',
				'redirect'           => '#!viapay-checkout',
				'is_viapay_checkout' => true,
				'viapay'             => $result,
				'accept_url'         => $this->get_return_url( $order ),
				'cancel_url'         => $order->get_cancel_order_url()
			);
		}

		// Initialize Payment
		$params = [
			'locale' => $this->get_language(),
			'recurring' => $maybe_save_card || self::order_contains_subscription( $order ) || self::wcs_is_payment_change(),
			'order' => [
				'handle' => $this->get_order_handle( $order ),
				'generate_handle' => false,
                'amount' => $this->skip_order_lines === 'yes' ? $this->prepare_amount($order->get_total(), $order->get_currency()) : null,
                'order_lines' => $this->skip_order_lines === 'no' ? $this->get_order_items( $order ) : null,
				'currency' => $order->get_currency(),
				'customer' => [
					'test' => $this->test_mode === 'yes',
					'handle' => $customer_handle,
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
			],
			'accept_url' => $this->get_return_url( $order ),
			'cancel_url' => $order->get_cancel_order_url(),
		];

		if ( $this->payment_methods && count( $this->payment_methods ) > 0 ) {
			$params['payment_methods'] = $this->payment_methods;
		}

		if ($order->needs_shipping_address()) {
			$params['order']['shipping_address'] = [
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

//			if (!strlen($params['order']['shipping_address'])) {
//				$params['order']['shipping_address'] = $params['order']['billing_address'];
//			}
		}

		$request_url = $this->getURL('checkout-charge');
		$result = $this->request('POST', $request_url, $params);

		$this->log( sprintf( '%s::%s Result %s', __CLASS__, __METHOD__, var_export( $result, true ) ) );

		if ( is_checkout_pay_page() ) {

			if ( $this->payment_type === self::METHOD_OVERLAY ) {
				return array(
					'result'             => 'success',
					'redirect'           => sprintf( '#!viapay-pay?rid=%s&accept_url=%s&cancel_url=%s',
						$result['id'],
						html_entity_decode( $this->get_return_url( $order ) ),
						html_entity_decode( $order->get_cancel_order_url() )
					),
				);
			} else {

				return array(
					'result'             => 'success',
					'redirect'           => $result['url'],
				);
			}
		}

		return array(
			'result'             => 'success',
			'redirect'           => '#!viapay-checkout',
			'is_viapay_checkout' => true,
			'viapay'             => $result,
			'accept_url'         => $this->get_return_url( $order ),
			'cancel_url'         => home_url() . '/index.php/checkout/viapay_cancel?id='. $order->get_id()
		);
	}

	/**
	 * Payment confirm action
	 * @return void
	 */
	public function payment_confirm() {
		if ( ! ( is_wc_endpoint_url( 'order-received' ) || is_account_page() ) ) {
			return;
		}

		if ( empty( $_GET['id'] ) ) {
			return;
		}

		if ( empty( $_GET['key'] ) ) {
			return;
		}

		if ( ! $order_id = wc_get_order_id_by_order_key( wc_clean($_GET['key']) ) ) {
			return;
		}

		if ( ! $order = wc_get_order( $order_id ) ) {
			return;
		}

		if ( $order->get_payment_method() !== $this->id ) {
			return;
		}

		//$this->log( sprintf( 'accept_url: Incoming data: %s', var_export($_GET, true) ) );

		// Save Payment Method
		$maybe_save_card = get_post_meta( $order->get_id(), '_viapay_maybe_save_card', true );

		if ( ! empty( $_GET['payment_method'] ) && ( $maybe_save_card || self::order_contains_subscription( $order ) ) ) {
			$this->viapay_save_token( $order, wc_clean( $_GET['payment_method'] ) );
		}

		// Complete payment if zero amount
		if ( abs( $order->get_total() ) < 0.01 ) {
			$order->payment_complete();
		}

		// Update the order status if webhook wasn't configured
		if ( 'no' === $this->is_webhook_configured ) {
			if ( ! empty( $_GET['invoice'] ) ) {
				$this->process_order_confirmation( wc_clean( $_GET['invoice'] ) );
			}
		}
	}

	/**
	 * WebHook Callback
	 * @return void
	 */
	public function return_handler() {
		try {
			$raw_body = file_get_contents( 'php://input' );
			$this->log( sprintf( 'WebHook: Initialized %s from %s', $_SERVER['REQUEST_URI'], $_SERVER['REMOTE_ADDR'] ) );
			$this->log( sprintf( 'WebHook: Post data: %s', var_export( $raw_body, true ) ) );
			$data = @json_decode( $raw_body, true );
			if ( ! $data ) {
				throw new Exception( 'Missing parameters' );
			}

			// Get Secret
			if ( ! ( $secret = get_transient( 'viapay_webhook_settings_secret' ) ) ) {
				$request_url = $this->getURL('webhook_settings');
				$result = $this->request( 'GET', $request_url );
				$secret = $result['secret'];

				set_transient( 'viapay_webhook_settings_secret', $secret, HOUR_IN_SECONDS );
			}

			// Verify secret
			$check = bin2hex( hash_hmac( 'sha256', $data['timestamp'] . $data['id'], $secret, true ) );
			if ( $check !== $data['signature'] ) {
				throw new Exception( 'Signature verification failed' );
			}

			$this->process_webhook( $data );

			http_response_code(200);
		} catch (Exception $e) {
			$this->log( sprintf(  'WebHook: Error: %s', $e->getMessage() ) );
			http_response_code(400);
		}
	}

    /**
     * Process the order confirmation using accept_url.
     *
     * @param string $invoice_id
     *
     * @return void
     * @throws Exception
     */
	public function process_order_confirmation( $invoice_id ) {

		// Update order status
		$this->log( sprintf( 'accept_url: Processing status update %s', $invoice_id ) );
		try {
			$result = $this->get_invoice_by_handle( $invoice_id );
		} catch ( Exception $e ) {
			return;
		}

		// Get order
		$order = $this->get_order_by_handle( $invoice_id );

		$this->log( sprintf( 'accept_url: invoice state: %s. Invoice ID: %s ', $result['state'], $invoice_id ) );

		switch ( $result['state'] ) {
			case 'authorized':
				// Check if the order has been marked as authorized before
				if ( $order->get_status() === VIAPAY_STATUS_AUTHORIZED ) {
					$this->log( sprintf( 'accept_url: Order #%s has been authorized before', $order->get_id() ) );
					return;
				}

				// Lock the order
				self::lock_order( $order->get_id() );

				WC_Viapay_Order_Statuses::set_authorized_status(
					$order,
					sprintf(
						__( 'Payment has been authorized. Amount: %s.', 'viapay-checkout-gateway' ),
						wc_price($this->make_initial_amount($result['amount'], $result['currency']))
					)
				);

				// Settle an authorized payment instantly if possible
				$this->process_instant_settle( $order );

				// Unlock the order
				self::unlock_order( $order->get_id() );

				$this->log( sprintf( 'accept_url: Order #%s has been marked as authorized', $order->get_id() ) );
				break;
			case 'settled':
				// Check if the order has been marked as settled before
				if ( $order->get_status() === VIAPAY_STATUS_SETTLED ) {
					$this->log( sprintf( 'accept_url: Order #%s has been settled before', $order->get_id() ) );
					return;
				}

				// Lock the order
				self::lock_order( $order->get_id() );

				WC_Viapay_Order_Statuses::set_settled_status(
					$order,
					sprintf(
						__( 'Payment has been settled. Amount: %s.', 'viapay-checkout-gateway' ),
						wc_price( $this->make_initial_amount($result['amount'], $result['currency']))
					)
				);

				// Unlock the order
				self::unlock_order( $order->get_id() );

				$this->log( sprintf( 'accept_url: Order #%s has been marked as settled', $order->get_id() ) );

				break;
			case 'cancelled':
				$order->update_status( 'cancelled', __( 'Cancelled.', 'viapay-checkout-gateway' ) );

				$this->log( sprintf( 'accept_url: Order #%s has been marked as cancelled', $order->get_id() ) );

				break;
			case 'failed':
				$order->update_status( 'failed', __( 'Failed.', 'viapay-checkout-gateway' ) );

				$this->log( sprintf( 'accept_url: Order #%s has been marked as failed', $order->get_id() ) );

				break;
			default:
				// no break
		}
	}

	/**
	 * Process WebHook.
	 *
	 * @param array $data
	 *
	 * @return void
	 */
	public function process_webhook( $data ) {
		// Check invoice state
		switch ( $data['event_type'] ) {
			case 'invoice_authorized':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				// Get Order by handle
				$order = $this->get_order_by_handle( $data['invoice'] );

				// Check transaction is applied
				if ( $order->get_transaction_id() === $data['transaction'] ) {
					$this->log( sprintf( 'WebHook: Transaction already applied: %s', $data['transaction'] ) );
					return;
				}

				// Wait to be unlocked
				$needs_reload = self::wait_for_unlock( $order->get_id() );
				if ( $needs_reload ) {
					$order = wc_get_order( $order->get_id() );
				}

				// Check if the order has been marked as authorized before
				if ( $order->get_status() === VIAPAY_STATUS_AUTHORIZED ) {
					$this->log( sprintf( 'WebHook: Event type: %s success. But the order had status early: %s',
						$data['event_type'],
						$order->get_status()
					) );

					http_response_code( 200 );
					return;
				}

				// Lock the order
				self::lock_order( $order->get_id() );

				// Add transaction ID
				$order->set_transaction_id( $data['transaction'] );
				$order->save();

				// Fetch the Invoice data at the moment
				try {
					$invoice_data = $this->get_invoice_by_handle( $data['invoice'] );
				} catch ( Exception $e ) {
					$invoice_data = array();
				}

				$this->log( sprintf( 'WebHook: Invoice data: %s', var_export( $invoice_data, true ) ) );

				// set order as authorized
				WC_Viapay_Order_Statuses::set_authorized_status(
					$order,
					sprintf(
						__( 'Payment has been authorized. Amount: %s. Transaction: %s', 'viapay-checkout-gateway' ),

                        wc_price( $this->make_initial_amount($invoice_data['amount'], $order->get_currency())),

						$data['transaction']
					),
					$data['transaction']
				);

				// Settle an authorized payment instantly if possible
				$this->process_instant_settle( $order );

				// Unlock the order
				self::unlock_order( $order->get_id() );

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'invoice_settled':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				// Get Order by handle
				$order = $this->get_order_by_handle( $data['invoice'] );

				// Wait to be unlocked
				$needs_reload = self::wait_for_unlock( $order->get_id() );
				if ( $needs_reload ) {
					$order = wc_get_order( $order->get_id() );
				}

				// Check transaction is applied
				if ( $order->get_transaction_id() === $data['transaction'] ) {
					$this->log( sprintf( 'WebHook: Transaction already applied: %s', $data['transaction'] ) );
					return;
				}

				// Check if the order has been marked as settled before
				if ( $order->get_status() === VIAPAY_STATUS_SETTLED ) {
					$this->log( sprintf( 'WebHook: Event type: %s success. But the order had status early: %s',
						$data['event_type'],
						$order->get_status()
					) );

					http_response_code( 200 );
					return;
				}

				// Lock the order
				self::lock_order( $order->get_id() );

				// Fetch the Invoice data at the moment
				try {
					$invoice_data = $this->get_invoice_by_handle( $data['invoice'] );
				} catch ( Exception $e ) {
					$invoice_data = array();
				}

				$this->log( sprintf( 'WebHook: Invoice data: %s', var_export( $invoice_data, true ) ) );

				WC_Viapay_Order_Statuses::set_settled_status(
					$order,
					sprintf(
						__( 'Payment has been settled. Amount: %s. Transaction: %s', 'viapay-checkout-gateway' ),
						wc_price( $this->make_initial_amount($invoice_data['amount'], $order->get_currency())),
						$data['transaction']
					),
					$data['transaction']
				);

				update_post_meta( $order->get_id(), '_viapay_capture_transaction', $data['transaction'] );

				// Unlock the order
				self::unlock_order( $order->get_id() );

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'invoice_cancelled':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				// Get Order by handle
				$order = $this->get_order_by_handle( $data['invoice'] );

				// Check transaction is applied
				if ( $order->get_transaction_id() === $data['transaction'] ) {
					$this->log( sprintf( 'WebHook: Transaction already applied: %s', $data['transaction'] ) );
					return;
				}

				// Add transaction ID
				$order->set_transaction_id( $data['transaction'] );
				$order->save();

				if ( $order->has_status( 'cancelled' ) ) {
					$this->log( sprintf( 'WebHook: Event type: %s success. Order status: %s', $data['event_type'], $order->get_status() ) );
					http_response_code( 200 );
					return;
				}

				$order->update_status( 'cancelled', __( 'Cancelled by WebHook.', 'viapay-checkout-gateway' ) );
				update_post_meta( $order->get_id(), '_viapay_cancel_transaction', $data['transaction'] );
				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'invoice_refund':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				// Get Order by handle
				$order = $this->get_order_by_handle( $data['invoice'] );

				// Get Invoice data
				try {
					$invoice_data = $this->get_invoice_by_handle( $data['invoice'] );
				} catch ( Exception $e ) {
					$invoice_data = array();
				}

				$credit_notes = $invoice_data['credit_notes'];
				foreach ($credit_notes as $credit_note) {
					// Get registered credit notes
					$credit_note_ids = get_post_meta( $order->get_id(), '_viapay_credit_note_ids', TRUE );
					if ( ! is_array( $credit_note_ids ) ) {
						$credit_note_ids = array();
					}

					// Check is refund already registered
					if ( in_array( $credit_note['id'], $credit_note_ids ) ) {
						continue;
					}

					$credit_note_id = $credit_note['id'];
					$amount = $this->make_initial_amount($credit_note['amount'], $order->get_currency());
					$reason = sprintf( __( 'Credit Note Id #%s.', 'viapay-checkout-gateway' ), $credit_note_id );

					// Create Refund
					$refund = wc_create_refund( array(
						'amount'   => $amount,
						'reason'   => '', // don't add Credit note to refund line
						'order_id' => $order->get_id()
					) );

					if ( $refund ) {
						// Save Credit Note ID
						$credit_note_ids = array_merge( $credit_note_ids, $credit_note_id );
						update_post_meta( $order->get_id(), '_viapay_credit_note_ids', $credit_note_ids );

						$order->add_order_note(
							sprintf( __( 'Refunded: %s. Reason: %s', 'viapay-checkout-gateway' ),
								wc_price( $amount ),
								$reason
							)
						);
					}
				}

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'invoice_created':
				if ( ! isset( $data['invoice'] ) ) {
					throw new Exception( 'Missing Invoice parameter' );
				}

				$this->log( sprintf( 'WebHook: Invoice created: %s', var_export( $data['invoice'], true ) ) );

				try {
					// Get Order by handle
					$order = $this->get_order_by_handle( $data['invoice'] );
				} catch ( Exception $e ) {
					$this->log( sprintf( 'WebHook: %s', $e->getMessage() ) );
				}

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'customer_created':
				$customer = $data['customer'];
				$user_id = $this->get_userid_by_handle( $customer );
				if ( ! $user_id ) {
					if ( strpos( $customer, 'customer-' ) !== false ) {
						$user_id = (int) str_replace( 'customer-', '', $customer );
						if ( $user_id > 0 ) {
							update_user_meta( $user_id, 'viapay_customer_id', $customer );
							$this->log( sprintf( 'WebHook: Customer created: %s', var_export( $customer, true ) ) );
						}
					}

					if ( ! $user_id ) {
						$this->log( sprintf( 'WebHook: Customer doesn\'t exists: %s', var_export( $customer, true ) ) );
					}
				}

				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			case 'customer_payment_method_added':
				// @todo
				$this->log( sprintf( 'WebHook: TODO: customer_payment_method_added: %s', var_export( $data, true ) ) );
				$this->log( sprintf( 'WebHook: Success event type: %s', $data['event_type'] ) );
				break;
			default:
				$this->log( sprintf( 'WebHook: Unknown event type: %s', $data['event_type'] ) );
				throw new Exception( sprintf( 'Unknown event type: %s', $data['event_type'] ) );
		}
	}

	/**
	 * Enqueue the webhook processing.
	 *
	 * @param $raw_body
	 *
	 * @return void
	 */
	public function enqueue_webhook_processing( $raw_body )
	{
		$data = @json_decode( $raw_body, true );

		// Create Background Process Task
		$background_process = new WC_Background_Viapay_Queue();
		$background_process->push_to_queue(
			array(
				'payment_method_id' => $this->id,
				'webhook_data'      => $raw_body,
			)
		);
		$background_process->save();

		$this->log(
			sprintf( 'WebHook: Task enqueued. ID: %s',
				$data['id']
			)
		);
	}

	/**
	 * Lock the order.
	 *
	 * @see wait_for_unlock()
	 * @param mixed $order_id
	 *
	 * @return void
	 */
	public static function lock_order( $order_id ) {
		update_post_meta( $order_id, '_viapay_locked', '1' );
	}

	/**
	 * Unlock the order.
	 *
	 * @see wait_for_unlock()
	 * @param mixed $order_id
	 *
	 * @return void
	 */
	public static function unlock_order( $order_id ) {
		delete_post_meta( $order_id, '_viapay_locked' );
	}

	/**
	 * Wait for unlock.
	 *
	 * @param $order_id
	 *
	 * @return bool
	 */
	public static function wait_for_unlock( $order_id ) {
		@set_time_limit( 0 );
		@ini_set( 'max_execution_time', '0' );

		$is_locked = (bool) get_post_meta( $order_id, '_viapay_locked', true );
		$needs_reload = false;
		$attempts = 0;
		while ( $is_locked ) {
			usleep( 500 );
			$attempts++;
			if ( $attempts > 30 ) {
				break;
			}

			wp_cache_delete( $order_id, 'post_meta' );
			$is_locked = (bool) get_post_meta( $order_id, '_viapay_locked', true );
			if ( $is_locked ) {
				$needs_reload = true;
				clean_post_cache( $order_id );
			}
		}

		return $needs_reload;
	}

	/**
	 * Overwrite the WC function
	 */
	public function generate_settings_html( $form_fields = array(), $echo = true ) {
				
		if ( empty( $form_fields ) ) {
		  $form_fields = $this->get_form_fields();
		}

		$html = '';					
		
		foreach ( $form_fields as $k => $v ) {			
		    $type = $this->get_field_type( $v );		  

		  	switch ($type) {
				case 'header_row':
					$html .= '<tr valign="top"><td scope="row" colspan="2" class="settings_section_label">'.$v['title'].'</td></tr>';
					break;
				case 'custom_html':		
					$row_class = '';
					if (isset($v['hide'])) {
						$row = empty($v['hide'])?false:true;
						if ($hide_row) {
							$row_class = 'class="hidden_row"';
						}
					}										
					$label = $v['label'];					
					if (!empty($label)) {
						$html .= '<tr '.$row_class.' valign="top">
						<th scope="row" class="titledesc">
						<label for="woocommerce_'.$k.'">'.esc_html($label).'</label></th>'.
						'<td class="forminp">'.$v['default'].'</td></tr>';
					} else {
						$html .= '<tr '.$row_class.' valign="top"><td scope="row" colspan="2" class="settings_custom_html">'.$v['default'].'</td></tr>';
					}					
					break;
				case 'test_mode_switch':										
					$field_prefix = 'woocommerce_viapay_checkout_';

					$private_key_k = 'private_key';					
					$private_key_v = $v[$private_key_k];
					$private_key_value = $this->get_option( $private_key_k, $private_key_v );
					$private_key_input = '<input class="input-text regular-input type="text" name="test_mode_switch_'.$private_key_k.'" id="test_mode_switch_'.$private_key_k.'" placeholder="'.__('Type your key here ...', 'viapay-checkout-gateway').'" value="'.$private_key_value.'" oninput="updateSettingParam(\''.$field_prefix.'private_key\', this.value);" />';
					unset($v[$private_key_k]);																				
					
					$private_key_test_k = 'private_key_test';
					$private_key_test_v = $v[$private_key_test_k];
					$private_key_test_value = $this->get_option( $private_key_test_k, $private_key_test_v );
					$private_key_test_input = '<input type="hidden" name="test_mode_switch_'.$private_key_test_k.'" id="test_mode_switch_'.$private_key_test_k.'" value="'.$private_key_test_value.'" oninput="updateSettingParam(\''.$field_prefix.'private_key_test\', this.value);" />';
					unset($v[$private_key_test_k]);
					
					$test_mode_k = 'test_mode';
					$test_mode_v = $v[$test_mode_k];
					$test_mode_value = $this->get_option( $test_mode_k, $test_mode_v );					
					$test_mode_enabled = ($test_mode_value == 'yes')?true:false;

					$test_info = $this->get_test_info($private_key_test_value, $private_key_test_input);
					$live_info = $this->get_live_info($private_key_value, $private_key_input);
																				
					if ($test_mode_enabled) {
						$display_test_info = '';
						$display_live_info = 'display: none';
						$radio_test_checked = 'checked';
						$radio_live_checked = '';
					} else {
						$display_test_info = 'display: none';
						$display_live_info = '';
						$radio_test_checked = '';
						$radio_live_checked = 'checked';
					}							

					$account_mode_switch = '<input type="radio" id="account_mode_test" name="account_mode" value="test" '.$radio_test_checked.'>
						<label for="html">'.__('Test Mode', 'viapay-checkout-gateway').'</label> 
						<input type="radio" id="account_mode_live" name="account_mode" value="live" '.$radio_live_checked.'>
						<label for="css">'.__('Live Mode', 'viapay-checkout-gateway').'</label><br>'.
						'<input type="hidden" id="test_mode_switch_'.$test_mode_k.'" name="test_mode_switch_'.$test_mode_k.'" value="'.$test_mode_value.'" />';

					$test_mode_info = '<div class="test_mode_info" id="test_mode_info" style="'.
						$display_test_info.'">'.$test_info.'</div>';
					$live_mode_info = '<div class="live_mode_info" id="live_mode_info" style="'.
						$display_live_info.'">'.$live_info.'</div>';
					
					$test_mode_html = $account_mode_switch.$test_mode_info.$live_mode_info;
					
					$html .= '<tr valign="top">
					<th scope="row" class="titledesc">
					<label for="test_mode_switch">'.$v['title'].'</label></th>'.
					'<td class="forminp">'.$test_mode_html.'</td></tr>';					
					break;						
				default:					
					if ( method_exists( $this, 'generate_' . $type . '_html' ) ) {
						$row_html = $this->{'generate_' . $type . '_html'}( $k, $v );												
					} else {
						$row_html = $this->generate_text_html( $k, $v );
					}
					$hide_row = false;
					if (isset($v['hide'])) {
						$hide_row = empty($v['hide'])?false:true;
						if ($hide_row) {
							$row_html = str_replace('<tr valign=', '<tr class="hidden_row" valign=', $row_html);
						}
					}					
					$html .= $row_html;
					break;
			}			  
		}				

		if ( $echo ) {		  		  
		  //echo wp_kses_post($html); // WPCS: XSS ok.
		  echo $html; // WPCS: XSS ok.
		} else {
		  //return wp_kses_post($html);
		  return $html;
		}
	}
	
	public function get_test_info($private_key_test_value, $private_key_test_input) {

		$info = '';
		$warning_threshold = '+3 days';		

		$test_api_key_request_date = get_option( 'viapay_test_api_key_request_date', '');
		if (!empty($test_api_key_request_date)) {
			$request_date = strtotime($warning_threshold, strtotime($test_api_key_request_date));
			$current_date = time();
			if ($current_date > $request_date) {
				$info .= '<p class="register_account_reminder_prompt">'.__('Ready to go live? Click on the <strong>Live Mode</strong> radio button and start accepting real payments.', 'viapay-checkout-gateway').'</p>';
			}					
		}

		add_thickbox();

		$help_file = __DIR__ .'/../../templates/admin/help-file.php';
		$help_contents = file_get_contents($help_file);

		$info .= '<p>When test mode is enabled you can try Viapay using the dummy credit card below:</p>

		<table class="card_info_tbl">
			<tr><td>Type:</td><td>Visa</td></tr>
			<tr><td>Card number:</td><td>4111 1111 1111 1111</td></tr>
			<tr><td>Expiratation date:</td><td>12/25</td></tr>
			<tr><td>CVV:</td><td>234</td></tr>
		</table>
		
		<p>You can also trigger different scenarios, using the CVV codes below:</p>
		
		<table class="cvv_info_tbl">
			<tr><td>001</td><td>The credit card is declined with due to credit card expired</td></tr>
			<tr><td>002</td><td>The credit card is declined by the acquirer</td></tr>
			<tr><td>003</td><td>The credit card is declined due to insufficient funds</td></tr>
		</table>

		<div id="test-help-id" style="display:none;">
			<p>
				'.$help_contents.'
			</p>
		</div>
		
		<p>To learn more about the available testing parameters, <a href="#TB_inline?&width=600&height=550&inlineId=test-help-id" class="thickbox">click here</a>.</p>';		
				
		$info .= $private_key_test_input;

		return $info;				
	}

	public function get_live_info($private_key_value, $private_key_input) {
		$info = '';

		$account_creation_request_date = get_option( 'viapay_account_creation_request_date', '');
		if (empty($account_creation_request_date)) {
			$info .= '<p>If you have an ViaPay account, login into your account, generate a private API key and enter it below:</p>';
		} else {
			$info .= '<p>If you have an ViaPay account, login into your account, generate a private API key and enter it below:</p>';
		}		
		$info .= $private_key_input;
		if (empty($account_creation_request_date)) {
			$info .= '<p class="register_account_request_prompt">'.sprintf(__('If you don\'t have an account yet, click <a class="btn btn_req" href="%s">here</a> to request a ViaPay account and go live!', 'viapay-checkout-gateway'), esc_url( admin_url( 'admin.php?page=viapay-account-creation' ) ) ).'</p>';
		} else {
			$info .= '<p class="register_account_date_prompt">'.sprintf(__('Your account creation request was sent at <strong>%s</strong>', 'viapay-checkout-gateway'), $account_creation_request_date).'</p>';
		}

		return $info;
	}
	
}
