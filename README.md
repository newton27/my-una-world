# My UNA World

**Vendor:** MyUNAWorld  
**Platform:** UNA CMS  
**Module:** `my_una_world`

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

## Project identity

The official vendor identity for this module is **MyUNAWorld**.

Compatibility-sensitive internal identifiers such as `my_una_world`, existing database prefixes, class prefixes and installed module paths are not renamed solely for branding purposes.

## Current verified release reference

The newest release package currently identified for repository import is:

`My-UNA-World-1.3.1-FINAL.zip`

SHA-256:

`884267e3c34c9633a37b18963964a85a04a0ff5f6632defeb34b9283574a39c9`

The module source will be imported from the verified release package after repository privacy is confirmed.

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
