# FP Prenotazioni Ristorante PRO - Modular Structure

This plugin has been refactored from a monolithic structure (1162 lines) into a modular architecture for better maintainability and organization.

## File Structure

### Main Plugin File
- `fp-prenotazioni-ristorante-pro.php` (60 lines) - Main plugin file with header and module loading

### Modules (includes/ directory)
- `utils.php` (131 lines) - Utility functions, timezone handling, and translations
- `admin.php` (397 lines) - Admin functionality, settings, calendar, and backend forms
- `frontend.php` (258 lines) - Frontend shortcode, form rendering, and assets
- `booking-handler.php` (215 lines) - Form submission processing and AJAX handlers
- `integrations.php` (226 lines) - Third-party integrations (GA4, Meta Pixel, Brevo, Email)

### Backup
- `fp-prenotazioni-ristorante-pro-original.php` - Original monolithic file (excluded from git)

## Benefits of Modular Structure

1. **Better Organization**: Related functionality is grouped together
2. **Easier Maintenance**: Changes can be made to specific modules without affecting others
3. **Improved Readability**: Smaller files are easier to understand and navigate
4. **Separation of Concerns**: Each module has a specific responsibility
5. **Easier Testing**: Individual modules can be tested in isolation

## Module Dependencies

- `utils.php`: Base utilities used by all modules
- `admin.php`: Depends on utils.php for translations and settings
- `frontend.php`: Depends on utils.php for translations and settings
- `booking-handler.php`: Uses functions from frontend.php and integrations.php
- `integrations.php`: Standalone module for third-party services

## Loading Order

Modules are loaded during the `init` action with priority 1 to ensure all WordPress core functions are available.