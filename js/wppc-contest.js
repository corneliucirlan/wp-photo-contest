jQuery(document).ready(function($) {
	
	/**
	 * ADD/EDIT CONTEST TABS
	 */
	$("#tabs").tabs();

	$('ul.nav-tab-wrapper').on('click', '.nav-tab', function(event) {
		$('.nav-tab').removeClass('ui-tabs-active ui-state-active nav-tab-active');
		$(this).addClass('nav-tab-active');
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