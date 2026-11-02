# My UNA World

**Vendor:** MyUNAWorld  
**Platform:** UNA CMS  
**Module:** `my_una_world`  
**Current version:** 1.3.5

**Price:** Free

Version 1.3.5 adds backup start/completion times, duration, phase timings and
live database table/row progress. It includes a full installer and separate
native updaters from 1.3.3 and 1.3.4. Existing Visitor Gate and maintenance-page
controls remain available.

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
- UTC backup start/completion times and elapsed duration.
- Recorded backup phase durations and slowest database tables.

## Install or update

For a fresh installation, upload `My-UNA-World-1.3.5.zip` through UNA Studio's
native Apps uploader, then install and enable My UNA World. The package contains
one top-level `my_una_world/` folder and installs under
`modules/newton/my_una_world/`.

For an existing installation, check the version shown in Studio and use the
matching native updater:

| Installed version | Update package |
| --- | --- |
| 1.3.3 | `My-UNA-World-Update-1.3.3-to-1.3.5.zip` |
| 1.3.4 | `My-UNA-World-Update-1.3.4-to-1.3.5.zip` |

Use UNA Studio's native upload/update flow. Earlier versions must first follow
their matching update chain to 1.3.3. After installation or update, confirm
Overview shows 1.3.5, run **Inventory Test**, and then create a full backup.

## Backup timing and progress

Backups shows **Started**, **Completed** and **Duration** for each archive.
Times are UTC. While a backup runs, the page shows its start time, elapsed
duration and current stage. During database export, progress identifies the
current table and exported row count. Details for completed backups include
recorded phase durations and the slowest database tables.

Older backups use existing completion records when available; missing timings
show **Not recorded**. Existing archives are preserved. These measurements
help identify slow stages; database export and restore algorithms are unchanged.

## Project identity

The official vendor identity for this module is **MyUNAWorld**.

Compatibility-sensitive internal identifiers remain unchanged:

- Module name: `my_una_world`
- Module path: `newton/my_una_world/`
- Class prefix: `MyUnaWorld`
- Database prefix: `my_una_world_`

These identifiers are not renamed solely for branding purposes.

## Visitor navigation gate (1.3.3+)

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

## Maintenance page controls (1.3.4+)

Studio → My UNA World → Settings now includes the maintenance presentation
controls used by My UNA World workflows: header/footer visibility, heading,
main message, patience message, return/status message, footer message, and the
**Use My UNA World Page** switch. Visitor Gate remains a separate My UNA World
setting.

## UNA Marketplace release standard

Every MyUNAWorld UNA module release must include and verify:

- Correct Studio icon.
- Marketplace header/media.
- Marketplace product description.
- Exact Marketplace internal Name matching the module's `install/config.php` `name` value.
- Correct current version and vendor identity.
- Full installer ZIP and native updater ZIPs for supported prior versions.
- Studio → Apps Market → Downloaded product-information popup.
- Product screenshots suitable for the native UNA Info popup.
- Clean installation/update validation on the designated UNA test environment.

## Safety

Back up an UNA website before performing repair, update, restore or recovery operations. A current verified backup provides the recovery point needed if a change does not complete as expected.
