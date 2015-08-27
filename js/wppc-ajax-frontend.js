jQuery(document).ready(function($) {

	/**
	 * FILTERS
	 */

	// SELECT
	$('#contest-photo-gallery').on('change', '.wppc-select-filter', function(event) {
		event.preventDefault();

		var value = $(this).val();
		$('.wppc-select-filter').prop('selectedIndex',0);
		$(this).val(value);
		$('#wppc-button-filter').val("mobile-only").text("Mobile only");
		
		var data = {
			'action': 'wppc-filter-photos',
			'wppc-id': $(this).prev().val(),
			'wppc-filter': $(this).val(),
			'wppc-url': document.URL,
		};

		$.ajax({
			url: wppcSubmitPhoto.ajaxurl,
			type: 'POST',
			data: data,
			dataType: 'html',
			beforeSend: function(jqXHR, settings) {
				$('#wppc-photos').html('<div style="text-align: center;"><img src="http://www.uncover-romania.com/wp-content/plugins/wp-photo-contest/img/ajax-loading.gif" style="width: 20px !important;"></div>');
			},
			success: function(data, textStatus, jqXHR) {
				console.log(data);
				$('#wppc-photos').html(data);
				$(".group1").colorbox({rel:'group1', transition:"fade", maxWidth: "70%"});

				// parse the whole page pin
				window.parsePinBtns();
			},
			error: function(jqXHR, textStatus, errorThrown) 
			{
				console.log(jqXHR);
			}
		});
	});
	
	// BUTTON
	$('#contest-photo-gallery').on('click', '#wppc-button-filter', function(event) {
		event.preventDefault();
		
		$('.wppc-select-filter').prop('selectedIndex',0);
		
		var data = {
			'action': 'wppc-filter-photos',
			'wppc-id': $(this).prev().val(),
			'wppc-filter': $(this).val(),
			'wppc-url': document.URL,
		};

		$.ajax({
			url: wppcSubmitPhoto.ajaxurl,
			type: 'POST',
			data: data,
			dataType: 'html',
			beforeSend: function(jqXHR, settings) {
				$('#wppc-photos').html('<div style="text-align: center;"><img src="http://www.uncover-romania.com/wp-content/plugins/wp-photo-contest/img/ajax-loading.gif" style="width: 20px !important;"></div>');
				if ($('#wppc-button-filter').val() === "mobile-only")
						$('#wppc-button-filter').val("mobile-all").text("All photos");
					else
						$('#wppc-button-filter').val("mobile-only").text("Mobile only");
			},		
			success: function(data, textStatus, jqXHR) {
				console.log(data);
				$('#wppc-photos').html(data);
				$(".group1").colorbox({rel:'group1', transition:"fade", maxWidth: "70%"});

				// parse the whole page pin
				window.parsePinBtns();
			},
			error: function(jqXHR, textStatus, errorThrown) 
			{
				console.log(jqXHR);
			}
		});
	});
	

	/**
	 * VOTE A PHOTO
	 */
	$('#contest-photo-gallery').on('submit', 'form.wppc-vote-photo', function(event) {
		event.preventDefault();

		//console.log($(this).serialize());
		
		$.ajax({
			url: wppcSubmitPhoto.ajaxurl,
			type: 'POST',
			data: $(this).serialize(),
			dataType: 'json',
			beforeSend: function(jqXHR, settings) {
				$('.wppc-vote-photo').addClass('disabled');
				//$('.wppc-vote-photo').parent().append('<span><img src="http://dekon.go.ro/uncover-romania.com/wp-content/plugins/wp-photo-contest/img/ajax-loading.gif" style="width: 20px !important;"></span>')
			},
			success: function(data, textStatus, jqXHR) {
				var target = '#vote-results-'+data['wppc-value'];
				var result = $(target);
				console.log(data);
				//console.log(target);

				switch (data.voteAdded)
				{
					// user vote was successful
					case true:
						result.hide().removeClass('alert-warning alert-info alert-success')
						result.show().addClass('alert-success').html('<p>Vote added</p>');
						result.prev('p').html(data.photoPoints + ' points with ' + data.photoVotes + ' votes so far');
						break;

					// user can't vote anymore
					case false:
						result.hide().removeClass('alert-warning alert-info alert-success')
						result.show().addClass('alert-info').html('<p>You can\'t vote anymore</p>');
						break;

					// user already voted in the last 24h
					case "24H":
						result.hide().removeClass('alert-warning alert-info alert-success')
						result.show().addClass('alert-warning').html('<p>Already voted in the last 24h</p>');
						break;
				}
				$('.wppc-vote-photo').removeClass('disabled');
			},
			error: function(jqXHR, textStatus, errorThrown) 
			{
				console.log(jqXHR);
			}
		});
		
	});



	/**
	 * ADD PHOTO TO CONTEST
	 */
	$('#contest-entry-form').on('click', '#wppc-submit', function(event) {
		event.preventDefault();

		var wppcOk = '<span class="glyphicon glyphicon-ok form-control-feedback"></span>';
		var wppcError = '<span class="glyphicon glyphicon-remove form-control-feedback"></span>';

		var data = new FormData();
		$.each($('#wppc-photo')[0].files, function(i, file) {
			data.append('file-'+i, file);
		});
		data.append('action', wppcSubmitPhoto.action);
		data.append('wppc-id', $('#wppc-id').val());
		data.append('wppc-name', $('#wppc-name').val());
		data.append('wppc-email', $('#wppc-email').val()); 
		data.append('wppc-mobile-photo', $('#wppc-mobile-photo').is(':checked'));
		data.append('wppc-photo-name', $('#wppc-photo-name').val());
		data.append('wppc-photo-location', $('#wppc-photo-location').val());
		data.append('wppc-agree-rules', $('#wppc-agree-rules').is(':checked'));
		
		$.ajax({
			url: wppcSubmitPhoto.ajaxurl,
			type: 'POST',
			data:  data,
			mimeType:"multipart/form-data",
			contentType: false,
			dataType: 'json',
			cache: false,
			processData: false,
			beforeSend: function(jqXHR, settings) {
				$('#wppc-submit').addClass('disabled');
				$('#wppc-loading').show();
			},
			success: function(data, textStatus, jqXHR)
			{
				console.log(data);

				switch (data.entryAdded)
				{
					// photo was added to the contest
					case true:
						var html = "<div class='alert alert-success' style='text-align: center;' role='alert'>";
							html += "Upload successful.";
							html += "</div>";
						$('#wppc-results').html(html).show();
						$('.glyphicon').remove();
						$('div').removeClass('has-error has-success');
						$('#wppc-photo, #wppc-photo-name, #wppc-photo-location').val('');
						break;

					// user already uploaded the allowed number of photos
					case "TOTAL":
						var html = "<div class='alert alert-warning' style='text-align: center;' role='alert'>";
							html += "You already uploaded the allowed number of photos.";
							html += "</div>";
						$('#wppc-results').html(html).show();
						$('.glyphicon').remove();
						//$('#wppc-form').remove();
						break;

					// user alreaded uploaded the allowed number of mobile photos
					case "MOBILE":
						var html = "<div class='alert alert-warning' style='text-align: center;' role='alert'>";
							html += "You already uploaded the allowed number of mobile photos.";
							html += "</div>";
						$('#wppc-results').html(html).show();
						break;

					// photo wasn't uploaded
					case "FILEFAIL":
						var html = "<div class='alert alert-warning' style='text-align: center;' role='alert'>";
							html += "You already uploaded the allowed number of mobile photos.";
							html += "</div>";
						$('#wppc-results').html(html).show();
						break;

					// default case - some fields are empty
					default:
						$('#wppc-results').hide();
						wppcValidate(data);
						break;
				}

				$('#wppc-submit').removeClass('disabled');
				$('#wppc-loading').hide();
			},
			error: function(jqXHR, textStatus, errorThrown) 
			{
				console.log(jqXHR);
			} 	        
		});



		// VALIDATE FORM
		function wppcValidate(data)
		{
			// validate name
			validateWWPPCField($('#wppc-name'), data.name);

			// validate email address
			validateWWPPCField($('#wppc-email'), data.email);

			// validate photo 		-- TEMPORARY
			validateWWPPCField($('#wppc-photo'), data.photo);

			// validate mobile photo
			validateWWPPCField($('#wppc-mobile-photo'), data.mobilePhoto);

			// validate photo name
			validateWWPPCField($('#wppc-photo-name'), data.photoName);

			// validate photo location
			validateWWPPCField($('#wppc-photo-location'), data.photoLocation);

			// validate rules agreement
			validateWWPPCField($('#wppc-agree-rules'), data.agreeRules); 
		}

		function validateWWPPCField(field, data)
		{
			if (data == '')
					{
						field.parent().removeClass('has-error has-success');
						field.next().remove();
						field.parent().addClass('has-error');
						field.after(wppcError);
					}
				else 
					{
						field.parent().removeClass('has-error has-success');
						field.next().remove();
						field.parent().addClass('has-success');
						field.after(wppcOk);
					}
			
			// check if file is the right type
			if (field == $('#wppc-photo') && data == '')
			{
				field.parent().removeClass('has-error has-success');
				field.next().remove();
				field.parent().addClass('has-error');
				field.after(wppcError);
			}
		}
	});
});