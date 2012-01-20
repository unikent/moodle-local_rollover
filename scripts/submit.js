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
          button.html('doing');
         },
         500: function(data, s) {
          button.html('fail');
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
