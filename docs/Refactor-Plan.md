# Refactor Plan

**Project**: FP-Prenotazioni-Ristorante-PRO  
**Version**: 11.0.0  
**Date**: 2024-08-23  
**Priority**: High to Low  

## DEDUP Plan

### 1. Database Query Patterns (HIGH PRIORITY)
**Pattern**: Repetitive `$wpdb->prepare()` queries with similar structure
**Files**: `admin.php`, `frontend.php`, `utils.php`
**Target**: Extract to `includes/database.php` utility class

#### Duplications Found:
- **Booking lookup by hash**: `frontend.php:198` and similar patterns
- **Booking data retrieval**: Multiple files use same JOIN patterns
- **Meta field queries**: Repeated `pm_*.meta_value` patterns

**Consolidation Plan**:
```php
// Create RBF_Database_Helper class
class RBF_Database_Helper {
    public static function get_booking_by_hash($hash) { /* unified query */ }
    public static function get_bookings_in_date_range($start, $end, $status = null) { /* unified query */ }
    public static function get_availability_for_slot($date, $slot) { /* unified query */ }
}
```

### 2. Email/Notification Patterns (HIGH PRIORITY)
**Pattern**: Repeated email sending logic
**Files**: `booking-handler.php`, `admin.php`, `integrations.php`

#### Duplications Found:
- Admin notification calls: `rbf_send_admin_notification_email()` - 4 occurrences
- Brevo automation calls: `rbf_trigger_brevo_automation()` - 4 occurrences
- Similar parameter passing patterns

**Consolidation Plan**:
```php
// Create RBF_Notification_Manager class
class RBF_Notification_Manager {
    public static function send_booking_notifications($booking_data, $type = 'new') { /* unified logic */ }
    public static function trigger_integrations($booking_data) { /* unified integration calls */ }
}
```

### 3. Validation/Sanitization Patterns (MEDIUM PRIORITY)
**Pattern**: Repeated input validation and sanitization
**Files**: `booking-handler.php`, `admin.php`, `utils.php`

#### Current Issues:
- 196 sanitization calls scattered throughout codebase
- Similar validation patterns for email, phone, date
- Inconsistent error handling

**Consolidation Plan**:
```php
// Extend RBF_Validator class
class RBF_Booking_Validator extends RBF_UTM_Validator {
    public static function validate_booking_data($post_data) { /* unified validation */ }
    public static function sanitize_booking_fields($fields) { /* unified sanitization */ }
}
```

### 4. Settings Access Patterns (LOW PRIORITY)
**Pattern**: `rbf_get_settings()` called 12+ times
**Files**: All modules

**Solution**: Implement singleton pattern or static caching to reduce DB calls

## Modularization Plan

### 1. Extract Database Layer (HIGH PRIORITY)
**New File**: `includes/database.php`
**Purpose**: Centralize all database operations
**Size Reduction**: ~200-300 lines from existing files

### 2. Extract Notification System (HIGH PRIORITY)
**New File**: `includes/notifications.php`
**Purpose**: Handle all email/integration notifications
**Size Reduction**: ~100-150 lines from existing files

### 3. Break Down `admin.php` (HIGH PRIORITY)
**Current**: 1,398 lines (oversized)
**Target Files**:
- `admin/calendar.php` - Calendar functionality (~400 lines)
- `admin/reports.php` - Reports and analytics (~300 lines)
- `admin/export.php` - Export functionality (~200 lines)
- `admin/settings.php` - Settings management (~300 lines)
- `admin/core.php` - Remaining admin logic (~198 lines)

### 4. Centralized Constants/Config (MEDIUM PRIORITY)
**New File**: `includes/config.php`
**Purpose**: Move hardcoded values to configuration
**Examples**: Email templates, validation rules, default settings

## Bugfix Plan (Rapid Fixes)

