# My UNA World Visitor Gate Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a UNA-native guest visitor gate that limits logged-out visitors to Home, Login, Create Account, Forgot Password, Terms, and Privacy while leaving authenticated members and administrators completely unrestricted.

**Architecture:** Extend the existing `MyUnaWorldAlertsResponse` handler with the UNA `system / design_included` alert. That alert delegates to a focused `handleVisitorGate()` method on `MyUnaWorldModule`, which checks UNA's authenticated state, classifies only ordinary frontend navigation requests, allows an exact public-route set, and temporarily redirects other guest page requests to `/`. Ship the change as the native UNA `1.3.2 -> 1.3.3` updater; do not modify `.htaccess` or UNA core files.

**Tech Stack:** PHP 8.3, UNA 15.x module APIs, UNA alerts (`BxDolAlertsResponse`), MariaDB updater SQL, native UNA updater packaging.

**Spec:** `docs/superpowers/specs/2026-09-16-my-una-world-visitor-gate-design.md`

## Global Constraints

- Current repository version is `1.3.2`; target release is `1.3.3`.
- Guest public routes are exactly `/`, `/login`, `/member.php`, `/create-account`, `/forgot-password`, `/terms`, and `/privacy`, with a single trailing slash tolerated on route-style paths.
- Logged-in members and administrators are completely unrestricted by the visitor gate.
- The visitor gate is a navigation gate, not a file/API security boundary.
- Do not modify `.htaccess`.
- Do not modify UNA core files.
- Do not manually edit `sys_modules.version`.
- Do not uninstall/reinstall the module to apply the update.
- Existing `system / before_install` and `system / before_uninstall` lifecycle behavior must remain unchanged.
- Use a temporary redirect for blocked guest navigation.
- Fail open if request/authentication context cannot be determined safely.

---

## File Structure

The implementation should keep responsibilities narrow:

- `classes/MyUnaWorldAlertsResponse.php` — alert dispatch only; route lifecycle alerts to the existing lifecycle method and `design_included` to the visitor gate.
- `classes/MyUnaWorldModule.php` — visitor-gate policy and request classification; no Apache logic.
- `install/config.php` — bump module version to `1.3.3` and normalize vendor to `My UNA World` only if release policy confirms this metadata change is intended for this release.
- `install/sql/enable.sql` — fresh-install registration for `system / design_included` using the existing alert handler.
- `updates/1.3.2_1.3.3/install/config.php` — native UNA updater metadata and action order.
- `updates/1.3.2_1.3.3/install/installer.php` — standard updater installer class matching the existing updater pattern.
- `updates/1.3.2_1.3.3/install/sql/install.sql` — idempotently add only the missing `system / design_included` subscription for the existing handler.
- `updates/1.3.2_1.3.3/source/...` — changed runtime/install source files copied into the installed module by `update_files`.
- `tests/test_visitor_gate.php` — lightweight PHP regression harness with UNA stubs for route/auth decisions and alert dispatch.
- `README.md` and/or release notes — document the new visitor-gate behavior and rollback expectations.

---

### Task 1: Add a Failing Visitor-Gate Regression Harness

**Files:**
- Create: `tests/test_visitor_gate.php`
- Read: `classes/MyUnaWorldAlertsResponse.php`
- Read: `classes/MyUnaWorldModule.php`

**Interfaces:**
- Consumes: existing `MyUnaWorldModule` and `MyUnaWorldAlertsResponse` classes.
- Produces: a CLI test harness that exits nonzero on failure and proves the required guest/member route matrix before implementation.

- [ ] **Step 1: Create minimal UNA stubs before including module classes**

The test harness must define `BX_DOL`, a minimal `BxDolModule` base class, a minimal `BxDolAlertsResponse`, and controlled authentication/request globals before requiring the production classes. The harness must not connect to a real UNA database or load the full UNA runtime.

Use a structure equivalent to:

```php
<?php

define('BX_DOL', true);

class BxDolModule
{
}

class BxDolAlertsResponse
{
}

$GLOBALS['muw_test_logged'] = false;

function isLogged()
{
    return !empty($GLOBALS['muw_test_logged']);
}

require_once __DIR__ . '/../classes/MyUnaWorldModule.php';
require_once __DIR__ . '/../classes/MyUnaWorldAlertsResponse.php';
```

