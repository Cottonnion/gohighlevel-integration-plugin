# Syncly Roadmap / Feature Ideas

Internal working document. Not shipped. This file is excluded from the release and deploy workflows
(release.yml rsync/zip exclusions + validation, SVN deploy). Do not distribute.

Priority legend: **P1** = build next · **P2** = valuable, schedule · **P3** = nice-to-have / long-term.

---

## Pro

### P1 — In-plugin Support + one-click diagnostics
- WordPress support screen (Pro) where the user contacts us from inside the plugin.
- User picks the **exact issue category** (sync failing, connection, membership/restrictions,
  WooCommerce, LearnDash, setup wizard) and the **relevant error(s)** to send.
- One-click **"Send debug logs + system info"**:
  - Collects relevant `sync_logs` rows (already stored in the `sync_logs` table, incl. `metadata` JSON).
  - System-info snapshot: WP version, PHP version, active plugins, WooCommerce version, HPOS on/off,
    license status, Syncly versions.
  - **Redact** secrets before sending: OAuth tokens, webhook secrets, API keys.
- Returns a **ticket ID**; user can check status in-plugin and opt into email updates when the ticket changes.
- Transport: reuse `LicenseClient` pattern (`synclyforgohighlevel.com` endpoint) so tickets are tied to a
  licensed customer automatically.
- Note: all the debug data + Pro gating plumbing already exists — this is mostly a new admin screen +
  a server-side ticket endpoint. The server/helpdesk side is the real build.

### P2 — WooCommerce Opportunities growth
- The core feature is already built (`OpportunityManager`, settings UI in `pro/templates/woocommerce.php`,
  wired into `WooCommerceSync`). Ideas to grow it:
  - **Per-order opportunity meta box** on the single order edit screen: linked GHL opportunity ID +
    current pipeline/stage + link to view it in GHL.
  - **Opportunity activity log / history** view (stage changes per order over time) in the sync log.
  - Abandoned-cart → opportunity flow polish and a "reopen" action from the orders list.

### P2 — Webhook notification destinations (Slack / Discord / Teams)
- `NotificationManager` (free) is email-only (`wp_mail`). Add a webhook destination so site owners can
  push sync/health alert notifications to Slack, Discord, or Teams via an inbound webhook URL.
- Small delta on the existing notification system; strong Pro value.

### P2 — Scheduled report exports (PDF / CSV email)
- `ReportingManager` (Pro) + `StatsProvider` already produce report/analytics data.
- Add scheduled delivery (daily/weekly) of a sync summary / new contacts / failures as CSV or PDF email.

### P3 — Re-sync / queue operations UI
- Expose the queue's retry/claim/reclaim machinery (already in `QueueManager`) in an admin view:
  - Stuck / maxed-out items with one-click **retry**.
  - Manual re-sync of a single user or order against the current mapping.
- `UserBulkActions` exists for users; extend the "unsynced / pending" view + bulk re-sync.

---

## Free

### P1 — Dry-run "test this user/order against the mapping"
- The free "preview" is only a GHL **contact search** (AjaxHandler `search_ghl_contacts`), not a field
  mapping dry-run. `SyncPreviewPro` is Pro-gated.
- Add a free one-off dry run: pick a user (or Woo order), see exactly which fields/tags would be pushed to
  GHL, no actual API write. Great onboarding/trust tool + natural Pro upsell.

### P2 — Webhook deliverability / debug UI (+ replay)
- `WebhookHandler` already logs every inbound webhook via `SyncLogger`.
- Add a view of recent webhook events (received → validated → mapped → result) with a **replay last event**
  action. Cuts the most confusing class of support issues.

### P2 — Onboarding completeness checklist
- Setup wizard exists. Extend into a persistent checklist: connect → map fields → pick tags → enable
  integrations → test sync. Checkmarks reduce abandonment and drive free→pro conversion.

### P3 — Tag / field usage recommendations
- Surface most-used GHL tags/fields and flag mapping mismatches (e.g., a mapped field that no longer
  exists in GHL) before users hit them. Small effort, high perceived value.

### P3 — User self-serve sync status shortcode
- Logged-in shortcode/page: "your account is linked / last synced / which tags". Reduces "why aren't I
  syncing" support load and strengthens the WordPress↔GHL bridge.

---