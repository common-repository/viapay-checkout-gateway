<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'ViaPay_Viabill_Pricetag' ) ) {
  /**
   * Viabill_Pricetag class
   */
  class ViaPay_Viabill_Pricetag {      
    
    /**
	  * Common PriceTags Merchant ID for testing
	  */
	  const TEST_PRICETAGS_MERCHANT_ID = 'e52vqdD37fM%3D';

    /**
     * Array of languages where the key is supported language and the value is
     * actual language code which is used.
     *
     * @var array
     */
    public static $supported_languages = array(
      'da' => 'da',
      'en' => 'en',
      'es' => 'es',
      'eu' => 'es',
      'ca' => 'es'
    );

    /**
     * Array of currencies where the key is supported supported and the value is
     * actual currency code which is used.
     *
     * @var array
     */
    public static $supported_currencies = array(
      'usd' => 'USD',
      'eur' => 'EUR',
      'dkk' => 'DKK'      
    );

    /**
     * Array of countries where the key is supported supported and the value is
     * actual country code which is used.
     *
     * @var array
     */
    public static $supported_countries  = array(
      'es' => 'ES',
      'spain' => 'ES',
      'dk' => 'DK',
      'denmark' => 'DK',
      'us' => 'US',
      'usa' => 'US',
    );

    /**
     * Contains all the payment gateway settings values.
     *
     * @var array
     */
    private $settings;

    public function __construct() {            
      $this->settings = self::get_gateway_settings();
    }

    /**
     * Show priceTag on different pages if enabled on the wc settings panel.
     *
     * @return void
     */
    public function maybe_show() {      

      $payment_gateway_enabled = isset( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
      $pricetags_enabled       = isset( $this->settings['pricetag_enabled'] ) && 'yes' !== $this->settings['pricetag_enabled'];     
            
      // If PriceTag settings are not explicitly set fallback to payment gateway enabled setting.
      if ( ( ! isset( $this->settings['pricetag_enabled'] ) && ! $payment_gateway_enabled ) || $pricetags_enabled ) {
        return;
      }

      $valid_combination = self::getValidCountryLanguageCurrencyCombination();
      if (! $valid_combination ) {
         return;
      }

      if ( ( ! isset( $this->settings['pricetag_on_product'] ) && $payment_gateway_enabled ) || 'yes' === $this->settings['pricetag_on_product'] ) {        
        $action_hook_name = isset( $this->settings['pricetag_product_hook'] )? $this->settings['pricetag_product_hook'] : 'woocommerce_single_product_summary';
        // or 'woocommerce_before_add_to_cart_form'

        add_action( 'viabill_pricetag_on_single_product', array( 'ViaPay_Viabill_Pricetag', 'show_on_product' ) );
        add_action( $action_hook_name, array( 'ViaPay_Viabill_Pricetag', 'show_on_product' ) );        
      }
      if ( ( ! isset( $this->settings['pricetag_on_cart'] ) && $payment_gateway_enabled ) || 'yes' === $this->settings['pricetag_on_cart'] ) {
        add_action( 'viabill_pricetag_on_cart', array( 'ViaPay_Viabill_Pricetag', 'show_on_cart' ) );
        add_action( 'woocommerce_proceed_to_checkout', array( 'ViaPay_Viabill_Pricetag', 'show_on_cart' ) );
      }
      if ( ( ! isset( $this->settings['pricetag_on_checkout'] ) && $payment_gateway_enabled ) || 'yes' === $this->settings['pricetag_on_checkout'] ) {
        add_action( 'viabill_pricetag_on_checkout', array( 'ViaPay_Viabill_Pricetag', 'show_on_checkout' ) );
        add_action( 'woocommerce_review_order_before_payment', array( 'ViaPay_Viabill_Pricetag', 'show_on_checkout' ) );        
      }

      $this->append_script();
    }

    /**
     * Checks if the currently selected currency, language and country code are valid for the 
     * pricetags to appear.
     */
    public static function getValidCountryLanguageCurrencyCombination() {
      $valid = false;

      $currency = self::get_supported_currency();
      $language = self::get_supported_language();
      
      if ((!empty($language))&&(!empty($currency))) {
        $country = self::get_supported_country();
        switch ($country) {
          case 'US':
            if (($language != 'en')&& ($language != 'es')) {
              $language = 'en';
            }
            if ($currency != 'USD') {
               $valid = false;
            } else {              
              $valid = true;
            }            
            break;

          case 'ES':
            if (($language != 'en')&& ($language != 'es')) {
              $language = 'es';
            }
            if ($currency != 'EUR') {
               $valid = false;
            } else {              
              $valid = true;
            }
            break;
              
          case 'DK':
            if (($language != 'da')&& ($language != 'en')) {
              $language = 'da';
            }
            if ($currency != 'DKK') {
               $valid = false;
            } else {              
              $valid = true;
            }
            break;    

          default:
            // unsupported country, do nothing
            break;  
        }                 
      }         
      
      if ($valid) {
        $combination['language'] = $language;
        $combination['currency'] = $currency;
        $combination['country'] = $country;
        return $combination;
      }
      
      return false;
    }

    /**
     * Echo script after the </body> tag.
     * Uses 'wp_print_footer_scripts' action hook.
     *
     * @return void
     */
    private function append_script() {
      add_action(
        'wp_print_footer_scripts',
        function() {
          $pricetag_merchant_id = self::get_pricetag_merchant_id();
          if ( ! empty( $pricetag_merchant_id ) ) {            
            echo "<script>(function(){
              var o=document.createElement('script');o.type='text/javascript';
              o.async=true;o.src='https://pricetag.viabill.com/script/".esc_attr($pricetag_merchant_id)."';
              var s=document.getElementsByTagName('script')[0];s.parentNode.insertBefore(o,s);})();
              </script>";
          }          
        },
        100
      );
    }            
  
    /**
     * Echo data-dynamic-price HTML attribute if the value is available in the
     * plugin's settings under the key "pricetag-{$target}-dynamic-price".
     *
     * @param  string $target   Should be "product", "cart", or "checkout".
     * @param  array  $settings
     * @return void
     */
    public static function display_dynamic_price( $target, $settings ) {
      $name = 'pricetag_' . esc_attr($target) . '_dynamic_price';
      if ( isset( $settings[ $name ] ) && ! empty( $settings[ $name ] ) ) {
        echo 'data-dynamic-price="' . esc_attr($settings[ $name ]) . '"';
      }
    }

    /**
     * Echo data-dynamic-price-triggers HTML attribute if the value is available
     * in the plugin's settings under the key
     * "pricetag-{$target}-dynamic-price-triggers".
     *
     * @param  string $target   Should be "product", "cart", or "checkout".
     * @param  array  $settings
     * @return void
     */
    public static function display_dynamic_price_trigger( $target, $settings ) {
      $name = 'pricetag_' . esc_attr($target) . '_dynamic-price-trigger';
      if ( isset( $settings[ $name ] ) && ! empty( $settings[ $name ] ) ) {
        echo 'data-dynamic-price-triggers="' . esc_attr($settings[ $name ]) . '"';
      }
    }

    /**
     * Display pricetag on the single product page.
     *
     * @static
     * @return void
     */
    public static function show_on_product() {
      if ( ! is_product() ) {
        return;
      }

      global $product;      
      self::show( 'product', 'product', wc_get_price_including_tax( $product ) );
    }

    /**
     * Dislay pricetag on the cart page.
     *
     * @static
     * @return void
     */
    public static function show_on_cart() {      
      $totals = WC()->cart->get_totals();
      $total  = isset( $totals['total'] ) ? $totals['total'] : 0;
      self::show( 'basket', 'cart', $total );
    }

    /**
     * Display pricetag on the checkout page.
     *
     * @return void
     */
    public static function show_on_checkout() {      
      $totals = WC()->cart->get_totals();
      $total  = isset( $totals['total'] ) ? $totals['total'] : 0;
      self::show( 'payment', 'checkout', $total);
    }

    /**
     * Display priceTag with the give parameters.
     *
     * @param  string           $view
     * @param  string           $target
     * @param  string|int|float $price     
     * @return void
     */
    public static function show( $view, $target, $price ) {      
      $settings = self::get_gateway_settings();

      $dynamic_price         = 'pricetag_' . $target . '_dynamic_price';
      $dynamic_price_trigger = $dynamic_price . '_trigger';
      $position_field        = 'pricetag_position_' . $target;
      $position              = self::get_gateway_settings( 'pricetag_position_' . $target );
      $style                 = self::get_gateway_settings( 'pricetag_style_' . $target );      
      $combination           = self::getValidCountryLanguageCurrencyCombination();      
      
      $language = $combination['language'];
      $currency = $combination['currency'];
      $country = $combination['country'];

      $attrs = array_filter(
        array(
          'view'                   => $view,
          'price'                  => $price,
          'currency'               => $currency,
          'country-code'           => $country,
          'language'               => $language,
          'dynamic-price'          => isset( $settings[ $dynamic_price ] ) && ! empty( $settings[ $dynamic_price ] ) ? $settings[ $dynamic_price ] : '',
          'dynamic-price-triggers' => isset( $settings[ $dynamic_price_trigger ] ) && ! empty( $settings[ $dynamic_price_trigger ] ) ? $settings[ $dynamic_price_trigger ] : '',
        )
      );      

      ?>
      <div class="viabill-pricetag-wrap" <?php echo $style ? 'style="' . esc_attr($style) . '"' : '' ?>>      
        <div 
          <?php 
          foreach ($attrs as $attr_name => $attr_value) {
            echo 'data-' . esc_attr($attr_name) . '="' . esc_attr($attr_value) . '" ';
          } 
          // If there is a jQuery selector saved for position render the selector and add class via javascript to trigger script.
          if ( $position ) {
            echo 'data-append-target="' . esc_attr($position) . '" ';            
          } else {
            echo 'class="viabill-pricetag" ';
          }         
          ?>
        >
        </div>
      </div>
      <?php
    }

    /**
     * Extract and return current language code from locale or false if not
     * supported.
     *
     * @return string|boolean
     */
    public static function get_supported_language() {
      $locale   = get_locale();
      $language = null;

      if ( strpos( $locale, '_' ) !== false ) {
        $locale_parts = explode( '_', $locale );
        $language     = $locale_parts[0];
      } elseif ( strlen( $locale ) === 2 ) {
        $language = $locale;
      }

      if ( array_key_exists( $language, self::$supported_languages ) ) {
        return self::$supported_languages[ $language ];
      } else {
        return false;
      }
    }

    /**
     * Extract and return current currency code or false if not supported
     */
    public static function get_supported_currency() {
      $currency = get_woocommerce_currency();
      if (empty($currency)) true; // this should never happen

      $currency = strtolower($currency);
      
      if ( array_key_exists( $currency, self::$supported_currencies ) ) {
        return self::$supported_currencies[ $currency ];
      } else {
        return false;
      }
    }

    /**
     * Extract and return current currency code or false if not supported
     */
    public static function get_supported_country() {
      $country = wc_get_base_location()['country'];
      if (empty($country)) true; // this should never happen

      $country = strtolower($country);
      
      if ( array_key_exists( $country, self::$supported_countries ) ) {
        return self::$supported_countries[ $country ];
      } else {
        return false;
      }
    }

    /**
     * Get PriceTags script that's specific for the merchant
     */
    public static function get_pricetag() {
      $script = self::get_gateway_settings('pricetag_script');      
      return $script;
    }

    /**
     * Get PriceTags merchant ID form the script
     */
    public static function get_pricetag_merchant_id() {
      $pricetag_merchant_id = self::TEST_PRICETAGS_MERCHANT_ID;
      $script = self::get_pricetag();
      $pos = strpos($script, 'pricetag.viabill.com/script/');
      if ($pos>0) {
        $pose = strpos($script, ';', $pos+1);
        if ($pose>0) {
          $pricetag_merchant_id = substr($script, $pos + strlen('pricetag.viabill.com/script/'), 
            $pose - $pos - strlen('pricetag.viabill.com/script/') - 1);          
        }
      }
      return $pricetag_merchant_id;
    }

    /**
     * Get PriceTags settings
     */
    public static function get_gateway_settings($key = null) {                  
      $settings = get_option('woocommerce_viapay_viabill_settings', array());   
      
      $settings['pricetag_enabled'] = isset( $settings['pricetag_enabled'] ) ? $settings['pricetag_enabled'] : 'yes';
		  $settings['pricetag_on_product'] = isset( $settings['pricetag_on_product'] ) ? $settings['pricetag_on_product'] : 'yes';
		  $settings['pricetag_on_cart'] = isset( $settings['pricetag_on_cart'] ) ? $settings['pricetag_on_cart'] : 'yes';
		  $settings['pricetag_on_checkout'] = isset( $settings['pricetag_on_checkout'] ) ? $settings['pricetag_on_checkout'] : 'yes';
      $settings['pricetag_product_hook'] = isset( $settings['pricetag_product_hook'] ) ? $settings['pricetag_product_hook'] : 'woocommerce_single_product_summary';
      
      $viabill_test_webshop_id = self::TEST_PRICETAGS_MERCHANT_ID;
      $settings['pricetag_script'] = isset($settings['pricetag_script']) ? $settings['pricetag_script'] : 
        "<script>(function(){var o=document.createElement('script');o.type='text/javascript';o.async=true;o.src='https://pricetag.viabill.com/script/{$viabill_test_webshop_id}';var s=document.getElementsByTagName('script')[0];s.parentNode.insertBefore(o,s);})();</script>";
    
      $settings['pricetag_position_product'] = isset($settings['pricetag_position_product']) ? $settings['pricetag_position_product'] :'';
      $settings['pricetag_product_dynamic_price'] = isset($settings['pricetag_product_dynamic_price']) ? $settings['pricetag_product_dynamic_price'] :'';
      $settings['pricetag_product_dynamic_price_trigger'] = isset($settings['pricetag_product_dynamic_price_trigger']) ? $settings['pricetag_product_dynamic_price_trigger'] :'';
      $settings['pricetag_style_product'] = isset($settings['pricetag_style_product']) ? $settings['pricetag_style_product'] :'';
      $settings['pricetag_position_cart'] = isset($settings['pricetag_position_cart']) ? $settings['pricetag_position_cart'] :'';
      $settings['pricetag_cart_dynamic_price'] = isset($settings['pricetag_cart_dynamic_price']) ? $settings['pricetag_cart_dynamic_price'] :'';
      $settings['pricetag_cart_dynamic_price_trigger'] = isset($settings['pricetag_cart_dynamic_price_trigger']) ? $settings['pricetag_cart_dynamic_price_trigger'] :'';
      $settings['pricetag_style_cart'] = isset($settings['pricetag_style_cart']) ? $settings['pricetag_style_cart'] :'';
      $settings['pricetag_position_checkout'] = isset($settings['pricetag_position_checkout']) ? $settings['pricetag_position_checkout'] :'';
      $settings['pricetag_checkout_dynamic_price'] = isset($settings['pricetag_checkout_dynamic_price']) ? $settings['pricetag_checkout_dynamic_price'] :'';
      $settings['pricetag_checkout_dynamic_price_trigger'] = isset($settings['pricetag_checkout_dynamic_price_trigger']) ? $settings['pricetag_checkout_dynamic_price_trigger'] :'';
      $settings['pricetag_style_checkout'] = isset($settings['pricetag_style_checkout']) ? $settings['pricetag_style_checkout'] :'';      

      if (isset($key)) {
        if (isset($settings[$key])) {
          return $settings[$key];
        } else {
          return '';
        }
      }

      return $settings;
    }


  }
}
