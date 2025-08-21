/**
 * Admin JavaScript for Restaurant Booking Plugin
 */

jQuery(function($) {
  
  /**
   * Initialize calendar if element exists
   */
  var calendarEl = document.getElementById('rbf-calendar');
  if (calendarEl) {
    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      firstDay: 1,
      events: function(fetchInfo, success, failure) {
        $.ajax({
          url: rbfAdminData.ajaxUrl,
          type: 'POST',
          data: {
            action: 'rbf_get_bookings_for_calendar',
            start: fetchInfo.startStr,
            end: fetchInfo.endStr,
            _ajax_nonce: rbfAdminData.nonce
          },
          success: function(response) {
            if (response.success) {
              success(response.data);
            } else {
              failure();
            }
          },
          error: failure
        });
      }
    });
    calendar.render();
  }

});