jQuery(document).ready(function() {

	// Note - Promises have now arrived natively in JS
	// we should consider switching.
	$.whenAll = function( args ) {
	    var sliceDeferred = [].slice,
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

var getCourseData = function(from) {

	jQuery.blockUI({ message: 
		'<div class="blockui_loading">Please wait, loading module lists.</div>' });

	return jQuery.ajax({
		url: M.cfg.wwwroot + '/local/rollover/ajax/sources.php?dist=' + from,
		dataType: 'json',
		success: function(data){
			jQuery.unblockUI();
		},
		error: function(x, t, m){
			if (x.status == 401) {
				
			}
			jQuery.unblockUI();
		},
		timeout: 20000 // 20 Seconds max to try and fetch
	});

}

var refreshCourseData = function() {
	
	var path_names = [];
	var paths = [];

	$.each(window.rollover_paths, function (k, v) {
		path_names.push(v);
		paths.push(getCourseData(v));
	});

	jQuery.whenAll(paths).always(function() {

		var course_data = {
			courses: [],
			courses_search: []
		};

		var errors = [];

		for (var i = 0; i < arguments.length; i++) {
			var name = path_names[i];
			var data = arguments[i];

			if (data[1] !== 'error' && data[0] !== null) {
				data = data[0];

				for (var course in data) {
					var search = '[' + name + ']: ' + data[course].shortname + ' - ' + data[course].fullname;

					if (name != 'archive' && $.inArray(search, course_data.courses_search) != -1) {
						course_data.courses_search.push(search + ' [Duplicate]');
						course_data.courses[search + ' [Duplicate]'] = [data[course].moodle_id, '2014'];
					} else {
						course_data.courses_search.push(search);
						course_data.courses[search] = [data[course].moodle_id, name];
					}	
				}
			}
		};
	
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

	jQuery('.rollover_crs_input').each(function(i, el) {
        el = jQuery(el)
        el.autocomplete({
    		minLength: 1,
    		source: function(request, response) {
    			var term = request.term;
    			term = term.substring(term.indexOf(':') + 1);

    			var results = jQuery.ui.autocomplete.filter(course_data.courses_search, term);
    			results.reverse();

                var toid = el.closest('.rollover_crs_from').find('.id_to').val();
                var todist = el.closest('.rollover_crs_from').find('.src_to').val();

                // Dont allow rollover into self.
                var newresults = []
                for (var result in results) {
                    search = results[result]
                    data = course_data.courses[search]
                    if (data[0] != toid || data[1] != todist) {
                        newresults.push(search)
                    }
                }

    			response(newresults.slice(0, 30));
    		},
    		delay: 0,
    		select: function(event, ui) {
    			jQuery(this).closest('.rollover_crs_from').find('.id_from').val(course_data.courses[ui.item.label][0]);
    			jQuery(this).closest('.rollover_crs_from').find('.src_from').val(course_data.courses[ui.item.label][1]);
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
    });

	jQuery('.rollover_crs_input').each(function() {
        var self = $(this);
        var id_to = $(this).closest('.rollover_crs_from').find('.id_to').val();
        var src_to = $(this).closest('.rollover_crs_from').find('.src_to').val();

		var srch = $(this).val();
 		if (srch) {
			var results = _.filter(course_data.courses_search, function(t) {
				return t.indexOf(srch) != -1;
			});

            $.each(results, function (i, result) {
                var id_from = course_data.courses[result][0];
                var src_from = course_data.courses[result][1];

                if (id_to != id_from && src_to != src_from) {
                    self.val(result);
                    self.closest('.rollover_crs_from').find('.id_from').val(id_from);
                    self.closest('.rollover_crs_from').find('.src_from').val(src_from);
                }
            });
		}
	})
};
