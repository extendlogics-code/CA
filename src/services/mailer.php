<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function mailer_is_enabled(): bool
{
    $config = mail_config();
    return !empty($config['enabled']) && !empty($config['host']);
}

function send_due_task_email(array $recipient, array $task, int $daysRemaining): bool
{
    if (!mailer_is_enabled()) { return false; }
    $email = (string) ($recipient['email'] ?? '');
    if ($email === '') { return false; }
    $cfg = mail_config();
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = (string) ($cfg['host'] ?? 'smtp.gmail.com');
        $mail->SMTPAuth = true;
        $mail->Username = (string) ($cfg['username'] ?? 'svasan1995@gmail.com');
        $mail->Password = (string) ($cfg['password'] ?? '918939111823');
        $mail->Port = (int) ($cfg['port'] ?? 587);
        $enc = $cfg['encryption'] ?? null; if ($enc) { $mail->SMTPSecure = $enc; }
        $mail->setFrom((string) ($cfg['from_email'] ?? ($cfg['username'] ?? '')), (string) ($cfg['from_name'] ?? 'CA Service Hub'));
        $mail->addAddress($email, (string) ($recipient['name'] ?? 'CA Service HUB'));
        $title = (string) ($task['title_display'] ?? ($task['title'] ?? ($task['T_NAME'] ?? 'Task')));
        $id = (int) ($task['id'] ?? ($task['T_ID'] ?? 0));
        $client = (string) ($task['client_name'] ?? ($task['client'] ?? ''));
        $due = (string) ($task['alert']['date'] ?? ($task['due_date'] ?? ''));
        $mail->Subject = 'In ' . $daysRemaining . ' day' . ($daysRemaining===1?'':'s') . ': Task #' . $id . ' \"' . $title . '\"';
        $mail->Body = "Task: $title\nClient: $client\nDue: $due\nDays remaining: $daysRemaining\n\nDashboard: /dashboard\nTask Board: /tasks";
        $mail->AltBody = $mail->Body;
        if (!empty($cfg['debug'])) { $mail->SMTPDebug = 2; }
        return $mail->send();
    } catch (Exception $e) {
        error_log('[MAILER] Reminder send failed: ' . $e->getMessage());
        return false;
    }
}

function send_alert_digest(string $email, array $items): bool
{
    if (!mailer_is_enabled() || $email==='') { return false; }
    $cfg = mail_config();
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = (string) ($cfg['host'] ?? 'smtp.gmail.com');
        $mail->SMTPAuth = true;
        $mail->Username = (string) ($cfg['username'] ?? 'svasan1995@gmail.com');
        $mail->Password = (string) ($cfg['password'] ?? '918939111823');
        $mail->Port = (int) ($cfg['port'] ?? 587);
        $enc = $cfg['encryption'] ?? null; if ($enc) { $mail->SMTPSecure = $enc; }
        $mail->setFrom((string) ($cfg['from_email'] ?? ($cfg['username'] ?? '')), (string) ($cfg['from_name'] ?? 'CA Service Hub'));
        $mail->addAddress($email);
        $mail->Subject = 'Daily alerts & reminders';
        $lines = [];
        foreach ($items as $t) {
            $title = (string) ($t['title_display'] ?? ($t['title'] ?? ($t['T_NAME'] ?? 'Task')));
            $id = (int) ($t['id'] ?? ($t['T_ID'] ?? 0));
            $client = (string) ($t['client_name'] ?? ($t['client'] ?? ''));
            $due = (string) (($t['alert']['date'] ?? null) ?: ($t['due_date'] ?? ''));
            $lines[] = sprintf('#%d %s · %s · due %s', $id, $title, $client, $due);
        }
        $body = "Here are your alerts/reminders:\n\n" . implode("\n", $lines) . "\n\nDashboard: /dashboard\nTask Board: /tasks";
        $mail->Body = $body; $mail->AltBody = $body;
        if (!empty($cfg['debug'])) { $mail->SMTPDebug = 2; }
        return $mail->send();
    } catch (Exception $e) {
        error_log('[MAILER] Digest send failed: ' . $e->getMessage());
        return false;
    }
}

function send_status_change_email(array $recipient, array $task, string $oldStatus, string $newStatus, array $actor): bool
{
    return false;
}
