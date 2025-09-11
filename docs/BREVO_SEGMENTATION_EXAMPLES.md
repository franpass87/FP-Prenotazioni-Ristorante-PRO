# Brevo List Segmentation - Usage Examples

## Practical Examples of the Enhanced Segmentation

### Example 1: Italian Customer
**Scenario**: Italian customer using Italian form with Italian phone number
- Form Language: `it` (Italian interface)
- Phone Number: `+39 333 123 4567` (Italian prefix)
- **Result**: → Italian Brevo list (`brevo_list_it`)
- **Reason**: Phone prefix is Italian

### Example 2: Italian Tourist/Expat
**Scenario**: Italian customer abroad using English form with Italian phone
- Form Language: `en` (English interface) 
- Phone Number: `+39 333 123 4567` (Italian prefix)
- **Result**: → Italian Brevo list (`brevo_list_it`)
- **Reason**: Phone prefix takes priority (Italian)

### Example 3: Foreign Tourist in Italy
**Scenario**: UK tourist using Italian form with UK phone
- Form Language: `it` (Italian interface)
- Phone Number: `+44 7700 900000` (UK prefix)
- **Result**: → Italian Brevo list (`brevo_list_it`)
- **Reason**: Form language fallback (Italian interface)

### Example 4: Foreign Customer
**Scenario**: UK customer using English form with UK phone
- Form Language: `en` (English interface)
- Phone Number: `+44 7700 900000` (UK prefix)
- **Result**: → English Brevo list (`brevo_list_en`)
- **Reason**: Consistent non-Italian preferences

### Example 5: US Customer
**Scenario**: US customer using English form with US phone
- Form Language: `en` (English interface)
- Phone Number: `+1 555 123 4567` (US prefix)
- **Result**: → English Brevo list (`brevo_list_en`)
- **Reason**: Consistent non-Italian preferences

## Technical Implementation

The logic is implemented in `includes/booking-handler.php`:

```php
// Determine Brevo list based on both form language and phone prefix
if ($country_code === 'it') {
    // Italian phone prefix → always Italian list
    $brevo_lang = 'it';
} else {
    // Non-Italian phone prefix → use form language to determine list
    $brevo_lang = ($lang === 'it') ? 'it' : 'en';
}
```

This ensures maximum capture of Italian customers while providing appropriate segmentation for international visitors based on their interaction preferences.