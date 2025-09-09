/**
 * Weekly Staff View JavaScript for Restaurant Booking Plugin
 * Handles drag & drop functionality for moving bookings
 */

jQuery(function($) {
  
  /**
   * Initialize weekly staff calendar with drag & drop
   */
  var calendarEl = document.getElementById('rbf-weekly-staff-calendar');
  if (calendarEl) {
    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'timeGridWeek',
      firstDay: 1, // Monday first
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'timeGridWeek'
      },
      height: 'auto',
      eventDisplay: 'block',
      slotMinTime: '11:00:00',
      slotMaxTime: '23:00:00',
      slotDuration: '00:30:00',
      snapDuration: '00:15:00',
      
      // Enable drag & drop
      editable: true,
      eventResizableFromStart: false,
      eventDurationEditable: false,
      
      // Event styling for compact view
      eventClassNames: 'rbf-compact-event',
      
      // Handle event drops (drag & drop)
      eventDrop: function(info) {
        handleEventDrop(info);
      },
      
      // Handle event clicks
      eventClick: function(info) {
        info.jsEvent.preventDefault();
        showCompactBookingModal(info.event);
      },
      
      // Load events via AJAX
      events: function(fetchInfo, success, failure) {
        $.ajax({
          url: rbfWeeklyStaffData.ajaxUrl,
          type: 'POST',
          data: {
            action: 'rbf_get_weekly_staff_bookings',
            start: fetchInfo.startStr,
            end: fetchInfo.endStr,
            _ajax_nonce: rbfWeeklyStaffData.nonce
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

  /**
   * Handle event drop (drag & drop)
   */
  function handleEventDrop(info) {
    var event = info.event;
    var newStart = info.event.start;
    var bookingId = event.extendedProps.booking_id;
    
    // Extract new date and time
    var newDate = newStart.toISOString().split('T')[0]; // YYYY-MM-DD
    var newTime = newStart.toTimeString().substr(0, 5); // HH:MM
    
    // Show confirmation dialog
    if (!confirm(rbfWeeklyStaffData.labels.confirmMove)) {
      info.revert();
      return;
    }
    
    // Show loading state
    showNotification('⏳ Spostamento in corso...', 'notice-info');
    
    $.ajax({
      url: rbfWeeklyStaffData.ajaxUrl,
      type: 'POST',
      data: {
        action: 'rbf_move_booking',
        booking_id: bookingId,
        new_date: newDate,
        new_time: newTime,
        _ajax_nonce: rbfWeeklyStaffData.nonce
      },
      success: function(response) {
        if (response.success) {
          showNotification('✅ ' + rbfWeeklyStaffData.labels.moveSuccess, 'notice-success');
          
          // Update event properties
          event.setExtendedProp('booking_date', newDate);
          event.setExtendedProp('booking_time', newTime);
        } else {
          showNotification('❌ ' + (response.data || rbfWeeklyStaffData.labels.moveError), 'notice-error');
          info.revert();
        }
      },
      error: function() {
        showNotification('❌ ' + rbfWeeklyStaffData.labels.moveError, 'notice-error');
        info.revert();
      }
    });
  }

  /**
   * Show compact booking modal
   */
  function showCompactBookingModal(event) {
    var bookingId = event.extendedProps.booking_id;
    
    var modalHtml = `
      <div id="rbf-compact-modal" class="rbf-modal-overlay">
        <div class="rbf-modal-content rbf-compact-modal-content">
          <div class="rbf-modal-header">
            <h3>Prenotazione</h3>
            <button class="rbf-modal-close">&times;</button>
          </div>
          <div class="rbf-modal-body">
            <div class="rbf-compact-booking-details">
              <div class="rbf-detail-row">
                <strong>Cliente:</strong> ${event.extendedProps.customer_name}
              </div>
              <div class="rbf-detail-row">
                <strong>Data:</strong> ${formatDate(event.extendedProps.booking_date)}
              </div>
              <div class="rbf-detail-row">
                <strong>Orario:</strong> ${event.extendedProps.booking_time}
              </div>
              <div class="rbf-detail-row">
                <strong>Persone:</strong> ${event.extendedProps.people}
              </div>
              <div class="rbf-detail-row">
                <strong>Pasto:</strong> ${event.extendedProps.meal}
              </div>
              <div class="rbf-detail-row">
                <strong>Stato:</strong> 
                <span class="rbf-status-badge rbf-status-${event.extendedProps.status}">
                  ${getStatusLabel(event.extendedProps.status)}
                </span>
              </div>
            </div>
          </div>
          <div class="rbf-modal-footer">
            <button class="button button-primary" onclick="editFullBooking(${bookingId})">Modifica Completa</button>
            <button class="button rbf-modal-close">Chiudi</button>
          </div>
        </div>
      </div>
    `;
    
    $('body').append(modalHtml);
    
    // Handle modal close
    $('.rbf-modal-close').on('click', function() {
      $('#rbf-compact-modal').remove();
    });
    
    // Close on outside click
    $('.rbf-modal-overlay').on('click', function(e) {
      if (e.target === this) {
        $('#rbf-compact-modal').remove();
      }
    });
  }

  /**
   * Show notification message
   */
  function showNotification(message, type = 'notice-info') {
    var $notification = $('#rbf-move-notification');
    var $message = $('#rbf-move-message');
    
    $notification.removeClass('notice-success notice-error notice-info').addClass(type);
    $message.html(message);
    $notification.show();
    
    // Auto-hide after 3 seconds
    setTimeout(function() {
      $notification.fadeOut();
    }, 3000);
  }

  /**
   * Format date for display
   */
  function formatDate(dateStr) {
    var date = new Date(dateStr);
    return date.toLocaleDateString('it-IT', {
      weekday: 'short',
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  }

  /**
   * Get status label
   */
  function getStatusLabel(status) {
    var labels = {
      'confirmed': 'Confermata',
      'cancelled': 'Cancellata',
      'completed': 'Completata'
    };
    return labels[status] || status;
  }

  /**
   * Edit full booking (navigate to edit page)
   */
  window.editFullBooking = function(bookingId) {
    var editUrl = rbfWeeklyStaffData.editUrl.replace('BOOKING_ID', bookingId);
    window.open(editUrl, '_blank');
  };

});