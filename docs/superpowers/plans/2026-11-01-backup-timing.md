# Backup Timing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show trustworthy start/completion/duration and database progress on a clearer native Backups page.

**Architecture:** Existing worker records supply historical completion times; verified sidecars supply new timing and phase metadata. A pure timing resolver validates association and renders unknown history honestly. No schema change or archive rewrite is required.

**Tech Stack:** Existing PHP/UNA classes, ZipArchive, native HTML/CSS and existing status polling.

**Spec:** ../specs/2026-11-01-backup-timing-design.md

## Global Constraints

- No UNA core, production, release asset or settings changes.
- Versioned sidecar timing must match the archive name and SHA-256.
- Display timestamps in UTC; never treat legacy preparation as completion.
- Preserve native recovery confirmations and protected backup handling.
- Preserve SQL export behavior; do not claim a performance fix.

## Review Focus

- Legacy lifecycle archives with no reliable finish time must remain unknown.
- Imported/mismatched sidecars cannot attach another archive's timing.
- Failure during export/finalization must not produce complete timing.
- Progress writes must be throttled within large tables.
- Card layout must retain escaping, CSRF and rollback protection on mobile.

### Task 1: Trustworthy timing and progress

**Files:** classes/MyUnaWorldBackup.php, classes/MyUnaWorldDb.php, classes/MyUnaWorldModule.php; new classes/MyUnaWorldBackupTiming.php; tests/test_backup_timing.php and tests/test_backup_progress.php.

**Interfaces:** `MyUnaWorldBackupTiming::resolve(array $record, array $verification)` returns started_at, completed_at, duration_seconds and phases. `getBackups()` enriches records with this timing. Verification metadata adds `timing.version = 1` after archive validation/checksum.

- [ ] Write failing tests for worker and sidecar completion, legacy/malformed association, real ZIP completion and database progress.
- [ ] Verify RED against the original source.
- [ ] Implement resolver, batched worker-history lookup, bounded sidecar reads and engine phase measurements.
- [ ] Verify real archive contents/checksum, preserved SQL rows and throttled progress without row values.
- [ ] Commit the tested timing change.

### Task 2: Native Backups page

**Files:** classes/MyUnaWorldStudioPage.php, status.php; tests/test_backup_page.php and tests/test_backup_status.php.

**Interfaces:** Backups consume enriched record timing. Status adds server_time for elapsed display, retaining existing access checks and job fields.

- [ ] Write failing page tests for timing labels, unknown finish, escaping and protected recovery actions.
- [ ] Verify RED; implement responsive native cards and live elapsed/progress details.
- [ ] Verify GREEN and inspect the generated layout at desktop and narrow widths.
- [ ] Commit the tested presentation change.

### Task 3: Native updater and full validation

**Files:** updates/1.3.3_1.3.4/source/, tests/test_updater.php, new candidate updater regression and validation command; RELEASES/1.3.4.md.

- [ ] Pin historical updater expectations to the released 1.3.3 checkpoint; retain existing historical SQL/language tests.
- [ ] Synchronize every changed runtime file into the current native updater and test byte identity.
- [ ] Run the complete suite and PHP syntax checks, review the diff and verify historical payloads/assets are unchanged.
- [ ] Record automated results and remaining native/performance checks; do not publish or deploy.
