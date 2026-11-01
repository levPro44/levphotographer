# LevPro Photo Manager

Version **3.1.0-beta.14**. PHP 8.4 / Imagick. Russian private photo dashboard.

This is a reviewable development beta, **not the complete stable v3.1 acceptance target**. The production application has not been replaced. Existing production PHP was inspected before this refactor; photographs, credentials, sessions, private JSON and historical source containing server-specific data are excluded from this repository.

## Implemented

- One selection controller, additive format-based bulk selection, manual selection and JPEG count/size totals.
- Prepared catalog with exact server-side IDs; 50 cards, search, stable sorting/pagination, verified format badges.
- Shared authenticated CSRF-protected API; sequential, resumable one-JPEG steps; source/settings/content-verified skipping; no overwrites.
- EXIF orientation via Imagick, codec detection, format/dimension verification, bounded image resource limits, reports and common locking.
- Read-only source-reference audit, separate static and dynamic results, explicit unresolved CMS dependencies.
- Fail-closed legacy deletion and automatic link replacement endpoints.

## Beta limitations / remaining work

- Reference application with backups and rollback, quarantine/restoration, deletion eligibility and permanent removal are **not implemented**. Deletion and automatic replacement are blocked, not claimed safe or complete.
- CMS/database/remote references and live website behavior have not been comprehensively audited.
- Thumbnail requests use the smallest indexed existing raster variant, not a newly generated thumbnail cache. Large existing variants can still be expensive to download.
- Gallery requests stat catalog paths to obtain current counts; no recursive scan occurs, but very large catalogs can benefit from incremental status refresh.
- Selection persists through format selection-mode changes, not navigation to another page or a server-side search/filter.
- Reindex is CLI-only. External new files appear after explicit reindex. Conversion outputs update the catalog immediately.
- Queues require a browser to advance; do not run concurrent jobs from separate sessions. Single image runtime depends on server codec/CPU.
- Legacy ambiguous format naming is deliberately left separate. Existing historical v1/v2/v3 snapshots have not been verified or tagged.

See [installation](docs/installation.md), [configuration](docs/configuration.md), [security](docs/security.md), [architecture](docs/architecture.md), and [changelog](CHANGELOG.md).

Tests: `node --test tests/selection.test.js`, `php tests/core.php`, PHP syntax checks. Use the installed PHP 8.4 binary on Plesk; no extra PHP installation is necessary.
