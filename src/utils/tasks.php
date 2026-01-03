<?php
declare(strict_types=1);

const TASK_COMMENT_LIMIT = 280;

const TASK_CHECKLIST_FIELDS = [
    ['column' => 'Financials_Reviewed_By_Partner', 'label' => 'Financials reviewed by partner'],
    ['column' => 'PDF_Sent_To_Client', 'label' => 'PDF sent to client'],
    ['column' => 'Signed_MRL_Recd', 'label' => 'Signed MRL received'],
    ['column' => 'Signed_Financials_Recd', 'label' => 'Signed financials received'],
    ['column' => 'Signed_Audit_Report_Sent_To_Client', 'label' => 'Signed audit report sent to client'],
    ['column' => 'Tax_Audit_Completed', 'label' => 'Tax audit completed'],
    ['column' => 'Three_CEB_Filed', 'label' => '3CEB filed'],
    ['column' => 'All_Docs_Shared_On_Shared_Folder', 'label' => 'All docs shared on shared folder'],
    ['column' => 'Appeal_Filed', 'label' => 'Appeal filed'],
];

function tasks_bootstrap(): void
{
    // Database backed, nothing to bootstrap.
}

function default_task_statuses(): array
{
    return [
        'Not Started',
        'On Track',
        'Awaiting Info',
        'Aligned with client',
        'At Risk',
        'Delayed',
        'Completed',
    ];
}

function task_title_with_year(string $title, ?int $year): string
{
    $trimmed = trim($title);
    if ($year !== null && $year > 0) {
        return $trimmed === '' ? (string) $year : $trimmed . ' - ' . $year;
    }

    return $trimmed;
}

function seed_default_status_details(PDO $pdo): void
{
    $insert = $pdo->prepare('INSERT INTO status_details (Description) VALUES (:description)');
    foreach (default_task_statuses() as $status) {
        try {
            $insert->execute(['description' => $status]);
        } catch (Throwable $exception) {
            // Ignore duplicates or constraint issues during seeding.
        }
    }
}

function get_status_details(): array
{
    try {
        $pdo = db();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $joinClause = $driver === 'pgsql'
            ? 'JOIN client c ON CAST(c.C_ID AS TEXT) = CAST(t.C_ID AS TEXT)'
            : 'JOIN client c ON CAST(c.C_ID AS CHAR) = CAST(t.C_ID AS CHAR)';
        $stmt = $pdo->query('SELECT Status_ID, Description FROM status_details ORDER BY Status_ID');
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            seed_default_status_details($pdo);
            $stmt = $pdo->query('SELECT Status_ID, Description FROM status_details ORDER BY Status_ID');
            $rows = $stmt->fetchAll();
        }

        return array_map(static function (array $row): array {
            $id = $row['status_id'] ?? $row['Status_ID'] ?? null;
            $description = $row['description'] ?? $row['Description'] ?? '';

            return [
                'id' => (int) $id,
                'description' => trim((string) $description),
            ];
        }, $rows);
    } catch (Throwable $exception) {
        error_log('Status fetch failed: ' . $exception->getMessage());
        return [];
    }
}

function task_has_status_column(): bool
{
    static $hasColumn;
    if ($hasColumn !== null) {
        return $hasColumn;
    }

    try {
        $pdo = db();
        $driver = db_driver();
        $schemaFunc = $driver === 'pgsql' ? 'current_schema()' : 'DATABASE()';
        $stmt = $pdo->query("SELECT 1 FROM information_schema.columns WHERE table_schema = $schemaFunc AND LOWER(table_name) = 'task' AND LOWER(column_name) = 'status' LIMIT 1");
        $hasColumn = (bool) $stmt->fetchColumn();
    } catch (Throwable $exception) {
        error_log('Status column detection failed: ' . $exception->getMessage());
        $hasColumn = false;
    }

    return $hasColumn;
}

function status_description_by_id(int $statusId): ?string
{
    if ($statusId <= 0) {
        return null;
    }

    foreach (get_status_details() as $status) {
        if ((int) $status['id'] === $statusId) {
            return $status['description'];
        }
    }

    return null;
}

function create_status_detail(string $description): int
{
    $label = trim($description);
    if ($label === '') {
        throw new InvalidArgumentException('Status description is required.');
    }

    $pdo = db();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'pgsql') {
        $stmt = $pdo->prepare('INSERT INTO status_details (Description) VALUES (:description) RETURNING Status_ID');
        $stmt->execute(['description' => $label]);
        return (int) $stmt->fetchColumn();
    }

    $stmt = $pdo->prepare('INSERT INTO status_details (Description) VALUES (:description)');
    $stmt->execute(['description' => $label]);
    return (int) $pdo->lastInsertId();
}

function update_status_detail(int $statusId, string $description): bool
{
    $label = trim($description);
    if ($statusId <= 0) {
        throw new InvalidArgumentException('Unknown status selection.');
    }
    if ($label === '') {
        throw new InvalidArgumentException('Status description is required.');
    }

    $pdo = db();
    $stmt = $pdo->prepare('UPDATE status_details SET Description = :description WHERE Status_ID = :id');
    $stmt->execute([
        'description' => $label,
        'id' => $statusId,
    ]);

    return $stmt->rowCount() > 0;
}

function delete_status_detail(int $statusId): bool
{
    if ($statusId <= 0) {
        return false;
    }

    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM status_details WHERE Status_ID = :id');
    $stmt->execute(['id' => $statusId]);

    return $stmt->rowCount() > 0;
}

function seed_tasks(): array
{
    static $seed;
    if ($seed !== null) {
        return $seed;
    }

    $clients = [];
    foreach (app_data('clients_seed', []) as $client) {
        $clients[(int) $client['id']] = $client['name'] ?? '';
    }

    $seed = array_map(static function (array $task) use ($clients) {
        $clientId = (int) ($task['client_id'] ?? 0);
        $clientName = $clients[$clientId] ?? ($task['client'] ?? '');
        $title = $task['title'] ?? '';
        $year = isset($task['year']) ? (int) $task['year'] : null;

        return [
            'id' => (int) ($task['id'] ?? 0),
            'title' => $title,
            'title_display' => task_title_with_year($title, $year),
            'client_id' => $clientId,
            'client' => $clientName,
            'client_name' => $clientName,
            'services' => array_values($task['services'] ?? []),
            'lead_id' => isset($task['lead_id']) ? (int) $task['lead_id'] : null,
            'team_ids' => array_values(array_map('intval', $task['team_ids'] ?? [])),
            'year' => $year,
            'status_id' => isset($task['status_id']) ? (int) $task['status_id'] : null,
            'status' => $task['status'] ?? 'Not Started',
            'due_date' => $task['due_date'] ?? date('Y-m-d'),
            'notice_date' => $task['notice_date'] ?? null,
            'ecd_tentative' => $task['ecd_tentative'] ?? null,
            'ecd_dropdead' => $task['ecd_dropdead'] ?? ($task['due_date'] ?? null),
            'due_per_notice' => $task['due_per_notice'] ?? null,
            'comments' => $task['comments'] ?? [],
            'attachments' => $task['attachments'] ?? [],
            'checklist' => $task['checklist'] ?? [],
            'progress' => (int) ($task['progress'] ?? 0),
        ];
    }, app_data('tasks_seed', []));

    return array_values(array_filter($seed, static fn (array $task) => $task['id'] > 0));
}

function task_statuses(): array
{
    $records = get_status_details();
    if (!empty($records)) {
        return array_values(array_unique(array_map(static fn (array $row): string => $row['description'], $records)));
    }

    return default_task_statuses();
}

