# TDD Plan

- Scope: unit, integration, and E2E checks aligned with business flows and audits
- Framework: start with lightweight PHP assertions; expand to PHPUnit if needed

## Unit Tests
- Auth helpers: CSRF validate, role checks
- Audit writers: ensure `details` includes `ip`, `ua`, `path` and correct domain/entity/action
- Task utilities: `task_days_remaining`, reminder analysis
- DB DDL guards: `ensure_task_audit_table`, `ensure_global_audit_table`

## Integration Tests
- Task lifecycle: create → status update → comment → upload → notice → alert, with audit rows present
- Clients: create/update/delete/update_services with global audit entries
- Templates: upload/update/delete with file storage and audit
- Checklists: global and per-task updates with audit

## E2E Scenarios
- CEO Audit review: filter by domain, entity, action; export CSV and verify columns match scope
- Reminder acknowledgement: per-role visibility and one-per-day logging
- Access matrix updates: verify permission enforcement on routes

## Test Data & Setup
- Use seed data from `src/data/*.php` or a dedicated test schema
- Environment: `APP_ENV=dev`, separate DB name, clean tables between tests

## Coverage Targets
- 80%+ for `src/utils`, 70%+ for `public/index.php` routing and actions
- Critical paths: audits, auth, uploads, reminders