<?php
/** @var WC_Gateway_Viapay_Checkout $gateway */
/** @var WC_Order $order */
/** @var int $order_id */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

?>
<div>
	<?php if ( $gateway->can_capture( $order ) ): ?>
		<button id="viapay_capture"
				data-nonce="<?php echo wp_create_nonce( 'viapay' ); ?>"
				data-order-id="<?php echo esc_html( $order->get_id() ); ?>">
			<?php _e( 'Capture Payment', 'viapay-checkout-gateway' ) ?>
		</button>
	<?php endif; ?>

	<?php if ( $gateway->can_cancel( $order ) ): ?>
		<button id="viapay_cancel"
				data-nonce="<?php echo wp_create_nonce( 'viapay' ); ?>"
				data-order-id="<?php echo esc_html( $order->get_id() ); ?>">
			<?php _e( 'Cancel Payment', 'viapay-checkout-gateway' ) ?>
		</button>
	<?php endif; ?>
</div>
