jQuery(document).ready(function() {

  jQuery(".rollover_crs_submit").each( function() {
    jQuery(this).click( function() {
      var button = jQuery(this);
      button.parent().parent('form')
      var o = {};
      jQuery( 'input', button.parent().parent('form') ).each( function( i, v ) {
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
     jQuery.ajax({
       url: 'schedule.php',
       type: "POST",
       data: o,
       statusCode: {
         201: function(data, s) {
          jQuery(button).closest('.from_form').fadeOut('fast', function() {
            jQuery(button).closest('.rollover_crs_from').addClass('pending').append('<h3>Pending...</h3>');
            jQuery(button).closest('.rollover_crs_from').find('h3').hide().fadeIn('slow');
          });
         },
         500: function(data, s) {
          jQuery(button).closest('.from_form').fadeOut('fast', function() {
            jQuery(button).closest('.rollover_crs_from').animate({
                backgroundColor: '#FAD7D7'
             }, 500 );
             jQuery(button).closest('.rollover_crs_from').find('.arrow').animate({
                borderRightColor: '#FAD7D7'
             }, 500 );
            jQuery(button).closest('.rollover_crs_from').addClass('error').append('<h3>Error!</h3>');
            jQuery(button).closest('.rollover_crs_from').find('h3').hide().fadeIn('slow');
          });
         }
       },
       error: function(j,t,e) {
        console.log(e);
        button.html('error');
       }
     });
     return false;
    });
  });

});