If the production implementation uses additional UNA helpers, stub only those helpers actually required by `handleVisitorGate()`; do not load unrelated UNA systems.

- [ ] **Step 2: Add assertions for the exact public route set**

The harness must exercise the visitor-gate decision method without actually sending HTTP headers. Implement a small test subclass that overrides the final redirect side effect so the test can capture the intended target.

Required guest expectations:

```text
/                         ALLOW
/login                    ALLOW
/login/                   ALLOW
/member.php               ALLOW
/create-account           ALLOW
/create-account/          ALLOW
/forgot-password          ALLOW
/terms                    ALLOW
/terms/                   ALLOW
/privacy                  ALLOW
/privacy/                 ALLOW
/groups-home              REDIRECT /
/m/artificer/             REDIRECT /
/terms-extra              REDIRECT /
/privacy/archive          REDIRECT /
/this-page-should-block   REDIRECT /
```

Also assert that query strings do not change path classification, for example `/terms?lang=ru` remains allowed while `/groups-home?next=/terms` remains blocked.

- [ ] **Step 3: Add authenticated-user assertions**

Set the authentication stub to logged in and verify representative blocked guest paths are all allowed for members:

```text
/groups-home
/m/artificer/
/studio/
/account
```

No redirect may be recorded for an authenticated user.

- [ ] **Step 4: Add non-navigation safety assertions**

The test harness must prove that the gate returns without redirecting for:

- CLI execution context used by the production predicate;
- an AJAX-style request detected by the production predicate;
- methods other than ordinary browser navigation when the production classifier marks them as internal/non-page requests.

The exact stubs must mirror the implementation introduced in Task 2 so the test and production code use the same request-state contract.

- [ ] **Step 5: Add alert-dispatch assertions**

Create a stub module object exposing counters for `handleModuleLifecycleAlert()` and `handleVisitorGate()`. Stub `BxDolModule::getInstance('my_una_world')` to return it, then verify:

- `system / before_install` -> lifecycle counter increments only;
- `system / before_uninstall` -> lifecycle counter increments only;
- `system / design_included` -> visitor-gate counter increments only;
- non-system or unknown system actions -> neither counter increments.

- [ ] **Step 6: Run the test and confirm it fails before implementation**

Run:

```bash
php -d display_errors=1 tests/test_visitor_gate.php
```

Expected: nonzero exit because `handleVisitorGate()` / visitor-gate dispatch does not yet exist.

- [ ] **Step 7: Commit the failing test**

```bash
git add tests/test_visitor_gate.php
git commit -m "test: define visitor gate behavior"
```

---

### Task 2: Implement the UNA-Native Visitor Gate

**Files:**
- Modify: `classes/MyUnaWorldModule.php`
- Modify: `classes/MyUnaWorldAlertsResponse.php`
- Test: `tests/test_visitor_gate.php`

**Interfaces:**
- Consumes: UNA's post-login `system / design_included` alert and global authenticated-state helper `isLogged()` after `check_logged()` has executed.
- Produces: `MyUnaWorldModule::handleVisitorGate()` and alert dispatch from `MyUnaWorldAlertsResponse::response()`.

- [ ] **Step 1: Add alert dispatch without disturbing lifecycle behavior**

Change `MyUnaWorldAlertsResponse::response()` so it:

1. returns unless `$oAlert->sUnit === 'system'`;
2. gets `BxDolModule::getInstance('my_una_world')` once;
3. for `before_install` and `before_uninstall`, calls `handleModuleLifecycleAlert($oAlert)` exactly as today;
4. for `design_included`, calls `handleVisitorGate()` if available;
5. returns for all other actions.

Do not combine lifecycle and visitor-gate logic into one module method.

- [ ] **Step 2: Add exact path normalization in `MyUnaWorldModule`**

Add a protected helper that derives only the URL path from `$_SERVER['REQUEST_URI']` using `parse_url(..., PHP_URL_PATH)`. Requirements:

