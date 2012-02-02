jQuery(document).ready(function() {

    jQuery(".rollover_crs_submit").each( function() {
        jQuery(this).click( function() {
            var button = jQuery(this);
            var o = {};
            jQuery( 'input', button.closest('form') ).each( function( i, v ) {
                
                if( v.name ) {
                    if( v.type == 'checkbox' ) {
                        if( v.checked ) {
                            o[v.name] = 1;
                        }
                    } else {
                        o[v.name] = v.value;
                    }
                }
            });

            $("#dialog_id_from_error").dialog({
                 autoOpen: false,
                 title: 'Rollover error',
                 modal: true,
                 buttons: {
                    "OK": function() {
                        $(this).dialog("close");
                    }
                 }
            });

            $("#dialog_id_to_error").dialog({
                 autoOpen: false,
                 title: 'Rollover error',
                 modal: true,
                 buttons: {
                    "OK": function() {
                        $(this).dialog("close");
                    }
                 }
            });

            $("#dialog_sure").dialog({
                 autoOpen: false,
                 title: 'Are you sure?',
                 draggable:false,
                 modal: true,
                 buttons: {
                     "Yes": function() {
                         $(this).dialog("close");
                         console.log(o);
                         jQuery.ajax({
                            url: 'schedule.php',
                            type: "POST",
                            data: o,
                            statusCode: {
                                201: function(data, s) {
                                    if(jQuery(button).closest('.rollover_crs_from ').hasClass('expanded')) {
                                        jQuery(button).closest('.rollover_crs_from ').css('height', '313');
                                    }
                                    jQuery(button).closest('.from_form').fadeOut('fast', function() {
                                        jQuery(button).closest('.rollover_crs_from').addClass('pending').append(window.pendingMessage);
                                        jQuery(button).closest('.rollover_crs_from').find('h3').hide().fadeIn('slow');
                                    });
                                },
                                500: function(data, s) {
                                    if(jQuery(button).closest('.rollover_crs_from ').hasClass('expanded')) {
                                        jQuery(button).closest('.rollover_crs_from ').css('height', '313');
                                    }
                                    jQuery(button).closest('.from_form').fadeOut('fast', function() {
                                        jQuery(button).closest('.rollover_crs_from').animate({
                                            backgroundColor: '#FAD7D7'
                                        }, 500 );
                                        jQuery(button).closest('.rollover_crs_from').find('.arrow').animate({
                                            borderRightColor: '#FAD7D7'
                                        }, 500 );
                                        jQuery(button).closest('.rollover_crs_from').addClass('error').append(window.errorMessage);
                                        jQuery(button).closest('.rollover_crs_from').find('h3').hide().fadeIn('slow');
                                    });
                                }
                            },
                            error: function(j,t,e) {
                                console.log(e);
                                button.html('error');
                            }
                        });

                    }
                    , "No": function() {
                        $(this).dialog("close");
                    }
                }
           });

            //Validate everything before proceeding at the end
            var search_string = jQuery(button).siblings('.rollover_crs_input').val();
            var id_from = jQuery(button).siblings('.id_from').val();
            var id_to = jQuery(button).siblings('.id_to').val();

            if (search_string === '' || id_from === '' || isNaN(id_from)){
                $("#dialog_id_from_error").dialog("open");
            } else if (isNaN(id_to)) {
                alert("IDTO:"+id_to);
                $("#dialog_id_to_error").dialog("open");
            } else {
                $("#dialog_sure").dialog("open");
            }

            return false;
        });
    });

});
