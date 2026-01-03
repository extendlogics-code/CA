# CA Service Hub

Business-ready PHP prototype for task & resource management across clients, services, and delivery teams. Clean URIs are enforced via rewrites so `.php` extensions never surface.

## What you get
- **Role-aware authentication** for super admin (CEO), employees, and customers with CSRF protection and hardened sessions.
- **Task board** with system-generated IDs, multi-service assignments per client, lead/team ownership, status dropdowns, and date-driven alerts.
- **Comments & evidence**: free-text updates capped at 280 characters plus secure document uploads per task, with download links and audit metadata.
- **Reminder engine + email alerts** that pings CEO, leads, and team members (logged under `storage/logs/reminders.log`) and now emails via PHPMailer/SMTP before items are due within seven days.
- **Per-user acknowledgements**: everyone on a task can acknowledge the reminder from the Task Board; reminders and emails re-fire only for people who haven’t acknowledged on that day.
- **Master data screens** for clients↔services (now backed by MySQL tables), staff rosters, and hierarchy.
- **Lead checklists** so CEOs can review completion before sign-off.
- **Template library** to store standard risk-control matrices, workpapers, and other artefacts in a dedicated repository.
- **Access matrix** page for quick read/write verification by employees and customers.
- **SRMRTMS sample data** – the workbook at `docs/SRMRTMS.xlsx` is ingested into the seed data so the Venkatesh & Co assignments (Task 1122/1124), entity master, and status picklists show up across every screen.

## Quick start
```bash
composer install
# Development (PHP built-in server)
php -S 0.0.0.0:8000 -t public public/index.php
```
- Or run behind Apache/Nginx with `public/` as the web root; Apache rewrites are defined in `public/.htaccess`.
- Configure `config/database.php` / `config/mail.php` or use environment variables.
- Visit `http://localhost:8000/login` and sign in using one of the demo accounts.

### Configuration
- Environment variables: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_ENV=dev` for verbose errors.
- Defaults target PostgreSQL via PDO. To use MySQL/MariaDB, set a DSN in `config/database.php`.

Example `config/database.php`:
```php
<?php return [
  'dsn' => 'pgsql:host=127.0.0.1;port=5432;dbname=ca',
  'username' => 'postgres',
  'password' => 'root',
  'options' => [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ],
];
```

### Testing
```bash
composer install
composer test
```
- Verify autoload: ensure `vendor/autoload.php` exists at `workspace/ca/vendor/autoload.php`.
- If `vendor/` is missing, run `composer install` in the project root.
- Logs are written under `storage/logs/` during tests.
- IDE tip: reload the window so Intelephense re-indexes vendor classes.
- DB-dependent tests are structured to avoid failing when a database is not configured.

## Key flows
1. **Task creation** (`/tasks`): choose a client, tag multiple services, assign a lead & team, set EDC, and optionally drop a kick-off comment.
2. **Evidence uploads**: add files to each task; everything lands under `storage/uploads/tasks` with hashed filenames while preserving download-friendly names.
3. **Checklist reviews** (`/checklist`): leads tick items, CEO passively reviews completion for every client.
4. **Templates** (`/templates`): upload or download standard documents, separated from engagement evidence.
5. **Reminders**: each dashboard or task-board visit triggers the reminder engine so notifications remain fresh even with 1,000+ concurrent sessions.

## Source data
- `docs/SRMRTMS.xlsx` – spreadsheet shared by the business. Its “list of clients”, “Type of assignment”, “Employees”, and “Sample-task” tabs map directly into `src/data/clients.php`, `service_types.php`, `employees.php`, and `tasks.php`.

## Database
- Uses PDO; defaults to PostgreSQL, MySQL/MariaDB supported. DDL differences are handled in code (e.g., `BIGSERIAL` vs `AUTO_INCREMENT`).
- Configure via environment variables or `config/database.php`.
- Audit tables (`task_audit`, `audit_log`) are auto-created on first write.
- Seed data is loaded for demo; replace with real tables incrementally.

## Email alerts
1. Copy `config/mail.php` and update the SMTP credentials (Hostinger or your preferred provider).
2. Flip `'enabled' => true`.
3. When `trigger_due_reminders()` runs (dashboard/tasks visit), an email is sent via PHPMailer and the dashboard highlights how many reminders went out.

## Logs & storage
- `storage/logs/reminders.log` – simulated outbound reminders to CEO/lead/team.
- `storage/uploads/tasks` – evidence attachments (server-side only).
- `storage/uploads/templates` – reusable templates.

See `docs/architecture.md` and the docs below for deeper dives:
- `docs/business-flow.md` – end-to-end business processes and roles
- `docs/api.md` – routes, actions, parameters, responses
- `docs/audits.md` – audit domains, tables, filters, CSV export
- `docs/tdd.md` – test plan (unit, integration, E2E) aligned with business flows
