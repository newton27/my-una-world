# My UNA World

**Vendor:** MyUNAWorld  
**Platform:** UNA CMS  
**Module:** `my_una_world`  
**Current version:** 1.3.3 candidate

**Source status:** candidate 1.3.3 adds the visitor navigation gate and its
native 1.3.2-to-1.3.3 updater on top of source that includes unreleased
September 11 hotfixes. The published 1.3.2 ZIP hashes below describe the
historical release, not the current source. See
[the 1.3.3 candidate notes](RELEASES/1.3.3.md) and
[the hotfix notes](RELEASES/UNRELEASED.md).

My UNA World is a Studio-only backup, restore, verification and disaster-recovery manager for UNA CMS.

It is designed to protect UNA sites before risky changes, create verified recovery archives, inspect and dry-run restores, verify restored sites, manage backup retention, and support complete server disaster recovery from a native UNA Studio workflow.

## Core capabilities

- Full verified UNA backups.
- Database backup and recovery.
- Installed-module source preservation.
- Storage/media protection.
- Automatic safety snapshots before selected module lifecycle operations.
- Optional blocking when a required pre-change safety backup fails.
- Restore inspection and dry-run recovery.
- Post-restore verification.
- Rollback support.
- Backup retention controls.
- Archive re-registration after server rebuild.
- SHA-256 integrity verification.
- Backup manifests and environment reports.
- Background manual backups with live Studio progress.

## Project identity

The official vendor identity for this module is **MyUNAWorld**.

Compatibility-sensitive internal identifiers remain unchanged:

- Module name: `my_una_world`
- Module path: `newton/my_una_world/`
- Class prefix: `MyUnaWorld`
- Database prefix: `my_una_world_`

These identifiers are not renamed solely for branding purposes.

## Visitor navigation gate (1.3.3 candidate)

For guests, the public routes are exactly `/`, `/login`, `/member.php`,
`/create-account`, `/forgot-password`, `/terms` and `/privacy`. A single
trailing slash is also accepted on route-style paths. Other clear browser
navigations receive a temporary redirect to the UNA site root. Authenticated
members and administrators are unrestricted.

The gate runs through UNA's `system / design_included` alert. It does not use
`.htaccess`, and it is a navigation gate rather than a file or API security
boundary. Dynamic, HTMX, API and XHR requests bypass it. It also fails open
when authentication or request context cannot be determined safely, including
ambiguous requests that cannot confidently be classified as browser
navigation.

The gate is enabled by default. Administrators can turn it on or off at
Studio → My UNA World → Settings with **Enable Visitor Gate**. The choice is
stored with the existing lifecycle and retention settings in
`.module_lifecycle_settings.json` under the private My UNA World backup
folder. An older or missing settings file has no visitor-gate field and is
treated as enabled. Turning the checkbox off bypasses visitor gating without
changing the `system / design_included` registration; lifecycle backup,
retention and alert-handler settings remain unchanged.

For an emergency rollback during controlled testing, remove only the
`system / design_included` subscription for the existing
`my_una_world_lifecycle` alert handler. Keep the handler and its
`system / before_install` and `system / before_uninstall` subscriptions so
lifecycle protection remains active. Then call
`BxDolAlerts::cacheInvalidate()` from an already bootstrapped, trusted UNA
context and make a fresh HTTP request. See the
[1.3.3 candidate notes](RELEASES/1.3.3.md) for the exact SQL and deployment
constraints.

## Release history baseline

The exact original **1.3.1 FINAL** source imported from `My-UNA-World-1.3.1-FINAL.zip` is preserved on branch:

`release-1.3.1-final`

Original package SHA-256:

`884267e3c34c9633a37b18963964a85a04a0ff5f6632defeb34b9283574a39c9`

The `main` branch advances that verified baseline to **1.3.2**, whose purpose is the vendor-identity migration from `NewtronJax` to **MyUNAWorld** while preserving technical compatibility.

## 1.3.2 release artifacts

Fresh-install package:

`My-UNA-World-1.3.2.zip`

SHA-256:

`5546eb52134cf29d4fb66189442485a73511460790625d316d82263907df60e2`

Native UNA updater from 1.3.1:

`My-UNA-World-Update-1.3.1-to-1.3.2.zip`

SHA-256:

`e6db001ae7cc636eef31204902ebf68bad85868777c16d57169f4d22f6754560`

## UNA Marketplace release standard

Every MyUNAWorld UNA module release must include and verify:

- Correct Studio icon.
- Marketplace header/media.
- Marketplace product description.
- Exact Marketplace internal Name matching the module's `install/config.php` `name` value.
- Correct current version and vendor identity.
- Studio → Apps Market → Downloaded product-information popup.
- Product screenshots suitable for the native UNA Info popup.
- Clean installation/update validation on the designated UNA test environment.

## Safety

Back up an UNA website before performing repair, update, restore or recovery operations. A current verified backup provides the recovery point needed if a change does not complete as expected.
