# API Documentation

## Authentication

### POST /register
- Description: First-time registration endpoint (only available when no users exist)
- Parameters:
  - `full_name` (required)
  - `email` (required, valid email format)
  - `password` (required)
  - `password_confirm` (required, must match password)
- Response: Redirects to dashboard on success, or back to form with errors

### POST /login
- Description: User authentication
- Parameters:
  - `email` (required)
  - `password` (required)
- Response: Sets session and redirects to dashboard on success

## Clients

### GET /clients
- Description: List all clients
- Response: Returns array of client objects with details

### GET /clients/{id}
- Description: Get specific client details
- Parameters:
  - `id` (client ID)
- Response: Returns client object with full details

### POST /clients (bulk_upload_clients)
- Description: Bulk upload clients via CSV or XLSX
- Form fields:
  - `csrf_token` (required)
  - `action=bulk_upload_clients` (required)
  - `bulk_file` (required, `.csv` or `.xlsx`)
- Notes:
  - CSV goes through direct SQL inserts (`bulk_import_clients_csv`), XLSX uses per-row validation.
  - Flash success shows counts and first failure reason when present.

### POST /clients (delete_all_clients)
- Description: Delete all clients and service mappings (CEO only)
- Form fields:
  - `csrf_token` (required)
  - `action=delete_all_clients` (required)
- Response: Redirects with success or error flash; writes aggregated audit.

### GET /clients.json
- Description: Paginated client list for the UI
- Query params:
  - `page` (default: 1)
  - `limit` (default: 50, max: 200)
- Response: `{ items: [...], next_page: <int|null>, total: <int> }`

## Tasks

### GET /tasks
- Description: List all tasks
- Response: Returns array of task objects with status, due dates, and assignments

### POST /tasks
- Description: Create new task
- Parameters:
  - `title` (required)
  - `client_id` (required)
  - `services` (array)
  - `lead_id` (required)
  - `status` (default: 'Not Started')
  - `due_date`
  - `priority` (default: 'Standard')
- Response: Returns created task object

## Access Control

### GET /access
- Description: Get current access matrix
- Response: Returns object mapping assets to permission levels for each role

### POST /access
- Description: Update access matrix
- Parameters:
  - Object mapping assets to permission levels
- Response: Returns updated access matrix

## Utilities

### GET /services
- Description: List all services
- Response: Returns array of service objects

### GET /employees
- Description: List all employees
- Response: Returns array of employee objects

### POST /staff (bulk_upload_staff)
- Description: Bulk upload staff via CSV or XLSX
- Form fields:
  - `csrf_token` (required)
  - `action=bulk_upload_staff` (required)
  - `bulk_file` (required, `.csv` or `.xlsx`)
- Notes:
  - CSV performs direct SQL inserts (`bulk_import_staff_csv`); XLSX validated row-by-row.

### GET /staff.json
- Description: Paginated staff list for the UI
- Query params:
  - `page` (default: 1)
  - `limit` (default: 50, max: 200)
- Response: `{ items: [...], next_page: <int|null>, total: <int> }`

## Audits

### GET /audits
- Description: Audit trail viewer
- Query params:
  - `domain` supports `db` (default) and `file` to read file-backed audits
- Notes:
  - File-backed audits aggregate legacy `audit.log` and monthly `audit-YYYY-MM.log`.
