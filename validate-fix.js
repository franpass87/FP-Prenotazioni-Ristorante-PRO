// Validation test to ensure the calendar logic respects configuration
console.log('=== Validation Test: Configurazioni Calendario ===');

const ADMIN_BANNER_KEY = 'calendar_all_days_closed';
const ADMIN_BANNER_MESSAGE = 'Tutti i giorni del calendario risultano chiusi. Verifica le impostazioni di apertura in WordPress.';

const rbfLog = {
  error: function(msg) { console.log('ERROR:', msg); },
  warn: function(msg) { console.log('WARN:', msg); },
  log: function(msg) {}
};

const adminBannerMessages = [];
const adminBannerKeys = new Set();

function showAdminConfigurationBanner(message, options = {}) {
  const key = (options && options.key) || message;
  if (adminBannerKeys.has(key)) {
    return;
  }
  adminBannerKeys.add(key);
  adminBannerMessages.push({ key, message });
  console.log('[ADMIN BANNER]', message);
}

function resetState() {
  globalThis.rbfTotalCount = 0;
  globalThis.rbfDisabledCount = 0;
  globalThis.rbfEmergencyMode = false;
  globalThis.rbfEmergencyModeLocked = false;
  globalThis.rbfEmergencySuppressedLogged = false;
  globalThis.rbfAllDaysClosedLogged = false;
  globalThis.rbfForceEmergencyMode = false;
  globalThis.rbfEmergencyOverrideDetected = false;
  globalThis.rbfEmergencyOverrideConsentLogged = false;
  globalThis.rbfEmergencyOverrideConsentMissingLogged = false;
  adminBannerMessages.length = 0;
  adminBannerKeys.clear();
}

function formatLocalISO(date) {
  return new Date(date.getTime() - date.getTimezoneOffset() * 60000)
    .toISOString()
    .split('T')[0];
}

