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

  function normalizeTabTarget(value) {
    if (!value && value !== 0) {
      return '';
    }

    return String(value)
      .trim()
      .replace(/^#/, '')
      .replace(/^rbf-tab-/, '');
  }

  function getLinkTarget(link) {
    if (!link) {
      return '';
    }

    const explicit = link.getAttribute('data-tab-target');
    if (explicit) {
      return normalizeTabTarget(explicit);
    }

    if (link.hash) {
      return normalizeTabTarget(link.hash);
    }

    const href = link.getAttribute('href');
    if (href && href.indexOf('#') !== -1) {
      return normalizeTabTarget(href.substring(href.indexOf('#')));
    }

    return '';
  }

  function getPanelTargets(panel) {
    if (!panel) {
      return [];
    }

    const potentialTargets = [
      panel.getAttribute('data-tab-panel'),
      panel.getAttribute('data-tab'),
      panel.id,
    ];

    const normalized = potentialTargets
      .map(normalizeTabTarget)
      .filter((target, index, arr) => target && arr.indexOf(target) === index);

    return normalized;
  }

  function setupAdminTabs() {
    const navs = document.querySelectorAll('.rbf-admin-tabs, .nav-tab-wrapper');

    navs.forEach((nav) => {
      const wrap = nav.closest('.rbf-admin-wrap') || nav.closest('.wrap') || document;
      const links = Array.from(nav.querySelectorAll('a'))
        .filter((link) => link.matches('.rbf-tab-link, [data-tab-target], [href^="#"]'));

      const panelCandidates = Array.from(
        wrap.querySelectorAll('[data-tab-panel], [data-tab], .rbf-tab-panel, .rbf-settings-tab-panel')
      );

      const panelInfos = panelCandidates
        .map((panel) => {
          const targets = getPanelTargets(panel);

          if (!targets.length) {
            return null;
          }

          if (!panel.classList.contains('rbf-tab-panel')) {
            panel.classList.add('rbf-tab-panel');
          }

          if (!panel.id) {
            panel.id = 'rbf-tab-' + targets[0];
          }

          return {
            element: panel,
            targets,
          };
        })
        .filter(Boolean);

      const linkInfos = links.map((link) => {
        const target = getLinkTarget(link);
        const hashTarget = link.getAttribute('href') || (target ? '#rbf-tab-' + target : '');

        const matchingPanel = panelInfos.find((info) => info.targets.indexOf(target) !== -1);
        const controlId = matchingPanel && matchingPanel.element.id ? matchingPanel.element.id : '';

        if (controlId) {
          link.setAttribute('aria-controls', controlId);
        }

        return {
          element: link,
          target,
          hashTarget,
        };
      });

      const validTargets = linkInfos
        .map((info) => info.target)
        .filter((value, index, arr) => value && arr.indexOf(value) === index);

      const panels = panelInfos.filter((info) =>
        info.targets.some((target) => validTargets.indexOf(target) !== -1)
      );

      if (!linkInfos.length || !panels.length) {
        return;
      }

      const group = {
        nav,
        links: linkInfos,
        panels,
        activate(target) {
          const desired = normalizeTabTarget(target);
          const availableTargets = group.links
            .map((info) => info.target)
            .filter(Boolean);
          const fallback = availableTargets[0] || '';
          const hasMatch = desired && availableTargets.indexOf(desired) !== -1;
          const resolved = hasMatch ? desired : fallback;

          if (!resolved) {
            return;
          }

          group.links.forEach((info) => {
            const isActive = info.target === resolved;
            info.element.classList.toggle('nav-tab-active', isActive);
            info.element.setAttribute('aria-selected', isActive ? 'true' : 'false');
          });

          group.panels.forEach((info) => {
            const matches = info.targets.indexOf(resolved) !== -1;
            const isActive = matches || (info.element.id && normalizeTabTarget(info.element.id) === resolved);

            info.element.classList.toggle('is-active', isActive);
            info.element.setAttribute('aria-hidden', isActive ? 'false' : 'true');

            if ('hidden' in info.element) {
              info.element.hidden = !isActive;
            }
          });
        }
      };

      tabGroups.push(group);

      group.links.forEach((info) => {
        info.element.addEventListener('click', (event) => {
          event.preventDefault();
          group.activate(info.target);

          if (
            info.hashTarget &&
            window.location.hash !== info.hashTarget &&
            typeof window.history.replaceState === 'function'
          ) {
            try {
              window.history.replaceState(null, '', info.hashTarget);
            } catch (e) {
              // Ignore history failures (e.g., older browsers)
            }
          }
        });

        info.element.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            info.element.click();
          }
        });
      });

      const initialTarget = window.location.hash || '';
      group.activate(initialTarget);
    });
  }

  setupAdminTabs();

  window.addEventListener('hashchange', () => {
    const target = window.location.hash || '';
    const normalizedTarget = normalizeTabTarget(target);

    if (!normalizedTarget) {
      return;
    }

    tabGroups.forEach((group) => {
      const hasTarget = group.links.some((info) => info.target === normalizedTarget);
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