jQuery(document).ready(function() {

	$.whenAll = function( firstParam ) {
	    var args = arguments,
	        sliceDeferred = [].slice,
	        i = 0,
	        length = args.length,
	        count = length,
	        rejected,
	        deferred = length <= 1 && firstParam && jQuery.isFunction( firstParam.promise )
	            ? firstParam
	            : jQuery.Deferred();
	    
	    function resolveFunc( i, reject ) {
	        return function( value ) {
	            rejected = true;
	            args[ i ] = arguments.length > 1 ? sliceDeferred.call( arguments, 0 ) : value;
	            if ( !( --count ) ) {
	                // Strange bug in FF4:
	                // Values changed onto the arguments object sometimes end up as undefined values
	                // outside the $.when method. Cloning the object into a fresh array solves the issue
	                var fn = rejected ? deferred.rejectWith : deferred.resolveWith;
	                fn.call(deferred, deferred, sliceDeferred.call( args, 0 ));
	            }
	        };
	    }
	    
	    if ( length > 1 ) {
	        for( ; i < length; i++ ) {
	            if ( args[ i ] && jQuery.isFunction( args[ i ].promise ) ) {
	                args[ i ].promise().then( resolveFunc(i), resolveFunc(i, true) );
	            } else {
	                --count;
	            }
	        }
	        if ( !count ) {
	            deferred.resolveWith( deferred, args );
	        }
	    } else if ( deferred !== firstParam ) {
	        deferred.resolveWith( deferred, length ? [ firstParam ] : [] );
	    }
	    return deferred.promise();
	};

	var courses_search = new Array();
	var courses = new Array();
	var course_data = refreshCourseData();

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
	
});

var getArchiveCourseData = function() {

	jQuery.blockUI({ message: 
		'<div class="blockui_loading">Please wait, loading Archive module lists.</div>' });

	return jQuery.ajax({
		url: window.archiveAutoCompleteUrl,
		dataType: 'json',
		success: function(data){
			jQuery.unblockUI();
		},
		error: function(x, t, m){
			jQuery.unblockUI();
		},
		timeout: 20000 //20 Seconds max to try and fetch
	});

}

var get2012CourseData = function() {

	jQuery.blockUI({ message: 
		'<div class="blockui_loading">Please wait, loading 2012/2013 module lists.</div>' });

	return jQuery.ajax({
		url: window.twentyTwelveAutoCompleteUrl,
		dataType: 'json',
		success: function(data){
			jQuery.unblockUI();
		},
		error: function(x, t, m){
			jQuery.unblockUI();
		},
		timeout: 20000 //20 Seconds max to try and fetch
	});

}

var getCurrentCourseData = function() {
	jQuery.blockUI({ message: 
		'<div class="blockui_loading">Please wait, loading Current module lists.</div>' });
 	return jQuery.ajax({
		url: window.autoCompleteUrl,
		dataType: 'json',
		success: function(data) {
			jQuery.unblockUI();
		},
		error: function(x, t, m){
			jQuery.unblockUI();
		},
		timeout: 20000
	});
}

