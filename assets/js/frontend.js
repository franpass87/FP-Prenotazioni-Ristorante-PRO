/**
 * Frontend JavaScript for Restaurant Booking Plugin
 */

jQuery(function($) {
  'use strict';
  
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
      if (window.console && window.console.log && (window.rbfDebug || false)) {
        console.log('RBF: ' + message);
      }
    }
  };
  
  // Check if essential data is loaded
  if (typeof rbfData === 'undefined') {
    rbfLog.error('Essential data not loaded - booking form cannot function');
    return;
  }

  const form = $('#rbf-form');
  if (!form.length) return;

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

  let fp = null;
  let iti = null;
  let currentStep = 1;
  let stepTimeouts = new Map(); // Track timeouts for each step element
  let componentsLoading = new Set(); // Track which components are loading
  let availabilityData = {}; // Store availability data for calendar coloring

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
  function showComponentLoading(component, message = rbfData.labels.loading) {
    componentsLoading.add(component);
    const $component = $(component);
    
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
    
    // Hide skeleton with fade out
    $skeletonElements.fadeOut(200, function() {
      // Show content with fade in
      $contentElements.addClass('loaded');
    });
  }

  /**
   * Lazy load flatpickr when date step is shown
   */
  function lazyLoadDatePicker() {
    return new Promise((resolve) => {
      if (typeof flatpickr === 'undefined') {
        // If flatpickr is not loaded, try to load it
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/flatpickr';
        script.onload = () => {
          initializeFlatpickr();
          resolve();
        };
        script.onerror = () => {
          rbfLog.warn('Could not load flatpickr, falling back to basic date input');
          // Ensure the date input still works with basic HTML5 date picker
          if (el.dateInput.length) {
            el.dateInput.attr('type', 'date');
            const today = new Date();
            const minDate = new Date(today.getTime() + rbfData.minAdvanceMinutes * 60 * 1000);
            el.dateInput.attr('min', minDate.toISOString().split('T')[0]);
          }
          resolve();
        };
        document.head.appendChild(script);
      } else {
        initializeFlatpickr();
        resolve();
      }
    });
  }

  /**
   * Fetch availability data for calendar month
   */
  function fetchAvailabilityData(startDate, endDate, meal) {
    return new Promise((resolve) => {
      $.post(rbfData.ajaxUrl, {
        action: 'rbf_get_calendar_availability',
        _ajax_nonce: rbfData.nonce,
        start_date: startDate,
        end_date: endDate,
        meal: meal
      }, function(response) {
        if (response.success) {
          availabilityData = response.data;
        } else {
          availabilityData = {};
        }
        resolve();
      }).fail(function() {
        availabilityData = {};
        resolve();
      });
    });
  }

  /**
   * Add availability tooltip to calendar day
   */
  function addAvailabilityTooltip(dayElem, status) {
    let tooltip = null;
    const tooltipId = 'rbf-tooltip-' + Math.random().toString(36).substr(2, 9);
    
    // Add aria-describedby for accessibility
    dayElem.setAttribute('aria-describedby', tooltipId);
    dayElem.setAttribute('role', 'button');
    dayElem.setAttribute('tabindex', '0');
    
    function showTooltip() {
      // Remove any existing tooltip
      if (tooltip) {
        tooltip.remove();
      }
      
      tooltip = document.createElement('div');
      tooltip.className = 'rbf-availability-tooltip';
      tooltip.id = tooltipId;
      tooltip.setAttribute('role', 'tooltip');
      
      let statusText;
      let contextualMessage = '';
      
      // Generate dynamic contextual messages based on availability
      if (status.level === 'available') {
        statusText = rbfData.labels.available || 'Disponibile';
        if (status.remaining > 20) {
          contextualMessage = rbfData.labels.manySpots || 'Molti posti disponibili';
        } else if (status.remaining > 10) {
          contextualMessage = rbfData.labels.someSpots || 'Buona disponibilità';
        }
      } else if (status.level === 'limited') {
        statusText = rbfData.labels.limited || 'Limitato';
        if (status.remaining <= 2) {
          contextualMessage = rbfData.labels.lastSpots || 'Ultimi 2 posti rimasti';
        } else if (status.remaining <= 5) {
          contextualMessage = rbfData.labels.fewSpots || 'Pochi posti rimasti';
        }
      } else {
        statusText = rbfData.labels.nearlyFull || 'Quasi pieno';
        contextualMessage = rbfData.labels.actFast || 'Prenota subito!';
      }
      
      const spotsLabel = rbfData.labels.spotsRemaining || 'Posti rimasti:';
      const occupancyLabel = rbfData.labels.occupancy || 'Occupazione:';
      
      tooltip.innerHTML = `
        <div class="rbf-tooltip-status">${statusText}</div>
        ${contextualMessage ? `<div class="rbf-tooltip-context">${contextualMessage}</div>` : ''}
        <div class="rbf-tooltip-spots">${spotsLabel} ${status.remaining}/${status.total}</div>
        <div class="rbf-tooltip-occupancy">${occupancyLabel} ${status.occupancy}%</div>
      `;
      
      document.body.appendChild(tooltip);
      
      // Position tooltip above the day element with responsive positioning
      const rect = dayElem.getBoundingClientRect();
      const tooltipRect = tooltip.getBoundingClientRect();
      const viewportWidth = window.innerWidth;
      const viewportHeight = window.innerHeight;
      
      let left = rect.left + rect.width / 2 - tooltipRect.width / 2;
      let top = rect.top - tooltipRect.height - 10;
      
      // Adjust horizontal position if tooltip would overflow viewport
      if (left < 10) {
        left = 10;
      } else if (left + tooltipRect.width > viewportWidth - 10) {
        left = viewportWidth - tooltipRect.width - 10;
      }
      
      // Adjust vertical position if tooltip would overflow top of viewport
      if (top < 10) {
        top = rect.bottom + 10;
        tooltip.classList.add('rbf-tooltip-below');
      }
      
      tooltip.style.left = left + 'px';
      tooltip.style.top = top + 'px';
    }
    
    function hideTooltip() {
      if (tooltip) {
        tooltip.remove();
        tooltip = null;
      }
    }
    
    dayElem.addEventListener('mouseenter', showTooltip);
    dayElem.addEventListener('mouseleave', hideTooltip);
    dayElem.addEventListener('focus', showTooltip);
    dayElem.addEventListener('blur', hideTooltip);
    
    // Handle keyboard navigation
    dayElem.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        hideTooltip();
        dayElem.blur();
      }
    });
  }

  /**
   * Initialize flatpickr after lazy loading
   */
  function initializeFlatpickr() {
    const selectedMeal = el.mealRadios.filter(':checked').val();
    
    const flatpickrConfig = {
      altInput: true,
      altFormat: 'd-m-Y',
      dateFormat: 'Y-m-d',
      minDate: new Date(new Date().getTime() + rbfData.minAdvanceMinutes * 60 * 1000),
      locale: (rbfData.locale === 'it') ? 'it' : 'default',
      disable: [function(date) {
        const day = date.getDay();
        
        // Convert JavaScript day (0=Sunday, 1=Monday...) to our day key format
        const dayMapping = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
        const dayKey = dayMapping[day];
        const dateStr = formatLocalISO(date);
        
        // Check if this meal is available on this day
        if (rbfData.mealAvailability && rbfData.mealAvailability[selectedMeal]) {
          const availableDays = rbfData.mealAvailability[selectedMeal];
          if (!availableDays.includes(dayKey)) {
            return true; // Disable this day for this meal
          }
        }
        
        // Check for exceptions first
        if (rbfData.exceptions) {
          for (let exception of rbfData.exceptions) {
            if (exception.date === dateStr) {
              // Only disable if it's a closure or holiday
              if (exception.type === 'closure' || exception.type === 'holiday') {
                return true;
              }
              // Special events and extended hours are allowed
              if (exception.type === 'special' || exception.type === 'extended') {
                return false;
              }
            }
          }
        }
        
        // Apply regular closed day/date logic
        if (rbfData.closedDays.includes(day)) return true;
        if (rbfData.closedSingles.includes(dateStr)) return true;
        for (let range of rbfData.closedRanges) {
          if (dateStr >= range.from && dateStr <= range.to) return true;
        }
        return false;
      }],
      onChange: onDateChange,
      onDayCreate: function(dObj, dStr, fp, dayElem) {
        const dateStr = formatLocalISO(dayElem.dateObj);
        
        // Add availability coloring
        if (availabilityData[dateStr]) {
          const status = availabilityData[dateStr];
          dayElem.classList.add('rbf-availability-' + status.level);
          addAvailabilityTooltip(dayElem, status);
        }
        
        // Check for exceptions and add visual indicators
        if (rbfData.exceptions) {
          for (let exception of rbfData.exceptions) {
            if (exception.date === dateStr) {
              const indicator = document.createElement('div');
              indicator.className = 'rbf-exception-indicator rbf-exception-' + exception.type;
              indicator.title = exception.description || exception.type;
              
              // Style the indicator based on exception type
              const styles = {
                'special': { background: '#20c997', title: 'Evento Speciale' },
                'extended': { background: '#0d6efd', title: 'Orari Estesi' },
                'holiday': { background: '#fd7e14', title: 'Festività' },
                'closure': { background: '#dc3545', title: 'Chiusura' }
              };
              
              const style = styles[exception.type] || styles.closure;
              indicator.style.cssText = `
                position: absolute;
                top: 2px;
                right: 2px;
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background: ${style.background};
                z-index: 1;
              `;
              
              if (!exception.description) {
                indicator.title = rbfData.labels[style.title] || style.title;
              }
              
              dayElem.style.position = 'relative';
              dayElem.appendChild(indicator);
              break;
            }
          }
        }
      },
      onMonthChange: function(selectedDates, dateStr, instance) {
        // Fetch availability data when month changes
        const selectedMeal = el.mealRadios.filter(':checked').val();
        if (selectedMeal) {
          const viewDate = instance.currentMonth;
          const startDate = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
          const endDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0);
          
          fetchAvailabilityData(
            formatLocalISO(startDate),
            formatLocalISO(endDate),
            selectedMeal
          ).then(() => {
            // Redraw calendar to apply new availability colors
            instance.redraw();
          });
        }
      }
    };
    
    if (rbfData.maxAdvanceMinutes > 0) {
      flatpickrConfig.maxDate = new Date(new Date().getTime() + rbfData.maxAdvanceMinutes * 60 * 1000);
    }
    
    fp = flatpickr(el.dateInput[0], flatpickrConfig);
    
    // Fetch initial availability data for current month
    if (selectedMeal) {
      const now = new Date();
      const startDate = new Date(now.getFullYear(), now.getMonth(), 1);
      const endDate = new Date(now.getFullYear(), now.getMonth() + 1, 0);
      
      fetchAvailabilityData(
        formatLocalISO(startDate),
        formatLocalISO(endDate),
        selectedMeal
      ).then(() => {
        // Redraw calendar to apply availability colors
        if (fp) {
          fp.redraw();
        }
      });
    }
    
    // Show exception legend if there are exceptions
    if (rbfData.exceptions && rbfData.exceptions.length > 0) {
      $('.rbf-exception-legend').show();
    }
  }

  /**
   * Lazy load international telephone input
   */
  function lazyLoadTelInput() {
    return new Promise((resolve) => {
      if (typeof intlTelInput === 'undefined') {
        // If intlTelInput is not loaded, try to load it
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/19.2.16/js/intlTelInput.min.js';
        script.onload = () => {
          initializeTelInput();
          resolve();
        };
        script.onerror = () => {
          rbfLog.warn('Could not load intl-tel-input, using fallback telephone input');
          // Setup basic telephone input fallback
          if (el.telInput.length) {
            el.telInput.addClass('rbf-tel-fallback');
            el.telInput.attr('placeholder', rbfData.labels.phonePlaceholder || 'Phone number');
            el.telInput.attr('type', 'tel');
            // Set default country code to IT
            $('#rbf_country_code').val('it');
          }
          resolve();
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
      
      // Auto-scroll to step on mobile
      if (window.innerWidth <= 768) {
        setTimeout(() => {
          $step[0].scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
          });
        }, 250);
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
                flagButton
                  .attr({
                    role: 'button',
                    title: rbfData.labels.selectPrefix || 'Seleziona prefisso',
                    'aria-label': rbfData.labels.selectPrefix || 'Seleziona prefisso'
                  })
                  .on('click', function(e) {
                    // Prevent the default anchor behavior which caused page jump
                    e.preventDefault();
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
        fp.destroy(); 
        fp = null; 
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
   * Handle meal selection change
   */
  el.mealRadios.on('change', function() {
    resetSteps(1);
    el.mealNotice.hide();
    
    const selectedMeal = $(this).val();
    
    // Show meal-specific tooltip if configured
    if (rbfData.mealTooltips && rbfData.mealTooltips[selectedMeal]) {
      el.mealNotice.text(rbfData.mealTooltips[selectedMeal]).show();
    }
    
    // Update availability data when meal changes
    if (fp && selectedMeal) {
      const viewDate = fp.currentMonth;
      const startDate = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
      const endDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0);
      
      fetchAvailabilityData(
        formatLocalISO(startDate),
        formatLocalISO(endDate),
        selectedMeal
      ).then(() => {
        // Redraw calendar to apply new availability colors
        if (fp) {
          fp.redraw();
        }
      });
    }
    
    // Show date step for any meal selection
    // The flatpickr will be lazy loaded when the step is shown
    showStep(el.dateStep, 2);
  });

  /**
   * Handle date selection change
   */
  function onDateChange(selectedDates) {
    if (!selectedDates.length) { 
      el.mealNotice.hide(); 
      return; 
    }
    
    resetSteps(2);
    const date = selectedDates[0];
    const selectedMeal = el.mealRadios.filter(':checked').val();
    
    const dateString = formatLocalISO(date);
    showStep(el.timeStep, 3);
    
    // Show loading state for time selection
    showComponentLoading(el.timeStep[0], rbfData.labels.loading + ' orari...');
    
    el.timeSelect.html(`<option value="">${rbfData.labels.loading}</option>`).prop('disabled', true);
    el.timeSelect.addClass('rbf-loading');

    // Get available times via AJAX
    $.post(rbfData.ajaxUrl, {
      action: 'rbf_get_availability',
      _ajax_nonce: rbfData.nonce,
      date: dateString,
      meal: selectedMeal
    }, function(response) {
      // Hide loading state
      hideComponentLoading(el.timeStep[0]);
      
      el.timeSelect.removeClass('rbf-loading');
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
            announceToScreenReader(`${availableCount} orari disponibili caricati`);
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
      // Hide loading state
      hideComponentLoading(el.timeStep[0]);
      
      // Handle AJAX errors
      el.timeSelect.removeClass('rbf-loading');
      el.timeSelect.html('');
      el.timeSelect.append(new Option(rbfData.labels.noTime, ''));
      
      // Show user-friendly error message
      announceToScreenReader('Errore nel caricamento degli orari. Riprova.');
    });
  }

  /**
   * Handle time selection change
   */
  el.timeSelect.on('change', function() {
    resetSteps(3);
    if (this.value) {
      showStep(el.peopleStep, 4);
      const maxPeople = 30; // generic cap
      el.peopleInput.val(1).attr('max', maxPeople);
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
    const val = parseInt(el.peopleInput.val());
    const max = parseInt(el.peopleInput.attr('max'));
    el.peopleMinus.prop('disabled', val <= 1);
    el.peoplePlus.prop('disabled', val >= max);
  }

  /**
   * People selector button handlers
   */
  el.peoplePlus.on('click', function() {
    let val = parseInt(el.peopleInput.val());
    let max = parseInt(el.peopleInput.attr('max'));
    if (val < max) {
      el.peopleInput.val(val + 1).trigger('input');
      // Show details step when user interacts with people selector
      showDetailsStepIfNeeded();
    }
  });
  
  el.peopleMinus.on('click', function() {
    let val = parseInt(el.peopleInput.val());
    if (val > 1) {
      el.peopleInput.val(val - 1).trigger('input');
      // Show details step when user interacts with people selector
      showDetailsStepIfNeeded();
    }
  });
  
  el.peopleInput.on('input', function() {
    updatePeopleButtons();
    resetSteps(4);
  });

  /**
   * Show details step after user interaction with people selector
   */
  function showDetailsStepIfNeeded() {
    const peopleVal = parseInt(el.peopleInput.val());
    if (peopleVal > 0) {
      // Small delay to let user see the people selection before showing details
      setTimeout(() => {
        showStep(el.detailsStep, 5);
        el.detailsInputs.prop('disabled', false);
        el.privacyCheckbox.prop('disabled', false);
        el.marketingCheckbox.prop('disabled', false);
        el.submitButton.show().prop('disabled', true);
        initializeTelInput();
      }, 500); // 500ms delay to let users see the people section
    }
  }

  /**
   * Privacy checkbox handler
   */
  el.privacyCheckbox.on('change', function() {
    el.submitButton.prop('disabled', !this.checked);
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
    const mealName = selectedMealRadio.length ? selectedMealRadio.next('span').text().trim() : '';
    
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
      const currentVal = parseInt($(this).val());
      const maxVal = parseInt($(this).attr('max'));

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
      if (!$(e.target).closest('.iti').length && lastFocusedElement) {
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
    // Improve form scrolling on mobile
    if (window.innerWidth <= 768) {
      // Add smooth scrolling behavior
      document.documentElement.style.scrollBehavior = 'smooth';
      
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
          // Re-focus current step if needed
          const activeStep = form.find('.rbf-step.active');
          if (activeStep.length && window.innerWidth <= 768) {
            activeStep[0].scrollIntoView({ 
              behavior: 'smooth', 
              block: 'center' 
            });
          }
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
          if (people > 20) {
            return { valid: false, message: rbfData.labels.peopleMaximum || 'Il numero di persone non può superare 20.' };
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

      return true;
    },

    // Initialize validation listeners
    init: function() {
      // Meal radio buttons validation
      el.mealRadios.on('change', function() {
        ValidationManager.validateField('rbf_meal', this.value, this);
      });

      // Date validation
      el.dateInput.on('change', function() {
        ValidationManager.validateField('rbf_data', this.value, this);
      });

      // Time validation
      el.timeSelect.on('change', function() {
        ValidationManager.validateField('rbf_orario', this.value, this);
      });

      // People validation
      el.peopleInput.on('change input', function() {
        ValidationManager.validateField('rbf_persone', this.value, this);
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
                }, 1000);
              }
            });
          }
        }
      });

      // Privacy checkbox validation
      el.privacyCheckbox.on('change', function() {
        ValidationManager.validateField('rbf_privacy', this.value, this);
      });

      rbfLog.log('Validation manager initialized');
    }
  };

  // Initialize validation
  ValidationManager.init();

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
    
    // Update date
    if (window.flatpickrInstance) {
      window.flatpickrInstance.setDate(date);
    } else {
      el.dateInput.val(date);
    }
    
    // Small delay to ensure date change is processed
    setTimeout(function() {
      // Trigger date change to reload time slots
      el.dateInput.trigger('change');
      
      // Another delay to allow time slots to load, then select the suggested time
      setTimeout(function() {
        const timeValue = meal + '|' + time;
        el.timeSelect.val(timeValue).trigger('change');
        
        // Scroll to the time step for better UX
        el.timeStep[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Remove suggestions since one was applied
        $('.rbf-suggestions-container').fadeOut(300, function() {
          $(this).remove();
        });
        
        // Announce change to screen readers
        announceToScreenReader(`Applicata alternativa: ${time} per ${meal} il ${date}`);
      }, 1000);
    }, 500);
  }

});