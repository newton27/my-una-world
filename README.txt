MY UNA WORLD 1.3.5
==================

Free Studio-only backup, restore and recovery module for UNA 15.
Vendor: MyUNAWorld
Internal module name: my_una_world

INSTALLATION
------------
For a fresh installation, upload My-UNA-World-1.3.5.zip through UNA Studio's
native Apps uploader, then install and enable My UNA World.

The installer contains one top-level folder:
    my_una_world/
It installs under:
    modules/newton/my_una_world/

UPDATING AN EXISTING INSTALLATION
--------------------------------
Check the currently installed version in Studio, then use its exact updater:
- 1.3.3: My-UNA-World-Update-1.3.3-to-1.3.5.zip
- 1.3.4: My-UNA-World-Update-1.3.4-to-1.3.5.zip

Use UNA Studio's native upload/update flow. Earlier versions must follow their
matching update chain to 1.3.3 first. This update replaces runtime files and
clears the module cache; it does not change the database schema.

FIRST BACKUP
------------
1. Open Studio -> My UNA World and confirm Overview shows 1.3.5.
2. Run Inventory Test first.
3. When the inventory ZIP completes, choose Create Full Backup.
4. Keep the backup ZIP, SHA-256 sidecar and Verify JSON together.

Manual backups run through UNA's native cron worker. The administrator may
leave the page after a backup is queued. Overview and Backups show its active
status when reopened. The Settings page reports cron registration and worker
health. Automatic pre-change safety backups remain synchronous.

BACKUP CONTENTS
---------------
- Complete UNA database SQL dump.
- Source folders for modules currently registered in sys_modules.
- UNA storage/media files.
- Module/system inventory and manifest.
- ZIP integrity result, external SHA-256 checksum and Verify JSON.

Backups use a private directory outside public_html when writable. A protected
tmp fallback is used when the hosting account cannot write to the parent folder.
The active path is shown on Overview.

TIMING AND PROGRESS
-------------------
The Backups page shows Started, Completed and Duration. All times are UTC.
Active backups show their start time, elapsed duration and current stage.
During database export, progress identifies the current table and exported row
count. Completed backup details show recorded phase durations and the slowest
database tables.

Older backups use existing completion records where available. Missing timings
show Not recorded. Existing ZIPs are preserved. Timing identifies slow stages;
the database export and restore algorithms are unchanged in this version.

RESTORE AND RECOVERY
--------------------
Inspect an archive and run Dry Run before a same-site restore. Restore requires
typed RESTORE confirmation and creates a full safety backup before changes.
Recovery overlays backed-up database, installed-module source and storage,
preserves the current My UNA World recovery engine, and verifies the result.
It does not restore UNA core files, operating-system or web-server settings,
SSL certificates, or migrate backups to a different hostname.

The latest restore's rollback safety backup is protected from deletion.
Recovery can scan and register existing archives after a server rebuild.
Large archives should be copied into the private backup folder and registered
through Recovery. Keep the matching UNA core available for disaster recovery.

SETTINGS
--------
- Automatic safety backup before another module is installed, removed or updated.
- Optional blocking when a required automatic safety backup fails.
- Retention by maximum archive count or age; defaults preserve all archives.
- Visitor Gate restricts logged-out browser navigation to public access routes.
- Maintenance-page header/footer visibility and personalized messages.

Visitor Gate and maintenance-page controls are managed in My UNA World Settings.
Lifecycle/retention settings are kept privately beside the backup archives so
they survive database recovery. Protected rollback backups remain excluded from
retention cleanup.

Before updating or restoring, keep a current verified backup and a recovery copy.
