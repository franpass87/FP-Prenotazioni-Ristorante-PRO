FP Prenotazioni Ristorante PRO – Phase 6 Compatibility Audit
============================================================

Overview
--------
- Introduced network-aware option helpers so multisite installations share configuration, plugin version metadata, and admin notices without duplicating state per site.
- Mirrored plugin settings/storage writes to the network and ensured cache invalidation hooks fire for both site and network option updates.
- Extended transient cleanup to purge matching entries from the `sitemeta` table when the plugin is network activated, avoiding stale caches on multisite environments.

Key updates
-----------
- Added `rbf_get_network_aware_option()`, `rbf_update_network_aware_option()`, and `rbf_delete_network_aware_option()` to transparently read/write/delete values at the appropriate scope.
- Updated settings bootstrap, branding profiles, onboarding, and admin UIs to leverage the new helpers for consistent behaviour on PHP 7.4–8.2 and WordPress 6.x.
- Ensured uninstall routines remove both site and network copies of plugin options and bootstrap markers.

Follow-up
---------
- During the refactoring phase, consider grouping option keys behind a dedicated repository/service so future modules automatically gain multisite compatibility.
- Re-run PHPStan after the refactor to confirm the new helpers resolve previous undefined-function notices related to network option usage.
