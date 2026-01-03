<?php
declare(strict_types=1);

function services_bootstrap(): void
{
    if (!isset($_SESSION['alert_log'])) {
        $_SESSION['alert_log'] = [];
    }
}

function get_services(): array
{
    $pdo = db();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $joinClause = $driver === 'pgsql'
        ? "JOIN client c ON CAST(c.C_ID AS TEXT) = CAST(t.C_ID AS TEXT)"
        : "JOIN client c ON CAST(c.C_ID AS CHAR) = CAST(t.C_ID AS CHAR)";

    $sql = <<<SQL
        SELECT 
               t.T_ID,
               t.T_NAME,
               t.STATUS_ID AS status_id,
               t.PROGRESS,
               t.ASSIGNMENT_TYPE,
               c.Name_of_Entity,
               COALESCE(t.ECD_DROP_DEAD, t.ECD_TENTATIVE, t.DUE_PER_NOTICE) AS due_date,
               e.Full_Name AS employee
        FROM task t
        $joinClause
        LEFT JOIN task_assignment ta ON ta.T_ID = t.T_ID AND ta.TASK_ROLE = 'Lead'
        LEFT JOIN employee e ON e.EMP_ID = ta.EMP_ID
        ORDER BY due_date
SQL;
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static function (array $row) {
        return [
            'id' => (int) ($row['T_ID'] ?? 0),
            'client' => $row['Name_of_Entity'] ?? '',
            'work_stream' => $row['T_NAME'] ?? '',
            'employee' => $row['employee'] ?? 'Unassigned',
            'status' => $row['status_id'] ?? null,
            'progress' => (int) ($row['PROGRESS'] ?? 0),
            'due_date' => $row['due_date'] ?? date('Y-m-d'),
            'priority' => $row['ASSIGNMENT_TYPE'] ?? 'Standard',
        ];
    }, $rows);
}

function add_service(array $payload): void
{
    $taskYear = isset($payload['task_year']) && $payload['task_year'] !== null && $payload['task_year'] !== ''
        ? (int) $payload['task_year']
        : (int) date('Y');

    $taskPayload = [
        'title' => $payload['work_stream'],
        'client_id' => (int) $payload['client_id'],
        'services' => [$payload['work_stream']],
        'lead_id' => (int) $payload['lead_id'],
        'team_ids' => [],
        'status_id' => (int) ($payload['status_id'] ?? 0),
        'due_date' => $payload['due_date'] ?? null,
        'progress' => (int) ($payload['progress'] ?? 0),
        'priority' => $payload['priority'] ?? 'Standard',
        'work_stream' => $payload['work_stream'],
        'task_year' => $taskYear,
    ];

    add_task($taskPayload);
}

function analyze_service(array $service): array
{
    $dueDate = new DateTimeImmutable($service['due_date']);
    $now = new DateTimeImmutable('today');
    $interval = $now->diff($dueDate);
    $daysRemaining = (int) $interval->format('%r%a');

    return [
        'days_remaining' => $daysRemaining,
        'due_soon' => $daysRemaining >= 0 && $daysRemaining <= 7,
        'overdue' => $daysRemaining < 0,
    ];
}