function get_tasks(): array
{
    static $cache;
    static $cacheVersion;

    $version = $GLOBALS['TASK_CACHE_VERSION'] ?? null;
    if (is_float($version) || is_int($version)) {
        if ((microtime(true) - (float)$version) > 60) { $GLOBALS['TASK_CACHE_VERSION'] = microtime(true); $version = $GLOBALS['TASK_CACHE_VERSION']; }
    } else {
        $GLOBALS['TASK_CACHE_VERSION'] = microtime(true);
        $version = $GLOBALS['TASK_CACHE_VERSION'];
    }
    if ($cache !== null && $cacheVersion === $version) {
        $tasks = $cache;
    } else {

    try {
        $pdo = db();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $joinClause = $driver === 'pgsql'
            ? 'JOIN client c ON CAST(c.C_ID AS TEXT) = CAST(t.C_ID AS TEXT)'
            : 'JOIN client c ON CAST(c.C_ID AS CHAR) = CAST(t.C_ID AS CHAR)';
        $statusSelect = task_has_status_column() ? 't.STATUS AS status_fallback, ' : '';
        $typeDescSelect = function_exists('task_has_type_description_column') && task_has_type_description_column() ? 't.T_TYPE_DESCRIPTION AS type_desc, ' : '';
        $codeSelect = task_has_code_column() ? 't.task_code AS task_code, ' : '';
        $sql = 'SELECT 
                       t.T_ID AS t_id,
                       ' . $codeSelect . '
                       t.T_NAME AS t_name,
                       t.T_TYPE AS t_type,
                       t.C_ID AS c_id,
                       ' . $statusSelect . 't.Status_ID AS status_id,
                       t.TASK_YEAR AS task_year,
                       ' . $typeDescSelect . 'sd.Description AS status_label,
                       t.ASSIGNMENT_TYPE AS assignment_type,
                       t.APPEALS_TYPE AS appeals_type,
                       t.ECD_TENTATIVE AS ecd_tentative,
                       t.ECD_DROP_DEAD AS ecd_drop_dead,
                       t.DUE_PER_NOTICE AS due_per_notice,
                       t.NOTICE_DATE AS notice_date,
                       t.PROGRESS AS progress,
                       c.Name_of_Entity AS client_name
                FROM task t
                ' . $joinClause . '
                LEFT JOIN status_details sd ON sd.Status_ID = t.Status_ID
                ORDER BY COALESCE(t.ECD_DROP_DEAD, t.ECD_TENTATIVE, t.DUE_PER_NOTICE, t.CREATED_DATE)';
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $tasks = [];
        foreach ($rows as $row) {
            $taskId = isset($row['t_id']) ? (int) $row['t_id'] : 0;
            $dueDate = $row['ecd_drop_dead'] ?? $row['ecd_tentative'] ?? $row['due_per_notice'] ?? date('Y-m-d');
            $assignments = fetch_task_assignments($taskId);
            $leadId = null;
            $teamIds = [];
            foreach ($assignments as $assignment) {
                if (strtolower($assignment['task_role']) === 'lead') {
                    $leadId = (int) $assignment['emp_id'];
                } else {
                    $teamIds[] = (int) $assignment['emp_id'];
                }
            }

            $taskYear = isset($row['task_year']) && $row['task_year'] !== null ? (int) $row['task_year'] : null;

            $task = [
                'id' => $taskId,
                'title' => $row['t_name'] ?? '',
                'title_display' => task_title_with_year(($row['t_name'] ?? ''), $taskYear),
                'client_id' => (string) ($row['c_id'] ?? ''),
                'client' => $row['client_name'] ?? '',
                'client_name' => $row['client_name'] ?? '',
                'services' => array_values(array_filter(array_map('trim', explode(',', (string) ($row['t_type'] ?? ''))))),
                'work_stream' => ($row['type_desc'] ?? null),
                'lead_id' => $leadId,
                'team_ids' => $teamIds,
                'status_id' => isset($row['status_id']) ? (int) $row['status_id'] : null,
                'status' => ($row['status_label'] ?? $row['status_fallback'] ?? 'Unknown'),
                'year' => $taskYear,
                'due_date' => $dueDate,
                'notice_date' => $row['notice_date'] ?? null,
                'ecd_tentative' => $row['ecd_tentative'] ?? null,
                'ecd_dropdead' => $row['ecd_drop_dead'] ?? null,
                'due_per_notice' => $row['due_per_notice'] ?? null,
                'comments' => fetch_task_comments($taskId),
                'attachments' => fetch_task_attachments($taskId),
                'checklist' => fetch_task_checklist($taskId),
                'progress' => (int) ($row['progress'] ?? 0),
            ];

            $config = fetch_task_config($taskId);
            if (is_array($config)) {
                $task['gst'] = $config['gst'] ?? null;
                $task['alert'] = $config['alert'] ?? null;
            }
            $tasks[] = $task;
        }
    } catch (Throwable $exception) {
        error_log('Task fetch failed: ' . $exception->getMessage());
        $tasks = [];
    }

    $cache = $tasks;
    $cacheVersion = $version;
}

if (!function_exists('get_tasks_page')) {
    function get_tasks_page(int $limit = 50, int $offset = 0): array
    {
        $user = current_user();
        $role = strtolower($user['role'] ?? 'employee');
        $uid = (int) ($user['id'] ?? 0);

        try {
            $pdo = db();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $joinClause = $driver === 'pgsql'
                ? 'JOIN client c ON CAST(c.C_ID AS TEXT) = CAST(t.C_ID AS TEXT)'
                : 'JOIN client c ON CAST(c.C_ID AS CHAR) = CAST(t.C_ID AS CHAR)';
            $statusSelect = task_has_status_column() ? 't.STATUS AS status_fallback, ' : '';
            $typeDescSelect = function_exists('task_has_type_description_column') && task_has_type_description_column() ? 't.T_TYPE_DESCRIPTION AS type_desc, ' : '';
            $codeSelect = task_has_code_column() ? 't.task_code AS task_code, ' : '';

            $filter = '';
            $params = [];
            if (!($role === 'ceo' || $role === 'superadmin')) {
                if ($role === 'lead') {
                    $filter = 'WHERE EXISTS (SELECT 1 FROM task_assignment ta WHERE ta.T_ID = t.T_ID AND ta.EMP_ID = :uid AND LOWER(ta.TASK_ROLE) = \'lead\')';
                } else {
                    $filter = 'WHERE EXISTS (SELECT 1 FROM task_assignment ta WHERE ta.T_ID = t.T_ID AND ta.EMP_ID = :uid)';
                }
                $params['uid'] = $uid;
            }

            $sql = 'SELECT 
                           t.T_ID AS t_id,
                           ' . $codeSelect . '
                           t.T_NAME AS t_name,
                           t.T_TYPE AS t_type,
                           t.C_ID AS c_id,
                           ' . $statusSelect . 't.Status_ID AS status_id,
                           t.TASK_YEAR AS task_year,
                           ' . $typeDescSelect . 'sd.Description AS status_label,
                           t.ASSIGNMENT_TYPE AS assignment_type,
                           t.APPEALS_TYPE AS appeals_type,
                           t.ECD_TENTATIVE AS ecd_tentative,
                           t.ECD_DROP_DEAD AS ecd_drop_dead,
                           t.DUE_PER_NOTICE AS due_per_notice,
                           t.NOTICE_DATE AS notice_date,
                           t.PROGRESS AS progress,
                           c.Name_of_Entity AS client_name
                    FROM task t
                    ' . $joinClause . '
                    LEFT JOIN status_details sd ON sd.Status_ID = t.Status_ID 
                    ' . $filter . '
                    ORDER BY COALESCE(t.ECD_DROP_DEAD, t.ECD_TENTATIVE, t.DUE_PER_NOTICE, t.CREATED_DATE)
                    LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $tasks = [];
            $taskIds = [];
            foreach ($rows as $row) {
                $taskIds[] = (int) ($row['t_id'] ?? 0);
            }

            $leadMap = [];
            $teamMap = [];
            if ($taskIds) {
                $ph = implode(',', array_fill(0, count($taskIds), '?'));
                $stmtA = $pdo->prepare('SELECT T_ID, EMP_ID, TASK_ROLE FROM task_assignment WHERE T_ID IN (' . $ph . ')');
                $stmtA->execute($taskIds);
                foreach ($stmtA->fetchAll(PDO::FETCH_ASSOC) as $a) {
                    $tid = (int) ($a['T_ID'] ?? 0);
                    $emp = (int) ($a['EMP_ID'] ?? 0);
                    $roleA = strtolower((string) ($a['TASK_ROLE'] ?? ''));
                    if ($roleA === 'lead') { $leadMap[$tid] = $emp; } else { $teamMap[$tid] = array_merge($teamMap[$tid] ?? [], [$emp]); }
                }
            }

            foreach ($rows as $row) {
                $taskId = isset($row['t_id']) ? (int) $row['t_id'] : 0;
                $dueDate = $row['ecd_drop_dead'] ?? $row['ecd_tentative'] ?? $row['due_per_notice'] ?? date('Y-m-d');
                $taskYear = isset($row['task_year']) && $row['task_year'] !== null ? (int) $row['task_year'] : null;

                $tasks[] = [
                    'id' => $taskId,
                    'title' => $row['t_name'] ?? '',
                    'title_display' => task_title_with_year(($row['t_name'] ?? ''), $taskYear),
                    'client_id' => (string) ($row['c_id'] ?? ''),
                    'client' => $row['client_name'] ?? '',
                    'client_name' => $row['client_name'] ?? '',
                    'services' => array_values(array_filter(array_map('trim', explode(',', (string) ($row['t_type'] ?? ''))))),
                    'work_stream' => ($row['type_desc'] ?? null),
                    'lead_id' => $leadMap[$taskId] ?? null,
                    'team_ids' => array_map('intval', $teamMap[$taskId] ?? []),
                    'status_id' => isset($row['status_id']) ? (int) $row['status_id'] : null,
                    'status' => ($row['status_label'] ?? $row['status_fallback'] ?? 'Unknown'),
                    'year' => $taskYear,
                    'due_date' => $dueDate,
                    'notice_date' => $row['notice_date'] ?? null,
                    'ecd_tentative' => $row['ecd_tentative'] ?? null,
                    'ecd_dropdead' => $row['ecd_drop_dead'] ?? null,
                    'due_per_notice' => $row['due_per_notice'] ?? null,
                    'progress' => (int) ($row['progress'] ?? 0),
                ];
            }

            return $tasks;
        } catch (Throwable $exception) {
            error_log('Task page fetch failed: ' . $exception->getMessage());
            return [];
        }
    }
}

    $user = current_user();
    $role = strtolower($user['role'] ?? 'employee');
    $uid = (int) ($user['id'] ?? 0);
    if ($role === 'ceo' || $role === 'superadmin') {
        
    } elseif ($role === 'lead') {
        $tasks = array_values(array_filter($tasks, static fn ($t) => (int) ($t['lead_id'] ?? 0) === $uid));
    } else {
        $tasks = array_values(array_filter($tasks, static fn ($t) => in_array($uid, array_map('intval', $t['team_ids'] ?? []), true)));
    }

    return $tasks;
}

