# FP Prenotazioni Ristorante PRO – Code Map

## Overview
- **Main entry point:** `fp-prenotazioni-ristorante-pro.php` handles environment checks, constant definitions, module loading, activation/deactivation hooks, and shared utilities such as build signature calculation and transient cleanup.
- **Namespace usage:** Most runtime code is organized as global functions with the `rbf_` prefix. A lightweight service container lives under `includes/backend` for booking pipelines.
- **Assets:** Enqueued CSS/JS are stored in `assets/css`, `assets/js`, and vendor bundles in `assets/vendor` (Flatpickr, FullCalendar, intl-tel-input, custom utilities). Build signature includes these folders for cache busting.

## Bootstrap Flow
1. Guard against direct access (`ABSPATH`).
2. Define constants for paths, URLs, plugin version, and minimum requirements.
3. Compute environment requirement errors and deactivate when unmet; render notices in admin.
4. Load `includes/utils.php` early to provide logging (`rbf_log`) and helpers used across modules.
5. Register translations on `plugins_loaded` and clear transients whenever the plugin version/build signature changes.
6. `rbf_load_modules()` requires a fixed list of files in `includes/` covering admin UI, frontend rendering, integrations, and utilities.
7. `rbf_initialize_runtime_environment()` triggers schema verification.
8. Conditional loading of developer test harnesses in `tests/*.php` when `rbf_should_load_admin_tests()` permits it.
9. Activation/deactivation/uninstall hooks handle schema setup/cleanup, cron registration, transient flushing, and CPT removal.

## Custom Database Tables
Schema declared in `includes/table-management.php` and `includes/email-failover.php`:
- `{prefix}rbf_areas`
- `{prefix}rbf_tables`
- `{prefix}rbf_table_groups`
- `{prefix}rbf_table_group_members`
- `{prefix}rbf_table_assignments`
- `{prefix}rbf_slot_versions`
- `{prefix}rbf_email_notifications`
Supporting functions manage schema verification, upgrades, and WP-CLI checks (`wp rbf verify-schema`).

## Custom Post Types & Taxonomies
- **CPT:** `rbf_booking` (private UI, no public query) registered in `includes/admin.php` with capability mapping to a custom capability returned by `rbf_get_booking_capability()`.
- **Taxonomies:** None registered.

## Options, Settings & Transients
- Core settings stored under `rbf_settings` with defaults defined in `includes/utils.php`.
- Administrative notices stored in `rbf_admin_notices`.
- Onboarding state and wizard results stored via options like `rbf_setup_wizard_state`, `rbf_setup_wizard_completed`, `rbf_setup_wizard_result`, `rbf_bootstrap_defaults_seeded`.
- Branding profiles saved in `rbf_brand_profiles`.
- Tracking packages/events stored in `rbf_tracking_packages` and `rbf_recent_tracking_events`.
- Schema verification timestamp stored in `rbf_schema_last_verified`.
- Plugin version/build stored in `rbf_plugin_version` and `rbf_plugin_build_signature`.
- Numerous transients prefixed `rbf_` for availability caching (`rbf_cal_avail_*`, `rbf_times_*`, `rbf_avail_*`), GA4 funnel caching, email failover, etc. Helpers in `includes/frontend.php`, `includes/utils.php`, and `includes/ga4-funnel-tracking.php` manage them.

## Admin Area Integration
Located primarily in `includes/admin.php`:
- Adds custom roles/capabilities during `init` and cleans up on uninstall.
- Registers the `rbf_booking` CPT.
- Creates a top-level admin menu `rbf_calendar` with subpages for calendar, weekly staff agenda, manual bookings, table management, reports, email notifications, export, settings, and tracking validation.
- Enqueues FullCalendar assets (`rbf_enqueue_fullcalendar_assets()`), admin styles/scripts, and localized strings.
- Provides AJAX handlers for booking data (`rbf_get_bookings_for_calendar_callback`, `rbf_update_booking_status_callback`, `rbf_update_booking_data_callback`, `rbf_move_booking_callback`, `rbf_get_weekly_staff_bookings_callback`).
- Implements export (CSV), reporting summaries, capability checks, and table assignment management.
- Schedules daily cron via `rbf_schedule_status_updates()`/`rbf_update_booking_statuses` to auto-update booking statuses.

