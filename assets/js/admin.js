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
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,listWeek'
      },
      height: 'auto',
      eventDisplay: 'block',
      eventClick: function(info) {
        info.jsEvent.preventDefault();
        showBookingModal(info.event);
      },
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

  /**
   * Show booking modal with edit functionality
   */
  function showBookingModal(event) {
    var bookingId = event.extendedProps.booking_id;
    
    // Create modal HTML
    var modalHtml = `
      <div id="rbf-booking-modal" class="rbf-modal-overlay">
        <div class="rbf-modal-content">
          <div class="rbf-modal-header">
            <h3>Gestisci Prenotazione</h3>
            <button class="rbf-modal-close">&times;</button>
          </div>
          <div class="rbf-modal-body">
            <div class="rbf-booking-details">
              <p><strong>Cliente:</strong> <span id="rbf-customer-name">${event.extendedProps.customer_name}</span></p>
              <p><strong>Email:</strong> <span id="rbf-customer-email">${event.extendedProps.customer_email}</span></p>
              <p><strong>Telefono:</strong> <span id="rbf-customer-phone">${event.extendedProps.customer_phone}</span></p>
              <p><strong>Data:</strong> <span id="rbf-booking-date">${event.extendedProps.booking_date}</span></p>
              <p><strong>Orario:</strong> <span id="rbf-booking-time">${event.extendedProps.booking_time}</span></p>
              <p><strong>Persone:</strong> <span id="rbf-booking-people">${event.extendedProps.people}</span></p>
              <p><strong>Note:</strong> <span id="rbf-booking-notes">${event.extendedProps.notes || 'Nessuna'}</span></p>
              <p><strong>Stato:</strong> 
                <select id="rbf-booking-status" data-booking-id="${bookingId}">
                  <option value="confirmed" ${event.extendedProps.status === 'confirmed' ? 'selected' : ''}>Confermata</option>
                  <option value="cancelled" ${event.extendedProps.status === 'cancelled' ? 'selected' : ''}>Cancellata</option>
                  <option value="completed" ${event.extendedProps.status === 'completed' ? 'selected' : ''}>Completata</option>
                </select>
              </p>
            </div>
          </div>
          <div class="rbf-modal-footer">
            <button class="button button-primary" id="rbf-save-booking">Salva Modifiche</button>
            <button class="button" id="rbf-edit-full">Modifica Completa</button>
            <button class="button button-secondary rbf-modal-close">Chiudi</button>
          </div>
        </div>
      </div>
    `;
    
    // Add modal to page
    $('body').append(modalHtml);
    
    // Bind events
    $('#rbf-booking-modal .rbf-modal-close').on('click', function() {
      $('#rbf-booking-modal').remove();
    });
    
    $('#rbf-save-booking').on('click', function() {
      saveBookingStatus(bookingId, $('#rbf-booking-status').val());
    });
    
    $('#rbf-edit-full').on('click', function() {
      window.open(rbfAdminData.editUrl.replace('BOOKING_ID', bookingId), '_blank');
    });
    
    // Click outside to close
    $(document).on('click', '#rbf-booking-modal', function(e) {
      if (e.target === this) {
        $(this).remove();
      }
    });
  }

  /**
   * Save booking status via AJAX
   */
  function saveBookingStatus(bookingId, newStatus) {
    $.ajax({
      url: rbfAdminData.ajaxUrl,
      type: 'POST',
      data: {
        action: 'rbf_update_booking_status',
        booking_id: bookingId,
        status: newStatus,
        _ajax_nonce: rbfAdminData.nonce
      },
      success: function(response) {
        if (response.success) {
          // Show success message
          showNotification('Stato prenotazione aggiornato con successo', 'success');
          // Refresh calendar
          calendar.refetchEvents();
          // Close modal
          $('#rbf-booking-modal').remove();
        } else {
          showNotification('Errore nell\'aggiornamento dello stato', 'error');
        }
      },
      error: function() {
        showNotification('Errore di connessione', 'error');
      }
    });
  }

  /**
   * Show notification
   */
  function showNotification(message, type) {
    var notificationClass = type === 'success' ? 'notice-success' : 'notice-error';
    var notification = `
      <div class="notice ${notificationClass} is-dismissible rbf-notification">
        <p>${message}</p>
        <button type="button" class="notice-dismiss">
          <span class="screen-reader-text">Dismiss this notice.</span>
        </button>
      </div>
    `;
    
    $('.rbf-admin-wrap h1').after(notification);
    
    // Auto-dismiss after 3 seconds
    setTimeout(function() {
      $('.rbf-notification').fadeOut();
    }, 3000);
  }

});