function fetch_task_assignments(int $taskId): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT 
                                TA_ID AS ta_id,
                                T_ID AS t_id,
                                EMP_ID AS emp_id,
                                TASK_ROLE AS task_role,
                                ASSIGN_DATE AS assign_date
                            FROM task_assignment
                            WHERE T_ID = :task');
    $stmt->execute(['task' => $taskId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static function (array $row) {
        return [
            'id' => (int) ($row['ta_id'] ?? 0),
            'task_id' => (int) ($row['t_id'] ?? 0),
            'emp_id' => (int) ($row['emp_id'] ?? 0),
            'task_role' => $row['task_role'] ?? '',
            'assign_date' => $row['assign_date'] ?? null,
        ];
    }, $rows);
}

function task_has_code_column(): bool
{
    static $has;
    if ($has !== null) { return $has; }
    try {
        $pdo = db();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $schemaFunc = $driver === 'pgsql' ? 'current_schema()' : 'DATABASE()';
        $stmt = $pdo->query("SELECT 1 FROM information_schema.columns WHERE table_schema = $schemaFunc AND LOWER(table_name)='task' AND LOWER(column_name)='task_code' LIMIT 1");
        $has = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Task_Code column detection failed: ' . $e->getMessage());
        $has = false;
    }
    return $has;
}

function ensure_task_code_column(): void
{
    try {
        $pdo = db();
        $exists = task_has_code_column();
        if (!$exists) { $pdo->exec("ALTER TABLE task ADD COLUMN Task_Code VARCHAR(16) NULL"); }
    } catch (Throwable $e) { error_log('Failed to ensure Task_Code: ' . $e->getMessage()); }
}

function task_code_padding(): int
{
    $pdo = db();
    $stmt = $pdo->query("SELECT COALESCE(MAX(LENGTH(SUBSTRING(Task_Code,2))),0) FROM task WHERE Task_Code LIKE 'T%'");
    $len = (int) ($stmt->fetchColumn() ?: 0);
    return max(3, $len);
}

function generate_next_task_code(): string
{
    $pdo = db();
    $len = task_code_padding();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $castType = $driver === 'pgsql' ? 'INTEGER' : 'UNSIGNED';
    $stmt = $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(Task_Code,2) AS $castType)),0) FROM task WHERE Task_Code LIKE 'T%'");
    $max = (int) ($stmt->fetchColumn() ?: 0);
    $num = $max + 1;
    return 'T' . str_pad((string) $num, $len, '0', STR_PAD_LEFT);
}

function backfill_missing_task_codes(): void
{
    $pdo = db();
    $len = task_code_padding();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $castType = $driver === 'pgsql' ? 'INTEGER' : 'UNSIGNED';
    $seqStmt = $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(Task_Code,2) AS $castType)),0) FROM task WHERE Task_Code LIKE 'T%'");
    $seq = (int) ($seqStmt->fetchColumn() ?: 0);
    $ids = $pdo->query("SELECT T_ID FROM task WHERE Task_Code IS NULL ORDER BY T_ID")->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) { return; }
    $update = $pdo->prepare('UPDATE task SET Task_Code = :code WHERE T_ID = :id');
    foreach ($ids as $id) {
        $seq++;
        $code = 'T' . str_pad((string) $seq, $len, '0', STR_PAD_LEFT);
        try { $update->execute(['code' => $code, 'id' => $id]); } catch (Throwable $e) {}
    }
}

