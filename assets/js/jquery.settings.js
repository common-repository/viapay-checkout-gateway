jQuery(document).ready(function( $ ) {
    var settings_param_prefix = '#woocommerce_viapay_checkout_';    

    var account_mode = jQuery('input[type=radio][name=account_mode]');
    if (account_mode) {
        account_mode.change(function() {
            if (this.value == 'test') {                
                jQuery('#test_mode_info').show();
                jQuery('#live_mode_info').hide();                
                jQuery(settings_param_prefix+'test_mode').prop( "checked", true );
            }
            else if (this.value == 'live') {                
                jQuery('#test_mode_info').hide();
                jQuery('#live_mode_info').show();
                jQuery(settings_param_prefix+'test_mode').prop( "checked", false );
            }
        }); 
    }                     
});

function updateSettingParam(el_id, new_value) {
    console.log("Updating "+el_id+" with value: "+new_value);
    document.getElementById(el_id).value = new_value;
} 