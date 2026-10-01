MY UNA WORLD 1.3.4
==================

Studio-only UNA 15 backup module.

ZIP INSTALL:
This archive contains exactly one top-level folder:
    my_una_world/

UNA should install that folder under:
    modules/newton/my_una_world/

FIRST TEST:
1. Install and enable My UNA World in UNA Studio.
2. Open the globe icon / My UNA World Studio tile.
3. Click "Open My UNA World".
4. Run "Inventory Test" first.
5. If the inventory ZIP succeeds, try "Create Full Backup".

FULL BACKUP CONTENT:
- Complete UNA database SQL dump
- Source folders for modules currently registered in sys_modules
- Complete UNA storage/ directory
- Module/system inventory and manifest
- ZIP integrity validation
- External SHA-256 checksum file

SECURITY:
Backups are stored outside public_html when writable. A protected tmp fallback
is used only when the hosting account cannot write to the parent directory.

RESTORE:
Restore/import is intentionally NOT included in v1.0.0. Validate backup
archives before adding restore functionality.

1.0.1 FIX
---------
- Explicitly loads classes/MyUnaWorldBackup.php before use.
- Routes backup-history writes through the module instead of accessing a
  protected module database property from the helper class.

1.0.2 FIX
---------
- Corrected Studio uninstall SQL so UNA/MariaDB cannot collapse `@iPageId OR`
  into invalid `@iPageIdOR`.
- Split Studio cleanup deletes into separate SQL statements for safer parsing.

1.0.3 FIX
---------
- Report module version now comes from UNA sys_modules instead of a hard-coded value.
- Installed module source discovery now trusts sys_modules.path first.
- UNA's special `system` registry entry is no longer reported as a missing module folder.

1.0.4 FEATURES
--------------
- Added a protected Delete action to each Backup History row.
- Delete removes the ZIP archive, SHA-256 sidecar, verification JSON, and history record.
- Deletion validates the archive filename and backup directory before removing files.
- Each new backup now creates a small .verify.json sidecar for easy remote verification.
- Verification JSON includes archive SHA-256, integrity status, UNA/PHP versions, and backup statistics.
- Manifest statistics now break out database SQL bytes, installed-module bytes, and storage bytes.
- Backup History offers a Verify JSON download when the sidecar exists.


1.1.0 RESTORE / RECOVERY
------------------------
- Added backup inspection before restore:
  * SHA-256 verification against backup history.
  * ZipArchive integrity validation.
  * Manifest/system report validation.
  * UNA and PHP version comparison.
  * Same-site host validation.
  * Full-backup/database presence validation.
- Added Restore Dry Run:
  * Counts database, module and storage bytes.
  * Counts files that would be overwritten versus newly created.
  * Reports protected My UNA World files skipped during restore.
  * Makes no changes.
- Added synchronous same-site Restore:
  * Requires the administrator to type RESTORE exactly.
  * Automatically creates a fresh full safety backup before any modification.
  * Restores backed-up module files and storage in safe overlay mode.
  * Does not delete unrelated files created after the backup.
  * Restores database tables and views from database/database.sql.
  * Retries dependent VIEW definitions when creation order requires it.
  * Preserves the current My UNA World 1.1.0 recovery engine.
  * Re-registers source and safety backups in history after database restoration.
  * Clears UNA cache and cache_public after successful restore.
  * Writes a private restore_YYYYMMDD_HHMMSS.json report beside the backups.
- 1.1.0 intentionally restores only the scope currently backed up:
  database, installed module source, and UNA storage/media.
- 1.1.0 does NOT restore UNA core source, Apache/Virtualmin configuration,
  SSL certificates, cron jobs, OS configuration, or migrate a backup to a
  different hostname. Cross-site import is intentionally deferred.


1.1.1 VERIFIED RESTORE + ROLLBACK
--------------------------------
- Automatic post-restore verification now runs after every restore/rollback:
  * Database connectivity.
  * Database object count versus source manifest.
  * Installed-module registry count versus source manifest.
  * Current My UNA World version/enabled/path registration.
  * Critical UNA directories.
  * Every restorable module/storage ZIP entry exists.
  * Restored file sizes match the source archive.
  * Installed module source directories from report/system.json exist.
  * Live storage count is at least the backup storage count.
  * Pre-operation safety backup is still present.
