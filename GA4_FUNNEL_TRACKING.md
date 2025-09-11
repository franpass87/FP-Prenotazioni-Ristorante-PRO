# GA4 Funnel Tracking - Feature Documentation

## Overview
This documentation describes the comprehensive GA4 funnel tracking implementation for the restaurant booking plugin. The system tracks user interactions through the booking funnel with custom GA4 events, providing detailed analytics with session and event deduplication.

## Hybrid GTM + GA4 Configuration

The plugin can load the Google Tag Manager container and the GA4 `gtag.js` script at the same time.  
Events are sent directly to GA4 while also being pushed into the `dataLayer` so that GTM can trigger additional tags.

```javascript
window.dataLayer = window.dataLayer || [];
dataLayer.push({
  event: 'time_selected',
  selected_time: '20:00',
  meal_type: 'cena'
});
```

**Avoid duplicates:** if GA4 events are sent directly via `gtag()`, disable the GA4 configuration tag inside GTM or adjust trigger conditions to prevent the same hit from being fired twice.

## Funnel Events

### 1. **Form View** (`form_view`)
- **Triggered:** When the booking form is loaded on the page
- **Parameters:**
  - `form_type`: 'restaurant_booking'
  - `funnel_step`: 1
  - `step_name`: 'form_view'

### 2. **Meal Selection** (`meal_selected`)
- **Triggered:** When user selects a meal type (pranzo/cena/aperitivo)
- **Parameters:**
  - `meal_type`: Selected meal type
  - `funnel_step`: 2
  - `step_name`: 'meal_selection'

### 3. **Date Selection** (`date_selected`)
- **Triggered:** When user selects a booking date
- **Parameters:**
  - `selected_date`: Selected date (YYYY-MM-DD)
  - `meal_type`: Previously selected meal type
  - `funnel_step`: 3
  - `step_name`: 'date_selection'

### 4. **Time Selection** (`time_selected`)
- **Triggered:** When user selects a booking time
- **Parameters:**
  - `selected_time`: Selected time (HH:MM)
  - `selected_date`: Previously selected date
  - `meal_type`: Previously selected meal type
  - `funnel_step`: 4
  - `step_name`: 'time_selection'

### 5. **People Selection** (`people_selected`)
- **Triggered:** When user selects number of people
- **Parameters:**
  - `people_count`: Number of people (integer)
  - `meal_type`: Previously selected meal type
  - `funnel_step`: 5
  - `step_name`: 'people_selection'

### 6. **Form Submission** (`form_submitted`)
- **Triggered:** When user submits the booking form
- **Parameters:**
  - `meal_type`: Selected meal type
  - `people_count`: Number of people
  - `has_phone`: Boolean if phone number provided
  - `has_notes`: Boolean if notes provided
  - `marketing_consent`: Boolean for marketing consent
  - `funnel_step`: 6
  - `step_name`: 'form_submission'

### 7. **Booking Confirmation** (`booking_confirmed`)
- **Triggered:** On successful booking completion
- **Parameters:**
  - `booking_id`: Unique booking identifier
  - `value`: Booking value (currency amount)
  - `currency`: Currency code (EUR)
  - `meal_type`: Selected meal type
  - `people_count`: Number of people
  - `traffic_source`: Traffic source bucket
  - `funnel_step`: 7
  - `step_name`: 'booking_confirmation'

### 8. **Booking Error** (`booking_error`)
- **Triggered:** When booking errors occur
- **Parameters:**
  - `error_message`: Error message (truncated to 100 chars)
  - `error_type`: Classified error type
  - `funnel_step`: Step where error occurred
  - `step_name`: 'error'

## Error Classification

The system automatically classifies errors into the following types:

- **`validation_error`**: Form validation failures
- **`availability_error`**: Capacity or availability issues
- **`security_error`**: Security check failures
- **`system_error`**: Database or system errors
- **`integration_error`**: Third-party API failures
- **`analytics_error`**: GA4 tracking errors
- **`technical_error`**: AJAX or technical failures
- **`unknown_error`**: Unclassified errors

## Deduplication System

### Session ID Generation
- Format: `rbf_` + 16-character hex string
- Based on: PHP session ID, user agent, IP address, timestamp
- Persistent within the same browser session
- Used as GA4 `client_id` for Measurement Protocol

### Event ID Generation
- Format: `rbf_{event_type}_{session_id}_{timestamp}`
- Unique for each event occurrence
- Prevents duplicate event tracking
- Used for both gtag and Measurement Protocol deduplication

## Technical Implementation

### Backend Components

#### Session Management
```php
rbf_generate_session_id() // Generate/retrieve session ID
rbf_generate_event_id($event_type, $session_id) // Generate unique event ID
```

#### Event Tracking
```php
rbf_track_booking_completion($booking_id, $booking_data) // Track successful booking
rbf_track_booking_error($message, $context, $session_id) // Track errors
rbf_send_ga4_measurement_protocol($event_name, $params, $session_id, $event_id) // Server-side tracking
```

