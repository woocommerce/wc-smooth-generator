# Demo order status changes

This opt-in demo-store tool makes one explicit final status change per owned order.
There is no scheduler, volume policy, refund processing, payment capture or restocking.

```sh
wp wc generate lifecycle preview ORDER_ID --status=completed --at=UTC_UNIX_TIMESTAMP
wp wc generate lifecycle apply ORDER_ID --status=completed --at=UTC_UNIX_TIMESTAMP
wp wc generate lifecycle audit ORDER_ID
wp wc generate lifecycle recover ORDER_ID --token=ATTEMPT_FROM_AUDIT --decision=retry
```

`preview` does not write. `apply` requires the same explicit positive integer order ID,
`completed` or `cancelled`, and an explicit UTC Unix timestamp in the past. The
corresponding PHP methods are `OrderLifecycle::{preview,apply,audit,recover}` in
`WC\SmoothGenerator\Generator`. The CLI does not require a logged-in WordPress user;
its safety boundary is filesystem access and strict synthetic-order eligibility.
Attempt UUIDs in audit output are forensic identifiers, not authentication secrets.

Only persisted ordinary `shop_order` records marked `created_via=smooth-generator`
qualify. The selected authoritative datastore verifies the stored type before a
`WC_Order` object is constructed; registered custom types and refunds stay excluded
even if they carry synthetic ownership metadata or have warmed ordinary-order caches. Registered
customers must have `_smooth_generator_created=yes`; guests must use a `.invalid`
email address. Refunds, transaction IDs, prior lifecycle metadata and existing
completion dates block changes. Completion requires `processing` with an existing
paid date between creation and the requested transition. Cancellation requires
unpaid `pending` or `on-hold` with no reduced-stock flag. The effective timestamp
must fall on a later UTC calendar day than creation, follow payment, and be at least
the existing modified date. Historical edits that move the modified date backwards
are rejected. Status, completion date, modified date and two activity markers are
the only intended changes. Money, line items, paid dates and stock flags are verified
against the proposal after saving. When compatibility sync is enabled, the complete
expected projection and activity/attempt markers must also match a fresh read of the
other backend. A missing or partial backup keeps evidence failed; an authoritative
save alone cannot produce completed evidence or be adopted. Inspect and repair the
backup separately before considering adoption; this API performs no automatic repair.

An insert-only per-order option claim blocks competing generator workers and
conflicting dates/statuses. Evidence state changes use byte-exact compare-and-swap.
Storage authority and compatibility-sync mode are recorded with the proposal.
A mode change blocks retries/adoption until the recorded mode is restored. Older
attempts without this storage evidence remain blocked for manual inspection; do not
delete their claims to force another attempt. An exact
successful retry returns the completed record without another save.
Failures retain their proposal, attempt UUID, error and earlier attempt history.
Inspect audit before an explicit recovery decision:

- `retry` requires a failed attempt and the exact unchanged pre-transition order.
- `adopt` requires the full intact recorded result, including the attempt marker.
- `abandon` requires a failed attempt and retains all evidence and partial data.

A `claimed` attempt is never retried or abandoned: its writer may still be running.
An intact result may be adopted after a crash. An incomplete crashed claim stays
blocked for manual inspection; there is no claim deletion or destructive repair API.
Partial saves cannot be retried or adopted. Claims coordinate generator workers;
manual edits/imports to these orders must run outside the generation window.

Application hooks are suspended while saving and restored on success and failure.
The exact WooCommerce order/refund datastore selectors and its core legacy-to-HPOS
sync callback remain installed. HPOS writes use WooCommerce’s own direct compatibility
backfill; application status, payment and integration callbacks remain suppressed.
One internal post-data filter preserves the explicitly requested modified date,
which WordPress would otherwise replace with wall-clock time. Payment, email,
webhook, stock and third-party status callbacks do not run. Analytics projections,
order status notes and third-party indexes are deliberately not rebuilt. Both legacy
order storage and HPOS are supported, with compatibility sync enabled or disabled.
Order writes use WooCommerce CRUD APIs; no lifecycle SQL writes target order tables.
Fresh authoritative reads bypass the object cache, refresh metadata and clear the
HPOS raw-data cache through WooCommerce. Sync-on-read is disabled for these reads,
so preview/audit cannot import a newer conflicting compatibility post. Refund checks
use a bounded WooCommerce order query and fail closed on a read error.
This is an explicit synthetic-data boundary, not a general order administration API.

