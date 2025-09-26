# Discovery Report – Phase 1

## Plugin Snapshot
- **Name:** FP Prenotazioni Ristorante PRO (`fp-prenotazioni-ristorante-pro.php`).
- **Version declared:** 1.6.3 (PHP ≥7.4, WordPress ≥6.0).
- **Architecture:** procedural modules loaded from `includes/` plus a lightweight service container under `includes/backend` for booking pipelines. Heavy reliance on global helper functions in `includes/utils.php`.
- **Data storage:** Custom CPT `rbf_booking`, multiple bespoke tables for table management and email failover, options under the `rbf_` prefix, transient-based caching for availability and tracking.
- **Interfaces:** Extensive admin UI (calendar, reports, onboarding, settings), frontend shortcodes for booking flows, numerous AJAX endpoints exposed to authenticated and unauthenticated users, WP-CLI commands (`wp rbf ...`).

## High-Priority Findings
1. **Missing schema coverage for booking status table** – Several queries expect a `{prefix}rbf_booking_status` table (`includes/utils.php`, `includes/table-management.php`), yet no creation routine exists. This leads to fallback on post meta and potential performance/consistency issues, and will block future migrations relying on the table.
2. **Monolithic admin/frontend modules** – `includes/admin.php` (~6k LOC) and `includes/frontend.php` (~2k LOC) bundle hooks, rendering, AJAX, and business logic. This makes auditing, testing, and future refactors risky; logic (e.g., exports, cron handlers) lacks separation of concerns.
3. **Inconsistent input normalization** – Some workflows (e.g., `rbf_handle_booking_submission()` and certain admin exports) pass raw `$_POST` arrays downstream without initial `wp_unslash()`/strict sanitization guarantees, depending on deeper layers to cope. Needs verification to avoid regression and ensure consistent security posture.
4. **Caching & cron side effects** – `rbf_clear_transients()` executes `wp_cache_flush()` on version/build changes, which can be expensive on large installs. Cron job `rbf_auto_complete_past_bookings()` compares dates lexicographically against `rbf_data` meta; inconsistent formats (date vs datetime) could leave stale bookings untouched.
5. **Tooling gaps** – No Composer configuration, PHPCS, PHPStan, PHPUnit, or CI workflows are present in the repository despite a sizeable codebase and existing `tests/` helpers. Automated quality gates are absent.
6. **Legacy/demo artifacts in root** – Numerous HTML demo/verification files ship with the plugin, inflating distribution size and risking disclosure of internal debug tooling if deployed as-is. Packaging process should exclude or relocate them.
7. **Security posture to review** – While many AJAX handlers enforce nonces/capabilities, coverage must be verified for every endpoint (`admin_post_*`, tracking analytics, AI suggestions) during the security phase; also ensure consistent escaping in rendered templates.

## Additional Observations
- Activation sets up multiple custom tables and default seating data unconditionally; reruns may duplicate defaults if not guarded tightly.
- Build signature computation traverses whole asset directories; ensure it does not cause noticeable overhead on large installations.
- WP-CLI commands are present but untested; future testing will need to mock WordPress CLI context.

## Suggested Next Steps
- Proceed with Phase 2 to establish linting/analysis baseline (Composer, PHPCS, PHPStan).
- Plan refactor to split admin/frontend monoliths into service classes during dedicated refactoring phase.
- Audit all schema/migration requirements; add booking status table migrations and upgrade routines later in the cycle.
- Prepare to remove QA/demo assets from release packaging in the final phase.
