# My UNA World

Your database, installed modules, and storage files, all safely covered.

A site is only as safe as its last backup. This tool creates a complete copy of your UNA site from inside Studio, and it can run on its own whenever your site changes.

Version 1.3.8

My UNA World is a free backup, verification, and recovery manager for UNA CMS. Its full backup covers the UNA database, installed module source, and supported local storage files.

Automatic protection
Enable safety backups before module installations, updates, and removals. You can also start a backup from Studio. Automatic safety backups apply to these supported module changes when the corresponding settings are enabled.

Maintenance during full backups
Public access pauses while a full backup runs. The original animated maintenance page includes its server video, countdown, and estimated progress without product branding. Studio stays available, and the previous maintenance settings are restored automatically when the backup finishes or fails. The estimate does not reopen a site before the backup is finished. Existing maintenance is preserved.

Backup history and verification
See start and completion times in UTC, duration, live job progress, and recorded phase timings. Verify SHA-256 checksums and ZIP consistency, inspect backup contents, and download recovery archives.

Recovery
Import existing archives, inspect compatibility, and run a restore dry run before making changes. Restore requires typed confirmation and creates a safety backup. Verification and rollback controls help you review the result.

Retention
Limit ordinary backup archives by count or age. Protected rollback safety backups are preserved and may remain in addition to the ordinary retention limit.

Installation and Studio updates
Current module version: 1.3.8.
Install through UNA Studio using the package offered for your version. The 1.3.8 source includes the native 1.3.6 → 1.3.8 update route. Versions 1.3.3 and 1.3.4 first update to 1.3.5 using their matching updater. Earlier installations follow the available intermediate update chain. Marketplace delivery uses Studio’s offered updates when the product is associated with the site’s Marketplace account.

Backup scope
The archive covers your UNA database, installed module source, and supported local storage/media. Keep a verified copy off the server. For complete server-loss recovery, also retain the matching UNA core and separate backups of DNS, certificates, web-server settings, mail, external storage, credentials, and server cron configuration.

Compatibility
UNA: 15.0.x
PHP: 8.1–8.4
Interface: UNA Studio
Vendor: MyUNAWorld
Price: Free


Release contract and technical requirements: [MARKETPLACE.md](MARKETPLACE.md).
