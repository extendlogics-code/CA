# Audits

- Tables: `task_audit` (task-only), `audit_log` (global)
- Writer: `write_task_audit` and `write_audit` capture `ip`, `ua`, and `path`
- Auto DDL: tables created if missing

## Domains
- `task`, `client`, `staff`, `service_type`, `status`, `template`, `checklist`, `task_checklist`, `hierarchy`, `access`, `lead_teams`

## Fields
- `task_audit`: `id`, `task_id`, `action`, `actor_id`, `actor_name`, `actor_role`, `details`, `created_at`
- `audit_log`: `id`, `domain`, `entity_id`, `action`, `actor_id`, `actor_name`, `actor_role`, `details`, `created_at`

## CEO Audits Screen
- Route: `/audits`, CSV export via `export=csv`
- Filters: `domain`, `task`, `entity`, `actor`, `action`, `from`, `to`, `q`
- Views: Table, Cards, Timeline with local storage view mode

## Code References
- Writer: `src/utils/tasks.php:1289–1327`
- Fetchers: `src/utils/tasks.php:1329–1352` (task), `src/utils/tasks.php:1329` (global)
- Route: `public/index.php:113–148`
- View: `src/views/audits.php:47–153`