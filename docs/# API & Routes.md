# API & Routes

- Pattern: Front controller in `public/index.php` with clean URIs via `public/.htaccess`
- Methods: Mostly POST for mutations with CSRF protection
- Auth: Session-based, role checks via `require_roles` and `user_has_role`

## Auth
- `GET/POST /login`: login/register (first-time superadmin), CSRF validated
- `POST /logout`: terminates session

## Dashboard
- `GET /dashboard`: task summaries, due reminders triggered

## Tasks
- `GET /tasks`: task board
- `POST /tasks`: actions
  - `create_task` (CEO): fields include `client_id`, `services[]`, `lead_id`, `team_ids[]`, `status_id`, `task_year`, `due_date`, `initial_comment`
  - `update_status`: `task_id`, `status_id`
  - `update_alert`: `task_id`, `alert_date`, optional `comment`
  - `update_notice`: `task_id`, `notice_date`, `due_per_notice`, optional `attachment`
- `POST /reminders/ack`: acknowledges per-user reminders, `task_id`

## Clients
- `GET/POST /clients`: actions
  - `create_client`, `update_client`, `delete_client`
  - `update_services`: `client_id`, `services[]`

## Statuses
- `GET/POST /statuses`: `create_status`, `update_status`, `delete_status`

## Service Types
- `GET/POST /service-types`: `create_service`, `update_service`, `delete_service`

## Templates
- `GET/POST /templates`: `create_template` with file upload, `update_template`, `delete_template`

## Checklist
- `GET/POST /checklist`:
  - Global: `global_add_item`, `global_edit_item`, `global_delete_item`, `global_update`
  - Per-task: `add_item`, `edit_item`, `delete_item`, `update`

## Access Matrix & Hierarchy
- `GET/POST /access`: add asset, update user permissions, update matrix
- `GET/POST /hierarchy`: update or create hierarchy member

## Audits
- `GET /audits` (CEO): filterable audits, CSV export
  - Parameters: `domain`, `task`, `entity`, `actor`, `action`, `from`, `to`, `q`, `page`, `per`, `export=csv`