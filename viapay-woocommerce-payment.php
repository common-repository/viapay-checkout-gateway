<?php
/*
 * Plugin Name: WooCommerce Viapay Checkout Gateway
 * Description: Provides a Payment Gateway through Viapay for WooCommerce.
 * Author: viapay
 * Author URI: http://viapay.com
 * Version: 1.0.2
 * Text Domain: viapay-checkout-gateway
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 5.9.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! defined( 'VIAPAY_PLUGIN_VERSION' ) ) {
	define( 'VIAPAY_PLUGIN_VERSION', '1.0.2' );
}

class WC_ViapayCheckout {
	const PAYMENT_METHODS = array(
		'viapay_checkout',
        'viapay_applepay',
        'viapay_klarna_pay_later',
        'viapay_klarna_pay_now',
        'viapay_mobilepay',
        'viapay_paypal',
        'viapay_resurs',
        'viapay_swish',
        'viapay_viabill',
        'viapay_googlepay',
        'viapay_vipps'
	);
	
	const DEFAULT_PAYMENT_METHODS = array(
		'visa',
		'viabill'
	);

	public static $db_version = '1.0.0';

	/**
	 * @var WC_Background_Viapay_Queue
	 */
	public static $background_process;

	/**
	 * Constructor
	 */
	public function __construct() {

		// Activation
		register_activation_hook( __FILE__, __CLASS__ . '::install' );

		// Uninstallation
		register_uninstall_hook( __FILE__, __CLASS__ . '::uninstall' );

		// Actions
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
		add_action( 'woocommerce_init', array( $this, 'woocommerce_init' ) );
		add_action( 'woocommerce_loaded', array( $this, 'woocommerce_loaded' ), 40 );
		add_action( 'init', __CLASS__ . '::may_add_notices' );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );

		// Add statuses for payment complete
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array(
			$this,
			'add_valid_order_statuses'
		), 10, 2 );


		// Add meta boxes
		//add_action( 'add_meta_boxes', __CLASS__ . '::add_meta_boxes' );

		// Add action buttons
		//add_action( 'woocommerce_order_item_add_action_buttons', __CLASS__ . '::add_action_buttons', 10, 1 );

		// Add scripts and styles for admin
		add_action( 'admin_enqueue_scripts', __CLASS__ . '::admin_enqueue_scripts' );

		// Add Footer HTML
		add_action( 'wp_footer', __CLASS__ . '::add_footer' );

		// Add Admin Backend Actions
		add_action( 'wp_ajax_viapay_capture', array(
			$this,
			'ajax_viapay_capture'
		) );

		add_action( 'wp_ajax_viapay_cancel', array(
			$this,
			'ajax_viapay_cancel'
		) );
		
		add_action( 'wp_ajax_viapay_refund', array(
			$this,
			'ajax_viapay_refund'
		) );
		
		add_action( 'wp_ajax_viapay_capture_partly', array(
			$this,
			'ajax_viapay_capture_partly'
		) );
		
		add_action( 'wp_ajax_viapay_refund_partly', array(
			$this,
			'ajax_viapay_refund_partly'
		) );

		$this->includes();

		// Add admin menu
		add_action( 'admin_menu', array( &$this, 'admin_menu' ), 99 );
		
		// add meta boxes
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );

		// Process queue
		if ( ! is_multisite() ) {
			add_action( 'customize_save_after', array( $this, 'maybe_process_queue' ) );
			add_action( 'after_switch_theme', array( $this, 'maybe_process_queue' ) );
		}

		add_action('parse_request', array($this, 'handle_cancel_url'));				

		// Check if the user needs to register/retrieve a private key for testing
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_registration' ) );

		require_once( dirname( __FILE__ ) . '/includes/class-wc-viapay-registration.php' );
		require_once(  dirname( __FILE__ ) . '/includes/class-wc-viapay-merchant-profile.php' );		  
		
		new Viapay_Registration( true );
		
		require_once( dirname( __FILE__ ) . '/includes/class-wc-viapay-account-creation.php' );
		
		new Viapay_AccountCreation( true );

		require_once( dirname( __FILE__ ) . '/includes/class-wc-viapay-support.php' );

		new Viapay_Support( true );
    }

    function handle_cancel_url() {
  	    if(preg_match('/checkout\/viapay_cancel/', $_SERVER["REQUEST_URI"]) ) {
            $order = wc_get_order( wc_clean($_GET['id']) );
            $payment_method = $order->get_payment_method();
            $gateways = WC()->payment_gateways()->get_available_payment_gateways();

            /** @var WC_Gateway_Viapay_Checkout $gateway */
            $gateway = 	$gateways[ $payment_method ];
            $result = $gateway->get_invoice_data( $order );

            if('viapay_checkout' == $payment_method && 'failed' == $result['state']) {
                if(isset($result['transactions'][0]['card_transaction']['acquirer_message'])) {
                    $error_message = $result['transactions'][0]['card_transaction']['acquirer_message'];
                    $order->add_order_note('Payment failed. Error from acquire: ' . $error_message);
                    wc_add_notice( __('Payment error: ', 'error') . $error_message, 'error' );
                }
                wp_redirect(wc_get_cart_url());
                exit();
            }
  	    }
    }

	/**
	 * Install
	 */
	public static function install() {
		if ( ! get_option( 'woocommerce_viapay_version' ) ) {
			add_option( 'woocommerce_viapay_version', self::$db_version );
		}
		if ( ! self::is_merchant_registered() ) {
			update_option( 'viapay_activation_redirect', true );
		}
	}

	/**
	 * Uninstall
	 */
	public static function uninstall() {
		self::delete_registration_data();
	}

	public function includes() {
		include_once( dirname( __FILE__ ) . '/includes/class-wc-viapay-order-statuses.php' );
	}

	/**
	 * Add relevant links to plugins page
	 *
	 * @param  array $links
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=viapay_checkout' ) . '">' . __( 'Settings', 'viapay-checkout-gateway' ) . '</a>'
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Init localisations and files
	 * @return void
	 */
	public function init() {
		// Localization
		load_plugin_textdomain( 'viapay-checkout-gateway', FALSE, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Show Upgrade notification
		if ( version_compare( get_option( 'woocommerce_viapay_version', self::$db_version ), self::$db_version, '<' ) ) {
			add_action( 'admin_notices', __CLASS__ . '::upgrade_notice' );
		}
	}

	/**
	 * WooCommerce Init
	 */
	public function woocommerce_init() {
		include_once( dirname( __FILE__ ) . '/includes/class-wc-background-viapay-queue.php' );
		self::$background_process = new WC_Background_Viapay_Queue();		
	}

	/**
	 * WooCommerce Loaded: load classes
	 * @return void
	 */
	public function woocommerce_loaded() {
		include_once( dirname( __FILE__ ) . '/includes/class-wc-payment-token-viapay.php' );
		include_once( dirname( __FILE__ ) . '/includes/interfaces/class-wc-payment-gateway-viapay-interface.php' );
		include_once( dirname( __FILE__ ) . '/includes/abstracts/abstract-wc-payment-gateway-viapay.php' );
		include_once( dirname( __FILE__ ) . '/includes/abstracts/abstract-wc-gateway-viapay.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-viapay-checkout.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-viapay-mobilepay.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-viapay-viabill.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-viapay-klarna-pay-later.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-viapay-klarna-pay-now.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-viapay-resurs.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-viapay-swish.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-viapay-paypal.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-viapay-apple-pay.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-viapay-googlepay.php' );
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-viapay-vipps.php' );		
	}

	/**
	 * Add notices
	 */
	public static function may_add_notices() {
		// Check if WooCommerce is missing
		if ( ! class_exists( 'WooCommerce', false ) || ! defined( 'WC_ABSPATH' ) ) {
			add_action( 'admin_notices', __CLASS__ . '::missing_woocommerce_notice' );
		}
	}

	/**
	 * Check if WooCommerce is missing, and deactivate the plugin if needs
	 */
	public static function missing_woocommerce_notice() {
		?>
        <div id="message" class="error">
            <p class="main">
                <strong>
                    <?php echo esc_html__(
                            'WooCommerce is inactive or missing.',
                            'viapay-checkout-gateway'
                    );
                    ?>
                </strong>
            </p>
            <p>
				<?php
				echo esc_html__(
				        'WooCommerce plugin is inactive or missing. Please install and active it.',
                        'viapay-checkout-gateway'
                );
				echo '<br />';
				echo sprintf(
					/* translators: 1: plugin name */                        
					esc_html__(
						'%1$s will be deactivated.',
						'viapay-checkout-gateway'
					),
					'WooCommerce Viapay Checkout Gateway'
				);

				?>
            </p>
        </div>
		<?php

		// Deactivate the plugin
		deactivate_plugins( plugin_basename( __FILE__ ), true );
	}

	/**
	 * Add Scripts
	 */
	public function add_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
        if( is_checkout() ) {
            wp_enqueue_style('wc-gateway-viapay-checkout', plugins_url('/assets/css/style' . $suffix . '.css', __FILE__), array());
        }
	}

	/**
	 * Add Footer HTML
	 */
	public static function add_footer() {
		$settings = get_option( 'woocommerce_viapay_checkout_settings' );
		if ( is_array( $settings ) && ! empty( $settings['logo_height'] ) ):
			$logo_height = $settings['logo_height'];
            if ( is_numeric( $logo_height ) ) {
	            $logo_height .= 'px';
            }
		?>
		<style type="text/css">
			.viapay-logos .viapay-logo img {
				height: <?php echo esc_html( $logo_height ); ?> !important;
				max-height: <?php echo esc_html( $logo_height ); ?> !important;
			}
		</style>
        <?php
        endif;

        if( is_checkout() ):
        ?>
        <style type="text/css">
            #payment li.payment_method_viapay_applepay {
                display: none;
            }

            #payment li.payment_method_viapay_googlepay {
                display: none;
            }

        </style>

        <script type="text/javascript">
            console.log('checkout page is loaded');
            jQuery('body').on('updated_checkout', function(){
                var className = 'wc_payment_method payment_method_viapay_applepay';
                if (true == Viapay.isApplePayAvailable()) {
                    for (let element of document.
                    getElementsByClassName(className)){
                        element.style.display = 'block';
                    }
                }

                var className = 'wc_payment_method payment_method_viapay_googlepay';
                Viapay.isGooglePayAvailable().then(isAvailable => {
                    if(true == isAvailable) {
                        for (let element of document.
                        getElementsByClassName(className)){
                            element.style.display = 'block';
                        }
                    }
                });
            });
        </script>

        <?php
        endif;
	}

	/**
	 * Dispatch Background Process
	 */
	public function maybe_process_queue() {
		self::$background_process->dispatch();
	}

	/**
	 * Register payment gateway
	 *
	 * @param string $class_name
	 */
	public static function register_gateway( $class_name ) {
		global $gateways;

		if ( ! $gateways ) {
			$gateways = array();
		}

		if ( ! isset( $gateways[ $class_name ] ) ) {
			// Initialize instance
			if ( $gateway = new $class_name ) {
				$gateways[] = $class_name;

				// Register gateway instance
				add_filter( 'woocommerce_payment_gateways', function ( $methods ) use ( $gateway ) {
					$methods[] = $gateway;

					return $methods;
				} );
			}
		}
	}

	/**
	 * Allow processing/completed statuses for capture
	 *
	 * @param array    $statuses
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public function add_valid_order_statuses( $statuses, $order ) {
		$payment_method = $order->get_payment_method();
		if ( in_array( $payment_method, self::PAYMENT_METHODS ) ) {
			$statuses = array_merge( $statuses, array(
				'processing',
				'completed'
			) );
		}

		return $statuses;
	}

	/**
	 * Add meta boxes in admin
	 * @return void
	 */
	public function add_meta_boxes() {
		/*global $post_id;
		if ( $order = wc_get_order( $post_id ) ) {
			$payment_method = $order->get_payment_method();
			if ( in_array( $payment_method, self::PAYMENT_METHODS ) ) {
				add_meta_box(
					'viapay_payment_actions',
					__( 'Viapay Payments Actions', 'viapay-checkout-gateway' ),
					__CLASS__ . '::order_meta_box_payment_actions',
					'shop_order',
					'side',
					'default'
				);
			}
		}*/
		
		global $post;
		$screen     = get_current_screen();
		$post_types = [ 'shop_order', 'shop_subscription' ];
	
		if ( in_array( $screen->id, $post_types, true ) && in_array( $post->post_type, $post_types, true ) ) {
			if ( $order = wc_get_order( $post->ID ) ) {
				$payment_method = $order->get_payment_method();
				if ( in_array( $payment_method, self::PAYMENT_METHODS ) ) {
					add_meta_box( 'viapay-payment-actions', __( 'Viapay Payment', 'viapay-checkout-gateway' ), [
						&$this,
						'meta_box_payment',
					], 'shop_order', 'side', 'high' );
					//add_meta_box( 'viapay-payment-actions', __( 'Viapay Subscription', 'viapay-checkout-gateway' ), [
					//	&$this,
					//	'meta_box_subscription',
					//], 'shop_subscription', 'side', 'high' );
				}
			}
		}
	}

	/**
	 * MetaBox for Payment Actions
	 * @return void
	 */
	public static function order_meta_box_payment_actions() {
		global $post_id;
		$order = wc_get_order( $post_id );

		// Get Payment Gateway
		$payment_method = $order->get_payment_method();
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();

		/** @var WC_Gateway_Viapay_Checkout $gateway */
		$gateway = 	$gateways[ $payment_method ];

		wc_get_template(
			'admin/payment-actions.php',
			array(
				'gateway'    => $gateway,
				'order'      => $order,
				'order_id'   => $post_id,
			),
			'',
			dirname( __FILE__ ) . '/templates/'
		);
	}

	/**
	 * @param WC_Order $order
	 */
	public static function add_action_buttons( $order ) {
		$payment_method = $order->get_payment_method();
		if ( in_array( $payment_method, self::PAYMENT_METHODS ) ) {
			// Get Payment Gateway
			$payment_method = $order->get_payment_method();
			$gateways = WC()->payment_gateways()->get_available_payment_gateways();

			/** @var WC_Gateway_Viapay_Checkout $gateway */
			$gateway = 	$gateways[ $payment_method ];

			wc_get_template(
				'admin/action-buttons.php',
				array(
					'gateway'    => $gateway,
					'order'      => $order
				),
				'',
				dirname( __FILE__ ) . '/templates/'
			);
		}
	}

	/**
	 * Enqueue Scripts in admin
	 *
	 * @param $hook
	 *
	 * @return void
	 */
	public static function admin_enqueue_scripts( $hook ) {
		if ( $hook === 'post.php' ) {
			// Scripts
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_register_script(
                'viapay-js-input-mask',
                plugin_dir_url( __FILE__ ) . 'assets/js/jquery.inputmask' . $suffix . '.js',
                array( 'jquery'),
                '5.0.3'
            );
			wp_register_script(
                'viapay-admin-js',
                plugin_dir_url( __FILE__ ) . 'assets/js/admin' . $suffix . '.js',
                array(
                    'jquery',
	                'viapay-js-input-mask'
                )
            );
			wp_enqueue_style( 'wc-gateway-viapay-checkout', plugins_url( '/assets/css/style' . $suffix . '.css', __FILE__ ), array(), FALSE, 'all' );

			// Localize the script
			$translation_array = array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'text_wait' => __( 'Please wait...', 'viapay-checkout-gateway' ),
			);
			wp_localize_script( 'viapay-admin-js', 'Viapay_Admin', $translation_array );

			// Enqueued script with localized data
			wp_enqueue_script( 'viapay-admin-js' );
		}
		wp_enqueue_style( 'wc-gateway-admin-css', plugins_url( '/assets/css/admin.css', __FILE__ ), array(), FALSE, 'all' );
	}

	/**
	 * Action for Capture
	 */
	public function ajax_viapay_capture() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'viapay' ) ) {
			exit( 'No naughty business' );
		}

		$order_id = (int) sanitize_text_field($_REQUEST['order_id']);
		$order = wc_get_order( $order_id );

		try {
			// Get Payment Gateway
			$payment_method = $order->get_payment_method();
			$gateways = WC()->payment_gateways()->get_available_payment_gateways();

			/** @var WC_Gateway_Viapay_Checkout $gateway */
			$gateway = 	$gateways[ $payment_method ];
			$gateway->capture_payment( $order );
			wp_send_json_success( __( 'Capture success.', 'viapay-checkout-gateway' ) );
		} catch ( Exception $e ) {
			$message = $e->getMessage();
			wp_send_json_error( $message );
		}
	}

	/**
	 * Action for Cancel
	 */
	public function ajax_viapay_cancel() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'viapay' ) ) {
			exit( 'No naughty business' );
		}

		$order_id = (int) sanitize_text_field($_REQUEST['order_id']);
		$order = wc_get_order( $order_id );
		
		//
		// Check if the order is already cancelled
		// ensure no more actions are made
		//
		if ( $order->get_meta( '_viapay_order_cancelled', true ) === "1" ) {
			wp_send_json_success( __( 'Order already cancelled.', 'viapay-checkout-gateway' ) );
			return;
		}

		try {
			// Get Payment Gateway
			$payment_method = $order->get_payment_method();
			$gateways = WC()->payment_gateways()->get_available_payment_gateways();

			/** @var WC_Gateway_Viapay_Checkout $gateway */
			$gateway = 	$gateways[ $payment_method ];
			
			// Check if the payment can be cancelled
			// $order->update_meta_data( '_' . $key, $value );
			// order->get_meta( '_viapay_token', true )
			if ($gateway->can_cancel( $order )) {
				$gateway->cancel_payment( $order );
			} 
			
			//
			// Mark the order as cancelled - no more communciation to viapay is done!
			// 
			$order->update_meta_data( '_viapay_order_cancelled', 1 );
			$order->save_meta_data();
			
			// Return success
			wp_send_json_success( __( 'Cancel success.', 'viapay-checkout-gateway' ) );
		} catch ( Exception $e ) {
			$message = $e->getMessage();
			wp_send_json_error( $message );
		}
	}
	
	/**
	 * Action for Cancel
	 */
	public function ajax_viapay_refund() {
	if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'viapay' ) ) {
			exit( 'No naughty business' );
		}
		$amount = (int) sanitize_text_field($_REQUEST['amount']);
		$order_id = (int) sanitize_text_field($_REQUEST['order_id']);
		$order = wc_get_order( $order_id );

		try {
			// Get Payment Gateway
			$payment_method = $order->get_payment_method();
			$gateways = WC()->payment_gateways()->get_available_payment_gateways();

			/** @var WC_Gateway_Viapay_Checkout $gateway */
			$gateway = 	$gateways[ $payment_method ];
			$gateway->refund_payment( $order, $amount );
			wp_send_json_success( __( 'Refund success.', 'viapay-checkout-gateway' ) );
		} catch ( Exception $e ) {
			$message = $e->getMessage();
			wp_send_json_error( $message );
		}
	}
	
	/**
	 * Action for Cancel
	 */
	public function ajax_viapay_capture_partly() {

		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'viapay' ) ) {
			exit( 'No naughty business' );
		}
		
		$amount = sanitize_text_field($_REQUEST['amount']);
		$order_id = (int) sanitize_text_field($_REQUEST['order_id']);
		$order = wc_get_order( $order_id );
		
		$amount = str_replace(",", "", $amount);
		$amount = str_replace(".", "", $amount);
		
		try {
			// Get Payment Gateway
			$payment_method = $order->get_payment_method();
			$gateways = WC()->payment_gateways()->get_available_payment_gateways();

			/** @var WC_Gateway_Viapay_Checkout $gateway */
			$gateway = 	$gateways[ $payment_method ];
			$gateway->capture_payment( $order, (float)((float)$amount / 100) );
			wp_send_json_success( __( 'Capture partly success.', 'viapay-checkout-gateway' ) );

		} catch ( Exception $e ) {
			$message = $e->getMessage();
			wp_send_json_error( $message );
		}
	}
	
	/**
	 * Action for Cancel
	 */
	public function ajax_viapay_refund_partly() {
		
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'viapay' ) ) {
			exit( 'No naughty business' );
		}
		
		$amount = sanitize_text_field($_REQUEST['amount']);
		$order_id = (int) sanitize_text_field($_REQUEST['order_id']);
		$order = wc_get_order( $order_id );
		
		$amount = str_replace(",", "", $amount);
		$amount = str_replace(".", "", $amount);
		
		try {
			// Get Payment Gateway
			$payment_method = $order->get_payment_method();
			$gateways = WC()->payment_gateways()->get_available_payment_gateways();

			/** @var WC_Gateway_Viapay_Checkout $gateway */
			$gateway = 	$gateways[ $payment_method ];
			$gateway->refund_payment( $order, (float)((float)$amount / 100) );
			wp_send_json_success( __( 'Refund partly success.', 'viapay-checkout-gateway' ) );
		} catch ( Exception $e ) {
			$message = $e->getMessage();
			wp_send_json_error( $message );
		}
	}

	/**
	 * Provide Admin Menu items
	 */
	public function admin_menu() {
		// Add Upgrade Page
		global $_registered_pages;
		$hookname = get_plugin_page_hookname( 'wc-viapay-upgrade', '' );
		if ( ! empty( $hookname ) ) {
			add_action( $hookname, __CLASS__ . '::upgrade_page' );
		}
		$_registered_pages[ $hookname ] = true;
	}

	/**
	 * Upgrade Page
	 */
	public static function upgrade_page() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		// Run Database Update
		include_once( dirname( __FILE__ ) . '/includes/class-wc-viapay-update.php' );
		WC_Viapay_Update::update();

		echo esc_html__( 'Upgrade finished.', 'viapay-checkout-gateway' );
	}

	/**
	 * Upgrade Notice
	 */
	public static function upgrade_notice() {
		if ( current_user_can( 'update_plugins' ) ) {
			?>
			<div id="message" class="error">
				<p>
					<?php
					echo esc_html__( 'Warning! WooCommerce Viapay Checkout plugin requires to update the database structure.', 'viapay-checkout-gateway' );
					echo ' ' . sprintf( esc_html__( 'Please click %s here %s to start upgrade.', 'viapay-checkout-gateway' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-viapay-upgrade' ) ) . '">', '</a>' );
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Inserts the content of the API actions into the meta box
	 */
	public function meta_box_payment() {
	        global $post;
		
		if ( $order = wc_get_order( $post->ID ) ) {
			
			$payment_method = $order->get_payment_method();
			if ( in_array( $payment_method, self::PAYMENT_METHODS ) ) {
				
				do_action( 'woocommerce_viapay_meta_box_payment_before_content', $order );

				global $post_id;
				$order = wc_get_order( $post_id );

				// Get Payment Gateway
		        $gateways = WC()->payment_gateways()->payment_gateways();

                /** @var WC_Gateway_Viapay_Checkout $gateway */
				$gateway = isset($gateways[$payment_method]) ? $gateways[$payment_method] : null;

				if( is_object( $gateway ) ) {
                   try {
                        wc_get_template(
                            'admin/metabox-order.php',
                            array(
                                'gateway' => $gateway,
                                'order' => $order,
                                'order_id' => $order->get_id(),
                                'order_data' => $gateway->get_invoice_data($order)
                            ),
                            '',
                            dirname(__FILE__) . '/templates/'
                        );
                    } catch (Exception $e) {
                        // Silence is golden
                    }
                }

			}
		}
	}

	public function meta_box_subscription() {
	    $this->meta_box_payment();
	}
	
	/*
	 * Formats a minor unit value into float with two decimals
	 * @priceMinor is the amount to format
	 * @return the nicely formatted value
	 */
	public function format_price_decimals( $priceMinor ) {
		return number_format( $priceMinor / 100, 2, wc_get_price_decimal_separator(), '' );
	}
	
	/**
     * Formats a credit card nicely
     *
	 * @param string $cc is the card number to format nicely
	 *
	 * @return false|string the nicely formatted value
	 */
	public static function formatCreditCard( $cc ) {
		$cc = str_replace(array('-', ' '), '', $cc);
		$cc_length = strlen($cc);
		$newCreditCard = substr($cc, -4);

		for ( $i = $cc_length - 5; $i >= 0; $i-- ) {
			if ( (($i + 1) - $cc_length) % 4 == 0 ) {
				$newCreditCard = ' ' . $newCreditCard;
			}
			$newCreditCard = $cc[$i] . $newCreditCard;
		}

		for ( $i = 7; $i < $cc_length - 4; $i++ ) {
			if ( $newCreditCard[$i] == ' ' ) {
				continue;
			}
			$newCreditCard[$i] = 'X';
		}

		return $newCreditCard;
	}
	
	/**
     * Does the merchant has registered the site preferences?
     *
     * @return bool
     */
    public static function is_merchant_registered() {		
		$settings = get_option( 'woocommerce_viapay_gateway_settings', array());
		
		$test_private_key = (isset($settings['private_key_test']))?$settings['private_key_test']:null;
		$live_private_key = (isset($settings['private_key']))?$settings['private_key']:null;

		if ($test_private_key || $live_private_key) return true;

		return false;		
	}

	/**
     * Redirect to registration page if 'viapay_activation_redirect' option is
     * set to true.
     */
    public function maybe_redirect_to_registration() {
		if ( get_option( 'viapay_activation_redirect', false ) ) {
		  delete_option( 'viapay_activation_redirect' );		  	  
		  wp_safe_redirect( get_admin_url( null, 'admin.php?page=viapay-register' ) );
		  exit;
		}
	}

	/**
     * Create settings link depending on registration status.
     */
    public static function get_settings_link() {
		if ( ! self::is_merchant_registered() ) {
		  $link = get_admin_url( null, 'admin.php?page=viapay-register' );
		} else {
		  $link = get_admin_url( null, 'admin.php?page=wc-settings&tab=checkout&section=viapay_checkout' );
		}
  
		return $link;
	}

	/**
	 * Set ViaPay Gateway settings
	 */
	public static function set_gateway_settings($new_settings) {
		$settings = get_option('woocommerce_viapay_gateway_settings', array());

		foreach ($new_settings as $key => $value) {
			$settings[$key] = $value;
		}

		update_option('woocommerce_viapay_gateway_settings', $settings);						  		
	}	

	/**
	 * Get ViaPay Gateway settings
	 */
	public static function get_gateway_settings($key = null) {                  
		$settings = get_option('woocommerce_viapay_gateway_settings', array());
						  
		if (isset($key)) {
		  if (isset($settings[$key])) {
			return $settings[$key];
		  } else {
			return '';
		  }
		}
  
		return $settings;
	}	

	/**
     * Delete ViaPay Gateway settings
     *
     * @return void
     */
    public function delete_registration_data() {
		delete_option( 'woocommerce_viapay_gateway_settings' ); 
		delete_option( 'woocommerce_viapay_checkout_settings' );    
		delete_option( 'woocommerce_viapay_viabill_settings' );
		delete_option( 'viapay_test_api_key_request_date' );
		delete_option( 'viapay_account_creation_request_date' );						
	}
	
}

new WC_ViapayCheckout();
