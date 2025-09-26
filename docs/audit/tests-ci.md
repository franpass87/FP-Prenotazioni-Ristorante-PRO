# Phase 8 – Tests & Continuous Integration

## PHPUnit Harness
- Added `phpunit/phpunit` as a dev dependency and wired the `composer test:unit` script.
- Introduced a dedicated PHPUnit bootstrap that defines `ABSPATH` and prepares shared globals used in fixtures.
- Created regression tests for the contextual module loader to verify context detection, duplicate prevention, and path normalization.

## Test Coverage Targets
- Configured `phpunit.xml.dist` to collect coverage for the `includes/` directory while excluding loader index stubs.
- Fixtures are kept under `tests/phpunit/fixtures` to allow future behavioural tests without polluting the plugin runtime.

## Continuous Integration
- Added a GitHub Actions workflow that runs Composer install, coding standards, static analysis, and PHPUnit across a PHP/WordPress version matrix.
- PHPCS and PHPStan remain in the pipeline to surface outstanding remediation work from earlier phases.

## Next Steps
- Expand PHPUnit coverage to include booking flows, caching invalidation, and multisite helpers once the loader refactors stabilise.
- Introduce integration tests backed by a lightweight WordPress test stack in the performance and upgrade phases.