- Restore status is now `verified` or `verification_failed` after the live checks.
- Existing restore reports can be re-verified against the current site state.
- Added Restore Reports UI with small downloadable JSON reports.
- Added rollback for the most recent restore:
  * Uses the full safety backup created immediately before that restore.
  * Creates another full safety backup before rollback begins.
  * Runs the same automatic post-rollback verification.
  * Writes rollback_YYYYMMDD_HHMMSS.json.
- The most recent restore's rollback safety backup is protected from Delete
  until rollback is completed/consumed or a newer restore supersedes it.
- No database schema changes are required for the 1.1.1 upgrade.


1.1.2 REGISTRATION SELF-HEAL
----------------------------
- Fixed an otherwise-successful restore being marked verification_failed when
  an older backup restored an older My UNA World sys_modules version.
- Post-restore verification now checks version, enabled, path, URI,
  class_prefix, db_prefix, and vendor for My UNA World.
- If the live My UNA World files are current but its restored sys_modules row
  is stale, 1.1.2 repairs only that module registration and immediately
  verifies it again.
- Verification reports `tool_registration_repaired: true` when this recovery
  path was used and records a warning explaining the repair.
- A missing My UNA World registration still remains a hard verification error.


1.1.3 MODULE LIFECYCLE SAFETY BACKUPS
-------------------------------------
- Automatic full backup before another UNA module is installed.
- Automatic full backup before another UNA module is removed or updated.
- Uses official UNA system/before_install and system/before_uninstall alerts.
- No UNA core files are patched.
- Studio settings:
  * Backup before module install.
  * Backup before module removal or update.
  * Block the module operation if its automatic safety backup fails.
- All three safety options default to ON.
- Settings are stored privately beside backup archives in
  .module_lifecycle_settings.json so they survive database restores/upgrades.
- My UNA World ignores its own install/uninstall events to avoid recursion.
- UNA upgrades run uninstall then install. A 15-minute marker deduplicates the
  second hook, producing one pre-update safety backup instead of two.
- Lifecycle backups record trigger/action/module/version/reason in reports.
- Backup History displays the lifecycle backup reason.

1.1.4 UNA SQL PARSER COMPATIBILITY
----------------------------------
- Rewrote lifecycle enable/disable/uninstall SQL so every statement is parser-safe
  when UNA collapses line breaks.
- Fixed the @sHandlerNameLIMIT syntax failure seen during module database changes.
- Normalized install.sql statements for the same UNA/MariaDB parser behavior.
- No lifecycle or restore feature changes beyond this compatibility fix.


1.1.5 RESTORE REPORT DELETION
-----------------------------
- Added Delete Report action to every row in Restore Reports.
- Deletes only the selected restore_*.json or rollback_*.json report file.
- Never deletes the report's source backup ZIP.
- Never deletes the report's safety backup ZIP.
- Never deletes verify sidecars or site data.
- Deleted reports disappear immediately from Restore Reports.
- Confirmation explicitly states that backup archives are preserved.


1.2.0 NATIVE STUDIO + DISASTER-RECOVERY IMPORT
-----------------------------------------------
NATIVE UNA / BOONEX STUDIO UI
- The primary interface now runs inside UNA Studio's native module page.
- Uses BxTemplStudioModule so the page inherits normal UNA Studio:
  * Header and breadcrumb.
  * Module activation switch.
  * Side navigation.
  * Module content blocks.
  * Studio typography, spacing, and buttons.
- Native navigation pages:
  * Overview
  * Backups
  * Recovery
  * Settings
- action.php is retained only as a compatibility redirect into Studio.

DISASTER-RECOVERY IMPORT
- Recovery can scan the private My UNA World backup folder for existing ZIPs.
- Unregistered ZIPs can be validated and re-registered into Backup History.
- Registration validates:
  * Filename safety.
  * ZipArchive CHECKCONS.
  * manifest.json.
  * report/system.json.
  * My UNA World product marker.
  * Backup mode.
  * database/database.sql for full backups.
  * SHA-256.
  * Existing Verify JSON SHA-256 when present.
  * Existing .sha256 sidecar when present.
- Missing checksum sidecars are recreated after successful validation.
- Missing Verify JSON sidecars are recreated with imported=true.
- Smaller ZIPs can be uploaded through Studio with optional Verify JSON.
- Existing files are never silently overwritten during upload.
- Large backups should be copied directly to the private backup folder with
  SFTP/SCP/hosting file manager and then registered in Recovery.
- This is the bootstrap path for a complete server-loss recovery:
  1. Install the matching UNA core.
  2. Install/enable My UNA World.
  3. Copy backup ZIP and sidecars into the private backup folder.
  4. Recovery -> Register Backup.
  5. Backups -> Inspect.
  6. Backups -> Dry Run.
  7. Backups -> Restore.
  8. Automatic post-restore verification.