function isDateDisabled(date, data) {
  try {
    const dateStr = formatLocalISO(date);
    const dayOfWeek = date.getDay();

    if (globalThis.rbfTotalCount === undefined) {
      globalThis.rbfTotalCount = 0;
      globalThis.rbfDisabledCount = 0;
    }
    if (globalThis.rbfEmergencyMode === undefined) {
      globalThis.rbfEmergencyMode = false;
    }
    if (globalThis.rbfEmergencyModeLocked === undefined) {
      globalThis.rbfEmergencyModeLocked = false;
    }
    if (globalThis.rbfEmergencySuppressedLogged === undefined) {
      globalThis.rbfEmergencySuppressedLogged = false;
    }
    if (globalThis.rbfAllDaysClosedLogged === undefined) {
      globalThis.rbfAllDaysClosedLogged = false;
    }
    if (globalThis.rbfForceEmergencyMode === undefined) {
      globalThis.rbfForceEmergencyMode = false;
    }
    if (globalThis.rbfEmergencyOverrideDetected === undefined) {
      globalThis.rbfEmergencyOverrideDetected = false;
    }
    if (globalThis.rbfEmergencyOverrideConsentLogged === undefined) {
      globalThis.rbfEmergencyOverrideConsentLogged = false;
    }
    if (globalThis.rbfEmergencyOverrideConsentMissingLogged === undefined) {
      globalThis.rbfEmergencyOverrideConsentMissingLogged = false;
    }

    globalThis.rbfTotalCount++;

    const originalClosedDays = Array.isArray(data.closedDays) ? [...data.closedDays] : [];

    if (Array.isArray(data.closedDays)) {
      const filteredClosedDays = data.closedDays.filter(
        day => typeof day === 'number' && day >= 0 && day <= 6
      );
      if (originalClosedDays.length !== filteredClosedDays.length) {
        rbfLog.warn('Removed invalid day values from closedDays');
      }
      data.closedDays = filteredClosedDays;
    } else {
      data.closedDays = [];
    }

    const closedDays = Array.isArray(data.closedDays) ? data.closedDays : [];
    const allDaysClosed = closedDays.length >= 7;

    if (allDaysClosed) {
      if (!globalThis.rbfAllDaysClosedLogged) {
        rbfLog.error('CRITICAL ISSUE DETECTED: All 7 days marked as closed!');
        rbfLog.error('Original closedDays: ' + JSON.stringify(originalClosedDays));
        rbfLog.error('This configuration will disable ALL calendar dates until updated.');
        rbfLog.warn('Admin configuration banner displayed for follow-up.');
        rbfLog.warn('Please check WordPress admin settings for restaurant opening hours');
        globalThis.rbfAllDaysClosedLogged = true;
      }

      showAdminConfigurationBanner(ADMIN_BANNER_MESSAGE, { key: ADMIN_BANNER_KEY });

      globalThis.rbfEmergencyModeLocked = true;
      if (globalThis.rbfEmergencyMode) {
        globalThis.rbfEmergencyMode = false;
      }
      if (!globalThis.rbfEmergencySuppressedLogged) {
        rbfLog.warn('Emergency override disabled to respect fully closed configuration');
        globalThis.rbfEmergencySuppressedLogged = true;
      }
    } else {
      if (globalThis.rbfAllDaysClosedLogged) {
        globalThis.rbfAllDaysClosedLogged = false;
      }
      if (globalThis.rbfEmergencyModeLocked) {
        globalThis.rbfEmergencyModeLocked = false;
        globalThis.rbfEmergencySuppressedLogged = false;
      }
    }

    let isDateCurrentlyDisabled = false;

    if (!isDateCurrentlyDisabled && closedDays.includes(dayOfWeek)) {
      globalThis.rbfDisabledCount++;
      isDateCurrentlyDisabled = true;
    }

    if (!isDateCurrentlyDisabled && Array.isArray(data.closedSingles) && data.closedSingles.includes(dateStr)) {
      globalThis.rbfDisabledCount++;
      isDateCurrentlyDisabled = true;
    }

    if (!isDateCurrentlyDisabled && Array.isArray(data.closedRanges) && data.closedRanges.length > 0) {
      data.closedRanges = data.closedRanges.filter(range => {
        if (!range || !range.from || !range.to) return false;

        try {
          const fromDate = new Date(range.from);
          const toDate = new Date(range.to);
          const diffDays = Math.ceil((toDate - fromDate) / (1000 * 60 * 60 * 24));

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

      for (const range of data.closedRanges) {
        if (range && range.from && range.to) {
          if (dateStr >= range.from && dateStr <= range.to) {
            globalThis.rbfDisabledCount++;
            isDateCurrentlyDisabled = true;
            break;
          }
        }
      }
    }

    const disabledRatio = globalThis.rbfTotalCount > 0
      ? globalThis.rbfDisabledCount / globalThis.rbfTotalCount
      : 0;

    const emergencyThresholdReached =
      !globalThis.rbfEmergencyModeLocked &&
      globalThis.rbfTotalCount > 20 &&
      disabledRatio > 0.8;

    if (emergencyThresholdReached) {
      if (!globalThis.rbfEmergencyOverrideDetected) {
        rbfLog.error('Safety check: more than 80% of evaluated dates are closed.');
        rbfLog.warn('Emergency override requires explicit consent (set forceEmergencyOverride = true) before unlocking dates.');
      }

      showAdminConfigurationBanner(
        "Più dell'80% delle date risulta <strong>chiuso</strong>. Verifica le impostazioni di apertura o abilita temporaneamente la modalità di emergenza impostando <code>forceEmergencyOverride</code> su <strong>true</strong> in rbfData.",
        { key: 'calendar_high_disabled_ratio' }
      );

      globalThis.rbfEmergencyOverrideDetected = true;
    } else if (globalThis.rbfEmergencyOverrideDetected) {
      rbfLog.log('Emergency override detection reset: disabled ratio back under threshold');
      globalThis.rbfEmergencyOverrideDetected = false;
      globalThis.rbfEmergencyOverrideConsentLogged = false;
      globalThis.rbfEmergencyOverrideConsentMissingLogged = false;
    }

    const manualOverrideActive = globalThis.rbfForceEmergencyMode === true || globalThis.rbfEmergencyMode === true;
    const configOverrideConsent = !!(data && data.forceEmergencyOverride === true);

    const shouldApplyEmergencyOverride =
      !globalThis.rbfEmergencyModeLocked &&
      (manualOverrideActive ||
        (globalThis.rbfEmergencyOverrideDetected && configOverrideConsent));

    if (shouldApplyEmergencyOverride) {
      if (!globalThis.rbfEmergencyMode) {
        globalThis.rbfEmergencyMode = true;
      }

      if (
        globalThis.rbfEmergencyOverrideDetected &&
        configOverrideConsent &&
        !globalThis.rbfEmergencyOverrideConsentLogged
      ) {
        rbfLog.warn('Emergency override activated with explicit consent (forceEmergencyOverride = true).');
        globalThis.rbfEmergencyOverrideConsentLogged = true;
        globalThis.rbfEmergencyOverrideConsentMissingLogged = false;
      } else if (
        manualOverrideActive &&
        !globalThis.rbfEmergencyOverrideConsentLogged
      ) {
        if (globalThis.rbfForceEmergencyMode === true) {
          rbfLog.warn('Emergency override manually forced via rbfForceEmergencyMode.');
        } else {
          rbfLog.warn('Emergency override manually activated via rbfEmergencyMode.');
        }
        globalThis.rbfEmergencyOverrideConsentLogged = true;
        globalThis.rbfEmergencyOverrideConsentMissingLogged = false;
      }
    } else {
      if (globalThis.rbfEmergencyMode && !globalThis.rbfEmergencyModeLocked) {
        globalThis.rbfEmergencyMode = false;
      }

      if (
        globalThis.rbfEmergencyOverrideDetected &&
        !configOverrideConsent &&
        !manualOverrideActive &&
        !globalThis.rbfEmergencyOverrideConsentMissingLogged
      ) {
        rbfLog.warn('Emergency override not activated: waiting for explicit consent (set forceEmergencyOverride = true or use manual override).');
        globalThis.rbfEmergencyOverrideConsentMissingLogged = true;
      }

      if (!manualOverrideActive && !configOverrideConsent) {
        globalThis.rbfEmergencyOverrideConsentLogged = false;
      }
    }

    if (globalThis.rbfEmergencyMode && !globalThis.rbfEmergencyModeLocked) {
      return false;
    }

    return isDateCurrentlyDisabled;
  } catch (error) {
    rbfLog.error('Error in isDateDisabled: ' + error.message);
    return false;
  }
}

const testConfigs = [
  { name: 'Normal - Monday closed', closedDays: [1] },
  { name: 'Weekend restaurant - Mon+Tue closed', closedDays: [1, 2] },
  { name: 'All open', closedDays: [] },
  { name: 'Sunday closed', closedDays: [0] },
  { name: 'Mid-week closed', closedDays: [2, 3] }
];

let allPassed = true;

testConfigs.forEach(config => {
  console.log('\nTesting:', config.name);
  resetState();
  const data = {
    closedDays: Array.isArray(config.closedDays) ? [...config.closedDays] : [],
    closedSingles: [],
    closedRanges: []
  };
  const originalClosedDays = [...data.closedDays];

  isDateDisabled(new Date(), data);

  const unchanged = JSON.stringify(data.closedDays) === JSON.stringify(originalClosedDays);
  console.log('  Original:', originalClosedDays);
  console.log('  Final:', data.closedDays);
  console.log('  Unchanged:', unchanged ? '✅' : '❌');
  if (!unchanged) {
    allPassed = false;
  }

  if (adminBannerMessages.length > 0) {
    console.log('  ⚠️ Unexpected admin banner:', adminBannerMessages);
    allPassed = false;
  }
});

console.log('\nTesting: Critical case - All days closed');
resetState();
const criticalData = {
  closedDays: [0, 1, 2, 3, 4, 5, 6],
  closedSingles: [],
  closedRanges: []
};
const originalCritical = [...criticalData.closedDays];
const sampleDates = Array.from({ length: 7 }, (_, i) => new Date(2024, 0, i + 1));
let disabledCount = 0;

sampleDates.forEach(date => {
  if (isDateDisabled(date, criticalData)) {
    disabledCount++;
  }
});

const configUnchanged = JSON.stringify(criticalData.closedDays) === JSON.stringify(originalCritical);
const allDisabled = disabledCount === sampleDates.length;
const bannerShown = adminBannerMessages.some(msg => msg.key === ADMIN_BANNER_KEY);

console.log('  Config unchanged:', configUnchanged ? '✅' : '❌');
console.log('  All dates disabled:', allDisabled ? '✅' : '❌');
console.log('  Admin banner shown:', bannerShown ? '✅' : '❌');

if (!configUnchanged || !allDisabled || !bannerShown) {
  allPassed = false;
}

console.log('\nTesting: High closure ratio without consent (only Sunday open)');
resetState();
const limitedOpenConfig = {
  closedDays: [1, 2, 3, 4, 5, 6],
  closedSingles: [],
  closedRanges: []
};

const sampleMonth = Array.from({ length: 31 }, (_, index) => new Date(2024, 0, index + 1));
let mondayDisabled = false;
let sundayDisabled = false;

sampleMonth.forEach(date => {
  const disabled = isDateDisabled(date, limitedOpenConfig);
  if (date.getDay() === 1 && disabled) {
    mondayDisabled = true;
  }
  if (date.getDay() === 0 && disabled) {
    sundayDisabled = true;
  }
});

const overrideStayedOff = globalThis.rbfEmergencyMode === false;
console.log('  Monday remains disabled:', mondayDisabled ? '✅' : '❌');
console.log('  Sunday remains enabled:', sundayDisabled ? '❌' : '✅');
console.log('  Emergency override inactive:', overrideStayedOff ? '✅' : '❌');

if (!mondayDisabled || sundayDisabled || !overrideStayedOff) {
  allPassed = false;
}

console.log('\nTesting: High closure ratio with explicit consent');
resetState();
const consentConfig = {
  closedDays: [1, 2, 3, 4, 5, 6],
  closedSingles: [],
  closedRanges: [],
  forceEmergencyOverride: true
};

let mondayAllowed = false;
let sundayAllowed = false;

sampleMonth.forEach(date => {
  const disabled = isDateDisabled(date, consentConfig);
  if (date.getDay() === 1 && !disabled) {
    mondayAllowed = true;
  }
  if (date.getDay() === 0 && !disabled) {
    sundayAllowed = true;
  }
});

const overrideActivated = globalThis.rbfEmergencyMode === true;
console.log('  Monday opened with override:', mondayAllowed ? '✅' : '❌');
console.log('  Sunday still available:', sundayAllowed ? '✅' : '❌');
console.log('  Emergency override active:', overrideActivated ? '✅' : '❌');

if (!mondayAllowed || !sundayAllowed || !overrideActivated) {
  allPassed = false;
}

console.log('\n=== Final Result ===');
console.log(allPassed ? '✅ ALL TESTS PASSED - Configuration respected' : '❌ SOME TESTS FAILED - Review needed');
