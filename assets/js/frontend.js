/**
 * Frontend JavaScript for Restaurant Booking Plugin
 */

jQuery(function($) {
  'use strict';
  
  // Check if required dependencies are loaded
  if (typeof rbfData === 'undefined' || typeof flatpickr === 'undefined' || typeof intlTelInput === 'undefined') return;

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
   * Show step with animation and accessibility
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
      
      // Focus management for screen readers
      const firstInput = $step.find('input, select, textarea').first();
      if (firstInput.length && !firstInput.prop('disabled')) {
        firstInput.focus();
      }
      
      // Auto-scroll to step on mobile
      if (window.innerWidth <= 768) {
        $step[0].scrollIntoView({ 
          behavior: 'smooth', 
          block: 'center' 
        });
      }
      
      // Announce step change to screen readers
      const stepLabel = $step.attr('aria-labelledby');
      if (stepLabel) {
        const labelText = $('#' + stepLabel).text();
        announceToScreenReader(`Passaggio ${stepNumber}: ${labelText}`);
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
   * Initialize international telephone input
   */
  function initializeTelInput() {
    if (el.telInput.is(':visible') && !iti) {
      iti = intlTelInput(el.telInput[0], {
        utilsScript: rbfData.utilsScript,
        initialCountry: 'it',
        preferredCountries: ['it','gb','us','de','fr','es'],
        separateDialCode: true,
        nationalMode: false
      });
    }
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
    showStep(el.dateStep, 2);

    fp = flatpickr(el.dateInput[0], {
      altInput: true,
      altFormat: 'd-m-Y',
      dateFormat: 'Y-m-d',
      minDate: 'today',
      locale: (rbfData.locale === 'it') ? 'it' : 'default',
      disable: [function(date) {
        const day = date.getDay();
        if (rbfData.closedDays.includes(day)) return true;
        const dateStr = date.toISOString().split('T')[0];
        if (rbfData.closedSingles.includes(dateStr)) return true;
        for (let range of rbfData.closedRanges) {
          if (dateStr >= range.from && dateStr <= range.to) return true;
        }
        return false;
      }],
      onChange: onDateChange
    });
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
    const dow = date.getDay();
    const selectedMeal = el.mealRadios.filter(':checked').val();
    
    // Show brunch notice for Sunday lunch
    if (selectedMeal === 'pranzo' && dow === 0) {
      el.mealNotice.text(rbfData.labels.sundayBrunchNotice).show();
    } else {
      el.mealNotice.hide();
    }
    
    const dateString = date.toISOString().split('T')[0];
    showStep(el.timeStep, 3);
    el.timeSelect.html(`<option value="">${rbfData.labels.loading}</option>`).prop('disabled', true);
    el.timeSelect.addClass('rbf-loading');

    // Get available times via AJAX
    $.post(rbfData.ajaxUrl, {
      action: 'rbf_get_availability',
      _ajax_nonce: rbfData.nonce,
      date: dateString,
      meal: selectedMeal
    }, function(response) {
      el.timeSelect.removeClass('rbf-loading');
      el.timeSelect.html('');
      if (response.success && response.data.length > 0) {
        el.timeSelect.append(new Option(rbfData.labels.chooseTime, ''));
        response.data.forEach(item => {
          const opt = new Option(item.time, `${item.slot}|${item.time}`);
          el.timeSelect.append(opt);
        });
        el.timeSelect.prop('disabled', false);
      } else {
        el.timeSelect.append(new Option(rbfData.labels.noTime, ''));
      }
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
   * Form submission handler
   */
  form.on('submit', function(e) {
    el.submitButton.prop('disabled', true);
    
    if (!el.privacyCheckbox.is(':checked')) {
      e.preventDefault();
      alert(rbfData.labels.privacyRequired);
      el.submitButton.prop('disabled', false);
      return;
    }
    
    if (iti && !iti.isValidNumber()) {
      e.preventDefault();
      alert(rbfData.labels.invalidPhone);
      el.submitButton.prop('disabled', false);
      return;
    }
    
    if (iti) el.telInput.val(iti.getNumber());
  });

  /**
   * Mobile enhancement functions
   */
  function enhanceMobileExperience() {
    // Improve form scrolling on mobile
    if (window.innerWidth <= 768) {
      // Add smooth scrolling behavior
      document.documentElement.style.scrollBehavior = 'smooth';
      
      // Prevent zoom on iOS when focusing inputs
      const inputs = form.find('input[type="text"], input[type="email"], input[type="tel"], input[type="number"], select, textarea');
      inputs.on('touchstart', function() {
        // Already handled in CSS with font-size: 16px
      });
      
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

});