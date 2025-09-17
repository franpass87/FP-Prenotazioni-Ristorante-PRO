# Brevo List Segmentation Enhancement

## Overview
Restored Brevo list segmentation to evaluate the submitted form language first and then apply the Italian phone override. This keeps Italian-speaking users on the Italian automation even when they enter a foreign phone number, while still prioritising Italian numbers when present.

## Previous Behavior
- Segmentation relied solely on the phone prefix (country code)
- Italian phone prefix (+39) → Italian Brevo list
- Any non-Italian phone prefix → English Brevo list

## New Behavior
Updated logic that blends form language with phone prefix:
1. Determine the default list from the form language (Italian forms default to the Italian list, English forms default to the English list)
2. If the detected phone prefix is Italian (+39), override to the Italian list regardless of form language
3. If the phone prefix is not Italian, keep the language-driven default from step 1

## Test Cases
| Form Language | Phone Prefix | Brevo List | Reason |
|---------------|--------------|------------|---------|
| English | Italian (+39) | Italian | Italian phone overrides form |
| Italian | Italian (+39) | Italian | Italian phone |
| Italian | UK (+44) | Italian | Italian form default |
| English | UK (+44) | English | English form default |
| Italian | US (+1) | Italian | Italian form default |
| English | US (+1) | English | English form default |

## Files Modified
- `includes/booking-handler.php` - Restored blended segmentation logic
- `includes/integrations.php` - Added documentation comments
- `tests/brevo-segmentation-test.php` - Updated test suite
- `docs/BREVO_SEGMENTATION_ENHANCEMENT.md` - Updated documentation

## Implementation Details
The enhancement is implemented in the `rbf_validate_request()` helper, where the `$brevo_lang` variable is determined. The refreshed logic normalises the form language, applies it as the baseline segmentation value, and then enforces the Italian phone override when needed.

This keeps Brevo automations aligned with how the booking forms are localised while still guaranteeing that Italian phone numbers always receive the Italian communication flow.