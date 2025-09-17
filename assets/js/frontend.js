/**
 * Frontend JavaScript for Restaurant Booking Plugin
 * Enhanced with resource loading monitoring and jQuery dependency handling
 */

// Enhanced jQuery wrapper with fallback and resource monitoring
(function(window, document) {
  'use strict';

  // Resource loading monitoring
  function monitorResourceLoading() {
    if (window.rbfResourceErrors && window.rbfResourceErrors.length > 0) {
      console.warn('RBF: Detected resource loading errors:', window.rbfResourceErrors);
      
      // Check for critical missing resources
      const missingFlatpickr = window.rbfResourceErrors.some(err => 
        err.src && (err.src.includes('flatpickr') || err.src.includes('jsdelivr')));
      const missingIntlTel = window.rbfResourceErrors.some(err => 
        err.src && (err.src.includes('intl-tel-input') || err.src.includes('cdnjs')));
      
      if (missingFlatpickr) {
        console.warn('RBF: Flatpickr failed to load from CDN - fallback mode will be used');
      }
      if (missingIntlTel) {
        console.warn('RBF: International Telephone Input failed to load from CDN - using standard input');
      }
    }
  }

  // Enhanced jQuery availability check
  function initializeWithJQuery() {
    if (typeof window.jQuery === 'undefined') {
      console.error('RBF: jQuery is not available - booking form cannot function');
      
      // Show user-friendly error message
      const forms = document.querySelectorAll('#rbf-form');
      forms.forEach(form => {
        const errorDiv = document.createElement('div');
        errorDiv.style.cssText = 'background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 8px; margin: 20px 0;';
        errorDiv.innerHTML = '<strong>Errore:</strong> Sistema di prenotazione temporaneamente non disponibile. Riprova più tardi o contattaci direttamente.';
        form.insertBefore(errorDiv, form.firstChild);
      });
      return;
    }

    // Monitor resource loading
    monitorResourceLoading();

    // Initialize with jQuery
    window.jQuery(function($) {
      initializeBookingForm($);
    });
  }

  // Wait for DOM and then check jQuery availability
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeWithJQuery);
  } else {
    initializeWithJQuery();
  }

})(window, document);

