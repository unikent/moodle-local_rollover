jQuery(document).ready(function() {

    var anitime = 200;
    var adv_height = jQuery('.rollover_advanced_options').height();
    window.pane_height = jQuery('.rollover_crs_from').height();
    jQuery('.rollover_advanced_options').css('height', '0')
    jQuery(".more_advanced").toggle(function() {
        jQuery(this).closest('.rollover_crs_from').addClass('expanded');
        jQuery(this).closest('.rollover_crs_from').find('.rollover_advanced_options').animate({
            height: adv_height
        }, anitime);
        jQuery(this).closest('form').find('.rollover_crs_title').animate({
            height: "auto"
        }, anitime);
        jQuery('.text',this).text('Hide options');
        jQuery('.arrow_border', this).hide();
        jQuery('.arrow_light', this).hide();
    }, function() {
        jQuery(this).closest('.rollover_crs_from').removeClass('expanded');
        jQuery(this).closest('.rollover_crs_from').find('.rollover_advanced_options').animate({
            height: "0px"
        }, anitime);
        jQuery(this).closest('form').find('.rollover_crs_title').animate({
            height: "auto"
        }, anitime);
        jQuery('.text',this).text('Show options');
        jQuery('.arrow_border', this).show();
        jQuery('.arrow_light', this).show();
    });
});

