jQuery(document).ready(function($) {
	$( "#main-tabs" ).tabs();

	$("#tabs").tabs();

	$(".nav-tab").click(function() {
		$(this).removeClass("nav-tab-active");
		$(this).addClass('nav-tab-active');
	});
});