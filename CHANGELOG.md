# Changelog

## 3.1.0-beta.14

Refactored after inspecting the four actual production PHP endpoints. Removed layered JavaScript selection patches and overlapping conversion behavior. Introduced a shared API, exact catalog identifiers, deterministic source/settings-specific outputs, a resumable bounded queue, CSRF validation, common file locking and audit logging. Unsafe deletion and combined conversion/reference replacement are blocked pending full dependency verification. Added isolated tests and operational documentation.

No earlier historical version tags are asserted. Existing backups require source verification before historical releases can be prepared.
