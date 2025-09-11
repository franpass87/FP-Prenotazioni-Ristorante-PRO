# Hybrid Tracking System Documentation

## Overview

The restaurant booking plugin now implements a comprehensive hybrid tracking system that combines Google Tag Manager (GTM) and Google Analytics 4 (GA4) without event duplication, while providing enhanced conversions for Google Ads and server-side backup via Facebook Conversion API.

## Key Features

### ✅ Anti-Duplication System
- **Conditional Event Sending**: In hybrid mode, events are sent only to dataLayer for GTM processing
- **Automatic gtag() Disabling**: Direct GA4 calls are disabled when GTM is handling tracking
- **Unique Event IDs**: Cross-platform deduplication using unique event identifiers
- **GTM-Specific Keys**: Special dataLayer keys for GTM trigger conditions

### ✅ Enhanced Conversions Support
- **Customer Data Hashing**: SHA256 hashing of email, phone, names for privacy
- **Google Ads Integration**: Automatic enhanced conversions when traffic source is Google Ads
- **Conditional Loading**: Enhanced conversions only fire for appropriate traffic sources

### ✅ Facebook Conversion API
- **Server-Side Backup**: Automatic CAPI events for browser-side Facebook Pixel deduplication
- **Event ID Sharing**: Same event ID used for Pixel and CAPI to prevent duplication
- **Customer Data Matching**: Hashed customer data for improved attribution

### ✅ Attribution Bucket System
- **Priority-Based Attribution**: gclid > fbclid > organic
- **Dual Bucket Tracking**: Standard bucket (gads/fbads/organic) + detailed bucket
- **Cross-Platform Consistency**: Same attribution logic across all platforms

## Configuration

### Hybrid Mode Setup

1. **Enable Hybrid Mode**: Check "Modalità ibrida GTM + GA4" in admin settings
2. **Configure IDs**: Set both GTM container ID and GA4 measurement ID
3. **GTM Configuration**: Ensure GTM container does NOT have GA4 configuration tag that triggers on purchase events
4. **Validation**: Use built-in tracking validation tool

### Required Settings

```php
// WordPress Admin Settings
$options = [
    'gtm_id' => 'GTM-XXXXXXX',
    'ga4_id' => 'G-XXXXXXXXXX',
    'gtm_hybrid' => 'yes',
    'meta_pixel_id' => '1234567890',
    'meta_access_token' => 'EAAxxxxx...',
    'ga4_api_secret' => 'xxxxxx'
];
```

## Event Flow

### Standard Mode (GA4 Only)
```
Booking Completion → JavaScript → gtag('event', 'purchase') → GA4
```

### Hybrid Mode (GTM + GA4)
```
Booking Completion → JavaScript → dataLayer.push() → GTM → GA4 Tag (in GTM)
                              → NO direct gtag() calls (prevented)
```

### Facebook Tracking
```
Booking Completion → Browser: fbq('track', 'Purchase') → Facebook Pixel
                  → Server: CAPI Request → Facebook Conversion API
                  (Same eventID for deduplication)
```

## Events Tracked

### 1. Standard GA4 Purchase Event
```javascript
gtag('event', 'purchase', {
  transaction_id: 'rbf_123',
  value: 50.00,
  currency: 'EUR',
  items: [{
    item_id: 'booking_cena',
    item_name: 'Prenotazione cena',
    category: 'booking',
    quantity: 2,
    price: 25.00
  }],
  bucket: 'gads', // Normalized attribution
  vertical: 'restaurant',
  // Enhanced Conversions (hashed)
  customer_email: 'hash_sha256',
  customer_phone: 'hash_sha256',
  customer_first_name: 'hash_sha256',
  customer_last_name: 'hash_sha256'
});
```

### 2. Custom Restaurant Booking Event
```javascript
gtag('event', 'restaurant_booking', {
  transaction_id: 'rbf_123',
  value: 50.00,
  currency: 'EUR',
  bucket: 'gads',          // Standard attribution
  traffic_bucket: 'direct', // Detailed attribution
  meal: 'cena',
  people: 2,
  vertical: 'restaurant',
  booking_date: '2024-01-15',
  booking_time: '20:00'
});
```

### 3. Google Ads Conversion (Conditional)
```javascript
// Only fires when traffic source is Google Ads and not in hybrid mode
gtag('event', 'conversion', {
  send_to: 'AW-CONVERSION_ID/CONVERSION_LABEL',
  transaction_id: 'rbf_123',
  value: 50.00,
  currency: 'EUR',
  customer_data: {
    email_address: 'hash_sha256',
    phone_number: 'hash_sha256',
    first_name: 'hash_sha256',
    last_name: 'hash_sha256'
  }
});
```

