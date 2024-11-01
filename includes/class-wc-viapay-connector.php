<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Viapay_Connector' ) ) {
  /**
   * Viapay_Connector class
   *
   * @since 0.1
   */
  class Viapay_Connector {

    /**
     * Gateway settings    
     *
     * @var array
     */
    private $settings = null;

    /**
     * Available payment gateways
     *
     * @var string
     */
    private $gateways = null;

    /**
     * Server domain for API request
     */
    const SERVER_DOMAIN = 'reepay.com';

    /**
     * Common API Key for testing
     */
    const COMMON_PRIVATE_KEY_TEST = 'priv_93394db3e8fc30b076d8b5cab7542dc7';

    /**
     * Class constructor.
     */
    public function __construct() {      
      $this->settings     = WC_ViapayCheckout::get_gateway_settings();           
    }
    
    /**
     * Format amount.
     *
     * @param  float $amount
     * @return string        Formated amount to string with 2 decimal places.
     */
    public function format_amount( $amount ) {
      return wc_format_decimal( $amount, 2 );
    }            

    /**
     * Return available countries or empty array in case of failure.
     *
     * @return array
     */
    public function get_available_countries() {
      $countries = array(
        array('code'=>'us', 'name'=> __('United States', 'viapay-checkout-gateway' )), 
        array('code'=>'at', 'name'=> __( 'Austria', 'viapay-checkout-gateway' )),
        array('code'=>'be', 'name'=> __( 'Belgium', 'viapay-checkout-gateway' )),
        array('code'=>'bg', 'name'=> __( 'Bulgaria', 'viapay-checkout-gateway' )),
        array('code'=>'hr', 'name'=> __( 'Croatia', 'viapay-checkout-gateway' )),
        array('code'=>'cy', 'name'=> __( 'Cyprus', 'viapay-checkout-gateway' )),
        array('code'=>'cz', 'name'=> __( 'Czech Republic', 'viapay-checkout-gateway' )),
        array('code'=>'dk', 'name'=> __( 'Denmark', 'viapay-checkout-gateway' )),
        array('code'=>'ee', 'name'=> __( 'Estonia', 'viapay-checkout-gateway' )),
        array('code'=>'fo', 'name'=> __( 'Faroe Islands', 'viapay-checkout-gateway' )),
        array('code'=>'fi', 'name'=> __( 'Finland', 'viapay-checkout-gateway' )),
        array('code'=>'fr', 'name'=> __( 'France', 'viapay-checkout-gateway' )),
        array('code'=>'de', 'name'=> __( 'Germany', 'viapay-checkout-gateway' )),
        array('code'=>'gr', 'name'=> __( 'Greece', 'viapay-checkout-gateway' )),
        array('code'=>'gl', 'name'=> __( 'Greenland', 'viapay-checkout-gateway' )),
        array('code'=>'hu', 'name'=> __( 'Hungary', 'viapay-checkout-gateway' )),
        array('code'=>'is', 'name'=> __( 'Iceland', 'viapay-checkout-gateway' )),
        array('code'=>'ie', 'name'=> __( 'Ireland', 'viapay-checkout-gateway' )),
        array('code'=>'it', 'name'=> __( 'Italy', 'viapay-checkout-gateway' )),
        array('code'=>'lv', 'name'=> __( 'Latvia', 'viapay-checkout-gateway' )),
        array('code'=>'lt', 'name'=> __( 'Lithuania', 'viapay-checkout-gateway' )),
        array('code'=>'lu', 'name'=> __( 'Luxembourg', 'viapay-checkout-gateway' )),
        array('code'=>'mt', 'name'=> __( 'Malta', 'viapay-checkout-gateway' )),
        array('code'=>'nl', 'name'=> __( 'Netherlands', 'viapay-checkout-gateway' )),
        array('code'=>'no', 'name'=> __( 'Norway', 'viapay-checkout-gateway' )),
        array('code'=>'pl', 'name'=> __( 'Poland', 'viapay-checkout-gateway' )),
        array('code'=>'pt', 'name'=> __( 'Portugal', 'viapay-checkout-gateway' )),
        array('code'=>'ro', 'name'=> __( 'Romania', 'viapay-checkout-gateway' )),
        array('code'=>'sk', 'name'=> __( 'Slovakia', 'viapay-checkout-gateway' )),
        array('code'=>'si', 'name'=> __( 'Slovenia', 'viapay-checkout-gateway' )),
        array('code'=>'es', 'name'=> __( 'Spain', 'viapay-checkout-gateway' )),
        array('code'=>'se', 'name'=> __( 'Sweden', 'viapay-checkout-gateway' )),
        array('code'=>'ch', 'name'=> __( 'Switzerland', 'viapay-checkout-gateway' )),
        array('code'=>'gb', 'name'=> __( 'United Kingdom', 'viapay-checkout-gateway' )));

      return $countries;      
    }

    /**
     * Return available countries or empty array in case of failure.
     *
     * @return array
     */
    public function get_available_languages() {
      $languages = array(
        array('code'=>'en_US', 'name'=> __( 'English', 'viapay-checkout-gateway' )),
        array('code'=>'da_DK', 'name'=> __( 'Danish', 'viapay-checkout-gateway' )),
        array('code'=>'sv_SE', 'name'=> __( 'Swedish', 'viapay-checkout-gateway' )),
        array('code'=>'no_NO', 'name'=> __( 'Norwegian', 'viapay-checkout-gateway' )),
        array('code'=>'de_DE', 'name'=> __( 'German', 'viapay-checkout-gateway' )),
        array('code'=>'es_ES', 'name'=> __( 'Spanish', 'viapay-checkout-gateway' )),
        array('code'=>'fr_FR', 'name'=> __( 'French', 'viapay-checkout-gateway' )),
        array('code'=>'it_IT', 'name'=> __( 'Italian', 'viapay-checkout-gateway' )),
        array('code'=>'nl_NL', 'name'=> __( 'Netherlands', 'viapay-checkout-gateway' )));

      return $languages;      
    }

    
    /**
     * Register merchant, it should return an array with following structure:     
     *
     * @param  array $registration_data
     * @return array|false
     */
    public function register( $registration_data ) {            
      $error_msg = null;
      $private_key_test = null;

      if ($this->get_test_private_key($private_key_test, $error_msg)) {
        $response = array('private_key' => null, 'private_key_test' => $private_key_test);
        
        $current_date = date('Y-m-d H:i:s');
		    update_option( 'viapay_test_api_key_request_date', $current_date);
      } else {
        $response = array('errors'=>array(0=>array('error'=>$error_msg)));
      }

      return $response;      
    }    
    
    /**
     * Return available currencies or empty array in case of failure.
     *
     * @return array
     */
    public function get_test_private_key(&$private_key_test, &$error_msg) {               
      $url = 'https://api.reepay.com/v1/account/privkey';
      $response = $this->request( 'post', $url);
      
      if ($response['success']) {        
        $entry = $response['data'];        
        if (isset($entry['key'])) {
          $private_key_test = $entry['key'];
          return true;
        }        
        $error_msg = print_r($response['data'], true);
        return false;        
      } else {
        $error_msg = __( 'Could not retrieve test private API key', 'viabill' ). ':'. $response['error_msg'];
        return false;
      }       
            
    }

    /**
     * Return an array for default args or false if failed to JSON encode.
     *
     * @param  array  $body
     * @return array|false
     */
    private function get_default_args( $body ) {
      $encoded_body = wp_json_encode( $body );
      if ( ! $encoded_body ) {
        return false;
      }

      return array(
        'body'    => $encoded_body,
        'headers' => array(
          'Content-Type' => 'application/json',
        ),
      );
    }        

    /**
     * Return error messages from the response body array or false if none.
     *
     * @param  array $data
     * @return string|bool
     */
    public function get_error_messages( $data ) {
      if ( isset( $data['errors'] ) && is_array( $data['errors'] ) ) {
        if ( ! empty( $data['errors'] ) && isset( $data['errors'][0]['error'] ) ) {
          return $data['errors'][0]['error'];
        } else {
          return __( 'Something is not right, please try again.', 'viabill' );
        }
      }

      return false;
    }   
    
    /**
	 * Request
	 * @param $method
	 * @param $url
	 * @param array $params
	 * @return array|mixed|object
	 * @throws Exception
	 */
    public function request($method, $url, $params = array(), &$error_msg = null) {			      
      $response = array('success'=>false, 'error_msg'=>null);

      $key = self::COMMON_PRIVATE_KEY_TEST;
      $method = strtoupper($method);

      $response = [];

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
        $response['error_msg'] = $data->get_error_message();        
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
        
        switch ($code) {          
          case 1:
            $response['error_msg'] = sprintf('Invalid HTTP Code: %s', $http_code);
            break;
          case 2:
          case 3:                        
            $response['data'] = json_decode($body, true);            
            $response['success'] = true;
            break;
          case 4:
          case 5:
            if ( mb_strpos( $body, 'Request rate limit exceeded', 0, 'UTF-8' ) !== false ) {
              global $request_retry;
              if ($request_retry) {
                $response['error_msg'] = 'Viapay: Request rate limit exceeded';
              }

              sleep(10);
              $request_retry = true;
              $result = $this->request($method, $url, $params);
              $request_retry = false;            
            }
            
            $response['error_msg'] = sprintf('API Error (request): %s. HTTP Code: %s', $body, $http_code);
            break;
          default:
            if (!empty($code)) {
              $response['error_msg'] = sprintf('Invalid HTTP Code: %s', $http_code);
            } else {
              $response['error_msg'] = sprintf('Invalid HTTP Code.');
            }            
            break;
        }        
      } else {
        $response['error_msg'] = 'Unknown error - Please try again.';        
      }    

      if (isset($response['error_msg'])) {
        $response['success'] = false;
      }
      
      return $response;                                   
    }

    /**
     * Return JSON decoded response body or $default in case of failure.
     *
     * @param  array  $response
     * @param  string $endpoint         Defaults to empty string.
     * @param  mixed  $default_response Fallback return, defaults to false.
     * @return string|array|bool
     */
    private function get_response_body( $response, $endpoint = '', $default_response = false ) {
      if ( is_wp_error( $response ) ) {
        if ( $response->has_errors() ) {
          $message = $response->get_error_message();          
        }
        return $default_response;
      }

      if ( is_array( $response ) && ! empty( $response['body'] ) ) {
        $decoded_body = json_decode( $response['body'], true );
        if ( is_null( $decoded_body ) ) {          
          return $default_response;
        }        
        return $decoded_body;
      }

      return $default_response;
    }

    /**
     * Return response status or false in case of failure.
     *
     * @param  array $response
     * @return int|bool
     */
    private function get_response_status_code( $response ) {
      if ( is_wp_error( $response ) || ! is_array( $response ) ) {
        return false;
      }

      if ( ! isset( $response['response'] ) || ! is_array( $response['response'] ) ) {
        //$this->logger->log( 'Failed to get status code, missing \'response\'.', 'warning' );
        return false;
      }

      if ( ! isset( $response['response']['code'] ) ) {
        //$this->logger->log( 'Failed to get status code, missing \'code\'.', 'warning' );
        return false;
      }

      return (int) $response['response']['code'];
    }

  }
}
