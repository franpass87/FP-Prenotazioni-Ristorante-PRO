# Autosave Functionality Documentation

## Overview

This document describes the implementation of the progressive autosave functionality for the restaurant booking form using localStorage. The feature prevents data loss due to accidental page abandonment by automatically saving form progress and restoring it when users return.

## Features Implemented

### 1. Automatic Form Data Saving
- **Debounced autosave**: Form data is saved 1 second after user stops interacting
- **All form fields supported**: Meal selection, date, time, people count, personal details, and consent checkboxes
- **Timestamp tracking**: Each save includes a timestamp for data freshness validation
- **URL validation**: Data is only restored on the same page to prevent cross-page data leakage

### 2. Smart Data Restoration
- **Automatic restoration**: Saved data is automatically restored when users return to the form
- **Data freshness check**: Data older than 24 hours is automatically cleared
- **URL-specific restoration**: Data is only restored on the exact same page where it was saved
- **Form state preservation**: All form fields including radio buttons, checkboxes, and text inputs are restored

### 3. Browser Compatibility
- **localStorage support detection**: Graceful degradation when localStorage is not available
- **Error handling**: Robust error handling for quota exceeded and other localStorage errors
- **Cross-browser compatibility**: Works on all modern browsers that support localStorage

### 4. Data Privacy and Security
- **Automatic cleanup**: Data is cleared on successful form submission
- **24-hour expiration**: Old data is automatically purged
- **URL-scoped data**: Data is only accessible on the same page
- **No sensitive data**: Only form input data is stored, no authentication tokens or credentials

## Technical Implementation

### Code Structure

The autosave functionality is implemented in `assets/js/frontend.js` with the following components:

#### 1. AutoSave Utility Object
```javascript
const AutoSave = {
  isSupported(),    // Check localStorage availability
  save(data),       // Save form data with metadata
  load(),           // Load and validate saved data
  clear()           // Clear saved data
}
```

#### 2. Data Collection Function
```javascript
function collectFormData()
```
Collects current form state from all relevant fields.

#### 3. Data Restoration Function
```javascript
function restoreFormData(data)
```
Restores form state from saved data.

#### 4. Debounced Save Function
```javascript
function scheduleAutosave()
```
Implements 1-second debounce to prevent excessive localStorage writes.

### Form Fields Supported

The autosave functionality captures the following form fields:

1. **Meal Selection** (`rbf_meal`) - Radio button selection
2. **Date** (`rbf_data`) - Date picker input
3. **Time** (`rbf_orario`) - Time slot selection
4. **People Count** (`rbf_persone`) - Number input
5. **Personal Details**:
   - Name (`rbf_nome`)
   - Surname (`rbf_cognome`) 
   - Email (`rbf_email`)
   - Phone (`rbf_tel`)
   - Notes/Allergies (`rbf_allergie`)
6. **Consent Checkboxes**:
   - Privacy Policy (`rbf_privacy`)
   - Marketing Consent (`rbf_marketing`)

### Data Storage Format

Data is stored in localStorage with the following structure:

```json
{
  "data": {
    "meal": "pranzo",
    "date": "2024-12-25",
    "time": "13:00",
    "people": "4",
    "name": "Mario",
    "surname": "Rossi",
    "email": "mario.rossi@example.com",
    "tel": "+39 123 456 7890",
    "notes": "Allergia ai crostacei",
    "privacy": true,
    "marketing": true
  },
  "timestamp": 1756556665128,
  "url": "https://example.com/booking-form"
}
```

### Event Listeners

The autosave functionality attaches event listeners to form fields:

- **Radio buttons**: `change` event
- **Date/time inputs**: `change` event  
- **Text inputs**: `input` event
- **Checkboxes**: `change` event
- **Form submission**: `submit` event (clears saved data)

### Performance Considerations

- **Debouncing**: 1-second delay prevents excessive localStorage writes
- **Minimal overhead**: Only saves when form data actually changes
- **Lazy initialization**: Event listeners are attached only once on page load
- **Error resilience**: Continues working even if localStorage operations fail

## Usage Instructions

### For Developers

1. The autosave functionality is automatically initialized when the page loads
2. No additional configuration is required
3. All form fields are automatically monitored
4. Data is cleared automatically on successful form submission

### For Users

1. Fill out any form fields
2. Data is automatically saved after 1 second of inactivity
3. If you accidentally leave the page, your data will be restored when you return
4. Submitting the form will clear the saved data

## Browser Support

The autosave functionality supports all modern browsers with localStorage:

- **Chrome**: 4+
- **Firefox**: 3.5+
- **Safari**: 4+
- **Edge**: All versions
- **Internet Explorer**: 8+

For browsers without localStorage support, the form continues to work normally without autosave functionality.

## Testing

The implementation includes comprehensive testing:

1. **Autosave functionality**: Form data is saved after user interaction
2. **Data restoration**: Saved data is restored on page reload
3. **Data clearing**: Data is cleared on form submission
4. **Cross-browser compatibility**: Tested on major browsers
5. **Error handling**: Graceful degradation when localStorage is unavailable

## Maintenance

### Monitoring
- Console logs indicate autosave events for debugging
- localStorage usage can be monitored via browser developer tools

### Updates
- When adding new form fields, add them to `collectFormData()` and `restoreFormData()`
- Event listeners should be added to `initializeAutosave()` for new fields

### Troubleshooting
- Check browser console for autosave-related errors
- Verify localStorage is enabled in browser settings
- Clear localStorage manually if needed: `localStorage.removeItem('rbf_booking_form_data')`