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
  window.showBookingModal = function(event) {
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
              <p><strong>Cliente:</strong> 
                <input type="text" id="rbf-customer-name" class="rbf-edit-field" value="${event.extendedProps.customer_name || ''}" placeholder="Nome e cognome">
              </p>
              <p><strong>Email:</strong> 
                <input type="email" id="rbf-customer-email" class="rbf-edit-field" value="${event.extendedProps.customer_email || ''}" placeholder="email@example.com" required>
              </p>
              <p><strong>Telefono:</strong> 
                <input type="tel" id="rbf-customer-phone" class="rbf-edit-field" value="${event.extendedProps.customer_phone || ''}" placeholder="+39 123 456 7890">
              </p>
              <p><strong>Data:</strong> 
                <input type="date" id="rbf-booking-date" class="rbf-edit-field" value="${event.extendedProps.booking_date || ''}" required>
              </p>
              <p><strong>Orario:</strong> 
                <input type="time" id="rbf-booking-time" class="rbf-edit-field" value="${event.extendedProps.booking_time || ''}" required>
              </p>
              <p><strong>Persone:</strong> 
                <input type="number" id="rbf-booking-people" class="rbf-edit-field" value="${event.extendedProps.people || 1}" min="1" max="20" required>
              </p>
              <p><strong>Note:</strong> 
                <textarea id="rbf-booking-notes" class="rbf-edit-field" placeholder="Allergie, richieste speciali...">${event.extendedProps.notes || ''}</textarea>
              </p>
              <p><strong>Stato:</strong> 
                <select id="rbf-booking-status" class="rbf-edit-field" data-booking-id="${bookingId}">
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
      saveBookingDetails(bookingId);
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
   * Save booking details via AJAX
   */
  function saveBookingDetails(bookingId) {
    // Collect all form data
    var bookingData = {
      customer_name: $('#rbf-customer-name').val().trim(),
      customer_email: $('#rbf-customer-email').val().trim(), 
      customer_phone: $('#rbf-customer-phone').val().trim(),
      booking_date: $('#rbf-booking-date').val(),
      booking_time: $('#rbf-booking-time').val(),
      people: parseInt($('#rbf-booking-people').val()),
      notes: $('#rbf-booking-notes').val().trim(),
      status: $('#rbf-booking-status').val()
    };
    
    // Basic validation
    if (!bookingData.customer_email || !bookingData.booking_date || !bookingData.booking_time || !bookingData.people) {
      showNotification('Compilare tutti i campi obbligatori (Email, Data, Orario, Persone)', 'error');
      return;
    }
    
    if (bookingData.people < 1 || bookingData.people > 20) {
      showNotification('Il numero di persone deve essere tra 1 e 20', 'error');
      return;
    }

    $.ajax({
      url: rbfAdminData.ajaxUrl,
      type: 'POST',
      data: {
        action: 'rbf_update_booking_details',
        booking_id: bookingId,
        booking_data: bookingData,
        _ajax_nonce: rbfAdminData.nonce
      },
      success: function(response) {
        if (response.success) {
          // Show success message
          showNotification('Prenotazione aggiornata con successo', 'success');
          // Refresh calendar
          calendar.refetchEvents();
          // Close modal
          $('#rbf-booking-modal').remove();
        } else {
          showNotification(response.data || 'Errore durante l\'aggiornamento', 'error');
        }
      },
      error: function() {
        showNotification('Errore di connessione', 'error');
      }
    });
  }

  /**
   * Save booking status via AJAX (kept for backward compatibility)
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