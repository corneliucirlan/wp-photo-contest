jQuery(document).ready(function($) {
	
	// ADD/EDIT CONTEST TABS
	$("#tabs").tabs();

	$('ul.nav-tab-wrapper').on('click', '.nav-tab', function(event) {
		$('.nav-tab').removeClass('ui-tabs-active ui-state-active nav-tab-active');
		$(this).addClass('nav-tab-active');
	});


	// VIEW IPs
	$('.row-actions').on('click', '.wppc-view', function(event) {
		event.preventDefault();

		$.ajax({
	 		url: wppcAdminContest.ajaxurl,
	 		type: 'POST',
	 		dataType: 'html',
	 		data: {action: 'view-photo-voters', photoid: $(this).attr('data-photo-id')},
	 	})
	 	.done(function(data) {
	 		$('#wppc-overlay').show().html(data);
	 		console.log(data);
	 		console.log("success");
	 	})
	 	.fail(function() {
	 		console.log("error");
	 	})
	 	.always(function() {
	 		console.log("complete");
	 	});
	});


	/**
	 * AJAX CALL TO GET PHOTO SPECS
	 */
	 $('table').on('click', '.view-photo-details', function(event) {
	 	event.preventDefault();

	 	$.ajax({
	 		url: wppcAdminContest.ajaxurl,
	 		type: 'POST',
	 		dataType: 'html',
	 		data: {action: 'view-photo-specs', photo: $(this).attr('href'), photoid: $(this).attr('data-photo-id')},
	 	})
	 	.done(function(data) {
	 		$('#wppc-overlay').show().html(data);
	 		console.log(data);
	 		console.log("success");
	 	})
	 	.fail(function() {
	 		console.log("error");
	 	})
	 	.always(function() {
	 		console.log("complete");
	 	});
	 });
	

	/**
	 * HIDE OVERLAY
	 */
	$(document).on('keydown', function(e) {
    	if (e.keyCode === 27) // ESC
    		$('#wppc-overlay').hide();
	});

	$('#wppc-overlay').on('click', '.dashicons-no', function(event) {
		event.preventDefault();
		$('#wppc-overlay').hide();
	});



	/**
	 * SEND TEST EMAIL
	 */
	$('#send-admit-test').click(function(event) {
		event.preventDefault();

		$.ajax({
			url: wppcAdminContest.ajaxurl,
			type: 'POST',
			dataType: 'html',
			data:
			{
				action: 'test-admit-email',
				subject: $('#contestAdmitPhotoSubject').val(),
				body: $('#contestAdmitPhotoBody').val()
			},
		})
		.done(function(data) {
			$('#admit-result').html(data);
			//console.log(data);
			console.log("success");
		})
		.fail(function() {
			console.log("error");
		})
		.always(function() {
			console.log("complete");
		});
		
	});
	
});