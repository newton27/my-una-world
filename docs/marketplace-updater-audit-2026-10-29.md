# My UNA World Marketplace updater audit — 29 October 2026 (America/New_York)

## Scope and evidence

Read the current Git trees, module manifests, available branches and release assets for 12 My UNA repositories. Inspected the signed-in Marketplace Manage Products page and the nine product listings shown there, including Albums LightGallery. Used Download → More → Show More to reveal historical and update files where a Download action was available. Inspected Mail Doctor's edit form without submitting any change.

No Marketplace files, product identifiers, prices, main-download selections, releases, tags or production sites were changed.

## Marketplace reconciliation

| Product | Observed Marketplace download state | Git evidence | Required action / gate |
|---|---|---|---|
| Styled Posts | 0.3.2 installer only; no visible updater | 0.3.3 correction at ca691d40ce1c1219741f5af7ff2e9bf8f5cd3e5c; draft PR newton27/my_una_styled_posts#1; CI passed | Native 0.3.2→0.3.3 update and clean install, then attach full version and separate update; reconcile older chains 0.1.0→0.2.0→0.3.0→0.3.1→0.3.2 |
| Weather | Main 0.1.1-marketplace ZIP; older 0.1.1 ZIP; no update listed | v0.1.1 has 0.1.0→0.1.1 updater asset; scripts/package_update.py builds a modules/... manual-extraction layout and still declares 15.0.0-RC2 | Inspect exact asset, reconcile packaging/compatibility with current approved conventions, native test Marketplace layout before upload |
| Translator | Main 0.1.9 candidate; two unversioned 0.1.10 RC1 files appear under older versions; no update listed | main declares 0.1.10; v0.1.10-rc.1 includes 0.1.9→0.1.10 updater; other feature branches exist | Resolve authoritative customer release and version metadata; do not promote an RC automatically; inspect updater bytes and test correct baseline |
| Finances | Main 0.1.0-marketplace ZIP and older 0.1.0 ZIP | v0.1.1 full installer only, identical to main runtime 9dfad6413643ed9cf285a1555d55d6c7a2c1b8cd | Draft PR newton27/my-una-finances#1 adds missing 0.1.0→0.1.1 updater with unchanged release runtime, language update and cache clears; native test required |
| Mail Doctor | No Download action; edit form shows my-una-email-doctor0.6.0.zip, Type version, 0.6.0, Use as Main unchecked | v0.6.0 includes frozen full installer and 0.5.1→0.6.0 updater; release notes report native tests | Replace source-archive distribution with exact certified packages after byte/layout reconciliation; set correct main installer and update metadata; preserve mailbox settings/credentials and suppressions |
| My UNA World | 1.3.3 full installer only | v1.3.3 includes 1.3.2→1.3.3 updater; main declares 1.3.4 | Reconcile exact existing validated 1.3.3 updater; do not mix 1.3.4 source into a 1.3.3 package |
| Module Doctor | 0.1.0 studio ZIP only; Manage active switch unchecked | v0.1.1 full installer only, no updater; tag compatibility RC1; main contains later runtime/compatibility changes under 0.1.1 | Select a new authoritative version before building missing updater(s); do not label main bytes as frozen v0.1.1 |
| My UNA Doctor | Main 0.6.6; old 0.6.1/0.6.2; visible updates 0.6.1→0.6.6 and 0.6.2→0.6.6 | v0.6.6 also has 0.6.3/0.6.4/0.6.5→0.6.6 assets; fix/una15-compatibility-067 declares 0.6.7 | Reconcile 0.6.7 correction and exact updater packages first; do not blindly publish older 0.6.6 assets with known prior compatibility/updater concerns |
| Albums LightGallery | Main 0.1.0-marketplace ZIP and older 0.1.0 module ZIP | Separate repository; current source not certified against Marketplace ZIP in this audit | No newer Marketplace target established; inspect source/version and exact distribution before declaring an updater necessary |

## Other repositories checked

Tickets and Jitsi main both declare 0.1.0; no updater directories found. Neither appears in the nine Manage Products rows inspected. No upgrade baseline established.
Stream Doctor main declares 0.1.5 but the latest visible GitHub release is 0.1.4; feature/ome-stream-control-0.1.5 exists. No Marketplace listing was present in the nine rows inspected.
Online TV main still declares 0.1.0; release/tv-013-reconcile-20261024 and prepare/tv-014-icons-final-20261029 contain release work. Preserve its separate controlled native acceptance and supported 0.1.0/0.1.2/0.1.3 update paths.

## Native Marketplace package contract

UNA studio/classes/BxDolStudioInstallerUtils.php::performUnarchive uses the first ZIP member as the package folder. performCopy then reads install/config.php directly below that folder and copies it to modules/<home_dir>.
A Marketplace ZIP must therefore contain exactly one explicit top-level package folder. A nested modules/newton/... manual-extraction layout is a separate artifact, not proof of a valid Marketplace download.
An updater must include version_from, version_to, matching module_dir/module_uri/home_dir, an updater class extending BxDolStudioUpdater, replacement files under source/, and required language/SQL actions.
Do not replay a fresh-install SQL file as an update. Preserve native hash/version checks and never modify UNA core.

## Work completed

- Styled Posts draft PR #1: comments heading fix; version 0.3.3; native 0.3.2→0.3.3 configuration; single-root Marketplace installer; PHP regression counts 0/1/12; CI builds checksummed ZIPs.
- Finances draft PR #1: exact 0.1.1 runtime copied into a native 0.1.0→0.1.1 updater; fresh-install registration INSERTs not replayed; revised language keys imported; single-root updater ZIP. Initial PHP/package CI passed.
- Finances also had an unrelated pre-existing Laravel workflow failure (missing .env.example/composer project). The draft replaces that scaffold with all four real UNA test scripts on PHP 8.2/8.3/8.4. Final matrix result must be verified.

## Gates

| Gate | Status | Next action |
|---|---|---|
| Repository and Marketplace inventory | PASS for scope above | Continue byte-level package reconciliation |
| Styled Posts regression and package build | PASS at ca691d40ce1c1219741f5af7ff2e9bf8f5cd3e5c | Native clean install and 0.3.2 update |
| Finances initial PHP/package workflow | PASS at 588239dc877878710ad0d29a1cba70efb5cd37db | Verify final test-matrix commit and native 0.1.0 update |
| Native Studio validation | BLOCKED | Browser automatic approval rejected gayjax.life access; obtain explicit user authorization for that site |
| Marketplace publication | NOT TESTED / NOT PERFORMED | Upload only validated exact bytes, Type update and exact from/to versions; preserve full installer as main |
| Existing GitHub release changes | NOT PERFORMED | Separate specific approval required by controlled-release skill |

No claim of suite-wide release readiness is made.
