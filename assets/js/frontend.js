jQuery(function($){
  'use strict';
  if (typeof rbfData === 'undefined' || typeof flatpickr === 'undefined' || typeof intlTelInput === 'undefined') return;

  const form = $('#rbf-form');
  if (!form.length) return;

  const el = {
    mealRadios: form.find('input[name="rbf_meal"]'),
    mealNotice: form.find('#rbf-meal-notice'),
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

  function initializeTelInput(){
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

  function resetSteps(fromStep){
    if (fromStep <= 1) {
      el.dateStep.hide();
      if (fp) { fp.clear(); fp.destroy(); fp = null; }
    }
    if (fromStep <= 2) el.timeStep.hide();
    if (fromStep <= 3) el.peopleStep.hide();
    if (fromStep <= 4) {
      el.detailsStep.hide();
      el.detailsInputs.prop('disabled', true);
      el.privacyCheckbox.prop('disabled', true);
      el.marketingCheckbox.prop('disabled', true);
      el.submitButton.hide().prop('disabled', true);
    }
  }

  el.mealRadios.on('change', function(){
    resetSteps(1);
    el.mealNotice.hide();
    el.dateStep.show();

    fp = flatpickr(el.dateInput[0], {
      altInput: true,
      altFormat: 'd-m-Y',
      dateFormat: 'Y-m-d',
      minDate: 'today',
      locale: (rbfData.locale === 'it') ? 'it' : 'default',
      disable: [function(date){
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

  function onDateChange(selectedDates){
    if (!selectedDates.length) { el.mealNotice.hide(); return; }
    resetSteps(2);
    const date = selectedDates[0];
    const dow = date.getDay();
    const selectedMeal = el.mealRadios.filter(':checked').val();
    if (selectedMeal === 'pranzo' && dow === 0) {
      el.mealNotice.text(rbfData.labels.sundayBrunchNotice).show();
    } else {
      el.mealNotice.hide();
    }
    const dateString = date.toISOString().split('T')[0];
    el.timeStep.show();
    el.timeSelect.html(`<option value="">${rbfData.labels.loading}</option>`).prop('disabled', true);

    $.post(rbfData.ajaxUrl, {
      action: 'rbf_get_availability',
      _ajax_nonce: rbfData.nonce,
      date: dateString,
      meal: selectedMeal
    }, function(response){
      el.timeSelect.html('');
      if (response.success && response.data.length > 0) {
        el.timeSelect.append(new Option(rbfData.labels.chooseTime,''));
        response.data.forEach(item=>{
          const opt = new Option(item.time, `${item.slot}|${item.time}`);
          el.timeSelect.append(opt);
        });
        el.timeSelect.prop('disabled', false);
      } else {
        el.timeSelect.append(new Option(rbfData.labels.noTime,''));
      }
    });
  }

  el.timeSelect.on('change', function(){
    resetSteps(3);
    if (this.value) {
      el.peopleStep.show();
      const maxPeople = 30; // cap generico
      el.peopleInput.val(1).attr('max', maxPeople).trigger('input');
    }
  });

  function updatePeopleButtons(){
    const val = parseInt(el.peopleInput.val());
    const max = parseInt(el.peopleInput.attr('max'));
    el.peopleMinus.prop('disabled', val <= 1);
    el.peoplePlus.prop('disabled', val >= max);
  }

  el.peoplePlus.on('click', function(){
    let val = parseInt(el.peopleInput.val());
    let max = parseInt(el.peopleInput.attr('max'));
    if (val < max) el.peopleInput.val(val+1).trigger('input');
  });
  el.peopleMinus.on('click', function(){
    let val = parseInt(el.peopleInput.val());
    if (val > 1) el.peopleInput.val(val-1).trigger('input');
  });
  el.peopleInput.on('input', function(){
    updatePeopleButtons();
    resetSteps(4);
    if (parseInt($(this).val()) > 0) {
      el.detailsStep.show();
      el.detailsInputs.prop('disabled', false);
      el.privacyCheckbox.prop('disabled', false);
      el.marketingCheckbox.prop('disabled', false);
      el.submitButton.show().prop('disabled', true);
      initializeTelInput();
    }
  });

  el.privacyCheckbox.on('change', function(){
    el.submitButton.prop('disabled', !this.checked);
  });

  form.on('submit', function(e){
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

  (function(){
    const qs = new URLSearchParams(window.location.search);
    const get = k => qs.get(k) || '';
    const setVal = (id,val)=>{ var el=document.getElementById(id); if(el) el.value = val; };

    setVal('rbf_utm_source',   get('utm_source'));
    setVal('rbf_utm_medium',   get('utm_medium'));
    setVal('rbf_utm_campaign', get('utm_campaign'));

    setVal('rbf_gclid',  get('gclid'));
    setVal('rbf_fbclid', get('fbclid'));

    if (document.getElementById('rbf_referrer') && !document.getElementById('rbf_referrer').value) {
      document.getElementById('rbf_referrer').value = document.referrer || '';
    }
  })();

});
