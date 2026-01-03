<?php
declare(strict_types=1);

function reminders_bootstrap(): void
{
    if (!isset($_SESSION['reminders_sent'])) {
        $_SESSION['reminders_sent'] = [];
    }
}

function reminder_log_path(): string
{
    $dir = __DIR__ . '/../../storage/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    return $dir . '/reminders.log';
}

function trigger_due_reminders(int $windowDays = 7): int
{
    reminders_bootstrap();

    $today = date('Y-m-d');
    $count = 0;
    $ceo = ceo_contact();

    foreach (get_tasks() as $task) {
        $daysRemaining = task_days_remaining($task);
        if ($daysRemaining > $windowDays) {
            continue;
        }

        $due = new DateTimeImmutable($task['due_date']);
        $shouldNotify = $due <= new DateTimeImmutable('+' . $windowDays . ' days');
        if (!$shouldNotify) {
            continue;
        }

        $recipients = [];
        if ($ceo) {
            $recipients[] = $ceo;
        }
        if ($lead = find_employee($task['lead_id'])) {
            $recipients[] = $lead;
        }
        foreach ($task['team_ids'] as $teamId) {
            if ($member = find_employee((int) $teamId)) {
                $recipients[] = $member;
            }
        }

        foreach ($recipients as $recipient) {
            $key = $task['id'] . ':' . ($recipient['email'] ?? $recipient['name']);
            $recipientId = (int) ($recipient['id'] ?? 0);

            if ($recipientId && reminder_acknowledged_today($task['id'], $recipientId, $today)) {
                continue;
            }

            if (($_SESSION['reminders_sent'][$key] ?? null) === $today) {
                continue;
            }

            $line = sprintf(
                '[%s] Reminder -> %s (%s) | Task #%d "%s" due %s (%d days remaining)' . PHP_EOL,
                date('c'),
                $recipient['name'] ?? 'Unknown',
                $recipient['email'] ?? 'n/a',
                $task['id'],
                $task['title'],
                $task['due_date'],
                $daysRemaining
            );
            ensure_reminder_log_rotation();
            file_put_contents(reminder_log_path(), $line, FILE_APPEND);

            if (!empty($recipient['email'])) {
                $payload = $task;
                $payload['client_name'] = $payload['client_name'] ?? ($payload['client_id'] ? (find_client((int) $payload['client_id'])['name'] ?? '') : '');
                send_due_task_email($recipient, $payload, $daysRemaining);
            }

            $_SESSION['reminders_sent'][$key] = $today;
            $count++;
        }
    }

    return $count;
}

function ensure_reminder_log_rotation(): void
{
    $dir = __DIR__ . '/../../storage/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $marker = $dir . '/reminders.current-month';
    $current = date('Y-m');
    $previous = null;
    if (is_file($marker)) { $previous = trim((string) @file_get_contents($marker)); }
    if ($previous === '' || $previous === null) {
        @file_put_contents($marker, $current);
        if (!is_file($dir . '/reminders.log')) { @touch($dir . '/reminders.log'); }
        return;
    }
    if ($previous !== $current) {
        $active = $dir . '/reminders.log';
        if (is_file($active) && @filesize($active) > 0) {
            $target = $dir . '/reminders-' . $previous . '.log';
            if (is_file($target)) {
                $i = 1;
                do { $target = $dir . '/reminders-' . $previous . '-' . $i . '.log'; $i++; } while (is_file($target) && $i < 100);
            }
            @rename($active, $target);
        }
        @file_put_contents($marker, $current);
        if (!is_file($dir . '/reminders.log')) { @touch($dir . '/reminders.log'); }
    }
}

function get_user_due_reminders(int $userId, int $windowDays = 7): array
{
    $user = find_employee($userId);
    if (!$user) { return []; }
    $role = strtolower((string) ($user['role_type'] ?? $user['role'] ?? ''));
    $acks = task_acknowledgements_for_user($userId);
    $today = new DateTimeImmutable('today');
    $end = $today->modify('+' . $windowDays . ' days');
    $list = [];
    foreach (get_tasks() as $task) {
        $dueDate = $task['due_date'] ?? null; if (!$dueDate) { continue; }
        $due = new DateTimeImmutable($dueDate);
        if ($due < $today || $due > $end) { continue; }
        if (isset($acks[(int) ($task['id'] ?? 0)])) { continue; }
        $isMine = false;
        if ($role === 'ceo') { $isMine = true; }
        else {
            if ((int) ($task['lead_id'] ?? 0) === $userId) { $isMine = true; }
            else {
                foreach ((array) ($task['team_ids'] ?? []) as $tid) { if ((int) $tid === $userId) { $isMine = true; break; } }
            }
        }
        if ($isMine) { $list[] = $task; }
    }
    return $list;
}

function reminder_acknowledged_today(int $taskId, int $userId, ?string $day = null): bool
{
    if ($taskId <= 0 || $userId <= 0) {
        return false;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT 1 FROM task_reminder_ack WHERE T_ID = :task AND EMP_ID = :emp AND Ack_Date = :ack LIMIT 1');
        $stmt->execute([
            'task' => $taskId,
            'emp' => $userId,
            'ack' => $day ?? date('Y-m-d'),
        ]);

        return (bool) $stmt->fetchColumn();
    } catch (Throwable $exception) {
        error_log('Reminder ack lookup failed: ' . $exception->getMessage());
        return false;
    }
}

function acknowledge_task_reminder(int $taskId, int $userId): void
{
    if ($taskId <= 0 || $userId <= 0) {
        return;
    }

    try {
        $pdo = db();
        $driver = db_driver();
        $sql = 'INSERT INTO task_reminder_ack (T_ID, EMP_ID, Ack_Date) VALUES (:task, :emp, :ack)';
        
        if ($driver === 'pgsql' || $driver === 'sqlite') {
            $sql .= ' ON CONFLICT (T_ID, EMP_ID) DO UPDATE SET Ack_Date = EXCLUDED.Ack_Date';
        } else {
            $sql .= ' ON DUPLICATE KEY UPDATE Ack_Date = VALUES(Ack_Date)';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'task' => $taskId,
            'emp' => $userId,
            'ack' => date('Y-m-d'),
        ]);
    } catch (Throwable $exception) {
        error_log('Ack write skipped: ' . $exception->getMessage());
    }
}
