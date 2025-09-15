# Brevo List Segmentation Enhancement

## Overview
Simplified the Brevo list segmentation to use only the phone prefix (country code) when determining which Brevo list to use for customer contacts.

## Previous Behavior
- Segmentation was based on both phone prefix and form compilation language
- Italian phone prefix (+39) → Italian Brevo list
- Non-Italian phone prefix + Italian form → Italian Brevo list
- Non-Italian phone prefix + English form → English Brevo list

## New Behavior
Simplified logic based only on phone prefix:
1. **If phone prefix is Italian (+39)** → Use Italian Brevo list
2. **If phone prefix is NOT Italian** → Use English Brevo list (regardless of form language)

## Test Cases
| Form Language | Phone Prefix | Brevo List | Reason |
|---------------|--------------|------------|---------|
| Italian | Italian (+39) | Italian | Italian phone |
| English | Italian (+39) | Italian | Italian phone |
| Italian | UK (+44) | English | Non-Italian phone |
| English | UK (+44) | English | Non-Italian phone |
| Italian | US (+1) | English | Non-Italian phone |
| English | US (+1) | English | Non-Italian phone |

## Files Modified
- `includes/booking-handler.php` - Simplified Brevo list segmentation logic
- `includes/integrations.php` - Added documentation comments
- `tests/brevo-segmentation-test.php` - Updated test suite
- `docs/BREVO_SEGMENTATION_ENHANCEMENT.md` - Updated documentation

## Implementation Details
The enhancement is implemented in the `rbf_validate_request()` helper, where the `$brevo_lang` variable is determined. The new logic uses only the phone prefix for segmentation, making the system simpler and more predictable.

This ensures clear segmentation based on phone country codes, with all non-Italian phone numbers directed to the English list.