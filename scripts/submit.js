jQuery(document).ready(function() {

    jQuery(".rollover_crs_submit").each( function() {
        jQuery(this).click( function() {

            var button = jQuery(this);
            var form = button.closest('form');

            var options = {};
            jQuery('input.rollover_option_item', form).each(function (i, v) {
                if (v.name) {
                    if (v.checked) {
                        options[v.name] = 1;
                    } else {
                        options[v.name] = 0;
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
                         jQuery.ajax({
                            url: 'ajax/schedule.php',
                            type: "POST",
                            data: {
                                'from_dist': form.find('.src_from').val(),
                                'from_course': form.find('.id_from').val(),
                                'to_course': form.find('.id_to').val(),
                                'options': JSON.stringify(options)
                            },
                            success: function (data, textStatus, jqXHR) {
                                if (data.error) {
                                    if (jQuery(button).closest('.rollover_crs_from ').hasClass('expanded')) {
                                        jQuery(button).closest('.rollover_crs_from ').css('height', window.pane_height);
                                    }

                                    jQuery(button).closest('.from_form').fadeOut('fast', function() {
                                        jQuery(button).closest('.rollover_crs_from').animate({
                                            backgroundColor: '#FAD7D7'
                                        }, 500 );

                                        jQuery(button).closest('.rollover_crs_from').find('.arrow').animate({
                                            borderRightColor: '#FAD7D7'
                                        }, 500 );

                                        jQuery(button).closest('.rollover_crs_from')
                                            .addClass('error')
                                            .append('<h3>Error!</h3><p>' + data.error.substr(6) + '</p>');

                                        jQuery(button).closest('.rollover_crs_from').find('h3')
                                            .hide()
                                            .fadeIn('slow');
                                    });

                                    return;
                                }

                                if (jQuery(button).closest('.rollover_crs_from ').hasClass('expanded')) {
                                    jQuery(button).closest('.rollover_crs_from ').css('height', window.pane_height);
                                }

                                jQuery(button).closest('.from_form').fadeOut('fast', function() {
                                    jQuery(button).closest('.rollover_crs_from').addClass('pending').append(M.str.local_rollover.requestedmessage);
                                    jQuery(button).closest('.rollover_crs_from').find('h3').hide().fadeIn('slow');
                                });
                            },
                            error: function(jqXHR, textStatus, errorThrown) {
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
                $("#dialog_id_to_error").dialog("open");
            } else {
                $("#dialog_sure").dialog("open");
            }

            return false;
        });
    });

});
