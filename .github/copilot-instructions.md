# FP-Prenotazioni-Ristorante-PRO - WordPress Restaurant Booking Plugin

**Always reference these instructions first and fallback to search or bash commands only when you encounter unexpected information that does not match the info here.**

This is a WordPress restaurant booking plugin with advanced marketing integrations (GA4, Meta Pixel, Brevo) and a modular architecture. The plugin handles restaurant reservations with multi-language support (IT/EN), debug monitoring, and performance tracking.

## Working Effectively

### Plugin Installation & Setup
**WordPress Environment Required** - This plugin requires a working WordPress installation to function.
- Upload plugin folder to `/wp-content/plugins/fp-prenotazioni-ristorante-pro/`
- Activate plugin via WordPress admin: Plugins → Installed Plugins → Activate "Prenotazioni Ristorante Completo"
- Configure via WordPress admin: Navigate to "Prenotazioni" menu
- Deploy booking form: Add shortcode `[ristorante_booking_form]` to any WordPress page

### Development Environment Setup
**NEVER CANCEL WordPress operations** - WordPress operations can take 2-5 minutes for plugin activation and configuration.
- **WordPress Required**: This plugin cannot run standalone - it requires WordPress 5.0+ with PHP 7.4+
- **No Build Process**: Plugin is deployed directly as PHP/JS/CSS files - no compilation needed
- **No Test Suite**: Plugin uses manual testing procedures described below

### PHP Validation
Always validate PHP syntax before making changes:
```bash
# Check main plugin file - completes in 2-3 seconds
php -l fp-prenotazioni-ristorante-pro.php

# Check all module files - NEVER CANCEL, takes 30-60 seconds with 9 modules
find includes/ -name "*.php" -exec php -l {} \;

# Alternative: Check specific module (faster for targeted changes)
php -l includes/booking-handler.php
```

### Asset Validation 
Verify frontend assets load correctly:
```bash
# Check if assets exist (quick validation)
ls -la assets/css/frontend.css assets/js/frontend.js

# Validate JavaScript syntax (if Node.js available)  
node -c assets/js/frontend.js 2>/dev/null || echo "Node.js not available - use browser console instead"
```

## File Structure & Navigation

### Core Files (Always reference these locations)
```
fp-prenotazioni-ristorante-pro/
├── fp-prenotazioni-ristorante-pro.php    # Main plugin entry (113 lines)
├── includes/                             # Core modules
│   ├── utils.php                        # Utilities & translations
│   ├── admin.php                        # WordPress admin interface
│   ├── frontend.php                     # Shortcode & frontend assets  
│   ├── booking-handler.php              # Form submission logic
│   ├── integrations.php                 # GA4/Meta/Brevo integrations
│   ├── debug-logger.php                 # Advanced debug system
│   ├── performance-monitor.php          # Performance tracking
│   ├── utm-validator.php                # UTM parameter validation
│   └── debug-dashboard.php              # Admin debug interface
└── assets/                              # Frontend resources
    ├── css/
    │   ├── admin.css                    # Admin styling
    │   └── frontend.css                 # Booking form styling
    └── js/
        ├── admin.js                     # Admin JavaScript
        └── frontend.js                  # Booking form logic & UTM capture
```

### Module Loading Order (Critical for debugging)
The plugin loads modules in this specific order via `rbf_load_modules()`:
1. debug-logger.php (debug system first)
2. performance-monitor.php 
3. utm-validator.php
4. debug-dashboard.php
5. utils.php (utilities & translations)
6. admin.php (WordPress admin interface)
7. frontend.php (shortcode & assets)
8. booking-handler.php (form processing)
9. integrations.php (marketing integrations)

## Testing & Validation

### Static Validation (No WordPress Required)
**Always run these checks before deploying to WordPress:**

