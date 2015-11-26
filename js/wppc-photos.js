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


	/**
	 * VIEW IPs
	 */
	$('.row-actions').on('click', '.voters', function(event) {
		event.preventDefault();

		var photoID = $(this).find('a').attr('data-photo-id');

		$dialogWindow.dialog({
			'title'		: "View voters IPs",
			'height'	: 580,
			'width'		: $(document).innerWidth()*0.8,
		});
		$dialogWindow.dialog('open');
		
		// create the map
		$dialogWindow.html('<div id="map"></div>');
		var map = new google.maps.Map(document.getElementById('map'), {
			center: {lat: 44.0, lng: 27.0},
			zoom: 2
		});

		// get a list of IPs
		$.getJSON(ajaxurl, {action: 'view-photo-voters', photoid: photoID}, function(data, textStatus) {

			$.each(data, function(index, val) {
				console.log(data);
		
				// get geolocation data for every IP
				$.getJSON("http://ip-api.com/json/"+val['ip']+"?callback=?", function(json) {

					// set marker
					var marker = new google.maps.Marker({
						position: {lat: json['lat'], lng: json['lon']},
						map: map,
						title: json['query'],
					});

					var contentString = '<div id="content">'+
						'<div id="siteNotice">'+
						'</div>'+
						'<h1 id="firstHeading" class="firstHeading"><a href="http://whatismyipaddress.com/ip/'+val['ip']+'" target="_blank">'+val['ip']+'</a></h1>'+
						'<div id="bodyContent">'+
						'<p><strong>Votes: '+val['votes']+'</strong></p>'+
						'<p>Country: '+json['country']+'</p>'+
						'<p>City: '+json['city']+'</p>'+
						'<p>ISP: '+json['isp']+'</p>'+
						'</div>'+
						'</div>';
					var infowindow = new google.maps.InfoWindow({content: contentString});

					google.maps.event.addListener(marker, 'click', function() {
						infowindow.open(map, marker);
					});

				});
			});
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
			'title'		: "Photo Details",
			'height'	: $(window).innerHeight()*0.7,
		});
		$dialogWindow.dialog('open');

		// get photo details
		$.get(ajaxurl, data, function(data) {
			$dialogWindow.html(data);
		});
	 });
	
});