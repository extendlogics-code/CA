# Security & Validation Notes

## Authentication & sessions
- Passwords are hashed with `password_hash()` (bcrypt) and verified via `password_verify()` before regenerating strict-mode sessions.
- HTTP-only cookies with optional `session.cookie_secure` guard session identifiers; logout flushes data + cookie.

## Authorization model
- Route guards ensure only staff access tasks, clients, staff rosters, checklists, templates, and hierarchy pages, while customers retain access to the shared access-matrix view.
- Checklist updates are limited to the assigned lead (matched via email) or CEO (super admin) per task.

## Input validation
- All mutating forms carry CSRF tokens validated server-side.
- Task comments enforce trimming and a 280-character limit before storage.
- Dropdowns (status) and multi-selects (services) are validated against whitelists to avoid arbitrary data injection.
- File uploads go through `save_uploaded_file()`, which limits extensions, size (5 MB default), hashes filenames, and stores everything outside `public/`.

## Transport & deployment
- Behind HTTPS, keep `session.cookie_secure=1` and add headers such as HSTS, CSP, and `X-Content-Type-Options` at the web server/proxy layer.
- Uploaded artefacts sit outside the `public` directory; downloads stream through PHP routes with authorization checks.

## Load & resiliency
- Reminder logging is idempotent per task/recipient/day and can be swapped with queue-backed email delivery without altering controllers.
- Evidence/template storage is filesystem-based for the prototype; in production move to object storage (S3, Azure Blob) with signed URLs for durability and CDN coverage.
- Consider rate limiting login attempts and adding audit trails/immutable logging for checklist updates before going live.
