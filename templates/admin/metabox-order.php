<?php
/** @var WC_Gateway_Viapay_Checkout $gateway */
/** @var WC_Order $order */
/** @var int $order_id */
/** @var array $order_data */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

?>



<ul class="order_action">
	<li class="viapay-admin-section-li-header">
        <?php echo __( 'State', 'viapay-checkout-gateway' ); ?>: <?php echo $order_data['state']; ?>
    </li>

	<?php $order_is_cancelled = ( $order->get_meta( '_viapay_order_cancelled', true ) === '1' ); ?>
	<?php if ($order_is_cancelled && 'cancelled' != $order_data['state']): ?>
		<li class="viapay-admin-section-li-small">
            <?php echo __( 'Order is cancelled', 'viapay-checkout-gateway' ); ?>
        </li>
	<?php endif; ?>

	<li class="viapay-admin-section-li">
        <span class="viapay-balance__label">
            <?php echo __( 'Remaining balance', 'viapay-checkout-gateway' ); ?>:
        </span>
        <span class="viapay-balance__amount">
            <span class='viapay-balance__currency'>
                &nbsp;
            </span>
            <?php echo wc_price(  $gateway->make_initial_amount($order_data['authorized_amount'] - $order_data['settled_amount'], $order_data['currency'])); ?>
        </span>
    </li>
	<li class="viapay-admin-section-li">
        <span class="viapay-balance__label">
            <?php echo __( 'Total authorized', 'viapay-checkout-gateway' ); ?>:
        </span>
        <span class="viapay-balance__amount">
            <span class='viapay-balance__currency'>
                &nbsp;
            </span>
            <?php echo wc_price( $gateway->make_initial_amount($order_data['authorized_amount'], $order_data['currency'])); ?>
       </span>
    </li>
	<li class="viapay-admin-section-li">
        <span class="viapay-balance__label">
            <?php echo __( 'Total settled', 'viapay-checkout-gateway' ); ?>:
        </span>
        <span class="viapay-balance__amount">
            <span class='viapay-balance__currency'>
                &nbsp;
            </span>
            <?php echo wc_price( $gateway->make_initial_amount($order_data['settled_amount'], $order_data['currency'])); ?>
        </span>
    </li>
	<li class="viapay-admin-section-li">
        <span class="viapay-balance__label">
            <?php echo __( 'Total refunded', 'viapay-checkout-gateway' ); ?>:
        </span>
        <span class="viapay-balance__amount">
            <span class='viapay-balance__currency'>
                &nbsp;
            </span>
            <?php echo wc_price( $gateway->make_initial_amount($order_data['refunded_amount'], $order_data['currency'])); ?>
        </span>
    </li>
	<li style='font-size: xx-small'>&nbsp;</li>
	<?php if ($order_data['settled_amount'] == 0 && ! in_array( $order_data['state'], array( 'cancelled', 'created') ) && !$order_is_cancelled): ?>
		<li class="viapay-full-width">
            <a class="button button-primary" data-action="viapay_capture" id="viapay_capture" data-nonce="<?php echo wp_create_nonce( 'viapay' ); ?>" data-order-id="<?php echo $order_id; ?>" data-confirm="<?php echo __( 'You are about to CAPTURE this payment', 'viapay-checkout-gateway' ); ?>">
                <?php echo sprintf( __( 'Capture Full Amount (%s)', 'viapay-checkout-gateway' ), wc_price(  $gateway->make_initial_amount($order_data['authorized_amount'], $order_data['currency']))); ?>
            </a>
        </li>
	<?php endif; ?>

	<?php if ($order_data['settled_amount'] == 0 && ! in_array( $order_data['state'], array( 'cancelled', 'created') ) && !$order_is_cancelled): ?>
		<li class="viapay-full-width">
            <a class="button" data-action="viapay_cancel" id="viapay_cancel" data-confirm="<?php echo __( 'You are about to CANCEL this payment', 'viapay-checkout-gateway' ); ?>" data-nonce="<?php echo wp_create_nonce( 'viapay' ); ?>" data-order-id="<?php echo $order_id; ?>">
                <?php echo __( 'Cancel remaining balance', 'viapay-checkout-gateway' ); ?>
            </a>
        </li>
		<li style='font-size: xx-small'>&nbsp;</li>
	<?php endif; ?>

	<?php if ($order_data['authorized_amount'] > $order_data['settled_amount'] && ! in_array( $order_data['state'], array( 'cancelled', 'created') ) && !$order_is_cancelled): ?>
		<li class="viapay-admin-section-li-header">
            <?php echo __( 'Partly capture', 'viapay-checkout-gateway' ); ?>
        </li>
		<li class="viapay-balance last">
            <span class="viapay-balance__label" style="margin-right: 0;">
                <?php echo __( 'Capture amount', 'viapay-checkout-gateway' ); ?>:
            </span>
            <span class="viapay-partly_capture_amount">
                <input id="viapay-capture_partly_amount-field" class="viapay-capture_partly_amount-field" type="text" autocomplete="off" size="6" value="<?php echo (  $gateway->make_initial_amount($order_data['authorized_amount'] - $order_data['settled_amount'], $order_data['currency']) ); ?>" />
            </span>
        </li>
		<li class="viapay-full-width">
            <a class="button" id="viapay_capture_partly" data-nonce="<?php echo wp_create_nonce( 'viapay' ); ?>" data-order-id="<?php echo $order_id; ?>">
                <?php echo __( 'Capture Specified Amount', 'viapay-checkout-gateway' ); ?>
            </a>
        </li>
		<li style='font-size: xx-small'>&nbsp;</li>
	<?php endif; ?>

	<?php if ( $order_data['settled_amount'] > $order_data['refunded_amount'] && ! in_array( $order_data['state'], array( 'cancelled', 'created') ) && !$order_is_cancelled ): ?>
		<li class="viapay-admin-section-li-header">
            <?php echo __( 'Partly refund', 'viapay-checkout-gateway' ); ?>
        </li>
		<li class="viapay-balance last">
            <span class="viapay-balance__label" style='margin-right: 0;'>
                <?php echo __( 'Refund amount', 'viapay-checkout-gateway' ); ?>:
            </span>
            <span class="viapay-partly_refund_amount">
                <input id="viapay-refund_partly_amount-field" class="viapay-refund_partly_amount-field" type="text" size="6" autocomplete="off" value="<?php echo $gateway->make_initial_amount($order_data['settled_amount'] - $order_data['refunded_amount'], $order_data['currency']); ?>" />
            </span>
        </li>
		<li class="viapay-full-width">
            <a class="button" id="viapay_refund_partly" data-nonce="<?php echo wp_create_nonce( 'viapay' ); ?>" data-order-id="<?php echo $order_id; ?>">
                <?php echo __( 'Refund Specified Amount', 'viapay-checkout-gateway' ); ?>
            </a>
        </li>
		<li style='font-size: xx-small'>&nbsp;</li>
	<?php endif; ?>

	<li class="viapay-admin-section-li-header-small">
        <?php echo __( 'Order ID', 'viapay-checkout-gateway' ) ?>
    </li>
	<li class="viapay-admin-section-li-small">
        <?php echo $order_data["handle"]; ?>
    </li>
	<li class="viapay-admin-section-li-header-small">
        <?php echo __( 'Transaction ID', 'viapay-checkout-gateway' ) ?>
    </li>
	<li class="viapay-admin-section-li-small">
        <?php echo $order_data["id"]; ?>
    </li>
	<?php if ( isset( $order_data['transactions'][0] ) && isset( $order_data['transactions'][0]['card_transaction'] ) ): ?>
        <li class="viapay-admin-section-li-header-small">
			<?php echo __( 'Card number', 'viapay-checkout-gateway' ); ?>
        </li>
        <li class="viapay-admin-section-li-small">
			<?php echo WC_ViapayCheckout::formatCreditCard( $order_data['transactions'][0]['card_transaction']['masked_card'] ); ?>
        </li>
        <p>
        <center>
            <img src="<?php echo esc_url ( $gateway->get_logo( $order_data['transactions'][0]['card_transaction']['card_type'] )); ?>" class="viapay-admin-card-logo" />
        </center>
        </p>
	<?php endif; ?>
</ul>
