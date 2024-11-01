<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Viapay_Merchant_Profile' ) ) {
  /**
   * Viapay_Merchant_Profile class
   *
   * @since 0.1
   */
  class Viapay_Merchant_Profile {
    /**
     * API's interface.
     *
     * @var Viapay_Connector
     */
    private $connector;

    public function __construct() {
      require_once( dirname( __FILE__ ) . '/class-wc-viapay-connector.php' );

      $this->connector = new Viapay_Connector();

      $settings = WC_ViapayCheckout::get_gateway_settings();
    }

    /**
     * Register AJAX endpoints.
     */
    public function register_ajax_endpoints() {
      add_action( 'wp_ajax_get_my_viabill_url', array( $this, 'do_my_viabill_url' ) );
    }

    /**
     * Echo merchant's my ViaBill URL in JSON format.
     */
    public function do_my_viabill_url() {
      $url = $this->connector->get_my_viabill_url();
      wp_send_json( $url );
      wp_die();
    }

    /**
     * Save registration data from the response and return true if successful or
     * false otherwise.
     *
     * @param  array $data
     * @return bool
     */
    public function save_registration_data( $data ) {
      if ( ! isset( $data['key'] ) || empty( $data['secret'] ) ) {
        return false;
      }

      if ( ! isset( $data['secret'] ) || empty( $data['secret'] ) ) {
        return false;
      }

      if ( ! isset( $data['pricetagScript'] ) || empty( $data['pricetagScript'] ) ) {
        return false;
      }

      $is_key_saved = update_option( 'viabill_key', $data['key'] );
      if ( ! $is_key_saved ) {
        $this->logger->log( 'Failed to save key in DB.', 'error' );
      }

      $is_secret_saved = update_option( 'viabill_secret', $data['secret'] );
      if ( ! $is_secret_saved ) {
        $this->logger->log( 'Failed to save secret in DB.', 'error' );
      }

      $is_pricetag_saved = update_option( 'viabill_pricetag', $data['pricetagScript'] );
      if ( ! $is_pricetag_saved ) {
        $this->logger->log( 'Failed to save priceTag in DB.', 'error' );
      }

      return $is_key_saved && $is_secret_saved && $is_pricetag_saved;
    }    

    /**
     * Return ViaBill merchant key or false if none.
     *
     * @return string|bool
     */
    public function get_key() {
      return get_option( 'viabill_key', false );
    }

    /**
     * Return ViaBill merchant secret or false if none.
     *
     * @return string|bool
     */
    public function get_secret() {
      return get_option( 'viabill_secret', false );
    }

    /**
     * Return ViaBill merchant priceTag script or false if none.
     *
     * @return string|bool
     */
    public function get_pricetag() {
      return get_option( 'viabill_pricetag', false );
    }
  }
}