```bash
# PHP syntax validation - NEVER CANCEL, takes 30-60 seconds for all files
php -l fp-prenotazioni-ristorante-pro.php
find includes/ -name "*.php" -exec php -l {} \;

# Check file structure integrity 
ls -la includes/ assets/css/ assets/js/

# Verify required files exist
test -f includes/admin.php && echo "Admin module: OK" || echo "Admin module: MISSING"
test -f includes/frontend.php && echo "Frontend module: OK" || echo "Frontend module: MISSING"
test -f includes/booking-handler.php && echo "Booking handler: OK" || echo "Booking handler: MISSING"
test -f assets/css/frontend.css && echo "Frontend CSS: OK" || echo "Frontend CSS: MISSING"
test -f assets/js/frontend.js && echo "Frontend JS: OK" || echo "Frontend JS: MISSING"

### WordPress Plugin Validation Commands
**Use these commands to understand plugin structure and validate changes:**

```bash
# Count total PHP functions (should be ~30+ core functions)
grep -c "^function rbf_" includes/*.php

# Find all WordPress hooks used (understand plugin integration points)
grep -r "add_action\|add_filter\|add_shortcode" includes/ --include="*.php"

# Check debug system integration
grep -r "RBF_DEBUG\|RBF_Performance" includes/ --include="*.php" | wc -l

# Validate marketing integration completeness  
grep -r "gtag\|fbq\|brevo" includes/ assets/ --include="*.php" --include="*.js"

# Check for AJAX endpoints (important for booking submission)
grep -r "wp_ajax" includes/ --include="*.php"

# Find all custom post types and settings
grep -r "register_post_type\|register_setting" includes/ --include="*.php"
```

## Timing Expectations & Cancellation Guidelines

### Command Execution Times
**NEVER CANCEL these operations - they have expected completion times:**

| Command | Expected Time | Notes |
|---------|---------------|-------|
| WordPress plugin activation | 2-5 minutes | Includes custom post type registration and rewrite rules flush |
| PHP syntax validation (all files) | 30-60 seconds | 9 PHP modules + main file |  
| Debug dashboard data loading | 1-2 minutes | Queries performance logs and statistics |
| Complete booking submission | 15-30 seconds | Includes marketing API calls (GA4, Meta, Brevo) |
| Brevo API calls | 5-15 seconds | Email automation triggers |
| Meta CAPI server-side events | 3-10 seconds | Facebook server-side API calls |
| Plugin settings save | 10-30 seconds | Database writes and cache clearing |

### WordPress Environment Setup Time
**NEVER CANCEL WordPress setup operations:**
- Fresh WordPress installation: 10-15 minutes
- Plugin upload and activation: 2-5 minutes  
- Initial configuration (settings): 3-5 minutes
- First booking test: 2-3 minutes

**Total setup time for complete testing: 20-30 minutes**
```
```

### Manual Testing Scenarios
**ALWAYS test these scenarios after making changes in WordPress:**

#### 1. Basic Booking Flow Test
- Navigate to page with `[ristorante_booking_form]` shortcode
- Test complete booking: Select meal type → Choose date → Select time → Fill details → Submit
- Verify booking appears in WordPress admin: Prenotazioni → Gestione Prenotazioni
- **Expected Time**: 3-5 minutes for full flow

#### 2. Debug System Test
```php
// Enable debug mode in wp-config.php
define('RBF_DEBUG', true);
define('RBF_LOG_LEVEL', 'INFO');
```
- Complete a booking while debug enabled
- Check WordPress admin: Prenotazioni → Debug Dashboard
- Verify logs show booking events and performance metrics
- **Expected Time**: 2-3 minutes

#### 3. Multi-language Test
- Test booking form in both IT and EN languages
- Verify date picker localization and translated labels
- **Expected Time**: 2-3 minutes per language

### Debug Mode Validation
Always enable debug mode when developing:
```php
// Method 1: wp-config.php (preferred)
define('RBF_DEBUG', true);
define('RBF_LOG_LEVEL', 'DEBUG'); // DEBUG, INFO, WARNING, ERROR

// Method 2: Plugin settings (via WordPress admin)
// Navigate to: Prenotazioni → Impostazioni → Debug settings
```

### Performance Monitoring
When debug enabled, monitor via WordPress admin: Prenotazioni → Debug Dashboard:
- API call success rates (Meta CAPI, Brevo, GA4)
- UTM parameter validation results  
- Execution times for booking operations
- Memory usage statistics

## Configuration & Settings

### Core Settings (WordPress Admin → Prenotazioni)
- **Orari Servizio**: Configure time slots for lunch/dinner/aperitivo
- **Capienza Servizi**: Set maximum capacity per service type
- **Valori Economici**: Set monetary values for conversion tracking

### Marketing Integrations
- **Google Analytics 4**: GA4 Measurement ID for ecommerce tracking
- **Meta Pixel**: Pixel ID + Access Token for CAPI (server-side events)
- **Brevo**: API Key for email automation
- **UTM Tracking**: Automatic source attribution and classification

### Debug Configuration
```php
// Debug levels (set in wp-config.php or admin)
RBF_LOG_LEVEL options:
- 'DEBUG': All events (verbose, use for development)
- 'INFO': Standard events (default)
- 'WARNING': Warnings and errors only
- 'ERROR': Errors only

// Auto-cleanup (admin configurable)
- Log retention: 1-30 days (default: 7 days)
- Auto-cleanup: Enabled/disabled via admin
```

## Common Development Tasks

### Adding New Booking Fields
1. Modify `includes/frontend.php` - add field to shortcode output
2. Update `assets/js/frontend.js` - add client-side validation
3. Modify `includes/booking-handler.php` - add server-side processing
4. **Always test complete booking flow after changes**

### Modifying Marketing Integrations
1. Edit `includes/integrations.php` for GA4/Meta/Brevo logic
2. Update `assets/js/frontend.js` for client-side tracking
3. **Always test with debug enabled** to verify API calls
4. Check debug dashboard for integration success rates

### Adding Translations
1. Edit `includes/utils.php` - add to `rbf_translate_string()` function
2. Test in both IT and EN language contexts
3. **Always verify** translations appear in booking form

## Troubleshooting

### Common Issues
- **Plugin doesn't activate**: Check PHP version (7.4+ required) and WordPress version (5.0+)
- **Booking form doesn't appear**: Verify shortcode `[ristorante_booking_form]` is correctly placed
- **Debug dashboard missing**: Enable debug mode and verify admin permissions
- **Marketing events not tracking**: Check API keys and debug dashboard for integration errors

### Debug Information Locations
- WordPress Admin: Prenotazioni → Debug Dashboard (when debug enabled)
- WordPress debug.log (if WP_DEBUG enabled)
- Browser console for client-side JavaScript errors
- Network tab for AJAX request failures

### Log Analysis
```bash
# Debug logs format (JSON structured)
{
  "timestamp": "2024-01-01T12:00:00Z",
  "event": "booking_submitted",
  "level": "INFO",
  "data": {
    "booking_id": 123,
    "meal": "cena",
    "utm_source": "google"
  }
}
```

## WordPress Integration Points

### Hooks & Filters Used
- `plugins_loaded`: Module initialization
- `wp_enqueue_scripts`: Asset loading (conditional - only on pages with shortcode)
- `admin_menu`: Admin interface registration
- `wp_ajax_*`: AJAX endpoints for booking submission and admin functions

### Database Integration
- **Custom Post Type**: 'ristorante_booking' for storing reservations
- **Options**: 'rbf_settings' for plugin configuration
- **Post Meta**: Booking details and tracking data
- **Transients**: Temporary caching for tracking data (15-minute TTL)

### Shortcodes
- `[ristorante_booking_form]`: Main booking form
- `[customer_booking_management]`: Customer booking lookup/management

## Security Considerations

### Data Sanitization
All user inputs are sanitized using WordPress functions:
- `sanitize_text_field()` for text inputs
- `sanitize_email()` for email addresses  
- `wp_verify_nonce()` for CSRF protection
- `current_user_can()` for permission checks

### External API Security
- All API credentials stored in WordPress options (encrypted)
- API calls use WordPress HTTP API with timeout limits
- Server-side validation for all marketing integration data

## Best Practices When Modifying

### Code Standards
- Follow WordPress coding standards
- Use WordPress sanitization and validation functions
- Maintain modular architecture - keep related functions in appropriate modules
- Always add debug logging for new features when `RBF_DEBUG` enabled

### Testing Approach
1. **Enable debug mode** before making changes
2. **Test complete user workflows** - don't just test individual functions
3. **Check debug dashboard** for performance impact and errors
4. **Validate PHP syntax** before committing changes
5. **Test in both IT and EN languages** for multi-language features

### Performance Considerations
- Plugin uses conditional loading - scripts only load on pages with shortcodes
- Debug mode adds overhead - disable in production
- Marketing integrations use transient caching to reduce API calls
- UTM validation includes performance timing to detect bottlenecks

**Remember: This is a production WordPress plugin with real restaurant bookings - always test thoroughly and enable debug monitoring when making changes.**

## Quick Reference Commands

### Daily Development Workflow
```bash
# 1. Validate syntax before changes (30-60 seconds)
find includes/ -name "*.php" -exec php -l {} \;

# 2. Check plugin structure integrity (5 seconds)  
ls -la includes/ assets/css/ assets/js/

# 3. Count functions after adding new ones (expected: 50+ total)
grep -c "^function rbf_" includes/*.php

# 4. After WordPress changes - check debug dashboard
# WordPress Admin → Prenotazioni → Debug Dashboard

# 5. Validate booking flow works end-to-end (3-5 minutes)
# Create test booking via [ristorante_booking_form] shortcode
```

### Emergency Troubleshooting
```bash
# Plugin won't activate - check basic requirements
php -v  # Should be 7.4+
php -l fp-prenotazioni-ristorante-pro.php  # Should show "No syntax errors"

# Form not appearing - verify shortcode  
grep -n "ristorante_booking_form" includes/frontend.php  # Should find shortcode registration

# Debug issues - check debug system
grep -r "RBF_DEBUG" includes/ --include="*.php" | head -5

# Marketing not tracking - find integration points
grep -r "gtag\|fbq\|brevo" includes/ assets/ --include="*.php" --include="*.js" | wc -l
```

**Critical: Always enable RBF_DEBUG=true when developing and check the debug dashboard for real-time validation of your changes.**