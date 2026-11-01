# Installation and rollback

Do not overwrite production with this beta. First copy the repository into a staging directory outside the public web root. Use isolated `images`, `data`, `data/sessions` directories and a test password hash. Run the PHP/core tests there; no production photographs are needed.

For a future reviewed deployment, back up all four original PHP files and take a private data backup. Keep them outside the web root, owned by the site account with private permissions. Never publish backups through GitHub.

Place `photo-manager/` under the web root only after staging acceptance. Private data must remain outside the web root, writable only by the website system user. Reuse the existing password hash and session directory; do not embed their contents in code. Configure the site root via the PHP-FPM environment `LEVPRO_SITE` or an absolute `LEVPRO_CONFIG` file path.

Run `bin/reindex.php` via the existing Plesk PHP 8.4 CLI as the website system user, with the same configuration environment. It reads the legacy photo index, scans the image tree once, then writes `catalog-v31.json`. This never converts or removes originals.

`bin/audit.php` creates a private dry-run report from verified v3.1 conversion mappings. It never edits source files. Existing legacy conversions without exact v3.1 settings/hash records are not treated as verified replacement targets.

Rollback: stop advancing conversion jobs, restore the original application directory from the private source backup, leave all JPEGs untouched. New outputs are additive; do not delete them automatically because they may have been referenced manually. Private v3.1 catalog/reports can remain for recovery. No database migration is performed.
