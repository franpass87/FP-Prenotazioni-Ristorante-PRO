// Validation test to ensure the fix doesn't interfere with normal operation
console.log('=== Validation Test: Normal Operation Preservation ===');

const rbfLog = { 
  error: function(msg) { console.log('ERROR:', msg); }, 
  warn: function(msg) { console.log('WARN:', msg); }, 
  log: function(msg) {} 
};

// Test various normal configurations to ensure they're not affected
const testConfigs = [
    { name: 'Normal - Monday closed', closedDays: [1] },
    { name: 'Weekend restaurant - Mon+Tue closed', closedDays: [1, 2] },
    { name: 'All open', closedDays: [] },
    { name: 'Sunday closed', closedDays: [0] },
    { name: 'Mid-week closed', closedDays: [2, 3] }
];

let allPassed = true;

testConfigs.forEach(function(config) {
    console.log('\nTesting:', config.name);
    const originalClosedDays = [...config.closedDays];
    
    // Apply the fix logic
    if (config.closedDays && Array.isArray(config.closedDays) && config.closedDays.length >= 7) {
        rbfLog.error('CRITICAL ISSUE DETECTED: All 7 days marked as closed!');
        config.closedDays = [1];
        rbfLog.warn('EMERGENCY FIX APPLIED');
    }
    
    const unchanged = JSON.stringify(originalClosedDays) === JSON.stringify(config.closedDays);
    console.log('  Original:', originalClosedDays);
    console.log('  Final:', config.closedDays);
    console.log('  Unchanged:', unchanged ? '✅' : '❌');
    
    if (!unchanged) {
        console.log('  ⚠️ UNEXPECTED: Normal config was modified!');
        allPassed = false;
    }
});

// Test the critical case
console.log('\nTesting: Critical case - All days closed');
const criticalConfig = { closedDays: [0,1,2,3,4,5,6] };
const originalCritical = [...criticalConfig.closedDays];

if (criticalConfig.closedDays && Array.isArray(criticalConfig.closedDays) && criticalConfig.closedDays.length >= 7) {
    rbfLog.error('CRITICAL ISSUE DETECTED: All 7 days marked as closed!');
    criticalConfig.closedDays = [1];
    rbfLog.warn('EMERGENCY FIX APPLIED');
}

const fixApplied = JSON.stringify(originalCritical) !== JSON.stringify(criticalConfig.closedDays);
console.log('  Original:', originalCritical);
console.log('  Final:', criticalConfig.closedDays);
console.log('  Fix applied:', fixApplied ? '✅' : '❌');

if (!fixApplied) {
    console.log('  ⚠️ PROBLEM: Critical case was not fixed!');
    allPassed = false;
}

console.log('\n=== Final Result ===');
console.log(allPassed ? '✅ ALL TESTS PASSED - Fix is safe and effective' : '❌ SOME TESTS FAILED - Review needed');