### 4. Facebook Events
```javascript
// Browser-side
fbq('track', 'Purchase', {
  value: 50.00,
  currency: 'EUR',
  content_type: 'product',
  content_name: 'Restaurant Booking'
}, { eventID: 'unique_event_id' });

// Server-side CAPI (automatic)
// Same eventID for deduplication
```

## Deduplication Mechanisms

### 1. GTM Hybrid Mode
- Events sent only to dataLayer when GTM is present
- Direct gtag() calls disabled automatically
- Special GTM keys: `gtm_uniqueEventId`

### 2. Event ID System
```javascript
// Format: rbf_eventtype_sessionid_timestamp_microseconds
eventId = `rbf_purchase_${sessionId}_${timestamp}_${microseconds}`;
```

### 3. Facebook Pixel + CAPI
- Same `eventID` used for browser and server events
- Facebook automatically deduplicates based on `eventID`

### 4. Google Ads Enhanced Conversions
- Uses `transaction_id` for deduplication
- Customer data hashed for privacy compliance

## Testing and Validation

### Built-in Validation Tool
Access via: **WordPress Admin → Prenotazioni → Tracking Validation**

The tool checks:
- ✅ Configuration completeness
- ✅ Hybrid mode setup
- ✅ Potential duplication risks
- ✅ API connectivity
- ✅ Event flow description

### Manual Testing Checklist

1. **Google Analytics DebugView**
   - Enable DebugView in GA4
   - Complete a test booking
   - Verify no duplicate purchase events

2. **Facebook Events Manager**
   - Check Events Manager for Pixel events
   - Verify CAPI events appear (if configured)
   - Confirm no duplication between Pixel and CAPI

3. **Google Ads Conversion Tracking**
   - Test with `gclid` parameter
   - Verify enhanced conversions data appears
   - Check Google Ads conversion reports

4. **Browser Console Testing**
   - Look for "RBF GA4 Funnel:" messages
   - Verify hybrid mode detection works
   - Check event deduplication logs

### Test URLs

```
// Test Google Ads traffic
https://yoursite.com/booking?gclid=test123

// Test Facebook traffic  
https://yoursite.com/booking?fbclid=test456

// Test organic traffic
https://yoursite.com/booking
```

## Troubleshooting

### Common Issues

1. **Duplicate Events in GA4**
   - Check GTM container doesn't have GA4 tag triggering on purchase
   - Verify hybrid mode is enabled correctly
   - Review GTM trigger conditions

2. **Enhanced Conversions Not Working**
   - Update Google Ads conversion ID in tracking code
   - Verify customer data is being captured
   - Check data hashing implementation

3. **Facebook CAPI Failing**
   - Verify Meta Access Token in settings
   - Check WordPress error logs
   - Test API connectivity

4. **Attribution Issues**
   - Verify UTM parameters are captured
   - Check bucket normalization logic
   - Review gclid/fbclid detection

### Debug Information

Enable WordPress debug mode and check for:
- "RBF GA4 Funnel:" JavaScript messages
- "Facebook CAPI error:" PHP error logs
- "Google Ads conversion:" tracking logs

## Best Practices

### For Optimal Setup

1. **GTM Configuration**
   - Use GTM for complex tracking scenarios
   - Disable GA4 config tag in GTM when using hybrid mode
   - Use dataLayer events for trigger conditions

2. **Privacy Compliance**
   - Customer data is automatically hashed
   - Respect consent management systems
   - Use conditional loading based on consent

3. **Performance Optimization**
   - Scripts load asynchronously
   - Events batched where possible
   - Minimal impact on page speed

4. **Testing Workflow**
   - Always test in staging environment
   - Use DebugView for real-time validation
   - Verify attribution across all sources

## Migration Guide

### From Standard to Hybrid Mode

1. Enable hybrid mode in plugin settings
2. Configure GTM container ID
3. Remove GA4 configuration tag from GTM (if present)
4. Test booking flow end-to-end
5. Verify no event duplication

### Rollback Procedure

1. Disable hybrid mode in plugin settings
2. Re-enable GA4 configuration tag in GTM (if needed)
3. Clear caches and test

## API Reference

### PHP Functions

```php
// Check if hybrid mode is enabled
rbf_is_gtm_hybrid_mode(): bool

// Validate tracking configuration
rbf_validate_tracking_setup(): array

// Send Facebook CAPI event
rbf_send_facebook_capi_event($booking_id, $pixel_id, $access_token, $event_id, $event_data): void

// Generate debug information
rbf_generate_tracking_debug_info(): array
```

### JavaScript Objects

```javascript
// Main tracking object
window.rbfFunnelTracker = {
  sessionId: string,
  trackEvent: function(name, params, options),
  generateEventId: function(type),
  log: function(message, data)
};

// Configuration object
window.rbfGA4Funnel = {
  sessionId: string,
  measurementId: string,
  gtmHybrid: boolean,
  debug: boolean
};
```