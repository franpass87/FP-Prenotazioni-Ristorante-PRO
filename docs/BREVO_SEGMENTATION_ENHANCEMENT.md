# Brevo List Segmentation Enhancement

## Overview
Enhanced the Brevo list segmentation to consider both form compilation language and phone prefix (country code) when determining which Brevo list to use for customer contacts.

## Previous Behavior
- Segmentation was based only on the phone prefix country code
- Italian phone prefix (+39) → Italian Brevo list
- Any other phone prefix → English Brevo list

## New Behavior
Enhanced logic that considers both factors:
1. **If phone prefix is Italian (+39)** → Always use Italian Brevo list (regardless of form language)
2. **If phone prefix is NOT Italian but form is in Italian** → Use Italian Brevo list
3. **If phone prefix is NOT Italian and form is in English** → Use English Brevo list

## Test Cases
| Form Language | Phone Prefix | Brevo List | Reason |
|---------------|--------------|------------|---------|
| Italian | Italian (+39) | Italian | Phone priority |
| English | Italian (+39) | Italian | Phone priority |
| Italian | UK (+44) | Italian | Form fallback |
| English | UK (+44) | English | Consistent |
| Italian | US (+1) | Italian | Form fallback |
| English | US (+1) | English | Consistent |

## Files Modified
- `includes/booking-handler.php` - Enhanced Brevo list segmentation logic
- `includes/integrations.php` - Added documentation comments
- `tests/brevo-segmentation-test.php` - Added comprehensive test suite

## Implementation Details
The enhancement was implemented in the `rbf_handle_booking_submission()` function where the `$brevo_lang` variable is determined. The new logic prioritizes the phone prefix but falls back to form language when the phone prefix is not Italian.

This ensures Italian customers are always captured in the Italian list, while providing appropriate segmentation for international customers based on their form interaction language.