```php
protected function _getVisitorGatePath()
```

Behavior:

- missing or invalid `REQUEST_URI` -> return `null` so the gate fails open;
- ensure the path begins with `/`;
- collapse no internal segments and perform no URL rewriting;
- normalize a single trailing slash for non-root route comparison;
- do not inspect query parameters when deciding whether a route is public.

- [ ] **Step 3: Add exact public-route matching**

Add:

```php
protected function _isVisitorGatePublicPath($sPath)
```

The normalized allowlist is exactly:

```php
array(
    '/',
    '/login',
    '/member.php',
    '/create-account',
    '/forgot-password',
    '/terms',
    '/privacy',
)
```

Use strict equality/in-array matching. Do not use prefix matching, regex wildcards, substring checks, or starts-with checks.

- [ ] **Step 4: Add fail-open request classification**

Add a protected predicate:

```php
protected function _shouldVisitorGateInspectRequest()
```

It must return `false` for execution contexts that should never be redirected. At execution time, verify the locally available UNA/runtime indicators and implement only those that are reliably defined on the deployed UNA 15 baseline. The minimum contract is:

- CLI (`PHP_SAPI === 'cli'`) -> false;
- authenticated users are handled before this predicate and never gated;
- AJAX/internal requests detected by the UNA runtime or standard `HTTP_X_REQUESTED_WITH=XMLHttpRequest` -> false;
- ordinary browser `GET` and `HEAD` page navigation -> true;
- public-form POSTs to `/member.php`, `/create-account`, and `/forgot-password` must remain allowed by path before any blocked-route redirect decision.

The implementation must fail open when request classification is ambiguous.

- [ ] **Step 5: Add a testable redirect seam**

Add:

```php
protected function _redirectGuestToHome()
```

Production behavior:

```php
header('Location: ' . BX_DOL_URL_ROOT, true, 302);
exit;
```

If `BX_DOL_URL_ROOT` is unavailable in the CLI test harness, the test subclass overrides this method, so production code should not add test-only branches.

- [ ] **Step 6: Implement `handleVisitorGate()`**

Implement the flow in this exact order:

```text
1. If CLI -> return.
2. If isLogged() -> return.
3. If request should not be inspected -> return.
4. Resolve normalized path; if unavailable -> return.
5. If path is public -> return.
6. Redirect temporarily to home and stop the HTTP request.
```

The method must not mutate sessions, cookies, membership levels, page visibility, or UNA authentication state.

- [ ] **Step 7: Run syntax checks**

Run:

```bash
php -l classes/MyUnaWorldModule.php
php -l classes/MyUnaWorldAlertsResponse.php
php -l tests/test_visitor_gate.php
```

Expected: `No syntax errors detected` for all three files.

- [ ] **Step 8: Run the regression harness**

Run:

```bash
php -d display_errors=1 tests/test_visitor_gate.php
```

Expected: all route, authenticated-user, safety, and alert-dispatch assertions PASS; process exits 0.

- [ ] **Step 9: Review the diff for scope**

Run:

```bash
git diff -- classes/MyUnaWorldModule.php classes/MyUnaWorldAlertsResponse.php tests/test_visitor_gate.php
```

Expected: only visitor-gate logic and alert dispatch changes; no backup/restore/lifecycle behavior changes.

- [ ] **Step 10: Commit the runtime implementation**

```bash
git add classes/MyUnaWorldModule.php classes/MyUnaWorldAlertsResponse.php tests/test_visitor_gate.php
git commit -m "feat: add UNA visitor gate"
```

---

### Task 3: Register `design_included` for Fresh Installs

**Files:**
- Modify: `install/sql/enable.sql`
- Test: `tests/test_visitor_gate.php` or add `tests/test_visitor_gate_sql.php` if keeping SQL assertions separate

**Interfaces:**
- Consumes: existing alert handler `my_una_world_lifecycle` / `MyUnaWorldAlertsResponse`.
- Produces: fresh installs/enables subscribe the same handler to `before_install`, `before_uninstall`, and `design_included` exactly once each.

