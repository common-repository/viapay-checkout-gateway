<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Gateway_Viapay_Checkout extends WC_Gateway_Viapay {
	/**
	 * Save CC
	 * @var string
	 */
	public $save_cc = 'yes';

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
		$this->id           = 'viapay_checkout';
		$this->has_fields   = TRUE;
		$this->method_title = __( 'Viapay Checkout', 'viapay-checkout-gateway' );
		//$this->icon         = apply_filters( 'woocommerce_viapay_checkout_icon', plugins_url( '/assets/images/viapay.png', dirname( __FILE__ ) ) );
		$this->supports     = array(
			'products',
			'refunds',
			'add_payment_method',
			'tokenization',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
		);

		parent::__construct();

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		add_action( 'admin_notices', array( $this, 'start_testing_notice'));

		// Define user set variables
		$this->enabled                  = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
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
		$this->payment_methods          = isset( $this->settings['payment_methods'] ) ? $this->settings['payment_methods'] : $this->payment_methods;
		$this->skip_order_lines         = isset( $this->settings['skip_order_lines'] ) ? $this->settings['skip_order_lines'] : $this->skip_order_lines;
		$this->enable_order_autocancel  = isset( $this->settings['enable_order_autocancel'] ) ? $this->settings['enable_order_autocancel'] : $this->enable_order_autocancel;
		$this->failed_webhooks_email    = isset( $this->settings['failed_webhooks_email'] ) ? $this->settings['failed_webhooks_email'] : $this->failed_webhooks_email;
		$this->is_webhook_configured    = isset( $this->settings['is_webhook_configured'] ) ? $this->settings['is_webhook_configured'] : $this->is_webhook_configured;

		// Disable "Add payment method" if the CC saving is disabled
		if ( $this->save_cc !== 'yes' && ($key = array_search('add_payment_method', $this->supports)) !== false ) {
			unset($this->supports[$key]);
		}

		if (!is_array($this->settle)) {
			$this->settle = array();
		}

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

		add_action( 'woocommerce_thankyou_' . $this->id, array(
			$this,
			'thankyou_page'
		) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_' . strtolower( __CLASS__ ), array(
			$this,
			'return_handler'
		) );

		// Checkout dummy card info
		add_action( 'woocommerce_before_checkout_form', array( $this, 'checkout_test_card_info'), 10 );

		// Subscriptions
		add_action( 'woocommerce_payment_token_added_to_order', array( $this, 'add_payment_token_id' ), 10, 4 );
		add_action( 'woocommerce_payment_complete', array( $this, 'add_subscription_card_id' ), 10, 1 );

		add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, array(
			$this,
			'update_failing_payment_method'
		), 10, 2 );

		add_action( 'wcs_resubscribe_order_created', array( $this, 'delete_resubscribe_meta' ), 10, 1 );
		add_filter( 'wcs_renewal_order_created', array( $this, 'renewal_order_created' ), 10, 2 );

		// Allow store managers to manually set card id as the payment method on a subscription
		add_filter( 'woocommerce_subscription_payment_meta', array(
			$this,
			'add_subscription_payment_meta'
		), 10, 2 );

		add_filter( 'woocommerce_subscription_validate_payment_meta', array(
			$this,
			'validate_subscription_payment_meta'
		), 10, 3 );

		add_action( 'wcs_save_other_payment_meta', array( $this, 'save_subscription_payment_meta' ), 10, 4 );

		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array(
			$this,
			'scheduled_subscription_payment'
		), 10, 2 );

		// Display the credit card used for a subscription in the "My Subscriptions" table
		add_filter( 'woocommerce_my_subscriptions_payment_method', array(
			$this,
			'maybe_render_subscription_payment_method'
		), 10, 2 );

		// Lock "Save card" if needs
		add_filter(
			'woocommerce_payment_gateway_save_new_payment_method_option_html',
			array(
				$this,
				'save_new_payment_method_option_html',
			),
			10,
			2
		);

		// Action for "Add Payment Method"
		add_action( 'wp_ajax_viapay_card_store', array( $this, 'viapay_card_store' ) );
		add_action( 'wp_ajax_nopriv_viapay_card_store', array( $this, 'viapay_card_store' ) );
		add_action( 'wp_ajax_viapay_finalize', array( $this, 'viapay_finalize' ) );
		add_action( 'wp_ajax_nopriv_viapay_finalize', array( $this, 'viapay_finalize' ) );

		// Add js for settings page
		add_action( 'admin_enqueue_scripts', array( $this, 'register_settings_script' ) );
	}

	/**
	 * Initialise Settings Form Fields
	 * @return string|void
	 */
	public function init_form_fields() {		
		$webhook_info = $this->get_webhook_info();
		$support_info = $this->get_support_info();
		$registration_info = $this->get_registration_info();

		$webhook_hide = false;
		if ($this->test_mode && empty($this->private_key)) {
			$webhook_hide = true;
		}
		
		$this->form_fields = array(
			'fieldset_1_start'          => array(
				'title'       => __( 'Basic Settings', 'viapay-checkout-gateway' ),
				'type'        => 'header_row',
				'description' => '',
				'default'     => ''
			),
			'title'          => array(
				'title'       => __( 'Title', 'viapay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout', 'viapay-checkout-gateway' ),
				'default'     => __( 'Viapay Checkout', 'viapay-checkout-gateway' )
			),
			'description'    => array(
				'title'       => __( 'Description', 'viapay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout', 'viapay-checkout-gateway' ),
				'default'     => __( 'Viapay Checkout', 'viapay-checkout-gateway' ),
			),			
			'enabled'        => array(
				'title'   => __( 'Enable/Disable', 'viapay-checkout-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable plugin', 'viapay-checkout-gateway' ),
				'default' => 'yes'
			),
			'test_mode_switch'       => array(
				'title'   => __( 'Account Mode', 'viapay-checkout-gateway' ),
				'type'    => 'test_mode_switch',
				'label'   => __( 'Account Mode', 'viapay-checkout-gateway' ),
				'test_mode' => $this->test_mode,
				'private_key' => $this->private_key,
				'private_key_test' => $this->private_key_test				
			),						
			'test_mode'       => array(
				'title'   => __( 'Test Mode', 'viapay-checkout-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Test Mode', 'viapay-checkout-gateway' ),
				'default' => $this->test_mode,
				'hide'    => true
			),						
			'private_key' => array(
				'title'       => __( 'Live Private Key', 'viapay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'Insert your private key from your live account', 'viapay-checkout-gateway' ) . $registration_info,
				'default'     => $this->private_key,
				'hide'   	  => true
			),				
			'private_key_test' => array(
				'title'       => __( 'Test Private Key', 'viapay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'Insert your private key from your Viapay test account', 'viapay-checkout-gateway' ),
				'default'     => $this->private_key_test,
				'hide'        => true
			),						
			'is_webhook_configured' => array(
				'title'   => __( 'Webhook status', 'viapay-checkout-gateway' ),
				'type'    => 'webhook_status',
				'label'   => __( 'Webhook status', 'viapay-checkout-gateway' ),
				'default' => $this->is_webhook_configured,
				'hide'	  => $webhook_hide			
			),	
			'webhook_info' => array(				
				'title'   => __( 'Webhook setup', 'viapay-checkout-gateway' ),
				'type'    => 'custom_html',
				'label'   => __( 'Webhook setup', 'viapay-checkout-gateway' ),
				'default' => $webhook_info,
				'hide'	  => $webhook_hide
			),	
			'failed_webhooks_email' => array(
				'title'       => __( 'Email address for notification about failed webhooks', 'viapay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'Email address for notification about failed webhooks', 'viapay-checkout-gateway' ),
				'default'     => '',				
				'sanitize_callback' => function( $value ) {
					if ( ! empty( $value ) ) {
						if ( ! is_email( $value ) ) {
							throw new Exception( __( 'Email address is invalid.', 'viapay-checkout-gateway' ) );
						}
					}

					return $value;
				},	
				'hide'	  => $webhook_hide			
			),
			'debug'          => array(
				'title'   => __( 'Debug', 'viapay-checkout-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging', 'viapay-checkout-gateway' ),
				'description' => $support_info,
				'default' => $this->debug
			),			
			
			// --------------------------------------
			
			'fieldset_2_start'          => array(
				'title'       => __( 'Display and Localization', 'viapay-checkout-gateway' ),
				'type'        => 'header_row',
				'description' => '',
				'default'     => ''
			),
			'payment_type' => array(
				'title'       => __( 'Payment Window Display', 'viapay-checkout-gateway' ),
				'description'    => __( 'Choose between a redirect window or a overlay window', 'viapay-checkout-gateway' ),
				'type'        => 'select',
				'options'     => array(
					self::METHOD_WINDOW  => 'Window',
					self::METHOD_OVERLAY => 'Overlay',
				),
				'default'     => $this->payment_type
			),								
			'language'     => array(
				'title'       => __( 'Language In Payment Window', 'viapay-checkout-gateway' ),
				'type'        => 'select',
				'options'     => array(
					''       => __( 'Detect Automatically', 'viapay-checkout-gateway' ),
					'en_US'  => __( 'English', 'viapay-checkout-gateway' ),
					'da_DK'  => __( 'Danish', 'viapay-checkout-gateway' ),
					'sv_SE'  => __( 'Swedish', 'viapay-checkout-gateway' ),
					'no_NO'  => __( 'Norwegian', 'viapay-checkout-gateway' ),
					'de_DE'  => __( 'German', 'viapay-checkout-gateway' ),
					'es_ES'  => __( 'Spanish', 'viapay-checkout-gateway' ),
					'fr_FR'  => __( 'French', 'viapay-checkout-gateway' ),
					'it_IT'  => __( 'Italian', 'viapay-checkout-gateway' ),
					'nl_NL'  => __( 'Netherlands', 'viapay-checkout-gateway' ),
				),
				'default'     => $this->language
			),
			'country'     => array(
				'title'       => __( 'Country In Payment Window', 'viapay-checkout-gateway' ),
				'type'        => 'select',
				'options'     => array(
					''       => __( 'Detect Automatically', 'viapay-checkout-gateway' ),
					'at'  => __( 'Austria', 'viapay-checkout-gateway' ),
					'be'  => __( 'Belgium', 'viapay-checkout-gateway' ),
					'bg'  => __( 'Bulgaria', 'viapay-checkout-gateway' ),
					'hr'  => __( 'Croatia', 'viapay-checkout-gateway' ),
					'cy'  => __( 'Cyprus', 'viapay-checkout-gateway' ),
					'cz'  => __( 'Czech Republic', 'viapay-checkout-gateway' ),
					'dk'  => __( 'Denmark', 'viapay-checkout-gateway' ),
					'ee'  => __( 'Estonia', 'viapay-checkout-gateway' ),
					'fo'  => __( 'Faroe Islands', 'viapay-checkout-gateway' ),
					'fi'  => __( 'Finland', 'viapay-checkout-gateway' ),
					'fr'  => __( 'France', 'viapay-checkout-gateway' ),
					'de'  => __( 'Germany', 'viapay-checkout-gateway' ),
					'gr'  => __( 'Greece', 'viapay-checkout-gateway' ),
					'gl'  => __( 'Greenland', 'viapay-checkout-gateway' ),
					'hu'  => __( 'Hungary', 'viapay-checkout-gateway' ),
					'is'  => __( 'Iceland', 'viapay-checkout-gateway' ),
					'ie'  => __( 'Ireland', 'viapay-checkout-gateway' ),
					'it'  => __( 'Italy', 'viapay-checkout-gateway' ),
					'lv'  => __( 'Latvia', 'viapay-checkout-gateway' ),
					'lt'  => __( 'Lithuania', 'viapay-checkout-gateway' ),
					'lu'  => __( 'Luxembourg', 'viapay-checkout-gateway' ),
					'mt'  => __( 'Malta', 'viapay-checkout-gateway' ),
					'nl'  => __( 'Netherlands', 'viapay-checkout-gateway' ),
					'no'  => __( 'Norway', 'viapay-checkout-gateway' ),
					'pl'  => __( 'Poland', 'viapay-checkout-gateway' ),
					'pt'  => __( 'Portugal', 'viapay-checkout-gateway' ),
					'ro'  => __( 'Romania', 'viapay-checkout-gateway' ),
					'sk'  => __( 'Slovakia', 'viapay-checkout-gateway' ),
					'si'  => __( 'Slovenia', 'viapay-checkout-gateway' ),
					'es'  => __( 'Spain', 'viapay-checkout-gateway' ),
					'se'  => __( 'Sweden', 'viapay-checkout-gateway' ),
					'ch'  => __( 'Switzerland', 'viapay-checkout-gateway' ),
					'gb'  => __( 'United Kingdom', 'viapay-checkout-gateway' ),
				),
				'default'     => $this->language
			),					

			// --------------------------------------

			'fieldset_3_start'          => array(
				'title'       => __( 'Payment Methods', 'viapay-checkout-gateway' ),
				'type'        => 'header_row',
				'description' => '',
				'default'     => ''
			),
			'payment_methods' => array(
				'title'       => __( 'Payment Methods', 'viapay-checkout-gateway' ),
				'description'    => __( 'Payment Methods', 'viapay-checkout-gateway' ),
				'type'           => 'multiselect',
				'css'            => 'height: 250px',
				'options'     => array(
					'card'  => 'All available debit / credit cards',
					'dankort' => 'Dankort',
					'visa' => 'VISA',
					'visa_dk' => 'VISA/Dankort',
					'visa_elec' => 'VISA Electron',
					'mc' => 'MasterCard',
					'amex' => 'American Express',
					'mobilepay' => 'MobilePay',
					'viabill' => 'ViaBill',
					'klarna_pay_later' => 'Klarna Pay Later',
					'klarna_pay_now' => 'Klarna Pay Now',
					'resurs' => 'Resurs Bank',
					'swish' => 'Swish',
					'diners' => 'Diners Club',
					'maestro' => 'Maestro',
					'laser' => 'Laser',
					'discover' => 'Discover',
					'jcb' => 'JCB',
					'china_union_pay' => 'China Union Pay',
					'ffk' => 'Forbrugsforeningen',
					'paypal' => 'PayPal',
					'applepay' => 'Apple Pay',
					'googlepay' => 'Google Pay',
					'vipps' => 'Vipps'
				),
				'default'     => WC_ViapayCheckout::DEFAULT_PAYMENT_METHODS /*$this->payment_methods*/
			),
			'logos'             => array(
				'title'          => __( 'Payment Logos', 'viapay-checkout-gateway' ),
				'description'    => __( 'Choose the logos you would like to show in WooCommerce checkout. Make sure that they are enabled in Viapay Dashboard', 'viapay-checkout-gateway' ),
				'type'           => 'multiselect',
				'css'            => 'height: 250px',
				'options'        => array(
					'dankort' => __( 'Dankort', 'viapay-checkout-gateway' ),
					'visa'       => __( 'Visa', 'viapay-checkout-gateway' ),
					'mastercard' => __( 'MasterCard', 'viapay-checkout-gateway' ),
					'visa-electron' => __( 'Visa Electron', 'viapay-checkout-gateway' ),
					'maestro' => __( 'Maestro', 'viapay-checkout-gateway' ),
					'paypal' => __( 'Paypal', 'viapay-checkout-gateway' ),
					'mobilepay' => __( 'MobilePay Online', 'viapay-checkout-gateway' ),
					'applepay' => __( 'ApplePay', 'viapay-checkout-gateway' ),
					'klarna' => __( 'Klarna', 'viapay-checkout-gateway' ),
					'viabill' => __( 'Viabill', 'viapay-checkout-gateway' ),
					'resurs' => __( 'Resurs Bank', 'viapay-checkout-gateway' ),
					'forbrugsforeningen' => __( 'Forbrugsforeningen', 'viapay-checkout-gateway' ),
					'amex' => __( 'AMEX', 'viapay-checkout-gateway' ),
					'jcb' => __( 'JCB', 'viapay-checkout-gateway' ),
					'diners' => __( 'Diners Club', 'viapay-checkout-gateway' ),
					'unionpay' => __( 'Unionpay', 'viapay-checkout-gateway' ),
					'discover' => __( 'Discover',   'viapay-checkout-gateway' ),
                    'googlepay' => __('Google pay', 'viapay-checkout-gateway' ),
                    'vipps' =>     __('Vipps',      'viapay-checkout-gateway')
				),
				'select_buttons' => TRUE,
			),
			'logo_height'          => array(
				'title'       => __( 'Logo Height', 'viapay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'Set the Logo height in pixels', 'viapay-checkout-gateway' ),
				'default'     => ''
			),
			'settle'             => array(
				'title'          => __( 'Instant Settle', 'viapay-checkout-gateway' ),
				'description'    => __( 'Instant Settle will charge your customers right away', 'viapay-checkout-gateway' ),
				'type'           => 'multiselect',
				'css'            => 'height: 150px',
				'options'        => array(
					self::SETTLE_VIRTUAL   => __( 'Instant Settle online / virtualproducts', 'viapay-checkout-gateway' ),
					self::SETTLE_PHYSICAL  => __( 'Instant Settle physical  products', 'viapay-checkout-gateway' ),
					self::SETTLE_RECURRING => __( 'Instant Settle recurring (subscription) products', 'viapay-checkout-gateway' ),
					self::SETTLE_FEE => __( 'Instant Settle fees', 'viapay-checkout-gateway' ),
				),
				'select_buttons' => TRUE,
				'default'     => $this->settle
			),		
			'enable_order_autocancel' => array(
				'title'       => __( 'The automatic order auto-cancel', 'viapay-checkout-gateway' ),
				'description'    => __( 'The automatic order auto-cancel', 'viapay-checkout-gateway' ),
				'type'        => 'select',
				'options'     => array(
					'yes' => 'Enable auto-cancel',
					'no'  => 'Ignore / disable auto-cancel'
				),
				'default'     => $this->enable_order_autocancel
			),	
			'save_cc'        => array(
				'title'   => __( 'Allow Credit Card saving', 'viapay-checkout-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Save CC feature', 'viapay-checkout-gateway' ),
				'default' => $this->save_cc
			),			
			'skip_order_lines' => array(
				'title'       => __( 'Skip order lines', 'viapay-checkout-gateway' ),
				'description'    => __( 'Select if order lines should not be send to Viapay', 'viapay-checkout-gateway' ),
				'type'        => 'select',
				'options'     => array(
					'no'   => 'Include order lines',
					'yes'  => 'Skip order lines'
				),
				'default'     => $this->skip_order_lines
			),

			// --------------------------------------

			'fieldset_4_start'          => array(
				'title'       => __( 'Other Preferences', 'viapay-checkout-gateway' ),
				'type'        => 'header_row',
				'description' => '',
				'default'     => ''
			)					
						
		);
	}

	/**
	 * Generate WebHook Status HTML.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
     *
	 * @return string
	 */
	public function generate_webhook_status_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'type'              => 'webhook_status',
			'desc_tip'          => false,
			'description'       => '',
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>

					<?php if ( 'yes' === $this->get_option( $key ) ): ?>
						<span style="color: green;">
                            <?php esc_html_e( 'Configured.', 'viapay-checkout-gateway' ); ?>
                        </span>
					<?php else: ?>
						<span style="color: red;">
                            <?php esc_html_e( 'Configuration is required.', 'viapay-checkout-gateway' ); ?>
                        </span>
                        <p>
	                        <?php esc_html_e( 'Please check api credentials and save the settings. Webhook will be installed automatically.', 'viapay-checkout-gateway' ); ?>
                        </p>
		            <?php endif; ?>

					<input type="hidden" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); // WPCS: XSS ok. ?>" />
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Output the gateway settings screen
	 * @return void
	 */
	public function admin_options() {
		$this->display_errors();

		// Check that WebHook was installed
		$token = $this->test_mode ? md5( $this->private_key_test ) : md5( $this->private_key );

		wc_get_template(
			'admin/admin-options.php',
			array(
				'gateway' => $this,
				'webhook_installed' => get_option( 'woocommerce_viapay_webhook_' . $token ) === 'installed'
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}		

	public function get_webhook_info() {
		$webhook_info = '';
		
		// Check that WebHook was installed
		$token = $this->test_mode ? md5( $this->private_key_test ) : md5( $this->private_key );
		$gateway = $this;
		$webhook_installed = get_option( 'woocommerce_viapay_webhook_' . $token ) === 'installed';
				
		if ( ! $webhook_installed ) {
			$webhook_info .= sprintf(
				__('Please setup WebHook in <a href="%s" target="_blank">Viapay Dashboard</a>.', 'viapay-checkout-gateway'),
				'https://admin.reepay.com/'
			);
			$webhook_info .= ' ';
			$webhook_info .= sprintf(
				__('WebHook URL: <a href="%s" target="_blank">%s</a>', 'viapay-checkout-gateway'),
				WC()->api_request_url( get_class( $gateway ) ),
				WC()->api_request_url( get_class( $gateway ) )
			);
		} else {
			$webhook_info .= sprintf(
				__('WebHook has been setup in <a href="%s" target="_blank">Viapay Dashboard</a>.', 'viapay-checkout-gateway'),
				'https://admin.reepay.com/'
			);
			$webhook_info .= ' ';
			$webhook_info .= sprintf(
				__('WebHook URL: <a href="%s" target="_blank">%s</a>', 'viapay-checkout-gateway'),
				WC()->api_request_url( get_class( $gateway ) ),
				WC()->api_request_url( get_class( $gateway ) )
			);
		}

		return $webhook_info;
	}

	public function get_registration_info() {		
		$html = sprintf(__('You don\'t have an account? Click <a href="%s">here</a> to request a ViaPay account and go live!', 'viapay-checkout-gateway'), esc_url( admin_url( 'admin.php?page=viapay-account-creation' ) ) );
		return $html;
	}

	public function get_support_info() {
		$html = sprintf(__('Do you want support? Click <a href="%s">here</a> to fiil in the support form. Alternatively you can contact us at tech@viabill.com.', 'viapay-checkout-gateway'), esc_url( admin_url( 'admin.php?page=viapay-support' ) ) );
		return $html;
	}

	/**
	 * Processes and saves options.
	 * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
	 *
	 * @return bool was anything saved?
	 */
	public function process_admin_options() {
		$result = parent::process_admin_options();

		// Reload settings
		$this->init_settings();
		$this->private_key      = isset( $this->settings['private_key'] ) ? $this->settings['private_key'] : $this->private_key;
		$this->private_key_test = isset( $this->settings['private_key_test'] ) ? $this->settings['private_key_test'] : $this->private_key_test;
		$this->test_mode        = isset( $this->settings['test_mode'] ) ? $this->settings['test_mode'] : $this->test_mode;

		// Check the webhooks settings
		try {
			$request_url = $this->getURL('webhook_settings');
			$result = $this->request('GET', $request_url );

			// The webhook settings
			$urls         = $result['urls'];
			$alert_emails = $result['alert_emails'];

			// The webhook settings of the payment plugin
			$webhook_url = WC()->api_request_url( get_class( $this ) );
			$alert_email = '';
			if ( ! empty( $this->settings['failed_webhooks_email'] ) &&
			     is_email( $this->settings['failed_webhooks_email'] )
			) {
				$alert_email = $this->settings['failed_webhooks_email'];
			}

			// Verify the webhook settings
			if ( in_array( $webhook_url, $urls ) &&
			     ( ! empty( $alert_email ) ? in_array( $alert_email, $alert_emails ) : true )
			) {
				// Webhook has been configured before
				$this->update_option( 'is_webhook_configured', 'yes' );

				// Skip the update
				return $result;
			}

			// Update the webhook settings
			try {
				$urls[] = $webhook_url;

				if ( ! empty( $alert_email ) && is_email( $alert_email ) ) {
					$alert_emails[] = $alert_email;
				}

				$data = array(
					'urls'         => array_unique( $urls ),
					'disabled'     => false,
					'alert_emails' => array_unique( $alert_emails )
				);

				$request_url = $this->getURL('webhook_settings');
				$result = $this->request('PUT', $request_url, $data);				
				$this->log( sprintf( 'WebHook has been successfully created/updated: %s', var_export( $result, true ) ) );				
				$this->update_option( 'is_webhook_configured', 'yes' );

				$test_mode_enabled = ($this->test_mode === 'yes')?true:false;
				if (!$test_mode_enabled) {
					WC_Admin_Settings::add_message( __( 'Viapay: WebHook has been successfully created/updated', 'viapay-checkout-gateway' ) );
				}
			} catch ( Exception $e ) {
				$this->update_option( 'is_webhook_configured', 'no' );
				$this->log( sprintf( 'WebHook creation/update has been failed: %s', var_export( $result, true ) ) );
				$test_mode_enabled = ($this->test_mode === 'yes')?true:false;
				if (!$test_mode_enabled) {					
					WC_Admin_Settings::add_error( __( 'Viapay: WebHook creation/update has been failed' ) );
				}				
			}
		} catch ( Exception $e ) {
			$test_mode_enabled = ($this->test_mode === 'yes')?true:false;
			if (!$test_mode_enabled) {
				$this->update_option( 'is_webhook_configured', 'no' );
				WC_Admin_Settings::add_error( __( 'Unable to retrieve the webhook settings. Wrong api credentials?', 'viapay-checkout-gateway' ) );
			}
		}

		return $result;
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
			dirname( __FILE__ ) . '/../templates/'
		);

		// The "Save card or use existed" form should appears when active or when the cart has a subscription
		if ( ( $this->save_cc === 'yes' && ! is_add_payment_method_page() ) ||
			 ( self::wcs_cart_have_subscription() || self::wcs_is_payment_change() )
		) {
			$this->tokenization_script();
			$this->saved_payment_methods();
			$this->save_payment_method_checkbox();
        }
	}

	/**
	 * Add Payment Method.
	 *
	 * @return array
	 */
	public function add_payment_method() {
		$user            = get_userdata( get_current_user_id() );
		$customer_handle = get_user_meta( $user->ID, 'viapay_customer_id', true );

		if ( empty ( $customer_handle ) ) {
			// Create viapay customer
			$customer_handle = $this->get_customer_handle( $user->ID );
			$location = wc_get_base_location();

			$params = [
				'locale'> $this->get_language(),
				'button_text' => __( 'Add card', 'viapay-checkout-gateway' ),
				'create_customer' => [
					'test' => $this->test_mode === 'yes',
					'handle' => $customer_handle,
					'email' => $user->user_email,
					'address' => '',
					'address2' => '',
					'city' => '',
					'country' => $location['country'],
					'phone' => '',
					'company' => '',
					'vat' => '',
					'first_name' => $user->first_name,
					'last_name' => $user->last_name,
					'postal_code' => ''
				],
				'accept_url' => add_query_arg( 'action', 'viapay_card_store', admin_url( 'admin-ajax.php' ) ),
				'cancel_url' => wc_get_cart_url() // wc_get_account_endpoint_url( 'payment-methods' ) 
			];
		} else {
			// Use customer who exists
			$params = [
				'locale'> $this->get_language(),
				'button_text' => __( 'Add card', 'viapay-checkout-gateway' ),
				'customer' => $customer_handle,
				'accept_url' => add_query_arg( 'action', 'viapay_card_store', admin_url( 'admin-ajax.php' ) ),
				'cancel_url' => wc_get_account_endpoint_url( 'payment-methods' )
			];
		}

		if ( $this->payment_methods && count( $this->payment_methods ) > 0 ) {
			$params['payment_methods'] = $this->payment_methods;
		}

		$request_url = $this->getURL('recurring');
		$result = $this->request('POST', $request_url, $params);
		$this->log( sprintf( '%s::%s Result %s', __CLASS__, __METHOD__, var_export( $result, true ) ) );

		wp_redirect( $result['url'] );
		exit();
	}

	/**
	 * Thank you page
	 *
	 * @param $order_id
	 *
	 * @return void
	 */
	public function thankyou_page( $order_id ) {
		// Add Subscription card id
		$this->add_subscription_card_id( $order_id );
	}

	/**
	 * Update the card meta for a subscription
	 * to complete a payment to make up for an automatic renewal payment which previously failed.
	 *
	 * @access public
	 *
	 * @param WC_Subscription $subscription  The subscription for which the failing payment method relates.
	 * @param WC_Order        $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 *
	 * @return void
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		$subscription->update_meta_data( '_viapay_token', $renewal_order->get_meta( '_viapay_token', true ) );
		$subscription->update_meta_data( '_viapay_token_id', $renewal_order->get_meta( '_viapay_token_id', true ) );
	}

	/**
	 * Don't transfer customer meta to resubscribe orders.
	 *
	 * @access public
	 *
	 * @param WC_Order $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 *
	 * @return void
	 */
	public function delete_resubscribe_meta( $resubscribe_order ) {
		if ( $resubscribe_order->get_payment_method() === $this->id ) {
			// Delete tokens
			delete_post_meta( $resubscribe_order->get_id(), '_payment_tokens' );
			delete_post_meta( $resubscribe_order->get_id(), '_viapay_token' );
			delete_post_meta( $resubscribe_order->get_id(), '_viapay_token_id' );
			delete_post_meta( $resubscribe_order->get_id(), '_viapay_order' );
		}
	}

	/**
	 * Create a renewal order to record a scheduled subscription payment.
	 *
	 * @param WC_Order|int $renewal_order
	 * @param WC_Subscription|int $subscription
	 *
	 * @return bool|WC_Order|WC_Order_Refund
	 */
	public function renewal_order_created( $renewal_order, $subscription ) {
		if ( ! is_object( $subscription ) ) {
			$subscription = wcs_get_subscription( $subscription );
		}

		if ( ! is_object( $renewal_order ) ) {
			$renewal_order = wc_get_order( $renewal_order );
		}

		if ( $renewal_order->get_payment_method() === $this->id ) {
			// Remove Viapay order handler from renewal order
			delete_post_meta( $renewal_order->get_id(), '_viapay_order' );
		}

		return $renewal_order;
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions v2.0+.
	 *
	 * @param array           $payment_meta associative array of meta data required for automatic payments
	 * @param WC_Subscription $subscription An instance of a subscription object
	 *
	 * @return array
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$viapay_token = get_post_meta( $subscription->get_id(), '_viapay_token', true );

		// If token wasn't stored in Subscription
		if ( empty( $viapay_token ) ) {
			$order = $subscription->get_parent();
		    if ( $order ) {
			    $viapay_token = get_post_meta( $order->get_id(), '_viapay_token', true );
		    }
		}

		$payment_meta[$this->id] = array(
			'post_meta' => array(
				'_viapay_token' => array(
					'value' => $viapay_token,
					'label' => 'Viapay Token',
				)
			)
		);

		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions 2.0+.
	 *
	 * @param string $payment_method_id The ID of the payment method to validate
	 * @param array  $payment_meta      associative array of meta data required for automatic payments
	 * @param WC_Subscription $subscription
	 *
	 * @throws Exception
	 * @return array
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta, $subscription ) {
		if ( $payment_method_id === $this->id ) {
			if ( empty( $payment_meta['post_meta']['_viapay_token']['value'] ) ) {
				throw new Exception( 'A "Viapay Token" value is required.' );
			}

			$tokens = explode( ',', $payment_meta['post_meta']['_viapay_token']['value'] );
			if ( count( $tokens ) > 1 ) {
				throw new Exception( 'Only one "Viapay Token" is allowed.' );
			}

			$token = self::get_payment_token( $tokens[0] );
			if ( ! $token ) {
				throw new Exception( 'This "Viapay Token" value not found.' );
			}

			if ( $token->get_gateway_id() !== $this->id ) {
				throw new Exception( 'This "Viapay Token" value should related to Viapay.' );
			}

			if ( $token->get_user_id() !== $subscription->get_user_id() ) {
				throw new Exception( 'Access denied for this "Viapay Token" value.' );
			}
		}
	}

	/**
	 * Save payment method meta data for the Subscription
	 *
	 * @param WC_Subscription $subscription
	 * @param string $meta_table
	 * @param string $meta_key
	 * @param string $meta_value
	 */
	public function save_subscription_payment_meta( $subscription, $meta_table, $meta_key, $meta_value ) {
		if ( $subscription->get_payment_method() === $this->id ) {
			if ( $meta_table === 'post_meta' && $meta_key === '_viapay_token' ) {
				// Add tokens
				$tokens = explode( ',', $meta_value );
				foreach ( $tokens as $viapay_token ) {
					// Get Token ID
					$token = self::get_payment_token( $viapay_token );
					if ( ! $token ) {
						// Create Payment Token
						$token = $this->add_payment_token( $subscription, $viapay_token );
					}

					self::assign_payment_token( $subscription, $token );
				}
			}
		}
	}

	/**
	 * Add Token ID.
	 *
	 * @param int $order_id
	 * @param int $token_id
	 * @param WC_Payment_Token_Viapay $token
	 * @param array $token_ids
	 *
	 * @return void
	 */
	public function add_payment_token_id( $order_id, $token_id, $token, $token_ids ) {
		$order = wc_get_order( $order_id );
		if ( $order->get_payment_method() === $this->id ) {
			update_post_meta( $order->get_id(), '_viapay_token_id', $token_id );
			update_post_meta( $order->get_id(), '_viapay_token', $token->get_token() );
		}
	}

	/**
	 * Clone Card ID when Subscription created
	 *
	 * @param $order_id
	 */
	public function add_subscription_card_id( $order_id ) {
		if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			return;
		}

		// Get subscriptions
		$subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'parent' ) );
		foreach ( $subscriptions as $subscription ) {
			/** @var WC_Subscription $subscription */
			$token = self::get_payment_token_order( $subscription );
			if ( ! $token ) {
				// Copy tokens from parent order
				$order = wc_get_order( $order_id );
				$token = self::get_payment_token_order( $order );

				if ( $token ) {
					self::assign_payment_token( $subscription, $token );
				}
			}
		}
	}

	/**
	 * When a subscription payment is due.
	 *
	 * @param          $amount_to_charge
	 * @param WC_Order $renewal_order
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		// Lookup token
		try {
			$token = self::get_payment_token_order( $renewal_order );

			// Try to find token in parent orders
			if ( ! $token ) {
				// Get Subscriptions
				$subscriptions = wcs_get_subscriptions_for_order( $renewal_order, array( 'order_type' => 'any' ) );
				foreach ( $subscriptions as $subscription ) {
					/** @var WC_Subscription $subscription */
					$token = self::get_payment_token_order( $subscription );
					if ( ! $token ) {
						$token = self::get_payment_token_order( $subscription->get_parent() );
					}
				}
			}

			// Failback: If token doesn't exist, but viapay token is here
			// We need that to provide woocommerce_subscription_payment_meta support
			// See https://github.com/Prospress/woocommerce-subscriptions-importer-exporter#importing-payment-gateway-meta-data
			if ( ! $token ) {
				$viapay_token = get_post_meta( $renewal_order->get_id(), '_viapay_token', true );

				// Try to find token in parent orders
				if ( empty( $viapay_token ) ) {
					// Get Subscriptions
					$subscriptions = wcs_get_subscriptions_for_order( $renewal_order, array( 'order_type' => 'any' ) );
					foreach ( $subscriptions as $subscription ) {
						/** @var WC_Subscription $subscription */
						$viapay_token = get_post_meta( $subscription->get_id(), '_viapay_token', true );
						if ( empty( $viapay_token ) ) {
						    if ( $order = $subscription->get_parent() ) {
							    $viapay_token = get_post_meta( $order->get_id(), '_viapay_token', true );
						    }
						}
					}
				}

				// Save token
				if ( ! empty( $viapay_token ) ) {
					if ( $token = $this->add_payment_token( $renewal_order, $viapay_token ) ) {
						self::assign_payment_token( $renewal_order, $token );
					}
				}
			}

			if ( ! $token ) {
				throw new Exception( 'Payment token isn\'t exists' );
			}

			// Validate
			if ( empty( $token->get_token() ) ) {
				throw new Exception( 'Payment token is empty' );
			}

			// Fix the viapay order value to prevent "Invoice already settled"
			$currently = get_post_meta( $renewal_order->get_id(), '_viapay_order', true );
			$shouldBe = 'order-' . $renewal_order->get_id();
			if ( $currently !== $shouldBe ) {
				update_post_meta( $renewal_order->get_id(), '_viapay_order', $shouldBe );
			}

			// Charge payment
			if ( true !== ( $result = $this->viapay_charge( $renewal_order, $token->get_token(), $amount_to_charge ) ) ) {
			    throw new Exception( $result );
			}

			// Instant settle
			$this->process_instant_settle( $renewal_order );
		} catch (Exception $e) {
			$renewal_order->update_status( 'failed' );
			$renewal_order->add_order_note(
				sprintf( __( 'Error: "%s". %s.', 'viapay-checkout-gateway' ),
					wc_price( $amount_to_charge ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @param string          $payment_method_to_display the default payment method text to display
	 * @param WC_Subscription $subscription              the subscription details
	 *
	 * @return string the subscription payment method
	 */
	public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
		if ( $this->id !== $subscription->get_payment_method() || ! $subscription->get_user_id() ) {
			return $payment_method_to_display;
		}

		$tokens = $subscription->get_payment_tokens();
		foreach ($tokens as $token_id) {
			$token = new WC_Payment_Token_Viapay( $token_id );
			if ( $token->get_gateway_id() !== $this->id ) {
				continue;
			}

			return sprintf( __( 'Via %s card ending in %s/%s', 'viapay-checkout-gateway' ),
				$token->get_masked_card(),
				$token->get_expiry_month(),
				$token->get_expiry_year()
			);
		}

		return $payment_method_to_display;
	}

	/**
	 * Modify "Save to account" to lock that if needs.
	 *
	 * @param string $html
	 * @param WC_Payment_Gateway $gateway
	 *
	 * @return string
	 */
	public function save_new_payment_method_option_html( $html, $gateway ) {
		if ( $gateway->id !== $this->id ) {
			return $html;
		}

		// Lock "Save to Account" for Recurring Payments / Payment Change
		if ( self::wcs_cart_have_subscription() || self::wcs_is_payment_change() ) {
			// Load XML
			libxml_use_internal_errors( true );
			$doc = new \DOMDocument();
			$status = @$doc->loadXML( $html );
			if ( false !== $status ) {
				$item = $doc->getElementsByTagName('input')->item( 0 );
				$item->setAttribute('checked','checked' );
				$item->setAttribute('disabled','disabled' );

				$html = $doc->saveHTML($doc->documentElement);
			}
		}

		return $html;
	}

	/**
	 * Ajax: Add Payment Method
	 * @return void
	 */
	public function viapay_card_store()
	{
		$id = wc_clean( $_GET['id'] );
		$customer_handle = wc_clean( $_GET['customer'] );
		$viapay_token = wc_clean( $_GET['payment_method'] );

		try {
			// Create Payment Token
			$source = $this->get_viapay_cards( $customer_handle, $viapay_token );
			$expiryDate = explode( '-', $source['exp_date'] );

			$token = new WC_Payment_Token_Viapay();
			$token->set_gateway_id( $this->id );
			$token->set_token( $viapay_token );
			$token->set_last4( substr( $source['masked_card'], -4 ) );
			$token->set_expiry_year( 2000 + $expiryDate[1] );
			$token->set_expiry_month( $expiryDate[0] );
			$token->set_card_type( $source['card_type'] );
			$token->set_user_id( get_current_user_id() );
			$token->set_masked_card( $source['masked_card'] );

			// Save Credit Card
			$token->save();
			if ( ! $token->get_id() ) {
				throw new Exception( __( 'There was a problem adding the card.', 'viapay-checkout-gateway' ) );
			}

			wc_add_notice( __( 'Payment method successfully added.', 'viapay-checkout-gateway' ) );
			wp_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
			exit();
		} catch (Exception $e) {
			wc_add_notice( $e->getMessage(), 'error' );
			wp_redirect( wc_get_account_endpoint_url( 'add-payment-method' ) );
			exit();
		}
	}

	/**
	 * Ajax: Finalize Payment
	 *
	 * @throws Exception
	 */
	public function viapay_finalize()
	{

		$id = wc_clean( $_GET['id'] );
		$customer_handle = wc_clean( $_GET['customer'] );
		$viapay_token = wc_clean( $_GET['payment_method'] );

		try {
			if ( empty( $_GET['key'] ) ) {
				throw new Exception('Order key is undefined' );
			}

			if ( ! $order_id = wc_get_order_id_by_order_key( sanitize_text_field($_GET['key']) ) ) {
				throw new Exception('Can not get order' );
			}

			if ( ! $order = wc_get_order( $order_id ) ) {
				throw new Exception('Can not get order' );
			}

			if ( $order->get_payment_method() !== $this->id ) {
				throw new Exception('Unable to use this order' );
			}

			// $this->log( sprintf( '%s::%s Incoming data %s', __CLASS__, __METHOD__, var_export($_GET, true) ) );

			// Save Token
			$token = $this->viapay_save_token( $order, $viapay_token );

			// Add note
			$order->add_order_note( sprintf( __( 'Payment method changed to "%s"', 'viapay-checkout-gateway' ), $token->get_display_name() ) );

			// Complete payment if zero amount
			if ( abs( $order->get_total() ) < 0.01 ) {
				$order->payment_complete();
			}

			// @todo Transaction ID should applied via WebHook
			if ( ! empty( $_GET['invoice'] ) ) {
			    $handle = wc_clean( $_GET['invoice'] );
			    if ( $handle !== $this->get_order_handle( $order ) ) {
				    throw new Exception('Invoice ID doesn\'t match the order.');
			    }

				$result = $this->get_invoice_by_handle( wc_clean( $_GET['invoice'] ) );
				switch ($result['state']) {
					case 'authorized':
						WC_Viapay_Order_Statuses::set_authorized_status(
							$order,
							sprintf(
								__( 'Payment has been authorized. Amount: %s.', 'viapay-checkout-gateway' ),
								wc_price( $this->make_initial_amount($result['amount'], $order->get_currency()))
							)
						);

						// Settle an authorized payment instantly if possible
						$this->process_instant_settle( $order );
						break;
					case 'settled':
						WC_Viapay_Order_Statuses::set_settled_status(
							$order,
							sprintf(
								__( 'Payment has been settled. Amount: %s.', 'viapay-checkout-gateway' ),
								wc_price( $this->make_initial_amount($result['amount'], $order->get_currency()))
							)
						);
						break;
					default:
						// @todo Order failed?
				}
			}

			wp_redirect( $this->get_return_url( $order ) );
		} catch (Exception $e) {
			wc_add_notice( $e->getMessage(), 'error' );
			wp_redirect( $this->get_return_url() );
		}

		exit();
	}

	/**
     * Register plugin's client JS script.
     */
    public function register_settings_script() {		
		$js_file = esc_url( plugins_url( '/assets/js/jquery.settings.js', dirname( __FILE__ ) . '/../../' ) );

		wp_enqueue_script( 'viapay-checkout-script', $js_file, array( 'jquery' ), false, true );
	}	

	/**
	 * Start testing notice
	 */
	public function start_testing_notice() {
		$page = sanitize_text_field($_GET['page']);
		$checkout_settings = get_option('woocommerce_viapay_checkout_settings', null);
		if (empty($checkout_settings) && ($page == 'wc-settings')) {
			$message = sprintf(__('Preview the settings below and click on the <strong>%s</strong> button to start the testing.',
			'viapay-checkout-gateway'),
			__('Save Changes', 'viapay-checkout-gateway'));

		printf( '<div class="notice notice-warning notice-prompt is-dismissible"><p>%s</p></div>',
			 $message 
			); 				
		}		
	}	

	/**
     * Display dummy credit card information for testing
     *
     * @return void
     */	
	function checkout_test_card_info( ) {
		$settings = get_option( 'woocommerce_viapay_checkout_settings', array());
		if (!empty($settings)) {
			if (isset($settings['test_mode'])) {
				if ($settings['test_mode'] == 'yes') {
					$message = '';					
					$message .= '<p><strong>Tip:</strong> When test mode is enabled, you can only use dummy credits cards and no other payments methods, such as ViaBill.</p>';
					$message .= '<p>Dummy credit card information for testing:</p>

					<table class="card_info_checkout_tbl">
						<tr><td>Type:</td><td>Visa</td></tr>
						<tr><td>Card number:</td><td>4111 1111 1111 1111</td></tr>
						<tr><td>Expiratation date:</td><td>12/25</td></tr>
						<tr><td>CVV:</td><td>234</td></tr>
					</table>
					
					<p>You can also trigger different scenarios, using the CVV codes below:</p>
					
					<table class="cvv_info_checkout_tbl">
						<tr><td>001</td><td>The credit card is declined with due to credit card expired</td></tr>
						<tr><td>002</td><td>The credit card is declined by the acquirer</td></tr>
						<tr><td>003</td><td>The credit card is declined due to insufficient funds</td></tr>
					</table>';

					echo '<style>
						table.card_info_checkout_tbl, table.cvv_info_checkout_tbl { 
							color: #030303; border-collapse: collapse; } 
						table.card_info_checkout_tbl tr td, table.cvv_info_checkout_tbl tr td {
							border-bottom: 1px solid #A0A0A0; 
						}
						</style>';
					
					echo '<div class="woocommerce-form-coupon-toggle">	
						<div class="woocommerce-message">
						'.wp_kses_post($message).'
						</div>
					</div>';					
				}
			}
		}					
	}
	
}

// Register Gateway
WC_ViapayCheckout::register_gateway( 'WC_Gateway_Viapay_Checkout' );
