# Audit Log Pro

Enterprise activity/audit logging plugin. Built as a technical portfolio piece
covering: custom table design, optimized `$wpdb` queries at scale, object
caching, cursor pagination, REST API, WP-Cron retention, Gutenberg dynamic
blocks, editor SlotFill with a custom `wp.data` store, and RBAC.

## Why this exists

Most WooCommerce/enterprise WP sites need *some* record of who-did-what — order
status changes, content edits, logins — for accountability and debugging. This
plugin is a self-contained, correctly-architected answer to that need, built to
survive millions of rows without falling over.

## Architecture decisions (and why)

**Custom table, not a Custom Post Type.**
At scale, `wp_postmeta`'s EAV structure means every filtered query is a JOIN,
and CPT rows carry revision/term/meta-cache overhead we don't need for an
append-mostly log. A dedicated table with purpose-built indexes
(`event_type`, `(object_type, object_id)`, `user_id`, `created_at`) is the
correct call here — see `includes/class-alp-activator.php`.

**Cursor pagination, not `OFFSET`.**
`LIMIT 50 OFFSET 200000` forces MySQL to scan and discard 200k rows first —
it gets linearly slower the deeper you page. Keying off the last-seen `id`
(`WHERE id < :last_id ORDER BY id DESC LIMIT 50`) uses the primary key index
directly regardless of depth. See `ALP_Query::get_log_page()`.

**Cache the aggregates, not the rows.**
Raw log rows change every second — row-level caching would thrash and add
overhead for no benefit. The dashboard summary (counts per event type, last
24h) is expensive, read repeatedly, and changes slowly — that's what actually
belongs behind `wp_cache_get`/`set`. See `ALP_Query::get_dashboard_summary()`
and its invalidation in `ALP_Logger::log()`.

**Batched deletes in the cron purge job.**
A single unbounded `DELETE` across millions of rows can hold locks long enough
to stall other queries on the same table. The purge job deletes in capped
batches (5,000 rows/iteration) instead. See `ALP_Cron::purge_old_logs()`.

**`wp-cron` vs real cron.**
`wp-cron` only fires on page load — unreliable on both very-low-traffic sites
(may not fire for days) and very-high-traffic sites (many requests can each
think a job is due). Production deployment assumes `DISABLE_WP_CRON` in
`wp-config.php` plus a real system crontab hitting `wp-cron.php` on a fixed
interval. This plugin just registers correctly against that setup — see the
docblock in `includes/class-alp-cron.php`.

**RBAC via a dedicated capability, not `manage_options`.**
Viewing audit logs is a security/compliance concern, not an "is this person an
admin" concern. `alp_view_activity_log` / `alp_export_activity_log` /
`alp_purge_activity_log` are registered and mapped onto roles explicitly, so
access is a policy decision made once, not a side effect of admin status.
See `includes/class-alp-capabilities.php`.

**Dynamic block, not static.**
The Recent Activity block has no `save()` — it always renders live via
`render.php`, because log data is inherently time-sensitive; static markup
saved into `post_content` would go stale the moment a new event is logged.

**`useSelect` vs `apiFetch` + `useState` — used deliberately, not
interchangeably.**
The block's `Edit` component uses local `useState`/`useEffect` with a direct
`apiFetch` call, because that preview data isn't shared with any other
component. The SlotFill sidebar panel uses `useSelect` against a custom
registered `wp.data` store (`src/slotfill/store.js`) with a resolver, because
that's the pattern that gives automatic caching, request de-duplication, and
re-render-on-update — the same mechanism core entities (posts, users, terms)
use under the hood. Reaching for `useSelect` without a backing store doesn't
do anything; reaching for local state when data should be shared/cached
across components is the more common mistake. Both are demonstrated here on
purpose, in the place each one is actually correct.

## Setup

```bash
npm install
npm run build
```

Activate the plugin, then visit **Settings → Permalinks** once to flush rewrite
rules for the new REST routes (handled automatically on activation, this is
just the manual fallback if routes 404).

## Not yet built (deliberately out of scope for this portfolio pass)

- CSV/JSON export endpoint gated behind `alp_export_activity_log`
- Multisite-aware table prefixing (network-wide vs per-site logs)
