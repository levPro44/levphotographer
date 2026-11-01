# Validation — 2026-11-01

Validated with the existing Plesk PHP **8.4.27** CLI and Imagick, in an isolated temporary directory outside the production website. All images, password hashes, private data and sessions used for testing were artificial fixtures. A temporary PHP HTTP server bound only to loopback was stopped after the checks.

- PHP syntax checks: all application, CLI and PHP test files passed.
- Node selection tests: 5 passed, 0 failed.
- PHP core checks: path traversal, ancestor symlink escape, empty/malformed CSRF, configuration validation, atomic JSON, corrupt manifest failure, real WebP output, no upscaling, exact settings skip, different-quality output and damaged-result overwrite refusal passed.
- Isolated HTTP checks: directory-aware PDF matching, password login and session regeneration, unauthenticated API and bad CSRF rejection, one-file conversion, completed job idempotency, destructive endpoint refusal, JPEG/PDF preservation and untracked partial-publication failure passed.

Not verified: full browser layout/accessibility across devices, production PHP-FPM runtime, thousands-of-files load/concurrency, EXIF fixture matrix, AVIF encoding end-to-end, source reference replacement, CMS/database dependencies, quarantine/restore, safe deletion or live website behavior. Core tests exercise real WebP encoding only. Do not describe this beta as stable v3.1.

Production PHP and photographs were read only for source inspection and aggregate schema verification. The application directory, private JSON, sessions and website references were not modified. No historical tags were fabricated.
