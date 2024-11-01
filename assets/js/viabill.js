jQuery(document).ready(function( $ ) {
  // Append and initialize pricetag on different location.
  appendPricetag();

  // fallback for priceTag on the cart page.
  $(document.body).on('updated_cart_totals', function () {
    appendPricetag();

    let pricetag_el = document.querySelector('.viabill-pricetag');
    if (pricetag_el) {
      pricetag_el.dispatchEvent(new CustomEvent('vb-update-price', {}));
    }    
  });

  // Allow PriceTag location change from the plugin PriceTags settings
  function appendPricetag() {
    let $pricetag = $('[data-append-target]');
    if ( $pricetag ) {
      let pricetag_selector = $pricetag.data('append-target');	  
      let insert_element = $pricetag.closest('div');		  	  
      $( pricetag_selector ).before(insert_element);	  
      $pricetag.addClass( 'viabill-pricetag' );
    }
  }

});

 