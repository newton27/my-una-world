# Backup timing and progress

User request: show backup start and completion times on the Backups page, improve its appearance, and make long database exports understandable.

The existing backup engine writes the manifest's `finished_at` before ZIP finalization. It cannot be presented as a verified completion time. Existing background jobs retain a later, reliable `finished_at`; lifecycle backups do not have job records.

## Approach

Use existing worker completion records for older manual backups, and add versioned timing metadata to verification sidecars for new backups. Do not change archive bytes to backfill timestamps. A database schema extension would duplicate the existing job and sidecar records; inferring completion from the embedded manifest would be incorrect. The sidecar approach is the implementation choice for this change, not a new user-selected architecture.

Display Started, Completed and Duration in UTC. Missing or invalid completion evidence displays Not recorded. Failed archives never display a successful completion time. Accept sidecar timing only when its archive name and SHA-256 match the history record. Do not read or hash backup ZIPs while rendering the list.

Use responsive backup cards in the existing UNA theme: status, archive identity, three timing fields and summary counts. Keep inspection/download actions prominent; group restore, dry-run and delete actions under Recovery actions. Preserve CSRF protection, typed RESTORE confirmation, and protected-backup deletion restrictions.

New backups measure preparation, database export, module files, storage, archive finalization, validation and checksum phases. Database export reports table and row progress at table boundaries and at most once per five seconds during a table. Preserve the export algorithm; performance changes require separate evidence. Capture the ten slowest tables without row content.

The current 1.3.4 candidate and its 1.3.3-to-1.3.4 source updater carry the change. Existing release ZIPs, tags, live sites and settings are outside this change. No change to UNA core.

## Acceptance

Real ZIP/SQL round-trip tests cover completion metadata, phases, progress throttling and sensitive-value exclusion. Page tests cover timestamps, unknown history, escaping, and recovery protections. All existing tests run; the historical updater test must compare its immutable 1.3.3 payload with the released checkpoint rather than assume current source is still 1.3.3. Native install/update and target-site performance remain separate checks.