function add_task(array $payload): int
{
    $statusId = (int) ($payload['status_id'] ?? 0);
    $statusLabel = status_description_by_id($statusId);
    if ($statusLabel === null) {
        throw new InvalidArgumentException('Invalid status selected.');
    }
    $taskYear = $payload['task_year'] ?? null;
    if ($taskYear !== null) {
        $taskYear = (int) $taskYear;
    }

    $pdo = db();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $useManualId = $driver !== 'pgsql';
    try {
        if ($driver === 'pgsql') {
            $schema = 'current_schema()';
            $colType = $pdo->query("SELECT data_type FROM information_schema.columns WHERE table_schema = $schema AND LOWER(table_name)='task' AND LOWER(column_name)='c_id' LIMIT 1")->fetchColumn();
            $isText = is_string($colType) && (stripos((string) $colType, 'character') !== false || stripos((string) $colType, 'text') !== false);
            if (!$isText) {
                $q = $pdo->query("SELECT tc.constraint_name FROM information_schema.table_constraints tc JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema WHERE tc.table_schema = $schema AND tc.table_name = 'task' AND tc.constraint_type = 'FOREIGN KEY' AND kcu.column_name = 'C_ID' LIMIT 5");
                $fks = $q->fetchAll(PDO::FETCH_COLUMN);
                foreach ($fks as $fk) { $pdo->exec("ALTER TABLE task DROP CONSTRAINT " . $fk); }
                $pdo->exec("ALTER TABLE task ALTER COLUMN C_ID TYPE VARCHAR(120) USING C_ID::text");
                $pdo->exec("ALTER TABLE task ADD CONSTRAINT fk_task_client FOREIGN KEY (C_ID) REFERENCES client(C_ID)");
            }
        } elseif ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE task MODIFY COLUMN C_ID VARCHAR(120)");
        }
    } catch (Throwable $e) {
        error_log('Ensure task C_ID text failed: ' . $e->getMessage());
    }
    $taskIdManual = null;
    if ($useManualId) {
        try {
            $stmtMax = $pdo->query("SELECT COALESCE(MAX(CAST(T_ID AS UNSIGNED)),0) FROM task");
            $taskIdManual = ((int) ($stmtMax->fetchColumn() ?: 0)) + 1;
        } catch (Throwable $e) {
            $taskIdManual = (int) (microtime(true) * 1000);
        }
    }
    $hasStatusColumn = task_has_status_column();
    $columns = [
        'T_NAME',
        'T_TYPE',
        'T_TYPE_DESCRIPTION',
        'C_ID',
    ];
    $placeholders = [
        ':name',
        ':type',
        ':type_desc',
        ':client',
    ];

    if ($useManualId) {
        array_unshift($columns, 'T_ID');
        array_unshift($placeholders, ':id');
    }

    if ($hasStatusColumn) {
        $columns[] = 'STATUS';
        $placeholders[] = ':status';
    }

    $columns[] = 'Status_ID';
    $placeholders[] = ':status_id';

    $columns[] = 'TASK_YEAR';
    $placeholders[] = ':task_year';

    $columns = array_merge($columns, [
        'ASSIGNMENT_TYPE',
        'APPEALS_TYPE',
        'ECD_TENTATIVE',
        'ECD_DROP_DEAD',
        'DUE_PER_NOTICE',
        'NOTICE_DATE',
        'PROGRESS',
        'CREATED_DATE',
        'LAST_UPDATED',
    ]);

    $placeholders = array_merge($placeholders, [
        ':assignment_type',
        ':appeals_type',
        ':ecd_tentative',
        ':ecd_dropdead',
        ':due_per_notice',
        ':notice_date',
        ':progress',
        'NOW()',
        'NOW()',
    ]);

    $sql = 'INSERT INTO task (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $services = array_values(array_filter($payload['services'] ?? []));
    $type = implode(', ', $services);
    $params = [
        'name' => $payload['title'],
        'type' => $type,
        'type_desc' => $payload['work_stream'] ?? null,
        'client' => $payload['client_id'],
        'status_id' => $statusId,
        'task_year' => $taskYear,
        'assignment_type' => $payload['priority'] ?? 'Standard',
        'appeals_type' => $payload['appeals_type'] ?? null,
        'ecd_tentative' => $payload['ecd_tentative'] ?? null,
        'ecd_dropdead' => $payload['due_date'] ?? null,
        'due_per_notice' => $payload['due_per_notice'] ?? null,
        'notice_date' => $payload['notice_date'] ?? null,
        'progress' => $payload['progress'] ?? 0,
    ];
    if ($useManualId && $taskIdManual !== null) { $params['id'] = $taskIdManual; }

    if ($hasStatusColumn) {
        $params['status'] = $statusLabel;
    }

    $stmt->execute($params);

    $taskId = $useManualId ? (int) $taskIdManual : (int) $pdo->lastInsertId();

    ensure_task_code_column();
    backfill_missing_task_codes();
    $code = generate_next_task_code();
    try { $pdo->prepare('UPDATE task SET Task_Code = :code WHERE T_ID = :id')->execute(['code' => $code, 'id' => $taskId]); } catch (Throwable $e) { error_log('Failed to set Task_Code: ' . $e->getMessage()); }

    if (!empty($payload['lead_id'])) {
        insert_task_assignment($taskId, (int) $payload['lead_id'], 'Lead');
    }
    foreach ($payload['team_ids'] ?? [] as $teamId) {
        insert_task_assignment($taskId, (int) $teamId, 'Team');
    }

    ensure_detailed_status_row($taskId);
    invalidate_task_cache();

    return $taskId;
}

function update_task(array $payload): void
{
    $taskId = (int) ($payload['id'] ?? 0);
    if ($taskId <= 0) {
        throw new InvalidArgumentException('Unknown task.');
    }

    $statusId = (int) ($payload['status_id'] ?? 0);
    $statusLabel = status_description_by_id($statusId);
    if ($statusLabel === null) {
        throw new InvalidArgumentException('Invalid status selected.');
    }

    $taskYear = $payload['task_year'] ?? null;
    if ($taskYear !== null) {
        $taskYear = (int) $taskYear;
    }

    $pdo = db();
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            $schema = 'current_schema()';
            $colType = $pdo->query("SELECT data_type FROM information_schema.columns WHERE table_schema = $schema AND LOWER(table_name)='task' AND LOWER(column_name)='c_id' LIMIT 1")->fetchColumn();
            $isText = is_string($colType) && (stripos((string) $colType, 'character') !== false || stripos((string) $colType, 'text') !== false);
            if (!$isText) {
                $q = $pdo->query("SELECT tc.constraint_name FROM information_schema.table_constraints tc JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema WHERE tc.table_schema = $schema AND tc.table_name = 'task' AND tc.constraint_type = 'FOREIGN KEY' AND kcu.column_name = 'C_ID' LIMIT 5");
                $fks = $q->fetchAll(PDO::FETCH_COLUMN);
                foreach ($fks as $fk) { $pdo->exec("ALTER TABLE task DROP CONSTRAINT " . $fk); }
                $pdo->exec("ALTER TABLE task ALTER COLUMN C_ID TYPE VARCHAR(120) USING C_ID::text");
                $pdo->exec("ALTER TABLE task ADD CONSTRAINT fk_task_client FOREIGN KEY (C_ID) REFERENCES client(C_ID)");
            }
        } elseif ($driver === 'mysql') {
            $pdo->exec("ALTER TABLE task MODIFY COLUMN C_ID VARCHAR(120)");
        }
    } catch (Throwable $e) {
        error_log('Ensure task C_ID text failed: ' . $e->getMessage());
    }
    $hasStatusColumn = task_has_status_column();
    $services = array_values(array_filter($payload['services'] ?? []));
    $type = implode(', ', $services);
    $params = [
        'task' => $taskId,
        'name' => $payload['title'],
        'type' => $type,
        'type_desc' => $payload['work_stream'] ?? null,
        'client' => $payload['client_id'],
        'status_id' => $statusId,
        'task_year' => $taskYear,
        'due_date' => $payload['due_date'] ?? null,
        'due_per_notice' => $payload['due_per_notice'] ?? null,
        'notice_date' => $payload['notice_date'] ?? null,
    ];
    if ($hasStatusColumn) {
        $params['status'] = $statusLabel;
    }

    $columns = [
        'T_NAME = :name',
        'T_TYPE = :type',
        'T_TYPE_DESCRIPTION = :type_desc',
        'C_ID = :client',
        'Status_ID = :status_id',
        'TASK_YEAR = :task_year',
        'ECD_DROP_DEAD = :due_date',
        'DUE_PER_NOTICE = :due_per_notice',
        'NOTICE_DATE = :notice_date',
    ];
    if ($hasStatusColumn) {
        $columns[] = 'STATUS = :status';
    }
    $columns[] = 'LAST_UPDATED = NOW()';

    $sql = 'UPDATE task SET ' . implode(', ', $columns) . ' WHERE T_ID = :task';

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $deleteAssignments = $pdo->prepare('DELETE FROM task_assignment WHERE T_ID = :task');
        $deleteAssignments->execute(['task' => $taskId]);

        if (!empty($payload['lead_id'])) {
            insert_task_assignment($taskId, (int) $payload['lead_id'], 'Lead');
        }
        foreach ($payload['team_ids'] ?? [] as $teamId) {
            insert_task_assignment($taskId, (int) $teamId, 'Team');
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    ensure_detailed_status_row($taskId);
    invalidate_task_cache();
}

