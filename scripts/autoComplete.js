jQuery(document).ready(function() {

    var courses_search = new Array();
    var courses = new Array();

    jQuery.getJSON(window.autoCompleteUrl, function(data) {
        for(var course in data.courses) {
            courses_search.push(data.courses[course].fullname);
            courses[data.courses[course].fullname] = [course, '1.9'];   
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
            }
        });
    });
});

