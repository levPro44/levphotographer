# Architecture

`lib.php` owns configuration, private JSON I/O, sessions/CSRF, safe paths, common locking, conversion and reports. `index.php` renders the prepared catalog and serves catalog-authorized rasters without recursive discovery. `assets/selection.js` is the only selection model; `app.js` binds one set of UI handlers and advances one file per API step.

`bin/reindex.php` is the only full image discovery path. Existing JPEG grouping is retained from the legacy index. Additional formats match full directory and stem; ambiguous stems remain separate. Legacy generated WebP names match the complete source stem and source-relative-path hash, never a short camera ID. Unrelated directories remain distinct. Files absent on disk are excluded from displayed counts.

The authenticated API creates a private job containing resolved indexed JPEG paths, group IDs and immutable conversion settings. Each step saves `processing` before work; retrying an interrupted step either validates/skips its recorded output or fails closed on an untracked destination. Reports distinguish pending/processing/converted/skipped/failed. Output names incorporate the full source path, source content hash and settings. Equal extensions alone never cause a skip.

The dry-run source audit examines bounded HTML/HTM/PHP/CSS/JS files, excluding images and this application. Its exact root-relative URL matcher covers common src/srcset/picture/link syntax, including commas and query/fragment suffixes. Relative, encoded, constructed, external and database references remain unresolved; it cannot certify global replacement or safe deletion.
