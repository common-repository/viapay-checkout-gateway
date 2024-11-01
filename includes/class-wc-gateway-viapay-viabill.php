<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

require_once(  dirname( __FILE__ ) . '/viabill/class-viabill-pricetag.php' );

class WC_Gateway_Viapay_Viabill extends WC_Gateway_Viapay {
	/**
	 * Logos
	 * @var array
	 */
	public $logos = array(
		'viabill',
	);

	/**
	 * Payment methods.
	 *
	 * @var array|null
	 */
	public $payment_methods = array(
		'viapay-checkout-gateway'
	);	

	
	/**
	 * PriceTags Main Class and Settings
	 */
	public $pricetag = null;
	public $pricetag_enabled = null;
	public $pricetag_on_product = null;
	public $pricetag_on_cart = null;
	public $pricetag_on_checkout = null;
	public $pricetag_position_product = null;
	public $pricetag_product_hook = null;
	public $pricetag_script = null;
	public $pricetag_product_dynamic_price = null;
	public $pricetag_product_dynamic_price_trigger = null;
	public $pricetag_style_product = null;
	public $pricetag_position_cart = null;
	public $pricetag_cart_dynamic_price = null;
	public $pricetag_cart_dynamic_price_trigger = null;
	public $pricetag_style_cart = null;
	public $pricetag_position_checkout = null;
	public $pricetag_checkout_dynamic_price = null;
	public $pricetag_checkout_dynamic_price_trigger = null;
	public $pricetag_style_checkout = null;	