### 1. Security Improvements (CRITICAL)
- **Issue**: Direct `$_GET` usage in `frontend.php:244`
- **Fix**: Replace with proper sanitization: `$booking_hash = sanitize_text_field($_GET['booking'] ?? '');`
- **Location**: `frontend.php`, lines 244-245

### 2. Error Handling Improvements (HIGH PRIORITY)
- **Issue**: Some functions lack proper error handling
- **Fix**: Add try-catch blocks around critical operations
- **Locations**: API calls in `booking-handler.php`, database operations

### 3. Input Validation (HIGH PRIORITY)
- **Issue**: Some user inputs bypass validation
- **Fix**: Implement centralized validation before processing
- **Location**: All form handlers

### 4. Memory/Performance (MEDIUM PRIORITY)
- **Issue**: Multiple `rbf_get_settings()` calls
- **Fix**: Implement static caching within function
- **Location**: `utils.php:rbf_get_settings()`

## Dead Code Removal Plan

### 1. Unused Functions (PENDING ANALYSIS)
**Action**: Need to analyze call graph to identify unused functions
**Tool**: Create simple PHP parser to track function usage

### 2. Obsolete Flags/Features (PENDING ANALYSIS)
**Action**: Review code for deprecated features or flags
**Examples**: Legacy migration code that's no longer needed

### 3. Unused Assets (LOW PRIORITY)
**Action**: Check if all CSS/JS is actually used
**Tool**: Browser dev tools analysis

## Test Addition Plan

### 1. Golden Master Tests (HIGH PRIORITY)
**Target**: Critical booking flow
**Files**: `booking-handler.php` main functions
**Approach**: Capture current output, ensure no regressions

### 2. Unit Tests (MEDIUM PRIORITY)
**Target**: Utility functions in `utils.php`
**Focus**: Validation, sanitization, translation functions

### 3. Integration Tests (LOW PRIORITY)
**Target**: End-to-end booking process
**Tool**: WordPress testing framework

## Risk Assessment & Rollback Plan

### High Risk Changes
1. **Database layer extraction**: Could break queries
   - **Mitigation**: Incremental migration, extensive testing
   - **Rollback**: Git revert, restore original functions

2. **Admin.php breakdown**: Could affect WordPress hooks
   - **Mitigation**: Maintain hook registration order
   - **Rollback**: Keep backup, merge files if needed

### Medium Risk Changes
1. **Validation consolidation**: Could change validation behavior
   - **Mitigation**: Maintain exact same validation logic
   - **Rollback**: Restore individual validation calls

### Low Risk Changes
1. **Settings caching**: Performance optimization only
   - **Mitigation**: Use WordPress transients for reliability
   - **Rollback**: Remove caching, restore direct DB calls

## Implementation Order (Recommended)

### Phase 1: Foundation (Week 1)
1. ✅ Set up linting tools (PHPCS, PHPStan)
2. ✅ Create basic test infrastructure  
3. ✅ Fix critical security issues
4. ✅ Implement settings caching

### Phase 2: Core Refactoring (Week 2)
1. Extract database helper class
2. Consolidate validation patterns
3. Create notification manager
4. Fix error handling gaps

### Phase 3: Modularization (Week 3)  
1. Break down admin.php into modules
2. Extract centralized config
3. Remove identified dead code
4. Optimize asset loading

### Phase 4: Testing & Hardening (Week 4)
1. Add comprehensive tests
2. Performance profiling and optimization
3. Final code review and cleanup
4. Documentation updates

## Success Metrics

### Quantitative Targets
- **Code Duplication**: Reduce by 30-40%
- **File Size**: No single file > 800 lines
- **Function Count**: Average function length < 50 lines
- **Test Coverage**: Minimum 60% for critical paths
- **PHPCS Warnings**: Zero warnings/errors

### Qualitative Targets
- **Maintainability**: Clear module boundaries
- **Readability**: Consistent coding patterns
- **Security**: All inputs properly validated/sanitized
- **Performance**: No regression in page load times

---
*This plan will be updated as analysis progresses and issues are identified.*