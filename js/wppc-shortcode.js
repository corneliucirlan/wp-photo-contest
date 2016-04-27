jQuery(document).ready(function($) {

	$( "#contest-tabs" ).tabs();

	$("#rules-tabs").tabs();

	$('#wppc-contest-pages').tabs();

	$(".group1").colorbox({rel:'group1', transition:"fade", maxWidth: "70%"});

	// sideber/about click listener
	$('#wppc-sidebar a, #contest-about a').click(function(event) {
		var tab = $(this).attr("href");
		var index = $('a[href='+tab+']').attr('id');
		$("#contest-tabs").tabs("option", "active", index.slice(-1) -1);
	});

	// Share buttons popup
	$('.wppc-share-button').on('click', function(event) {
		event.preventDefault();
		
		var popup = {width: 500, height: 350};
		window.open($(this).find('a').attr('href'), "", "toolbar=no, location=yes, status=no, scrollbars=no, resizable=yes, left=10, top=10, width="+popup.width+", height="+popup.height);
	});
});