Additional admin-focused modules:
- `includes/booking-dashboard.php`: renders dashboard widgets and analytics.
- `includes/onboarding.php`: guides setup wizard with notice hooks and option persistence.
- `includes/system-health-dashboard.php` & `includes/site-health.php`: adds Site Health checks and custom admin diagnostics with actions on `admin_post_rbf_health_action`.
- `includes/branding-profiles.php`: manage themeing presets with `admin_post` handlers.
- `includes/accessibility-checker.php`: surfaces accessibility audit UI and settings.
- `includes/ga4-funnel-tracking.php`: analytics admin pages, reports, and AJAX endpoints.
- `includes/tracking-validation.php`, `includes/tracking-presets.php`, `includes/ai-suggestions.php`: provide admin pages/tools with AJAX endpoints for AI/analytics suggestions.

## Frontend Functionality
Implemented in `includes/frontend.php`:
- Registers shortcode handlers:
  - `[customer_booking_management]`
  - `[ristorante_booking_form]`
  - `[anniversary_booking_form]`
  - `[birthday_booking_form]`
  - `[romantic_booking_form]`
  - `[celebration_booking_form]`
  - `[business_booking_form]`
  - `[proposal_booking_form]`
  - `[special_booking_form]`
- Handles rendering of booking forms, management dashboards, and confirmation modals using templates built inline with sanitized HTML helpers.
- Enqueues frontend assets (styles, scripts, Flatpickr, intl-tel-input) with localization data (`wp_localize_script('rbf-booking-form', ...)`).
- Implements AJAX endpoints for availability:
  - `rbf_get_calendar_availability`
  - `rbf_get_availability`
  - `rbf_refresh_calendar`
- Integrates with Brevo/Meta tracking hooks, hybrid tracking utilities, and data-layer events.
- Provides logging utilities for frontend validation, honeypot/antibot logic, and dynamic slot rendering.

## Booking Backend Services (`includes/backend`)
- `bootstrap.php` exposes `rbf_backend()` service locator to resolve booking services.
- `booking` classes handle validation (`class-rbf-booking-request-validator.php`), availability checks (`class-rbf-availability-service.php`), persistence (`class-rbf-booking-repository.php`), notifications (`class-rbf-notification-service.php`), tracking data (`class-rbf-tracking-builder.php`), and pipeline orchestration (`class-rbf-booking-pipeline.php`).
- Services rely on utility helpers from `includes/utils.php` and WordPress functions (`wp_insert_post`, metadata APIs, mail integrations).

## Integrations & Utilities
- `includes/integrations.php`: connectors for Brevo, Meta, and other marketing platforms.
- `includes/email-failover.php`: manages a local email log table and failover queue, schedules cleanup via `rbf_cleanup_email_notifications_event`.
- `includes/optimistic-locking.php`: prevents double-booking with transient-based locks.
- `includes/privacy.php`: registers privacy policy content and exporters.
- `includes/tracking-enhanced-integration.php`: advanced tracking hooks and listeners.
- `includes/wp-cli.php`: defines `wp rbf` commands (`check-environment`, `verify-schema`, `clear-cache`, `reschedule-cron`, email log pruning).
- `includes/utils.php`: central helper library (settings defaults, sanitizers, date helpers, logging, capability names, admin notices, caching utilities, translation wrappers, etc.).

## Cron Jobs & Scheduled Events
- `rbf_update_booking_statuses`: scheduled daily to adjust booking status post-event (auto-cancel/complete) – registered in `includes/admin.php`.
- `rbf_cleanup_email_notifications_event`: scheduled daily to prune email failover logs (`includes/email-failover.php`).
- Support functions ensure events are registered on activation and cleared on deactivation/uninstall.

## CLI & Developer Tooling
- WP-CLI command `rbf` with subcommands (`check-environment`, `verify-schema`, `clear-cache`, `reschedule-cron`, `prune-email-log`).
- Developer test harness files in `/tests` conditionally loaded when debug mode is enabled.

## External Integrations & APIs
- Sends REST requests to Brevo and other marketing APIs (through `wp_remote_post` in integrations modules).
- Uses GA4/Meta pixel event dispatchers via AJAX endpoints (`rbf_track_ga4_event`, `rbf_get_booking_completion_data`).
- Custom logging through `rbf_log()` writing to `error_log` when enabled in settings.

## Key Assets & Templates
- Admin and frontend scripts: `assets/js/*.js` (form validation, calendar UI, tracking utilities).
- Stylesheets: `assets/css/*.css` and vendor CSS bundles.
- HTML demos and verification files in repo root used for manual QA (non-runtime).

## High-Risk Interaction Points
- Numerous AJAX endpoints exposed to unauthenticated users (calendar, suggestions, tracking) – rely on nonces and sanitization.
- Direct database table management for seating layout and email failover.
- Cron tasks manipulating booking status and sending notifications.
- Email sending with fallback queue and manual retry logic.

