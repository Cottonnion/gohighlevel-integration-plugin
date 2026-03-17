# Changelog

All notable changes to GoHighLevel CRM Integration will be documented in this file.

## [1.0.1] - 2026-03-17

### Added

- **LearnDash Course Progress Sync** — Syncs course progress (percentage, status, completed steps, total steps) to GHL custom fields via the Pro add-on. Includes a 15-minute per-user-per-course debounce to prevent API rate limit exhaustion on rapid lesson completions. Filterable via `ghl_crm_progress_debounce_seconds`.
- **Virtual Field Resolution Filter** — Added `ghl_crm_resolve_field_value` filter in `UserHooks::prepare_contact_data()` so integrations can resolve computed/virtual field values (e.g., `ld_progress_percentage_{course_id}`) during regular profile update syncs.
- **Contact Form 7 Integration** — GHL CRM tab in the CF7 editor with per-form field mapping, tag assignment, and automatic submission sync on form send.
- **Sync Preview / Dry Run** — Preview sync operations before executing: shows create vs update action, field-by-field comparison, tag changes, conflicts, and estimated API calls.
- **Email Notification System** — Six notification types (connection lost, sync errors, queue backlog, rate limit, webhook failures, daily summary) with HTML email templates and configurable throttling.
- **Elementor Widget Restrictions** — GoHighLevel Restrictions section in the Advanced tab of all Elementor widgets with five restriction types.
- **Gutenberg Restricted Content Block** — `ghl-crm/restricted-content` block with tag selection, fallback content, and customizable styling.

### Improved

- **Multisite Queue Safety** — Queue processing lock switched from `get_transient`/`set_transient` to `get_site_transient`/`set_site_transient` in `QueueManager` for network-wide locking.
- **TagManager Documentation** — Full PHPDoc blocks added to every method with logical method reordering (no logic changes).
- **FEATURES-AND-BENEFITS.md** — Complete rewrite based on codebase audit. Removed non-existent features, added Free vs Pro distinction, and documented all verified features with technical specifics.

### Fixed

- **Duplicate Contact Recovery** — Auto-converts POST to PUT on duplicate detection; handles deleted/merged contacts via email re-lookup and meta update.
- **Ping-Pong Prevention** — 30-second inbound guard transient prevents infinite sync loops between webhook inbound and profile update outbound sync.

## [1.0.0] - 2026-01-01

- Initial release.
