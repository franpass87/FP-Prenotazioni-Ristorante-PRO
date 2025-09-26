# Runtime QA Test Plan (Phase 3)

Because the automated audit container does not ship with a full WordPress environment, run the following steps on a local or staging stack to validate the runtime logging harness.

1. Enable `WP_DEBUG` and `WP_DEBUG_LOG` in `wp-config.php`.
2. Activate the plugin and ensure the generated `docs/audit/runtime-issues.log` file is writable by the web server user.
3. Visit the Booking Dashboard admin page and perform sample filters; confirm that the runtime log does not capture PHP warnings/notices.
4. Load the public booking form with different languages and edge-case inputs (invalid party size, closed days) and verify no runtime warnings are recorded.
5. Trigger AJAX booking submissions and confirm that failed validations surface cleanly without PHP errors.
6. After testing, archive the updated `docs/audit/runtime-issues.log` file for inclusion in the audit report.

Document any runtime issues directly in `docs/audit/runtime-issues.log` and create follow-up tickets for remediation.
