# Architecture Overview

## Runtime topology
- **Front controller**: `public/index.php` keeps URIs extension-free via `.htaccess` rewrites and routes every request.
- **Sessions**: PHP strict-mode sessions store only user payload, CSRF, reminder markers, and in-memory master/task data so 1,000+ concurrent logins stay lightweight.
- **Data providers**: demo seeds live under `src/data/*.php` (clients, services, employees, tasks, templates) and are hydrated directly from `docs/SRMRTMS.xlsx` so the prototype mirrors the business workbook. Swap them for database calls later without touching views.
- **Views**: Pages under `src/views` act as slim templates. Helpers/utility layers (`src/utils`) centralize auth, master data, uploads, tasks, and reminder orchestration.

## Flow highlights
1. Requests hit Apache/Nginx, rewrite into `public/index.php`.
2. `src/bootstrap.php` wires helpers, loads seeds into `$GLOBALS['APP_DATA']`, and bootstraps session-level stores for clients, tasks, and templates.
3. Routes enforce `require_auth()` / `require_roles()` before exposing dashboards, task boards, checklists, and template repositories.
4. The **task module** (`src/utils/tasks.php`) issues auto IDs, tracks status, comments, attachments, and provides checklist helpers.
5. The **reminder engine** (`src/utils/reminders.php`) scans due/overdue tasks and logs per-recipient notifications (CEO + lead + team) to `storage/logs/reminders.log`, once per day per recipient.
6. Upload handling lives in `src/utils/uploads.php`, which hashes filenames and stores artefacts under `storage/uploads/*` outside the public root.

## Scaling for 1,000 logins
- **Stateless rendering**: each request recomputes derived data (status counts, reminders) from session seeds, so any number of PHP-FPM workers can be fronted by a load balancer.
- **Minimal session footprint**: task/comment metadata remains lightweight (<2 KB average), and uploads stay on disk, preventing session bloat.
- **Async-ready reminders**: the logging hook can be replaced with a queue/mailer call without touching controllers.
- **Extensible master data**: swap the session bootstrap with PDO/ORM repositories to persist clients, tasks, and templates while reusing the same UI.

## Key directories
- `public/` – document root plus router, assets, and rewrite config.
- `src/utils/` – auth, helper utilities, master data services, tasks, uploads, and reminders.
- `src/views/` – UI for login, dashboard, tasks, master data, checklists, templates, etc.
- `docs/` – architecture & security references.
- `storage/uploads/` – evidence + template documents.
- `storage/logs/` – reminder log simulating outbound email.

## Feature updates (end-to-end)

- **Audit trail (file-backed)**
  - Parsing is centralized in `src/utils/tasks.php` via `fetch_file_audits(...)` which aggregates legacy `storage/logs/audit.log` and the monthly `storage/logs/audit-YYYY-MM.log`.
  - The `/audits` route accepts `domain=file` to render file-backed audits alongside DB-backed audits.
  - The audits view adds a “File” domain filter to switch to file-backed entries.

- **Bulk importers (master data)**
  - Clients CSV importer: `bulk_import_clients_csv(filePath, actor)` in `src/utils/master.php` performs direct prepared `INSERT` into `client` and `client_service_map`.
    - CSV headers expected: `name, client_code, entity_type, entity_subtype, address1, address2, city, state, pin, primary_contact, contact_email, ceo_name, ceo_email, pan, tan, gst, aadhaar, incorporated_on, pco_phone, services`.
    - `incorporated_on` normalization: accepts `YYYY-MM-DD` or `DD-MM-YYYY`/`DD/MM/YYYY`. Values like `NA/N/A/-/null/none` are treated as `NULL`.
    - Upload size limit: 32MB for Clients bulk CSV.
    - On success/failure, flash messages include counts and the first failure reason.
  - Staff CSV importer: `bulk_import_staff_csv(filePath, actor)` in `src/utils/master.php` inserts directly into `employee` and updates `Employee_Code` when provided.
    - CSV headers expected: `full_name, email, role, password, employee_code`.
    - Roles are resolved via `get_roles()`; password must be ≥8 chars.
    - Upload size limit: 8MB for Staff bulk CSV.
  - XLSX uploads: parsed inline in `public/index.php` and validated row-by-row before insert.
  - CSV parsing uses explicit escape parameters for forward compatibility: `fgetcsv($fh, 0, ',', '"', '\\')`.

- **Delete All Clients**
  - New action `delete_all_clients` in `public/index.php` (CEO-only) invokes `delete_all_clients()` utility.
  - Utility `delete_all_clients()` (in `src/utils/master.php`) deletes all rows from `client_service_map` and `client` inside a transaction and writes an aggregated audit: `bulk_delete_all` with a count of deleted records.
  - UI: a “Delete All” button in Client & Service Registry (CEO-only) with confirmation.

- **Flash feedback improvements**
  - Bulk upload success messages append the first failure reason (`failMsg`) when present to aid troubleshooting.
