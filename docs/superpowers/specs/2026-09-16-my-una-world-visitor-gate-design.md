# My UNA World Visitor Gate Design

Date: 2026-09-16
Repository: `newton27/my-una-world`
Current repository version: `1.3.2`
Target release: `1.3.3`
Status: Approved design, pending implementation plan

## Purpose

Add a UNA-native visitor gate to My UNA World so logged-out visitors can access only a small public set of pages while authenticated UNA members and administrators remain unrestricted.

This feature replaces the rejected Apache `.htaccess` approach. Apache rewrite rules run before UNA authentication and therefore cannot safely distinguish guests from logged-in members. The visitor gate must run inside UNA after the current login state has been established.

## Required Behavior

### Logged-out visitors

Guests may access only these public routes:

- `/`
- `/login`
- `/member.php`
- `/create-account`
- `/forgot-password`
- `/terms`
- `/privacy`

A normal guest-facing page request to any other route must receive a temporary redirect to `/`.

The redirect must not create loops. `/` itself is always allowed.

### Logged-in members and administrators

Authenticated UNA users must be completely unrestricted by the visitor gate.

The visitor gate must not interfere with:

- ordinary member navigation;
- logout;
- Studio access;
- module pages;
- AJAX/service requests;
- internal UNA actions;
- existing My UNA World backup, restore, import, download, status, report, lifecycle, and cron behavior.

## Hook Selection

Use the existing My UNA World alert infrastructure and subscribe it to the UNA `system / design_included` alert.

The earlier `system / begin` alert is intentionally rejected for this purpose. In the deployed UNA 15 request lifecycle, `system / begin` is fired before `check_logged()`, so it is too early to make a reliable guest-versus-member decision.

`system / design_included` is the selected frontend hook because it occurs later in the UNA request lifecycle, after the standard initialization path has established authentication state, while still allowing a normal frontend request to be redirected before final page delivery.

No UNA core files are to be modified.

## Existing Alert Infrastructure

My UNA World already has one alert response class:

- `classes/MyUnaWorldAlertsResponse.php`

The existing handler is registered through:

- `install/sql/enable.sql`

and currently subscribes to:

- `system / before_install`
- `system / before_uninstall`

The visitor gate must extend this existing handler instead of introducing an unrelated second alert architecture.

## Alert Dispatch Design

`MyUnaWorldAlertsResponse::response()` will continue to accept only `system` alerts.

Dispatch behavior:

1. `before_install` and `before_uninstall` continue to call the existing lifecycle-backup handler exactly as before.
2. `design_included` calls a new visitor-gate method on `MyUnaWorldModule`.
3. Unknown actions return without side effects.

The existing lifecycle behavior must not be refactored beyond the minimum change necessary to add dispatch for the new action.

## Visitor Gate Method

Add a focused method to `MyUnaWorldModule`, conceptually named `handleVisitorGate()`.

The method must:

1. return immediately for CLI/background execution;
2. return immediately when UNA reports an authenticated user;
3. operate only on ordinary browser page requests suitable for frontend redirection;
4. obtain and normalize the request path without trusting query-string content;
5. allow the exact public route set defined above;
6. otherwise issue a temporary redirect to `/` and terminate only that HTTP request cleanly.

Authentication must be determined through UNA's established authenticated-user state after `check_logged()` has run. The implementation must not parse cookies or duplicate UNA login logic.

## Request Classification and Safety Exclusions

The visitor gate is a navigation gate, not an Apache firewall and not a replacement for UNA access controls.

It must not redirect requests that are not ordinary frontend page navigation and whose interruption could break UNA internals. The implementation plan must verify the local UNA 15 request APIs and use existing request-state indicators where available rather than inventing brittle URI prefixes.

At minimum, the gate must avoid interfering with:

- CLI and cron execution;
- internal/background jobs;
- AJAX requests;
- service/API-style requests required by UNA;
- asset delivery;
- already authenticated Studio/admin requests;
- My UNA World operational endpoints when called from an authenticated session.

The implementation must favor UNA-native request predicates or constants over manual user-agent detection or broad `.htaccess` exemptions.

## Public Route Matching

Route matching must be exact after path normalization.

Examples that are allowed for guests:

- `/`
- `/login`
- `/login/`
- `/member.php`
- `/create-account`
- `/forgot-password`
- `/terms`
- `/privacy`

Examples that are not implicitly allowed:

- `/login-anything`
- `/terms-extra`
- `/privacy/archive`
- `/m/...`
- arbitrary module/content/profile URLs

Trailing slash handling may normalize a single trailing slash on a public route, but matching must not become prefix-based.

Query strings on an allowed path may pass through normally. The query string must never be used to turn a blocked path into an allowed path.

## Redirect Behavior

Blocked guest page requests redirect to `/` using a temporary redirect.

Use temporary behavior during this feature's initial release so browsers and intermediaries do not permanently cache the restriction.

The gate must not redirect `/` to itself.

## Installer and Update Design

### Fresh installs

`install/sql/enable.sql` must register `system / design_included` for the existing `my_una_world_lifecycle` alert handler alongside the existing lifecycle alerts.

Disable and uninstall behavior already removes all alert rows belonging to the handler and therefore should continue to remove the visitor-gate subscription as part of the same handler cleanup.

### Existing 1.3.2 installations

Ship the change as a normal UNA updater from `1.3.2` to `1.3.3`.

The updater must:

- update the modified runtime files;
- update the module version metadata;
- add the `system / design_included` alert subscription through UNA update SQL;
- preserve the existing lifecycle alert subscriptions;
- avoid duplicate handler or alert rows;
- clear the appropriate UNA caches through the native updater contract.

Do not manually edit the installed module database version and do not require uninstall/reinstall.

## Expected Files Touched

Implementation is expected to be limited primarily to:

- `classes/MyUnaWorldAlertsResponse.php`
- `classes/MyUnaWorldModule.php`
- `install/config.php`
- `install/sql/enable.sql`
- new `updates/1.3.2_1.3.3/...` updater files
- release documentation/changelog files where appropriate

If implementation requires broader core or template changes, stop and re-evaluate the architecture before proceeding.

## Failure Handling

The visitor gate should fail open rather than lock out the site if required module state cannot be obtained safely.

Examples:

- if the module instance cannot be obtained, the alert response returns;
- if the implementation cannot reliably determine request context, it does not redirect;
- existing lifecycle alert failures must remain isolated from visitor-gate dispatch.

The feature must never modify login state, user accounts, sessions, cookies, access levels, page visibility records, or `.htaccess`.

## Security Model

This feature is intentionally not a security boundary for files or endpoints. UNA's own authentication, authorization, and access controls remain authoritative.

The visitor gate only changes what unauthenticated frontend visitors can navigate to. It must not be represented as protection for private server files or as a substitute for access-control configuration.

## Verification Plan

### Static verification

- PHP syntax passes for every changed PHP file.
- Updater metadata reports `1.3.2 -> 1.3.3`.
- Fresh-install enable SQL contains exactly one `system / design_included` subscription for the existing handler.
- Existing `before_install` and `before_uninstall` subscriptions remain present.
- No `.htaccess` or UNA core file changes are included.

### Guest browser verification

Confirm guests can reach:

- `/`
- `/login`
- `/create-account`
- `/forgot-password`
- `/terms`
- `/privacy`

Confirm login submission through `/member.php` still works.

Confirm representative blocked guest routes redirect temporarily to `/`, including:

- a module/content route;
- a profile/content route;
- an arbitrary unknown route that otherwise enters normal UNA frontend routing.

### Authenticated verification

After login:

- normal member pages load;
- module pages load;
- navigation works;
- AJAX-backed features continue to work;
- logout works;
- after logout, the guest restriction resumes.

### Administrator verification

- Studio remains usable for an authenticated administrator;
- My UNA World Studio page loads;
- existing backup/restore functions remain usable.

### Lifecycle regression verification

Verify the existing My UNA World lifecycle handler remains registered for:

- `system / before_install`
- `system / before_uninstall`

and that the lifecycle backup behavior is unchanged.

## Rollback

Rollback is performed through the normal module/updater source state, not through Apache configuration.

If the new visitor gate causes a problem during testing, the test deployment must allow the new `design_included` subscription to be disabled or removed without changing the existing lifecycle subscriptions. Source and database changes must be backed up before test deployment.

## Out of Scope

This release does not add:

- a Studio UI for editing the public route list;
- per-membership-level visitor-gate rules;
- IP allowlists;
- robots/search-engine policy;
- maintenance-mode replacement;
- `.htaccess` restrictions;
- changes to UNA core authentication;
- custom login, registration, Terms, or Privacy implementations.

Those can be considered separately after the basic visitor gate is proven stable.