function insert_task_assignment(int $taskId, int $employeeId, string $role): void
{
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO task_assignment (T_ID, EMP_ID, TASK_ROLE, ASSIGN_DATE)
                           VALUES (:task, :emp, :role, NOW())');
    try {
        $stmt->execute([
            'task' => $taskId,
            'emp' => $employeeId,
            'role' => $role,
        ]);
    } catch (Throwable $e) {
        error_log('[TASKS][assignment] ' . $e->getMessage() . ' code=' . $e->getCode());
        throw $e;
    }
}

function update_task_status(int $taskId, int $statusId): void
{
    $statusLabel = status_description_by_id($statusId);
    if ($statusLabel === null) {
        throw new InvalidArgumentException('Invalid status selected.');
    }

    $pdo = db();
    $hasStatusColumn = task_has_status_column();
    $sql = 'UPDATE task SET Status_ID = :status_id';
    $params = [
        'status_id' => $statusId,
        'task' => $taskId,
    ];

    if ($hasStatusColumn) {
        $sql .= ', STATUS = :status';
        $params['status'] = $statusLabel;
    }

    $sql .= ', LAST_UPDATED = NOW() WHERE T_ID = :task';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    invalidate_task_cache();
}

function delete_task(int $taskId): void
{
    if ($taskId <= 0) {
        throw new InvalidArgumentException('Unknown task.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $tables = [
            'task_assignment',
            'task_comments',
            'task_attachments',
            'detailed_status',
            'task_reminder_ack',
            'remainder',
            'notice',
        ];
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE T_ID = :task");
            $stmt->execute(['task' => $taskId]);
        }

        $stmt = $pdo->prepare('DELETE FROM task WHERE T_ID = :task');
        $stmt->execute(['task' => $taskId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    invalidate_task_cache();
}

function mark_task_notice(int $taskId, ?string $noticeDate = null): void
{
    if ($taskId <= 0) {
        throw new InvalidArgumentException('Unknown task.');
    }

    $date = $noticeDate ?: date('Y-m-d');

    $pdo = db();
    $stmt = $pdo->prepare('UPDATE task SET NOTICE_DATE = :notice, LAST_UPDATED = NOW() WHERE T_ID = :task');
    $stmt->execute([
        'notice' => $date,
        'task' => $taskId,
    ]);
    invalidate_task_cache();
}

function add_task_comment(int $taskId, string $comment, array $author): void
{
    $trimmed = trim($comment);
    if ($trimmed === '') {
        throw new InvalidArgumentException('Comment cannot be empty');
    }

    if (mb_strlen($trimmed) > TASK_COMMENT_LIMIT) {
        throw new InvalidArgumentException('Comment exceeds limit');
    }

    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO task_comments (T_ID, Author_Name, Author_Role, Body, Created_At)
                           VALUES (:task, :name, :role, :body, NOW())');
    $stmt->execute([
        'task' => $taskId,
        'name' => $author['name'] ?? 'System',
        'role' => role_label($author['role'] ?? 'system'),
        'body' => $trimmed,
    ]);
    invalidate_task_cache();
}

function fetch_task_comments(int $taskId): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT 
                                Comment_ID AS comment_id,
                                Author_Name AS author_name,
                                Author_Role AS author_role,
                                Body AS body,
                                Created_At AS created_at
                            FROM task_comments
                            WHERE T_ID = :task
                            ORDER BY Created_At DESC');
    $stmt->execute(['task' => $taskId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static function (array $row) {
        return [
            'id' => (int) ($row['comment_id'] ?? $row['Comment_ID'] ?? 0),
            'author' => $row['author_name'] ?? $row['Author_Name'] ?? '',
            'role' => $row['author_role'] ?? $row['Author_Role'] ?? '',
            'body' => $row['body'] ?? $row['Body'] ?? '',
            'created_at' => $row['created_at'] ?? $row['Created_At'] ?? '',
        ];
    }, $rows);
}

function add_task_attachment(int $taskId, array $attachment): void
{
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO task_attachments (T_ID, File_Path, Original_Name, Uploaded_By, Uploaded_At, File_Size)
                           VALUES (:task, :path, :name, :user, :uploaded, :size)');
    $stmt->execute([
        'task' => $taskId,
        'path' => $attachment['path'],
        'name' => $attachment['name'],
        'user' => $attachment['uploaded_by'] ?? 'System',
        'uploaded' => $attachment['uploaded_at'] ?? date('c'),
        'size' => $attachment['size'] ?? 0,
    ]);
    invalidate_task_cache();
}

function fetch_task_attachments(int $taskId): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT Attachment_ID, File_Path, Original_Name, Uploaded_By, Uploaded_At, File_Size
                            FROM task_attachments
                            WHERE T_ID = :task');
    $stmt->execute(['task' => $taskId]);
    return array_map(static function (array $row) {
        return [
            'id' => (int) $row['Attachment_ID'],
            'path' => $row['File_Path'],
            'name' => $row['Original_Name'],
            'uploaded_by' => $row['Uploaded_By'],
            'uploaded_at' => $row['Uploaded_At'],
            'size' => (int) $row['File_Size'],
        ];
    }, $stmt->fetchAll());
}

function find_task_attachment(string $attachmentId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT ta.Attachment_ID, ta.File_Path, ta.Original_Name, ta.Uploaded_By, ta.Uploaded_At, ta.File_Size, t.T_ID, t.T_NAME
                            FROM task_attachments ta
                            JOIN task t ON t.T_ID = ta.T_ID
                            WHERE ta.Attachment_ID = :id');
    $stmt->execute(['id' => $attachmentId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    return [
        'task' => [
            'id' => (int) $row['T_ID'],
            'title' => $row['T_NAME'],
        ],
        'attachment' => [
            'id' => (int) $row['Attachment_ID'],
            'path' => $row['File_Path'],
            'name' => $row['Original_Name'],
            'uploaded_by' => $row['Uploaded_By'],
            'uploaded_at' => $row['Uploaded_At'],
            'size' => (int) $row['File_Size'],
        ],
    ];
}

function update_task_checklist(int $taskId, array $completedKeys): void
{
    $pdo = db();
    ensure_detailed_status_row($taskId);

    $sets = [];
    $params = ['task' => $taskId];
    $fixedCols = array_column(TASK_CHECKLIST_FIELDS, 'column');
    foreach (TASK_CHECKLIST_FIELDS as $field) {
        $col = $field['column'];
        $sets[] = "$col = :$col";
        $params[$col] = in_array($col, $completedKeys, true) ? 'Yes' : 'No';
    }
    if (!empty($sets)) {
        $sql = 'UPDATE detailed_status SET ' . implode(', ', $sets) . ', Updated_At = NOW() WHERE T_ID = :task';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    $config = fetch_task_config($taskId);
    $custom = array_values($config['custom'] ?? []);
    $updated = [];
    foreach ($custom as $item) {
        $key = 'custom:' . ($item['id'] ?? '');
        $item['done'] = in_array($key, $completedKeys, true);
        $updated[] = $item;
    }
    $config['custom'] = $updated;
    save_task_config($taskId, $config);
    invalidate_task_cache();
}

function fetch_task_checklist(int $taskId): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM detailed_status WHERE T_ID = :task');
    $stmt->execute(['task' => $taskId]);
    $row = $stmt->fetch();
    if (!$row) {
        $row = ensure_detailed_status_row($taskId);
    }

    $config = fetch_task_config($taskId);
    $labels = $config['labels'] ?? [];
    $hidden = array_map('strval', $config['hidden'] ?? []);

    $items = [];
    foreach (TASK_CHECKLIST_FIELDS as $field) {
        $col = $field['column'];
        if (in_array($col, $hidden, true)) { continue; }
        $items[] = [
            'key' => $col,
            'type' => 'fixed',
            'item' => ($labels[$col] ?? $field['label']),
            'done' => ($row[$col] ?? 'No') === 'Yes',
        ];
    }

    foreach (array_values($config['custom'] ?? []) as $ci) {
        $items[] = [
            'key' => 'custom:' . $ci['id'],
            'type' => 'custom',
            'item' => $ci['label'],
            'done' => (bool) ($ci['done'] ?? false),
        ];
    }

    return $items;
}

function task_has_type_description_column(): bool
{
    static $hasColumn;
    if ($hasColumn !== null) { return $hasColumn; }
    try {
        $pdo = db();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $schemaFunc = $driver === 'pgsql' ? 'current_schema()' : 'DATABASE()';
        $stmt = $pdo->query("SELECT 1 FROM information_schema.columns WHERE table_schema = $schemaFunc AND LOWER(table_name) = 'task' AND LOWER(column_name) = 't_type_description' LIMIT 1");
        $hasColumn = (bool) $stmt->fetchColumn();
    } catch (Throwable $exception) {
        error_log('Type description column detection failed: ' . $exception->getMessage());
        $hasColumn = false;
    }
    return $hasColumn;
}

function ensure_task_type_description_column(): void
{
    if (task_has_type_description_column()) { return; }
    try {
        $pdo = db();
        $pdo->exec('ALTER TABLE task ADD COLUMN T_TYPE_DESCRIPTION TEXT NULL');
        $GLOBALS['TASK_CACHE_VERSION'] = microtime(true);
        // Reset detection cache
        $ref = new ReflectionFunction('task_has_type_description_column');
    } catch (Throwable $exception) {
        error_log('Failed to add T_TYPE_DESCRIPTION: ' . $exception->getMessage());
    }
}

function task_has_gst_month_columns(): bool
{
    static $has;
    if ($has !== null) { return $has; }
    try {
        $pdo = db();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $schemaFunc = $driver === 'pgsql' ? 'current_schema()' : 'DATABASE()';
        $q = "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = $schemaFunc AND LOWER(table_name) = 'task' AND LOWER(column_name) IN ('gst_return_month','gst_send_month')";
        $stmt = $pdo->query($q);
        $has = ((int) ($stmt->fetchColumn() ?: 0)) >= 2;
    } catch (Throwable $e) { $has = false; }
    return $has;
}

function ensure_task_gst_month_columns(): void
{
    try {
        $pdo = db();
        if (task_has_gst_month_columns()) { return; }
        $pdo->exec('ALTER TABLE task ADD COLUMN GST_Return_Month CHAR(7) NULL');
        $pdo->exec('ALTER TABLE task ADD COLUMN GST_Send_Month CHAR(7) NULL');
    } catch (Throwable $e) { error_log('Failed to add GST month columns: ' . $e->getMessage()); }
}

function fetch_task_config(int $taskId): array
{
    if (!task_has_type_description_column()) { return []; }
    $pdo = db();
    ensure_task_gst_month_columns();
    $stmt = $pdo->prepare('SELECT T_TYPE_DESCRIPTION, GST_Return_Month, GST_Send_Month FROM task WHERE T_ID = :task');
    $stmt->execute(['task' => $taskId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $val = (string) ($row['T_TYPE_DESCRIPTION'] ?? '');
    $trim = trim($val);
    $data = [];
    if ($trim !== '' && $trim[0] === '{') {
        $d = json_decode($trim, true);
        if (is_array($d)) { $data = $d; }
    }
    $ret = $row['GST_Return_Month'] ?? null;
    $send = $row['GST_Send_Month'] ?? null;
    if ($ret || $send) {
        $gst = $data['gst'] ?? [];
        if ($ret) { $gst['return_month'] = $ret; }
        if ($send) { $gst['send_month'] = $send; }
        $data['gst'] = $gst;
    }
    return is_array($data) ? $data : [];
}

function save_task_config(int $taskId, array $config): void
{
    ensure_task_type_description_column();
    if (!task_has_type_description_column()) { return; }
    ensure_task_gst_month_columns();
    $pdo = db();
    $ret = (string) ($config['gst']['return_month'] ?? '');
    $send = (string) ($config['gst']['send_month'] ?? '');
    $stmt = $pdo->prepare('UPDATE task SET T_TYPE_DESCRIPTION = :desc, GST_Return_Month = :ret, GST_Send_Month = :send, LAST_UPDATED = NOW() WHERE T_ID = :task');
    $stmt->execute(['desc' => json_encode($config), 'ret' => ($ret !== '' ? $ret : null), 'send' => ($send !== '' ? $send : null), 'task' => $taskId]);
}

function add_task_checklist_item(int $taskId, string $label): void
{
    $label = trim($label);
    if ($label === '') { throw new InvalidArgumentException('Label required'); }
    $config = fetch_task_config($taskId);
    $list = array_values($config['custom'] ?? []);
    $list[] = ['id' => uniqid('ci_', true), 'label' => $label, 'done' => false];
    $config['custom'] = $list;
    save_task_config($taskId, $config);
}

function update_task_checklist_item(int $taskId, string $itemId, string $label): void
{
    $label = trim($label);
    if ($label === '' || $itemId === '') { throw new InvalidArgumentException('Invalid'); }
    $config = fetch_task_config($taskId);
    $list = array_values($config['custom'] ?? []);
    foreach ($list as &$ci) {
        if (($ci['id'] ?? '') === $itemId) { $ci['label'] = $label; }
    }
    $config['custom'] = $list;
    save_task_config($taskId, $config);
}

function delete_task_checklist_item(int $taskId, string $itemId): void
{
    if ($itemId === '') { throw new InvalidArgumentException('Invalid'); }
    $config = fetch_task_config($taskId);
    $list = array_values($config['custom'] ?? []);
    $list = array_values(array_filter($list, static fn($ci) => ($ci['id'] ?? '') !== $itemId));
    $config['custom'] = $list;
    save_task_config($taskId, $config);
}

function ensure_detailed_status_row(int $taskId): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM detailed_status WHERE T_ID = :task');
    $stmt->execute(['task' => $taskId]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }

    $insert = $pdo->prepare('INSERT INTO detailed_status (T_ID, Created_At, Updated_At) VALUES (:task, NOW(), NOW())');
    $insert->execute(['task' => $taskId]);
    invalidate_task_cache();
    return ['T_ID' => $taskId];
}

function global_checklist_path(): string
{
    return __DIR__ . '/../../storage/checklist_global.json';
}

function fetch_global_checklist(): array
{
    $path = global_checklist_path();
    if (!is_file($path)) { return []; }
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    $list = is_array($data) ? $data : [];
    return array_values(array_map(static function ($ci) {
        return [
            'id' => (string) ($ci['id'] ?? uniqid('gci_', true)),
            'label' => trim((string) ($ci['label'] ?? '')),
            'done' => (bool) ($ci['done'] ?? false),
        ];
    }, $list));
}

function save_global_checklist(array $items): void
{
    $path = global_checklist_path();
    file_put_contents($path, json_encode(array_values($items)));
}

function add_global_checklist_item(string $label): void
{
    $label = trim($label);
    if ($label === '') { throw new InvalidArgumentException('Label required'); }
    $list = fetch_global_checklist();
    $list[] = ['id' => uniqid('gci_', true), 'label' => $label, 'done' => false];
    save_global_checklist($list);
}

function update_global_checklist_item(string $itemId, string $label): void
{
    $label = trim($label);
    if ($label === '' || $itemId === '') { throw new InvalidArgumentException('Invalid'); }
    $list = fetch_global_checklist();
    foreach ($list as &$ci) {
        if (($ci['id'] ?? '') === $itemId) { $ci['label'] = $label; }
    }
    unset($ci);
    save_global_checklist($list);
}

function delete_global_checklist_item(string $itemId): void
{
    if ($itemId === '') { throw new InvalidArgumentException('Invalid'); }
    $list = array_values(array_filter(fetch_global_checklist(), static fn($ci) => ($ci['id'] ?? '') !== $itemId));
    save_global_checklist($list);
}

function update_global_checklist(array $completedKeys): void
{
    $ids = array_map(static function ($k) {
        $s = (string) $k;
        return substr($s, 0, 7) === 'global:' ? substr($s, 7) : $s;
    }, $completedKeys);
    $set = array_flip($ids);
    $list = fetch_global_checklist();
    foreach ($list as &$ci) {
        $ci['done'] = isset($set[$ci['id']]);
    }
    unset($ci);
    save_global_checklist($list);
}

function tasks_due_within(int $days): array
{
    $today = new DateTimeImmutable('today');
    $end = $today->modify("+{$days} days");

    return array_values(array_filter(get_tasks(), static function (array $task) use ($today, $end) {
        $dueDate = $task['due_date'] ?? null;
        if (!$dueDate) {
            return false;
        }
        $due = new DateTimeImmutable($dueDate);
        return $due >= $today && $due <= $end;
    }));
}

function tasks_overdue(): array
{
    $today = new DateTimeImmutable('today');
    return array_values(array_filter(get_tasks(), static function (array $task) use ($today) {
        $dueDate = $task['due_date'] ?? null;
        if (!$dueDate) {
            return false;
        }
        $due = new DateTimeImmutable($dueDate);
        return $due < $today;
    }));
}

function find_task(int $taskId): ?array
{
    foreach (get_tasks() as $task) {
        if ($task['id'] === $taskId) {
            return $task;
        }
    }
    return null;
}

function task_status_summary(): array
{
    $summary = [];
    foreach (get_tasks() as $task) {
        $status = $task['status'] ?? 'Unknown';
        $summary[$status] = ($summary[$status] ?? 0) + 1;
    }
    return $summary;
}

function services_needing_alert(): array
{
    return array_filter(array_map(function ($task) {
        $analysis = analyze_service([
            'due_date' => $task['due_date'],
        ]);
        return $analysis['due_soon'] ? array_merge($task, $analysis) : null;
    }, get_tasks()));
}

function log_due_alert(array $service): void
{
    if (!isset($_SESSION['alert_log'])) {
        $_SESSION['alert_log'] = [];
    }
    $logKey = 'task_' . $service['id'];
    if (in_array($logKey, $_SESSION['alert_log'], true)) {
        return;
    }

    $message = sprintf(
        '[%s] Due alert for %s (Client: %s, Lead ID: %s, due in %d days)' . PHP_EOL,
        date('c'),
        $service['title'] ?? 'Unknown Task',
        $service['client'] ?? 'Unknown Client',
        $service['lead_id'] ?? 'n/a',
        $service['days_remaining'] ?? 0
    );

    ensure_alerts_log_rotation();
    file_put_contents(alerts_log_path(), $message, FILE_APPEND);
    $_SESSION['alert_log'][] = $logKey;
}

function ensure_task_audit_table(): void
{
    try {
        $pdo = db();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS task_audit (
                Audit_ID BIGSERIAL PRIMARY KEY,
                T_ID INTEGER NOT NULL,
                Action VARCHAR(32) NOT NULL,
                Actor_ID INTEGER,
                Actor_Name TEXT,
                Actor_Role TEXT,
                Details TEXT,
                Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS task_audit (
                Audit_ID BIGINT AUTO_INCREMENT PRIMARY KEY,
                T_ID INT NOT NULL,
                Action VARCHAR(32) NOT NULL,
                Actor_ID INT NULL,
                Actor_Name VARCHAR(255),
                Actor_Role VARCHAR(64),
                Details TEXT,
                Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )');
        }
    } catch (Throwable $e) {
        error_log('Audit table ensure failed: ' . $e->getMessage());
    }
}

function write_task_audit(int $taskId, string $action, array $actor, array $extra = []): void
{
    try {
        ensure_task_audit_table();
        $pdo = db();
        $context = array_merge($extra, [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'path' => $_SERVER['REQUEST_URI'] ?? '',
        ]);
        $stmt = $pdo->prepare('INSERT INTO task_audit (T_ID, Action, Actor_ID, Actor_Name, Actor_Role, Details, Created_At)
                               VALUES (:task, :action, :actor_id, :actor_name, :actor_role, :details, CURRENT_TIMESTAMP)');
        $stmt->execute([
            'task' => $taskId,
            'action' => $action,
            'actor_id' => (int) ($actor['id'] ?? 0),
            'actor_name' => $actor['name'] ?? '',
            'actor_role' => $actor['role'] ?? '',
            'details' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        $line = sprintf('[%s] Audit -> Task #%d %s by %s (%s) %s' . PHP_EOL, date('c'), $taskId, $action, $actor['name'] ?? 'Unknown', $actor['role'] ?? 'unknown', json_encode($context));
        ensure_audit_log_rotation();
        @file_put_contents(audit_log_path(), $line, FILE_APPEND);
    } catch (Throwable $e) {
        error_log('Audit write failed: ' . $e->getMessage());
    }
}

function ensure_global_audit_table(): void
{
    try {
        $pdo = db();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS audit_log (
                Audit_ID BIGSERIAL PRIMARY KEY,
                Domain VARCHAR(32) NOT NULL,
                Entity_ID INTEGER NOT NULL,
                Action VARCHAR(32) NOT NULL,
                Actor_ID INTEGER,
                Actor_Name TEXT,
                Actor_Role TEXT,
                Details TEXT,
                Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS audit_log (
                Audit_ID BIGINT AUTO_INCREMENT PRIMARY KEY,
                Domain VARCHAR(32) NOT NULL,
                Entity_ID INT NOT NULL,
                Action VARCHAR(32) NOT NULL,
                Actor_ID INT NULL,
                Actor_Name VARCHAR(255),
                Actor_Role VARCHAR(64),
                Details TEXT,
                Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )');
        }
    } catch (Throwable $e) {
        error_log('Global audit table ensure failed: ' . $e->getMessage());
    }
}

function write_audit(string $domain, int $entityId, string $action, array $actor, array $extra = []): void
{
    try {
        ensure_global_audit_table();
        $pdo = db();
        $context = array_merge($extra, [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'path' => $_SERVER['REQUEST_URI'] ?? '',
        ]);
        $stmt = $pdo->prepare('INSERT INTO audit_log (Domain, Entity_ID, Action, Actor_ID, Actor_Name, Actor_Role, Details, Created_At)
                               VALUES (:domain, :entity, :action, :actor_id, :actor_name, :actor_role, :details, CURRENT_TIMESTAMP)');
        $stmt->execute([
            'domain' => $domain,
            'entity' => $entityId,
            'action' => $action,
            'actor_id' => (int) ($actor['id'] ?? 0),
            'actor_name' => $actor['name'] ?? '',
            'actor_role' => $actor['role'] ?? '',
            'details' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        $line = sprintf('[%s] Audit[%s] -> #%d %s by %s (%s) %s' . PHP_EOL, date('c'), $domain, $entityId, $action, $actor['name'] ?? 'Unknown', $actor['role'] ?? 'unknown', json_encode($context));
        ensure_audit_log_rotation();
        @file_put_contents(audit_log_path(), $line, FILE_APPEND);
    } catch (Throwable $e) {
        error_log('Global audit write failed: ' . $e->getMessage());
    }
}

function fetch_task_audits(array $filters = [], int $limit = 25, int $offset = 0): array
{
    ensure_task_audit_table();
    $pdo = db();
    $where = [];
    $params = [];
    if (!empty($filters['task'])) { $where[] = 'T_ID = :task'; $params['task'] = (int) $filters['task']; }
    if (!empty($filters['actor'])) { $where[] = 'Actor_ID = :actor'; $params['actor'] = (int) $filters['actor']; }
    if (!empty($filters['action'])) { $where[] = 'Action = :action'; $params['action'] = (string) $filters['action']; }
    if (!empty($filters['from'])) { $where[] = 'Created_At >= :from'; $params['from'] = (string) $filters['from']; }
    if (!empty($filters['to'])) { $where[] = 'Created_At <= :to'; $params['to'] = (string) $filters['to']; }
    if (!empty($filters['q'])) { $where[] = 'LOWER(Details) LIKE :q'; $params['q'] = '%' . strtolower((string) $filters['q']) . '%'; }
    $sql = 'SELECT Audit_ID AS id, T_ID AS task_id, Action AS action, Actor_ID AS actor_id, Actor_Name AS actor_name, Actor_Role AS actor_role, Details AS details, Created_At AS created_at FROM task_audit';
    if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
    $sql .= ' ORDER BY Created_At DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map(static function (array $row): array {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['task_id'] = (int) ($row['task_id'] ?? 0);
        $row['actor_id'] = (int) ($row['actor_id'] ?? 0);
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function fetch_global_audits(array $filters = [], int $limit = 25, int $offset = 0): array
{
    ensure_global_audit_table();
    $pdo = db();
    $where = [];
    $params = [];
    if (!empty($filters['domain'])) { $where[] = 'Domain = :domain'; $params['domain'] = (string) $filters['domain']; }
    if (!empty($filters['entity'])) { $where[] = 'Entity_ID = :entity'; $params['entity'] = (int) $filters['entity']; }
    if (!empty($filters['actor'])) { $where[] = 'Actor_ID = :actor'; $params['actor'] = (int) $filters['actor']; }
    if (!empty($filters['action'])) { $where[] = 'Action = :action'; $params['action'] = (string) $filters['action']; }
    if (!empty($filters['from'])) { $where[] = 'Created_At >= :from'; $params['from'] = (string) $filters['from']; }
    if (!empty($filters['to'])) { $where[] = 'Created_At <= :to'; $params['to'] = (string) $filters['to']; }
    if (!empty($filters['q'])) { $where[] = 'LOWER(Details) LIKE :q'; $params['q'] = '%' . strtolower((string) $filters['q']) . '%'; }
    $sql = 'SELECT Audit_ID AS id, Domain AS domain, Entity_ID AS entity_id, Action AS action, Actor_ID AS actor_id, Actor_Name AS actor_name, Actor_Role AS actor_role, Details AS details, Created_At AS created_at FROM audit_log';
    if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
    $sql .= ' ORDER BY Created_At DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map(static function (array $row): array {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['entity_id'] = (int) ($row['entity_id'] ?? 0);
        $row['actor_id'] = (int) ($row['actor_id'] ?? 0);
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function fetch_file_audits(array $filters = [], int $limit = 1000, int $offset = 0): array
{
    $dir = __DIR__ . '/../../storage/logs';
    $files = [];
    $legacy = $dir . '/audit.log';
    if (is_file($legacy)) { $files[] = $legacy; }
    foreach (glob($dir . '/audit-*.log') ?: [] as $fp) {
        if (is_file($fp)) { $files[] = $fp; }
    }
    if (empty($files)) { return []; }
    $rows = [];
    $index = 0;
    foreach ($files as $fp) {
        $lines = file($fp, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
        $m1 = [];
        $m2 = [];
        $details = [];
        if (preg_match('/^\[(.+?)\]\s+Audit\[(.+?)\]\s*->\s*#(\d+)\s+(\w+)\s+by\s+(.+?)\s+\((.+?)\)\s+(\{.*\})$/', $line, $m1)) {
            $created = $m1[1];
            $domain = strtolower($m1[2]);
            $entityId = (int) $m1[3];
            $action = $m1[4];
            $actorName = $m1[5];
            $actorRole = strtolower($m1[6]);
            $details = json_decode($m1[7], true);
            $rows[] = [
                'id' => ++$index,
                'domain' => $domain,
                'entity_id' => $entityId,
                'action' => $action,
                'actor_id' => 0,
                'actor_name' => $actorName,
                'actor_role' => $actorRole,
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $created,
            ];
        } elseif (preg_match('/^\[(.+?)\]\s+Audit\s*->\s*Task\s*#(\d+)\s+(\w+)\s+by\s+(.+?)\s+\((.+?)\)\s+(\{.*\})$/', $line, $m2)) {
            $created = $m2[1];
            $taskId = (int) $m2[2];
            $action = $m2[3];
            $actorName = $m2[4];
            $actorRole = strtolower($m2[5]);
            $details = json_decode($m2[6], true);
            $rows[] = [
                'id' => ++$index,
                'domain' => 'task',
                'entity_id' => $taskId,
                'action' => $action,
                'actor_id' => 0,
                'actor_name' => $actorName,
                'actor_role' => $actorRole,
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $created,
            ];
        }
        }
    }
    $out = [];
    foreach ($rows as $row) {
        if (!empty($filters['domain']) && (string) $filters['domain'] !== (string) $row['domain']) { continue; }
        if (!empty($filters['entity']) && (int) $filters['entity'] !== (int) $row['entity_id']) { continue; }
        if (!empty($filters['action']) && (string) $filters['action'] !== (string) $row['action']) { continue; }
        if (!empty($filters['from']) && strtotime((string) $row['created_at']) < strtotime((string) $filters['from'])) { continue; }
        if (!empty($filters['to']) && strtotime((string) $row['created_at']) > strtotime((string) $filters['to'])) { continue; }
        if (!empty($filters['q'])) { $d = strtolower((string) $row['details']); if (strpos($d, strtolower((string) $filters['q'])) === false) { continue; } }
        $out[] = $row;
    }
    usort($out, static function ($a, $b) { return strcmp((string) $b['created_at'], (string) $a['created_at']); });
    return array_slice($out, $offset, $limit);
}

function task_days_remaining(array $task): int
{
    if (empty($task['due_date'])) {
        return 0;
    }

    try {
        $due = new DateTimeImmutable($task['due_date']);
        $today = new DateTimeImmutable('today');
        return (int) $today->diff($due)->format('%r%a');
    } catch (Exception $exception) {
        error_log('Days remaining calc failed: ' . $exception->getMessage());
        return 0;
    }
}

function invalidate_task_cache(): void
{
    $GLOBALS['TASK_CACHE_VERSION'] = microtime(true);
}

function task_acknowledgements_for_user(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT T_ID FROM task_reminder_ack WHERE EMP_ID = :user AND Ack_Date = :ack');
        $stmt->execute([
            'user' => $userId,
            'ack' => date('Y-m-d'),
        ]);

        $acks = [];
        foreach ($stmt->fetchAll() as $row) {
            $acks[(int) $row['T_ID']] = true;
        }

        return $acks;
    } catch (Throwable $exception) {
        error_log('Ack lookup failed: ' . $exception->getMessage());
        return [];
    }
}