IMPORTANT
- 1.2.0 remains same-hostname restore. Migration to another domain is still
  intentionally blocked.
- A backup does not contain general UNA core/server/SSL/OS configuration.


1.2.1 BACKUP RETENTION
----------------------
- Added configurable backup retention in Studio -> My UNA World -> Settings.
- Maximum backup ZIPs to keep:
  * 0 = unlimited.
  * Positive value keeps the newest eligible archives.
- Delete backups older than N days:
  * 0 = disabled.
  * Positive value deletes eligible backups older than that age.
- The count and age rules are OR conditions: a backup is eligible for deletion
  when it exceeds either configured limit.
- Protected rollback safety backups are never automatically deleted.
- Added Run Cleanup Now.
- Retention automatically runs after successful normal/lifecycle backups.
- Pre-restore and pre-rollback safety backups explicitly skip retention while
  the recovery operation is active.
- Retention runs after a completed restore/rollback, when rollback protection
  is known and can be honored safely.
- Retention defaults are non-destructive:
  * Maximum archives = 0 (unlimited).
  * Maximum age = 0 (disabled).


1.3.0 BACKGROUND BACKUPS + LIVE PROGRESS
----------------------------------------
- Manual Inventory Test and Full Backup actions are now queued background jobs.
- Uses UNA's native sys_cron_jobs / periodic cron worker instead of relying on
  the Studio browser request to remain connected.
- The administrator may leave My UNA World after a manual job is queued.
- Returning to Overview or Backups shows the active/recent job.
- Native progress line reports approximate stages:
  * Queued
  * Starting
  * Preparing
  * Inventory
  * Database
  * Module files
  * Storage
  * Finalizing
  * Integrity check
  * Checksum
  * Recording
  * Complete / Failed
- Progress is polled every 2 seconds while the page is open.
- Added status.php (administrator-only JSON endpoint).
- Added my_una_world_jobs queue table. Existing installations create it lazily,
  so a database reinstall is not required just to gain the job queue.
- Added MyUnaWorldCron, scheduled every minute through UNA sys_cron_jobs.
- Settings reports cron registration, last worker heartbeat, and worker health.
- Stale "running" jobs older than 12 hours are marked failed automatically.
- Only one manual background backup may be queued/running at a time, preventing
  accidental concurrent multi-gigabyte backups.
- Automatic module-install/update/removal safety backups remain SYNCHRONOUS by
  design. UNA must wait for those backups to finish before it is safe to
  continue changing the target module.
- Pre-restore/pre-rollback safety backups also remain synchronous because the
  recovery operation must not proceed until the safety snapshot is complete.
- Restore/rollback themselves remain foreground Studio operations in 1.3.0.

1.3.1 BACKGROUND BACKUP RE-SUBMISSION FIX
-----------------------------------------
- Fixed a Studio polling bug that could repeatedly queue the same manual
  Inventory Test or Full Backup after the background job completed.
- The completed-job refresh now performs a fresh GET navigation instead of
  reloading the original POST response, preventing browser POST resubmission.
- The fix applies to both manual inventory and manual full background backups.
- No database schema changes are required from 1.3.0.


1.3.2 MYUNAWORLD VENDOR IDENTITY
---------------------------------
- Official module vendor changed from NewtronJax to MyUNAWorld.
- Restore verification and registration self-heal now require MyUNAWorld.
- Existing technical identifiers remain unchanged for upgrade compatibility:
  * Module name: my_una_world
  * Module path: newton/my_una_world/
  * Class prefix: MyUnaWorld
  * Database prefix: my_una_world_
- No database schema changes are required from 1.3.1.
- This is a vendor-identity/version maintenance release; backup, restore,
  rollback, retention and background-worker behavior are otherwise unchanged.



1.3.4 MAINTENANCE PAGE CONTROLS
-------------------------------
- Keeps the existing My UNA World Visitor Gate setting and behavior.
- Adds My UNA World maintenance-page presentation controls to Studio Settings.
- Header and footer visibility can be enabled or disabled independently.
- Administrators can personalize the maintenance heading, main message,
  patience message, return/status message, and footer message.
- Adds the "Use My UNA World Page" switch so My UNA World can use its branded
  maintenance presentation or preserve the site's existing UNA maintenance page.
- Does not add Visitor Gate to My UNA Doctor Settings.
- Includes a native UNA updater from exactly 1.3.3 to 1.3.4.