## Isolated verification

Never use an existing site's database. Provision a new disposable installation,
activate WooCommerce, and add these constants to its config:

```php
define( 'SMOOTH_LIFECYCLE_TEST_ENV', true );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'DISABLE_WP_CRON', true );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
```

The database name must start with `smooth_lifecycle_`.

```sh
SMOOTH_TEST_WP_ROOT=/path/to/disposable/wordpress \
php -d error_reporting=22527 -d sendmail_path=/usr/bin/false vendor/bin/phpunit \
  --no-configuration --bootstrap tests/Integration/lifecycle-bootstrap.php \
  tests/Integration/OrderLifecycleTest.php
```

Tests create real products/orders, native database fault triggers and concurrent
PHP workers. Mail and HTTP are blocked for fixtures as well. SQLite uses the official
SQLite Database Integration package and a unique database outside the web root;
follow the package's `db.copy` instructions for a new installation. No existing
Studio site, database or driver is needed.

## Verified storage matrix

Every row runs the same thirteen real-datastore tests. Counts below show assertions and
skips for sync disabled/enabled. A skip means the test is inapplicable, not passed.

| WordPress / WooCommerce | Database | Authoritative storage | Assertions, sync off / on | Skips, sync off / on |
| --- | --- | --- | --- | --- |
| 6.9 / 10.5.0 | MariaDB 11.4.12 | Legacy posts | 169 / 181 | 2 / 2 |
| 6.9 / 10.5.0 | MariaDB 11.4.12 | HPOS | 175 / 210 | 2 / 0 |
| 6.9 / 10.5.0 | SQLite 3.54.0 | Legacy posts | 163 / 175 | 3 / 3 |
| 6.9 / 10.5.0 | SQLite 3.54.0 | HPOS | 169 / 204 | 3 / 1 |
| 7.1 / 11.2.1 | MariaDB 11.4.12 | HPOS | 175 / 210 | 2 / 0 |
| 7.1 / 11.2.1 | SQLite 3.54.0 | HPOS | 169 / 204 | 3 / 1 |

Verified 2026-10-17 using PHP 8.5.11 and PHPUnit 9.6.34. The plugin declares a
WooCommerce 5.0 minimum. This command retains the legacy datastore stock getter when
the WooCommerce 7.0 getter is unavailable; a focused proxy test exercises that
fallback. WooCommerce 5.x integration was not run. SQLite uses the official
SQLite Database Integration 3.1.0 package, drop-in 1.8.0. The forced in-flight
writer/recovery overlap uses a MariaDB SLEEP trigger and is skipped on SQLite.
The conflicting-newer-compatibility-post and backup-only fault tests apply only to
HPOS with sync enabled. Registered custom/refund record rejection is tested in every
mode, including warmed caches. Native backup-only metadata faults retain failed
evidence and block retry/adoption until both projections can be verified.
Both engines test competing claim inserts, native partial-save faults, storage-mode
fences, SQL-read failures, warm-cache invalidation, exact timestamps, money/stock
preservation and suppressed application effects. SQL assertions inspect authoritative
order, operational and metadata rows, synced backup records or untouched placeholders.
No database errors were present in the final matrix logs.

The existing WordPress-backed unit regression passed 105 tests/247 assertions with
three existing variable-product fixture skips. Live Studio, staging and production are outside this matrix.
WooCommerce already declares this plugin HPOS-compatible; this work changes only
this demo order-status API and does not broaden other generator features' claims.

## Test environment

Use a new, disposable WordPress installation for these tests. All database names
must begin `smooth_lifecycle_`; SQLite files should be outside the disposable web
root. Never point the suite at an existing site's database.

Set `SMOOTH_TEST_STORAGE=hpos` (or `posts`) and `SMOOTH_TEST_SYNC=yes` (or `no`)
alongside the integration command above to select a test mode. These settings are
applied only after the disposable-environment guards pass. Datastore caching is
enabled during the matrix, and the bootstrap verifies the requested authority.
The suite leaves fixture data in that disposable database. Provisioning, teardown
and database cleanup are separate steps owned by the person running it.
