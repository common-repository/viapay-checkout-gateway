jQuery(document).ready(function ($) {

	$(document).on('click', '#viapay_capture', function (e) {
		e.preventDefault();
		var nonce = $(this).data('nonce');
		var order_id = $(this).data('order-id');
		var self = $(this);
		
		$.ajax({
			url       : Viapay_Admin.ajax_url,
			type      : 'POST',
			data      : {
				action        : 'viapay_capture',
				nonce         : nonce,
				order_id      : order_id
			},
			beforeSend: function () {
				self.data('text', self.html());
				self.html(Viapay_Admin.text_wait);
				self.prop('disabled', true);
			},
			success   : function (response) {
				self.html(self.data('text'));
				self.prop('disabled', false);
				if (!response.success) {
					return false;
				}

				window.location.href = location.href;
			}
		});
	});

	$(document).on('click', '#viapay_cancel', function (e) {
		e.preventDefault();
		var nonce = $(this).data('nonce');
		var order_id = $(this).data('order-id');
		var self = $(this);
		$.ajax({
			url       : Viapay_Admin.ajax_url,
			type      : 'POST',
			data      : {
				action        : 'viapay_cancel',
				nonce         : nonce,
				order_id      : order_id
			},
			beforeSend: function () {
				self.data('text', self.html());
				self.html(Viapay_Admin.text_wait);
				self.prop('disabled', true);
			},
			success   : function (response) {
				self.html(self.data('text'));
				self.prop('disabled', false);
				/*
				if (!response.success) {
					alert(response.data);
					return false;
				}
				*/
				window.location.href = location.href;
			}
		});
	});
	
	$(document).on('click', '#viapay_refund', function (e) {
		e.preventDefault();
		var nonce = $(this).data('nonce');
		var order_id = $(this).data('order-id');
		var amount = $(this).data('amount');
		var self = $(this);
		$.ajax({
			url       : Viapay_Admin.ajax_url,
			type      : 'POST',
			data      : {
				action        : 'viapay_refund',
				nonce         : nonce,
				order_id      : order_id,
				amount        : amount
			},
			beforeSend: function () {
				self.data('text', self.html());
				self.html(Viapay_Admin.text_wait);
				self.prop('disabled', true);
			},
			success   : function (response) {
				self.html(self.data('text'));
				self.prop('disabled', false);
				if (!response.success) {
					alert(response.data);
					return false;
				}

				window.location.href = location.href;
			},
			error	: function (response) {
				alert(response);
			}
		});
	});
	
	$(document).on('click', '#viapay_capture_partly', function (e) {
		e.preventDefault();
		var nonce = $(this).data('nonce');
		var order_id = $(this).data('order-id');
		var amount = $("#viapay-capture_partly_amount-field").val();
		var self = $(this);
		
		$.ajax({
			url       : Viapay_Admin.ajax_url,
			type      : 'POST',
			data      : {
				action        : 'viapay_capture_partly',
				nonce         : nonce,
				order_id      : order_id,
				amount        : amount
			},
			beforeSend: function () {
				self.data('text', self.html());
				self.html(Viapay_Admin.text_wait);
				self.prop('disabled', true);
			},
			success   : function (response) {
				self.html(self.data('text'));
				self.prop('disabled', false);
				if (!response.success) {
					alert(response);
					alert(response.data);
					return false;
				}
				window.location.href = location.href;
			},
			error	: function (response) {
				alert("error response: " + JSON.stringify(response));
			}
		});
	});
	
	$(document).on('click', '#viapay_refund_partly', function (e) {
		console.log('refund_partually');
		e.preventDefault();
		var nonce = $(this).data('nonce');
		var order_id = $(this).data('order-id');
		var amount = $("#viapay-refund_partly_amount-field").val();
		var self = $(this);
		
		$.ajax({
			url       : Viapay_Admin.ajax_url,
			type      : 'POST',
			data      : {
				action        : 'viapay_refund_partly',
				nonce         : nonce,
				order_id      : order_id,
				amount        : amount
			},
			beforeSend: function () {
				self.data('text', self.html());
				self.html(Viapay_Admin.text_wait);
				self.prop('disabled', true);
			},
			success   : function (response) {
				self.html(self.data('text'));
				self.prop('disabled', false);
				if (!response.success) {
					alert(response);
					alert(response.data);
					return false;
				}
				window.location.href = location.href;
			},
			error	: function (response) {
				alert("error response: " + JSON.stringify(response));
			}
		});
	});

	$( '#viapay-capture_partly_amount-field, #viapay-refund_partly_amount-field' ).inputmask({ alias: "currency", groupSeparator: '' });
});