	public function __construct() {
		$this->id           = 'viapay_viabill';
		$this->has_fields   = true;
		$this->method_title = __( 'Viapay - ViaBill', 'viapay-checkout-gateway' );
		//$this->icon         = apply_filters( 'woocommerce_viapay_viabill_icon', plugins_url( '/assets/images/viabill.png', dirname( __FILE__ ) ) );
		$this->supports     = array(
			'products',
			'refunds',
		);
		$this->logos        = array( 'viabill' );

		parent::__construct();

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		$this->enabled                  = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title                    = isset( $this->settings['title'] ) ? $this->settings['title'] : '';
		$this->description              = isset( $this->settings['description'] ) ? $this->settings['description'] : '';

		// Load setting from parent method
		$settings = $this->get_parent_settings();		

		$this->private_key             = $settings['private_key'];
		$this->private_key_test        = $settings['private_key_test'];
		$this->test_mode               = $settings['test_mode'];
		$this->settle                  = $settings['settle'];
		$this->language                = $settings['language'];
		$this->country                 = $settings['country'];
		$this->debug                   = $settings['debug'];
		$this->payment_type            = $settings['payment_type'];
		$this->skip_order_lines        = $settings['skip_order_lines'];
		$this->enable_order_autocancel = $settings['enable_order_autocancel'];

		$this->pricetag = new ViaPay_Viabill_Pricetag();
		
		$this->pricetag_enabled		   = isset( $this->settings['pricetag_enabled'] ) ? $this->settings['pricetag_enabled'] : $this->pricetag->get_gateway_settings('pricetag_enabled');
		$this->pricetag_on_product	   = isset( $this->settings['pricetag_on_product'] ) ? $this->settings['pricetag_on_product'] : $this->pricetag->get_gateway_settings('pricetag_on_product');
		$this->pricetag_on_cart		   = isset( $this->settings['pricetag_on_cart'] ) ? $this->settings['pricetag_on_cart'] : $this->pricetag->get_gateway_settings('pricetag_on_cart');
		$this->pricetag_on_checkout	   = isset( $this->settings['pricetag_on_checkout'] ) ? $this->settings['pricetag_on_checkout'] : $this->pricetag->get_gateway_settings('pricetag_on_checkout');
		$this->pricetag_product_hook   = isset( $this->settings['pricetag_product_hook'] ) ? $this->settings['pricetag_product_hook'] : $this->pricetag->get_gateway_settings('pricetag_product_hook');
		$this->pricetag_script  	   = isset( $this->settings['pricetag_script'] ) ? $this->settings['pricetag_script'] : $this->pricetag->get_gateway_settings('pricetag_script');


		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_' . strtolower( __CLASS__ ), array(
			$this,
			'return_handler'
		) );

		add_action( 'wp_enqueue_scripts', array( $this, 'register_client_script' ) );
		
		$this->init_pricetags();
	}	

	/**
	 * Initialise Settings Form Fields
	 * @return string|void
	 */
	public function init_form_fields() {

		$viabill_test_webshop_id = ViaPay_Viabill_Pricetag::TEST_PRICETAGS_MERCHANT_ID;
		$default_pricetag_script = "<script>(function(){var o=document.createElement('script');o.type='text/javascript';o.async=true;o.src='https://pricetag.viabill.com/script/{$viabill_test_webshop_id}';var s=document.getElementsByTagName('script')[0];s.parentNode.insertBefore(o,s);})();</script>";

		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Enable/Disable', 'viapay-checkout-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable plugin', 'viapay-checkout-gateway' ),
				'default' => 'yes'
			),
			'title'          => array(
				'title'       => __( 'Title', 'viapay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout', 'viapay-checkout-gateway' ),
				'default'     => __( 'Viapay - ViaBill', 'viapay-checkout-gateway' )
			),
			'description'    => array(
				'title'       => __( 'Description', 'viapay-checkout-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout', 'viapay-checkout-gateway' ),
				'default'     => __( 'Buy now, pay later', 'viapay-checkout-gateway' ),
			),		
			'pricetag_general'                        => array(
				'id'   => 'pricetag_general',
				'title' => __( 'PriceTag general', 'viapay-checkout-gateway' ),
				'type' => 'title',
				'description' => __( 'Enable ViaBill\'s PriceTags to obtain the best possible conversion, and inform your customers about ViaBill.', 'viapay-checkout-gateway' ),
			),
			'pricetag_enabled'                        => array(
				'id'      => 'pricetag_enabled',
				'title'   => __( 'Enable PriceTags', 'viapay-checkout-gateway' ),
				'type'    => 'checkbox',
				'description'    => __( 'Enable display of PriceTags', 'viapay-checkout-gateway' ),
				'default' => 'yes',
			),				
			'pricetag_locations'                      => array(
				'id'   => 'pricetag_locations',
				'title' => __( 'PriceTag locations', 'viapay-checkout-gateway' ),
				'type' => 'title',
				'description' => __( 'Enable display of PriceTags for each location separately (PriceTags need to be enabled for location settings to take effect). Following sections contain advanced settings for each location.', 'viapay-checkout-gateway' ),
			),
			'pricetag_on_product'                     => array(
				'id'      => 'pricetag_on_product',
				'title'   => __( 'Product page', 'woocommerce_gateway-viapay-checkout' ),
				'type'    => 'checkbox',
				'description'    => __( 'Show on product page', 'viapay-checkout-gateway' ),
				'default' => 'yes',
			),
			'pricetag_on_cart'                        => array(
				'id'      => 'pricetag_on_cart',
				'title'   => __( 'Cart page', 'viapay-checkout-gateway' ),
				'type'    => 'checkbox',
				'description'    => __( 'Show on cart summary', 'viapay-checkout-gateway' ),
				'default' => 'yes',
			),
			'pricetag_on_checkout'                    => array(
				'id'      => 'pricetag_on_checkout',
				'title'   => __( 'Checkout page', 'viapay-checkout-gateway' ),
				'type'    => 'checkbox',
				'description'    => __( 'Show on checkout', 'viapay-checkout-gateway' ),
				'default' => 'yes',
			),					
			'pricetag_product'                        => array(
				'id'   => 'pricetag_product',
				'title' => __( 'PriceTag product page', 'viapay-checkout-gateway' ),
				'type' => 'title',
			),
			'pricetag_position_product'               => array(
				'id'      => 'pricetag_position_product',
				'title'    => __( 'Product PriceTag position', 'viapay-checkout-gateway' ),
				'type'    => 'text',
				'description'    => __( 'A Query selector of the element before which a PriceTag will be inserted on the product page. If left empty the PriceTag will be inserted after the price.', 'viapay-checkout-gateway' ),
				'default' => '',
			),
			'pricetag_product_hook'                  => array(
				'id'      => 'pricetag_product_hook',
				'title'    => __( 'Product PriceTag Hook', 'viapay-checkout-gateway' ),
				'type'    => 'select',
				'description'    => __( 'Here you can change the default hook that will trigger the display of the PriceTag on the product page.', 'viapay-checkout-gateway' ),
				'default' => 'woocommerce_single_product_summary',
				'options' => array(
				'woocommerce_single_product_summary' => 'woocommerce_single_product_summary',        
				'woocommerce_before_add_to_cart_form' => 'woocommerce_before_add_to_cart_form'
				)
			),
			'pricetag_script'          => array(
				'id'      => 'pricetag_script',
				'title'    => __( 'PriceTag Script', 'viapay-checkout-gateway' ),
				'type'    => 'textarea',
				'description'    => __( 'The javascript code to fetch the actual Javascript file from the ViaBill server.', 'viapay-checkout-gateway' ),
				'default' => $default_pricetag_script,
			),
			'pricetag_product_dynamic_price'          => array(
				'id'      => 'pricetag_product_dynamic_price',
				'title'    => __( 'Product dynamic price selector', 'viapay-checkout-gateway' ),
				'type'    => 'text',
				'description'    => sprintf( __( 'A Query selector for the element that contains the total price of the product on the single product page. In some cases it may prove practical to use the following selector: %1$s. With this selector the element will be found using this logic: %2$s.', 'viapay-checkout-gateway' ), '<code>' . esc_html( '<closest>|<actual element>' ) . '</code>', '<code>' . esc_html( 'pricetag.closest(<closest>).find(<actual_element>)' ) . '</code>' ),
				'default' => '',
			),
			'pricetag_product_dynamic_price_trigger'  => array(
				'id'      => 'pricetag_product_dynamic_price_trigger',
				'title'    => __( 'Product dynamic price trigger', 'viapay-checkout-gateway' ),
				'type'    => 'text',
				'description'    => sprintf( __( 'If the price is variable then it is possible to trigger an %1$s. %2$s selector is also supported using this attribute.', 'viapay-checkout-gateway' ), '<code>vb-update-price event</code>', '<code>' . esc_html( '<closest>|<actual elements>' ) . '</code>' ),
				'default' => '',
			),
			'pricetag_style_product'                  => array(
				'id'      => 'pricetag_style_product',
				'title'    => __( 'Product PriceTag CSS style', 'viapay-checkout-gateway' ),
				'type'    => 'text',
				'description'    => __( 'Here you can add your own custom CSS style to the PriceTag wrapper. Please enter CSS properties following this example: "margin-left: 20px; padding: 10px;".', 'viapay-checkout-gateway' ),
				'default' => '',
			),    					
			'pricetag_cart'                           => array(
				'id'   => 'pricetag_cart',
				'title' => __( 'PriceTag cart page', 'viapay-checkout-gateway' ),
				'type' => 'title',
			),
			'pricetag_position_cart'                  => array(
				'id'      => 'pricetag_position_cart',
				'title'   => __( 'Cart PriceTag position', 'viapay-checkout-gateway' ),
				'type'    => 'text',
				'description'    => __( 'A Query selector of the element before which a PriceTag will be inserted on the cart page. If left empty the PriceTag will be inserted afer cart totals.', 'viapay-checkout-gateway' ),
				'default' => '',
			),
			'pricetag_cart_dynamic_price'             => array(
				'id'      => 'pricetag_cart_dynamic_price',
				'title'   => __( 'Cart dynamic price selector', 'viapay-checkout-gateway' ),
				'type'    => 'text',
				'description'    => __( 'A Query selector for the element that contains the total price on the cart.', 'viapay-checkout-gateway' ),
				'default' => '',
			),
			'pricetag_cart_dynamic_price_trigger'     => array(
				'id'      => 'pricetag_cart_dynamic_price_trigger',
				'title'   => __( 'Cart dynamic price trigger', 'viapay-checkout-gateway' ),
				'type'    => 'text',
				'description'    => __( 'A Query selector for the trigger element on the cart page.', 'viapay-checkout-gateway' ),
				'default' => '',
			),
			'pricetag_style_cart'                     => array(
				'id'      => 'pricetag_style_cart',
				'title'   => __( 'Cart PriceTag CSS style', 'viapay-checkout-gateway' ),
				'type'    => 'text',
				'description'    => __( 'Here you can add your own custom CSS style to the PriceTag wrapper. Please enter CSS properties following this example: "margin-left: 20px; padding: 10px;".', 'viapay-checkout-gateway' ),
				'default' => '',
			),				
			'pricetag_checkout'                       => array(
				'id'   => 'pricetag_checkout',
				'title' => __( 'PriceTag checkout page', 'viapay-checkout-gateway' ),
				'type' => 'title',
			),
			'pricetag_position_checkout'              => array(
				'id'      => 'pricetag_position_checkout',
				'title'    => __( 'Checkout PriceTag position', 'viapay-checkout-gateway' ),
				'type'    => 'text',
				'description'    => __( 'A Query selector of the element before which a PriceTag will be inserted on the checkout page. If left empty the PriceTag will be inserted, depending if the ViaBill payment gateway is enabled, in the payment method description or under the list of payment methods.', 'viapay-checkout-gateway' ),
				'default' => '',
			),
			'pricetag_checkout_dynamic_price'         => array(
				'id'      => 'pricetag_checkout_dynamic_price',
				'title'    => __( 'Checkout dynamic price selector', 'viapay-checkout-gateway' ),
				'type'    => 'text',
				'description'    => __( 'A Query selector for the element that contains the total price on the checkout page.', 'viapay-checkout-gateway' ),
				'default' => '',
			),
			'pricetag_checkout_dynamic_price_trigger' => array(
				'id'      => 'pricetag_checkout_dynamic_price_trigger',
				'title'    => __( 'Checkout dynamic price trigger', 'viapay-checkout-gateway' ),
				'type'    => 'text',
				'description'    => __( 'A Query selector for the trigger element on the checkout page.', 'viapay-checkout-gateway' ),
				'default' => '',
			),
			'pricetag_style_checkout'                 => array(
				'id'      => 'pricetag_style_checkout',
				'title'    => __( 'Checkout PriceTag CSS style', 'viapay-checkout-gateway' ),
				'type'    => 'text',
				'description'    => __( 'Here you can add your own custom CSS style to the PriceTag wrapper. Please enter CSS properties following this example: "margin-left: 20px; padding: 10px;".', 'viapay-checkout-gateway' ),
				'default' => '',
			),
			
		);
	}

	 /**
     * Register plugin's client JS script.
     */
    public function register_client_script() {		
		$js_file = esc_url( plugins_url( '/assets/js/viabill.js', dirname( __FILE__ ) . '/../../' ) );

		wp_enqueue_script( 'viabill-client-script', $js_file, array( 'jquery' ), false, true );
	}	

	/**
	 * Display PriceTags, if enabled
	 */
	public function init_pricetags() {					
		$this->pricetag->maybe_show();
	}	
	
}

// Register Gateway
WC_ViapayCheckout::register_gateway( 'WC_Gateway_Viapay_Viabill' );
