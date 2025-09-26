# Iteration 2 – Discovery Report

## Context
A follow-up review reopened the iterative hardening playbook after the original 1.7.0 release cut. This iteration restarts the phased audit with the existing codebase as the baseline so later phases can focus on closing the remaining lint, static analysis, and maintenance gaps before cutting another release candidate.

## Architecture Notes
- `fp-prenotazioni-ristorante-pro.php` still orchestrates bootstrap, module loading, requirement enforcement, and activation hooks, delegating contextual includes to the `RBF_Module_Loader`.【F:fp-prenotazioni-ristorante-pro.php†L1-L184】
- Runtime logic continues to live in large, prefixed function collections under `includes/`, with the booking back-end pipeline organized into service classes (`includes/backend`) while admin/front-end layers remain largely procedural (`includes/admin.php`, `includes/frontend.php`).【F:includes/admin.php†L1-L120】【F:includes/frontend.php†L1-L120】
- Tooling remains Composer-driven with PHPCS, PHPStan, and PHPUnit scripts defined but previously left failing because of outstanding violations (822 level-6 PHPStan findings noted in the prior iteration’s logs).【F:composer.json†L1-L25】【F:docs/audit/linters.txt†L20-L27】

## Key Risks Identified
1. **Security review backlog across admin handlers.** The `admin_post` and AJAX callbacks in modules such as `includes/branding-profiles.php`, `includes/system-health-dashboard.php`, and the calendar tooling still unslash raw payload arrays before delegating to custom helpers, so we need to catalogue each nested field and centralise sanitisation/escaping work during Phase 4 (for example `rbf_update_booking_data_callback` pulls the entire `booking_data` array via `wp_unslash` before passing it to `rbf_sanitize_input_fields`).【F:includes/branding-profiles.php†L170-L239】【F:includes/system-health-dashboard.php†L161-L200】【F:includes/admin.php†L2725-L2760】
2. **Overgrown procedural modules.** `includes/admin.php` and `includes/utils.php` each exceed several thousand lines with mixed concerns (CPT registration, AJAX endpoints, cron, option helpers). This size complicates linting, testing, and static analysis, and it impedes migration toward namespaced, testable units for Phase 7 refactors.【F:includes/admin.php†L1-L240】【F:includes/utils.php†L1-L200】
3. **Transient invalidation pressure.** Cache clearing for availability data issues multiple wildcard DELETE queries against `wp_options` on every booking change (`rbf_clear_calendar_cache()` → `rbf_delete_transients_like()`), which can become expensive on high-volume sites and will need optimisation during the performance phase.【F:includes/frontend.php†L1804-L1852】【F:includes/utils.php†L1550-L1575】
4. **Static analysis backlog.** The previous cycle recorded 822 PHPStan level-6 issues and outstanding PHPCS warnings; without addressing them the CI workflow (`.github/workflows/ci.yml`) will continue failing, blocking automated quality gates. Tackling this requires staged refactors plus baseline suppressions in future phases.【F:docs/audit/linters.txt†L20-L27】【F:.github/workflows/ci.yml†L1-L43】

## Proposed Next Steps
- **Phase 2:** Introduce incremental PHPCS autofixes targeting `includes/admin.php` subsets and begin extracting shared helpers to shrink the file. Evaluate PHPStan baseline generation to unblock CI while we chip away at the violations.
- **Phase 3:** Re-enable the runtime logger smoke tests in a vanilla WP install to capture any new notices introduced since the last release cut.
- **Phase 4:** Inventory every `admin_post` handler and AJAX endpoint for consistent nonce + capability enforcement; add request object wrappers where sanitization is ad-hoc.
- **Phase 5+:** Prioritize caching reviews around availability builders, audit transients, and ensure the upgrade manager flushes relevant caches after schema changes.

All subsequent phases will append reports under `docs/audit/iteration-2/` to keep iteration boundaries explicit.
