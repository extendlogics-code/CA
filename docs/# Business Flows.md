# Business Flows

- Roles: `ceo`, `lead`, `employee` with access checks enforced via helpers
- Storage: PDO-backed data plus demo seeds from `src/data/*.php`
- Auditing: Task-level (`task_audit`) and global (`audit_log`) across domains

## Task Management
- Create task (CEO): client, services, lead/team, status, due dates, initial comment
- Update status: any authorized role, records `status_update`
- Comments & attachments: `add_comment`, `upload_attachment` logged
- Notices & alerts: `update_notice`, `update_alert` with reminder acknowledgements
- Where: `public/index.php:160–350`, `src/utils/tasks.php:1172+`

## Clients
- Create/update/delete, update services mapping
- Global audits: `client:create`, `client:update`, `client:delete`, `client:update_services`
- Where: `public/index.php:679–905`

## Staff & Hierarchy
- Update hierarchical relationships and memberships
- Global audits: `hierarchy:update`, `lead_teams:add_member`, `lead_teams:remove_member`
- Where: `public/index.php:1460–1660`

## Statuses & Service Types
- CRUD on statuses/service types
- Global audits: `status:create|update|delete`, `service_type:create|update|delete`
- Where: `public/index.php:550–640`

## Templates
- Upload, update metadata, delete
- Global audits: `template:create|update|delete`
- Where: `public/index.php:1220–1280`

## Checklists
- Global checklist: add/edit/delete/update
- Per-task checklist: add/edit/delete/update
- Global audits: `checklist:global_*`, `task_checklist:*`
- Where: `public/index.php:1140–1200`

## Email alert
Functional Tests

- CEO MFA email
  
  - Sign in as a CEO with MFA_CEO=1 .
  - The flow generates a 6‑digit code and calls send_mfa_code ; you should receive the email.
  - If debug is enabled, PHPMailer SMTP logs appear in the response or server logs.
- Dashboard digest email
  
  - Ensure you have at least one alert or due‑soon reminder:
    - Alerts: set a task’s alert.date within 3 days (CEO can set alerts in tasks).
    - Reminders: ensure task due date within the next 7 days and that you haven’t acknowledged today.
  - Visit /dashboard . The route:
    - Emails each alert to CEO/lead/team via send_due_task_email .
    - Sends a once‑per‑day digest to the logged‑in user via send_alert_digest .
  - Digest contains title_display , client name, and due date (prefers alert.date if present).