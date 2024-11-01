<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Viapay_AccountCreation' ) ) {
  /**
   * Viapay_AccountCreation class
   *
   * @since 0.1
   */
  class Viapay_AccountCreation {

    /**
     * API's interface.
     *
     * @var Viapay_Connector
     */
    private $connector;

    /**
     * Merchant's profile, object which holds merchant's data.
     *
     * @var Viapay_Merchant_Profile
     */
    private $merchant;    

    /**
     * Settings page slug.
     *
     * @static
     * @var string
     */
    const SLUG = 'viapay-account-creation';

    /**
     * Contact email address
     */
    const VIAPAY_TECH_SUPPORT_EMAIL = 'sales@viabill.com';

    /**
     * Class constructor, initialize class attributes and hooks if $initialize
     * is set to true.
     *
     * @param boolean $initialize Defaults to false.
     */
    public function __construct( $initialize = false ) {
      require_once( __DIR__ . '/class-wc-viapay-connector.php' );
      require_once( __DIR__ . '/class-wc-viapay-merchant-profile.php' );      

      $this->merchant  = new Viapay_Merchant_Profile();
      $this->connector = new Viapay_Connector();

      $settings     = WC_ViapayCheckout::get_gateway_settings();      
      
      if ( $initialize ) {
        $this->init();
      }
    }

    /**
     * Initialize action hooks for account creation page
     *
     * @return void
     */
    public function init() {            
      if ( WC_ViapayCheckout::is_merchant_registered() ) {        
        add_action( 'admin_menu', array( $this, 'register_account_creation_page' ), 200 );        
      }
    }

    /**
     * Return account creation settings page URL.
     *
     * @static
     * @return string
     */
    public static function get_admin_url() {
      return get_admin_url( null, 'admin.php?page=' . self::SLUG );
    }

    /**
     * Display account creation or registration form if user not registered.
     */
    public function show() {

      if (isset($_REQUEST['ticket_info'])) {
         $result = $this->getAccountCreationRequestFormOutput();
         echo wp_kses_post($result);
         return;
      }        

      $params = $this->getAccountCreationRequestForm();
      if (isset($params['error'])) {
          echo '<div class="alert alert-danger" role="alert">'.esc_html__($params['error'], 'viapay-checkout-gateway').'</div>';
          return;
      } else {
         foreach ($params as $key => $value) {
           $$key = $value;
         }
      }  
      
      $action_url = $this->getActionURL();    

      $registration_data = WC_ViapayCheckout::get_gateway_settings();
      
      $merchant_name = $registration_data['contact_name'];
      $merchant_phone = $registration_data['phone'];
      $merchant_email = $registration_data['email'];
      $shop_url = $registration_data['shop_url'];      
      $shop_country = $registration_data['country'];
      $shop_language = $registration_data['language'];

      $countries = $this->connector->get_available_countries();      
      $languages = $this->connector->get_available_languages();      

      $terms_of_service_lang = strtolower(trim($langCode));
      switch ($terms_of_service_lang) {
        case 'us':
            $terms_of_use_url = 'https://viabill.com/us/legal/cooperation-agreement/';
            break;
        case 'es':
            $terms_of_use_url = 'https://viabill.com/es/legal/contrato-cooperacion/';
            break;
        case 'dk':
            $terms_of_use_url = 'https://viabill.com/dk/legal/cooperation-agreement/';
            break;
        default:
            $terms_of_use_url = 'https://viabill.com/dk/legal/cooperation-agreement/';
            break;
      }
      
      $recommended_payment_methods = $this->get_recommended_payment_methods();      

      ?>    

    <div class="wrap">
    <h2><?php echo __( 'ViaPay Account Creation Request', 'viapay-checkout-gateway' ); ?></h2>
    <br>
    <a class="button-secondary" href="<?php echo esc_url( WC_ViapayCheckout::get_settings_link() ); ?>"><?php echo __( 'ViaPay settings', 'viapay-checkout-gateway' ); ?></a>
    <br><br><br>  
    
    <h2><?php echo __( 'Account Creation Request Form', 'viapay-checkout-gateway');?></h2>
    <div class="alert alert-info" role="alert">
        <?php 
        echo __( 'Please fill out the form below and click on the', 'viapay-checkout-gateway');
        echo ' <em>';
        echo __( 'Send Account Creation Request', 'viapay-checkout-gateway');
        echo '</em> ';
        echo __( 'button to send your request', 'viapay-checkout-gateway'); 
        ?>        
    </div>
    <form id="tech_account_creation_form" action="<?php echo esc_url($action_url); ?>" method="post">
     
    <fieldset>
        <legend class="w-auto text-primary"><?php echo __( 'ViaPay Account Preferences', 'viapay-checkout-gateway'); ?></legend> 
        <div class="form-group">
            <label><?php echo __( 'Your Name', 'viapay-checkout-gateway');?></label>
            <input class="form-control" type="text" required="true" name="ticket_info[name]"
                 value="<?php echo esc_attr($merchant_name); ?>" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'Your Email', 'viapay-checkout-gateway');?></label>
            <input class="form-control" type="text" required="true" name="ticket_info[email]"
                 value="<?php echo esc_attr($merchant_email); ?>" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'Your Phone', 'viapay-checkout-gateway');?></label>
            <input class="form-control" type="text" required="true" name="ticket_info[phone]"
                 value="<?php echo esc_attr($merchant_phone); ?>" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'Shop/Website URL', 'viapay-checkout-gateway');?></label>
            <input class="form-control" type="text" required="true" name="ticket_info[shop_url]"
                 value="<?php echo esc_attr($shop_url); ?>" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'Shop/Country', 'viapay-checkout-gateway');?></label>
            <select class="form-control" type="text" required="true" name="ticket_info[country]" value="" >
                <option value="">
                    <?php echo __('Select country', 'viapay-checkout-gateway'); ?>
                </option>
            <?php            
            foreach ($countries as $country) {
                $value = $country['code'];
                $label = $country['name'];
                if ($value == $shop_country) {
                  echo '<option value="'.esc_attr($value).'" selected="selected">'.esc_html($label).'</option>';
                } else {
                  echo '<option value="'.esc_attr($value).'">'.esc_html($label).'</option>';
                }          
            }            
            ?>                
            </select>
        </div>
        <div class="form-group">
            <label><?php echo __( 'Shop/Language', 'viapay-checkout-gateway');?></label>
            <select class="form-control" type="text" required="true" name="ticket_info[language]" value="" >
                <option value="">
                    <?php echo __('Select language', 'viapay-checkout-gateway'); ?>
                </option>
            <?php
            foreach ($languages as $language) {
                $value = $language['code'];
                $label = $language['name'];
                if ($value == $shop_language) {
                    echo '<option value="'.esc_attr($value).'" selected="selected">'
                        .esc_html($label).'</option>';
                } else {
                    echo '<option value="'.esc_attr($value).'">'
                        .esc_html($label).'</option>';
                }          
            }            
            ?>                    
            </select>
        </div>
        <div class="form-group">
            <label><?php echo __( 'Message', 'viapay-checkout-gateway');?></label>
            <textarea class="form-control" name="ticket_info[message]" 
            placeholder="<?php echo __( 'Type an optional message here regarding your ViaPay account ...', 'viapay-checkout-gateway'); ?>" rows="10"></textarea>
        </div>
    </fieldset>
    <div class="form-group form-check">
        <input type="checkbox" value="accepted" required="true" checked 
         class="form-check-input" name="ticket_info[enable_suggested_payments]" id="enable_suggested_payments"/>
          <label class="form-check-label"><?php echo __( 'Yes, I want to activate the following recommended payment methods', 'viapay-checkout-gateway');?>
          <div class="recommended_payment_methods">
              <?php echo wp_kses_post($recommended_payment_methods); ?>
          </div>
    </div>
    <div class="form-group form-check">
        <input type="checkbox" value="accepted" required="true"
         class="form-check-input" name="terms_of_use" id="terms_of_use"/>
          <label class="form-check-label"><?php echo __( 'I have read and accept the', 'viapay-checkout-gateway');?>
           <a href="<?php echo esc_url($terms_of_use_url); ?>" target="_blank"><?php echo __( 'Terms and Conditions', 'viapay-checkout-gateway');?></a></label>
    </div>
    <button type="button" onclick="validateAndSubmit()" class="button-primary">
    <?php echo __( 'Send Account Creation Request', 'viapay-checkout-gateway');?></button>
    </form>
    </div>

    <script>
    function validateAndSubmit() {
        var form_id = "tech_account_creation_form";
        var error_msg = "";
        var valid = true;
        
        jQuery("#" + form_id).find("select, textarea, input").each(function() {
            if (jQuery(this).prop("required")) {
                if (!jQuery(this).val()) {
                    valid = false;
                    var label = jQuery(this).closest(".form-group").find("label").text();
                    error_msg += "* " + label + " <?php echo __('is required', 'viapay-checkout-gateway');?>\n";
                }
            }
        });
        
        if (jQuery("#terms_of_use").prop("checked") == false) {
            valid = false;
            error_msg += "* <?php echo __('You need to accept The Terms and Conditions.', 'viapay-checkout-gateway');?>\n";
        }
        
        if (valid) {
            jQuery("#" + form_id).submit();	
        } else {
            error_msg = "<?php echo __('Please correct the following errors and try again:', 'viapay-checkout-gateway'); ?>\n" + error_msg;
            alert(error_msg);
        }		
    }
    </script>
      
      <?php
    }
    
    protected function getAccountCreationRequestForm()
    {
        $params = array();

        try {
            // Get Module Version            
            $module_version = VIAPAY_PLUGIN_VERSION;
                                    
            // Get PHP info
            $php_version = phpversion();
            $memory_limit = ini_get('memory_limit');

            // Get WooCommerce Version
            $platform_version = '';
            if (defined('WC_VERSION')) {
              $platform_version = WC_VERSION;
            } else {
              $valid = false;
            }                              
            
            // Get Store Info
            $langCode = get_bloginfo('language');
            $currencyCode = get_woocommerce_currency();
            $storeName = get_bloginfo('name');
            $storeURL = get_home_url();

            // Get Viapay Config
            $storeCountry = WC()->countries->get_base_country();
            
            $storeEmail = get_bloginfo('admin_email');                
            
            $action_url = $this->getActionURL();
    
            $terms_of_service_lang = strtolower(trim($langCode));
            switch ($terms_of_service_lang) {
                case 'us':
                    $terms_of_use_url = 'https://viabill.com/us/legal/cooperation-agreement/';
                    break;
                case 'es':
                    $terms_of_use_url = 'https://viabill.com/es/legal/contrato-cooperacion/';
                    break;
                case 'dk':
                    $terms_of_use_url = 'https://viabill.com/dk/legal/cooperation-agreement/';
                    break;
                default:
                    $terms_of_use_url = 'https://viabill.com/dk/legal/cooperation-agreement/';
                    break;
            }                                
    
            $params = [
                'module_version'=>$module_version,
                'platform_version'=>$platform_version,
                'php_version'=>$php_version,
                'memory_limit'=>$memory_limit,
                'os'=>PHP_OS,                
                'action_url'=>$action_url,                
                'terms_of_use_url'=>$terms_of_use_url,
                'langCode'=>$langCode,
                'currencyCode'=>$currencyCode,
                'storeName'=>$storeName,
                'storeURL'=>$storeURL,
                'storeEmail'=>$storeEmail,
                'storeCountry'=>$storeCountry
            ];
        } catch (\Exception $e) {            
            $params['error'] = $e->getMessage();

            return $params;
        }
        
        return $params;
    }
    
    protected function getAccountCreationRequestFormOutput()
    {        
        // array values, they will be saniized later on                        
        $platform = sanitize_text_field($_REQUEST['shop_info']['platform']);
        $merchant_email = sanitize_email(trim($_REQUEST['ticket_info']['email']));
        $shop_url = sanitize_url($_REQUEST['shop_info']['url']);
        $contact_name = sanitize_text_field($_REQUEST['ticket_info']['name']);
        $message = sanitize_textarea_field($_REQUEST['ticket_info']['message']);
                
        $account_info_html = '<ul>';
        foreach ($_REQUEST['ticket_info'] as $key => $value) {
            if (!in_array($key, array('name', 'email', 'message'))) {
                $label = strtoupper(str_replace('_', ' ', sanitize_key($key)));                
                $account_info_html .= '<li><strong>'.esc_html($label).'</strong>: '.esc_attr(sanitize_text_field($value)).'</li>';
            }            
        }
        $account_info_html .= '</ul>';

        // Get Module Version            
        $module_version = VIAPAY_PLUGIN_VERSION;                                    
        // Get PHP info
        $php_version = phpversion();
        // Get WooCommerce Version
        $platform_version = '';
        if (defined('WC_VERSION')) {
          $platform_version = WC_VERSION;
        } else {
            $platform_version = 'Not found';
        }   
        $platform_info_html = '<ul>';
        $platform_info_html .= '<li><strong>Platform</strong>: WooCommerce</li>';
        $platform_info_html .= '<li><strong>Platform Version</strong>: '.esc_attr($platform_version).'</li>';
        $platform_info_html .= '<li><strong>Module Version</strong>: '.esc_attr($module_version).'</li>';
        $platform_info_html .= '</ul>';
                        
        $email_subject = "New ViaPay Account Creation Request";
        $email_body = "Dear support,\n<br/>You have received a new account creation request with ".
                       "the following details:\n";       
        $email_body .= "<table>";
        $email_body .= "<tr><td style='background: #eee;'><strong>Name:</strong></td><td>".
            esc_attr($contact_name)."</td></tr>";
        $email_body .= "<tr><td style='background: #eee;'><strong>Email:</strong></td><td>".
            esc_attr($merchant_email)."</td></tr>";
        $email_body .= "<tr><td style='background: #eee;'><strong>Message:</strong></td><td>".
            esc_attr($message)."</td></tr>";
        $email_body .= "</table>";
        $email_body .= "<h3>Account Details</h3>";
        $email_body .= $account_info_html;
        $email_body .= "<h3>E-Commerce Platfrom</h3>";
        $email_body .= $platform_info_html;
                
        $sender_email = $this->getSenderEmail($merchant_email);
        $to = self::VIAPAY_TECH_SUPPORT_EMAIL;        
        $support_email = self::VIAPAY_TECH_SUPPORT_EMAIL;

        $success = $this->sendMail($to, $merchant_email, $email_subject, $email_body);
        if (!$success) {
            // use another method
            $success = $this->sendMail($to, $merchant_email, $email_subject, $email_body, true);
        }
        
        if ($success) {
            $success_msg = '';
            $success_msg = __('Your request has been received successfully!', 'viapay-checkout-gateway').' '.
                __('We will get back to you soon at ', 'viapay-checkout-gateway')."<strong>{$merchant_email}</strong>. ".
                __('You may also contact us at ', 'viapay-checkout-gateway')."<strong>{$support_email}</strong>.";
            $body = "<div class='alert alert-success'><div class='alert-text'>
                <strong>".__('Success!', 'viapay-checkout-gateway')."</strong><br/>".
                $success_msg.
                "</div></div>";
            $current_date = date('Y-m-d H:i:s');
            update_option( 'viapay_account_creation_request_date', $current_date);            
        } else {
            $fail_msg = __('Could not email your request form to the technical support team. ', 'viapay-checkout-gateway').
                __('Please try again or contact us at ', 'viapay-checkout-gateway')."<strong>{$support_email}</strong>.";
            $body = "<div class='alert alert-danger'><div class='alert-text'>
                <strong>".__('Error', 'viapay-checkout-gateway')."</strong><br/>".
                $fail_msg.
                "</div></div>";
        }
        
        $html = $body;
   
        return $html;
    }

    protected function sendMail($to, $from, $email_subject, $email_body)
    {
        $success = false;
        
        $headers = "From: " . $from . "\r\n";
        $headers .= "Reply-To: ". $to . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        $phpMailer = 'mail';
        $success = $phpMailer($to, $email_subject, $email_body, $headers);
            
        return $success;
    }
    
    protected function getActionURL()
    {
        return $this->get_admin_url();        
    }
    
    protected function getSenderEmail($merchant_email)
    {
        $senderEmail = '';
        
        $site_host = wc_get_cart_url();
                
        // check if merchant email shares the same domain with the site host
        if (!empty($merchant_email)) {
            list($account, $domain) = explode('@', $merchant_email, 2);
            if (strpos($site_host, $domain)!== false) {
                $senderEmail = $merchant_email;
            }
        }
        
        if (empty($senderEmail)) {
            $senderEmail = get_option( 'admin_email' );            
        }
        
        # sanity check
        if (empty($senderEmail)) {
            $domain_name = $site_host;

            if (strpos($site_host, '/')!==false) {
                $parts = explode('/', $site_host);
                foreach ($parts as $part) {
                    if (strpos($part, '.')!==false) {
                        $domain_name = $part;
                        break;
                    }
                }
            }

            $parts = explode('.', $domain_name);
            $parts_n = count($parts);
            $sep = '';
            $senderEmail = 'noreply@';
            for ($i=($parts_n-2); $i<$parts_n; $i++) {
                $senderEmail .= $sep . $parts[$i];
                $sep = '.';
            }
        }
                    
        return $senderEmail;
    }

    /**
     * Get a string will all recommended payment methods
     */
    function get_recommended_payment_methods() {
        $payment_methods = 'Major Debit/Credit Cards (VISA, VISA/Dankort, VISA Electron, MasterCard), ViaBill.';
        return $payment_methods;
    }
        
    /**
     * Register submenu page for the account_creation.
     *
     * @return void
     */
    public function register_account_creation_page() {       
        $account_creation_request_date = get_option( 'viapay_account_creation_request_date', '');		
        if (empty($account_creation_request_date)) {
            add_submenu_page(
                'woocommerce',
                __( 'ViaPay Account Creation', 'viapay-checkout-gateway' ),
                __( 'ViaPay Account Creation', 'viapay-checkout-gateway' ),
                'manage_woocommerce',
                self::SLUG,
                array( $this, 'show' )
            );
        }         
    }
  }
}

