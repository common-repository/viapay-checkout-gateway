<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Viapay_Registration' ) ) {
  /**
   * Viapay_Registration class
   */
  class Viapay_Registration {
    /**
     * API's interface.
     *
     * @var Viapay_Connector
     */
    private $connector;

    /**
     * Merchant's profile, object which holds merchant's data and it's methods.
     *
     * @var Viapay_Merchant_Profile
     */
    private $merchant;    

    /**
     * Was the registration already tried.
     *
     * @var bool
     */
    public static $tried_to_register = false;

    /**
     * ViaBill's URL for terms and conditions.
     *
     * @var string
     */
    private $terms_url = 'https://viabill.com/dk/legal/cooperation-agreement/';

    /**
     * Registration page slug.
     *
     * @static
     * @var string
     */
    const SLUG = 'viapay-register';

    /**
     * Class constructor, initialize class attributes and defines hooked methods.
     *
     * @param boolean $register_settings_page Defaults to false.
     */
    public function __construct( $register_settings_page = false ) {      
      require_once( dirname( __FILE__ ) . '/class-wc-viapay-connector.php' );
      require_once( dirname( __FILE__ ) . '/class-wc-viapay-merchant-profile.php' );

      $this->connector = new Viapay_Connector();
      $this->merchant  = new Viapay_Merchant_Profile();
      
      add_action( 'admin_init', array( $this, 'maybe_process' ) );

      if ( $register_settings_page && ! WC_ViapayCheckout::is_merchant_registered() ) {
        add_action( 'admin_menu', array( $this, 'register_settings_page' ), 200 );        
      }
    }

    /**
     * Return registration page admin URL.
     *
     * @return string
     */
    public static function get_admin_url() {                                  
      return get_admin_url( null, 'admin.php?page=' . self::SLUG );
    }

    /**
     * Trigger process method and, if successful, redirect to notices page.
     */
    public function maybe_process() {
      if ( self::$tried_to_register ) {
        return false;
      }
      self::$tried_to_register = true;

      $response = $this->process();
      if ( is_array( $response ) ) {
        if ( isset( $response['success'] ) && $response['success'] ) {
          wp_safe_redirect( WC_ViapayCheckout::get_settings_link() );
          exit;
        } else {
          $this->reg_response = $response;
          add_action( 'admin_notices', array( $this, 'show_register_wp_notice' ) );
        }
      }
    }

    /**
     * Process registration or login (fetch data from $_POST) and return
     * an array with the following structure:
     *    ['success' => bool, 'message' => string]
     * or false if registration already tried.
     *
     * @return array|bool
     */
    private function process() {
      $response = array(
        'success' => false,
        'message' => __( 'Something went wrong with your request processing, please try again.', 'viapay-checkout-gateway' ),
      );

      if ( isset( $_POST[ self::SLUG ] ) && isset( $_POST[ self::SLUG . '-nonce' ] ) ) {
        $nonce = sanitize_key( $_POST[ self::SLUG . '-nonce' ] );
        if ( wp_verify_nonce( $nonce, self::SLUG . '-action' ) === 1 ) {
          $contact_name = isset( $_POST['viapay-reg-contact-name'] ) ? sanitize_text_field( wp_unslash( $_POST['viapay-reg-contact-name'] ) ) : '';
          $shop_url = isset( $_POST['viapay-reg-shop-url'] ) ? sanitize_url( wp_unslash( $_POST['viapay-reg-shop-url'] ) ) : '';
          $phone = isset( $_POST['viapay-reg-phone'] ) ? sanitize_text_field( wp_unslash( $_POST['viapay-reg-phone'] ) ) : '';
          $email = isset( $_POST['viapay-reg-email'] ) ? sanitize_email( wp_unslash( $_POST['viapay-reg-email'] ) ) : '';                    
          $country = isset( $_POST['viapay-reg-country'] ) ? sanitize_text_field( wp_unslash( $_POST['viapay-reg-country'] ) ) : '';
          $language = isset( $_POST['viapay-reg-language'] ) ? sanitize_text_field( wp_unslash( $_POST['viapay-reg-language'] ) ) : '';          

          $registration_data = array(
            'contact_name' => $contact_name,
            'shop_url' => $shop_url,
            'phone' => $phone,
            'email' => $email,
            'country' => $country,
            'language' => $language
          );
          
          $body = $this->connector->register( $registration_data );

          $this->process_response_body( $response, $body );          
          if ( $response['success'] ) {
            // store them locally
            $test_mode = true;
            $new_settings = $registration_data;
            if (isset($response['private_key'])) {              
              $new_settings['private_key'] = $response['private_key'];
              if (!empty($new_settings['private_key'])) $test_mode = false;
            }
            if (isset($response['private_key_test'])) {              
              $new_settings['private_key_test'] = $response['private_key_test'];
            }
            $new_settings['test_mode'] = ($test_mode)?'yes':'no';
            WC_ViapayCheckout::set_gateway_settings($new_settings);    
            delete_option( 'viapay_activation_redirect' );
          }          
        }
      } elseif ( isset( $_POST['viapay-login'] ) && isset( $_POST['viapay-login-nonce'] ) ) {
          $nonce = sanitize_key( $_POST['viapay-login-nonce'] );
          if ( wp_verify_nonce( $nonce, 'viapay-login-action' ) === 1 ) {
            
            $live_private_key = isset( $_POST['viapay-live-private-key'] ) ? sanitize_text_field( wp_unslash( $_POST['viapay-live-private-key'] ) ) : '';
            $test_private_key = isset( $_POST['viapay-test-private-key'] ) ? sanitize_text_field( wp_unslash( $_POST['viapay-test-private-key'] ) ) : '';

            // store them locally
            $new_settings = array();
            if (isset($response['private_key'])) {              
              if (!empty($new_settings['private_key'])) {
                $new_settings['private_key'] = $response['private_key'];
                $test_mode = false;
              }              
            }
            if (isset($response['private_key_test'])) {
              if (!empty($new_settings['private_key_test'])) {
                $new_settings['private_key_test'] = $response['private_key_test'];
              }              
            }
            $new_settings['test_mode'] = ($test_mode)?'yes':'no';
            WC_ViapayCheckout::set_gateway_settings($new_settings);     
            delete_option( 'viapay_activation_redirect' );            
        } else {
          // write it down to a log file
        }
      } else {
        return false;
      }
      return $response;
    }

    /**
     * Process registration response body from the ViaBill's API and define
     * $response's message and success bool flag.
     *
     * @param  array &$response
     * @param  array $body
     * @param  bool  $is_body   Defaults to false.
     * @return void
     */
    private function process_response_body( &$response, $body, $is_login = false ) {
      if ( ! $body ) {
        if ( $is_login ) {
          $response['message'] = __( 'Failed to login, please try again.', 'viapay-checkout-gateway' );
        } else {
          $response['message'] = __( 'Failed to register, please try again.', 'viapay-checkout-gateway' );
        }
        return;
      }      

      $error_messages = $this->connector->get_error_messages( $body );
      if ( is_string( $error_messages ) ) {
        $response['message'] = $error_messages;
      } else {                
        $response['success'] = true;          
        if ($is_login) {
          $response['message'] = __( 'User is successfully logged in.', 'viapay-checkout-gateway' );
        } else {
          $response['message'] = __( 'User is successfully registered.', 'viapay-checkout-gateway' );
        }        
        foreach ($body as $key => $value) {
           if (!empty($value)) $response[$key] = $value;
        }
      }      

    }

    /**
     * Echo merchant's registration/login form.
     */
    public function show() {
      $countries = $this->connector->get_available_countries();
      array_unshift(
        $countries,
        array(
          'code' => '',
          'name' => 'Choose Country',
        )
      );

      $languages = $this->connector->get_available_languages();
      array_unshift(
        $languages,
        array(
          'code' => '',
          'name' => 'Choose Language',
        )
      );

      ?>
      <form method="post">
        <h2><?php esc_html_e( 'New to ViaPay?', 'viapay-checkout-gateway' ); ?></h2>
        <p><?php esc_html_e( 'Enter the following information to generate your test API private key so you can start testing immediately.', 'viapay-checkout-gateway' ); ?></p>
        <table class="form-table">
          <tbody>
          <?php
            $current_user_id = get_current_user_id();
            $user_data       = get_userdata( $current_user_id );
            $user_phone      = get_user_meta( $current_user_id, 'billing_phone', true );
            ?>

            <?php
            $this->do_field(
              __( 'Contact name', 'viapay-checkout-gateway' ),
              array(
                'id'    => 'viapay-reg-contact-name',
                'name'  => 'viapay-reg-contact-name',
                'type'  => 'text',
                'value' => $user_data->display_name ? $user_data->display_name : '',
                'class' => 'input-text regular-input',
              )
            );
            ?>

            <?php
            $this->do_field(
              __( 'Phone number', 'viapay-checkout-gateway' ),
              array(
                'id'    => 'viapay-reg-phone',
                'name'  => 'viapay-reg-phone',
                'type'  => 'phone',
                'value' => $user_phone,
                'class' => 'input-text regular-input',
              )
            );
            ?>

            <?php
            $this->do_field(
              __( 'E-mail', 'viapay-checkout-gateway' ),
              array(
                'id'       => 'viapay-reg-email',
                'name'     => 'viapay-reg-email',
                'type'     => 'email',
                'required' => true,
              )
            );
            ?>            

            <?php
            $this->do_field(
              __( 'Shop URL (live)', 'viapay-checkout-gateway' ),
              array(
                'id'       => 'viapay-reg-shop-url',
                'name'     => 'viapay-reg-shop-url',
                'type'     => 'text',
                'value'    => get_site_url(),
                'class'    => 'input-text regular-input',
                'required' => true,
              )
            );
            ?>

            <?php
            $this->do_field(
              __( 'Country', 'viapay-checkout-gateway' ),
              array(
                'id'       => 'viapay-reg-country',
                'name'     => 'viapay-reg-country',
                'type'     => 'select',
                'class'    => 'select',
                'style'    => 'min-width: 215px;',
                'required' => true,
              ),
              $countries
            );
            ?>

            <?php
            $this->do_field(
              __( 'Language', 'viapay-checkout-gateway' ),
              array(
                'id'       => 'viapay-reg-language',
                'name'     => 'viapay-reg-language',
                'type'     => 'select',
                'class'    => 'select',
                'style'    => 'min-width: 215px;',
                'required' => true,
              ),
              $languages
            );
            ?>

            <?php
            $terms_label  = __( 'I\'ve read and accept the', 'viapay-checkout-gateway' ) . ' ';
            $terms_label .= '<a id="viapay-terms-link" href="' . esc_url($this->terms_url) . '" target="_blank">' . __( 'terms & conditions', 'viapay-checkout-gateway' ) . '</a>';

            $this->do_field(
              $terms_label,
              array(
                'id'       => 'viapay-terms',
                'name'     => 'viapay-terms',
                'type'     => 'checkbox',
                'required' => true,
              )
            );
            ?>            

            <?php $this->do_submit( self::SLUG, __( 'Submit', 'viapay-checkout-gateway' ), self::SLUG . '-action', self::SLUG . '-nonce' ); ?>
          </tbody>
        </table>
      </form>
      <form method="post">
        <table class="form-table">
          <tbody>
            <tr valign="top">
              <th scope="row" class="titledesc"></th>
              <td class="forminp">
                <h3>- <?php esc_html_e( 'OR', 'viapay-checkout-gateway' ); ?> -</h3>
              </td>
            </tr>
          </tbody>
        </table>
        <h2><?php esc_html_e( 'Already have a ViaPay account?', 'viapay-checkout-gateway' ); ?></h2>
        <p><?php esc_html_e( 'If you have a private API key you can enter it below.', 'viapay-checkout-gateway' ); ?></p>
        <table class="form-table">
          <tbody>
            <?php
            $this->do_field(
              __( 'Live Private Key', 'viapay-checkout-gateway' ),
              array(
                'id'       => 'viapay-live-private-key',
                'name'     => 'viapay-live-private-key',
                'type'     => 'text',
                'class'    => 'input-text regular-input',
                'required' => true,
              )
            );
            ?>

            <?php
            $this->do_field(
              __( 'Test Private Key', 'viapay-checkout-gateway' ),
              array(
                'id'       => 'viapay-test-private-key',
                'name'     => 'viapay-test-private-key',
                'type'     => 'text',
                'class'    => 'input-text regular-input',
                'required' => true,
              )
            );
            ?>
            
            <?php $this->do_submit( 'viapay-login', __( 'Submit', 'viapay-checkout-gateway' ), 'viapay-login-action', 'viapay-login-nonce' ); ?>
          </tbody>
        </table>
      </form>
      <?php
    }

    /**
     * Echo registration submit button HTML.
     *
     * @param string $id
     * @param string $label
     * @param string $nonce_action
     * @param string $nonce_name
     */
    private function do_submit( $id, $label, $nonce_action, $nonce_name ) {
      ?>
      <tr valign="top">
        <th scope="row" class="titledesc"></th>
        <td class="forminp">
          <input id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($id); ?>" class="button-primary woocommerce-save-button" type="submit" value="<?php echo esc_html($label); ?>">
          <?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
        </td>
      </tr>
      <?php
    }

    /**
     * Echo registration field's HTML.
     *
     * @param  string $label    Label for input or standalone
     * @param  array  $args     Different attributes:
     *                          array (
     *                            'id'       => 'test_id',
     *                            'name'     => 'test_name',
     *                            'value'    => '',
     *                            'required' => false
     *                          )
     * @param  array  $options  Defaults to empty array.
     */
    private function do_field( $label = '', $args = array(), $options = array() ) {
      array_walk(
        $args,
        function( $attr_val, $attr_name ) use ( &$attrs ) {
          $attrs .= esc_attr($attr_name) . '=\'' . esc_attr($attr_val) . '\' ';
        }
      );
      ?>
      <tr valign="top">
        <th scope="row" class="titledesc">
          <?php if ( ! empty( $label ) && 'checkbox' !== $args['type'] ) : ?>
            <label for="<?php echo esc_attr($args['id']); ?>"><?php echo esc_html($label); ?></label>
          <?php endif; ?>
        </th>
        <td class="forminp">
          <?php if ( 'select' === $args['type'] && $options ) : ?>
            <select 
            <?php
            array_walk(
              $args,
                function( $attr_val, $attr_name ) use ( &$attrs ) {
                  echo esc_attr($attr_name) . '=\'' . esc_attr($attr_val) . '\' ';
                }
              );
            ?>
            >
              <?php foreach ( $options as $option ) : ?>
                <option value="<?php echo esc_attr($option['code']); ?>"><?php echo esc_html($option['name']); ?></option>
              <?php endforeach; ?>
            </select>
          <?php else : ?>
            <input 
            <?php
            array_walk(
              $args,
                function( $attr_val, $attr_name ) use ( &$attrs ) {
                  echo esc_attr($attr_name) . '=\'' . esc_attr($attr_val) . '\' ';
                }
              );
            ?>
            >
            <?php if ( 'checkbox' === $args['type'] && ! empty( $label ) ) : ?>
              <label for="<?php echo esc_attr($args['id']); ?>"><?php echo $label; ?></label>
            <?php endif; ?>
          <?php endif; ?>
        </td>
      </tr>
      <?php
    }

    /**
     * Echo register notice HTML.
     */
    public function show_register_wp_notice() {
      $type    = isset( $this->reg_response['success'] ) && $this->reg_response['success'] ? 'success' : 'error';
      $message = isset( $this->reg_response['message'] ) ? $this->reg_response['message'] : false;
      if ( ! $message ) {
        if ( 'success' === $type ) {
          $message = __( 'Registration didn\'t went as expected but, for now, everything seems alright.', 'viapay-checkout-gateway' );
        } else {
          $message = __( 'Something went wrong with the registration, please try again.', 'viapay-checkout-gateway' );
        }
      }
      ?>
      <div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
          <p><?php echo esc_html( $message ); ?></p>
      </div>
      <?php
    }

    /**
     * Register WooCommerce settings subpage.
     */
    public function register_settings_page() {
      add_submenu_page(
        'woocommerce',
        __( 'ViaPay Register', 'viapay-checkout-gateway' ),
        __( 'ViaPay Register', 'viapay-checkout-gateway' ),
        'manage_woocommerce',
        self::SLUG,
        array( $this, 'show' )
      );
    }
  }
}