#### AJAX Handlers
- `rbf_ajax_track_ga4_event` - Server-side event tracking
- `rbf_ajax_get_booking_completion_data` - Retrieve completion data for success page

### Frontend Components

#### JavaScript Funnel Tracker
```javascript
rbfFunnelTracker.trackEvent(eventName, params, options) // Manual event tracking
rbfFunnelTracker.trackFormView() // Track form view
rbfFunnelTracker.trackMealSelection(mealType) // Track meal selection
// ... other tracking methods
```

#### Automatic Event Listeners
- Form field changes trigger corresponding funnel events
- Error events are captured automatically
- AJAX failures are tracked as technical errors

## Configuration

### Required Settings
1. **GA4 Measurement ID** (`ga4_id`): Your GA4 property measurement ID (G-XXXXXXXXXX)
2. **GA4 API Secret** (`ga4_api_secret`): For server-side Measurement Protocol (optional)

### Admin Configuration
Navigate to: **WordPress Admin → Settings → Booking Settings → Integrations**

### Measurement Protocol Benefits
When GA4 API Secret is configured:
- Server-side event tracking for reliability
- Enhanced data accuracy
- Backup tracking method
- Better ad platform integration

## Conversion Tracking

### Standard E-commerce Events
The system sends standard GA4 e-commerce events:

```javascript
gtag('event', 'purchase', {
  transaction_id: 'rbf_123',
  value: 50.00,
  currency: 'EUR',
  items: [{
    item_id: 'restaurant_booking',
    item_name: 'Restaurant Booking - Cena',
    category: 'restaurant',
    quantity: 1,
    price: 25.00
  }]
});
```

### Custom Restaurant Events
```javascript
gtag('event', 'restaurant_booking', {
  transaction_id: 'rbf_123',
  value: 50.00,
  currency: 'EUR',
  meal: 'cena',
  people: 2,
  bucket: 'organic',
  vertical: 'restaurant'
});
```

## Analytics Reporting

### Funnel Analysis in GA4

1. **Create Custom Funnel Report:**
   - Events: `form_view` → `meal_selected` → `date_selected` → `time_selected` → `form_submitted` → `booking_confirmed`

2. **Conversion Goals:**
   - Primary: `booking_confirmed` events
   - Secondary: `form_submitted` events

3. **Error Analysis:**
   - Track `booking_error` events by `error_type`
   - Analyze drop-off points in funnel

### Custom Dimensions
Consider setting up these custom dimensions in GA4:
- `meal_type` - Meal service type
- `error_type` - Error classification
- `traffic_source` - Detailed traffic source
- `vertical` - Business vertical (restaurant)

## Testing and Validation

### Automated Tests
Run the comprehensive test suite:
```
/wp-admin/?rbf_test_ga4_funnel=1&nonce=[nonce]
```

### Manual Testing Steps

1. **Enable Debug Mode:**
   ```javascript
   // Add to page or console
   window.rbfDebug = true;
   ```

2. **Check Browser Console:**
   - Look for "RBF GA4 Funnel:" messages
   - Verify event parameters are correct

3. **GA4 DebugView:**
   - Enable Debug mode in GA4
   - Watch events in real-time

4. **Test Error Scenarios:**
   - Submit invalid forms
   - Network interruptions
   - Capacity limits

### Validation Checklist

- [ ] Session ID generation working
- [ ] Event ID uniqueness verified
- [ ] All funnel events firing correctly
- [ ] Error classification accurate
- [ ] Measurement Protocol configured (if using)
- [ ] GA4 DebugView showing events
- [ ] No duplicate events in GA4
- [ ] Conversion tracking working

## Performance Considerations

### Client-Side Optimization
- Events are batched when possible
- Duplicate event prevention
- Minimal DOM manipulation
- Efficient event listeners

### Server-Side Optimization
- Transient caching for booking data
- Error logging without affecting UX
- Async API calls where possible
- Graceful fallbacks for API failures

## Privacy and Compliance

### Data Collection
- Only booking-related interaction data
- No personally identifiable information in events
- Session-based tracking (not persistent user tracking)
- Respects user consent preferences

### GDPR Compliance
- Session data is temporary
- No cross-session user tracking
- Error messages are sanitized
- Booking data follows existing privacy policy

## Troubleshooting

### Common Issues

1. **Events Not Appearing in GA4:**
   - Check measurement ID configuration
   - Verify gtag is loaded
   - Check browser console for errors

2. **Duplicate Events:**
   - Verify event ID generation
   - Check for multiple form instances

3. **Missing Error Events:**
   - Ensure error handler integration
   - Check PHP error logs

4. **Server-Side Tracking Failing:**
   - Verify API secret configuration
   - Check network connectivity
   - Review error logs

### Debug Information
Enable WordPress debug mode and check logs for:
- "RBF GA4 Funnel:" JavaScript messages
- "RBF Error [ga4_measurement_protocol]:" PHP error logs
- Network request failures in browser dev tools

## Future Enhancements

Potential future improvements:
- Enhanced user journey mapping
- A/B testing integration
- Advanced segmentation
- Real-time funnel monitoring dashboard
- Integration with Google Ads enhanced conversions