/**
 * Frontend JavaScript for Restaurant Booking Plugin
 */

jQuery(function($) {
  'use strict';
  
  // Check if essential data is loaded
  if (typeof rbfData === 'undefined') {
    console.error('rbfData not loaded - booking form cannot function');
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
        console.warn('Autosave failed:', e);
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
        console.warn('Autosave load failed:', e);
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
        console.warn('Autosave clear failed:', e);
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
    
    console.log('Form data restored from autosave');
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
          console.log('Form data auto-saved');
        }
      }
    }, AUTOSAVE_DELAY);
  }

  /**
   * Initialize autosave event listeners
   */
  function initializeAutosave() {
    if (!AutoSave.isSupported()) {
      console.warn('localStorage not supported - autosave disabled');
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
    
    console.log('Autosave initialized');
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
          console.warn('Could not load flatpickr, falling back to basic date input');
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
      }
    };
    
    if (rbfData.maxAdvanceMinutes > 0) {
      flatpickrConfig.maxDate = new Date(new Date().getTime() + rbfData.maxAdvanceMinutes * 60 * 1000);
    }
    
    fp = flatpickr(el.dateInput[0], flatpickrConfig);
    
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
          console.warn('Could not load intl-tel-input, using fallback telephone input');
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
      
      if (response.success && response.data.length > 0) {
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
   * Form submission handler with loading states
   */
  form.on('submit', function(e) {
    // Show loading state immediately
    showComponentLoading(form[0], 'Invio prenotazione in corso...');
    el.submitButton.prop('disabled', true);
    
    if (!el.privacyCheckbox.is(':checked')) {
      e.preventDefault();
      hideComponentLoading(form[0]);
      alert(rbfData.labels.privacyRequired);
      el.submitButton.prop('disabled', false);
      return;
    }
    
    if (iti) {
      // Validate phone number if intlTelInput is initialized
      if (!iti.isValidNumber()) {
        e.preventDefault();
        hideComponentLoading(form[0]);
        alert(rbfData.labels.invalidPhone);
        el.submitButton.prop('disabled', false);
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
        e.preventDefault();
        hideComponentLoading(form[0]);
        alert(rbfData.labels.invalidPhone);
        el.submitButton.prop('disabled', false);
        return;
      }
    }
    
    // Form validation complete, proceeding with submission
    // Clear autosave data on successful submission
    AutoSave.clear();
    console.log('Autosave data cleared on form submission');
    
    // Loading state will be cleared by page navigation or success message
  });

  /**
   * Mobile enhancement functions
   */
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

});