jQuery(document).ready(function($) {

	var $dialogWindow 			= $("#modal-content");
	var spinner 				= '<span class="spinner spinner-center is-active"></span>';


	$dialogWindow.dialog({
		'width'			: $(window).innerWidth()*0.7,
	    'dialogClass'   : 'wp-dialog',           
	    'draggable'		: false,
	    'resizable'		: false,
	    'modal'         : true,
	    'autoOpen'      : false, 
	    'closeOnEscape' : true,
	    'open': function() {
		    $('body').css('overflow', 'hidden');
		},
		'close': function() {
			$dialogWindow.dialog('close');
		    $('body').css('overflow', 'auto');
		    $dialogWindow.html(spinner);
		},
	});

	
	// ADD/EDIT CONTEST TABS
	$("#tabs").tabs();

	$('ul.nav-tab-wrapper').on('click', '.nav-tab', function(event) {
		$('.nav-tab').removeClass('ui-tabs-active ui-state-active nav-tab-active');
		$(this).addClass('nav-tab-active');
	});


	// VIEW IPs
	$('.row-actions').on('click', '.voters', function(event) {
		event.preventDefault();

		var photoID = $(this).find('a').attr('data-photo-id');

		$dialogWindow.dialog({
			'title'		: "View voters IPs",
			'height'	: 380,
		});
		$dialogWindow.dialog('open');


		$.get(ajaxurl, {action: 'view-photo-voters', photoid: photoID}, function(data) {
			$dialogWindow.html(data);
		});
	});


	/**
	 * VIEW PHOTO SPECS
	 */
	$('.wrap').on('click', '.view-photo-details', function(event) {
		event.preventDefault();

		// set data
		var data = {
			action: 'view-photo-specs',
			photo: $(this).attr('data-photo-id'),
			nonce: wppc.nonce,
			photoURL: wppc.photoURL,
		};

		// open modal windows
		$dialogWindow.dialog({
			'title'		: "View voters IPs",
			'height'	: $(window).innerHeight()*0.7,
		});
		$dialogWindow.dialog('open');

		// get photo details
		$.get(ajaxurl, data, function(data) {
			$dialogWindow.html(data);
		});
	 });


	/**
	 * SEND TEST EMAIL
	 */
	$('#send-admit-test').click(function(event) {
		event.preventDefault();

		$.post(ajaxurl, {action: 'test-admit-email', subject: $('#admitted-subject').val(), body: $('#admitted-body').val()}, function(data, textStatus, xhr) {
			$('#admit-result').html(data);
		});		
	});
	
});