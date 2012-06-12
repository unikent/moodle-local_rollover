jQuery(document).ready(function() {

	var courses_search = new Array();
	var courses = new Array();

	

	var course_data = getCourseDataFromCache();

	// if the cache was empty, or the search array is empty, just force a refresh
	if (!course_data || course_data.courses_search.length == 0) {
		course_data = refreshCourseData();
	} else {
		// no need to fetch, the cache was ok, so just populate with the cache data
		populateCourseAutoComplete(course_data);
	}

	

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

	/*
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
	*/

		/*
		},
		error: function(x, t, m) {
			jQuery.unblockUI();
			$("#dialog_autocomplete_error").dialog("open");
		}
	})
  */

	
});


var getCourseDataFromCache = function() {

	if (!localStorage || !JSON) {
		return null;
	}

	var cache_json = localStorage.getItem('rollover_autocomplete_data');

	if (!cache_json) {
		return null;
	}

	var cache = JSON.parse(cache_json);

	if (!cache) {
		return null;
	}

	// use cached value if the timestamp is not older than 5 min
	if (cache.timestamp + 300000 > new Date().getTime()) {
		
		return {
			courses: cache.courses,
			courses_search: cache.courses_search
		};
	} else {
		return null;
	}
}

var refreshCourseData = function() {

	jQuery.blockUI({ message: '<div class="blockui_loading">Please wait, loading module lists.</div>' });

	var course_data = {
		courses: [],
		courses_search: []
	};

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
						course_data.courses_search.push(data.courses[course].fullname);
						course_data.courses[data.courses[course].fullname] = [course, '1.9'];
					}
				}

				// try to cache this data to localstorage if possible
				if (localStorage && JSON) {
					local_data = {
						timestamp: new Date().getTime(),
						courses: course_data.courses,
						courses_search: course_data.courses_search
					};
					localStorage.setItem('rollover_autocomplete_data', JSON.stringify(local_data));
					
				}

				populateCourseAutoComplete(course_data);

			}
		},
		error: function(x, t, m){
			jQuery.unblockUI();
			$("#dialog_autocomplete_error").dialog("open");
		},
		timeout: 20000 //20 Seconds max to try and fetch
	});

}

var populateCourseAutoComplete = function(course_data) {

	jQuery('.rollover_crs_input').autocomplete({
		minLength: 1,
		source: function(request, response) {
			var results = jQuery.ui.autocomplete.filter(course_data.courses_search, request.term);
			response(results.slice(0, 30));
		},
		delay: 0,
		select: function(event, ui) {
			jQuery(this).closest('.rollover_crs_from').find('.id_from').val(course_data.courses[ui.item.label][0]);
			if(course_data.courses[ui.item.label][1] === '1.9') {
				jQuery(this).parent().find('.m1 input').attr('disabled', 'disabled').removeAttr('checked');
			} else {
				jQuery(this).parent().find('.m1 input').attr('checked', 'checked').removeAttr('disabled');
			}
		},
		change: function(event, ui){
			var change_results = jQuery.ui.autocomplete.filter(course_data.courses_search, jQuery(this).val().trim());
			if(change_results.length === 0 || jQuery(this).val() === ''){
				jQuery(this).closest('.rollover_crs_from').find('.id_from').val('');
			} else {
				jQuery(this).closest('.rollover_crs_from').find('.id_from').val(course_data.courses[change_results[0]][0]);
			}
		}
	}).focus(function() {
		$(this).autocomplete("search");
	});
};