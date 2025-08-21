jQuery(function($){
  var el = document.getElementById('rbf-calendar'); if(!el) return;
  var calendar = new FullCalendar.Calendar(el,{
    initialView:'dayGridMonth',
    firstDay:1,
    events:function(fetchInfo,success,failure){
      $.ajax({
        url: rbfAdminData.ajaxUrl, type: 'POST',
        data: { action:'rbf_get_bookings_for_calendar', start:fetchInfo.startStr, end:fetchInfo.endStr, _ajax_nonce: rbfAdminData.nonce },
        success: function(r){ if(r.success) success(r.data); else failure(); },
        error: failure
      });
    }
  });
  calendar.render();
});