- [ ] **Step 1: Add a failing static SQL assertion**

Extend the test harness or add a focused SQL text test that reads `install/sql/enable.sql` and asserts the INSERT contains all three required alert tuples:

```text
('system', 'before_install', @iHandler)
('system', 'before_uninstall', @iHandler)
('system', 'design_included', @iHandler)
```

The test must also count each action and require exactly one occurrence in the alert registration statement.

- [ ] **Step 2: Run the SQL assertion and confirm failure**

Run the selected PHP test file.

Expected: failure because `design_included` is not yet registered.

- [ ] **Step 3: Update `install/sql/enable.sql`**

Preserve the existing handler replacement pattern and cron/injection setup. Change only the alert insert so the handler receives the third subscription:

```sql
INSERT INTO `sys_alerts` (`unit`, `action`, `handler_id`)
VALUES
    ('system', 'before_install', @iHandler),
    ('system', 'before_uninstall', @iHandler),
    ('system', 'design_included', @iHandler);
```

Do not add a second handler.

- [ ] **Step 4: Re-run the SQL assertion**

Expected: PASS with exactly one occurrence of each required action.

- [ ] **Step 5: Commit fresh-install registration**

```bash
git add install/sql/enable.sql tests/
git commit -m "feat: register visitor gate alert"
```

---

### Task 4: Build the Native UNA 1.3.2 -> 1.3.3 Updater

**Files:**
- Modify: `install/config.php`
- Create: `updates/1.3.2_1.3.3/install/config.php`
- Create: `updates/1.3.2_1.3.3/install/installer.php`
- Create: `updates/1.3.2_1.3.3/install/sql/install.sql`
- Create: `updates/1.3.2_1.3.3/source/classes/MyUnaWorldModule.php`
- Create: `updates/1.3.2_1.3.3/source/classes/MyUnaWorldAlertsResponse.php`
- Create: `updates/1.3.2_1.3.3/source/install/config.php`
- Create: `updates/1.3.2_1.3.3/source/install/sql/enable.sql`
- Test: updater metadata/static SQL checks under `tests/`

**Interfaces:**
- Consumes: installed My UNA World `1.3.2` and existing `my_una_world_lifecycle` alert handler.
- Produces: UNA-discoverable `1.3.2 -> 1.3.3` update that copies runtime/source files and adds one `design_included` subscription without disturbing lifecycle alerts.

- [ ] **Step 1: Add updater metadata tests before creating the updater**

Add static assertions requiring:

```text
version_from = 1.3.2
version_to   = 1.3.3
module_dir   = newton/my_una_world/
module_uri   = my_una_world
```

Require updater actions in this order:

```text
execute_sql
update_files
clear_db_cache
```

No language action is needed unless language source changes during implementation.

- [ ] **Step 2: Run updater tests and confirm failure**

Expected: FAIL because `updates/1.3.2_1.3.3` does not yet exist.

- [ ] **Step 3: Bump source package version**

Change `install/config.php`:

```php
'version' => '1.3.3',
```

Preserve all unrelated install metadata. If vendor normalization from `MyUNAWorld` to `My UNA World` is included, make that a deliberate release-metadata change in both package and updater and document it in release notes; otherwise keep vendor metadata unchanged in this release to avoid bundling an unrelated change.

- [ ] **Step 4: Create updater `install/config.php` from the known 1.3.1 -> 1.3.2 pattern**

Use:

```php
<?php

$aConfig = array(
    'title' => 'My UNA World',
    'version_from' => '1.3.2',
    'version_to' => '1.3.3',
    'vendor' => 'MyUNAWorld',
    'compatible_with' => array('15.0.x'),
    'home_dir' => 'newton/my_una_world/updates/update_1.3.2_1.3.3/',
    'home_uri' => 'my_una_world_update_132_133',
    'module_dir' => 'newton/my_una_world/',
    'module_uri' => 'my_una_world',
    'db_prefix' => 'my_una_world_',
    'class_prefix' => 'MyUnaWorld',
    'install' => array(
        'execute_sql' => 1,
        'update_files' => 1,
        'clear_db_cache' => 1,
    ),
    'language_category' => 'My UNA World',
    'delete_files' => array(),
);
```

