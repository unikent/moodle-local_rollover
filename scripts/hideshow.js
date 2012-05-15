jQuery(function($) {
    $('.more_advanced').click(function(e) {
        if(e.target === $('.iconhelp',this)[0]){
            return true;        
        }
        if($('.rollover_advanced_options').hasClass('open')) {
            $('.text',this).text('Show advanced options');
            $('.rollover_advanced_options').stop(true, true).slideUp('fast');
            $('.rollover_advanced_options').removeClass('open');
            $('.arrow_border', this).show();
            $('.arrow_light', this).show();
        } else {
            $('.text',this).text('Hide advanced options');
            $('.rollover_advanced_options').stop(true, true).slideDown('fast');
            $('.rollover_advanced_options').addClass('open');
            $('.arrow_border', this).hide();
            $('.arrow_light', this).hide();
        }
    });
});