var refreshCourseData = function() {

	//Note: the whenAll function not native jQuery, it is defined earlier
	//in this document and was created by InfinitiesLoop in answer to a problem
	//on stackoverflow. 
	//http://stackoverflow.com/questions/5518181/jquery-deferreds-when-and-the-fail-callback-arguments

	jQuery.whenAll(getArchiveCourseData(), getCurrentCourseData(), get2012CourseData()).always(function(aData, cData, tData){

		var course_data = {
			courses: [],
			courses_search: []
		};

		var errors = [];

		if(aData[1] === 'error' || aData[0] === null) {
			errors.push('We were unable to access the Archive Moodle! You will not be able to rollover from Archive modules');
		} else {
			aData = aData[0];
			for(var course in aData.courses) {

				var search = "[archive]: - " + aData.courses[course].shortname + " - " + aData.courses[course].fullname;

				course_data.courses_search.push(search);
				course_data.courses[search] = [course, '1.9'];
			}
		}

		if(cData[1] === 'error' || cData[0] === null) {
			errors.push('We were unable to access the Current Moodle modules! You will not be able to rollover from Current modules');
		} else {
			cData = cData[0];
			for(var course in cData.courses) {

				var search = "[2013/2014]: " + cData.courses[course].shortname + " - " + cData.courses[course].fullname;

				if($.inArray(search, course_data.courses_search) != -1) {
					course_data.courses_search.push(search + ' [Moodle 2/Duplicate]');
					course_data.courses[search + ' [Moodle 2/Duplicate]'] = [course, '2'];
				} else {
					course_data.courses_search.push(search);
					course_data.courses[search] = [course, '2'];
				}	
			}
		}

		if(tData[1] === 'error' || tData[0] === null) {
			errors.push('We were unable to access the 2012/2013 Moodle modules! You will not be able to rollover from 2012/2013 modules');
		} else {
			tData = tData[0];
			for(var course in tData.courses) {

				var search = "[2012/2013]: " + tData.courses[course].shortname + " - " + tData.courses[course].fullname;

				if($.inArray(search, course_data.courses_search) != -1) {
					course_data.courses_search.push(search + ' [Moodle 2/Duplicate]');
					course_data.courses[search + ' [Moodle 2/Duplicate]'] = [course, 'twentytwelve'];
				} else {
					course_data.courses_search.push(search);
					course_data.courses[search] = [course, 'twentytwelve'];
				}	
			}
		}
	
		if(errors.length > 0) {
			var err_txt = '<ul>';
			$.each(errors, function(k, v) {
				err_txt += '<li>' + v + '</li>'; 
			});	
			err_txt += '<ul>';
			$("#dialog_autocomplete_error").html(err_txt).dialog("open");
		}

		populateCourseAutoComplete(course_data);
	});
}

var populateCourseAutoComplete = function(course_data) {

	jQuery('.rollover_crs_input').autocomplete({
		minLength: 1,
		source: function(request, response) {
			var id_to = jQuery(this.element).closest('.rollover_crs_from').find('.id_to').val();
			
			var course = -1;
			// loop through courses to find this course by id
			for(var c in course_data.courses) {
				if(course_data.courses[c][0] == id_to) {
					course = c;
					break;
				}
			}

			// create a filtered copy without this course id as an option
			var filtered_course_data = jQuery.extend(true, {}, course_data);

			if(course != -1) {
				// filter this id_to out of data
				filtered_course_data.courses_search.splice(filtered_course_data.courses_search.indexOf(course), 1);
			}

			var results = jQuery.ui.autocomplete.filter(filtered_course_data.courses_search, request.term);
			response(results.slice(0, 30));
		},
		delay: 0,
		select: function(event, ui) {
			jQuery(this).closest('.rollover_crs_from').find('.id_from').val(course_data.courses[ui.item.label][0]);
			jQuery(this).closest('.rollover_crs_from').find('.src_from').val(course_data.courses[ui.item.label][1]);
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
				jQuery(this).closest('.rollover_crs_from').find('.src_from').val('');
			} else {
				jQuery(this).closest('.rollover_crs_from').find('.id_from').val(course_data.courses[change_results[0]][0]);
				jQuery(this).closest('.rollover_crs_from').find('.src_from').val(course_data.courses[change_results[0]][1]);
			}
		}
	}).focus(function() {
		$(this).autocomplete("search");
	});

	jQuery('.rollover_crs_input').each(function() {
		var srch = $(this).val();
 		if(srch) {
 			var id_to = $(this).closest('.rollover_crs_from').find('.id_to').val();

			var results = _.filter(course_data.courses_search, function(t) {
				// do not select if this is this module
				var course = -1;
				// loop through courses
				for(var c in course_data.courses) {
					if(c == t) {
						course = course_data.courses[c][0]
						break;
					}
				}

				// exclude this course
				if(id_to == course) {
					return false;
				}

				return t.indexOf(srch) != -1;
			}).reverse();

			var shrt_cd = $(this).closest('.rollover_layout').find('.rollover_sc_num').text() + ' ';

			if(results.length > 0) {
				if(results[0].split('-')[0] === shrt_cd) {
					results.shift();
				}
				if(results.length > 0) {

					if(course_data.courses[results[0]][1] === '1.9') {
						$(this).parent().find('.m1 input').attr('disabled', 'disabled').removeAttr('checked');
					}

					$(this).val(results[0]);
					$(this).closest('.rollover_crs_from').find('.id_from').val(course_data.courses[results[0]][0]);
					$(this).closest('.rollover_crs_from').find('.src_from').val(course_data.courses[results[0]][1]);
				}
			} else {
				$(this).val("");
			}
		}
	})
};
