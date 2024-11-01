<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Viapay_Support' ) ) {
  /**
   * Viapay_Support class
   *
   * @since 0.1
   */
  class Viapay_Support {

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
    const SLUG = 'viapay-support';

    /**
     * Contact email address
     */
    const VIAPAY_TECH_SUPPORT_EMAIL = 'support@reepay.com';

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
     * Initialize action hooks for support page
     *
     * @return void
     */
    public function init() {            
      if ( WC_ViapayCheckout::is_merchant_registered() ) {        
        add_action( 'admin_menu', array( $this, 'register_support_page' ), 200 );        
      }
    }

    /**
     * Return support settings page URL.
     *
     * @static
     * @return string
     */
    public static function get_admin_url() {
      return get_admin_url( null, 'admin.php?page=' . self::SLUG );
    }

    /**
     * Display support or registration form if user not registered.
     */
    public function show() {
      
      if (isset($_REQUEST['ticket_info'])) {
         $result = $this->getContactFormOutput();
         echo wp_kses_post($result);
         return;
      }        

      $params = $this->getContactForm();
      if (isset($params['error'])) {
          echo '<div class="alert alert-danger" role="alert">'.esc_html__($params['error'], 'viapay-checkout-gateway').'</div>';
          return;
      } else {
         foreach ($params as $key => $value) {
           $$key = $value;
         }
      }  
      
      $action_url = $this->getActionURL();

      $terms_of_service_lang = strtolower(trim($langCode));
        switch ($terms_of_service_lang) {
            case 'us':
                //$terms_of_use_url = 'https://viapay.com/us/legal/cooperation-agreement/';
                $terms_of_use_url = 'https://viabill.com/dk/viapay/';
                break;
            case 'es':
                //$terms_of_use_url = 'https://viapay.com/es/legal/contrato-cooperacion/';
                $terms_of_use_url = 'https://viabill.com/dk/viapay/';
                break;
            case 'dk':
                //$terms_of_use_url = 'https://viapay.com/dk/legal/cooperation-agreement/';
                $terms_of_use_url = 'https://viabill.com/dk/viapay/';
                break;
            default:
                //$terms_of_use_url = 'https://viapay.com/dk/legal/cooperation-agreement/';
                $terms_of_use_url = 'https://viabill.com/dk/viapay/';
                break;
        }  

      ?>    

    <div class="wrap">
    <h2><?php echo __( 'ViaPay Support', 'viapay-checkout-gateway' ); ?></h2>
    <br>
    <a class="button-secondary" href="<?php echo esc_url( WC_ViapayCheckout::get_settings_link() ); ?>"><?php echo __( 'ViaPay settings', 'viapay-checkout-gateway' ); ?></a>
    <br><br><br>  
    
    <h2><?php echo __( 'Support Request Form');?></h2>
    <div class="alert alert-info" role="alert">
        <?php 
        echo __( 'Please fill out the form below and click on the', 'viapay-checkout-gateway');
        echo ' <em>';
        echo __( 'Send Support Request', 'viapay-checkout-gateway');
        echo '</em> ';
        echo __( 'button to send your request', 'viapay-checkout-gateway'); 
        ?>        
    </div>
    <form id="tech_support_form" action="<?php echo esc_url($action_url); ?>" method="post">
    <fieldset>
        <legend class="w-auto text-primary"><?php echo __( 'Issue Description', 'viapay-checkout-gateway'); ?></legend>        
        <div class="form-group">
            <label><?php echo __( 'Your Name', 'viapay-checkout-gateway');?></label>
            <input class="form-control" type="text" required="true" name="ticket_info[name]"
                 value="" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'Your Email', 'viapay-checkout-gateway');?></label>
            <input class="form-control" type="text" required="true" name="ticket_info[email]"
                 value="" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'Message', 'viapay-checkout-gateway');?></label>
            <textarea class="form-control" name="ticket_info[issue]" 
            placeholder="<?php echo __( 'Type your issue description here ...', 'viapay-checkout-gateway'); ?>" rows="10" required="true"></textarea>
        </div>
    </fieldset>
    <fieldset>
        <legend class="w-auto text-primary"><?php echo __( 'Eshop Info', 'viapay-checkout-gateway');?></legend>
        <div class="form-group">
            <label><?php echo __( 'Store Name', 'viapay-checkout-gateway');?></label>
            <input class="form-control" type="text" required="true"
                 value="<?php echo esc_attr($storeName); ?>" name="shop_info[name]" />
        </div>                
        <div class="form-group">
            <label><?php echo __( 'Store URL', 'viapay-checkout-gateway');?></label>
            <input class="form-control" type="text" required="true"
             value="<?php echo esc_url($storeURL); ?>" name="shop_info[url]" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'Store Email', 'viapay-checkout-gateway');?></label>
            <input class="form-control" type="text" required="true"
             value="<?php echo sanitize_email($storeEmail); ?>" name="shop_info[email]" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'Eshop Country', 'viapay-checkout-gateway');?></label>
            <input class="form-control" type="text" required="true"
             value="<?php echo esc_attr($storeCountry); ?>" name="shop_info[country]" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'Eshop Language', 'viapay-checkout-gateway');?></label>
            <input class="form-control" type="text" required="true"
             value="<?php echo esc_attr($langCode); ?>" name="shop_info[language]" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'Eshop Currency', 'viapay-checkout-gateway');?></label>
            <input class="form-control" type="text" required="true"
             value="<?php echo esc_attr($currencyCode); ?>" name="shop_info[currency]" />
        </div>                
        <div class="form-group">
            <label><?php echo __( 'Module Version', 'viapay-checkout-gateway');?></label>
            <input class="form-control" type="text"
             value="<?php echo esc_attr($module_version); ?>" name="shop_info[addon_version]" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'WooCommerce Version', 'viapay-checkout-gateway');?></label>
            <input type="hidden" value="woocommerce" name="shop_info[platform]" />
            <input class="form-control" type="text"
             value="<?php echo esc_attr($platform_version); ?>" name="shop_info[platform_version]" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'PHP Version', 'viapay-checkout-gateway');?></label>
            <input class="form-control" type="text"
             value="<?php echo esc_attr($php_version); ?>" name="shop_info[php_version]" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'Memory Limit', 'viapay-checkout-gateway');?></label>
            <input class="form-control" type="text"
             value="<?php echo esc_attr($memory_limit); ?>" name="shop_info[memory_limit]" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'O/S', 'viapay-checkout-gateway');?></label>
            <input class="form-control" type="text"
             value="<?php echo esc_attr($os); ?>" name="shop_info[os]" />
        </div>
    </fieldset>            
    <div class="form-group form-check">
        <input type="checkbox" value="accepted" required="true"
         class="form-check-input" name="terms_of_use" id="terms_of_use"/>
          <label class="form-check-label"><?php echo __( 'I have read and accept the', 'viapay-checkout-gateway');?>
           <a href="<?php echo esc_url($terms_of_use_url); ?>" target="_blank"><?php echo __( 'Terms and Conditions', 'viapay-checkout-gateway');?></a></label>
    </div>           
    <button type="button" onclick="validateAndSubmit()" class="button-primary">
    <?php echo __( 'Send Support Request', 'viapay-checkout-gateway');?></button>
    </form>
    </div>

    <script>
    function validateAndSubmit() {
        var form_id = "tech_support_form";
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
    
    protected function getContactForm()
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
                    //$terms_of_use_url = 'https://viapay.com/us/legal/cooperation-agreement/';
                    $terms_of_use_url = 'https://viabill.com/dk/viapay/';
                    break;
                case 'es':
                    //$terms_of_use_url = 'https://viapay.com/es/legal/contrato-cooperacion/';
                    $terms_of_use_url = 'https://viabill.com/dk/viapay/';
                    break;
                case 'dk':
                    //$terms_of_use_url = 'https://viapay.com/dk/legal/cooperation-agreement/';
                    $terms_of_use_url = 'https://viabill.com/dk/viapay/';
                    break;
                default:
                    //$terms_of_use_url = 'https://viapay.com/dk/legal/cooperation-agreement/';
                    $terms_of_use_url = 'https://viabill.com/dk/viapay/';
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
    
    protected function getContactFormOutput()
    {        
        // array values, they will be saniized later on                
        $platform = sanitize_text_field($_REQUEST['shop_info']['platform']);                
        $merchant_email = sanitize_email(trim($_REQUEST['ticket_info']['email']));
        $shop_url =  sanitize_url($_REQUEST['shop_info']['url']);
        $contact_name = sanitize_text_field($_REQUEST['ticket_info']['name']);
        $message = sanitize_textarea_field($_REQUEST['ticket_info']['issue']);
        
        $shop_info_html = '<ul>';
        foreach ($_REQUEST['shop_info'] as $key => $value) {
            $label = strtoupper(str_replace('_', ' ', sanitize_key($key)));
            $shop_info_html .= '<li><strong>'.esc_html($label).'</strong>: '.esc_attr(sanitize_text_field($value)).'</li>';            
        }
        $shop_info_html .= '</ul>';        
                        
        $email_subject = "New ViaPay Support Request";
        $email_body = "Dear support,\n<br/>You have received a new support request with ".
                       "the following details:\n";
        $email_body .= "<h3>Ticket</h3>";
        $email_body .= "<table>";
        $email_body .= "<tr><td style='background: #eee;'><strong>Name:</strong></td><td>".
            esc_attr($contact_name)."</td></tr>";
        $email_body .= "<tr><td style='background: #eee;'><strong>Email:</strong></td><td>".
            esc_attr($merchant_email)."</td></tr>";
        $email_body .= "<tr><td style='background: #eee;'><strong>Issue:</strong></td><td>".
            esc_attr($message)."</td></tr>";
        $email_body .= "</table>";
        $email_body .= "<h3>Shop Info</h3>";
        $email_body .= $shop_info_html;
                
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
            $success_msg = __('Your request has been received successfully!', 'viapay-checkout-gateway').
                __('We will get back to you soon at ', 'viapay-checkout-gateway')."<strong>{$merchant_email}</strong>. ".
                __('You may also contact us at ', 'viapay-checkout-gateway')."<strong>{$support_email}</strong>.";
            $body = "<div class='alert alert-success'><div class='alert-text'>
                <strong>".__('Success!', 'viapay-checkout-gateway')."</strong><br/>".
                $success_msg.
                "</div></div>";
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
     * Register submenu page for the support.
     *
     * @return void
     */
    public function register_support_page() {          
      add_submenu_page(
        'woocommerce',
        __( 'ViaPay Support', 'viapay-checkout-gateway' ),
        __( 'ViaPay Support', 'viapay-checkout-gateway' ),
        'manage_woocommerce',
        self::SLUG,
        array( $this, 'show' )
      );
    }
  }
}