If Task 3 deliberately normalizes vendor metadata, use `My UNA World` consistently instead.

- [ ] **Step 5: Create updater installer class**

Copy the structural pattern from `updates/1.3.1_1.3.2/install/installer.php`, changing only names/version-specific class identifiers required by that existing implementation.

- [ ] **Step 6: Create idempotent updater SQL**

The updater SQL must reuse the existing handler rather than delete/recreate it. Use an idempotent insert based on the handler name:

```sql
SET @iHandler = (
    SELECT `id`
    FROM `sys_alerts_handlers`
    WHERE `name` = 'my_una_world_lifecycle'
    LIMIT 1
);

INSERT INTO `sys_alerts` (`unit`, `action`, `handler_id`)
SELECT 'system', 'design_included', @iHandler
WHERE @iHandler IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM `sys_alerts`
      WHERE `unit` = 'system'
        AND `action` = 'design_included'
        AND `handler_id` = @iHandler
  );
```

Do not delete or replace the existing handler in updater SQL. Do not touch `before_install` or `before_uninstall` rows.

- [ ] **Step 7: Populate updater source files from the final 1.3.3 source**

Copy the exact final versions of:

```text
classes/MyUnaWorldModule.php
classes/MyUnaWorldAlertsResponse.php
install/config.php
install/sql/enable.sql
```

into matching paths beneath `updates/1.3.2_1.3.3/source/`.

Verify with byte-for-byte comparisons, not visual inspection.

- [ ] **Step 8: Run PHP syntax checks across updater PHP files**

Run:

```bash
find updates/1.3.2_1.3.3 -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: no syntax errors.

- [ ] **Step 9: Run updater metadata and SQL tests**

Expected: PASS for versions, action order, module identity, idempotent `design_included` SQL, and preservation of lifecycle subscriptions.

- [ ] **Step 10: Verify source/updater file identity**

Run:

```bash
cmp -s classes/MyUnaWorldModule.php updates/1.3.2_1.3.3/source/classes/MyUnaWorldModule.php
cmp -s classes/MyUnaWorldAlertsResponse.php updates/1.3.2_1.3.3/source/classes/MyUnaWorldAlertsResponse.php
cmp -s install/config.php updates/1.3.2_1.3.3/source/install/config.php
cmp -s install/sql/enable.sql updates/1.3.2_1.3.3/source/install/sql/enable.sql
```

Expected: exit 0 for each comparison.

- [ ] **Step 11: Commit updater package**

```bash
git add install/config.php updates/1.3.2_1.3.3 tests/
git commit -m "build: add My UNA World 1.3.3 updater"
```

---

### Task 5: Perform Repository-Level Regression and Release Documentation

**Files:**
- Modify: `README.md`
- Modify or create release note file according to the repository's existing release-documentation convention
- Test: all visitor-gate/updater tests

**Interfaces:**
- Consumes: completed 1.3.3 runtime and updater.
- Produces: documented, reviewable release candidate ready for controlled deployment to a test UNA 15 site.

- [ ] **Step 1: Document visitor-gate behavior**

Add a concise section explaining:

- guests are limited to the seven public routes;
- authenticated members/admins are unrestricted;
- the feature is implemented through UNA `system / design_included`, not `.htaccess`;
- the feature is a navigation gate, not a security boundary;
- disabling/removing the alert subscription is the emergency rollback for test deployment.

- [ ] **Step 2: Run all PHP syntax checks**

Run:

```bash
find classes install updates/1.3.2_1.3.3 tests -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: all PASS.

- [ ] **Step 3: Run all visitor-gate/updater regression tests**

Run the PHP test files under `tests/` individually so a failing file is obvious.

Expected: all PASS with exit 0.

- [ ] **Step 4: Verify no forbidden files changed**

Run:

```bash
git status --short
git diff --name-only HEAD~1..HEAD
```

Across the full feature branch/review range, confirm there are no `.htaccess`, UNA core, unrelated backup engine, restore engine, or database-version hand edits.

- [ ] **Step 5: Commit documentation**

