jQuery(document).ready(function() {

    var anitime = 200;

    jQuery(".more_advanced").toggle(function() {
        jQuery(this).closest('.rollover_crs_from').addClass('expanded');
        jQuery(this).closest('.rollover_crs_from').find('.rollover_advanced_options').animate({
            height: "190px"
        }, anitime);
        jQuery(this).closest('form').find('.rollover_crs_title').animate({
            height: "295px"
        }, anitime);
        jQuery('.text',this).text('Hide options');
        jQuery('.arrow_border', this).hide();
        jQuery('.arrow_light', this).hide();
    }, function() {
        jQuery(this).closest('.rollover_crs_from').removeClass('expanded');
        jQuery(this).closest('.rollover_crs_from').find('.rollover_advanced_options').animate({
            height: "86px"
        }, anitime);
        jQuery(this).closest('form').find('.rollover_crs_title').animate({
            height: "191px"
        }, anitime);
        jQuery('.text',this).text('More options');
        jQuery('.arrow_border', this).show();
        jQuery('.arrow_light', this).show();
    });
});

