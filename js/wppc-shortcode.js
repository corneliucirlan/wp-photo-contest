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
});