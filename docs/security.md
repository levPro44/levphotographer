# Security and recovery

All processing POSTs require an authenticated session and a nonempty exact CSRF token. Login/logout also validate CSRF. Passwords and session tokens are never logged. Rotate/revoke the temporary maintenance SSH key when the work ends.

The browser submits group/job identifiers, never authoritative paths. Files are resolved from a private prepared catalog, confined by realpath and component-wise symlink rejection. Only raster MIME types can be served. AI/PDF cannot enter JPEG conversion or deletion. Private JSON remains outside the web root. Treat the site account and image tree as trusted: hostile concurrent filesystem changes by another privileged process are outside the lock's protection.

One advisory operation lock serializes cooperating file operations. Writes use private temporary JSON plus atomic rename. Converted files are validated, published with a no-overwrite hard link, and recorded with source/output hashes. Originals are never removed. A crash between publishing and recording may leave an untracked output; retries fail closed rather than overwrite it. An administrator must inspect the orphan and report before retrying. A conversion record written before catalog failure can be recovered by reindex.

Do not enable the old permanent-delete endpoint or combined optimizer. This beta intentionally refuses those operations until comprehensive reference auditing, private backup, rollback, quarantine and restore are implemented and verified. An existing WebP is never proof that a JPEG can be removed.

No production application changes are made by preparing the repository. Tests create temporary fixtures only. Historical backups can contain embedded credentials and must be reviewed before any future public commit.
