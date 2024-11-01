<?php
/** @var WC_Gateway_Viapay_Checkout $gateway */
/** @var WC_Order $order */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

?>

<?php if ( $gateway->can_capture( $order ) ): ?>
	<button id="viapay_capture"
			type="button" class="button button-primary"
			data-nonce="<?php echo wp_create_nonce( 'viapay' ); ?>"
			data-order-id="<?php echo esc_html( $order->get_id() ); ?>">
		<?php _e( 'Capture Payment', 'viapay-checkout-gateway' ) ?>
	</button>
<?php endif; ?>

<?php if ( $gateway->can_cancel( $order ) ): ?>
	<button id="viapay_cancel"
			type="button" class="button button-primary"
			data-nonce="<?php echo wp_create_nonce( 'viapay' ); ?>"
			data-order-id="<?php echo esc_html( $order->get_id() ); ?>">
		<?php _e( 'Cancel Payment', 'viapay-checkout-gateway' ) ?>
	</button>
<?php endif; ?>

