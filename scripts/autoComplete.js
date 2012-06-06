jQuery(document).ready(function() {

	var courses_search = new Array();
	var courses = new Array();

	jQuery.blockUI({ message: '<div class="blockui_loading">Please wait, loading module lists.</div>' });

	$("#dialog_autocomplete_error").dialog({
		 autoOpen: false,
		 title: 'Rollover error',
		 modal: true,
		 buttons: {
			"OK": function() {
				$(this).dialog("close");
			}
		 }
	});

	// we're making two requests, one to the saml auth page, to allow SSO to log
	// the user in if they're currently unauthed, and then a second to get
	// the actual autocomplete data

	// although one request would be preferable, it's a little flaky with SSO
	// and there's no guarantee SSO will redirect the user back to the auto-
	// complete api when they're done.
	
	// the auth request is commented out for now, as for the time being SSO isn't
	// enabled for 1.9 - when it is, we can uncomment this stuff

	/*
	jQuery.ajax({
		url: window.oldMoodleAuthUrl,
		success: function(data) {
	*/
			
			// hopefully the user has been logged in by now, so we can make the
			// modulelist api request

	jQuery.ajax({
		url: window.autoCompleteUrl,
		dataType: 'json',
		success: function(data){

			//First check if we have courses and error if so.
			if (data === null){
				jQuery.unblockUI();
				$("#dialog_autocomplete_error").dialog("open");
			} else {

				jQuery.unblockUI();
				if (data !== null){
					for(var course in data.courses) {
						courses_search.push(data.courses[course].fullname);
						courses[data.courses[course].fullname] = [course, '1.9'];
					}
				}

				jQuery('.rollover_crs_input').autocomplete({
					minLength: 1,
					source: function(request, response) {
						var results = jQuery.ui.autocomplete.filter(courses_search, request.term);
						response(results.slice(0, 30));
					},
					delay: 0,
					select: function(event, ui) {
						jQuery(this).closest('.rollover_crs_from').find('.id_from').val(courses[ui.item.label][0]);
						if(courses[ui.item.label][1] === '1.9') {
							jQuery(this).parent().find('.m1 input').attr('disabled', 'disabled').removeAttr('checked');
						} else {
							jQuery(this).parent().find('.m1 input').attr('checked', 'checked').removeAttr('disabled');
						}
					},
					change: function(event, ui){
						var change_results = jQuery.ui.autocomplete.filter(courses_search, jQuery(this).val().trim());
						if(change_results.length === 0 || jQuery(this).val() === ''){
							jQuery(this).closest('.rollover_crs_from').find('.id_from').val('');
						} else {
							jQuery(this).closest('.rollover_crs_from').find('.id_from').val(courses[change_results[0]][0]);
						}
					}
				}).focus(function() {
                                                $(this).autocomplete("search");
                                            });

			}
		},
		error: function(x, t, m){
			jQuery.unblockUI();
			$("#dialog_autocomplete_error").dialog("open");
		},
		timeout: 20000 //20 Seconds max to try and fetch
	});

		/*
		},
		error: function(x, t, m) {
			jQuery.unblockUI();
			$("#dialog_autocomplete_error").dialog("open");
		}
	})
  */

	
});

