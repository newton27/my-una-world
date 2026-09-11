# Unreleased — September 11, 2026 production hotfixes

This records the changes deployed to seven UNA installations during the
September 11 server cleanup. It is a source synchronization, not a new
Marketplace release. Version metadata remains 1.3.2. Historical 1.3.2 ZIP
hashes and the 1.3.1-to-1.3.2 updater remain unchanged.

## Included

- Non-blocking engine lock prevents simultaneous backup execution per site.
- Shared queue/worker lock prevents concurrent queue checks and worker claims.
- Locks release on normal return and exceptions.
- Retention counts only complete full-backup history entries with a matching
  ZIP file present; failed, inventory, missing and symlink entries are excluded.
- Studio safety notice reflects install/removal safety settings and updates
  its message during module actions.
- Enable SQL registers the notice; disable and uninstall SQL remove it.

## Existing installations

Deploy both changed PHP classes together, preserving site ownership. For the
notice, execute only the sys_injections_admin statements appended to
install/sql/enable.sql in the site's UNA database. Do not replay the entire
enable script solely to register the banner, since it also changes lifecycle
hooks and cron registration.

After registration, invalidate the Studio injection cache through UNA:

```php
BxDolDb::getInstance()->cleanCache('sys_injections_admin.inc');
```

Refresh Studio's Apps page. The notice appears when at least one automatic
safety-backup setting is enabled; it is omitted from My UNA World's own page.
Do not change saved backup settings as part of this deployment.

## Validation and limits

User-supplied deployment results recorded PHP syntax checks, lock contention
and exception-release tests, retention selection tests, and deployed-file
comparisons. GayMenOnline completed inventory job #3 and the Studio notice
was visually confirmed after cache invalidation.

The engine SHA-256 matches the tested deployed file:
aa455d1e08ab7c6486c92ded5feaff3bba2942371b36181deb985b97ef65e176.
Before adding the notice, the module SHA-256 matches:
ee7ca35ab6b89cf2625adedb3e60192637d9e7cd9174286587b46ba2f4f4ac85.

Retention does not revalidate ZIP contents or guarantee restorable snapshots.
Existing age-limit behavior and history query limits are unchanged. The
notice describes successful-backup gating, but failure blocking still depends
on the site's block_on_failure setting.

Private archive staging, abandoned temporary-file cleanup, and a versioned
native updater for these hotfixes remain outstanding. Do not relabel or
overwrite the old release archives as though they contain these changes.