```bash
git add README.md RELEASES docs tests
git commit -m "docs: document visitor gate release"
```

Adjust the staged release-documentation path to the actual file created/modified; do not stage unrelated release assets.

---

### Task 6: Controlled UNA Test Deployment and Functional Verification

**Files:**
- No repository source changes unless a test exposes a defect.
- Deployment target: a designated UNA 15 test site running My UNA World `1.3.2`.

**Interfaces:**
- Consumes: committed `1.3.3` updater candidate.
- Produces: evidence that the native updater and live visitor gate work without breaking login, logout, Studio, AJAX, or lifecycle backups.

- [ ] **Step 1: Capture pre-update state**

Record:

- installed module version;
- enabled state;
- module file-track status;
- existing `my_una_world_lifecycle` handler ID;
- exact alert rows for `before_install` and `before_uninstall`;
- absence of `design_included` for that handler.

Create a normal safety backup before deployment.

- [ ] **Step 2: Stage only the `1.3.2_1.3.3` updater**

Place the updater under the installed module's `updates/` directory without manually replacing registered live runtime files.

Verify UNA discovers exactly `1.3.2 -> 1.3.3` before running it.

- [ ] **Step 3: Apply through UNA's native updater**

Use UNA Studio's normal update mechanism. Do not manually bump `sys_modules.version` and do not uninstall the module.

- [ ] **Step 4: Verify post-update registration**

Confirm:

```text
version = 1.3.3
enabled = 1
before_install = exactly 1
before_uninstall = exactly 1
design_included = exactly 1
```

Confirm runtime files match UNA file tracking after the update.

- [ ] **Step 5: Verify guest public pages**

In a private/incognito browser session, verify HTTP/page behavior for:

```text
/                  loads
/login             loads
/create-account    loads when UNA account creation is enabled
/forgot-password   loads
/terms             loads
/privacy           loads
```

Submit the login form through `/member.php` and verify login succeeds.

- [ ] **Step 6: Verify blocked guest navigation**

While logged out, navigate to representative non-public pages such as:

```text
/m/artificer/
/groups-home
/account
/an-arbitrary-unknown-route
```

Expected: temporary redirect to `/`, with no redirect loop.

- [ ] **Step 7: Verify authenticated behavior**

After login, verify:

- `/m/artificer/` or another normal member/module route loads;
- member navigation works;
- at least one AJAX-backed feature works;
- `/studio/` remains available to an authenticated administrator;
- My UNA World Studio page loads;
- logout succeeds;
- after logout, a blocked route redirects to `/` again.

- [ ] **Step 8: Verify lifecycle-backup regression**

Confirm the existing lifecycle handler remains healthy and still responds to `before_install` / `before_uninstall` according to configured settings. Do not perform a destructive uninstall solely for this test; use the existing safe lifecycle diagnostic/status mechanism or a controlled non-destructive module operation.

- [ ] **Step 9: Verify rollback path**

Before declaring success, document the exact handler ID and SQL needed to remove only the `system / design_included` row for `my_una_world_lifecycle` if emergency rollback is needed, leaving `before_install` and `before_uninstall` untouched.

- [ ] **Step 10: Record deployment evidence**

Save the test results, updater hash/checksum, final commit SHA, and file-track verification in the release notes/checkpoint before publishing a release asset.

---

## Self-Review Results

- **Spec coverage:** Every approved requirement is mapped to a task: post-login UNA hook, exact guest routes, unrestricted members/admins, internal-request safety, fail-open behavior, native updater, lifecycle preservation, functional login/logout testing, and rollback.
- **Placeholder scan:** No TBD/TODO placeholders remain. Execution-time UNA request predicates are constrained to locally verified indicators and fail-open behavior rather than guessed APIs.
- **Interface consistency:** The plan consistently uses `handleVisitorGate()`, `_getVisitorGatePath()`, `_isVisitorGatePublicPath()`, `_shouldVisitorGateInspectRequest()`, and `_redirectGuestToHome()`.
- **Scope:** One subsystem only: UNA-native visitor navigation gating inside the existing My UNA World module.
