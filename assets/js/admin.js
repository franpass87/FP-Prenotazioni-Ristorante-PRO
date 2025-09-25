/**
 * Admin JavaScript for Restaurant Booking Plugin
 */

jQuery(function($) {

  const baseAdminData = (typeof window.rbfAdminData === 'object' && window.rbfAdminData) ? window.rbfAdminData : {};
  window.rbfAdminData = {
    ajaxUrl: baseAdminData.ajaxUrl || '',
    nonce: baseAdminData.nonce || '',
    editUrl: baseAdminData.editUrl || ''
  };

  const tabGroups = [];

  function setupAdminTabs() {
    const navs = document.querySelectorAll('.rbf-admin-tabs');

    navs.forEach((nav) => {
      const wrap = nav.closest('.rbf-admin-wrap') || document;
      const links = Array.from(nav.querySelectorAll('.rbf-tab-link'));
      const panels = Array.from(wrap.querySelectorAll('.rbf-tab-panel'));

      if (!links.length || !panels.length) {
        return;
      }

      const group = {
        nav,
        links,
        panels,
        activate(target) {
          const desired = (target || '').replace(/^#/, '');
          const hasMatch = desired && nav.querySelector('[data-tab-target="' + desired + '"]');
          const fallback = links[0] ? links[0].dataset.tabTarget : '';
          const resolved = hasMatch ? desired : fallback;

          if (!resolved) {
            return;
          }

          links.forEach((link) => {
            const isActive = link.dataset.tabTarget === resolved;
            link.classList.toggle('nav-tab-active', isActive);
            link.setAttribute('aria-selected', isActive ? 'true' : 'false');
          });

          panels.forEach((panel) => {
            const isActive = panel.dataset.tabPanel === resolved;
            panel.classList.toggle('is-active', isActive);
            panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            panel.hidden = !isActive;
          });
        }
      };

      tabGroups.push(group);

      links.forEach((link) => {
        const target = link.dataset.tabTarget || '';

        if (target) {
          link.setAttribute('aria-controls', target);
        }

        link.addEventListener('click', (event) => {
          event.preventDefault();
          group.activate(target);

          if (target && window.location.hash !== '#' + target && typeof window.history.replaceState === 'function') {
            try {
              window.history.replaceState(null, '', '#' + target);
            } catch (e) {
              // Ignore history failures (e.g., older browsers)
            }
          }
        });

        link.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            link.click();
          }
        });
      });

      const initialTarget = window.location.hash ? window.location.hash.substring(1) : '';
      group.activate(initialTarget);
    });
  }

  setupAdminTabs();

  window.addEventListener('hashchange', () => {
    const target = window.location.hash ? window.location.hash.substring(1) : '';

    if (!target) {
      return;
    }

    tabGroups.forEach((group) => {
      const hasTarget = group.nav.querySelector('[data-tab-target="' + target + '"]');
      if (hasTarget) {
        group.activate(target);
      }
    });
  });

  /**
   * Initialize calendar if element exists
   */
  let calendarInstance = null;

  function initCalendar() {
    const calendarEl = document.getElementById('rbf-calendar');

    if (!calendarEl) {
      return;
    }

    if (!window.FullCalendar || typeof window.FullCalendar.Calendar !== 'function') {
      window.setTimeout(initCalendar, 150);
      return;
    }

    if (calendarInstance) {
      return;
    }

    calendarInstance = new window.FullCalendar.Calendar(calendarEl, {
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
        if (!window.rbfAdminData.ajaxUrl) {
          failure();
          return;
        }

        $.ajax({
          url: window.rbfAdminData.ajaxUrl,
          type: 'POST',
          data: {
            action: 'rbf_get_bookings_for_calendar',
            start: fetchInfo.startStr,
            end: fetchInfo.endStr,
            _ajax_nonce: window.rbfAdminData.nonce
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

    calendarInstance.render();
  }

  initCalendar();
  window.addEventListener('load', initCalendar);

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
                <input type="text" id="rbf-customer-name" value="${event.extendedProps.customer_name}" style="width: 100%; padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px;">
              </p>
              <p><strong>Email:</strong> 
                <input type="email" id="rbf-customer-email" value="${event.extendedProps.customer_email}" style="width: 100%; padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px;">
              </p>
              <p><strong>Telefono:</strong> 
                <input type="tel" id="rbf-customer-phone" value="${event.extendedProps.customer_phone}" style="width: 100%; padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px;">
              </p>
              <p><strong>Data:</strong> <span id="rbf-booking-date">${event.extendedProps.booking_date}</span></p>
              <p><strong>Orario:</strong> <span id="rbf-booking-time">${event.extendedProps.booking_time}</span></p>
              <p><strong>Persone:</strong> 
                <input type="number" id="rbf-booking-people" value="${event.extendedProps.people}" min="1" max="30" style="width: 80px; padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px;">
              </p>
              <p><strong>Note:</strong> 
                <textarea id="rbf-booking-notes" rows="2" style="width: 100%; padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;">${event.extendedProps.notes || ''}</textarea>
              </p>
              <p><strong>Stato:</strong> 
                <select id="rbf-booking-status" data-booking-id="${bookingId}" style="padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px;">
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
      var bookingData = {
        customer_name: $('#rbf-customer-name').val(),
        customer_email: $('#rbf-customer-email').val(),
        customer_phone: $('#rbf-customer-phone').val(),
        people: $('#rbf-booking-people').val(),
        notes: $('#rbf-booking-notes').val(),
        status: $('#rbf-booking-status').val()
      };
      saveBookingData(bookingId, bookingData);
    });
    
    $('#rbf-edit-full').on('click', function() {
      if (window.rbfAdminData.editUrl) {
        window.open(window.rbfAdminData.editUrl.replace('BOOKING_ID', bookingId), '_blank');
      }
    });
    
    // Click outside to close
    $(document).on('click', '#rbf-booking-modal', function(e) {
      if (e.target === this) {
        $(this).remove();
      }
    });
  }

  /**
   * Save booking data via AJAX
   */
  function saveBookingData(bookingId, bookingData) {
    if (!window.rbfAdminData.ajaxUrl) {
      return;
    }

    $.ajax({
      url: window.rbfAdminData.ajaxUrl,
      type: 'POST',
      data: {
        action: 'rbf_update_booking_data',
        booking_id: bookingId,
        booking_data: bookingData,
        _ajax_nonce: window.rbfAdminData.nonce
      },
      success: function(response) {
        if (response.success) {
          // Show success message
          showNotification('Prenotazione aggiornata con successo', 'success');
          // Refresh calendar
          if (calendarInstance) {
            calendarInstance.refetchEvents();
          }
          // Close modal
          $('#rbf-booking-modal').remove();
        } else {
          showNotification('Errore nell\'aggiornamento della prenotazione', 'error');
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