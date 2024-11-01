<?php
/** @var WC_Payment_Gateway $gateway */
/** @var bool $webhook_installed */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

$payment_method_title = trim ( esc_html( $gateway->get_method_title() ) );
if (empty($payment_method_title)) {
	$payment_method_title = _e('Viapay Checkout', 'viapay-checkout-gateway');
}
?>

<h2><?php echo esc_attr($payment_method_title); ?></h2>
<?php wp_kses_post( wpautop( $gateway->get_method_description() ) ); ?>

<table class="form-table">
	<?php $gateway->generate_settings_html( $gateway->get_form_fields(), true ); ?>
</table>