// Main initialization function
function initializeBookingForm($) {
  
  // Safe console logging wrapper for production
  const rbfLog = {
    error: function(message) {
      if (window.console && window.console.error) {
        console.error('RBF: ' + message);
      }
    },
    warn: function(message) {
      if (window.console && window.console.warn) {
        console.warn('RBF: ' + message);
      }
    },
    log: function(message) {
      // Only log in debug mode or when explicitly enabled
      if (window.console && window.console.log && (window.rbfDebug || (rbfData && rbfData.debug))) {
        console.log('RBF: ' + message);
      }
    }
  };
  
  // Check if essential data is loaded
  if (typeof rbfData === 'undefined') {
    rbfLog.error('Essential data not loaded - booking form cannot function');
    return;
  }

  // Set debug mode based on configuration
  const isDebugMode = (rbfData && rbfData.debug) || window.rbfDebug || false;

  const form = $('#rbf-form');
  if (!form.length) return;

  const DEFAULT_MAX_ADVANCE_MINUTES = 259200;
  const parsedMaxAdvanceMinutes = Number(rbfData.maxAdvanceMinutes);
  const hasFiniteMaxAdvanceMinutes = Number.isFinite(parsedMaxAdvanceMinutes);
  const hasPositiveMaxAdvanceMinutes = hasFiniteMaxAdvanceMinutes && parsedMaxAdvanceMinutes > 0;
  const maxAdvanceMinutes = hasPositiveMaxAdvanceMinutes ? parsedMaxAdvanceMinutes : DEFAULT_MAX_ADVANCE_MINUTES;
  const shouldApplyMaxAdvanceLimit = hasPositiveMaxAdvanceMinutes;

  if (!hasPositiveMaxAdvanceMinutes) {
    if (hasFiniteMaxAdvanceMinutes && parsedMaxAdvanceMinutes === 0) {
      rbfLog.log('maxAdvanceMinutes set to 0, no maximum advance limit applied');
    } else if (typeof rbfData.maxAdvanceMinutes === 'undefined' || rbfData.maxAdvanceMinutes === null || rbfData.maxAdvanceMinutes === '') {
      rbfLog.warn('maxAdvanceMinutes not provided, using default of ' + DEFAULT_MAX_ADVANCE_MINUTES + ' minutes');
    } else {
      rbfLog.warn('Invalid maxAdvanceMinutes value (' + rbfData.maxAdvanceMinutes + '), using default of ' + DEFAULT_MAX_ADVANCE_MINUTES + ' minutes');
    }
  }

  const DEFAULT_MAX_PEOPLE = 20;
  const parsedPeopleMax = parseInt(rbfData.peopleMax, 10);
  const hasProvidedPeopleMax = typeof rbfData.peopleMax !== 'undefined' && rbfData.peopleMax !== null && rbfData.peopleMax !== '';
  const hasValidPeopleMax = Number.isFinite(parsedPeopleMax) && parsedPeopleMax > 0;
  const peopleMaxLimit = hasValidPeopleMax ? parsedPeopleMax : DEFAULT_MAX_PEOPLE;

  if (!hasValidPeopleMax && hasProvidedPeopleMax) {
    rbfLog.warn('Invalid peopleMax value (' + rbfData.peopleMax + '), using default of ' + DEFAULT_MAX_PEOPLE);
  }

  // Cache DOM elements
  const el = {
    mealRadios: form.find('input[name="rbf_meal"]'),
    mealNotice: form.find('#rbf-meal-notice'),
    progressSteps: form.find('.rbf-progress-step'),
    dateStep: form.find('#step-date'),
    dateInput: form.find('#rbf-date'),
    timeStep: form.find('#step-time'),
    timeSelect: form.find('#rbf-time'),
    peopleStep: form.find('#step-people'),
    peopleInput: form.find('#rbf-people'),
    peopleMinus: form.find('#rbf-people-minus'),
    peoplePlus: form.find('#rbf-people-plus'),
    detailsStep: form.find('#step-details'),
    detailsInputs: form.find('#step-details input:not([type=checkbox]), #step-details textarea'),
    telInput: form.find('#rbf-tel'),
    privacyCheckbox: form.find('#rbf-privacy'),
    marketingCheckbox: form.find('#rbf-marketing'),
    submitButton: form.find('#rbf-submit')
  };

  const legendElement = form.find('.rbf-exception-legend');

  let fp = null;
  let datePickerInitPromise = null;
  let iti = null;
  let currentStep = 1;
  let stepTimeouts = new Map(); // Track timeouts for each step element
  let componentsLoading = new Set(); // Track which components are loading
  let availabilityData = {}; // Store availability data for calendar coloring
  let availabilityRequest = null; // Track ongoing availability AJAX request

  function hasAvailabilityLegendEntries() {
    if (!availabilityData || typeof availabilityData !== 'object') {
      return false;
    }

    try {
      return Object.keys(availabilityData).some(function(key) {
        const entry = availabilityData[key];
        if (!entry || typeof entry !== 'object') {
          return false;
        }

        return Object.keys(entry).length > 0;
      });
    } catch (error) {
      rbfLog.warn('Availability legend inspection failed: ' + error.message);
      return false;
    }
  }

  function updateLegendVisibility() {
    if (!legendElement.length) {
      return;
    }

    try {
      const exceptions = (rbfData && Array.isArray(rbfData.exceptions)) ? rbfData.exceptions : [];
      const hasExceptions = exceptions.length > 0;
      const hasAvailability = hasAvailabilityLegendEntries();
      const shouldShowLegend = hasExceptions || hasAvailability;

      legendElement.toggle(shouldShowLegend);
      legendElement.attr('aria-hidden', shouldShowLegend ? 'false' : 'true');
    } catch (error) {
      rbfLog.warn('Legend visibility update failed: ' + error.message);
    }
  }

  updateLegendVisibility();

  // Autosave functionality variables
  let autosaveTimeout = null;
  const AUTOSAVE_DELAY = 1000; // 1 second debounce
  const AUTOSAVE_KEY = 'rbf_booking_form_data';

  /**
   * localStorage utilities for autosave functionality
   */
  const AutoSave = {
    /**
     * Check if localStorage is supported and available
     */
    isSupported: function() {
      try {
        const test = '__rbf_test__';
        localStorage.setItem(test, test);
        localStorage.removeItem(test);
        return true;
      } catch (e) {
        return false;
      }
    },

    /**
     * Save form data to localStorage with timestamp
     */
    save: function(data) {
      if (!this.isSupported()) return false;
      
      try {
        const saveData = {
          data: data,
          timestamp: Date.now(),
          url: window.location.href
        };
        localStorage.setItem(AUTOSAVE_KEY, JSON.stringify(saveData));
        return true;
      } catch (e) {
        rbfLog.warn('Autosave failed: ' + e.message);
        return false;
      }
    },

    /**
     * Load form data from localStorage
     */
    load: function() {
      if (!this.isSupported()) return null;
      
      try {
        const saved = localStorage.getItem(AUTOSAVE_KEY);
        if (!saved) return null;
        
        const saveData = JSON.parse(saved);
        
        // Check if data is from the same page and not too old (24 hours)
        if (saveData.url !== window.location.href || 
            Date.now() - saveData.timestamp > 24 * 60 * 60 * 1000) {
          this.clear();
          return null;
        }
        
        return saveData.data;
      } catch (e) {
        rbfLog.warn('Autosave load failed: ' + e.message);
        this.clear();
        return null;
      }
    },

    /**
     * Clear saved form data
     */
    clear: function() {
      if (!this.isSupported()) return;
      
      try {
        localStorage.removeItem(AUTOSAVE_KEY);
      } catch (e) {
        rbfLog.warn('Autosave clear failed: ' + e.message);
      }
    }
  };

  /**
   * Collect current form data for autosave
   */
  function collectFormData() {
    const data = {};
    
    // Meal selection
    const selectedMeal = el.mealRadios.filter(':checked').val();
    if (selectedMeal) data.meal = selectedMeal;
    
    // Date
    const dateValue = el.dateInput.val();
    if (dateValue) data.date = dateValue;
    
    // Time
    const timeValue = el.timeSelect.val();
    if (timeValue) data.time = timeValue;
    
    // People count
    const peopleValue = el.peopleInput.val();
    if (peopleValue) data.people = peopleValue;
    
    // Personal details
    const nameValue = form.find('#rbf-name').val();
    if (nameValue) data.name = nameValue;
    
    const surnameValue = form.find('#rbf-surname').val();
    if (surnameValue) data.surname = surnameValue;
    
    const emailValue = form.find('#rbf-email').val();
    if (emailValue) data.email = emailValue;
    
    const telValue = form.find('#rbf-tel').val();
    if (telValue) data.tel = telValue;
    
    const notesValue = form.find('#rbf-notes').val();
    if (notesValue) data.notes = notesValue;
    
    // Checkboxes
    data.privacy = el.privacyCheckbox.is(':checked');
    data.marketing = el.marketingCheckbox.is(':checked');
    
    return data;
  }

  /**
   * Restore form data from autosave
   */
  function restoreFormData(data) {
    if (!data || typeof data !== 'object') return;
    
    // Restore meal selection
    if (data.meal) {
      el.mealRadios.filter(`[value="${data.meal}"]`).prop('checked', true).trigger('change');
    }
    
    // Restore date
    if (data.date) {
      el.dateInput.val(data.date);
    }
    
    // Restore time
    if (data.time) {
      el.timeSelect.val(data.time);
    }
    
    // Restore people count
    if (data.people) {
      el.peopleInput.val(data.people).trigger('input');
    }
    
    // Restore personal details
    if (data.name) form.find('#rbf-name').val(data.name);
    if (data.surname) form.find('#rbf-surname').val(data.surname);
    if (data.email) form.find('#rbf-email').val(data.email);
    if (data.tel) form.find('#rbf-tel').val(data.tel);
    if (data.notes) form.find('#rbf-notes').val(data.notes);
    
    // Restore checkboxes
    if (data.privacy) el.privacyCheckbox.prop('checked', true);
    if (data.marketing) el.marketingCheckbox.prop('checked', true);
    
    rbfLog.log('Form data restored from autosave');
  }

  /**
   * Debounced autosave function
   */
  function scheduleAutosave() {
    if (autosaveTimeout) {
      clearTimeout(autosaveTimeout);
    }
    
    autosaveTimeout = setTimeout(function() {
      const formData = collectFormData();
      if (Object.keys(formData).length > 0) {
        if (AutoSave.save(formData)) {
          rbfLog.log('Form data auto-saved');
        }
      }
    }, AUTOSAVE_DELAY);
  }

  /**
   * Initialize autosave event listeners
   */
  function initializeAutosave() {
    if (!AutoSave.isSupported()) {
      rbfLog.warn('localStorage not supported - autosave disabled');
      return;
    }
    
    // Add change listeners to all form fields
    el.mealRadios.on('change', scheduleAutosave);
    el.dateInput.on('change', scheduleAutosave);
    el.timeSelect.on('change', scheduleAutosave);
    el.peopleInput.on('input', scheduleAutosave);
    
    // Personal details fields
    form.find('#rbf-name, #rbf-surname, #rbf-email, #rbf-tel').on('input', scheduleAutosave);
    form.find('#rbf-notes').on('input', scheduleAutosave);
    
    // Checkboxes
    el.privacyCheckbox.on('change', scheduleAutosave);
    el.marketingCheckbox.on('change', scheduleAutosave);
    
    rbfLog.log('Autosave initialized');
  }

  /**
   * Show loading state for component
   */
  function showComponentLoading(component, message = (rbfData && rbfData.labels && rbfData.labels.loading) || 'Caricamento...') {
    componentsLoading.add(component);
    const $component = $(component);
    
    // CRITICAL FIX: Never apply loading state to calendar elements
    if ($component.hasClass('flatpickr-calendar') || 
        $component.find('.flatpickr-calendar').length > 0 ||
        $component.closest('.flatpickr-calendar').length > 0) {
      rbfLog.log('Skipping loading state for calendar element to preserve interactivity');
      return;
    }
    
    if (!$component.hasClass('rbf-component-loading')) {
      $component.addClass('rbf-component-loading');
      
      const overlay = $('<div class="rbf-loading-overlay"><div class="rbf-loading-spinner"></div><span>' + message + '</span></div>');
      $component.append(overlay);
    }
  }

  /**
   * Hide loading state for component
   */
  function hideComponentLoading(component) {
    componentsLoading.delete(component);
    const $component = $(component);
    
    $component.removeClass('rbf-component-loading');
    $component.find('.rbf-loading-overlay').remove();
  }

  /**
   * Remove skeleton and show actual content with fade-in
   */
  function removeSkeleton($step) {
    const $skeletonElements = $step.find('[aria-hidden="true"]');
    const $contentElements = $step.find('.rbf-fade-in');
    
    $step.attr('data-skeleton', 'false');
    
    // Remove any loading classes that might prevent interaction
    $step.removeClass('rbf-component-loading rbf-loading');
    $contentElements.removeClass('rbf-component-loading rbf-loading');
    
    // CRITICAL: Remove loading classes from parent elements too
    $step.parents().removeClass('rbf-component-loading rbf-loading');
    
    // Hide skeleton with fade out
    $skeletonElements.fadeOut(200, function() {
      // Show content with fade in
      $contentElements.addClass('loaded');
      
      // Ensure calendar is fully interactive for date step
      if ($step.attr('id') === 'step-date') {
        setTimeout(() => {
          // Remove any remaining pointer-events restrictions
          $step.css('pointer-events', 'auto');
          $contentElements.css('pointer-events', 'auto');
          
          // Ensure date input is enabled and interactive
          el.dateInput.prop('disabled', false);
          el.dateInput.css('pointer-events', 'auto');
          el.dateInput.parent().css('pointer-events', 'auto');

          // CRITICAL FIX: Force calendar interactivity aggressively
          if (fp) {
            forceCalendarInteractivity(fp);
            
            // Start periodic checker to prevent regression
            startInteractivityChecker(fp);
            
            // Double-check after a brief delay
            setTimeout(() => {
              forceCalendarInteractivity(fp);
            }, 200);
          }
          
          // CRITICAL: Remove any loading overlays from the entire step
          $step.find('.rbf-loading-overlay').remove();
          
          rbfLog.log('📅 Calendar skeleton removed and interactivity forcefully enabled');
        }, 100);
      }
    });
  }

  /**
   * Force Flatpickr calendar interactivity by removing blocking styles
   */
  function forceCalendarInteractivity(calendarInstance) {
    if (!calendarInstance || !calendarInstance.calendarContainer) return;

    const calendar = calendarInstance.calendarContainer;

    // Remove loading classes from calendar and all parent elements
    calendar.classList.remove('rbf-component-loading', 'rbf-loading');
    let parent = calendar.parentElement;
    while (parent && parent !== document.body) {
      parent.classList.remove('rbf-component-loading', 'rbf-loading');
      parent = parent.parentElement;
    }

    // Force calendar container styles
    calendar.style.pointerEvents = 'auto';
    calendar.style.opacity = '1';
    calendar.style.removeProperty('filter');
    calendar.style.position = 'relative';
    calendar.style.zIndex = '1100';

    // Force wrapper styles
    const wrapper = calendar.closest('.flatpickr-wrapper');
    if (wrapper) {
      wrapper.style.pointerEvents = 'auto';
      wrapper.style.opacity = '1';
    }

    const interactiveSelectors = [
      '.flatpickr-day',
      '.flatpickr-prev-month',
      '.flatpickr-next-month',
      '.flatpickr-current-month',
      '.flatpickr-monthDropdown-months',
      '.numInputWrapper'
    ].join(', ');

    calendar.querySelectorAll(interactiveSelectors).forEach((element) => {
      if (!element.classList.contains('flatpickr-disabled')) {
        element.style.pointerEvents = 'auto';
        element.style.cursor = 'pointer';
        element.style.position = 'relative';
        element.style.zIndex = '1';
      }
    });

    // Remove any loading overlays from calendar and parent elements
    calendar.querySelectorAll('.rbf-loading-overlay').forEach((overlay) => overlay.remove());
    
    // Also check parent elements for overlays
    let currentElement = calendar;
    while (currentElement && currentElement !== document.body) {
      currentElement.querySelectorAll('.rbf-loading-overlay').forEach((overlay) => overlay.remove());
      currentElement = currentElement.parentElement;
    }

    rbfLog.log('🎯 Calendar interactivity forced successfully');
  }

  /**
   * Start periodic interactivity checker to prevent regression
   */
  function startInteractivityChecker(calendarInstance) {
    if (!calendarInstance) return;
    
    // Clear any existing checker
    if (window.rbfInteractivityChecker) {
      clearInterval(window.rbfInteractivityChecker);
    }
    
    // Start periodic check every 2 seconds
    var interactivityChecker = setInterval(function() {
      if (calendarInstance && calendarInstance.calendarContainer) {
        forceCalendarInteractivity(calendarInstance);
      }
    }, 2000);
    
    // Store reference for cleanup
    window.rbfInteractivityChecker = interactivityChecker;
    
    rbfLog.log('🔄 Calendar interactivity checker started');
  }

  /**
   * Initialize calendar with enhanced fallback support and better error handling
   */
  function lazyLoadDatePicker() {
    if (fp) return Promise.resolve();
    if (datePickerInitPromise) return datePickerInitPromise;

    datePickerInitPromise = new Promise((resolve) => {
      const init = () => {
        try {
          // Wait a bit to ensure DOM is ready and libraries are loaded
          setTimeout(() => {
            if (typeof flatpickr !== 'undefined') {
              rbfLog.log('Flatpickr available, initializing calendar...');
              initializeFlatpickr();
            } else {
              rbfLog.warn('Flatpickr still not available after wait, using fallback');
              initFallback();
            }
            resolve();
            datePickerInitPromise = null;
          }, 150); // Increased delay for better stability
        } catch (error) {
          rbfLog.error('Calendar initialization error: ' + error.message);
          initFallback();
          resolve();
          datePickerInitPromise = null;
        }
      };

      const initFallback = () => {
        rbfLog.warn('Using HTML5 date input fallback');
        setupFallbackDateInput();
      };

      // Check if Flatpickr is already available
      if (typeof flatpickr !== 'undefined') {
        rbfLog.log('Flatpickr already available');
        init();
      } else {
        // Try to wait for Flatpickr to load, then fallback if needed
        let checkAttempts = 0;
        const maxAttempts = 10;
        const checkInterval = 200;
        
        const checkFlatpickr = () => {
          checkAttempts++;
          
          if (typeof flatpickr !== 'undefined') {
            rbfLog.log(`Flatpickr found after ${checkAttempts} attempts`);
            init();
          } else if (checkAttempts >= maxAttempts) {
            rbfLog.warn(`Flatpickr not found after ${maxAttempts} attempts, using fallback`);
            initFallback();
            resolve();
            datePickerInitPromise = null;
          } else {
            setTimeout(checkFlatpickr, checkInterval);
          }
        };
        
        // Start checking
        checkFlatpickr();
      }
    });

    return datePickerInitPromise;
  }

  /**
   * Enhanced HTML5 date input fallback
   */
  function setupFallbackDateInput() {
    if (!el.dateInput.length) {
      rbfLog.error('Date input element not found for fallback');
      return;
    }

    rbfLog.log('Setting up enhanced HTML5 date input fallback...');
    
    try {
      // Ensure the input has the correct id for accessibility
      if (!el.dateInput.attr('id')) {
        const originalId = el.dateInput.attr('data-original-id') || 'rbf-date';
        el.dateInput.attr('id', originalId);
        rbfLog.log('📋 Restored id attribute for accessibility: ' + originalId);
      }
      
      // Convert to HTML5 date input
      el.dateInput.attr('type', 'date');
      el.dateInput.removeAttr('readonly');
      
      // Set minimum date based on advance time requirements
      const today = new Date();
      const parsedMinAdvance = Number(rbfData.minAdvanceMinutes);
      const hasMinAdvance = Number.isFinite(parsedMinAdvance) && parsedMinAdvance > 0;
      const minDate = hasMinAdvance
        ? new Date(today.getTime() + parsedMinAdvance * 60 * 1000)
        : today;
      el.dateInput.attr('min', minDate.toISOString().split('T')[0]);

      // Set maximum date to mirror Flatpickr behaviour
      const maxDate = shouldApplyMaxAdvanceLimit
        ? new Date(today.getTime() + maxAdvanceMinutes * 60 * 1000)
        : new Date(today.getTime() + 365 * 24 * 60 * 60 * 1000);
      el.dateInput.attr('max', maxDate.toISOString().split('T')[0]);
      
      // Remove any existing change handlers to avoid duplicates
      el.dateInput.off('change.fallback');
      
      // Add event listener for date changes
      el.dateInput.on('change.fallback', function() {
        const selectedDate = this.value;
        if (selectedDate) {
          rbfLog.log('Date selected via HTML5 input: ' + selectedDate);
          // Convert to date object and trigger the same flow as Flatpickr
          const dateObj = new Date(selectedDate + 'T00:00:00');
          onDateChange([dateObj]);
        }
      });
      
      // Improve styling for fallback date input
      el.dateInput.addClass('rbf-date-fallback');
      
      // Show a helpful message
      if (el.dateInput.attr('placeholder')) {
        el.dateInput.attr('placeholder', (rbfData && rbfData.labels && rbfData.labels.chooseDate) || 'Seleziona una data');
      }
      
      rbfLog.log('✅ HTML5 date input fallback configured successfully');
      
    } catch (error) {
      rbfLog.error('Failed to setup fallback date input: ' + error.message);
    }
  }

  /**
   * Fetch availability data for calendar month
   */
  function fetchAvailabilityData(startDate, endDate, meal) {
    return new Promise((resolve) => {
      // Safety check for rbfData
      if (!rbfData || !rbfData.ajaxUrl || !rbfData.nonce) {
        rbfLog.error('Missing required rbfData properties for AJAX call');
        resolve({});
        return;
      }

      if (availabilityRequest && typeof availabilityRequest.abort === 'function') {
        try {
          availabilityRequest.abort();
          rbfLog.log('⏹️ Previous availability request aborted');
        } catch (error) {
          rbfLog.warn('Failed to abort previous availability request: ' + error.message);
        }
      }

      availabilityRequest = null;

      const request = $.ajax({
        url: rbfData.ajaxUrl,
        method: 'POST',
        data: {
          action: 'rbf_get_calendar_availability',
          _ajax_nonce: rbfData.nonce,
          start_date: startDate,
          end_date: endDate,
          meal: meal
        }
      });

      availabilityRequest = request;

      request.done(function(response) {
        if (response && response.success) {
          availabilityData = response.data;
        } else if (response && response.success === false) {
          availabilityData = {};
        }

        updateLegendVisibility();
      }).fail(function(jqXHR, textStatus) {
        if (textStatus !== 'abort') {
          availabilityData = {};
          updateLegendVisibility();
        }
      }).always(function() {
        if (availabilityRequest === request) {
          availabilityRequest = null;
        }
        resolve();
      });
    });
  }

  /**
   * Add availability tooltip to calendar day
   */
  function addAvailabilityTooltip(dayElem, status) {
    if (!dayElem || !status || dayElem.classList.contains('flatpickr-disabled')) {
      return;
    }

    const state = dayElem._rbfTooltipState || {};
    state.status = status;
    dayElem._rbfTooltipState = state;

    if (state.hideTooltip) {
      state.hideTooltip();
    }

    if (!state.tooltipId) {
      state.tooltipId = 'rbf-tooltip-' + Math.random().toString(36).substr(2, 9);
    }

    dayElem.setAttribute('aria-describedby', state.tooltipId);
    dayElem.setAttribute('role', 'button');
    dayElem.setAttribute('tabindex', '0');

    if (state.initialized) {
      return;
    }

    const showTooltip = function() {
      const availability = state.status;
      if (!availability) {
        return;
      }

      if (state.tooltip) {
        state.tooltip.remove();
        state.tooltip = null;
      }

      const tooltip = document.createElement('div');
      tooltip.className = 'rbf-availability-tooltip';
      tooltip.id = state.tooltipId;
      tooltip.setAttribute('role', 'tooltip');

      const labels = (rbfData && rbfData.labels) || {};

      let statusText;
      let contextualMessage = '';

      if (availability.level === 'available') {
        statusText = labels.available || 'Disponibile';
        if (availability.remaining > 20) {
          contextualMessage = labels.manySpots || 'Molti posti disponibili';
        } else if (availability.remaining > 10) {
          contextualMessage = labels.someSpots || 'Buona disponibilità';
        }
      } else if (availability.level === 'limited') {
        statusText = labels.limited || 'Quasi al completo';
        if (availability.remaining <= 2) {
          contextualMessage = labels.lastSpots || 'Ultimi 2 posti rimasti';
        } else if (availability.remaining <= 5) {
          contextualMessage = labels.fewSpots || 'Pochi posti rimasti';
        }
      } else if (availability.level === 'nearlyFull') {
        statusText = labels.nearlyFull || labels.limited || 'Quasi al completo';
        contextualMessage = labels.actFast || 'Prenota subito!';
      } else {
        statusText = labels.full || 'Completo';
      }

      const spotsLabel = labels.spotsRemaining || 'Posti rimasti:';
      const occupancyLabel = labels.occupancy || 'Occupazione:';

      tooltip.innerHTML = `
        <div class="rbf-tooltip-status">${statusText}</div>
        ${contextualMessage ? `<div class="rbf-tooltip-context">${contextualMessage}</div>` : ''}
        <div class="rbf-tooltip-spots">${spotsLabel} ${availability.remaining}/${availability.total}</div>
        <div class="rbf-tooltip-occupancy">${occupancyLabel} ${availability.occupancy}%</div>
      `;

      document.body.appendChild(tooltip);

      const rect = dayElem.getBoundingClientRect();
      const tooltipRect = tooltip.getBoundingClientRect();
      const viewportWidth = window.innerWidth;
      const viewportHeight = window.innerHeight;

      let left = rect.left + rect.width / 2 - tooltipRect.width / 2;
      let top = rect.top - tooltipRect.height - 10;

      if (left < 10) {
        left = 10;
      } else if (left + tooltipRect.width > viewportWidth - 10) {
        left = viewportWidth - tooltipRect.width - 10;
      }

      if (top < 10) {
        top = rect.bottom + 10;
        tooltip.classList.add('rbf-tooltip-below');
      }

      tooltip.style.left = left + 'px';
      tooltip.style.top = top + 'px';

      state.tooltip = tooltip;
    };

    const hideTooltip = function() {
      if (state.tooltip) {
        state.tooltip.remove();
        state.tooltip = null;
      }
    };

    const keydownHandler = function(e) {
      if (e.key === 'Escape') {
        hideTooltip();
        dayElem.blur();
      }
    };

    dayElem.addEventListener('mouseenter', showTooltip);
    dayElem.addEventListener('mouseleave', hideTooltip);
    dayElem.addEventListener('focus', showTooltip);
    dayElem.addEventListener('blur', hideTooltip);
    dayElem.addEventListener('keydown', keydownHandler);

    state.showTooltip = showTooltip;
    state.hideTooltip = hideTooltip;
    state.keydownHandler = keydownHandler;
    state.initialized = true;
  }

  /**
   * COMPLETELY RENEWED CALENDAR IMPLEMENTATION
   * Enhanced with better error handling and reliability
   */
  function transferAriaAttributesToAltInput(instance) {
    if (!instance || !instance.input || !instance.altInput) {
      return;
    }

    try {
      const originalInput = instance.input;
      const altInput = instance.altInput;
      const attributesToTransfer = [];

      Array.from(originalInput.attributes).forEach(attr => {
        if (!attr || !attr.name) {
          return;
        }

        const name = attr.name;
        const lowerName = name.toLowerCase();

        if (lowerName === 'role' || lowerName.startsWith('aria-')) {
          attributesToTransfer.push({ name: name, value: attr.value });
        }
      });

      if (attributesToTransfer.length > 0) {
        originalInput.setAttribute('data-rbf-transferred-aria', JSON.stringify(attributesToTransfer));

        attributesToTransfer.forEach(attribute => {
          altInput.setAttribute(attribute.name, attribute.value);
          originalInput.removeAttribute(attribute.name);
        });

        rbfLog.log('📋 Copied ARIA attributes to alternate date input');
      }

      if (!altInput.hasAttribute('aria-expanded')) {
        altInput.setAttribute('aria-expanded', 'false');
      }
    } catch (error) {
      rbfLog.warn('Failed to transfer ARIA attributes to Flatpickr alternate input: ' + error.message);
    }
  }

  function restoreFlatpickrAriaAttributes(instance) {
    if (!instance || !instance.input) {
      return;
    }

    const originalInput = instance.input;
    const storedAttributes = originalInput.getAttribute('data-rbf-transferred-aria');

    if (!storedAttributes) {
      return;
    }

    try {
      const parsedAttributes = JSON.parse(storedAttributes);

      if (Array.isArray(parsedAttributes)) {
        parsedAttributes.forEach(attribute => {
          if (attribute && attribute.name) {
            originalInput.setAttribute(attribute.name, attribute.value);
          }
        });

        rbfLog.log('📋 Restored ARIA attributes to original date input');
      }
    } catch (error) {
      rbfLog.warn('Failed to restore ARIA attributes to original date input: ' + error.message);
    } finally {
      originalInput.removeAttribute('data-rbf-transferred-aria');
    }
  }

  function updateAltInputAriaExpanded(instance, isExpanded) {
    if (!instance || !instance.altInput) {
      return;
    }

    try {
      instance.altInput.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
    } catch (error) {
      rbfLog.warn('Failed to update aria-expanded on alternate date input: ' + error.message);
    }
  }

  function restoreOriginalDateInputAttributes(instance, contextLabel) {
    if (!instance || !instance.input) {
      return;
    }

    const context = contextLabel ? ` ${contextLabel}` : '';

    try {
      const originalInput = instance.input;
      const originalId = originalInput.getAttribute('data-original-id');

      if (originalId) {
        originalInput.id = originalId;

        if (instance.altInput) {
          instance.altInput.removeAttribute('id');
        }

        rbfLog.log(`📋 Restored id to original input${context}`);
      }

      restoreFlatpickrAriaAttributes(instance);
    } catch (error) {
      if (contextLabel) {
        rbfLog.warn(`Error restoring original input ${contextLabel}: ${error.message}`);
      } else {
        rbfLog.warn(`Error restoring original input: ${error.message}`);
      }
    }
  }

  function initializeFlatpickr() {
    // First, validate that we have the minimum required data
    if (!rbfData) {
      rbfLog.error('rbfData not available - cannot initialize calendar');
      return;
    }

    if (!el.dateInput || !el.dateInput.length) {
      rbfLog.error('Date input element not found - cannot initialize calendar');
      return;
    }

    rbfLog.log('Initializing enhanced calendar system...');
    
    // EMERGENCY: Check if we should use ultra-simple mode
    const useEmergencyMode = window.rbfForceEmergencyMode || false;
    
    if (useEmergencyMode) {
      rbfLog.log('Using emergency mode - minimal restrictions');
      initializeEmergencyCalendar();
      return;
    }

    try {
      const refreshAvailabilityForVisibleMonth = () => {
        const selectedMeal = el.mealRadios.filter(':checked').val();

        if (selectedMeal) {
          updateAvailabilityDataForMeal(selectedMeal);
        }
      };

      const flatpickrConfig = {
        // Basic configuration - keep it simple and reliable
        altInput: true,
        altFormat: 'd-m-Y',
        dateFormat: 'Y-m-d',
        minDate: 'today',
        locale: (rbfData.locale === 'it') ? 'it' : 'default',
        
        // Standard flatpickr settings for best user experience
        enableTime: false,
        noCalendar: false,
        enableMonthDropdown: true,
        enableYearDropdown: true,
        shorthandCurrentMonth: false,
        showMonths: 1,
        
        // Position calendar relative to input field instead of bottom of page
        static: true,
        
        // ENHANCED DISABLE FUNCTION - Ultra-safe with comprehensive logging
        disable: [function(date) {
          try {
            // EMERGENCY: If rbfData is not available, allow all dates
            if (!rbfData || typeof rbfData !== 'object') {
              rbfLog.warn('rbfData not available, allowing all dates');
              return false;
            }

            const result = isDateDisabled(date);
            
            // DEBUG: Log decisions for troubleshooting (only first few)
            if (rbfData.debug && (window.rbfDebugCount || 0) < 3) {
              rbfLog.log(`Date ${formatLocalISO(date)} -> ${result ? 'DISABLED' : 'ENABLED'}`);
              window.rbfDebugCount = (window.rbfDebugCount || 0) + 1;
            }
            
            return result;
            
          } catch (error) {
            rbfLog.error('Calendar disable function error: ' + error.message);
            // EMERGENCY: On any error, ALWAYS allow the date to prevent all dates being disabled
            return false;
          }
        }],

        onMonthChange: function() {
          refreshAvailabilityForVisibleMonth();
        },

        onYearChange: function() {
          refreshAvailabilityForVisibleMonth();
        },

        onChange: onDateChange,

        onReady: function(selectedDates, dateStr, instance) {
          rbfLog.log('✅ Enhanced calendar initialized successfully');
          
          // Fix accessibility issue: ensure the alternate input has the correct id
          if (instance.altInput && instance.input) {
            transferAriaAttributesToAltInput(instance);
            updateAltInputAriaExpanded(instance, false);

            // Transfer the id from the original input to the alternate input
            const originalId = instance.input.id;
            if (originalId) {
              instance.altInput.id = originalId;
              // Remove id from original input to avoid duplicates
              instance.input.removeAttribute('id');
              // Set a data attribute on the original for reference
              instance.input.setAttribute('data-original-id', originalId);
              rbfLog.log('📋 Fixed accessibility: transferred id to alternate input');
            }
          } else {
            rbfLog.warn('altInput or input not available during onReady - accessibility fix not applied');
          }
          
          // Debug rbfData structure for troubleshooting
          if (rbfData.debug || (typeof WP_DEBUG !== 'undefined' && WP_DEBUG)) {
            rbfLog.log('📊 rbfData structure:', {
              hasClosedDays: !!(rbfData.closedDays),
              closedDaysCount: rbfData.closedDays ? rbfData.closedDays.length : 0,
              hasClosedSingles: !!(rbfData.closedSingles),
              closedSinglesCount: rbfData.closedSingles ? rbfData.closedSingles.length : 0,
              hasClosedRanges: !!(rbfData.closedRanges),
              closedRangesCount: rbfData.closedRanges ? rbfData.closedRanges.length : 0,
              hasExceptions: !!(rbfData.exceptions),
              exceptionsCount: rbfData.exceptions ? rbfData.exceptions.length : 0,
              hasMealAvailability: !!(rbfData.mealAvailability),
              availableMeals: rbfData.mealAvailability ? Object.keys(rbfData.mealAvailability) : []
            });
          }
          
          // Ensure calendar is fully interactive
          setTimeout(() => {
            forceCalendarInteractivity(instance);
            rbfLog.log('🎯 Calendar forced to be interactive');
          }, 100);
          
          // Store global reference for debugging
          window.rbfCalendarInstance = instance;
        },
        
        onOpen: function(selectedDates, dateStr, instance) {
          rbfLog.log('📅 Calendar opened - forcing interactivity');

          updateAltInputAriaExpanded(instance, true);

          // CRITICAL: Force interactivity immediately when calendar opens
          setTimeout(() => {
            forceCalendarInteractivity(instance);
          }, 50);
          
          // Double-check after a brief delay to handle any race conditions
          setTimeout(() => {
            forceCalendarInteractivity(instance);
          }, 200);
        },

        onClose: function(selectedDates, dateStr, instance) {
          rbfLog.log('📅 Calendar closed');

          updateAltInputAriaExpanded(instance, false);
        },
        
        onDayCreate: function(dObj, dStr, fp, dayElem) {
          // CRITICAL: Ensure day is interactive if not disabled
          if (!dayElem.classList.contains('flatpickr-disabled')) {
            dayElem.style.pointerEvents = 'auto';
            dayElem.style.cursor = 'pointer';
            dayElem.style.position = 'relative';
            dayElem.style.zIndex = '1';
            dayElem.setAttribute('tabindex', '0');
            
            // Remove any problematic classes
            dayElem.classList.remove('rbf-component-loading', 'rbf-loading');
          }
          
          // Add availability colors based on data
          try {
            const dateStr = formatLocalISO(dayElem.dateObj);
            if (availabilityData && availabilityData[dateStr]) {
              const availability = availabilityData[dateStr];
              
              // Remove any existing availability classes
              dayElem.classList.remove('rbf-availability-available', 'rbf-availability-limited', 'rbf-availability-full');
              
              // Apply availability class based on level
              if (availability.level === 'available') {
                dayElem.classList.add('rbf-availability-available');
              } else if (availability.level === 'limited') {
                dayElem.classList.add('rbf-availability-limited');
              } else if (availability.level === 'nearlyFull' || availability.level === 'full') {
                dayElem.classList.add('rbf-availability-full');
              }
              
              // Add hover tooltip for availability info
              if (!dayElem.classList.contains('flatpickr-disabled')) {
                addAvailabilityTooltip(dayElem, availability);
              }
            }
          } catch (error) {
            rbfLog.warn(`Availability color error for date: ${error.message}`);
          }
          
          // Add visual indicators for special dates (non-blocking)
          try {
            addDateIndicators(dayElem);
          } catch (error) {
            rbfLog.warn(`Visual indicator error for date: ${error.message}`);
            // Continue without visual indicators rather than breaking the calendar
          }
        }
      };
      
      // Set reasonable max date (1 year from now if not specified)
      const maxAdvanceMs = shouldApplyMaxAdvanceLimit ? 
        maxAdvanceMinutes * 60 * 1000 : 
        365 * 24 * 60 * 60 * 1000; // 1 year default
      
      flatpickrConfig.maxDate = new Date(new Date().getTime() + maxAdvanceMs);
      
      // Apply minimum advance time
      if (rbfData.minAdvanceMinutes && rbfData.minAdvanceMinutes > 0) {
        flatpickrConfig.minDate = new Date(new Date().getTime() + rbfData.minAdvanceMinutes * 60 * 1000);
      }
      
      // Clean up any existing calendar instance
      if (fp) {
        try {
          restoreOriginalDateInputAttributes(fp, 'before cleanup');
          fp.destroy();
        } catch (error) {
          rbfLog.warn('Error destroying previous calendar instance');
        }
        fp = null;
      }
      
      // Create the new flatpickr instance with enhanced error handling
      fp = flatpickr(el.dateInput[0], flatpickrConfig);
      
      if (fp && fp.calendarContainer) {
        rbfLog.log('✅ Flatpickr instance created successfully');
        
        // Show exception legend if there are exceptions
        updateLegendVisibility();
        
        return true;
      } else {
        throw new Error('Flatpickr instance creation failed - no instance or container returned');
      }
      
    } catch (error) {
      rbfLog.error(`Failed to create calendar: ${error.message}`);
      
      // Clear the failed instance
      fp = null;
      
      // Auto-switch to fallback
      rbfLog.log('Auto-switching to HTML5 date input fallback due to Flatpickr error');
      setupFallbackDateInput();
      
      return false;
    }
  }
  
  /**
   * EMERGENCY CALENDAR - Ultra-simple with no restrictions
   * Used when the main calendar fails
   */
  function initializeEmergencyCalendar() {
    rbfLog.log('Initializing emergency calendar with minimal restrictions');
    
    const emergencyConfig = {
      altInput: true,
      altFormat: 'd-m-Y',
      dateFormat: 'Y-m-d',
      minDate: 'today',
      locale: (rbfData && rbfData.locale === 'it') ? 'it' : 'default',
      enableTime: false,
      noCalendar: false,
      
      // Position calendar relative to input field instead of bottom of page
      static: true,
      
      // EMERGENCY: Allow all dates - no disable function
      // disable: [], // Commenting out completely to allow all dates
      
      onChange: onDateChange,

      onReady: function(selectedDates, dateStr, instance) {
        rbfLog.log('Emergency calendar initialized - all dates should be available');

        if (instance.altInput && instance.input) {
          transferAriaAttributesToAltInput(instance);
          updateAltInputAriaExpanded(instance, false);

          const originalId = instance.input.id;
          if (originalId) {
            instance.altInput.id = originalId;
            instance.input.removeAttribute('id');
            instance.input.setAttribute('data-original-id', originalId);
            rbfLog.log('📋 Emergency accessibility: transferred id to alternate input');
          }
        } else {
          rbfLog.warn('Emergency calendar altInput not available - accessibility fix not applied');
        }

        setTimeout(() => {
          forceCalendarInteractivity(instance);
        }, 100);
      },

      onOpen: function(selectedDates, dateStr, instance) {
        updateAltInputAriaExpanded(instance, true);

        setTimeout(() => {
          forceCalendarInteractivity(instance);
        }, 50);

        setTimeout(() => {
          forceCalendarInteractivity(instance);
        }, 200);
      },

      onClose: function(selectedDates, dateStr, instance) {
        updateAltInputAriaExpanded(instance, false);
      }
    };

    // Clean up any existing calendar instance
    if (fp) {
      try {
        restoreOriginalDateInputAttributes(fp, 'in emergency cleanup');
        fp.destroy();
      } catch (error) {
        rbfLog.warn('Error destroying emergency calendar instance: ' + error.message);
      }
      fp = null;
    }
    
    try {
      fp = flatpickr(el.dateInput[0], emergencyConfig);
      
      if (fp) {
        rbfLog.log('Emergency Flatpickr instance created successfully');
        window.rbfCalendarInstance = fp;
      } else {
        throw new Error('Emergency flatpickr instance creation failed');
      }
      
    } catch (error) {
      rbfLog.error('Emergency calendar failed: ' + error.message);
      // Fallback to HTML5 date input
      setupFallbackDateInput();
    }
  }

  /**
   * EMERGENCY FIXED DISABLE LOGIC - Ultra-safe and diagnostic
   * Designed to prevent all dates being disabled
   */
  function isDateDisabled(date) {
    try {
      const dateStr = formatLocalISO(date);
      const dayOfWeek = date.getDay(); // 0 = Sunday, 1 = Monday, etc.
      
      // DEBUG: Log first few checks in detail (only in debug mode)
      if (rbfData.debug && window.rbfDetailedDebugCount === undefined) {
        window.rbfDetailedDebugCount = 0;
      }
      if (rbfData.debug && window.rbfDetailedDebugCount < 3) {
        rbfLog.log(`DETAILED DEBUG ${window.rbfDetailedDebugCount}:`);
        rbfLog.log(`  Date: ${dateStr}, Day: ${dayOfWeek}`);
        rbfLog.log(`  rbfData.closedDays: ${JSON.stringify(rbfData.closedDays)}`);
        rbfLog.log(`  rbfData.closedSingles: ${JSON.stringify(rbfData.closedSingles)}`);
        window.rbfDetailedDebugCount++;
      }
      
      // EMERGENCY OVERRIDE: If we detect that everything is being disabled, just allow all dates
      if (window.rbfEmergencyMode === undefined) {
        window.rbfEmergencyMode = false;
        window.rbfDisabledCount = 0;
        window.rbfTotalCount = 0;
      }
      
      window.rbfTotalCount++;
      
      // CRITICAL FIX: Detect and fix configuration where all days are disabled
      if (rbfData.closedDays && Array.isArray(rbfData.closedDays) && rbfData.closedDays.length >= 7) {
        rbfLog.error('CRITICAL ISSUE DETECTED: All 7 days marked as closed!');
        rbfLog.error('Original closedDays: ' + JSON.stringify(rbfData.closedDays));
        rbfLog.error('This would disable ALL calendar dates - applying emergency fix');
        
        // Emergency fix: Reset to only Monday closed (common restaurant closure)
        rbfData.closedDays = [1]; // 1 = Monday
        
        rbfLog.warn('EMERGENCY FIX APPLIED: Only Monday will be closed now');
        rbfLog.warn('Please check WordPress admin settings for restaurant opening hours');
      }
      
      // ADDITIONAL SAFETY: Remove any invalid day values
      if (rbfData.closedDays && Array.isArray(rbfData.closedDays)) {
        const originalLength = rbfData.closedDays.length;
        rbfData.closedDays = rbfData.closedDays.filter(day => 
          typeof day === 'number' && day >= 0 && day <= 6
        );
        if (originalLength !== rbfData.closedDays.length) {
          rbfLog.warn('Removed invalid day values from closedDays');
        }
      }
      
      // SIMPLE CHECK 1: General restaurant closed days
      if (rbfData.closedDays && Array.isArray(rbfData.closedDays) && rbfData.closedDays.length > 0) {
        if (rbfData.closedDays.includes(dayOfWeek)) {
          window.rbfDisabledCount++;
          if (rbfData.debug) rbfLog.log(`Date ${dateStr} disabled: restaurant closed on day ${dayOfWeek}`);
          return true;
        }
      }
      
      // SIMPLE CHECK 2: Specific closed dates (with safety limits)
      if (rbfData.closedSingles && Array.isArray(rbfData.closedSingles) && rbfData.closedSingles.length > 0) {
        // SAFETY CHECK: Warn if too many individual dates are closed
        if (rbfData.closedSingles.length > 100) {
          rbfLog.warn(`WARNING: ${rbfData.closedSingles.length} individual dates are closed - this might be excessive`);
          rbfLog.warn('Consider using date ranges instead of individual dates for better performance');
        }
        
        if (rbfData.closedSingles.includes(dateStr)) {
          window.rbfDisabledCount++;
          if (rbfData.debug) rbfLog.log(`Date ${dateStr} disabled: specific closure date`);
          return true;
        }
      }
      
      // SIMPLE CHECK 3: Closed date ranges (with safety checks)
      if (rbfData.closedRanges && Array.isArray(rbfData.closedRanges) && rbfData.closedRanges.length > 0) {
        // SAFETY CHECK: Remove extremely long ranges that might disable everything
        const originalRanges = rbfData.closedRanges.length;
        rbfData.closedRanges = rbfData.closedRanges.filter(range => {
          if (!range || !range.from || !range.to) return false;
          
          try {
            const fromDate = new Date(range.from);
            const toDate = new Date(range.to);
            const diffDays = Math.ceil((toDate - fromDate) / (1000 * 60 * 60 * 24));
            
            // Remove ranges longer than 2 years (probably erroneous)
            if (diffDays > 730) {
              rbfLog.warn(`Removed extremely long closed range: ${range.from} to ${range.to} (${diffDays} days)`);
              return false;
            }
            return true;
          } catch (error) {
            rbfLog.warn(`Removed invalid date range: ${JSON.stringify(range)}`);
            return false;
          }
        });
        
        if (originalRanges !== rbfData.closedRanges.length) {
          rbfLog.warn(`Cleaned closedRanges: removed ${originalRanges - rbfData.closedRanges.length} problematic ranges`);
        }
        
        for (let range of rbfData.closedRanges) {
          if (range && range.from && range.to) {
            if (dateStr >= range.from && dateStr <= range.to) {
              window.rbfDisabledCount++;
              if (rbfData.debug) rbfLog.log(`Date ${dateStr} disabled: within closed range ${range.from} to ${range.to}`);
              return true;
            }
          }
        }
      }
      
      // EMERGENCY CHECK: If too many dates are being disabled, switch to emergency mode
      if (window.rbfTotalCount > 20 && (window.rbfDisabledCount / window.rbfTotalCount) > 0.8) {
        rbfLog.error('Too many dates disabled, switching to emergency mode');
        window.rbfEmergencyMode = true;
      }
      
      // EMERGENCY MODE: Allow all dates if we're in emergency mode
      if (window.rbfEmergencyMode) {
        if (rbfData.debug) rbfLog.log(`Date ${dateStr} allowed: EMERGENCY MODE ACTIVE`);
        return false;
      }
      
      // SIMPLE CHECK 4: Special exceptions (only closure and holiday)
      if (rbfData.exceptions && Array.isArray(rbfData.exceptions) && rbfData.exceptions.length > 0) {
        for (let exception of rbfData.exceptions) {
          if (exception && exception.date === dateStr) {
            if (exception.type === 'closure' || exception.type === 'holiday') {
              window.rbfDisabledCount++;
              if (rbfData.debug) rbfLog.log(`Date ${dateStr} disabled: ${exception.type} exception`);
              return true;
            }
          }
        }
      }
      
      // SKIP MEAL AVAILABILITY CHECK FOR NOW - it was causing issues
      // TODO: Re-enable once core calendar is working
      
      // DEFAULT: Allow the date
      if (rbfData.debug) rbfLog.log(`Date ${dateStr} allowed: no restrictions apply`);
      return false;
      
    } catch (error) {
      rbfLog.error('Error in isDateDisabled: ' + error.message);
      // EMERGENCY: Always allow date on error
      return false;
    }
  }

  /**
   * Add visual indicators to calendar days (non-blocking)
   */
  function addDateIndicators(dayElem) {
    if (!dayElem || !dayElem.dateObj) return;
    
    const dateStr = formatLocalISO(dayElem.dateObj);
    
    // Add CSS classes for different special date types
    if (rbfData.exceptions && Array.isArray(rbfData.exceptions)) {
      for (let exception of rbfData.exceptions) {
        if (exception && exception.date === dateStr) {
          // Remove existing special date classes to avoid conflicts
          dayElem.classList.remove('rbf-special-event', 'rbf-extended-hours', 'rbf-holiday', 'rbf-closure');
          
          // Apply appropriate class based on exception type for visual differentiation
          switch (exception.type) {
            case 'special':
              dayElem.classList.add('rbf-special-event');
              break;
            case 'extended':
              dayElem.classList.add('rbf-extended-hours');
              break;
            case 'holiday':
              dayElem.classList.add('rbf-holiday');
              break;
            case 'closure':
              dayElem.classList.add('rbf-closure');
              break;
            default:
              dayElem.classList.add('rbf-closure'); // Default to closure for safety
          }
          
          // Add tooltip with description
          if (exception.description) {
            dayElem.title = `${exception.type.toUpperCase()}: ${exception.description}`;
          } else {
            dayElem.title = exception.type.toUpperCase();
          }
          
          break;
        }
      }
    }
  }

  /**
   * Enhanced international telephone input with robust fallback
   */
  function lazyLoadTelInput() {
    return new Promise((resolve) => {
      const setupFallback = () => {
        rbfLog.warn('intl-tel-input not available, setting up enhanced fallback');
        if (el.telInput.length) {
          // Enhanced fallback phone input
          el.telInput.addClass('rbf-tel-fallback');
          el.telInput.attr('type', 'tel');
          el.telInput.attr('placeholder', rbfData.labels.phonePlaceholder || '+39 000 000 0000');
          
          // Add country code selector as a prefix
          const wrapper = $('<div class="rbf-tel-wrapper"></div>');
          const prefix = $('<div class="rbf-tel-prefix">🇮🇹 +39</div>');
          
          el.telInput.wrap(wrapper);
          el.telInput.before(prefix);
          
          // Set default country code
          if ($('#rbf_country_code').length === 0) {
            $('<input type="hidden" id="rbf_country_code" name="rbf_country_code" value="it">').insertAfter(el.telInput);
          } else {
            $('#rbf_country_code').val('it');
          }
          
          // Enhanced validation for fallback
          el.telInput.on('input', function() {
            const value = $(this).val();
            // Basic Italian phone number validation
            const isValid = /^[\d\s\-\+\(\)]{6,}$/.test(value);
            $(this).toggleClass('rbf-invalid', !isValid && value.length > 0);
          });
          
          rbfLog.log('Enhanced fallback phone input configured');
        }
        resolve();
      };

      if (typeof intlTelInput === 'undefined') {
        // Try to load intl-tel-input with timeout
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/19.2.16/js/intlTelInput.min.js';
        script.async = true;
        
        const loadTimeout = setTimeout(() => {
          rbfLog.warn('intl-tel-input loading timeout, using fallback');
          setupFallback();
        }, 5000);
        
        script.onload = () => {
          clearTimeout(loadTimeout);
          initializeTelInput();
          resolve();
        };
        
        script.onerror = () => {
          clearTimeout(loadTimeout);
          setupFallback();
        };
        
        document.head.appendChild(script);
      } else {
        initializeTelInput();
        resolve();
      }
    });
  }

  /**
   * Add optimal layout class based on number of meals
   */
  function addMealCountClass() {
    const mealCount = el.mealRadios.length;
    const radioGroup = el.mealRadios.closest('.rbf-radio-group');
    
    // Remove existing meal count classes
    radioGroup.removeClass(function(index, className) {
      return (className.match(/\bmeals-\d+/g) || []).join(' ');
    });
    
    // Add appropriate class for optimal layout
    if (mealCount >= 2 && mealCount <= 6) {
      radioGroup.addClass('meals-' + mealCount);
    }
  }
  
  // Initialize optimal meal layout
  addMealCountClass();

  function formatLocalISO(date) {
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000)
      .toISOString()
      .split('T')[0];
  }

  /**
   * Update progress indicator
   */
  function updateProgressIndicator(step) {
    currentStep = step;
    
    // Update progress bar ARIA attributes
    const progressBar = el.progressSteps.parent();
    progressBar.attr('aria-valuenow', step);
    
    el.progressSteps.each(function(index) {
      const $step = $(this);
      const stepNumber = index + 1;
      
      $step.removeClass('active completed');
      
      if (stepNumber < currentStep) {
        $step.addClass('completed').attr('aria-current', 'false');
      } else if (stepNumber === currentStep) {
        $step.addClass('active').attr('aria-current', 'step');
      } else {
        $step.attr('aria-current', 'false');
      }
    });
  }

  /**
   * Show step with animation, accessibility, and skeleton handling
   */
  function showStep($step, stepNumber) {
    // Cancel any pending hide timeout for this step
    if (stepTimeouts.has($step[0])) {
      clearTimeout(stepTimeouts.get($step[0]));
      stepTimeouts.delete($step[0]);
    }
    
    const timeout = setTimeout(() => {
      $step.show().addClass('active');
      updateProgressIndicator(stepNumber);
      
      // Handle skeleton loading for specific steps
      const stepId = $step.attr('id');
      
      if (stepId === 'step-date' && $step.attr('data-skeleton') === 'true') {
        // Show skeleton initially, then lazy load date picker
        setTimeout(() => {
          lazyLoadDatePicker().then(() => {
            removeSkeleton($step);
            // CRITICAL FIX: Extra safety check to ensure calendar is interactive
            setTimeout(() => {
              if (fp) {
                rbfLog.log('Calendar fully enabled and interactive after lazy load');
              }
            }, 200);
          });
        }, 100);
      } else if (stepId === 'step-time' && $step.attr('data-skeleton') === 'true') {
        // Remove skeleton immediately for time step as it's just a select
        setTimeout(() => {
          removeSkeleton($step);
        }, 150);
      } else if (stepId === 'step-people' && $step.attr('data-skeleton') === 'true') {
        // Remove skeleton for people selector after short delay
        setTimeout(() => {
          removeSkeleton($step);
        }, 100);
      } else if (stepId === 'step-details' && $step.attr('data-skeleton') === 'true') {
        // Show skeleton initially, then lazy load telephone input
        setTimeout(() => {
          lazyLoadTelInput().then(() => {
            removeSkeleton($step);
          });
        }, 200);
      }
      
      // Focus management for screen readers (after skeleton is removed)
      setTimeout(() => {
        const firstInput = $step.find('input:not([type="hidden"]), select, textarea').not('[disabled]').first();
        if (firstInput.length && !firstInput.prop('disabled')) {
          firstInput.focus();
        }
      }, 300);
      
      // Auto-scroll disabled to prevent annoying anchor jumps
      // The form is designed to be visible without forced scrolling
      // Users can manually scroll if needed
      
      // Announce step change to screen readers
      const stepLabel = $step.attr('aria-labelledby');
      if (stepLabel) {
        const labelText = $('#' + stepLabel).text();
        setTimeout(() => {
          announceToScreenReader(`Passaggio ${stepNumber}: ${labelText}`);
        }, 300);
      }
      
      stepTimeouts.delete($step[0]);
    }, 100);
    
    stepTimeouts.set($step[0], timeout);
  }

  /**
   * Show step with animation and accessibility but without auto-scrolling
   * Used when we don't want to interrupt user's current view position
   */
  function showStepWithoutScroll($step, stepNumber) {
    // Cancel any pending hide timeout for this step
    if (stepTimeouts.has($step[0])) {
      clearTimeout(stepTimeouts.get($step[0]));
      stepTimeouts.delete($step[0]);
    }
    
    if ($step.hasClass('active')) return;
    
    // Mark step as active immediately for proper state tracking
    $step.addClass('active').show();
    
    // Update progress indicator
    updateProgressIndicator(stepNumber);
    
    const timeout = setTimeout(() => {
      // Handle skeleton loading for specific steps (same as showStep but without scrolling)
      const stepId = $step.attr('id');
      const isDateStep = stepId === 'step-date';

      if (isDateStep && ($step.attr('data-skeleton') === 'true' || fp === null)) {
        // Handle both skeleton loading and calendar reinitialization in a single call
        setTimeout(() => {
          lazyLoadDatePicker().then(() => {
            // Remove skeleton if present
            if ($step.attr('data-skeleton') === 'true') {
              removeSkeleton($step);
            }
            
            // Ensure calendar is ready for user interaction (don't auto-open)
            setTimeout(() => {
              if (fp) {
                rbfLog.log('Calendar fully enabled and interactive after lazy load');
              }
            }, 200);
          });
        }, 100);
      } else if (stepId === 'step-time' && $step.attr('data-skeleton') === 'true') {
        // Remove skeleton immediately for time step as it's just a select
        setTimeout(() => {
          removeSkeleton($step);
        }, 150);
      } else if (stepId === 'step-people' && $step.attr('data-skeleton') === 'true') {
        // Remove skeleton for people selector after short delay
        setTimeout(() => {
          removeSkeleton($step);
        }, 100);
      } else if (stepId === 'step-details' && $step.attr('data-skeleton') === 'true') {
        // Show skeleton initially, then lazy load telephone input
        setTimeout(() => {
          lazyLoadTelInput().then(() => {
            removeSkeleton($step);
          });
        }, 200);
      } else {
        // Handle generic skeleton removal for steps without specific handlers
        if ($step.attr('data-skeleton') === 'true') {
          $step.removeAttr('data-skeleton');
          $step.find('.rbf-skeleton-fields, .rbf-skeleton-time, .rbf-skeleton-people-selector').fadeOut(300, function() {
            $(this).remove();
          });
          $step.find('.rbf-fade-in').delay(200).fadeIn(400);
        }
      }
      
      // Focus management for accessibility (but no scrolling)
      if (stepNumber >= 2) {
        setTimeout(() => {
          const firstFocusable = $step.find('input:not([readonly]), select, button, [tabindex="0"]').first();
          if (firstFocusable.length && firstFocusable.is(':visible')) {
            firstFocusable.focus();
          }
        }, 300); // Increased delay to ensure skeleton is removed first
      }
      
      // Announce step change to screen readers
      const stepLabel = $step.attr('aria-labelledby');
      if (stepLabel) {
        const labelText = $('#' + stepLabel).text();
        setTimeout(() => {
          announceToScreenReader(`Passaggio ${stepNumber}: ${labelText}`);
        }, 300);
      }
      
      stepTimeouts.delete($step[0]);
    }, 100);
    
    stepTimeouts.set($step[0], timeout);
  }

  /**
   * Announce message to screen readers
   */
  function announceToScreenReader(message) {
    const announcement = $('<div>').attr({
      'aria-live': 'polite',
      'aria-atomic': 'true',
      'class': 'sr-only'
    }).text(message);
    
    $('body').append(announcement);
    
    setTimeout(() => {
      announcement.remove();
    }, 1000);
  }

  /**
   * Hide step with animation
   */
  function hideStep($step) {
    // Cancel any pending show timeout for this step
    if (stepTimeouts.has($step[0])) {
      clearTimeout(stepTimeouts.get($step[0]));
      stepTimeouts.delete($step[0]);
    }
    
    $step.removeClass('active');
    const timeout = setTimeout(() => {
      $step.hide();
      stepTimeouts.delete($step[0]);
    }, 300);
    
    stepTimeouts.set($step[0], timeout);
  }

  /**
   * Initialize international telephone input with enhanced error handling and styling
   */
  function initializeTelInput() {
    let retryCount = 0;
    const maxRetries = 5;
    
    function attemptInit() {
      // Check if intlTelInput is available
      if (typeof intlTelInput === 'undefined') {
        if (retryCount < maxRetries) {
          retryCount++;
          setTimeout(attemptInit, 1000 * retryCount); // Exponential backoff
          return;
        } else {
          el.telInput.addClass('rbf-tel-fallback');
          $('#rbf_country_code').val('it'); // Default to Italy
          return;
        }
      }
      
      // Use a small delay to ensure the element is fully visible
      setTimeout(() => {
        if (el.telInput.length && !iti) {
          try {
            // Enhanced initialization with better flag support
            iti = intlTelInput(el.telInput[0], {
              utilsScript: rbfData.utilsScript,
              initialCountry: 'it',
              preferredCountries: ['it', 'gb', 'us', 'de', 'fr', 'es', 'ch', 'at'],
              separateDialCode: true,
              nationalMode: false,
              autoPlaceholder: 'aggressive',
              allowDropdown: true,
              showSelectedDialCode: true,
              formatOnDisplay: true,
              autoFormat: true,
              // Enhanced flag container styling
              // Attach dropdown directly to the input wrapper to avoid page jumps
              // when the user clicks the flag button
              customPlaceholder: function(selectedCountryPlaceholder, selectedCountryData) {
                return rbfData.labels.phonePlaceholder || selectedCountryPlaceholder;
              }
            });

            if (iti) {
              // Enhanced event handling
              el.telInput[0].addEventListener('countrychange', function() {
                const countryData = iti.getSelectedCountryData();
                $('#rbf_country_code').val(countryData.iso2);
                
                // Announce to screen readers
                announceToScreenReader(`Country selected: ${countryData.name}`);
              });
              
              // Enhanced dropdown handling
              el.telInput[0].addEventListener('open:countrydropdown', function() {
                // Ensure proper z-index
                const dropdown = document.querySelector('.iti__country-list');
                if (dropdown) {
                  dropdown.style.zIndex = '9999';
                }
              });
              
              el.telInput[0].addEventListener('close:countrydropdown', function() {
                // Dropdown closed
              });
              
              // Improved validation feedback
              el.telInput[0].addEventListener('blur', function() {
                if (iti && el.telInput.val().trim()) {
                  const isValid = iti.isValidNumber();
                  if (!isValid) {
                    el.telInput.addClass('rbf-tel-invalid');
                  } else {
                    el.telInput.removeClass('rbf-tel-invalid');
                  }
                }
              });
              
              // Set initial country code
              const initialCountryData = iti.getSelectedCountryData();
              if (initialCountryData) {
                $('#rbf_country_code').val(initialCountryData.iso2);
              }
              
              // Remove fallback styling
              el.telInput.removeClass('rbf-tel-fallback');
              
              // Add custom CSS class for styling
              el.telInput.closest('.iti').addClass('rbf-iti-enhanced');

              // Rebuild flag selector button to prevent anchor jumps and improve accessibility
              const flagButton = el.telInput.closest('.iti').find('.iti__selected-flag');
              if (flagButton.length) {
                const flagLabel = rbfData.labels.selectPrefix || 'Seleziona prefisso';

                flagButton.attr({
                  role: 'button',
                  title: flagLabel,
                  'aria-label': flagLabel,
                  tabindex: flagButton.attr('tabindex') || '0'
                });

                const toggleDropdown = function(event) {
                  if (event) {
                    event.preventDefault();
                  }

                  if (iti && typeof iti.toggleCountryDropdown === 'function') {
                    iti.toggleCountryDropdown();
                  }
                };

                flagButton.on('click', toggleDropdown);
                flagButton.on('keydown', function(event) {
                  if (event.key === 'Enter' || event.key === ' ') {
                    toggleDropdown(event);
                  }
                });
              }

            } else {
              el.telInput.addClass('rbf-tel-fallback');
              $('#rbf_country_code').val('it');
            }
          } catch (error) {
            el.telInput.addClass('rbf-tel-fallback');
            $('#rbf_country_code').val('it');
          }
        } else if (!el.telInput.length) {
          // Tel input element not found
        } else if (iti) {
          // intlTelInput already initialized
        }
      }, 500); // Increased delay for better element rendering
    }
    
    attemptInit();
  }

  /**
   * Reset steps from a given step onwards
   */
  function resetSteps(fromStep) {
    if (fromStep <= 1) {
      hideStep(el.dateStep);
      if (fp) {
        fp.clear();
        restoreOriginalDateInputAttributes(fp, 'in resetSteps');
        fp.destroy();
        fp = null;
        datePickerInitPromise = null;
      }
    }
    if (fromStep <= 2) hideStep(el.timeStep);
    if (fromStep <= 3) hideStep(el.peopleStep);
    if (fromStep <= 4) {
      hideStep(el.detailsStep);
      el.detailsInputs.prop('disabled', true);
      el.privacyCheckbox.prop('disabled', true);
      el.marketingCheckbox.prop('disabled', true);
      el.submitButton.hide().prop('disabled', true);
    }
    updateProgressIndicator(fromStep);
  }

  /**
   * Handle meal selection change - RENEWED with complete calendar refresh
   */
  el.mealRadios.on('change', function() {
    resetSteps(1);
    el.mealNotice.hide();
    
    // Remove any existing suggestions when meal changes
    $('.rbf-suggestions-container').remove();
    
    const selectedMeal = $(this).val();
    rbfLog.log(`🍽️ Meal changed to: ${selectedMeal}`);
    
    // Show meal-specific tooltip if configured
    if (rbfData.mealTooltips && rbfData.mealTooltips[selectedMeal]) {
      el.mealNotice.text(rbfData.mealTooltips[selectedMeal]).show();
    }
    
    // Show date step for any meal selection without scrolling
    showStepWithoutScroll(el.dateStep, 2);

    // RENEWED: Complete calendar refresh when meal changes
    refreshCalendarForMeal(selectedMeal);
  });

  /**
   * RENEWED: Refresh calendar completely for meal changes
   * This ensures that date availability is properly updated based on meal selection
   */
  function refreshCalendarForMeal(selectedMeal) {
    rbfLog.log(`🔄 Refreshing calendar for meal: ${selectedMeal}`);
    
    // If calendar exists, refresh it
    if (fp) {
      try {
        // Store current selected date if any
        const currentDate = fp.selectedDates.length > 0 ? fp.selectedDates[0] : null;
        
        // Clear selection first
        fp.clear();
        
        // Force a complete redraw to re-evaluate all dates
        fp.redraw();
        
        // If we had a selected date, check if it's still valid and reselect
        if (currentDate) {
          const isStillValid = !isDateDisabled(currentDate);
          if (isStillValid) {
            fp.setDate(currentDate);
            rbfLog.log(`✅ Previous date ${formatLocalISO(currentDate)} is still valid for ${selectedMeal}`);
          } else {
            rbfLog.log(`❌ Previous date ${formatLocalISO(currentDate)} is no longer valid for ${selectedMeal}`);
            // Clear the input value since the date is no longer valid
            el.dateInput.val('');
          }
        }
        
        rbfLog.log(`✅ Calendar refreshed successfully for meal: ${selectedMeal}`);
        
      } catch (error) {
        rbfLog.error(`Calendar refresh error: ${error.message}`);
        // If refresh fails, reinitialize the calendar
        reinitializeCalendar();
      }
    } else {
      // If no calendar exists yet, it will be created when the date step is shown
      rbfLog.log('📅 Calendar will be initialized when date step is shown');
    }

    // Update availability data for the selected meal
    if (selectedMeal) {
      updateAvailabilityDataForMeal(selectedMeal);
    }
  }

  /**
   * RENEWED: Complete calendar reinitialization
   * Used when refresh fails or major errors occur
   */
  function reinitializeCalendar() {
    rbfLog.log('🔧 Reinitializing calendar completely...');
    
    try {
      // Destroy existing instance
      if (fp) {
        restoreOriginalDateInputAttributes(fp, 'in reinitializeCalendar');
        fp.destroy();
        fp = null;
      }
      
      // Clear any cached data
      datePickerInitPromise = null;
      
      // Reinitialize
      lazyLoadDatePicker().then(() => {
        rbfLog.log('✅ Calendar reinitialized successfully');
        
        // Ensure interactivity
        setTimeout(() => {
          if (fp) {
            forceCalendarInteractivity(fp);
          }
        }, 100);
      });
      
    } catch (error) {
      rbfLog.error(`Calendar reinitialization failed: ${error.message}`);
      // Fallback to HTML5 date input
      setupFallbackDateInput();
    }
  }

  /**
   * Update availability data for specific meal
   */
  function updateAvailabilityDataForMeal(selectedMeal) {
    if (!fp) {
      lazyLoadDatePicker().then(() => {
        if (fp) {
          updateAvailabilityDataForMeal(selectedMeal);
        }
      });
      return;
    }

    try {
      if (!selectedMeal) {
        rbfLog.warn('No meal provided for availability update');

        if (availabilityRequest && typeof availabilityRequest.abort === 'function') {
          try {
            availabilityRequest.abort();
            rbfLog.log('⏹️ Availability request aborted due to missing meal selection');
          } catch (error) {
            rbfLog.warn('Failed to abort availability request without meal: ' + error.message);
          }
        }

        availabilityRequest = null;
        availabilityData = {};

        updateLegendVisibility();

        if (fp) {
          fp.redraw();
        }

        return;
      }

      const fallbackDate = (fp.now instanceof Date) ? fp.now : new Date();
      const targetYear = Number.isFinite(fp.currentYear) ? fp.currentYear : fallbackDate.getFullYear();
      const targetMonth = Number.isFinite(fp.currentMonth) ? fp.currentMonth : fallbackDate.getMonth();
      const startDate = new Date(targetYear, targetMonth, 1);
      const endDate = new Date(targetYear, targetMonth + 1, 0);

      fetchAvailabilityData(
        formatLocalISO(startDate),
        formatLocalISO(endDate),
        selectedMeal
      ).then(() => {
        // Redraw calendar to apply new availability colors
        if (fp) {
          fp.redraw();
          rbfLog.log(`📊 Availability data updated for meal: ${selectedMeal}`);
        }
      }).catch(error => {
        rbfLog.warn(`Failed to update availability data: ${error.message}`);
      });
    } catch (error) {
      rbfLog.warn(`Error updating availability data: ${error.message}`);
    }
  }

  /**
   * Retrieve the currently selected date information
   */
  function getSelectedDateInfo() {
    if (fp && fp.selectedDates && fp.selectedDates.length) {
      const dateObj = fp.selectedDates[0];
      return {
        dateObj,
        dateString: formatLocalISO(dateObj)
      };
    }

    const rawValue = el.dateInput.val();
    if (!rawValue) {
      return null;
    }

    let parsedDate = new Date(rawValue + 'T00:00:00');
    if (Number.isNaN(parsedDate.getTime())) {
      const parts = rawValue.split('-');
      if (parts.length === 3) {
        const [part1, part2, part3] = parts;
        if (part1.length === 4) {
          parsedDate = new Date(`${part1}-${part2}-${part3}T00:00:00`);
        } else {
          const day = parseInt(part1, 10);
          const month = parseInt(part2, 10);
          const year = parseInt(part3, 10);

          if (!Number.isNaN(day) && !Number.isNaN(month) && !Number.isNaN(year)) {
            parsedDate = new Date(year, month - 1, day);
          }
        }
      }
    }

    if (Number.isNaN(parsedDate.getTime())) {
      return null;
    }

    return {
      dateObj: parsedDate,
      dateString: formatLocalISO(parsedDate)
    };
  }

  /**
   * Retrieve the active people count with a safe fallback
   */
  function getSelectedPeopleCount() {
    const parsed = parseInt(el.peopleInput.val(), 10);
    const normalized = Number.isNaN(parsed) ? 1 : parsed;
    return Math.min(peopleMaxLimit, Math.max(1, normalized));
  }

  /**
   * Load available times for the provided configuration
   */
  function loadAvailableTimes({ dateString, selectedMeal, preserveSelectedTime = false } = {}) {
    if (!dateString || !selectedMeal) {
      return Promise.resolve({ selectionPreserved: false });
    }

    // Remove any existing suggestions when reloading availability
    $('.rbf-suggestions-container').remove();

    // Ensure the time step is visible while loading
    showStepWithoutScroll(el.timeStep, 3);

    const previousSelection = preserveSelectedTime ? el.timeSelect.val() : null;

    el.timeSelect.html(`<option value="">${rbfData.labels.loading}</option>`).prop('disabled', true);
    el.timeSelect.addClass('rbf-loading');

    const progressText = '⏳ Caricamento orari in corso...';
    el.timeSelect.find('option').first().text(progressText);

    const peopleCount = getSelectedPeopleCount();

    return new Promise(resolve => {
      let selectionPreserved = false;

      const loadingTimeout = setTimeout(function() {
        el.timeSelect.removeClass('rbf-loading');
        el.timeSelect.html('');
        el.timeSelect.append(new Option('Errore: timeout nel caricamento. Riprova.', ''));
        el.timeSelect.prop('disabled', false);
        rbfLog.warn('AJAX timeout: Loading took too long, forced cleanup');
      }, 10000); // 10 second timeout

      const request = $.post({
        url: rbfData.ajaxUrl,
        data: {
          action: 'rbf_get_availability',
          _ajax_nonce: rbfData.nonce,
          date: dateString,
          meal: selectedMeal,
          people: peopleCount
        },
        timeout: 8000 // 8 second AJAX timeout
      });

      request.done(function(response) {
        el.timeSelect.html('');

        if (response.success) {
          // Check if we have available times or suggestions
          const hasAvailableTimes = response.data.available_times && response.data.available_times.length > 0;
          const hasSuggestions = response.data.suggestions && response.data.suggestions.length > 0;

          if (hasAvailableTimes) {
            // Handle normal available times
            el.timeSelect.append(new Option(rbfData.labels.chooseTime, ''));

            // Simplified client-side logging (server handles all filtering logic)
            const today = new Date();
            const currentDate = dateString;
            const todayString = formatLocalISO(today);
            const isToday = (currentDate === todayString);

            // Filter out past time slots only for today's date
            let availableCount = 0;
            const minTime = new Date(today.getTime() + rbfData.minAdvanceMinutes * 60 * 1000);

            response.data.available_times.forEach(item => {
              if (isToday) {
                const [h, m] = item.time.split(':').map(Number);
                const slotDate = new Date(today.getFullYear(), today.getMonth(), today.getDate(), h, m);
                if (slotDate < minTime) {
                  return; // Skip past times
                }
              }
              const opt = new Option(item.time, `${item.slot}|${item.time}`);
              el.timeSelect.append(opt);
              availableCount++;
            });

            if (availableCount > 0) {
              el.timeSelect.prop('disabled', false);

              // Enhanced success feedback
              const successMessage = `✅ ${availableCount} orari disponibili caricati per ${selectedMeal}`;
              announceToScreenReader(successMessage);

              // Show brief success indication (optional visual feedback)
              if (rbfData.debug) {
                console.log('RBF: Time slots loaded successfully', {
                  meal: selectedMeal,
                  date: dateString,
                  count: availableCount
                });
              }
            } else {
              el.timeSelect.html('');
              el.timeSelect.append(new Option(rbfData.labels.noTime, ''));
              announceToScreenReader('Nessun orario disponibile per questa data');
            }
          } else if (hasSuggestions) {
            // Handle suggestions when no times available
            el.timeSelect.append(new Option(rbfData.labels.noTime, ''));
            displayAlternativeSuggestions(response.data.suggestions, response.data.message);
            announceToScreenReader(response.data.message || 'Nessun orario disponibile, ma ci sono alternative');
          } else {
            // Handle legacy response format (array of times)
            if (response.data.length > 0) {
              el.timeSelect.append(new Option(rbfData.labels.chooseTime, ''));

              // Simplified client-side logging (server handles all filtering logic)
              const today = new Date();
              const currentDate = dateString;
              const todayString = formatLocalISO(today);
              const isToday = (currentDate === todayString);

              // Filter out past time slots only for today's date
              let availableCount = 0;
              const minTime = new Date(today.getTime() + rbfData.minAdvanceMinutes * 60 * 1000);

              response.data.forEach(item => {
                if (isToday) {
                  const [h, m] = item.time.split(':').map(Number);
                  const slotDate = new Date(today.getFullYear(), today.getMonth(), today.getDate(), h, m);
                  if (slotDate < minTime) {
                    return; // Skip past times
                  }
                }
                const opt = new Option(item.time, `${item.slot}|${item.time}`);
                el.timeSelect.append(opt);
                availableCount++;
              });

              if (availableCount > 0) {
                el.timeSelect.prop('disabled', false);
                announceToScreenReader(`${availableCount} orari disponibili caricati`);
              } else {
                el.timeSelect.html('');
                el.timeSelect.append(new Option(rbfData.labels.noTime, ''));
                announceToScreenReader('Nessun orario disponibile per questa data');
              }
            } else {
              el.timeSelect.append(new Option(rbfData.labels.noTime, ''));
              announceToScreenReader('Nessun orario disponibile per questa data');
            }
          }
        } else {
          el.timeSelect.append(new Option(rbfData.labels.noTime, ''));
          announceToScreenReader('Nessun orario disponibile per questa data');
        }
      }).fail(function(xhr, status, error) {
        el.timeSelect.html('');

        // Enhanced error handling with specific messages
        let errorMessage = 'Errore nel caricamento degli orari. Riprova.';

        if (xhr.responseJSON && xhr.responseJSON.data) {
          const errorData = xhr.responseJSON.data;
          switch (errorData.code) {
            case 'meal_not_configured':
              errorMessage = 'Il servizio selezionato non è configurato. Contatta il ristorante.';
              break;
            case 'date_in_past':
              errorMessage = 'Non è possibile prenotare per date passate.';
              break;
            case 'missing_params':
              errorMessage = 'Parametri mancanti. Riprova selezionando data e pasto.';
              break;
            case 'meal_not_available':
              errorMessage = 'Il servizio non è disponibile per questa data.';
              break;
            default:
              errorMessage = errorData.message || errorMessage;
          }
        }

        el.timeSelect.append(new Option(rbfData.labels.noTime, ''));

        // Show user-friendly error message
        announceToScreenReader(errorMessage);

        // Log error for debugging (only if debug mode is enabled)
        if (rbfData.debug) {
          console.error('RBF Time Loading Error:', {
            status: status,
            error: error,
            response: xhr.responseJSON
          });
        }
      }).always(function() {
        clearTimeout(loadingTimeout);
        el.timeSelect.removeClass('rbf-loading');
        el.timeSelect.prop('disabled', false);

        if (preserveSelectedTime && previousSelection) {
          const optionExists = el.timeSelect.find(`option[value="${previousSelection}"]`).length > 0;
          if (optionExists) {
            el.timeSelect.val(previousSelection);
            selectionPreserved = true;
          } else {
            el.timeSelect.val('');
          }
        }

        if (rbfData.debug) {
          console.log('RBF: Time loading AJAX completed (always handler)');
        }

        resolve({ selectionPreserved });
      });
    });
  }

  /**
   * Handle date selection change
   */
  function onDateChange(selectedDates) {
    if (!selectedDates.length) {
      el.mealNotice.hide(); 
      return; 
    }
    
    // Enhanced validation: Check if a meal is selected first
    const selectedMeal = el.mealRadios.filter(':checked').val();
    if (!selectedMeal) {
      // Show helpful message and prevent date selection
      el.mealNotice.show().text('Seleziona prima un pasto per vedere gli orari disponibili.');
      announceToScreenReader('Seleziona prima un pasto per continuare.');
      
      // Reset the date selection visually but allow the user to see the message
      if (fp && fp.clear) {
        setTimeout(() => {
          fp.clear();
        }, 100);
      }
      return;
    }

    resetSteps(2);
    const date = selectedDates[0];
    const dateString = formatLocalISO(date);

    loadAvailableTimes({
      dateString,
      selectedMeal,
      preserveSelectedTime: false
    });
  }

  /**
   * Handle time selection change
   */
  el.timeSelect.on('change', function() {
    resetSteps(3);
    if (this.value) {
      showStepWithoutScroll(el.peopleStep, 4);
      el.peopleInput.val(1).attr('max', peopleMaxLimit);
      updatePeopleButtons(); // Update buttons without triggering input event
      // Don't trigger input event here - let user interact with people selector first
      
      // Initialize form tooltips when people step is shown
      setTimeout(initializeFormTooltips, 100);
    }
  });

  /**
   * Update people selector button states
   */
  function updatePeopleButtons() {
    let val = parseInt(el.peopleInput.val(), 10);

    if (Number.isNaN(val) || val < 1) {
      val = 1;
    }

    if (val > peopleMaxLimit) {
      val = peopleMaxLimit;
    }

    if (parseInt(el.peopleInput.val(), 10) !== val) {
      el.peopleInput.val(val);
    }

    el.peopleInput.attr('aria-valuenow', val);
    el.peopleInput.attr('aria-valuemax', peopleMaxLimit);
    el.peopleInput.attr('max', peopleMaxLimit);
    el.peopleMinus.prop('disabled', val <= 1);
    el.peoplePlus.prop('disabled', val >= peopleMaxLimit);
  }

  /**
   * People selector button handlers
   */
  el.peoplePlus.on('click', function() {
    let val = parseInt(el.peopleInput.val(), 10);

    if (Number.isNaN(val)) {
      val = 1;
    }

    if (val < peopleMaxLimit) {
      el.peopleInput.val(val + 1);
      updatePeopleButtons();
      el.peopleInput.trigger('input');
      // Show details step when user interacts with people selector
      showDetailsStepIfNeeded();
    }
  });

  el.peopleMinus.on('click', function() {
    let val = parseInt(el.peopleInput.val(), 10);

    if (Number.isNaN(val)) {
      val = 1;
    }

    if (val > 1) {
      el.peopleInput.val(val - 1);
      updatePeopleButtons();
      el.peopleInput.trigger('input');
      // Show details step when user interacts with people selector
      showDetailsStepIfNeeded();
    }
  });
  
  el.peopleInput.on('input', function() {
    const previousTimeSelection = el.timeSelect.val();
    updatePeopleButtons();

    const selectedMeal = el.mealRadios.filter(':checked').val();
    const dateInfo = getSelectedDateInfo();

    resetSteps(4);

    if (selectedMeal && dateInfo) {
      loadAvailableTimes({
        dateString: dateInfo.dateString,
        selectedMeal,
        preserveSelectedTime: Boolean(previousTimeSelection)
      }).then(result => {
        if (result.selectionPreserved && previousTimeSelection) {
          showDetailsStepIfNeeded();
        }
      });
    }
  });

  /**
   * Show details step after user interaction with people selector
   */
  function showDetailsStepIfNeeded() {
    const peopleVal = parseInt(el.peopleInput.val());
    if (peopleVal > 0) {
      // Small delay to let user see the people selection before showing details
      setTimeout(() => {
        showStepWithoutScroll(el.detailsStep, 5);
        el.detailsInputs.prop('disabled', false);
        el.privacyCheckbox.prop('disabled', false);
        el.marketingCheckbox.prop('disabled', false);
        el.submitButton.show().prop('disabled', true);
        initializeTelInput();
        // Check submit button state after showing it
        setTimeout(() => updateSubmitButtonState(), 100);
      }, 500); // 500ms delay to let users see the people section
    }
  }

  /**
   * Privacy checkbox handler
   */
  el.privacyCheckbox.on('change', function() {
    updateSubmitButtonState();
  });

  /**
   * Form submission handler with confirmation modal
   */
  form.on('submit', function(e) {
    e.preventDefault(); // Always prevent default submission first
    
    // Perform all validations first
    if (!el.privacyCheckbox.is(':checked')) {
      alert(rbfData.labels.privacyRequired);
      return;
    }
    
    if (iti) {
      // Validate phone number if intlTelInput is initialized
      if (!iti.isValidNumber()) {
        alert(rbfData.labels.invalidPhone);
        return;
      }
      
      // Set the full international number and country code
      const fullNumber = iti.getNumber();
      const countryData = iti.getSelectedCountryData();
      
      el.telInput.val(fullNumber);
      $('#rbf_country_code').val(countryData.iso2);
      
    } else {
      // Fallback: if intlTelInput is not initialized, default to Italy
      $('#rbf_country_code').val('it');
      
      // Basic phone validation as fallback
      const phoneValue = el.telInput.val().trim();
      if (!phoneValue || phoneValue.length < 6) {
        alert(rbfData.labels.invalidPhone);
        return;
      }
    }
    
    // All validations passed - show confirmation modal
    showBookingConfirmationModal();
  });

  /**
   * Show booking confirmation modal with summary
   */
  function showBookingConfirmationModal() {
    // Collect form data for display
    const formData = collectFormData();
    
    // Get meal name from selected radio button
    const selectedMealRadio = el.mealRadios.filter(':checked');
    let mealName = '';

    if (selectedMealRadio.length) {
      let mealLabel = selectedMealRadio.next('label');

      if (!mealLabel.length) {
        const radioId = selectedMealRadio.attr('id');
        if (radioId) {
          mealLabel = form.find(`label[for="${radioId}"]`);
        }
      }

      if (mealLabel.length) {
        mealName = mealLabel.text().trim();
      } else {
        const selectedValue = selectedMealRadio.val();
        mealName = selectedValue ? String(selectedValue).trim() : '';
      }
    }
    
    // Format data for display
    const customerName = `${form.find('#rbf-name').val()} ${form.find('#rbf-surname').val()}`;
    const formattedDate = formatDateForDisplay(formData.date);
    const notesText = formData.notes || rbfData.labels.noNotes;
    
    // Create modal HTML
    const modalHtml = `
      <div id="rbf-confirmation-modal" class="rbf-confirmation-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="rbf-confirmation-modal-content">
          <div class="rbf-confirmation-modal-header">
            <h3 id="modal-title">${rbfData.labels.confirmBookingTitle}</h3>
            <button class="rbf-confirmation-modal-close" aria-label="${rbfData.labels.cancel}" type="button">&times;</button>
          </div>
          <div class="rbf-confirmation-modal-body">
            <div class="rbf-confirmation-warning">
              <span class="rbf-confirmation-warning-icon">⚠️</span>
              <span>${rbfData.labels.confirmWarning}</span>
            </div>
            
            <div class="rbf-booking-summary">
              <h4>${rbfData.labels.bookingSummary}</h4>
              
              <div class="rbf-summary-item">
                <span class="rbf-summary-label">${rbfData.labels.meal}:</span>
                <span class="rbf-summary-value">${mealName}</span>
              </div>
              
              <div class="rbf-summary-item">
                <span class="rbf-summary-label">${rbfData.labels.date}:</span>
                <span class="rbf-summary-value">${formattedDate}</span>
              </div>
              
              <div class="rbf-summary-item">
                <span class="rbf-summary-label">${rbfData.labels.time}:</span>
                <span class="rbf-summary-value">${formData.time}</span>
              </div>
              
              <div class="rbf-summary-item">
                <span class="rbf-summary-label">${rbfData.labels.people}:</span>
                <span class="rbf-summary-value">${formData.people}</span>
              </div>
              
              <div class="rbf-summary-item">
                <span class="rbf-summary-label">${rbfData.labels.customer}:</span>
                <span class="rbf-summary-value">${customerName}</span>
              </div>
              
              <div class="rbf-summary-item">
                <span class="rbf-summary-label">${rbfData.labels.email}:</span>
                <span class="rbf-summary-value">${formData.email}</span>
              </div>
              
              <div class="rbf-summary-item">
                <span class="rbf-summary-label">${rbfData.labels.phone}:</span>
                <span class="rbf-summary-value">${el.telInput.val()}</span>
              </div>
              
              ${formData.notes ? `
                <div class="rbf-summary-item">
                  <span class="rbf-summary-label">${rbfData.labels.notes}:</span>
                  <span class="rbf-summary-value">${notesText}</span>
                </div>
              ` : ''}
            </div>
          </div>
          <div class="rbf-confirmation-modal-footer">
            <button type="button" class="rbf-btn rbf-btn-cancel" id="rbf-modal-cancel">
              ${rbfData.labels.cancel}
            </button>
            <button type="button" class="rbf-btn rbf-btn-confirm" id="rbf-modal-confirm">
              ${rbfData.labels.confirmBooking}
            </button>
          </div>
        </div>
      </div>
    `;
    
    // Add modal to page
    $('body').append(modalHtml);
    
    // Get modal elements
    const $modal = $('#rbf-confirmation-modal');
    const $modalContent = $modal.find('.rbf-confirmation-modal-content');
    
    // Show modal with animation
    setTimeout(() => {
      $modal.addClass('show');
      $modalContent.addClass('show');
    }, 10);
    
    // Focus management
    const $closeBtn = $modal.find('.rbf-confirmation-modal-close');
    const $cancelBtn = $('#rbf-modal-cancel');
    const $confirmBtn = $('#rbf-modal-confirm');
    
    // Store current focus to restore later
    const previousFocus = document.activeElement;
    
    // Focus first interactive element
    setTimeout(() => {
      $closeBtn.focus();
    }, 300);
    
    // Trap focus within modal
    const focusableElements = $modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    const firstElement = focusableElements.first();
    const lastElement = focusableElements.last();
    
    $modal.on('keydown', function(e) {
      if (e.key === 'Tab') {
        if (e.shiftKey) {
          // Shift + Tab
          if (document.activeElement === firstElement[0]) {
            e.preventDefault();
            lastElement.focus();
          }
        } else {
          // Tab
          if (document.activeElement === lastElement[0]) {
            e.preventDefault();
            firstElement.focus();
          }
        }
      } else if (e.key === 'Escape') {
        closeModal();
      }
    });
    
    // Bind modal events
    function closeModal() {
      $modal.removeClass('show');
      $modalContent.removeClass('show');
      
      setTimeout(() => {
        $modal.remove();
        // Restore focus
        if (previousFocus && previousFocus.focus) {
          previousFocus.focus();
        }
      }, 300);
    }
    
    // Close button and cancel button
    $closeBtn.add($cancelBtn).on('click', closeModal);
    
    // Click outside to close
    $modal.on('click', function(e) {
      if (e.target === this) {
        closeModal();
      }
    });
    
    // Confirm button - actually submit the form
    $confirmBtn.on('click', function() {
      // Disable confirm button and show loading
      $confirmBtn.addClass('loading').prop('disabled', true);
      $confirmBtn.text(rbfData.labels.submittingBooking);
      
      // Clear autosave data
      AutoSave.clear();
      rbfLog.log('Autosave data cleared on form submission');
      
      // Show loading state on form
      showComponentLoading(form[0], rbfData.labels.submittingBooking);
      
      // Actually submit the form
      form.off('submit'); // Remove our handler to avoid recursion
      form.submit(); // Submit the form normally
    });
  }

  /**
   * Format date for display in modal
   */
  function formatDateForDisplay(dateString) {
    if (!dateString) return '';
    
    try {
      const date = new Date(dateString);
      const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric'
      };
      
      return date.toLocaleDateString(rbfData.locale === 'it' ? 'it-IT' : 'en-US', options);
    } catch (e) {
      return dateString; // Fallback to original string
    }
  }

  /**
   * Add contextual tooltip to form elements
   */
  function addFormTooltip(element, tooltipText, options = {}) {
    if (!element || !tooltipText) return;
    
    const wrapper = document.createElement('div');
    wrapper.className = 'rbf-form-tooltip';
    
    // Replace element with wrapper containing element + tooltip
    element.parentNode.insertBefore(wrapper, element);
    wrapper.appendChild(element);
    
    const tooltip = document.createElement('div');
    tooltip.className = 'rbf-tooltip-content';
    tooltip.textContent = tooltipText;
    tooltip.setAttribute('role', 'tooltip');
    
    const tooltipId = 'rbf-form-tooltip-' + Math.random().toString(36).substr(2, 9);
    tooltip.id = tooltipId;
    element.setAttribute('aria-describedby', tooltipId);
    
    wrapper.appendChild(tooltip);
    
    // Add dynamic content updates if specified
    if (options.dynamicContent) {
      const updateTooltip = () => {
        const newText = options.dynamicContent(element);
        if (newText) tooltip.textContent = newText;
      };
      
      element.addEventListener('input', updateTooltip);
      element.addEventListener('change', updateTooltip);
    }
  }

  /**
   * Initialize form tooltips after elements are ready
   */
  function initializeFormTooltips() {
    // Time selection tooltip
    if (el.timeSelect.length) {
      addFormTooltip(el.timeSelect[0], rbfData.labels.timeTooltip || 'Seleziona il tuo orario preferito', {
        dynamicContent: (element) => {
          const selectedOption = element.options[element.selectedIndex];
          if (selectedOption && selectedOption.value) {
            return rbfData.labels.timeSelected?.replace('{time}', selectedOption.text) || 
                   `Orario selezionato: ${selectedOption.text}`;
          }
          return rbfData.labels.timeTooltip || 'Seleziona il tuo orario preferito';
        }
      });
    }
    
    // People count tooltip  
    if (el.peopleInput.length) {
      addFormTooltip(el.peopleInput[0], rbfData.labels.peopleTooltip || 'Numero di persone per la prenotazione', {
        dynamicContent: (element) => {
          const count = parseInt(element.value) || 1;
          const max = parseInt(element.getAttribute('max')) || 8;
          
          if (count === 1) {
            return rbfData.labels.singlePerson || 'Prenotazione per 1 persona';
          } else if (count >= max - 1) {
            return rbfData.labels.nearMaxPeople || `Prenotazione per ${count} persone (quasi al massimo)`;
          } else if (count >= 6) {
            return rbfData.labels.largePeople || `Prenotazione per ${count} persone (gruppo numeroso)`;
          } else {
            return rbfData.labels.multiplePeople?.replace('{count}', count) || 
                   `Prenotazione per ${count} persone`;
          }
        }
      });
    }
    
    // Phone number tooltip
    if (el.telInput.length) {
      addFormTooltip(el.telInput[0], rbfData.labels.phoneTooltip || 'Inserisci il tuo numero di telefono per confermare la prenotazione');
    }
    
    // Email tooltip
    const emailInput = el.detailsStep.find('input[type="email"]');
    if (emailInput.length) {
      addFormTooltip(emailInput[0], rbfData.labels.emailTooltip || 'Riceverai una email di conferma della prenotazione');
    }
    
    // Name tooltip
    const nameInput = el.detailsStep.find('input[name="rbf_name"]');
    if (nameInput.length) {
      addFormTooltip(nameInput[0], rbfData.labels.nameTooltip || 'Il nome del titolare della prenotazione');
    }
  }
  
  /**
   * Enhanced keyboard navigation system
   */
  function initializeKeyboardNavigation() {
    // Global keyboard handlers
    $(document).on('keydown', function(e) {
      // Escape key - universal close action
      if (e.key === 'Escape') {
        // Close any open tooltips
        $('.rbf-availability-tooltip').remove();
        // Remove focus from date picker if focused
        if (document.activeElement && document.activeElement.closest('.flatpickr-calendar')) {
          document.activeElement.blur();
        }
        // Close country dropdown if open
        if ($('.iti__country-list:visible').length) {
          if (iti) {
            iti.close();
          }
        }
      }
    });

    // Enhanced radio group navigation
    el.mealRadios.on('keydown', function(e) {
      const radios = el.mealRadios.get();
      const currentIndex = radios.indexOf(this);
      let newIndex = currentIndex;

      switch (e.key) {
        case 'ArrowRight':
        case 'ArrowDown':
          e.preventDefault();
          newIndex = (currentIndex + 1) % radios.length;
          break;
        case 'ArrowLeft':
        case 'ArrowUp':
          e.preventDefault();
          newIndex = (currentIndex - 1 + radios.length) % radios.length;
          break;
        case 'Home':
          e.preventDefault();
          newIndex = 0;
          break;
        case 'End':
          e.preventDefault();
          newIndex = radios.length - 1;
          break;
        case ' ':
        case 'Enter':
          e.preventDefault();
          $(this).prop('checked', true).trigger('change');
          announceToScreenReader(`Pasto selezionato: ${$(this).next('label').text()}`);
          return;
      }

      if (newIndex !== currentIndex) {
        $(radios[newIndex]).focus();
      }
    });

    // Enhanced people selector keyboard navigation
    el.peopleInput.on('keydown', function(e) {
      let currentVal = parseInt($(this).val(), 10);

      if (Number.isNaN(currentVal)) {
        currentVal = 1;
      }

      const maxVal = peopleMaxLimit;

      switch (e.key) {
        case 'ArrowUp':
        case '+':
          e.preventDefault();
          if (currentVal < maxVal) {
            el.peoplePlus.trigger('click');
          }
          break;
        case 'ArrowDown':
        case '-':
          e.preventDefault();
          if (currentVal > 1) {
            el.peopleMinus.trigger('click');
          }
          break;
        case 'Home':
          e.preventDefault();
          $(this).val(1).trigger('input');
          updatePeopleButtons();
          break;
        case 'End':
          e.preventDefault();
          $(this).val(maxVal).trigger('input');
          updatePeopleButtons();
          break;
      }
    });

    // Enhanced button keyboard navigation
    el.peopleMinus.add(el.peoplePlus).on('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        $(this).trigger('click');
      }
    });

    // Enhanced form field navigation
    const formFields = el.detailsInputs.add(el.timeSelect).add(el.privacyCheckbox).add(el.marketingCheckbox);
    
    formFields.on('keydown', function(e) {
      if (e.key === 'Tab') {
        // Let default tab behavior work, but announce field entry
        setTimeout(() => {
          const fieldLabel = $('label[for="' + this.id + '"]').text() || 
                          this.getAttribute('aria-label') || 
                          this.getAttribute('placeholder') || 
                          'Campo modulo';
          announceToScreenReader(`Campo: ${fieldLabel}`);
        }, 50);
      }
    });

    // Enhanced time selector keyboard navigation
    el.timeSelect.on('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        if (this.value) {
          e.preventDefault();
          announceToScreenReader(`Orario selezionato: ${this.options[this.selectedIndex].text}`);
        }
      }
    });

    // Calendar keyboard enhancements
    $(document).on('keydown', '.flatpickr-day[tabindex="0"]', function(e) {
      const day = $(this);
      const calendar = day.closest('.flatpickr-calendar');
      const days = calendar.find('.flatpickr-day[tabindex="0"]');
      const currentIndex = days.index(this);
      let newIndex = currentIndex;

      switch (e.key) {
        case 'ArrowRight':
          e.preventDefault();
          newIndex = Math.min(currentIndex + 1, days.length - 1);
          break;
        case 'ArrowLeft':
          e.preventDefault();
          newIndex = Math.max(currentIndex - 1, 0);
          break;
        case 'ArrowDown':
          e.preventDefault();
          newIndex = Math.min(currentIndex + 7, days.length - 1);
          break;
        case 'ArrowUp':
          e.preventDefault();
          newIndex = Math.max(currentIndex - 7, 0);
          break;
        case 'Home':
          e.preventDefault();
          newIndex = 0;
          break;
        case 'End':
          e.preventDefault();
          newIndex = days.length - 1;
          break;
        case 'Enter':
        case ' ':
          e.preventDefault();
          day.trigger('click');
          const dayText = day.text();
          announceToScreenReader(`Data selezionata: ${dayText}`);
          return;
        case 'PageUp':
          e.preventDefault();
          // Previous month
          if (fp) {
            fp.changeMonth(-1);
          }
          return;
        case 'PageDown':
          e.preventDefault();
          // Next month
          if (fp) {
            fp.changeMonth(1);
          }
          return;
      }

      if (newIndex !== currentIndex && days[newIndex]) {
        days[newIndex].focus();
        // Announce the new date
        const dayElement = days[newIndex];
        const dayText = $(dayElement).text();
        announceToScreenReader(`Giorno ${dayText}`);
      }
    });

    // Form submission keyboard shortcuts
    form.on('keydown', function(e) {
      // Ctrl+Enter to submit (if privacy is checked)
      if (e.ctrlKey && e.key === 'Enter') {
        if (el.privacyCheckbox.is(':checked') && !el.submitButton.is(':disabled')) {
          e.preventDefault();
          el.submitButton.trigger('click');
        }
      }
    });
  }

  /**
   * Enhanced focus management for dynamic content
   */
  function enhanceFocusManagement() {
    // Focus management when steps change
    const originalShowStep = showStep;
    window.showStep = function($step, stepNumber) {
      const result = originalShowStep.call(this, $step, stepNumber);
      
      // Set focus to first interactive element in new step
      setTimeout(() => {
        const firstFocusable = $step.find('input, select, button, [tabindex="0"]').first();
        if (firstFocusable.length) {
          firstFocusable.focus();
        }
      }, 350); // After step animation
      
      return result;
    };

    // Focus restoration for modals/dropdowns
    let lastFocusedElement = null;

    $(document).on('focus', 'input, select, button, [tabindex]', function() {
      if (!$(this).closest('.iti__country-list, .flatpickr-calendar').length) {
        lastFocusedElement = this;
      }
    });

    // Restore focus when closing dropdowns
    $(document).on('click', function(e) {
      if (!$(e.target).closest('.iti, .flatpickr-calendar').length && lastFocusedElement) {
        // Country dropdown closed
        if ($('.iti__country-list:visible').length === 0) {
          setTimeout(() => {
            if (lastFocusedElement && $(lastFocusedElement).is(':visible')) {
              lastFocusedElement.focus();
            }
          }, 100);
        }
      }
    });
  }

  /**
   * Enhanced ARIA live region announcements
   */
  function enhanceARIAAnnouncements() {
    // Create dedicated live region for status updates
    if (!$('#rbf-aria-status').length) {
      $('<div>')
        .attr({
          'id': 'rbf-aria-status',
          'aria-live': 'polite',
          'aria-atomic': 'true',
          'class': 'sr-only'
        })
        .appendTo('body');
    }

    // Create dedicated live region for alerts
    if (!$('#rbf-aria-alert').length) {
      $('<div>')
        .attr({
          'id': 'rbf-aria-alert',
          'aria-live': 'assertive',
          'aria-atomic': 'true',
          'class': 'sr-only'
        })
        .appendTo('body');
    }

    // Enhanced announcement function
    window.announceToScreenReader = function(message, isAlert = false) {
      const regionId = isAlert ? '#rbf-aria-alert' : '#rbf-aria-status';
      const $region = $(regionId);
      
      $region.text(message);
      
      // Clear after announcement to allow repeated messages
      setTimeout(() => {
        $region.empty();
      }, 1000);
    };

    // Announce form validation errors
    form.on('invalid', 'input, select, textarea', function(e) {
      e.preventDefault();
      const field = $(this);
      const label = $('label[for="' + this.id + '"]').text() || this.getAttribute('aria-label') || 'Campo';
      const validationMessage = this.validationMessage || 'Campo non valido';
      
      announceToScreenReader(`Errore in ${label}: ${validationMessage}`, true);
      
      // Focus the invalid field
      setTimeout(() => {
        this.focus();
      }, 100);
    });
  }

  // Initialize all keyboard navigation enhancements
  initializeKeyboardNavigation();
  enhanceFocusManagement();
  enhanceARIAAnnouncements();

  function enhanceMobileExperience() {
    // Smooth scrolling disabled to prevent anchor jumps
    if (window.innerWidth <= 768) {
      // Smooth scrolling behavior disabled to prevent unwanted anchor jumps
      // document.documentElement.style.scrollBehavior = 'smooth';
      
      // Improve people selector on mobile
      el.peopleMinus.add(el.peoplePlus).on('touchstart', function() {
        $(this).addClass('touch-active');
      }).on('touchend', function() {
        $(this).removeClass('touch-active');
      });
      
      // Improve radio button interaction on mobile
      el.mealRadios.parent('.rbf-radio-group').find('label').on('touchstart', function() {
        $(this).addClass('touch-active');
      }).on('touchend', function() {
        $(this).removeClass('touch-active');
      });
      
      // Handle viewport changes (orientation changes)
      let resizeTimer;
      $(window).on('resize orientationchange', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
          // Auto-scroll on resize disabled to prevent anchor jumps
          // Form remains visible and accessible without forced scrolling
        }, 100);
      });
    }
  }
  
  // Initialize mobile enhancements
  enhanceMobileExperience();

  /**
   * Inline validation functionality
   */
  const ValidationManager = {
    // Validation rules
    rules: {
      'rbf_meal': {
        required: true,
        validate: function(value) {
          if (!value) {
            return { valid: false, message: rbfData.labels.mealRequired || 'Seleziona un pasto per continuare.' };
          }
          return { valid: true };
        }
      },
      'rbf_data': {
        required: true,
        validate: function(value) {
          if (!value) {
            return { valid: false, message: rbfData.labels.dateRequired || 'Seleziona una data per continuare.' };
          }
          // Check if date is in the past
          const selectedDate = new Date(value);
          const today = new Date();
          today.setHours(0, 0, 0, 0);
          if (selectedDate < today) {
            return { valid: false, message: rbfData.labels.dateInPast || 'La data selezionata non può essere nel passato.' };
          }
          return { valid: true };
        }
      },
      'rbf_orario': {
        required: true,
        validate: function(value) {
          if (!value) {
            return { valid: false, message: rbfData.labels.timeRequired || 'Seleziona un orario per continuare.' };
          }
          return { valid: true };
        }
      },
      'rbf_persone': {
        required: true,
        validate: function(value) {
          const people = parseInt(value);
          if (!people || people < 1) {
            return { valid: false, message: rbfData.labels.peopleMinimum || 'Il numero di persone deve essere almeno 1.' };
          }
          if (people > peopleMaxLimit) {
            const message = rbfData.labels.peopleMaximum || ('Il numero di persone non può superare ' + peopleMaxLimit + '.');
            return { valid: false, message };
          }
          return { valid: true };
        }
      },
      'rbf_nome': {
        required: true,
        validate: function(value) {
          if (!value || value.trim().length < 2) {
            return { valid: false, message: rbfData.labels.nameRequired || 'Il nome deve contenere almeno 2 caratteri.' };
          }
          if (!/^[a-zA-ZÀ-ÿ\s\'-]+$/.test(value.trim())) {
            return { valid: false, message: rbfData.labels.nameInvalid || 'Il nome può contenere solo lettere, spazi, apostrofi e trattini.' };
          }
          return { valid: true };
        }
      },
      'rbf_cognome': {
        required: true,
        validate: function(value) {
          if (!value || value.trim().length < 2) {
            return { valid: false, message: rbfData.labels.surnameRequired || 'Il cognome deve contenere almeno 2 caratteri.' };
          }
          if (!/^[a-zA-ZÀ-ÿ\s\'-]+$/.test(value.trim())) {
            return { valid: false, message: rbfData.labels.surnameInvalid || 'Il cognome può contenere solo lettere, spazi, apostrofi e trattini.' };
          }
          return { valid: true };
        }
      },
      'rbf_email': {
        required: true,
        validate: function(value) {
          if (!value) {
            return { valid: false, message: rbfData.labels.emailRequired || 'L\'indirizzo email è obbligatorio.' };
          }
          const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          if (!emailRegex.test(value)) {
            return { valid: false, message: rbfData.labels.emailInvalid || 'Inserisci un indirizzo email valido.' };
          }
          return { valid: true };
        },
        asyncValidate: function(value) {
          // Check if email is already used for booking on the same date
          return new Promise((resolve) => {
            const selectedDate = el.dateInput.val();
            if (!selectedDate) {
              resolve({ valid: true });
              return;
            }
            
            // Simple check for common email providers format
            setTimeout(() => {
              if (value.includes('@example.') || value.includes('@test.')) {
                resolve({ valid: false, message: rbfData.labels.emailTest || 'Utilizza un indirizzo email reale per la prenotazione.' });
              } else {
                resolve({ valid: true });
              }
            }, 500);
          });
        }
      },
      'rbf_tel': {
        required: true,
        validate: function(value) {
          if (!value) {
            return { valid: false, message: rbfData.labels.phoneRequired || 'Il numero di telefono è obbligatorio.' };
          }
          // Remove all non-numeric characters for validation
          const cleaned = value.replace(/[^\d]/g, '');
          if (cleaned.length < 8) {
            return { valid: false, message: rbfData.labels.phoneMinLength || 'Il numero di telefono deve contenere almeno 8 cifre.' };
          }
          if (cleaned.length > 15) {
            return { valid: false, message: rbfData.labels.phoneMaxLength || 'Il numero di telefono non può superare 15 cifre.' };
          }
          return { valid: true };
        }
      },
      'rbf_privacy': {
        required: true,
        validate: function(value, element) {
          if (!element.checked) {
            return { valid: false, message: rbfData.labels.privacyRequired || 'Devi accettare la Privacy Policy per procedere.' };
          }
          return { valid: true };
        }
      }
    },

    // Show field error
    showFieldError: function(fieldName, message) {
      const errorElement = document.getElementById(fieldName + '-error');
      const field = document.getElementById(fieldName.replace('rbf_', 'rbf-'));
      
      if (errorElement) {
        errorElement.textContent = message;
        errorElement.classList.add('show');
      }
      
      if (field) {
        field.classList.remove('rbf-field-valid', 'rbf-field-validating');
        field.classList.add('rbf-field-invalid');
      }
    },

    // Show field success
    showFieldSuccess: function(fieldName) {
      const errorElement = document.getElementById(fieldName + '-error');
      const field = document.getElementById(fieldName.replace('rbf_', 'rbf-'));
      
      if (errorElement) {
        errorElement.classList.remove('show');
      }
      
      if (field) {
        field.classList.remove('rbf-field-invalid', 'rbf-field-validating');
        field.classList.add('rbf-field-valid');
      }
    },

    // Show field validating state
    showFieldValidating: function(fieldName) {
      const field = document.getElementById(fieldName.replace('rbf_', 'rbf-'));
      if (field) {
        field.classList.remove('rbf-field-invalid', 'rbf-field-valid');
        field.classList.add('rbf-field-validating');
      }
    },

    // Clear field validation state
    clearFieldValidation: function(fieldName) {
      const errorElement = document.getElementById(fieldName + '-error');
      const field = document.getElementById(fieldName.replace('rbf_', 'rbf-'));
      
      if (errorElement) {
        errorElement.classList.remove('show');
      }
      
      if (field) {
        field.classList.remove('rbf-field-invalid', 'rbf-field-valid', 'rbf-field-validating');
      }
    },

    // Validate a single field
    validateField: function(fieldName, value, element) {
      const rule = this.rules[fieldName];
      if (!rule) return true;

      // Synchronous validation
      const result = rule.validate(value, element);
      if (!result.valid) {
        this.showFieldError(fieldName, result.message);
        return false;
      }

      // Asynchronous validation if available
      if (rule.asyncValidate) {
        this.showFieldValidating(fieldName);
        rule.asyncValidate(value).then((asyncResult) => {
          if (!asyncResult.valid) {
            this.showFieldError(fieldName, asyncResult.message);
          } else {
            this.showFieldSuccess(fieldName);
          }
        }).catch(() => {
          this.showFieldSuccess(fieldName);
        });
      } else {
        this.showFieldSuccess(fieldName);
      }

      // Update submit button state after validation
      setTimeout(() => updateSubmitButtonState(), 100);

      return true;
    },

    // Initialize validation listeners
    init: function() {
      // Meal radio buttons validation
      el.mealRadios.on('change', function() {
        ValidationManager.validateField('rbf_meal', this.value, this);
        updateSubmitButtonState();
      });

      // Date validation
      el.dateInput.on('change', function() {
        ValidationManager.validateField('rbf_data', this.value, this);
        updateSubmitButtonState();
      });

      // Time validation
      el.timeSelect.on('change', function() {
        ValidationManager.validateField('rbf_orario', this.value, this);
        updateSubmitButtonState();
      });

      // People validation
      el.peopleInput.on('change input', function() {
        ValidationManager.validateField('rbf_persone', this.value, this);
        updateSubmitButtonState();
      });

      // Personal details validation
      ['rbf-name', 'rbf-surname', 'rbf-email', 'rbf-tel'].forEach(function(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
          const fieldName = fieldId.replace('-', '_');
          
          // Validate on blur
          field.addEventListener('blur', function() {
            if (this.value.trim()) {
              ValidationManager.validateField(fieldName, this.value, this);
            }
            updateSubmitButtonState();
          });

          // Clear validation on focus (give user a fresh start)
          field.addEventListener('focus', function() {
            ValidationManager.clearFieldValidation(fieldName);
          });

          // For email and phone, also validate on input with debounce
          if (fieldId === 'rbf-email' || fieldId === 'rbf-tel') {
            let timeout;
            field.addEventListener('input', function() {
              clearTimeout(timeout);
              if (this.value.length > 0) {
                timeout = setTimeout(() => {
                  ValidationManager.validateField(fieldName, this.value, this);
                  updateSubmitButtonState();
                }, 1000);
              }
            });
          }
        }
      });

      // Privacy checkbox validation
      el.privacyCheckbox.on('change', function() {
        ValidationManager.validateField('rbf_privacy', this.value, this);
        updateSubmitButtonState();
      });

      rbfLog.log('Validation manager initialized');
    }
  };

  // Initialize validation
  ValidationManager.init();

  /**
   * Comprehensive submit button validation function
   */
  function updateSubmitButtonState() {
    const submitButton = el.submitButton;
    if (!submitButton.length) return;

    // Check if privacy checkbox is checked (required)
    const privacyChecked = el.privacyCheckbox.is(':checked');
    if (!privacyChecked) {
      submitButton.prop('disabled', true);
      return;
    }

    // Check all required fields validation
    const allRequiredFieldsValid = Object.keys(ValidationManager.rules).every(fieldName => {
      const rule = ValidationManager.rules[fieldName];
      if (!rule.required) return true;

      // Get field value based on field type
      let value, element;
      switch(fieldName) {
        case 'rbf_meal':
          element = el.mealRadios.filter(':checked')[0];
          value = element ? element.value : '';
          break;
        case 'rbf_data':
          element = el.dateInput[0];
          value = el.dateInput.val();
          break;
        case 'rbf_orario':
          element = el.timeSelect[0];
          value = el.timeSelect.val();
          break;
        case 'rbf_persone':
          element = el.peopleInput[0];
          value = el.peopleInput.val();
          break;
        case 'rbf_nome':
          element = document.getElementById('rbf-name');
          value = element ? element.value : '';
          break;
        case 'rbf_cognome':
          element = document.getElementById('rbf-surname');
          value = element ? element.value : '';
          break;
        case 'rbf_email':
          element = document.getElementById('rbf-email');
          value = element ? element.value : '';
          break;
        case 'rbf_tel':
          element = document.getElementById('rbf-tel');
          value = element ? element.value : '';
          break;
        case 'rbf_privacy':
          element = el.privacyCheckbox[0];
          value = el.privacyCheckbox.is(':checked') ? 'on' : '';
          break;
        default:
          return true; // Unknown field, assume valid
      }

      if (!element) return true; // Field not found, assume valid

      // Run validation
      const result = rule.validate(value, element);
      return result.valid;
    });

    // Enable submit button only if all validations pass
    submitButton.prop('disabled', !allRequiredFieldsValid);
    
    rbfLog.log('Submit button state updated: ' + (allRequiredFieldsValid ? 'enabled' : 'disabled'));
  }

  /**
   * UTM parameters and click ID capture
   */
  (function() {
    const qs = new URLSearchParams(window.location.search);
    const get = k => qs.get(k) || '';
    const setVal = (id, val) => { 
      var el = document.getElementById(id); 
      if (el) el.value = val; 
    };

    setVal('rbf_utm_source', get('utm_source'));
    setVal('rbf_utm_medium', get('utm_medium'));
    setVal('rbf_utm_campaign', get('utm_campaign'));
    setVal('rbf_gclid', get('gclid'));
    setVal('rbf_fbclid', get('fbclid'));

    if (document.getElementById('rbf_referrer') && !document.getElementById('rbf_referrer').value) {
      document.getElementById('rbf_referrer').value = document.referrer || '';
    }
  })();

  // Enhanced calendar opening when date field gains focus or is clicked
  el.dateInput.on('focus click', () => {
    rbfLog.log('Date input focused/clicked - attempting to open calendar...');
    
    lazyLoadDatePicker().then(() => {
      // Wait longer for proper initialization before attempting to open
      setTimeout(() => {
        if (fp && typeof fp.open === 'function') {
          try {
            if (!fp.isOpen) {
              rbfLog.log('Opening Flatpickr calendar...');
              fp.open();
            } else {
              rbfLog.log('Calendar already open');
            }
          } catch (error) {
            rbfLog.error('Failed to open Flatpickr calendar: ' + error.message);
            // If Flatpickr fails, ensure HTML5 fallback is active
            if (el.dateInput.attr('type') !== 'date') {
              rbfLog.log('Switching to HTML5 date input fallback...');
              setupFallbackDateInput();
            }
          }
        } else {
          rbfLog.log('No Flatpickr instance available, HTML5 fallback should be active');
          // Ensure the fallback is properly set up
          if (el.dateInput.attr('type') !== 'date') {
            setupFallbackDateInput();
          }
        }
      }, 200); // Increased delay for better reliability
    }).catch(error => {
      rbfLog.error('lazyLoadDatePicker failed: ' + error.message);
      // Ensure fallback is set up even if promise fails
      if (el.dateInput.attr('type') !== 'date') {
        setupFallbackDateInput();
      }
    });
  });

  // Initialize autosave functionality
  initializeAutosave();
  
  // Restore autosave data on page load
  const savedData = AutoSave.load();
  if (savedData) {
    // Small delay to ensure DOM is fully ready
    setTimeout(function() {
      restoreFormData(savedData);
    }, 100);
  }

  /**
   * Display alternative booking suggestions when no times available
   */
  function displayAlternativeSuggestions(suggestions, message) {
    // Remove any existing suggestions display
    $('.rbf-suggestions-container').remove();
    
    if (!suggestions || suggestions.length === 0) {
      return;
    }
    
    // Create suggestions container
    const suggestionsHtml = `
      <div class="rbf-suggestions-container">
        <div class="rbf-suggestions-header">
          <h4>${message || rbfData.labels.alternativesTitle || 'Alternative disponibili'}</h4>
          <p>${rbfData.labels.alternativesSubtitle || 'Seleziona una delle alternative seguenti:'}</p>
        </div>
        <div class="rbf-suggestions-list">
          ${suggestions.map(suggestion => `
            <div class="rbf-suggestion-item" 
                 data-date="${suggestion.date}" 
                 data-meal="${suggestion.meal}" 
                 data-time="${suggestion.time}">
              <div class="rbf-suggestion-primary">
                <span class="rbf-suggestion-date">${suggestion.date_display}</span>
                <span class="rbf-suggestion-time">${suggestion.time_display}</span>
              </div>
              <div class="rbf-suggestion-secondary">
                <span class="rbf-suggestion-meal">${suggestion.meal_name}</span>
                <span class="rbf-suggestion-reason">${suggestion.reason}</span>
              </div>
              <div class="rbf-suggestion-capacity">
                ${suggestion.remaining_spots} ${rbfData.labels.spotsRemaining || 'posti rimasti'}
              </div>
            </div>
          `).join('')}
        </div>
      </div>
    `;
    
    // Insert suggestions after time select
    el.timeStep.append(suggestionsHtml);
    
    // Add click handlers for suggestions
    $('.rbf-suggestion-item').on('click', function() {
      const $suggestion = $(this);
      const date = $suggestion.data('date');
      const meal = $suggestion.data('meal');
      const time = $suggestion.data('time');
      
      // Update form with suggestion
      applySuggestion(date, meal, time);
    });
    
    // Add keyboard navigation for suggestions
    $('.rbf-suggestion-item').attr('tabindex', '0').on('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        $(this).click();
      }
    });
  }
  
  /**
   * Apply a selected suggestion to the form
   */
  function applySuggestion(date, meal, time) {
    // Update meal selection
    $('input[name="rbf_meal"][value="' + meal + '"]').prop('checked', true).trigger('change');

    const suggestionDate = new Date(date + 'T00:00:00');
    if (Number.isNaN(suggestionDate.getTime())) {
      rbfLog.warn('Suggerimento con data non valida ricevuto: ' + date);
      return;
    }

    // Update date on the appropriate calendar instance
    if (fp && typeof fp.setDate === 'function') {
      fp.setDate(date, true);
    } else if (window.rbfCalendarInstance && typeof window.rbfCalendarInstance.setDate === 'function') {
      window.rbfCalendarInstance.setDate(date, true);
    } else {
      // Ensure fallback mode is properly configured before simulating selection
      setupFallbackDateInput();
      el.dateInput.val(date);
      onDateChange([suggestionDate]);
    }

    // Ensure the underlying input value matches and trigger autosave flow
    if (el.dateInput.val() !== date) {
      el.dateInput.val(date);
    }
    if (typeof scheduleAutosave === 'function') {
      scheduleAutosave();
    }
    if (el.dateInput.length) {
      ValidationManager.validateField('rbf_data', el.dateInput.val(), el.dateInput[0]);
      updateSubmitButtonState();
    }

    const timeValue = meal + '|' + time;
    const maxAttempts = 10;
    const attemptDelay = 200;

    function attemptSelectSuggestedTime(attempt = 0) {
      const $option = el.timeSelect.find(`option[value="${timeValue}"]`);

      if ($option.length) {
        el.timeSelect.val(timeValue).trigger('change');

        $('.rbf-suggestions-container').fadeOut(300, function() {
          $(this).remove();
        });

        announceToScreenReader(`Applicata alternativa: ${time} per ${meal} il ${date}`);
        return;
      }

      if (attempt >= maxAttempts) {
        rbfLog.warn(`Impossibile selezionare automaticamente l'orario suggerito (${timeValue}) dopo ${maxAttempts} tentativi.`);
        return;
      }

      setTimeout(function() {
        attemptSelectSuggestedTime(attempt + 1);
      }, attemptDelay);
    }

    // Allow the calendar/onDateChange flow to fetch time slots before attempting selection
    setTimeout(function() {
      attemptSelectSuggestedTime();
    }, 400);
  }

  /**
   * GLOBAL CALENDAR MANAGEMENT FUNCTIONS
   * Exposed for debugging and manual control
   */
  
  // Expose calendar management functions globally for debugging
  window.rbfCalendar = {
    refresh: function() {
      rbfLog.log('🔄 Manual calendar refresh requested');
      if (fp) {
        try {
          fp.redraw();
          rbfLog.log('✅ Calendar refreshed successfully');
          return true;
        } catch (error) {
          rbfLog.error(`Manual refresh failed: ${error.message}`);
          return false;
        }
      } else {
        rbfLog.warn('No calendar instance to refresh');
        return false;
      }
    },
    
    reinitialize: function() {
      rbfLog.log('🔧 Manual calendar reinitialization requested');
      reinitializeCalendar();
    },
    
    // EMERGENCY: Force emergency mode
    activateEmergencyMode: function() {
      console.log('🚨 ACTIVATING EMERGENCY MODE - This will allow all dates');
      window.rbfForceEmergencyMode = true;
      reinitializeCalendar();
      console.log('✅ Emergency mode activated. All dates should now be available.');
    },
    
    // EMERGENCY: Disable all restrictions completely  
    disableAllRestrictions: function() {
      console.log('🚨 DISABLING ALL RESTRICTIONS - Calendar will allow every date');
      
      if (fp) {
        try {
          fp.destroy();
        } catch (e) {}
      }
      
      // Create super-simple calendar with no restrictions at all
      fp = flatpickr(el.dateInput[0], {
        altInput: true,
        altFormat: 'd-m-Y',
        dateFormat: 'Y-m-d',
        minDate: 'today',
        locale: (rbfData && rbfData.locale === 'it') ? 'it' : 'default',
        enableTime: false,
        noCalendar: false,
        // Position calendar relative to input field instead of bottom of page
        static: true,
        // NO DISABLE FUNCTION - all dates allowed
        onChange: onDateChange,
        onReady: function() {
          console.log('✅ UNRESTRICTED calendar created - ALL dates available');
        }
      });
      
      console.log('✅ All restrictions disabled. Every date should now be selectable.');
    },
    
    getInstance: function() {
      return fp;
    },
    
    testDate: function(dateStr) {
      if (!dateStr) {
        rbfLog.error('Please provide a date string (YYYY-MM-DD)');
        return;
      }
      
      try {
        const testDate = new Date(dateStr);
        const isDisabled = isDateDisabled(testDate);
        rbfLog.log(`📅 Test date ${dateStr}: ${isDisabled ? '❌ DISABLED' : '✅ ALLOWED'}`);
        return !isDisabled;
      } catch (error) {
        rbfLog.error(`Error testing date: ${error.message}`);
        return false;
      }
    },
    
    debugData: function() {
      rbfLog.log('🔍 Current rbfData:', rbfData);
      return rbfData;
    },
    
    getCurrentSelection: function() {
      const meal = el.mealRadios.filter(':checked').val();
      const date = el.dateInput.val();
      const time = el.timeSelect.val();
      const people = el.peopleInput.val();
      
      rbfLog.log('📋 Current form state:', { meal, date, time, people });
      return { meal, date, time, people };
    },
    
    // EMERGENCY: Force calendar interactivity 
    forceInteractivity: function() {
      rbfLog.log('🚨 EMERGENCY: Forcing calendar interactivity...');
      
      if (fp && fp.calendarContainer) {
        forceCalendarInteractivity(fp);
        rbfLog.log('✅ Calendar interactivity forced');
        return true;
      } else {
        rbfLog.log('❌ No calendar instance found');
        return false;
      }
    },
    
    // EMERGENCY: Clear all loading states
    clearLoadingStates: function() {
      rbfLog.log('🧹 EMERGENCY: Clearing all loading states...');
      
      // Remove loading classes from all elements
      document.querySelectorAll('.rbf-component-loading, .rbf-loading').forEach(el => {
        el.classList.remove('rbf-component-loading', 'rbf-loading');
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
      });
      
      // Remove loading overlays
      document.querySelectorAll('.rbf-loading-overlay').forEach(el => el.remove());
      
      // Fix skeleton states
      document.querySelectorAll('[data-skeleton="true"]').forEach(el => {
        el.setAttribute('data-skeleton', 'false');
        el.style.pointerEvents = 'auto';
      });
      
      rbfLog.log('✅ Loading states cleared');
    },
    
    // EMERGENCY: Nuclear option - fix everything
    emergencyFix: function() {
      rbfLog.log('🚨 EMERGENCY: Applying nuclear fix...');
      
      // Clear loading states
      this.clearLoadingStates();
      
      // Force calendar interactivity
      if (fp) {
        this.forceInteractivity();
        
        // Extra aggressive fix
        setTimeout(() => {
          if (fp && fp.calendarContainer) {
            const calendar = fp.calendarContainer;
            calendar.style.pointerEvents = 'auto !important';
            calendar.style.opacity = '1 !important';
            calendar.style.zIndex = '9999';
            
            calendar.querySelectorAll('.flatpickr-day').forEach(day => {
              if (!day.classList.contains('flatpickr-disabled')) {
                day.style.pointerEvents = 'auto !important';
                day.style.cursor = 'pointer !important';
                day.style.backgroundColor = '';
                day.style.color = '';
              }
            });
          }
        }, 100);
      }
      
      // Activate emergency mode
      window.rbfForceEmergencyMode = true;
      window.rbfEmergencyMode = true;
      
      rbfLog.log('🚨 EMERGENCY FIX APPLIED - Calendar should now be interactive');
      rbfLog.log('📋 Try clicking on calendar dates now');
      
      return 'Emergency fix applied. Try clicking calendar dates now.';
    }
  };

  // Add a console helper for users (only in debug mode)
  if (isDebugMode || (typeof WP_DEBUG !== 'undefined' && WP_DEBUG)) {
    console.log('🎯 RBF Calendar Debug Tools Available:');
    console.log('  - rbfCalendar.refresh() - Refresh the calendar');
    console.log('  - rbfCalendar.reinitialize() - Completely reinitialize the calendar');  
    console.log('  - rbfCalendar.testDate("2024-12-25") - Test if a date is allowed');
    console.log('  - rbfCalendar.debugData() - Show current configuration data');
    console.log('  - rbfCalendar.getCurrentSelection() - Show current form state');
    console.log('');
    console.log('🚨 EMERGENCY TOOLS (if calendar dates are disabled/not clickable):');
    console.log('  - rbfCalendar.emergencyFix() - Apply all fixes at once');
    console.log('  - rbfCalendar.forceInteractivity() - Force calendar to be clickable');
    console.log('  - rbfCalendar.clearLoadingStates() - Remove blocking loading states');
    console.log('  - rbfCalendar.activateEmergencyMode() - Switch to emergency mode');
    console.log('  - rbfCalendar.disableAllRestrictions() - Remove ALL restrictions');
  }
  
  // ONLY show emergency commands if calendar seems broken and debug is enabled
  setTimeout(() => {
    if (isDebugMode && (window.rbfEmergencyMode || (window.rbfDisabledCount > 10 && window.rbfTotalCount > 0))) {
      console.log('');
      console.log('🚨 RBF EMERGENCY: Calendar dates may be disabled. Try these commands:');
      console.log('  rbfCalendar.emergencyFix() - Apply all fixes at once');
      console.log('  rbfCalendar.forceInteractivity() - Force calendar interactivity');
      console.log('  rbfCalendar.clearLoadingStates() - Clear loading states');
    }
  }, 5000);

} // End of initializeBookingForm function
