jQuery(function($) {
    $('.more_advanced').click(function(e) {
        if(e.target === $('.iconhelp',this)[0]){
            return true;        
        }
        var _this = $(this).closest('.more_advanced_wrap').siblings('.rollover_advanced_options');
        if(_this.hasClass('open')) {
            $('.text', this).text('Show advanced options');
            $(_this).stop(true, true).slideUp('fast');
            $(_this).removeClass('open');
            $('.arrow_border', this).show();
            $('.arrow_light', this).show();
        } else {
            $('.text', this).text('Hide advanced options');
            $(_this).stop(true, true).slideDown('fast');
            $(_this).addClass('open');
            $('.arrow_border', this).hide();
            $('.arrow_light', this).hide();
        }
    });
});

