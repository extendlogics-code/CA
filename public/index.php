<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$path = request_path();
$method = $_SERVER['REQUEST_METHOD'];

if ($path === '/') {
    if (is_authenticated()) {
        redirect('/dashboard');
    }
    redirect('/login');
}

switch ($path) {
    case '/login':
        try {
            $pdo = db();
            $stmt = $pdo->query("SELECT 1 FROM employee e JOIN roles r ON r.Role_ID = e.Role_ID WHERE LOWER(r.Role_Name) = 'superadmin' LIMIT 1");
            $superadminExists = (bool) $stmt->fetchColumn();
        } catch (Throwable $exception) {
            error_log('[LOGIN] Failed to determine superadmin presence: ' . $exception->getMessage());
            $superadminExists = true;
        }

        if ($method === 'POST') {
            if (!validate_csrf($_POST['csrf_token'] ?? null)) {
                set_flash('error', 'Security token mismatch. Please try again.');
                redirect('/login');
            }

            $action = $_POST['action'] ?? 'login';

            if ($action === 'register') {
                $result = handle_registration();

                if ($result['success'] ?? false) {
                    set_flash('success', $result['message'] ?? 'Registration successful! Please login.');
                    unset($_SESSION['auth_form']);
                } else {
                    $_SESSION['auth_form'] = [
                        'tab' => 'register',
                        'data' => $result['data'] ?? [
                            'full_name' => trim($_POST['full_name'] ?? ''),
                            'email' => trim($_POST['email'] ?? ''),
                            'role' => $_POST['role'] ?? $_POST['role_id'] ?? '',
                        ],
                        'errors' => $result['field_errors'] ?? [],
                    ];

                    if (!empty($result['message'])) {
                        set_flash('error', $result['message']);
                    }
                }

                redirect('/login');
            }

            if ($action === 'login') {
                handle_login();
            }

            if ($action === 'mfa_verify') {
                $code = trim($_POST['code'] ?? '');
                $mfa = $_SESSION['mfa'] ?? null;
                if (!$mfa || ($mfa['expires'] ?? 0) < time()) {
                    write_login_log('[MFA EXPIRED]');
                    $_SESSION['auth_form'] = [
                        'tab' => 'login',
                        'data' => ['email' => trim($_POST['email'] ?? '')],
                        'errors' => ['password' => 'MFA session expired. Please sign in again.'],
                    ];
                    set_flash('error', 'MFA session expired. Please sign in again.');
                    redirect('/login');
                }
                $_SESSION['mfa']['attempts'] = (int) ($_SESSION['mfa']['attempts'] ?? 0) + 1;
                if ($code !== ($mfa['code'] ?? '')) {
                    write_login_log('[MFA INVALID] ' . ($mfa['email'] ?? ''));
                    $_SESSION['auth_form'] = [
                        'tab' => 'mfa',
                        'data' => ['email' => $mfa['email'] ?? ''],
                        'errors' => ['code' => 'Invalid verification code.'],
                    ];
                    set_flash('error', 'Invalid verification code.');
                    redirect('/login');
                }
                $user = $mfa['user'];
                unset($_SESSION['mfa']);
                write_login_log('[MFA VERIFIED] ' . ($user['email'] ?? ''));
                login_user($user);
                set_flash('success', 'Login successful');
                redirect('/dashboard');
            }

            set_flash('error', 'Invalid action.');
            redirect('/login');
        }

        $authForm = $_SESSION['auth_form'] ?? [];
        unset($_SESSION['auth_form']);

        $activeTab = $authForm['tab'] ?? 'login';
        $loginForm = $activeTab === 'login' ? ($authForm['data'] ?? []) : [];
        $registerForm = $activeTab === 'register' ? ($authForm['data'] ?? []) : [];
        $registerErrors = $activeTab === 'register' ? ($authForm['errors'] ?? []) : [];

        render('auth/login', [
            'title' => 'Sign in',
            'flashError' => get_flash('error'),
            'flashSuccess' => get_flash('success'),
            'show_registration_link' => !$superadminExists,
            'active_tab' => $activeTab,
            'login_form' => $loginForm,
            'register_form' => $registerForm,
            'register_errors' => $registerErrors,
        ]);
        break;

    case '/logout':
        if ($method === 'POST' && validate_csrf($_POST['csrf_token'] ?? null)) {
            logout_user();
        }
        redirect('/login');
        break;

    case '/dashboard':
        require_auth();
        $user = current_user();
        $remindersTriggered = 0;
        $userReminders = [];
        $statusSummary = [];
        $tasks = [];
        $upcoming = [];
        $overdue = [];
        $clients = [];

        try {
            $remindersTriggered = trigger_due_reminders();
            $userReminders = get_user_due_reminders((int) ($user['id'] ?? 0));
            $tasks = get_tasks();
            $alertsRoute = array_filter($tasks, static function($t){
                $date = $t['alert']['date'] ?? null; if (!$date) return false; $st = strtolower($t['status'] ?? ''); if ($st === 'completed') return false;
                $dr = (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($date)); $days = (int) $dr->format('%r%a'); return $days <= 3; });

            if (mailer_is_enabled()) {
                try {
                    foreach ($alertsRoute as $t) {
                        $dr = (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($t['alert']['date']));
                        $days = (int) $dr->format('%r%a');
                        $recipients = [];
                        $ceo = ceo_contact(); if ($ceo) { $recipients[] = $ceo; }
                        if ($lead = find_employee((int) ($t['lead_id'] ?? 0))) { $recipients[] = $lead; }
                        foreach ((array) ($t['team_ids'] ?? []) as $tid) { if ($member = find_employee((int) $tid)) { $recipients[] = $member; } }
                        foreach ($recipients as $rcpt) { send_due_task_email($rcpt, $t, $days); }
                    }
                    if (!isset($_SESSION['daily_alert_mail'])) { $_SESSION['daily_alert_mail'] = []; }
                    $k = ((int) ($user['id'] ?? 0)) . ':' . date('Y-m-d');
                    if (empty($_SESSION['daily_alert_mail'][$k])) {
                        $digestItems = array_merge($alertsRoute, $userReminders);
                        if (!empty($digestItems) && !empty($user['email'])) {
                            send_alert_digest((string) $user['email'], $digestItems);
                            $_SESSION['daily_alert_mail'][$k] = 1;
                        }
                    }
                } catch (Throwable $e) { /* ignore mail errors */ }
            }

            $statusSummary = task_status_summary();
            $upcoming = tasks_due_within(7);
            $overdue = tasks_overdue();
            $clients = get_clients();
        } catch (Throwable $exception) {
            write_app_error('dashboard', 'Dashboard load failed', $exception);
            set_flash('error', 'Unable to load dashboard data. Please try again.');
        }

        render('dashboard', [
            'title' => 'Command Center',
            'user' => $user,
            'statusSummary' => $statusSummary,
            'tasks' => $tasks,
            'upcoming' => $upcoming,
            'overdue' => $overdue,
            'clients' => $clients,
            'userReminders' => $userReminders,
            'remindersFired' => $remindersTriggered,
            'flashSuccess' => get_flash('success'),
        ]);
        break;

    case '/dashboard/digest':
        require_auth();
        $user = current_user();
        $alerts = array_filter(get_tasks(), static function($t){
            $date = $t['alert']['date'] ?? null; if (!$date) return false; $st = strtolower($t['status'] ?? ''); if ($st === 'completed') return false;
            $dr = (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($date)); $days = (int) $dr->format('%r%a'); return $days <= 3; });
        $items = array_merge($alerts, get_user_due_reminders((int) ($user['id'] ?? 0)));
        render('dashboard_digest', [
            'title' => 'Alerts & Upcoming Digest',
            'user' => $user,
            'items' => $items,
        ]);
        break;

    case '/debug/mail':
        require_roles(['ceo']);
        $user = current_user();
        $alertsRoute = array_filter(get_tasks(), static function($t){
            $date = $t['alert']['date'] ?? null; if (!$date) return false; $st = strtolower($t['status'] ?? ''); if ($st === 'completed') return false;
            $dr = (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($date)); $days = (int) $dr->format('%r%a'); return $days <= 3; });
        $items = array_merge($alertsRoute, get_user_due_reminders((int) ($user['id'] ?? 0)));
        $email = (string) ($user['email'] ?? '');
        if (mailer_is_enabled() && $email !== '') {
            try {
                $ok = send_alert_digest($email, !empty($items) ? $items : array_slice(get_tasks(), 0, 5));
                set_flash($ok ? 'success' : 'error', $ok ? ('Test email sent to ' . $email) : 'Mailer enabled but send failed');
            } catch (Throwable $e) {
                set_flash('error', 'Failed to send test email: ' . $e->getMessage());
            }
        } else {
            set_flash('error', 'Mailer not enabled or user email missing.');
        }
        redirect('/dashboard');
        break;

    case '/audits':
        require_roles(['ceo']);
        $filters = [
            'domain' => trim((string) ($_GET['domain'] ?? 'task')) ?: 'task',
            'task' => (int) ($_GET['task'] ?? 0) ?: null,
            'entity' => (int) ($_GET['entity'] ?? 0) ?: null,
            'actor' => (int) ($_GET['actor'] ?? 0) ?: null,
            'action' => trim((string) ($_GET['action'] ?? '')) ?: null,
            'from' => trim((string) ($_GET['from'] ?? '')) ?: null,
            'to' => trim((string) ($_GET['to'] ?? '')) ?: null,
            'q' => trim((string) ($_GET['q'] ?? '')) ?: null,
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $per = min(100, max(10, (int) ($_GET['per'] ?? 25)));
        $offset = ($page - 1) * $per;
        $domain = (string) ($filters['domain'] ?? 'task');
        $mode = $domain === 'task' ? 'task' : ($domain === 'file' ? 'file' : 'global');
        if ($mode === 'task') {
            $audits = fetch_task_audits(array_filter([
                'task' => $filters['task'] ?? null,
                'actor' => $filters['actor'] ?? null,
                'action' => $filters['action'] ?? null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'q' => $filters['q'] ?? null,
            ], static fn($v) => $v !== null && $v !== ''), $per, $offset);
        } elseif ($mode === 'global') {
            $audits = fetch_global_audits(array_filter([
                'domain' => $domain,
                'entity' => $filters['entity'] ?? null,
                'actor' => $filters['actor'] ?? null,
                'action' => $filters['action'] ?? null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'q' => $filters['q'] ?? null,
            ], static fn($v) => $v !== null && $v !== ''), $per, $offset);
        } else {
            $audits = fetch_file_audits(array_filter([
                'domain' => (string) ($_GET['domain_filter'] ?? ''),
                'entity' => $filters['entity'] ?? null,
                'action' => $filters['action'] ?? null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'q' => $filters['q'] ?? null,
            ], static fn($v) => $v !== null && $v !== ''), $per, $offset);
        }
        if (strtolower($_GET['export'] ?? '') === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="audits.csv"');
            $out = fopen('php://output', 'w');
            $s = static function($v){
                if (!is_string($v)) { return $v; }
                return preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
            };
            if ($mode === 'task') {
                fputcsv($out, ['id','task_id','action','actor_id','actor_name','actor_role','created_at','details']);
                foreach ($audits as $row) {
                    fputcsv($out, [$s($row['id']),$s($row['task_id']),$s($row['action']),$s($row['actor_id']),$s($row['actor_name']),$s($row['actor_role']),$s($row['created_at']),$s($row['details'])]);
                }
            } elseif ($mode === 'global') {
                fputcsv($out, ['id','domain','entity_id','action','actor_id','actor_name','actor_role','created_at','details']);
                foreach ($audits as $row) {
                    fputcsv($out, [$s($row['id']),$s($row['domain']),$s($row['entity_id']),$s($row['action']),$s($row['actor_id']),$s($row['actor_name']),$s($row['actor_role']),$s($row['created_at']),$s($row['details'])]);
                }
            } else {
                fputcsv($out, ['id','domain','entity_id','action','actor_id','actor_name','actor_role','created_at','details']);
                foreach ($audits as $row) {
                    fputcsv($out, [$s($row['id']),$s($row['domain']),$s($row['entity_id']),$s($row['action']),$s($row['actor_id']),$s($row['actor_name']),$s($row['actor_role']),$s($row['created_at']),$s($row['details'])]);
                }
            }
            fclose($out);
            exit;
        }
        $appliedFilters = array_filter([
            $filters['task'] ?? null,
            $filters['entity'] ?? null,
            $filters['actor'] ?? null,
            $filters['action'] ?? null,
            $filters['from'] ?? null,
            $filters['to'] ?? null,
            $filters['q'] ?? null,
        ], static fn($v) => $v !== null && $v !== '');
        if (!empty($appliedFilters)) {
            set_flash('success', 'Filters applied.');
        }

        render('audits', [
            'title' => 'Audit Trail',
            'user' => current_user(),
            'audits' => $audits,
            'filters' => $filters,
            'scope' => $mode,
            'page' => $page,
            'per' => $per,
            'flashSuccess' => get_flash('success'),
            'flashError' => get_flash('error'),
        ]);
        break;

    case '/license':
        require_roles(['ceo','superadmin']);
        render('license', [
            'title' => 'System License Status',
            'user' => current_user(),
            'license' => get_license_info(),
            'flashSuccess' => get_flash('success'),
            'flashError' => get_flash('error'),
        ]);
        break;

    case '/tasks':
        require_roles(['ceo', 'lead', 'employee']);
        $user = current_user();

        if ($method === 'POST') {
            if (!validate_csrf($_POST['csrf_token'] ?? null)) {
                set_flash('error', 'Security token mismatch.');
                redirect('/tasks');
            }

            $action = $_POST['action'] ?? '';
            $redirectTarget = '/tasks';
            try {
                switch ($action) {
                    case 'create_task':
                        if (!user_has_role(['ceo','lead'])) { set_flash('error', 'Only CEO or Lead can create tasks.'); break; }
                        $title = trim($_POST['title'] ?? '');
                        $clientIdRaw = trim((string) ($_POST['client_id'] ?? ''));
                        $clientId = $clientIdRaw;
                        if ($clientIdRaw !== '') {
                            foreach (get_clients() as $c) {
                                $cid = (string) ($c['c_id'] ?? '');
                                $nid = (string) ($c['id'] ?? '');
                                $code = (string) ($c['code'] ?? ($c['client_code'] ?? ''));
                                if (($clientIdRaw === $nid || $clientIdRaw === $code) && $cid !== '') {
                                    $clientId = $cid;
                                    break;
                                }
                            }
                        }
                        $services = array_values(array_filter((array) ($_POST['services'] ?? [])));
                        $leadId = (int) ($_POST['lead_id'] ?? 0);
                        if ($leadId <= 0 && user_has_role('lead')) {
                            $leadId = (int) ($user['id'] ?? 0);
                        }
                        $teamIds = array_values(array_map('intval', (array) ($_POST['team_ids'] ?? [])));
                        $statusId = (int) ($_POST['status_id'] ?? 0);
                        $taskYearInput = trim((string) ($_POST['task_year'] ?? ''));
                        if ($taskYearInput === '') {
                            throw new InvalidArgumentException('Select a task year.');
                        }
                        if (!preg_match('/^\d{4}$/', $taskYearInput)) {
                            throw new InvalidArgumentException('Enter a valid 4-digit year.');
                        }
                        $taskYear = (int) $taskYearInput;
                        $dueDate = trim((string) ($_POST['due_date'] ?? ''));
                        if ($dueDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) { throw new InvalidArgumentException('Enter a valid due date.'); }
                        $initialComment = trim($_POST['initial_comment'] ?? '');

                        if ($leadId <= 0) {
                            $availableLeads = get_employees('lead');
                            if (empty($availableLeads)) {
                                throw new InvalidArgumentException('No lead users available. Create a Lead user first.');
                            }
                        }
                        if ($title === '' || $clientIdRaw === '' || $leadId <= 0) {
                            throw new InvalidArgumentException('Title, client, and lead are mandatory.');
                        }

                        if (empty($services)) { throw new InvalidArgumentException('Select at least one service.'); }
                        if (empty($teamIds)) { throw new InvalidArgumentException('Select one or more team members.'); }
                        if ($statusId <= 0) { throw new InvalidArgumentException('Select a status.'); }

                        $taskId = add_task([
                            'title' => $title,
                            'client_id' => $clientId,
                            'services' => $services,
                            'lead_id' => $leadId,
                            'team_ids' => $teamIds,
                            'status_id' => $statusId,
                            'task_year' => $taskYear,
                            'due_date' => $dueDate,
                        ]);

                        $hasGst = in_array('gst', array_map('strtolower', $services), true);
                        $gstFiling = $hasGst || isset($_POST['gst_toggle']);
                        $ret = trim((string) ($_POST['gst_return_month'] ?? ''));
                        $cfg = fetch_task_config($taskId);
                        if ($gstFiling) {
                            $gst = ['filing' => true];
                            if ($ret !== '' && preg_match('/^\d{4}-\d{2}$/', $ret)) {
                                $dt = DateTime::createFromFormat('Y-m', $ret) ?: new DateTime('first day of this month');
                                $send = $dt->modify('+1 month')->format('Y-m');
                                $gst['return_month'] = $ret;
                                $gst['send_month'] = $send;
                            }
                            $cfg['gst'] = $gst;
                        } else {
                            unset($cfg['gst']);
                        }
                        save_task_config($taskId, $cfg);

                        if ($initialComment !== '') {
                            add_task_comment($taskId, $initialComment, $user);
                        }

                        write_task_audit($taskId, 'create', $user, ['title' => $title, 'client_id' => $clientId]);
                        set_flash('success', 'Task #' . $taskId . ' created successfully.');
                        break;

                    case 'update_task':
                        $taskId = (int) ($_POST['task_id'] ?? 0);
                        $task = find_task($taskId);
                        if (!$task) { set_flash('error', 'Unknown task.'); break; }
                        if (!user_has_role('ceo')) { set_flash('error', 'Only CEO can update this task.'); break; }
                        $title = trim($_POST['title'] ?? '');
                        $clientIdRaw = trim((string) ($_POST['client_id'] ?? ''));
                        $clientId = $clientIdRaw;
                        if ($clientIdRaw !== '') {
                            foreach (get_clients() as $c) {
                                $cid = (string) ($c['c_id'] ?? '');
                                $nid = (string) ($c['id'] ?? '');
                                $code = (string) ($c['code'] ?? ($c['client_code'] ?? ''));
                                if (($clientIdRaw === $nid || $clientIdRaw === $code) && $cid !== '') {
                                    $clientId = $cid;
                                    break;
                                }
                            }
                        }
                        $services = array_values(array_filter((array) ($_POST['services'] ?? [])));
                        $leadId = (int) ($_POST['lead_id'] ?? 0);
                        if ($leadId <= 0 && user_has_role('lead')) {
                            $leadId = (int) ($user['id'] ?? 0);
                        }
                        $teamIds = array_values(array_map('intval', (array) ($_POST['team_ids'] ?? [])));
                        $statusId = (int) ($_POST['status_id'] ?? 0);
                        $taskYearInput = trim((string) ($_POST['task_year'] ?? ''));
                        if ($taskId <= 0) {
                            throw new InvalidArgumentException('Unknown task.');
                        }
                        if ($leadId <= 0) {
                            $availableLeads = get_employees('lead');
                            if (empty($availableLeads)) {
                                throw new InvalidArgumentException('No lead users available. Create a Lead user first.');
                            }
                        }
                        if ($title === '' || $clientIdRaw === '' || $leadId <= 0) {
                            throw new InvalidArgumentException('Title, client, and lead are mandatory.');
                        }
                        if (empty($services)) {
                            throw new InvalidArgumentException('Select at least one service.');
                        }
                        if ($taskYearInput === '' || !preg_match('/^\d{4}$/', $taskYearInput)) {
                            throw new InvalidArgumentException('Enter a valid 4-digit year.');
                        }
                        $taskYear = (int) $taskYearInput;
                        $dueDate = trim((string) ($_POST['due_date'] ?? ''));
                        if ($dueDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) { throw new InvalidArgumentException('Enter a valid due date.'); }
                        $duePerNotice = $_POST['due_per_notice'] ?? null;
                        $noticeDate = $_POST['notice_date'] ?? null;

                        update_task([
                            'id' => $taskId,
                            'title' => $title,
                            'client_id' => $clientId,
                            'services' => $services,
                            'lead_id' => $leadId,
                            'team_ids' => $teamIds,
                            'status_id' => $statusId,
                            'task_year' => $taskYear,
                            'due_date' => $dueDate,
                            'due_per_notice' => $duePerNotice,
                            'notice_date' => $noticeDate,
                        ]);

                        $hasGst = in_array('gst', array_map('strtolower', $services), true);
                        $gstFiling = $hasGst || isset($_POST['gst_toggle']);
                        $ret = trim((string) ($_POST['gst_return_month'] ?? ''));
                        $cfg = fetch_task_config($taskId);
                        if ($gstFiling) {
                            $gst = ['filing' => true];
                            if ($ret !== '' && preg_match('/^\d{4}-\d{2}$/', $ret)) {
                                $dt = DateTime::createFromFormat('Y-m', $ret) ?: new DateTime('first day of this month');
                                $send = $dt->modify('+1 month')->format('Y-m');
                                $gst['return_month'] = $ret;
                                $gst['send_month'] = $send;
                            }
                            $cfg['gst'] = $gst;
                        } else {
                            unset($cfg['gst']);
                        }
                        $alertDateRaw = trim((string) ($_POST['alert_date'] ?? ''));
                        if (user_has_role('ceo')) {
                            if ($alertDateRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $alertDateRaw)) {
                                $cfg['alert'] = ['date' => $alertDateRaw];
                            } else {
                                unset($cfg['alert']);
                            }
                        }
                        save_task_config($taskId, $cfg);

                        $updateComment = trim((string) ($_POST['update_comment'] ?? ''));
                        if ($updateComment !== '') {
                            add_task_comment($taskId, $updateComment, $user);
                            write_task_audit($taskId, 'add_comment', $user, ['comment' => $updateComment]);
                        }

                        if (isset($_FILES['notice_pdf']) && ($_FILES['notice_pdf']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                            $upload = save_uploaded_file($_FILES['notice_pdf'], 'tasks', ['pdf']);
                            $upload['uploaded_by'] = $user['name'];
                            add_task_attachment($taskId, $upload);
                            write_task_audit($taskId, 'upload_attachment', $user, ['name' => $upload['Original_Name'] ?? ($upload['name'] ?? ''), 'size' => (int) ($upload['File_Size'] ?? ($upload['size'] ?? 0))]);
                        }

                        write_task_audit($taskId, 'update', $user, ['title' => $title, 'client_id' => $clientId]);
                        set_flash('success', 'Task #' . $taskId . ' updated successfully.');
                        break;

                    case 'add_comment':
                        $taskId = (int) ($_POST['task_id'] ?? 0);
                        $body = trim((string) ($_POST['comment'] ?? ''));
                        add_task_comment($taskId, $body, $user);
                        write_task_audit($taskId, 'add_comment', $user, ['comment' => $body]);
                        set_flash('success', 'Comment added.');
                        break;

                    case 'upload_attachment':
                        $taskId = (int) ($_POST['task_id'] ?? 0);
                        if (!isset($_FILES['attachment'])) {
                            throw new RuntimeException('No file uploaded.');
                        }

                        $upload = save_uploaded_file(
                            $_FILES['attachment'],
                            'tasks',
                            ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'zip', 'jpg', 'jpeg', 'png', 'txt']
                        );

                        $upload['uploaded_by'] = $user['name'];
                        add_task_attachment($taskId, $upload);
                        write_task_audit($taskId, 'upload_attachment', $user, ['name' => $upload['Original_Name'] ?? ($upload['name'] ?? ''), 'size' => (int) ($upload['File_Size'] ?? ($upload['size'] ?? 0))]);
                        set_flash('success', 'Evidence uploaded.');
                        break;

                    case 'update_status':
                        $taskId = (int) ($_POST['task_id'] ?? 0);
                        $task = find_task($taskId);
                        $user = current_user();
                        $isCeo = user_has_role('ceo');
                        $isLead = $task && (int) ($task['lead_id'] ?? 0) === (int) ($user['id'] ?? 0);
                        $isEmployee = user_has_role('employee');
                        if (!$isCeo && !$isLead && !$isEmployee) { set_flash('error', 'Only CEO, lead, or employee can update status.'); break; }
                        $oldLabel = $task['status'] ?? 'Unknown';
                        $newStatusId = (int) ($_POST['status_id'] ?? 0);
                        $newLabel = status_description_by_id($newStatusId) ?? '';
                        update_task_status($taskId, $newStatusId);
                        try { add_task_comment($taskId, sprintf('Status changed by %s (%s): %s -> %s', $user['name'] ?? 'Unknown', $user['role'] ?? 'employee', $oldLabel, $newLabel), $user ?? ['role' => 'system']); } catch (Throwable $e) {}
                        $ceo = ceo_contact();
                        $lead = $task && !empty($task['lead_id']) ? find_employee((int) $task['lead_id']) : null;
                        foreach (array_filter([$ceo, $lead]) as $recipient) { send_status_change_email($recipient, $task ?? ['id' => $taskId], $oldLabel, $newLabel, $user ?? []); }
                        $logLine = sprintf('[%s] Status change -> Task #%d "%s": %s -> %s by %s (%s)%s%s' . PHP_EOL, date('c'), $taskId, $task['title'] ?? '', $oldLabel, $newLabel, $user['name'] ?? 'Unknown', $user['role'] ?? 'employee', $lead ? ' | Lead: ' . ($lead['email'] ?? '') : '', $ceo ? ' | CEO: ' . ($ceo['email'] ?? '') : '');
                        ensure_alerts_log_rotation();
                        @file_put_contents(alerts_log_path(), $logLine, FILE_APPEND);
                        write_task_audit($taskId, 'status_update', $user, ['old' => $oldLabel, 'new' => $newLabel]);
                        set_flash('success', 'Status updated.');
                        break;

                    case 'delete_task':
                        if (!user_has_role('ceo')) { set_flash('error', 'Only CEO can delete tasks.'); break; }
                        $taskId = (int) ($_POST['task_id'] ?? 0);
                        delete_task($taskId);
                        write_task_audit($taskId, 'delete', $user, []);
                        set_flash('success', 'Task deleted.');
                        break;

                    case 'mark_notice':
                        $taskId = (int) ($_POST['task_id'] ?? 0);
                        $noticeDate = trim((string) ($_POST['notice_date'] ?? ''));
                        if ($noticeDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $noticeDate)) {
                            throw new InvalidArgumentException('Invalid notice date.');
                        }
                        mark_task_notice($taskId, $noticeDate !== '' ? $noticeDate : null);
                        set_flash('success', 'Notice date updated.');
                        break;

                    case 'update_alert':
                        if (!user_has_role('ceo')) { set_flash('error', 'Only CEO can set alerts.'); break; }
                        $taskId = (int) ($_POST['task_id'] ?? 0);
                        $alertDateRaw = trim((string) ($_POST['alert_date'] ?? ''));
                        $cfg = fetch_task_config($taskId);
                        if ($alertDateRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $alertDateRaw)) {
                            $cfg['alert'] = ['date' => $alertDateRaw];
                        } else {
                            unset($cfg['alert']);
                        }
                        save_task_config($taskId, $cfg);
                        $comment = trim((string) ($_POST['comment'] ?? ''));
                        if ($comment !== '') { add_task_comment($taskId, $comment, $user); }
                        write_task_audit($taskId, 'update_alert', $user, ['alert_date' => $alertDateRaw]);
                        set_flash('success', 'Alert updated.');
                        break;

                    case 'update_notice':
                        $taskId = (int) ($_POST['task_id'] ?? 0);
                        $task = find_task($taskId);
                        $isCeo = user_has_role('ceo');
                        $isLead = $task && (int) ($task['lead_id'] ?? 0) === (int) (current_user()['id'] ?? 0);
                        if (!$isCeo && !$isLead) { set_flash('error', 'Only CEO or lead can update notice.'); break; }
                        $noticeDate = trim((string) ($_POST['notice_date'] ?? ''));
                        $endDate = trim((string) ($_POST['due_per_notice'] ?? ''));
                        if ($noticeDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $noticeDate)) { set_flash('error', 'Invalid notice date.'); break; }
                        if ($endDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) { set_flash('error', 'Invalid end date.'); break; }
                        mark_task_notice($taskId, $noticeDate !== '' ? $noticeDate : null);
                        if ($endDate !== '') {
                            $stmt = db()->prepare('UPDATE task SET DUE_PER_NOTICE = :due, LAST_UPDATED = NOW() WHERE T_ID = :task');
                            $stmt->execute(['due' => $endDate, 'task' => $taskId]);
                        }
                        if (isset($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                            $upload = save_uploaded_file($_FILES['attachment'], 'tasks', ['pdf']);
                            $upload['uploaded_by'] = $user['name'];
                            add_task_attachment($taskId, $upload);
                            write_task_audit($taskId, 'upload_attachment', $user, ['name' => $upload['Original_Name'] ?? ($upload['name'] ?? ''), 'size' => (int) ($upload['File_Size'] ?? ($upload['size'] ?? 0))]);
                        }
                        $comment = trim((string) ($_POST['comment'] ?? ''));
                        if ($comment !== '') { add_task_comment($taskId, $comment, $user); }
                        write_task_audit($taskId, 'update_notice', $user, ['notice_date' => $noticeDate, 'due_per_notice' => $endDate]);
                        set_flash('success', 'Notice updated.');
                        break;

                    case '/license':
        require_roles(['ceo']);
        require __DIR__ . '/../src/views/license.php';
        break;

    default:
                        set_flash('error', 'Unsupported action.');
                }
            } catch (Throwable $exception) {
                set_flash('error', $exception->getMessage());
                error_log('[TASKS][' . $action . '] ' . $exception->getMessage() . ' at ' . $exception->getFile() . ':' . $exception->getLine());
                error_log('[TASKS][' . $action . '] ' . $exception->getTraceAsString());
                write_app_error('tasks', 'Task action failed', $exception, [
                    'action' => $action,
                    'task_id' => (int) ($_POST['task_id'] ?? 0),
                ]);
                if ($action === 'update_task') {
                    $taskId = (int) ($_POST['task_id'] ?? 0);
                    if ($taskId > 0) {
                        $redirectTarget = '/tasks?edit_task=' . $taskId;
                    }
                }
            }

            redirect($redirectTarget);
        }

        $remindersTriggered = trigger_due_reminders();
        $editTask = null;
        $editTaskId = (int) ($_GET['edit_task'] ?? 0);
        if ($editTaskId > 0) {
            $editTask = find_task($editTaskId);
        }
        $teamCategory = isset($_GET['team_category']) ? strtolower((string) $_GET['team_category']) : 'employee';
        if (!in_array($teamCategory, ['employee'], true)) { $teamCategory = 'employee'; }
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        invalidate_task_cache();
        $viewTaskId = (int) ($_GET['view_task'] ?? 0);
        render('tasks', [
            'title' => 'Task Board',
            'user' => $user,
            'tasks' => get_tasks(),
            'services' => get_services(),
            'clients' => get_clients(),
            'serviceCatalog' => get_service_catalog(),
            'leads' => get_employees('lead'),
            'teamMembers' => get_employees($teamCategory),
            'teamCategory' => $teamCategory,
            'statusOptions' => get_status_details(),
            'acknowledgedTasks' => task_acknowledgements_for_user($user['id'] ?? 0),
            'remindersFired' => $remindersTriggered,
            'flashError' => get_flash('error'),
            'flashSuccess' => get_flash('success'),
            'editTask' => $editTask,
            'editAlertId' => (int) ($_GET['edit_alert'] ?? 0),
            'noticeTaskId' => (int) ($_GET['notice_task'] ?? 0),
            'viewTaskId' => $viewTaskId,
        ]);
        break;

    case '/services':
        require_roles(['ceo']);
        if ($method === 'POST') {
            if (!validate_csrf($_POST['csrf_token'] ?? null)) {
                set_flash('error', 'Security token mismatch.');
                redirect('/services');
            }

            try {
                $clientId = (int) ($_POST['client_id'] ?? 0);
                $leadId = (int) ($_POST['lead_id'] ?? 0);
                $workStream = trim($_POST['work_stream'] ?? '');
                $statusId = (int) ($_POST['status_id'] ?? 0);
                $taskYearInput = trim((string) ($_POST['task_year'] ?? ''));
                if ($taskYearInput === '') {
                    throw new InvalidArgumentException('Select a task year.');
                }
                if (!preg_match('/^\d{4}$/', $taskYearInput)) {
                    throw new InvalidArgumentException('Enter a valid 4-digit year.');
                }
                $taskYear = (int) $taskYearInput;
                $dueDate = $_POST['due_date'] ?? null;
                $priority = $_POST['priority'] ?? 'Standard';
                $progress = (int) ($_POST['progress'] ?? 0);

                if (!$clientId || !$leadId || $workStream === '') {
                    throw new InvalidArgumentException('Client, lead, and work stream are required.');
                }

                add_service([
                    'client_id' => $clientId,
                    'lead_id' => $leadId,
                    'work_stream' => $workStream,
                    'status_id' => $statusId,
                    'task_year' => $taskYear,
                    'due_date' => $dueDate,
                    'priority' => $priority,
                    'progress' => $progress,
                ]);

                set_flash('success', 'Service engagement created.');
            } catch (Throwable $exception) {
                set_flash('error', $exception->getMessage());
            }

            redirect('/services');
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        invalidate_task_cache();
        render('services', [
            'title' => 'Service Assignments',
            'user' => current_user(),
            'services' => get_services(),
            'clients' => get_clients(),
            'leads' => get_employees('lead'),
            'statusOptions' => get_status_details(),
            'serviceCatalog' => get_service_catalog(),
            'flashError' => get_flash('error'),
            'flashSuccess' => get_flash('success'),
        ]);
        break;

    case '/service-types':
        require_roles(['ceo']);
        if ($method === 'POST') {
            if (!validate_csrf($_POST['csrf_token'] ?? null)) {
                set_flash('error', 'Security token mismatch.');
                redirect('/service-types');
            }

            $action = $_POST['action'] ?? 'create';
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            $name = trim($_POST['service_name'] ?? '');

            if ($action === 'delete') {
                if ($serviceId <= 0) {
                    set_flash('error', 'Unknown service selection.');
                } elseif (delete_service_type($serviceId)) {
                    write_audit('service_type', (int) $serviceId, 'delete', current_user(), []);
                    set_flash('success', 'Service type deleted.');
                } else {
                    set_flash('error', 'Could not delete service type. Please try again.');
                }
            } elseif ($action === 'update') {
                if ($serviceId <= 0) {
                    set_flash('error', 'Unknown service selection.');
                } elseif ($name === '') {
                    set_flash('error', 'Service name is required.');
                } elseif (update_service_type($serviceId, $name)) {
                    write_audit('service_type', (int) $serviceId, 'update', current_user(), ['name' => $name]);
                    set_flash('success', 'Service type updated.');
                } else {
                    set_flash('error', 'Could not update service type. Please try again.');
                }
            } else {
                if ($name === '') {
                    set_flash('error', 'Service name is required.');
                } elseif (in_array(strtolower($name), array_map('strtolower', get_service_catalog()), true)) {
                    set_flash('error', 'Service already exists.');
                } elseif (create_service_type($name)) {
                    write_audit('service_type', 0, 'create', current_user(), ['name' => $name]);
                    set_flash('success', 'Service type added.');
                } else {
                    set_flash('error', 'Could not add service type. Please try again.');
                }
            }

            $redirectTarget = $_POST['redirect'] ?? '/service-types';
            $redirectTarget = is_string($redirectTarget) && preg_match('#^/[A-Za-z0-9/_-]*$#', $redirectTarget)
                ? $redirectTarget
                : '/service-types';
            redirect($redirectTarget);
        }

        render('service-types', [
            'title' => 'Service Catalog',
            'serviceCatalog' => get_service_catalog(),
            'serviceCatalogRecords' => get_service_type_records(),
            'flashError' => get_flash('error'),
            'flashSuccess' => get_flash('success'),
        ]);
        break;

    case '/service-types.json':
        header('Content-Type: application/json');
        echo json_encode(get_service_catalog());
        exit;

    case '/entity-groups':
        require_roles(['ceo']);
        if ($method === 'POST') {
            if (!validate_csrf($_POST['csrf_token'] ?? null)) { set_flash('error', 'Security token mismatch.'); redirect('/entity-groups'); }
            $action = $_POST['action'] ?? 'create';
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($action === 'delete') {
                if ($id <= 0) { set_flash('error', 'Unknown selection.'); }
                elseif (delete_entity_group($id)) { set_flash('success', 'Entity group deleted.'); }
                else { set_flash('error', 'Could not delete entity group.'); }
            } elseif ($action === 'update') {
                if ($id <= 0 || $name === '') { set_flash('error', 'Name required.'); }
                elseif (update_entity_group($id, $name)) { set_flash('success', 'Entity group updated.'); }
                else { set_flash('error', 'Could not update entity group.'); }
            } else {
                if ($name === '') { set_flash('error', 'Name required.'); }
                elseif (create_entity_group($name)) { set_flash('success', 'Entity group added.'); }
                else { set_flash('error', 'Could not add entity group.'); }
            }
            redirect('/entity-groups');
        }
        render('entity-groups', [
            'title' => 'Entity Groups',
            'items' => get_entity_group_records(),
            'flashError' => get_flash('error'),
            'flashSuccess' => get_flash('success'),
        ]);
        break;

    case '/entity-groups.json':
        header('Content-Type: application/json');
        echo json_encode(get_entity_group_catalog());
        exit;

    case '/entity-subtypes':
        require_roles(['ceo']);
        if ($method === 'POST') {
            if (!validate_csrf($_POST['csrf_token'] ?? null)) { set_flash('error', 'Security token mismatch.'); redirect('/entity-subtypes'); }
            $action = $_POST['action'] ?? 'create';
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($action === 'delete') {
                if ($id <= 0) { set_flash('error', 'Unknown selection.'); }
                elseif (delete_entity_subtype($id)) { set_flash('success', 'Entity subtype deleted.'); }
                else { set_flash('error', 'Could not delete entity subtype.'); }
            } elseif ($action === 'update') {
                if ($id <= 0 || $name === '') { set_flash('error', 'Name required.'); }
                elseif (update_entity_subtype($id, $name)) { set_flash('success', 'Entity subtype updated.'); }
                else { set_flash('error', 'Could not update entity subtype.'); }
            } else {
                if ($name === '') { set_flash('error', 'Name required.'); }
                elseif (create_entity_subtype($name)) { set_flash('success', 'Entity subtype added.'); }
                else { set_flash('error', 'Could not add entity subtype.'); }
            }
            redirect('/entity-subtypes');
        }
        render('entity-subtypes', [
            'title' => 'Entity Subtypes',
            'items' => get_entity_subtype_records(),
            'flashError' => get_flash('error'),
            'flashSuccess' => get_flash('success'),
        ]);
        break;

    case '/entity-subtypes.json':
        header('Content-Type: application/json');
        echo json_encode(get_entity_subtype_catalog());
        exit;

    case '/clients.json':
        header('Content-Type: application/json');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));
        $clients = get_clients();
        // Compute next due date per client from tasks
        $tasks = get_tasks();
        $nextDue = [];
        foreach ($tasks as $t) {
            $cid = (string) ($t['client_id'] ?? '');
            $due = (string) ($t['due_date'] ?? '');
            if ($cid === '' || $due === '') { continue; }
            if (!isset($nextDue[$cid]) || strcmp($due, $nextDue[$cid]) < 0) { $nextDue[$cid] = $due; }
        }
        foreach ($clients as &$c) {
            $cid = (string) ($c['c_id'] ?? '');
            $c['next_due_date'] = $nextDue[$cid] ?? null;
        }
        unset($c);
        $total = count($clients);
        $offset = ($page - 1) * $limit;
        $slice = array_slice($clients, $offset, $limit);
        $next = ($offset + $limit < $total) ? ($page + 1) : null;
        echo json_encode(['items' => $slice, 'next_page' => $next, 'total' => $total], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;

    case '/clients_dup.json':
        header('Content-Type: application/json');
        $uid = (int) (current_user()['id'] ?? 0);
        $items = client_dup_store_list($uid);
        echo json_encode(['count' => count($items), 'items' => array_map(static function($x){ return [
            'c_id' => (string) ($x['c_id'] ?? ''),
            'name' => (string) ($x['name'] ?? ''),
            'pan' => (string) ($x['pan'] ?? ''),
            'tan' => (string) ($x['tan'] ?? ''),
            'diff' => (array) ($x['diff'] ?? []),
            'services_add' => (array) ($x['services_add'] ?? []),
        ]; }, $items)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;

    case '/client-events':
        require_roles(['ceo','lead']);
        if ($method === 'POST') {
            if (!validate_csrf($_POST['csrf_token'] ?? null)) { set_flash('error', 'Security token mismatch.'); redirect('/clients'); }
            $action = $_POST['action'] ?? 'create';
            $clientId = trim((string)($_POST['client_id'] ?? ''));
            if ($clientId === '' || !find_client($clientId)) { set_flash('error','Unknown client.'); redirect('/clients'); }
            if ($action === 'delete') {
                $eid = (int)($_POST['event_id'] ?? 0);
                if ($eid <= 0 || !delete_client_event($eid)) { set_flash('error','Could not delete event.'); }
                else { set_flash('success','Event deleted.'); }
            } elseif ($action === 'update') {
                $eid = (int)($_POST['event_id'] ?? 0);
                $data = ['name'=>$_POST['name'] ?? '', 'date'=>$_POST['date'] ?? '', 'status'=>$_POST['status'] ?? '', 'notes'=>$_POST['notes'] ?? ''];
                if ($eid <= 0 || trim((string)$data['name'])==='') { set_flash('error','Name required.'); }
                elseif (!update_client_event($eid, $data)) { set_flash('error','Could not update event.'); }
                else { set_flash('success','Event updated.'); }
            } else {
                $data = ['name'=>$_POST['name'] ?? '', 'date'=>$_POST['date'] ?? '', 'status'=>$_POST['status'] ?? '', 'notes'=>$_POST['notes'] ?? ''];
                if (trim((string)$data['name'])==='') { set_flash('error','Name required.'); }
                elseif (!create_client_event($clientId, $data)) { set_flash('error','Could not create event.'); }
                else { set_flash('success','Event added.'); }
            }
            $_GET['client_id'] = $clientId; // for redirect view
        }
        $cid = trim((string)($_GET['client_id'] ?? ''));
        $client = $cid !== '' ? find_client($cid) : null;
        if (!$client) { set_flash('error','Unknown client.'); redirect('/clients'); }
        render('client-events', [
            'title' => 'Client Events',
            'clientId' => $cid,
            'client' => $client,
            'events' => get_client_events($cid),
            'flashError' => get_flash('error'),
            'flashSuccess' => get_flash('success'),
        ]);
        break;

    case '/staff.json':
        header('Content-Type: application/json');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));
        $employees = get_employees();
        $total = count($employees);
        $offset = ($page - 1) * $limit;
        $slice = array_slice($employees, $offset, $limit);
        $next = ($offset + $limit < $total) ? ($page + 1) : null;
        echo json_encode(['items' => $slice, 'next_page' => $next, 'total' => $total], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;

    case '/tasks.json':
        header('Content-Type: application/json');
        try {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));
            $offset = ($page - 1) * $limit;
            $pageItems = get_tasks_page($limit, $offset);
            $clientMap = [];
            foreach (get_clients() as $c) { $clientMap[(string)($c['c_id'] ?? '')] = $c['name'] ?? ''; }
            $employees = get_employees();
            $leadMap = [];
            foreach ($employees as $e) { $leadMap[(int)($e['id'] ?? 0)] = $e['name'] ?? ''; }
            $slice = array_map(static function(array $t) use ($clientMap, $leadMap): array {
                $t['client_name'] = $clientMap[(string)($t['client_id'] ?? '')] ?? '';
                $t['lead_name'] = $leadMap[(int)($t['lead_id'] ?? 0)] ?? '';
                return $t;
            }, $pageItems);
            $next = (count($slice) === $limit) ? ($page + 1) : null;
            echo json_encode(['items' => $slice, 'next_page' => $next], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $exception) {
            write_app_error('tasks', 'Tasks JSON failed', $exception);
            http_response_code(500);
            echo json_encode(['error' => 'Task data unavailable'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        exit;

    case '/dashboard.json':
        header('Content-Type: application/json');
        try {
            $clients = get_clients();
            $clientMap = [];
            foreach ($clients as $c) { $clientMap[(string)($c['c_id'] ?? '')] = $c['name'] ?? ''; }
            $employees = get_employees();
            $leadMap = [];
            foreach ($employees as $e) { $leadMap[(int)($e['id'] ?? 0)] = $e['name'] ?? ''; }
            $statusSummary = task_status_summary();
            $upcoming = array_map(static function(array $t) use ($clientMap, $leadMap): array {
                $t['client_name'] = $clientMap[(string)($t['client_id'] ?? '')] ?? '';
                $t['lead_name'] = $leadMap[(int)($t['lead_id'] ?? 0)] ?? '';
                return $t;
            }, tasks_due_within(7));
            $overdue = array_map(static function(array $t) use ($clientMap, $leadMap): array {
                $t['client_name'] = $clientMap[(string)($t['client_id'] ?? '')] ?? '';
                $t['lead_name'] = $leadMap[(int)($t['lead_id'] ?? 0)] ?? '';
                return $t;
            }, tasks_overdue());
            echo json_encode([
                'statusSummary' => $statusSummary,
                'upcoming' => $upcoming,
                'overdue' => $overdue,
                'clients' => $clients,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $exception) {
            write_app_error('dashboard', 'Dashboard JSON failed', $exception);
            http_response_code(500);
            echo json_encode(['error' => 'Dashboard data unavailable'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        exit;

    case '/statuses':
        require_roles(['ceo']);
        if ($method === 'POST') {
            if (!validate_csrf($_POST['csrf_token'] ?? null)) {
                set_flash('error', 'Security token mismatch.');
                redirect('/statuses');
            }

            $action = $_POST['action'] ?? 'create';
            $statusId = (int) ($_POST['status_id'] ?? 0);
            $description = trim((string) ($_POST['description'] ?? ''));

            try {
                if ($action === 'delete') {
                    if (delete_status_detail($statusId)) {
                        write_audit('status', (int) $statusId, 'delete', current_user(), []);
                        set_flash('success', 'Status removed.');
                    } else {
                        set_flash('error', 'Unable to delete status.');
                    }
                } elseif ($action === 'update') {
                    if (update_status_detail($statusId, $description)) {
                        write_audit('status', (int) $statusId, 'update', current_user(), ['description' => $description]);
                        set_flash('success', 'Status updated.');
                    } else {
                        set_flash('error', 'Unable to update status.');
                    }
                } else {
                    create_status_detail($description);
                    write_audit('status', 0, 'create', current_user(), ['description' => $description]);
                    set_flash('success', 'Status added.');
                }
            } catch (Throwable $exception) {
                set_flash('error', $exception->getMessage());
            }

            redirect('/statuses');
        }

        render('statuses', [
            'title' => 'Task Statuses',
            'statusRecords' => get_status_details(),
            'flashError' => get_flash('error'),
            'flashSuccess' => get_flash('success'),
        ]);
        break;

    case '/reminders/ack':
        require_auth();
        if ($method !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed';
            break;
        }

        if (!validate_csrf($_POST['csrf_token'] ?? null)) {
            set_flash('error', 'Security token mismatch.');
            redirect($_POST['redirect'] ?? '/tasks');
        }

        $taskId = (int) ($_POST['task_id'] ?? 0);
        if ($taskId <= 0) {
            set_flash('error', 'Unknown task.');
            redirect($_POST['redirect'] ?? '/tasks');
        }

        acknowledge_task_reminder($taskId, current_user()['id']);
        set_flash('success', 'Reminder acknowledged for today.');
        redirect($_POST['redirect'] ?? '/tasks');

    case '/clients':
        require_roles(['ceo', 'lead', 'employee']);
        if (user_has_role('lead') && !user_has_permission('clients', 'read')) {
            set_flash('error', 'Client access requires CEO approval.');
            redirect('/dashboard');
        }
        if ($method === 'GET' && strtolower((string)($_GET['bulk_template'] ?? '')) === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="clients_template.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name','client_code','entity_type','entity_subtype','address1','address2','city','state','pin','primary_contact','contact_email','ceo_name','ceo_email','pan','tan','gst','aadhaar','incorporated_on','pco_phone','services'], ',', '"', '\\');
            fputcsv($out, ['Acme Manufacturing Pvt Ltd','ACME001','Manufacturing','Automobile Components','Plot 12, SIPCOT','Phase 2','Chennai','Tamil Nadu','600001','Ravi Kumar','pco@acmemfg.in','Meera Iyer','ceo@acmemfg.in','ABCDE1234F','ABCDE1234F','33ABCDE1234F1ZX','1234 5678 9012','2012-08-15','+91 98765 43210','GST filing|Accounting|Tax Audit'], ',', '"', '\\');
            fputcsv($out, ['Zen Tech Solutions LLP','ZENLLP01','IT Services','Consulting','#45 MG Road','Suite 803','Bengaluru','Karnataka','560001','Anil Shah','contact@zentechllp.com','Priya Nair','ceo@zentechllp.com','PQRSX6789L','PQRSX6789L','29PQRSX6789L1XZ','4321 8765 2109','2018-03-01','+91-99887 66554','Consultation|Internal Audit|Risk Advisory'], ',', '"', '\\');
            fclose($out);
            exit;
        }
        if ($method === 'POST') {
            if (!validate_csrf($_POST['csrf_token'] ?? null)) {
                set_flash('error', 'Security token mismatch.');
                redirect('/clients');
            }

            $action = $_POST['action'] ?? 'update_services';

            if ($action === 'create_client') {
                if (!user_has_role('ceo')) {
                    set_flash('error', 'Only CEO can create clients.');
                    redirect('/clients');
                }
                $clientData = [
                    'name' => trim($_POST['name'] ?? ''),
                    'client_code' => substr(trim((string) ($_POST['client_code'] ?? '')), 0, 16),
                    'entity_type' => trim($_POST['entity_type'] ?? ''),
                    'entity_subtype' => trim($_POST['entity_subtype'] ?? ''),
                    'address1' => trim($_POST['address1'] ?? ''),
                    'address2' => trim($_POST['address2'] ?? ''),
                    'city' => trim($_POST['city'] ?? ''),
                    'state' => trim($_POST['state'] ?? ''),
                    'pin' => trim($_POST['pin'] ?? ''),
                    'primary_contact' => trim($_POST['primary_contact'] ?? ''),
                    'contact_email' => trim($_POST['contact_email'] ?? ''),
                    'ceo_name' => trim($_POST['ceo_name'] ?? ''),
                    'ceo_email' => trim($_POST['ceo_email'] ?? ''),
                    'pan' => trim($_POST['pan'] ?? ''),
                    'tan' => trim($_POST['tan'] ?? ''),
                    'gst' => trim($_POST['gst'] ?? ''),
                    'aadhaar' => trim($_POST['aadhaar'] ?? ''),
                    'incorporated_on' => trim($_POST['incorporated_on'] ?? ''),
                    'pco_phone' => trim($_POST['pco_phone'] ?? ''),
                    'login_id' => (string) (current_user()['email'] ?? ''),
                ];
                $services = array_values(array_filter((array) ($_POST['services'] ?? [])));

                $errors = [];
                if ($clientData['name'] === '') {
                    $errors['name'] = 'Client name is required.';
                }
                if ($clientData['entity_type'] === '') {
                    $errors['entity_type'] = 'Entity type is required.';
                }
                if ($clientData['address1'] === '') { $errors['address1'] = 'Address line 1 is required.'; }
                if ($clientData['address2'] === '') { $errors['address2'] = 'Address line 2 is required.'; }
                if ($clientData['city'] === '') { $errors['city'] = 'City is required.'; }
                if ($clientData['state'] === '') { $errors['state'] = 'State is required.'; }
                if ($clientData['pin'] === '') { $errors['pin'] = 'PIN is required.'; }
                if ($clientData['primary_contact'] === '') { $errors['primary_contact'] = 'POC name is required.'; }
                if ($clientData['contact_email'] === '' || !filter_var($clientData['contact_email'], FILTER_VALIDATE_EMAIL)) { $errors['contact_email'] = 'Valid PCO email is required.'; }
                if ($clientData['pan'] === '' || !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $clientData['pan'])) { $errors['pan'] = 'PAN must match AAAAA1111A.'; }
                if ($clientData['tan'] === '' || !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $clientData['tan'])) { $errors['tan'] = 'TAN must match AAAAA1111A.'; }
                if ($clientData['gst'] === '' || !preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z]\d[A-Z]{2}$/', $clientData['gst'])) { $errors['gst'] = 'GST must match 11AAAAA111A1AA.'; }
                if ($clientData['aadhaar'] === '' || !preg_match('/^\d{4} \d{4} \d{4}$/', $clientData['aadhaar'])) { $errors['aadhaar'] = 'Aadhaar must match 1111 1111 1111.'; }
                if ($clientData['incorporated_on'] === '' || !DateTime::createFromFormat('Y-m-d', $clientData['incorporated_on'])) {
                    $errors['incorporated_on'] = 'Valid incorporation date is required.';
                }
                if ($clientData['pco_phone'] === '' || !preg_match('/^[0-9+\-\s]{7,15}$/', $clientData['pco_phone'])) { $errors['pco_phone'] = 'Enter a valid PCO number.'; }

                $nm = trim((string) $clientData['name']);
                $_first = '';
                for ($i = 0; $i < strlen($nm); $i++) { $ch = $nm[$i]; if (preg_match('/[A-Za-z0-9]/', $ch)) { $_first = strtoupper($ch); break; } }
                if ($_first === '') { $_first = 'X'; }
                if ($clientData['client_code'] !== '') {
                    if (!preg_match('/^' . preg_quote($_first, '/') . '\\d{4}$/', $clientData['client_code'])) {
                        $errors['client_code'] = 'Client ID must be ' . $_first . ' followed by 4 digits.';
                    } else {
                        try {
                            $pdo = db();
                            $stmt = $pdo->prepare('SELECT 1 FROM client WHERE Client_Code = :code');
                            $stmt->execute(['code' => $clientData['client_code']]);
                            if ($stmt->fetchColumn()) { $errors['client_code'] = 'Client ID already exists.'; }
                        } catch (Throwable $e) { /* ignore */ }
                    }
                }

                if (empty($services)) { $errors['services'] = 'Select initial services.'; }

                if (!empty($errors)) {
                    $_SESSION['client_form'] = [
                        'data' => $clientData,
                        'errors' => $errors,
                        'services' => $services,
                    ];
                    set_flash('error', 'Please fix the highlighted fields.');
                    redirect('/clients');
                }

                foreach ($services as $svc) { create_service_type($svc); }
                $clientId = create_client_record($clientData, $services);
                if ($clientId) {
                    unset($_SESSION['client_form']);
                    $GLOBALS['CLIENT_CACHE_VERSION'] = microtime(true);
                    $GLOBALS['TASK_CACHE_VERSION'] = microtime(true);
                    write_audit('client', 0, 'create', current_user(), ['c_id' => $clientId, 'data' => $clientData, 'services' => $services]);
                    set_flash('success', 'Client created successfully.');
                } else {
                    $_SESSION['client_form'] = [
                        'data' => $clientData,
                        'errors' => [],
                        'services' => $services,
                    ];
                    set_flash('error', 'Could not create client. Please try again.');
                }

                redirect('/clients');
            }

            if ($action === 'bulk_upload_clients') {
                if (!user_has_role('ceo')) {
                    if ((string)($_POST['ajax'] ?? '') === '1') {
                        header('Content-Type: application/json');
                        echo json_encode(['ok' => 0, 'updated' => 0, 'fail' => 1, 'message' => 'Only CEO can bulk upload clients.', 'dup_items' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        exit;
                    }
                    set_flash('error', 'Only CEO can bulk upload clients.');
                    redirect('/clients');
                }
                if (!isset($_FILES['bulk_file']) || ($_FILES['bulk_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    if ((string)($_POST['ajax'] ?? '') === '1') {
                        header('Content-Type: application/json');
                        echo json_encode(['ok' => 0, 'updated' => 0, 'fail' => 1, 'message' => 'Select a CSV or XLSX file.', 'dup_items' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        exit;
                    }
                    set_flash('error', 'Select a CSV or XLSX file.');
                    redirect('/clients');
                }
                try {
                    $upload = save_uploaded_file($_FILES['bulk_file'], 'clients', ['csv','xlsx'], 33554432);
                    $fullPath = realpath(__DIR__ . '/../' . $upload['path']);
                    if (!$fullPath || !is_file($fullPath)) { throw new RuntimeException('Upload missing on server.'); }
                    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                    $ok = 0; $updated = 0; $fail = 0; $failMsg = '';
                    if ($ext === 'csv') {
                        $strategy = strtolower(trim((string)($_POST['dup_strategy'] ?? 'skip')));
                        if (!in_array($strategy, ['skip','overwrite'], true)) { $strategy = 'skip'; }
                        $res = bulk_import_clients_csv($fullPath, current_user(), $strategy);
                        $ok += (int) ($res['ok'] ?? 0);
                        $updated += (int) ($res['updated'] ?? 0);
                        $fail += (int) ($res['fail'] ?? 0);
                        if (!empty($res['errors']) && $failMsg === '') { $failMsg = (string) $res['errors'][0]; }
                        if ((string)($_POST['ajax'] ?? '') === '1') {
                            $uid = (int) (current_user()['id'] ?? 0);
                            $items = array_map(static function($x){ return [
                                'c_id' => (string) ($x['c_id'] ?? ''),
                                'name' => (string) ($x['name'] ?? ''),
                                'pan' => (string) ($x['pan'] ?? ''),
                                'tan' => (string) ($x['tan'] ?? ''),
                            ]; }, client_dup_store_list($uid));
                            header('Content-Type: application/json');
                            echo json_encode(['ok' => $ok, 'updated' => $updated, 'fail' => $fail, 'message' => $failMsg, 'dup_items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            exit;
                        }
                    } elseif ($ext === 'xlsx') {
                        if (!class_exists('ZipArchive')) { throw new RuntimeException('XLSX not supported on server. Upload CSV.'); }
                        write_staff_bulk_log('XLSX start ' . $fullPath);
                        $zip = new ZipArchive();
                        if ($zip->open($fullPath) !== true) { throw new RuntimeException('Unable to open XLSX.'); }
                        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
                        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
                        $shared = [];
                        if ($sharedXml) { $sx = @simplexml_load_string($sharedXml); if ($sx) { foreach ($sx->si as $si) { $shared[] = (string) ($si->t ?? ''); } } }
                        $s1 = @simplexml_load_string($sheetXml);
                        if (!$s1) { throw new RuntimeException('Invalid XLSX sheet.'); }
                        $header = null;
                        foreach ($s1->sheetData->row as $r) {
                            $rowVals = [];
                            foreach ($r->c as $c) {
                                $t = (string) ($c['t'] ?? '');
                                $v = '';
                                if ($t === 's') { $idx = (int) ($c->v ?? -1); $v = $idx >= 0 && isset($shared[$idx]) ? $shared[$idx] : ''; }
                                else { $v = (string) ($c->v ?? ''); }
                                $rowVals[] = trim($v);
                            }
                            if ($header === null) { $header = array_map(static fn($h) => strtolower(trim((string)$h)), $rowVals); continue; }
                            $row = [];
                            for ($i=0; $i<count($header); $i++) { $row[$header[$i] ?? ('col'.$i)] = trim((string)($rowVals[$i] ?? '')); }
                            if (empty(array_filter($row))) { continue; }
                            $clientData = [
                                'name' => $row['name'] ?? '',
                                'client_code' => $row['client_code'] ?? '',
                                'entity_type' => $row['entity_type'] ?? '',
                                'entity_subtype' => $row['entity_subtype'] ?? '',
                                'address1' => $row['address1'] ?? '',
                                'address2' => $row['address2'] ?? '',
                                'city' => $row['city'] ?? '',
                                'state' => $row['state'] ?? '',
                                'pin' => $row['pin'] ?? '',
                                'primary_contact' => $row['primary_contact'] ?? '',
                                'contact_email' => $row['contact_email'] ?? '',
                                'ceo_name' => $row['ceo_name'] ?? '',
                                'ceo_email' => $row['ceo_email'] ?? '',
                                'pan' => $row['pan'] ?? '',
                                'tan' => $row['tan'] ?? '',
                                'gst' => $row['gst'] ?? '',
                                'aadhaar' => $row['aadhaar'] ?? '',
                                'incorporated_on' => $row['incorporated_on'] ?? '',
                                'pco_phone' => $row['pco_phone'] ?? '',
                            ];
                        $servicesRaw = (string) ($row['services'] ?? '');
                        $services = array_values(array_unique(array_filter(array_map('trim', explode('|', $servicesRaw)), static function($s){
                            $x = strtolower($s);
                            return $x !== '' && !in_array($x, ['na','n/a','null','none','-'], true);
                        })));
                            $errs = [];
                            if ($clientData['client_code'] === '') { $errs['client_code'] = 1; }
                            if ($clientData['name'] === '') { $errs['name'] = 1; }
                            if ($clientData['entity_type'] === '') { $errs['entity_type'] = 1; }
                            if ($clientData['address1'] === '') { $errs['address1'] = 1; }
                            if ($clientData['address2'] === '') { $errs['address2'] = 1; }
                            if ($clientData['city'] === '') { $errs['city'] = 1; }
                            if ($clientData['state'] === '') { $errs['state'] = 1; }
                            if ($clientData['pin'] === '') { $errs['pin'] = 1; }
                            if ($clientData['primary_contact'] === '') { $errs['primary_contact'] = 1; }
                            if ($clientData['contact_email'] === '' || !filter_var($clientData['contact_email'], FILTER_VALIDATE_EMAIL)) { $errs['contact_email'] = 1; }
                            if ($clientData['ceo_email'] !== '' && !filter_var($clientData['ceo_email'], FILTER_VALIDATE_EMAIL)) { $errs['ceo_email'] = 1; }
                            if (empty($services)) { $errs['services'] = 1; }
                            if (!empty($errs)) { $fail++; if ($failMsg==='') { $failMsg = 'Invalid row for client_code ' . ($clientData['client_code'] ?: $clientData['name']); } continue; }
                            foreach ($services as $svc) { create_service_type($svc); }
                            // sanitize incorporated_on tokens like NA
                            $inc = (string) ($clientData['incorporated_on'] ?? '');
                            $incTrim = strtolower(trim($inc));
                            if (in_array($incTrim, ['na','n/a','null','none','-'], true)) { $clientData['incorporated_on'] = ''; }
                            $existing = find_client((string) ($clientData['client_code'] ?? ''));
                            if ($existing) {
                                // update existing record partially and add only missing services
                                update_client_record_partial((string) ($clientData['client_code'] ?? ''), $clientData);
                                $pdo = db();
                                $map = $pdo->prepare(client_service_map_insert_sql());
                                foreach ($services as $svc) { $map->execute(['client' => (string) ($clientData['client_code'] ?? ''), 'service' => $svc]); }
                                $updated++;
                                client_dup_store_add((int) (current_user()['id'] ?? 0), [
                                    'c_id' => (string) ($clientData['client_code'] ?? ''),
                                    'name' => (string) ($clientData['name'] ?? ''),
                                    'pan' => (string) ($clientData['pan'] ?? ''),
                                    'tan' => (string) ($clientData['tan'] ?? ''),
                                ]);
                                write_audit('client', 0, 'bulk_update_from_xlsx', current_user(), ['c_id' => (string) ($clientData['client_code'] ?? '')]);
                            } else {
                                $clientData['login_id'] = (string) (current_user()['email'] ?? ($clientData['login_id'] ?? ''));
                                $cid = create_client_record($clientData, $services);
                                if ($cid) { $ok++; write_audit('client', 0, 'create', current_user(), ['c_id' => $cid, 'data' => $clientData, 'services' => $services]); }
                                else { $fail++; if ($failMsg==='') { $failMsg = 'Failed to create client ' . ($clientData['client_code'] ?: $clientData['name']); } }
                            }
                        }
                        $zip->close();
                        if ((string)($_POST['ajax'] ?? '') === '1') {
                            $uid = (int) (current_user()['id'] ?? 0);
                            $items = array_map(static function($x){ return [
                                'c_id' => (string) ($x['c_id'] ?? ''),
                                'name' => (string) ($x['name'] ?? ''),
                                'pan' => (string) ($x['pan'] ?? ''),
                                'tan' => (string) ($x['tan'] ?? ''),
                            ]; }, client_dup_store_list($uid));
                            header('Content-Type: application/json');
                            echo json_encode(['ok' => $ok, 'updated' => $updated, 'fail' => $fail, 'message' => $failMsg, 'dup_items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            exit;
                        }
                    } else {
                        throw new RuntimeException('Unsupported file type.');
                    }
                    if ($ok > 0) {
                        $GLOBALS['CLIENT_CACHE_VERSION'] = microtime(true);
                        $GLOBALS['TASK_CACHE_VERSION'] = microtime(true);
                    }
                    if ($fail > 0 && $ok === 0 && ($updated === 0)) {
                        set_flash('error', 'Bulk upload failed. ' . $failMsg);
                    } else {
                        $msg = 'Bulk upload complete. Imported: ' . $ok . '. Updated: ' . $updated . '. Failed: ' . $fail . '.';
                        if ($failMsg !== '') { $msg .= ' ' . $failMsg; }
                        set_flash('success', $msg);
                    }
                } catch (Throwable $e) {
                    if ((string)($_POST['ajax'] ?? '') === '1') {
                        $uid = (int) (current_user()['id'] ?? 0);
                        $items = array_map(static function($x){ return [
                            'c_id' => (string) ($x['c_id'] ?? ''),
                            'name' => (string) ($x['name'] ?? ''),
                            'pan' => (string) ($x['pan'] ?? ''),
                            'tan' => (string) ($x['tan'] ?? ''),
                        ]; }, client_dup_store_list($uid));
                        header('Content-Type: application/json');
                        echo json_encode(['ok' => 0, 'updated' => 0, 'fail' => 1, 'message' => $e->getMessage(), 'dup_items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        exit;
                    }
                    set_flash('error', $e->getMessage());
                    redirect('/clients');
                }
                redirect('/clients');
            }

            if ($action === 'update_services') {
                if (!user_has_permission('clients', 'write')) {
                    set_flash('error', 'You do not have permission to update services.');
                    redirect('/clients');
                }
                $clientId = trim((string) ($_POST['client_id'] ?? ''));
                $services = array_values(array_filter((array) ($_POST['services'] ?? [])));

                if ($clientId === '') {
                    set_flash('error', 'Unknown client.');
                } elseif (update_client_services($clientId, $services)) {
                    $GLOBALS['CLIENT_CACHE_VERSION'] = microtime(true);
                    $GLOBALS['TASK_CACHE_VERSION'] = microtime(true);
                    write_audit('client', 0, 'update_services', current_user(), ['c_id' => $clientId, 'services' => $services]);
                    set_flash('success', 'Services updated.');
                } else {
                    set_flash('error', 'Could not update client.');
                }
            } elseif ($action === 'delete_client') {
                if (!user_has_role('ceo')) {
                    set_flash('error', 'Only CEO can delete clients.');
                } else {
                    $clientId = trim((string) ($_POST['client_id'] ?? ''));
                    if ($clientId === '') {
                        set_flash('error', 'Unknown client.');
                    } elseif (delete_client($clientId)) {
                        $GLOBALS['CLIENT_CACHE_VERSION'] = microtime(true);
                        $GLOBALS['TASK_CACHE_VERSION'] = microtime(true);
                        write_audit('client', 0, 'delete', current_user(), ['c_id' => $clientId]);
                        set_flash('success', 'Client deleted.');
                    } else {
                        set_flash('error', 'Could not delete client. Remove related tasks first.');
                    }
                }
            } elseif ($action === 'delete_all_clients') {
                if (!user_has_role('ceo')) {
                    set_flash('error', 'Only CEO can delete all clients.');
                } else {
                    if (delete_all_clients()) {
                        $GLOBALS['CLIENT_CACHE_VERSION'] = microtime(true);
                        $GLOBALS['TASK_CACHE_VERSION'] = microtime(true);
                        set_flash('success', 'All clients deleted.');
                    } else {
                        set_flash('error', 'Could not delete all clients.');
                    }
                }
                redirect('/clients');
            } elseif ($action === 'bulk_update_duplicates') {
                if (!user_has_role(['ceo','lead'])) { set_flash('error', 'Unauthorized'); redirect('/clients'); }
                $uid = (int) (current_user()['id'] ?? 0);
                $items = client_dup_store_list($uid);
                $ok=0; $fail=0;
                foreach ($items as $dup) {
                    $cid = (string) ($dup['c_id'] ?? '');
                    $data = (array) ($dup['data'] ?? []);
                    $services = (array) ($dup['services'] ?? []);
                    if ($cid === '') { $fail++; continue; }
                    try {
                        update_client_record_partial($cid, $data);
                        $pdo = db();
                        // add only missing services, sanitize invalid tokens
                        $services = array_values(array_unique(array_filter(array_map('trim', $services), static function($s){
                            $x = strtolower($s);
                            return $x !== '' && !in_array($x, ['na','n/a','null','none','-'], true);
                        })));
                        $map = $pdo->prepare(client_service_map_insert_sql());
                        foreach ($services as $svc) { $map->execute(['client' => $cid, 'service' => $svc]); }
                        write_audit('client', 0, 'bulk_update_from_temp', current_user(), ['c_id' => $cid]);
                        $ok++;
                    } catch (Throwable $e) { $fail++; }
                }
                client_dup_store_clear($uid);
                set_flash('success', 'Updated duplicates: ' . $ok . '. Failed: ' . $fail . '.');
                redirect('/clients');
            } elseif ($action === 'bulk_clear_duplicates') {
                $uid = (int) (current_user()['id'] ?? 0);
                client_dup_store_clear($uid);
                set_flash('success', 'Dismissed duplicate list.');
                redirect('/clients');
            } elseif ($action === 'edit_client_start') {
                if (!user_has_role(['ceo','lead'])) {
                    set_flash('error', 'Only CEO or Lead can edit clients.');
                    redirect('/clients');
                }
                $clientId = trim((string) ($_POST['client_id'] ?? ''));
                $client = $clientId !== '' ? find_client($clientId) : null;
                if (!$client) {
                    set_flash('error', 'Unknown client.');
                } else {
                    $_SESSION['client_form'] = [
                        'data' => [
                            'edit_id' => $clientId,
                            'name' => $client['name'] ?? '',
                            'client_code' => $client['code'] ?? '',
                            'entity_type' => $client['entity_type'] ?? '',
                            'entity_subtype' => $client['entity_subtype'] ?? '',
                            'address1' => $client['address']['line1'] ?? '',
                            'address2' => $client['address']['line2'] ?? '',
                            'city' => $client['address']['city'] ?? '',
                            'state' => $client['address']['state'] ?? '',
                            'pin' => $client['address']['pin'] ?? '',
                            'primary_contact' => $client['poc_name'] ?? '',
                            'contact_email' => $client['poc_email'] ?? '',
                            'ceo_name' => $client['ceo_name'] ?? '',
                            'ceo_email' => $client['ceo_email'] ?? '',
                            'pan' => $client['pan'] ?? '',
                            'tan' => $client['tan'] ?? '',
                            'gst' => $client['gst'] ?? '',
                            'aadhaar' => $client['aadhaar'] ?? '',
                            'incorporated_on' => $client['incorporated_on'] ?? '',
                            'pco_phone' => $client['poc_phone'] ?? '',
                        ],
                        'errors' => [],
                        'services' => $client['services'] ?? [],
                    ];
                }
            } elseif ($action === 'update_client') {
                if (!user_has_role(['ceo','lead'])) {
                    set_flash('error', 'Only CEO or Lead can update clients.');
                    redirect('/clients');
                }
                $clientId = trim((string) ($_POST['client_id'] ?? ''));
                $clientData = [
                    'name' => trim($_POST['name'] ?? ''),
                    'entity_type' => trim($_POST['entity_type'] ?? ''),
                    'entity_subtype' => trim($_POST['entity_subtype'] ?? ''),
                    'address1' => trim($_POST['address1'] ?? ''),
                    'address2' => trim($_POST['address2'] ?? ''),
                    'city' => trim($_POST['city'] ?? ''),
                    'state' => trim($_POST['state'] ?? ''),
                    'pin' => trim($_POST['pin'] ?? ''),
                    'primary_contact' => trim($_POST['primary_contact'] ?? ''),
                    'contact_email' => trim($_POST['contact_email'] ?? ''),
                    'ceo_name' => trim($_POST['ceo_name'] ?? ''),
                    'ceo_email' => trim($_POST['ceo_email'] ?? ''),
                    'pan' => trim($_POST['pan'] ?? ''),
                    'tan' => trim($_POST['tan'] ?? ''),
                    'gst' => trim($_POST['gst'] ?? ''),
                    'aadhaar' => trim($_POST['aadhaar'] ?? ''),
                    'incorporated_on' => trim($_POST['incorporated_on'] ?? ''),
                    'pco_phone' => trim($_POST['pco_phone'] ?? ''),
                ];
                $errors = [];
                if ($clientData['name'] === '') { $errors['name'] = 'Client name is required.'; }
                if ($clientData['entity_type'] === '') { $errors['entity_type'] = 'Industry is required.'; }
                if ($clientData['address1'] === '') { $errors['address1'] = 'Address line 1 is required.'; }
                if ($clientData['address2'] === '') { $errors['address2'] = 'Address line 2 is required.'; }
                if ($clientData['city'] === '') { $errors['city'] = 'City is required.'; }
                if ($clientData['state'] === '') { $errors['state'] = 'State is required.'; }
                if ($clientData['pin'] === '') { $errors['pin'] = 'PIN is required.'; }
                if ($clientData['primary_contact'] === '') { $errors['primary_contact'] = 'POC name is required.'; }
                if ($clientData['contact_email'] === '' || !filter_var($clientData['contact_email'], FILTER_VALIDATE_EMAIL)) { $errors['contact_email'] = 'Valid PCO email is required.'; }
                if ($clientData['ceo_email'] !== '' && !filter_var($clientData['ceo_email'], FILTER_VALIDATE_EMAIL)) { $errors['ceo_email'] = 'Valid CEO email is required.'; }
                if ($clientData['pan'] === '' || !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $clientData['pan'])) { $errors['pan'] = 'PAN must match AAAAA1111A.'; }
                if ($clientData['tan'] === '' || !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $clientData['tan'])) { $errors['tan'] = 'TAN must match AAAAA1111A.'; }
                if ($clientData['gst'] === '' || !preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z]\d[A-Z]{2}$/', $clientData['gst'])) { $errors['gst'] = 'GST must match 11AAAAA111A1AA.'; }
                if ($clientData['aadhaar'] === '' || !preg_match('/^\d{4} \d{4} \d{4}$/', $clientData['aadhaar'])) { $errors['aadhaar'] = 'Aadhaar must match 1111 1111 1111.'; }
                if ($clientData['incorporated_on'] === '' || !DateTime::createFromFormat('Y-m-d', $clientData['incorporated_on'])) { $errors['incorporated_on'] = 'Valid incorporation date is required.'; }
                if ($clientData['pco_phone'] === '' || !preg_match('/^[0-9+\-\s]{7,15}$/', $clientData['pco_phone'])) { $errors['pco_phone'] = 'Enter a valid PCO number.'; }

                if (!empty($errors) || !$clientId) {
                    $_SESSION['client_form'] = [
                        'data' => array_merge($clientData, ['edit_id' => $clientId]),
                        'errors' => $errors,
                        'services' => [],
                    ];
                    set_flash('error', 'Please fix the highlighted fields.');
                } elseif (update_client_record($clientId, $clientData)) {
                    unset($_SESSION['client_form']);
                    $GLOBALS['CLIENT_CACHE_VERSION'] = microtime(true);
                    $GLOBALS['TASK_CACHE_VERSION'] = microtime(true);
                    write_audit('client', 0, 'update', current_user(), ['c_id' => $clientId, 'data' => $clientData]);
                    set_flash('success', 'Client updated.');
                } else {
                    $_SESSION['client_form'] = [
                        'data' => array_merge($clientData, ['edit_id' => $clientId]),
                        'errors' => [],
                        'services' => [],
                    ];
                    set_flash('error', 'Could not update client.');
                }
            } else {
                set_flash('error', 'Unsupported action.');
            }

            redirect('/clients');
        }

        $clientForm = $_SESSION['client_form'] ?? ['data' => [], 'errors' => [], 'services' => []];
        unset($_SESSION['client_form']);

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        render('clients', [
            'title' => 'Client & Service Matrix',
            'user' => current_user(),
            'clients' => get_clients(),
            'serviceCatalog' => get_service_catalog(),
            'entityGroupCatalog' => get_entity_group_catalog(),
            'entitySubtypeCatalog' => get_entity_subtype_catalog(),
            'flashError' => get_flash('error'),
            'flashSuccess' => get_flash('success'),
            'clientFormData' => $clientForm['data'],
            'clientFormErrors' => $clientForm['errors'],
            'clientFormServices' => $clientForm['services'],
        ]);
        break;

    case '/staff':
        require_roles(['ceo', 'employee']);
        if ($method === 'GET' && strtolower((string)($_GET['bulk_template'] ?? '')) === 'csv') {
            $roleSlug = strtolower((string)($_GET['role'] ?? ''));
            $roles = get_roles();
            $roleId = 0; $roleName = '';
            foreach ($roles as $r) { $slug = strtolower(str_replace(' ', '-', (string)($r['name'] ?? ''))); if ($slug === $roleSlug) { $roleId = (int)($r['id'] ?? 0); $roleName = (string)($r['name'] ?? ''); break; } }
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="staff_template' . ($roleSlug ? ('_' . $roleSlug) : '') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['emp_id','employee_number','full_name','email','role','password'], ',', '"', '\\');
            if ($roleName !== '') {
                fputcsv($out, [1,1,'John Doe','john.doe@example.com',$roleName,'TempPass@123'], ',', '"', '\\');
                fputcsv($out, [2,2,'Jane Smith','jane.smith@example.com',$roleName,'TempPass@123'], ',', '"', '\\');
            } else {
                foreach ($roles as $r) {
                    $name = (string)($r['name'] ?? '');
                    $slug = strtolower(str_replace(' ', '-', $name));
                    fputcsv($out, [1,1,'John Doe',$slug.'.john@example.com',$name,'TempPass@123'], ',', '"', '\\');
                    fputcsv($out, [2,2,'Jane Smith',$slug.'.jane@example.com',$name,'TempPass@123'], ',', '"', '\\');
                }
            }
            fclose($out);
            exit;
        }
        if ($method === 'POST') {
            if (!validate_csrf($_POST['csrf_token'] ?? null)) {
                set_flash('error', 'Security token mismatch.');
                redirect('/staff');
            }

            if (!user_has_role(['ceo'])) {
                set_flash('error', 'Only CEO can manage staff records.');
                redirect('/staff');
            }

            $action = $_POST['action'] ?? 'create_staff';
            if ($action === 'bulk_upload_staff') {
                if (!isset($_FILES['bulk_file']) || ($_FILES['bulk_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { set_flash('error', 'Select a CSV or XLSX file.'); redirect('/staff'); }
                try {
                    $upload = save_uploaded_file($_FILES['bulk_file'], 'staff', ['csv','xlsx'], 8388608);
                    $fullPath = realpath(__DIR__ . '/../' . $upload['path']);
                    if (!$fullPath || !is_file($fullPath)) { throw new RuntimeException('Upload missing on server.'); }
                    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                    $ok=0; $fail=0; $failMsg='';
                    if ($ext === 'csv') {
                        $res = bulk_import_staff_csv($fullPath, current_user());
                        $ok += (int) ($res['ok'] ?? 0);
                        $fail += (int) ($res['fail'] ?? 0);
                        if (!empty($res['errors']) && $failMsg === '') { $failMsg = (string) $res['errors'][0]; }
                    } elseif ($ext === 'xlsx') {
                        if (!class_exists('ZipArchive')) { throw new RuntimeException('XLSX not supported on server. Upload CSV.'); }
                        $zip = new ZipArchive(); if ($zip->open($fullPath) !== true) { throw new RuntimeException('Unable to open XLSX.'); }
                        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml'); $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
                        $shared = []; if ($sharedXml) { $sx = @simplexml_load_string($sharedXml); if ($sx) { foreach ($sx->si as $si) { $shared[] = (string) ($si->t ?? ''); } } }
                        $s1 = @simplexml_load_string($sheetXml); if (!$s1) { throw new RuntimeException('Invalid XLSX sheet.'); }
                        $rowsRaw = []; foreach ($s1->sheetData->row as $r) { $rowVals = []; foreach ($r->c as $c) { $t = (string) ($c['t'] ?? ''); $v=''; if ($t==='s'){ $idx=(int)($c->v ?? -1); $v = $idx>=0 && isset($shared[$idx]) ? $shared[$idx] : ''; } else { $v=(string)($c->v ?? ''); } $rowVals[] = trim($v); } $rowsRaw[] = $rowVals; }
                        if (empty($rowsRaw)) { throw new RuntimeException('Empty XLSX.'); }
                        $header = array_map(static fn($h)=>strtolower(trim((string)$h)), $rowsRaw[0]);
                        $rows = [];
                        for ($ri=1;$ri<count($rowsRaw);$ri++) { $src=$rowsRaw[$ri]; $row=[]; for ($i=0;$i<count($header);$i++){ $row[$header[$i] ?? ('col'.$i)] = trim((string)($src[$i] ?? '')); } if (!empty(array_filter($row))) { $rows[] = $row; } }
                        $zip->close();
                    } else { throw new RuntimeException('Unsupported file type.'); }
                    if ($ext === 'xlsx') {
                        foreach ($rows as $row) {
                            $fullName = $row['full_name'] ?? '';
                            $email = normalize_email_input((string)($row['email'] ?? ''));
                            $code = $row['emp_id'] ?? ($row['employee_number'] ?? ($row['employee_code'] ?? ''));
                            $roleName = (string)($row['role'] ?? '');
                            $password = $row['password'] ?? '';
                            $roleId = role_id_by_label($roleName);
                            $errs = [];
                            if ($fullName==='') { $errs['full_name']=1; }
                            if ($email==='') { $errs['email']=1; }
                            elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errs['email']=1; }
                            
                            if ($roleId<=0) { $errs['role']=1; }
                            if (strlen($password) < 8) { $errs['password']=1; }
                            if (!empty($errs)) { write_staff_bulk_log('Invalid row ' . ($email ?: $fullName)); $fail++; if ($failMsg==='') { $failMsg = 'Invalid row for ' . ($email ?: $fullName); } continue; }
                            try { $existing = $email !== '' ? find_employee_by_email_any($email) : null; } catch (Throwable $ex) { $existing = null; }
                            if ($existing) {
                                $upd = update_employee_profile((int) ($existing['id'] ?? 0), [
                                    'full_name' => $fullName,
                                    'email' => $email,
                                    'role_id' => $roleId,
                                ]);
                                if (($upd['success'] ?? false)) {
                                    write_staff_bulk_log('Updated existing ' . $email . ' role=' . $roleName);
                                    try {
                                        $info = find_employee((int) ($existing['id'] ?? 0));
                                        if (empty($info['employee_number'])) { update_employee_number((int) ($existing['id'] ?? 0), employee_number_from_id((int) ($existing['id'] ?? 0))); }
                                    } catch (Throwable $e) {}
                                    $ok++;
                                    write_audit('staff', (int) ($existing['id'] ?? 0), 'update', current_user(), ['email' => $email, 'role' => $roleName]);
                                } else {
                                    $fail++;
                                    write_staff_bulk_log('Update failed ' . $email);
                                    if ($failMsg==='') { $failMsg = 'Failed to update ' . $email; }
                                }
                                continue;
                            }
                            $creation = create_employee_profile($fullName, $email, $password, $roleId, ($code !== '' ? $code : null));       
                            if ($creation['success'] ?? false) {
                                $newId = (int) ($creation['id'] ?? 0);
                                $finalCode = employee_number_from_id($newId);
                                write_staff_bulk_log('Created ' . $email . ' role=' . $roleName . ' code=' . $finalCode);
                                try { update_employee_number($newId, $finalCode); } catch (Throwable $e) {}
                                $ok++;
                                write_audit('staff', (int) ($creation['id'] ?? 0), 'create', current_user(), ['email' => $email, 'role' => $roleName]);
                            } else {
                                $fail++;
                                $detail = '';
                                $sqlState = (string) ($creation['sqlstate'] ?? '');
                                $driverCode = (string) ($creation['driver_code'] ?? '');
                                if ($sqlState !== '' || $driverCode !== '') { $detail = ' [' . $sqlState . '/' . $driverCode . ']'; }
                                $errKey = (string) ($creation['error'] ?? '');
                                if ($errKey === 'duplicate_emp_id') {
                                    write_staff_bulk_log('Duplicate emp_id ' . ($code ?: '') . $detail);
                                    if ($failMsg==='') { $failMsg = 'Employee number already exists' . ($code !== '' ? (': ' . $code) : '') . $detail; }
                                } elseif ($errKey === 'duplicate') {
                                    write_staff_bulk_log('Duplicate email ' . $email . $detail);
                                    if ($failMsg==='') { $failMsg = 'Email already exists: ' . $email . $detail; }
                                } else {
                                    write_staff_bulk_log('Create failed ' . ($email ?: $fullName) . $detail);
                                    if ($failMsg==='') { $failMsg = 'DB error' . ($detail !== '' ? (' ' . $detail) : '') . ' for ' . ($email ?: $fullName); }
                                }
                            }
                        }
                    }
                    if ($ok>0) { $GLOBALS['ORG_DIRECTORY_CACHE'] = null; }
                    write_staff_bulk_log('XLSX summary ok=' . $ok . ' fail=' . $fail);
                    if ($fail>0 && $ok===0) { write_staff_bulk_log('XLSX failure reason: ' . $failMsg); set_flash('error', 'Bulk upload failed. ' . $failMsg); }
                    else {
                        $msg = 'Bulk upload complete: ' . $ok . ' imported, ' . $fail . ' failed.';
                        if ($failMsg !== '') { $msg .= ' ' . $failMsg; }
                        set_flash('success', $msg);
                    }
                } catch (Throwable $e) { set_flash('error', $e->getMessage()); }
                redirect('/staff');
            }
            $currentUserId = (int) (current_user()['id'] ?? 0);

            if ($action === 'delete_staff') {
                $employeeId = (int) ($_POST['employee_db_id'] ?? ($_POST['emp_id'] ?? 0));
                if ($employeeId <= 0) {
                    set_flash('error', 'Select a valid staff member.');
                } elseif ($employeeId === $currentUserId) {
                    set_flash('error', 'You cannot delete your own staff profile.');
                } elseif (delete_employee($employeeId)) {
                    set_flash('success', 'Staff member deleted.');
                } else {
                    set_flash('error', 'Unable to delete staff member. Please try again.');
                }
                redirect('/staff'); 
            }

            if (($postAction ?? '') === 'org_delete') {
                $memberId = (int) ($_POST['member_id'] ?? 0);
                if ($memberId <= 0) {
                    set_flash('error', 'Invalid org member.');
                    redirect('/hierarchy');
                }
                if (delete_org_member($memberId)) {
                    set_flash('success', 'Org member deleted successfully.');
                } else {
                    set_flash('error', 'Unable to delete org member.');
                }
                redirect('/hierarchy');
            }

            $formData = [
                'full_name' => trim($_POST['full_name'] ?? ''),
                'email' => normalize_email_input((string) ($_POST['email'] ?? '')),
                'employee_number' => substr(trim((string) ($_POST['emp_id'] ?? '')), 0, 32),
                'role_id' => (int) ($_POST['role_id'] ?? 0),
            ];
            $password = (string) ($_POST['password'] ?? '');
            $errors = [];

            if ($formData['full_name'] === '') {
                $errors['full_name'] = 'Full name is required.';
            }
            if ($formData['email'] === '') {
                $errors['email'] = 'Email is required.';
            } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Enter a valid email address.';
            } elseif (!preg_match('/@([A-Za-z0-9-]+\.)+(co\.in|com)$/i', $formData['email'])) {
                $errors['email'] = 'Use a .co.in or .com email like name@example.co.in or name@example.com.';
            }
            // Employee number is optional; auto-generated when missing
            if ($formData['role_id'] <= 0) {
                $errors['role_id'] = 'Select a role level.';
            }

            if ($action === 'create_staff') {
                if (strlen($password) < 8) {
                    $errors['password'] = 'Password must be at least 8 characters.';
                }
                $employeeId = null;
            } elseif ($action === 'update_staff') {
                $employeeId = (int) ($_POST['employee_db_id'] ?? 0);
                if ($employeeId <= 0) {
                    $errors['general'] = 'Select a staff record to update.';
                }
            } else {
                set_flash('error', 'Unsupported staff action.');
                redirect('/staff');
            }

            if (empty($errors)) {
                $codeInput = (string) ($formData['employee_code'] ?? '');
                if ($codeInput !== '') {
                    try { $existByNum = find_employee_by_number($codeInput); } catch (Throwable $exception) { $existByNum = null; }
                    if ($existByNum && ($action === 'create_staff' || (int) ($existByNum['id'] ?? 0) !== $employeeId)) {
                        $errors['emp_id'] = 'Employee number already exists.';
                    }
                }
                if (empty($errors)) {
                    try {
                        $existing = $formData['email'] !== '' ? find_employee_by_email_any($formData['email']) : null;
                    } catch (Throwable $exception) {
                        $existing = null;
                    }
                    if ($existing && ($action === 'create_staff' || (int) ($existing['id'] ?? 0) !== $employeeId)) {
                        $errors['email'] = 'Email already exists.';
                    }
                }
            }

            if (!empty($errors)) {
                $_SESSION['staff_form'] = [
                    'data' => $formData,
                    'errors' => $errors,
                    'mode' => $action === 'update_staff' ? 'edit' : 'create',
                    'edit_id' => $action === 'update_staff' ? ($employeeId ?? 0) : null,
                ];
                set_flash('error', 'Please correct the highlighted errors.');
                redirect('/staff' . ($action === 'update_staff' && $employeeId ? '?edit=' . $employeeId : ''));
            }

            if ($action === 'create_staff') {
                $overrideCode = ($formData['employee_number'] ?? ($formData['employee_code'] ?? null));
                $creation = create_employee_profile($formData['full_name'], $formData['email'], $password, $formData['role_id'], ($overrideCode !== null && trim((string)$overrideCode) !== '' ? (string)$overrideCode : null));
                if ($creation['success'] ?? false) {
                    set_flash('success', 'Staff member created successfully.');
                    unset($_SESSION['staff_form']);
                } else {
                    $errorKey = $creation['error'] ?? 'unknown';
                    if ($errorKey === 'duplicate_emp_id') {
                        $message = 'Employee number already exists.';
                        $errors['emp_id'] = $message;
                    } elseif ($errorKey === 'duplicate') {
                        $message = 'Email already exists.';
                        $errors['email'] = $message;
                    } elseif ($errorKey === 'invalid') {
                        $message = 'Invalid data supplied.';
                    } else {
                        $message = 'Could not create staff member. Please try again.';
                    }
                    $_SESSION['staff_form'] = ['data' => $formData, 'errors' => $errors];
                    set_flash('error', $message);
                }
            } else {
                $update = update_employee_profile($employeeId, $formData);
                if ($update['success'] ?? false) {
                    set_flash('success', 'Staff member updated successfully.');
                    unset($_SESSION['staff_form']);
                } else {
                    $errorKey = $update['error'] ?? 'unknown';
                    if ($errorKey === 'duplicate_emp_id') {
                        $errors['emp_id'] = 'Employee number already exists.';
                        $message = 'Employee number already exists.';
                    } elseif ($errorKey === 'duplicate') {
                        $errors['email'] = 'Email already exists.';
                        $message = 'Email already exists.';
                    } elseif ($errorKey === 'not_found') {
                        $message = 'Staff record not found.';
                    } else {
                        $message = 'Could not update staff member. Please try again.';
                        if (getenv('APP_ENV') === 'dev' && !empty($update['message'])) {
                            $message .= ' (' . $update['message'] . ')';
                        }
                    }
                    $_SESSION['staff_form'] = [
                        'data' => $formData,
                        'errors' => $errors,
                        'mode' => 'edit',
                        'edit_id' => $employeeId,
                    ];
                    set_flash('error', $message);
                }
            }

            redirect('/staff');
        }

        $staffForm = $_SESSION['staff_form'] ?? ['data' => [], 'errors' => []];
        unset($_SESSION['staff_form']);

        $editId = 0;
        if (($staffForm['mode'] ?? '') === 'edit' && !empty($staffForm['edit_id'])) {
            $editId = (int) $staffForm['edit_id'];
        } elseif (isset($_GET['edit'])) {
            $editId = (int) $_GET['edit'];
        }

        $editingEmployee = null;
        if ($editId > 0) {
            $editingEmployee = find_employee($editId);
            if (!$editingEmployee) {
                set_flash('error', 'Staff record not found.');
                redirect('/staff');
            }
                if (empty($staffForm['data'])) {
                $staffForm['data'] = [
                    'full_name' => $editingEmployee['name'],
                    'email' => $editingEmployee['email'],
                    'employee_number' => $editingEmployee['employee_number'] ?? '',
                    'role_id' => $editingEmployee['role_id'] ?? 0,
                ];
            }
        }

        render('staff', [
            'title' => 'Team Directory',
            'user' => current_user(),
            'ceo' => get_employees('ceo'),
            'leads' => get_employees('lead'),
            'teamMembers' => get_employees('team'),
            'allEmployees' => get_employees(),
            'roles' => get_roles(),
            'flashError' => get_flash('error'),
            'flashSuccess' => get_flash('success'),
            'staffFormData' => $staffForm['data'],
            'staffFormErrors' => $staffForm['errors'],
            'staffEditId' => $editId,
            'editingEmployee' => $editingEmployee,
        ]);
        break;

    case '/checklist':
        require_roles(['ceo', 'lead', 'employee']);
        $user = current_user();
        try {
            $employeeRecord = find_employee_by_email($user['email']);
        } catch (Throwable $exception) {
            error_log('[CHECKLIST] Failed to load employee record: ' . $exception->getMessage());
            set_flash('error', 'Unable to load your staff profile. Please try again later.');
            redirect('/dashboard');
        }

        if ($method === 'POST') {
            if (!validate_csrf($_POST['csrf_token'] ?? null)) {
                set_flash('error', 'Security token mismatch.');
                redirect('/checklist');
            }

            $taskId = (int) ($_POST['task_id'] ?? 0);
            $action = $_POST['action'] ?? 'update';

            // Global checklist management when no task is selected
            if ($taskId <= 0) {
                if (!user_has_role('ceo')) {
                    set_flash('error', 'Only the CEO can manage the global checklist.');
                    redirect('/checklist');
                }

                if ($action === 'add_item') {
                    $label = trim($_POST['label'] ?? '');
                    try { add_global_checklist_item($label); write_audit('checklist', 0, 'global_add_item', current_user(), ['label' => $label]); set_flash('success', 'Item added.'); } catch (Throwable $e) { set_flash('error', 'Unable to add item'); }
                    redirect('/checklist');
                } elseif ($action === 'edit_item') {
                    $itemId = (string) ($_POST['item_id'] ?? '');
                    $label = trim($_POST['label'] ?? '');
                    try { update_global_checklist_item($itemId, $label); write_audit('checklist', 0, 'global_edit_item', current_user(), ['item_id' => $itemId, 'label' => $label]); set_flash('success', 'Item updated.'); } catch (Throwable $e) { set_flash('error', 'Unable to update item'); }
                    redirect('/checklist');
                } elseif ($action === 'delete_item') {
                    $itemId = (string) ($_POST['item_id'] ?? '');
                    try { delete_global_checklist_item($itemId); write_audit('checklist', 0, 'global_delete_item', current_user(), ['item_id' => $itemId]); set_flash('success', 'Item deleted.'); } catch (Throwable $e) { set_flash('error', 'Unable to delete item'); }
                    redirect('/checklist');
                } else {
                    $completedKeys = array_keys($_POST['completed'] ?? []);
                    update_global_checklist(array_map('strval', $completedKeys));
                    write_audit('checklist', 0, 'global_update', current_user(), ['completed' => array_map('strval', $completedKeys)]);
                    set_flash('success', 'Checklist updated.');
                    redirect('/checklist');
                }
            }

            $task = find_task($taskId);
            if (!$task) {
                set_flash('error', 'Task not found.');
                redirect('/checklist');
            }

            $canUpdate = user_has_role('ceo');
            if (!$canUpdate && $employeeRecord) {
                $canUpdate = $employeeRecord['id'] === $task['lead_id'];
            }

            if (!$canUpdate) {
                set_flash('error', 'Only the lead or CEO can update this checklist.');
                redirect('/checklist');
            }

            if ($action === 'add_item') {
                $label = trim($_POST['label'] ?? '');
                try { add_task_checklist_item($taskId, $label); write_audit('task_checklist', (int) $taskId, 'add_item', current_user(), ['label' => $label]); set_flash('success', 'Item added.'); } catch (Throwable $e) { set_flash('error', 'Unable to add item'); }
                redirect('/checklist');
            } elseif ($action === 'edit_item') {
                $itemId = (string) ($_POST['item_id'] ?? '');
                $label = trim($_POST['label'] ?? '');
                try { update_task_checklist_item($taskId, $itemId, $label); write_audit('task_checklist', (int) $taskId, 'edit_item', current_user(), ['item_id' => $itemId, 'label' => $label]); set_flash('success', 'Item updated.'); } catch (Throwable $e) { set_flash('error', 'Unable to update item'); }
                redirect('/checklist');
            } elseif ($action === 'delete_item') {
                $itemId = (string) ($_POST['item_id'] ?? '');
                try { delete_task_checklist_item($taskId, $itemId); write_audit('task_checklist', (int) $taskId, 'delete_item', current_user(), ['item_id' => $itemId]); set_flash('success', 'Item deleted.'); } catch (Throwable $e) { set_flash('error', 'Unable to delete item'); }
                redirect('/checklist');
            } else {
                $completedKeys = array_keys($_POST['completed'] ?? []);
                update_task_checklist($taskId, array_map('strval', $completedKeys));
                write_audit('task_checklist', (int) $taskId, 'update', current_user(), ['completed' => array_map('strval', $completedKeys)]);
                set_flash('success', 'Checklist updated.');
                redirect('/checklist');
            }
        }

        render('checklist', [
            'title' => 'Task Checklists',
            'user' => $user,
            'tasks' => get_tasks(),
            'employeeRecord' => $employeeRecord,
            'flashError' => get_flash('error'),
            'flashSuccess' => get_flash('success'),
        ]);
        break;

    case '/templates':
        require_roles(['ceo']);
        $user = current_user();

        if ($method === 'POST') {
            if (!validate_csrf($_POST['csrf_token'] ?? null)) {
                set_flash('error', 'Security token mismatch.');
                redirect('/templates');
            }

            $action = $_POST['action'] ?? 'upload';
            try {
                if ($action === 'delete') {
                    $id = (int) ($_POST['template_id'] ?? 0);
                    if ($id && delete_template_record($id)) {
                        write_audit('template', (int) $id, 'delete', $user, []);
                        set_flash('success', 'Template deleted.'); 
                    } else {
                        set_flash('error', 'Unable to delete template.');
                    }
                } elseif ($action === 'update') {
                    $id = (int) ($_POST['template_id'] ?? 0);
                    if ($id <= 0) { throw new RuntimeException('Invalid template.'); }
                    $payload = [
                        'name' => trim($_POST['name'] ?? ''),
                        'category' => trim($_POST['category'] ?? ''),
                    ];
                    if (isset($_FILES['template_file']) && ($_FILES['template_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        $upload = save_uploaded_file(
                            $_FILES['template_file'],
                            'templates',
                            ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'text', 'rtf', 'md']
                        );
                        $payload['file_path'] = $upload['path'];
                    }
                    if (update_template_record($id, $payload)) {
                        write_audit('template', (int) $id, 'update', $user, $payload);
                        set_flash('success', 'Template updated.');
                    } else {
                        set_flash('error', 'No changes to update.');
                    }
                } else {
                    if (!isset($_FILES['template_file'])) {
                        set_flash('error', 'Select a template to upload.');
                        redirect('/templates');
                    }
                    $upload = save_uploaded_file(
                        $_FILES['template_file'],
                        'templates',
                        ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'text', 'rtf', 'md']
                    );

                    add_template_record([
                        'name' => trim($_POST['name'] ?? ($upload['name'] ?? 'Template')),
                        'category' => trim($_POST['category'] ?? 'General'),
                        'file_path' => $upload['path'],
                        'uploaded_by' => $user['name'],
                        'uploaded_at' => $upload['uploaded_at'],
                    ]);
                    write_audit('template', 0, 'create', $user, ['name' => trim($_POST['name'] ?? ($upload['name'] ?? 'Template')), 'category' => trim($_POST['category'] ?? 'General'), 'file_path' => $upload['path']]);

                    set_flash('success', 'Template stored successfully.');
                }
            } catch (Throwable $exception) {
                set_flash('error', $exception->getMessage());
            }

            redirect('/templates');
        }

        $editTemplateId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $editTemplate = $editTemplateId ? find_template($editTemplateId) : null;

        render('templates', [
            'title' => 'Template Library',
            'user' => $user,
            'templates' => get_templates(),
            'editTemplate' => $editTemplate,
            'flashError' => get_flash('error'),
            'flashSuccess' => get_flash('success'),
        ]);
        break;

    case '/templates/download':
        require_roles(['ceo']);
        $templateId = (int) ($_GET['id'] ?? 0);
        $template = null;
        foreach (get_templates() as $candidate) {
            if ($candidate['id'] === $templateId) {
                $template = $candidate;
                break;
            }
        }

        if (!$template) {
            http_response_code(404);
            echo 'Template not found';
            break;
        }

        $fullPath = realpath(__DIR__ . '/../' . $template['file_path']);
        if (!$fullPath || !is_file($fullPath)) {
            http_response_code(404);
            echo 'File missing';
            break;
        }

        $downloadName = basename($template['name'] ?? basename($template['file_path']));
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName);
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none';");
        header('X-Frame-Options: DENY');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;

    case '/license':
        require __DIR__ . '/../src/views/license.php';
        break;

    case '/attachments/download':
        require_roles(['ceo', 'employee']);
        $attachmentId = (string) ($_GET['id'] ?? '');
        if ($attachmentId === '') {
            http_response_code(404);
            echo 'Attachment missing';
            break;
        }

        $result = find_task_attachment($attachmentId);
        if (!$result) {
            http_response_code(404);
            echo 'Attachment not found';
            break;
        }

        $attachment = $result['attachment'];
        $fullPath = realpath(__DIR__ . '/../' . $attachment['path']);
        if (!$fullPath || !is_file($fullPath)) {
            http_response_code(404);
            echo 'File missing';
            break;
        }

        $attachmentName = basename($attachment['name'] ?? 'attachment');
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $attachmentName);
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none';");
        header('X-Frame-Options: DENY');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;

    case '/hierarchy':
        require_roles(['ceo', 'lead', 'employee']);
        $locationLookup = tamil_nadu_location_lookup();
        $stateOptions = indian_states();
        $countryOptions = array_values(array_unique(array_map(static fn ($meta) => $meta['country'], $locationLookup)));

        if ($method === 'POST') {
            $directorySnapshot = get_org_directory();
            $knownLevels = $directorySnapshot['levels'] ?? [];
            if (!validate_csrf($_POST['csrf_token'] ?? null)) {
                set_flash('error', 'Security token mismatch.');
                redirect('/hierarchy');
            }

            $postAction = $_POST['action'] ?? 'org_create';
            $canModify = user_has_role(['ceo','lead']);
            $canAdminEdit = user_has_role(['ceo']);
            if ($postAction === 'org_delete' || $postAction === 'org_update') {
                if (!$canAdminEdit) {
                    set_flash('error', 'Only CEO can edit or delete org members.');
                    redirect('/hierarchy');
                }
            } else {
                if (!$canModify) {
                    set_flash('error', 'Only CEO or Leads can update the org structure.');
                    redirect('/hierarchy');
                }
            }

            $formData = [
                'level' => trim($_POST['level'] ?? ''),
                'level_order' => (int) ($_POST['level_order'] ?? 0),
                'display_order' => (int) ($_POST['display_order'] ?? 0),
                'name' => trim($_POST['name'] ?? ''),
                'title' => trim($_POST['title'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'location' => trim($_POST['location'] ?? ''),
                'state' => trim($_POST['state'] ?? ''),
                'country' => trim($_POST['country'] ?? ''),
                'tenure' => trim($_POST['tenure'] ?? ''),
                'focus' => trim($_POST['focus'] ?? ''),
                'bio' => trim($_POST['bio'] ?? ''),
                'skills' => trim($_POST['skills'] ?? ''),
            ];

            $errors = [];
            if ($formData['level'] === '') {
                $errors['level'] = 'Level name is required.';
            }
            if ($formData['name'] === '') {
                $errors['name'] = 'Employee name is required.';
            }
            if ($formData['email'] !== '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Enter a valid email address.';
            }
            if ($formData['level'] !== '' && isset($knownLevels[$formData['level']])) {
                if ((int) $formData['level_order'] !== (int) $knownLevels[$formData['level']]) {
                    $errors['level_order'] = 'Existing levels must retain their original order.';
                }
            }
            if ((int) $formData['display_order'] !== (int) $formData['level_order']) {
                $errors['display_order'] = 'Display order must match level order.';
            }
            if ($formData['title'] === '' && $formData['level'] !== '') {
                $formData['title'] = $formData['level'];
            }
            if ($formData['location'] !== '') {
                if (isset($locationLookup[$formData['location']])) {
                    $expected = $locationLookup[$formData['location']];
                    if ($formData['state'] === '') {
                        $formData['state'] = $expected['state'];
                    }
                    if ($formData['country'] === '') {
                        $formData['country'] = $expected['country'];
                    }
                    if ($formData['state'] !== $expected['state']) {
                        $errors['state'] = 'State does not match the selected city.';
                    }
                    if ($formData['country'] !== $expected['country']) {
                        $errors['country'] = 'Country does not match the selected city.';
                    }
                } else {
                    if ($formData['state'] === '') {
                        $errors['state'] = 'State is required for custom cities.';
                    }
                    if ($formData['country'] === '') {
                        $errors['country'] = 'Country is required for custom cities.';
                    }
                }
            } elseif ($formData['state'] !== '' || $formData['country'] !== '') {
                $errors['location'] = 'Select a city when providing state/country.';
            }

            if (!empty($errors)) {
                $_SESSION['org_form'] = ['data' => $formData, 'errors' => $errors];
                set_flash('error', 'Please fix the highlighted errors.');
                redirect('/hierarchy');
            }

            if ($postAction === 'org_update') {
                $memberId = (int) ($_POST['member_id'] ?? 0);
                if ($memberId <= 0) {
                    $_SESSION['org_form'] = ['data' => $formData, 'errors' => ['general' => 'Invalid member']];
                    set_flash('error', 'Invalid org member.');
                    redirect('/hierarchy');
                }
                $result = update_org_member($memberId, $formData);
                if ($result['success'] ?? false) {
                    unset($_SESSION['org_form']);
                    write_audit('hierarchy', (int) $memberId, 'update', current_user(), $formData);
                    set_flash('success', 'Org member updated successfully.');
                } else {
                    $_SESSION['org_form'] = ['data' => $formData, 'errors' => []];
                    set_flash('error', 'Unable to update org member. Please try again.');
                }
            } else {
                $result = create_org_member($formData);
                if ($result['success'] ?? false) {
                    unset($_SESSION['org_form']);
                    write_audit('hierarchy', (int) ($result['id'] ?? 0), 'create', current_user(), $formData);
                    set_flash('success', 'Org member added successfully.');
                } else {
                    $_SESSION['org_form'] = ['data' => $formData, 'errors' => []];
                    if (($result['error'] ?? '') === 'duplicate') {
                        set_flash('error', 'Member already exists at this level.');
                    } else {
                        set_flash('error', 'Unable to add org member. Please try again.');
                    }
                }
            }

            redirect('/hierarchy');
        }

        $orgDirectory = get_org_directory();
        $orgForm = $_SESSION['org_form'] ?? ['data' => [], 'errors' => []];
        unset($_SESSION['org_form']);

        $editMemberId = isset($_GET['edit_member']) ? (int) $_GET['edit_member'] : 0;
        if ($editMemberId > 0 && empty($orgForm['data'])) {
            $member = find_org_member($editMemberId);
            if ($member) {
                $orgForm['data'] = $member;
            }
        }

        render('hierarchy', [
            'title' => 'Org Hierarchy',
            'user' => current_user(),
            'hierarchy' => $orgDirectory['hierarchy'],
            'profiles' => $orgDirectory['profiles'],
            'levelOrders' => $orgDirectory['levels'] ?? [],
            'tnLocations' => $locationLookup,
            'stateOptions' => $stateOptions,
            'countryOptions' => $countryOptions,
            'tenureOptions' => tenure_years_options(),
            'orgFormData' => $orgForm['data'],
            'orgFormErrors' => $orgForm['errors'],
            'orgEditId' => $editMemberId,
            'flashError' => get_flash('error'),
            'flashSuccess' => get_flash('success'),
        ]);
        break;

    case '/access':
        require_roles(['ceo', 'employee', 'customer']);
        $user = current_user();

        if ($method === 'POST') {
            if (!user_has_role(['ceo'])) {
                set_flash('error', 'Only CEO can update access levels.');
                redirect('/access');
            }

            if (!validate_csrf($_POST['csrf_token'] ?? null)) {
                set_flash('error', 'Security token mismatch.');
                redirect('/access');
            }

            $action = $_POST['action'] ?? 'matrix';
            if ($action === 'add_asset') {
                $nameSelect = trim((string) ($_POST['asset_name_select'] ?? ''));
                $nameCustom = trim((string) ($_POST['asset_name_custom'] ?? ''));
                $name = $nameSelect !== '' ? $nameSelect : $nameCustom;
                if ($name === '') {
                    set_flash('error', 'Select or enter an asset name.');
                    redirect('/access');
                }
                $defaults = [
                    'superadmin' => strtolower((string) ($_POST['default_superadmin'] ?? 'none')),
                    'employee' => strtolower((string) ($_POST['default_employee'] ?? 'none')),
                    'customer' => strtolower((string) ($_POST['default_customer'] ?? 'none')),
                ];
                create_access_asset($name, $defaults);
                write_audit('access', 0, 'add_asset', current_user(), ['name' => $name, 'defaults' => $defaults]);
                $leadDefaultRaw = strtolower((string) ($_POST['default_lead'] ?? 'none'));
                $leadDefault = in_array($leadDefaultRaw, ACCESS_LEVELS, true) ? $leadDefaultRaw : 'none';
                $leadDefault = $leadDefault === 'read/write' ? 'write' : $leadDefault;
                $leadRoleId = 0; foreach (get_roles() as $role) { if (($role['slug'] ?? '') === 'lead') { $leadRoleId = (int) $role['id']; break; } }
                if ($leadRoleId > 0 && $leadDefault !== 'none') { update_role_permissions($leadRoleId, [$name => $leadDefault]); }
                set_flash('success', 'Asset added to Access matrix.');
                redirect('/access');
            } elseif ($action === 'user_update') {
                $empId = (int) ($_POST['user_id'] ?? 0);
                if ($empId <= 0 || !find_employee($empId)) {
                    set_flash('error', 'Select a valid user.');
                    redirect('/access');
                }
                $assets = array_keys(get_access_matrix());
                $submitted = $_POST['user_permissions'] ?? [];
                $clean = [];
                foreach ($assets as $asset) {
                    $raw = strtolower(trim((string) ($submitted[$asset] ?? 'none')));
                    $clean[$asset] = in_array($raw, ACCESS_LEVELS, true) ? $raw : 'none';
                }
                update_user_permissions($empId, $clean);
                write_audit('access', (int) $empId, 'user_update', current_user(), $clean);
                set_flash('success', 'User access updated.');
                redirect('/access?user=' . $empId);
            } else {
                $submitted = $_POST['permissions'] ?? [];
                $matrix = get_access_matrix();
                $roles = ['superadmin', 'employee', 'customer'];
                foreach ($matrix as $asset => &$permissions) {
                    foreach ($roles as $roleKey) {
                        $raw = $submitted[$asset][$roleKey] ?? ($permissions[$roleKey] ?? 'none');
                        $normalized = strtolower(trim((string) $raw));
                        if (!in_array($normalized, ACCESS_LEVELS, true)) {
                            $normalized = $permissions[$roleKey] ?? 'none';
                        }
                        $permissions[$roleKey] = $normalized;
                    }
                }
                $leadRoleId = 0; foreach (get_roles() as $role) { if (($role['slug'] ?? '') === 'lead') { $leadRoleId = (int) $role['id']; break; } }
                $leadSubmitted = $_POST['lead_permissions'] ?? [];
                if (is_array($leadSubmitted)) {
                    foreach (array_keys($matrix) as $asset) {
                        $raw = strtolower(trim((string) ($leadSubmitted[$asset] ?? ($matrix[$asset]['lead'] ?? 'none'))));
                        if (!in_array($raw, ACCESS_LEVELS, true)) { $raw = $matrix[$asset]['lead'] ?? 'none'; }
                        $matrix[$asset]['lead'] = $raw;
                    }
                }
                update_access_matrix($matrix);
                write_audit('access', 0, 'matrix_update', current_user(), $matrix);
                if ($leadRoleId > 0 && is_array($leadSubmitted)) {
                    $leadMatrix = [];
                    foreach (array_keys($matrix) as $asset) {
                        $lvl = strtolower((string) ($matrix[$asset]['lead'] ?? 'none'));
                        $leadMatrix[$asset] = $lvl === 'read/write' ? 'write' : $lvl;
                    }
                    update_role_permissions($leadRoleId, $leadMatrix);
                }
                set_flash('success', 'Access levels updated.');
                redirect('/access');
            }
        }

        $selectedUserId = (int) ($_GET['user'] ?? 0);
        $selectedUser = $selectedUserId ? find_employee($selectedUserId) : null;
        $leadRoleId = 0; foreach (get_roles() as $role) { if (($role['slug'] ?? '') === 'lead') { $leadRoleId = (int) $role['id']; break; } }
        render('access', [
            'title' => 'Access Matrix',
            'user' => $user,
            'matrix' => get_access_matrix(),
            'canEdit' => user_has_role(['ceo']),
            'allEmployees' => get_employees(),
            'selectedUserId' => $selectedUserId,
            'selectedUser' => $selectedUser,
            'menuAssets' => array_keys(get_access_matrix()),
            'availableAssets' => array_values(array_diff(system_assets(), array_keys(get_access_matrix()))),
            'leadRolePermissions' => $leadRoleId ? get_role_permissions($leadRoleId) : [],
            'userPermissions' => $selectedUserId ? get_user_permissions($selectedUserId) : [],
            'effectivePermissions' => $selectedUserId ? effective_user_permissions($selectedUserId) : [],
            'flashError' => get_flash('error'),
            'flashSuccess' => get_flash('success'),
        ]);
        break;

    case '/lead-teams':
        require_roles(['ceo', 'lead']);
        if (user_has_role('lead') && !user_has_permission('lead-teams', 'read')) {
            set_flash('error', 'Lead Teams requires CEO-granted access.');
            redirect('/dashboard');
        }
        if ($method === 'POST') {
            if (!validate_csrf($_POST['csrf_token'] ?? null)) {
                set_flash('error', 'Security token mismatch.');
                redirect('/lead-teams');
            }
            $action = $_POST['action'] ?? '';
            $leadId = (int) ($_POST['lead_id'] ?? 0);
            $teamId = (int) ($_POST['team_id'] ?? 0);
            $canWrite = user_has_role('ceo') || user_has_permission('lead-teams', 'write');
            if (!$canWrite) {
                set_flash('error', 'Write access required.');
                redirect('/lead-teams?lead=' . $leadId);
            }
            if ($action === 'add') {
                if ($leadId > 0 && $teamId > 0 && add_lead_team_member($leadId, $teamId)) {
                    write_audit('lead_teams', (int) $leadId, 'add_member', current_user(), ['team_id' => $teamId]);
                    set_flash('success', 'Member added to lead team.');
                } else {
                    set_flash('error', 'Unable to add member.');
                }
            } elseif ($action === 'remove') {
                if ($leadId > 0 && $teamId > 0 && remove_lead_team_member($leadId, $teamId)) {
                    write_audit('lead_teams', (int) $leadId, 'remove_member', current_user(), ['team_id' => $teamId]);
                    set_flash('success', 'Member removed.');
                } else {
                    set_flash('error', 'Unable to remove member.');
                }
            } else {
                set_flash('error', 'Unsupported action.');
            }
            redirect('/lead-teams?lead=' . $leadId);
        }
        $selectedLeadId = (int) ($_GET['lead'] ?? 0);
        $selectedLead = $selectedLeadId ? find_employee($selectedLeadId) : null;
        render('lead-teams', [
            'title' => 'Lead Teams',
            'user' => current_user(),
            'leads' => get_employees('lead'),
            'members' => array_values(array_reduce(array_merge(get_employees('team'), get_employees('employee')), function($carry,$emp){
                $carry[$emp['id']] = $emp; return $carry; }, [])),
            'selectedLeadId' => $selectedLeadId,
            'selectedLead' => $selectedLead,
            'leadTeam' => $selectedLeadId ? get_lead_team($selectedLeadId) : [],
            'flashError' => get_flash('error'),
            'flashSuccess' => get_flash('success'),
        ]);
        break;

    case '/cache/clear':
        require_roles(['ceo', 'superadmin']);
        if ($method === 'POST') {
            if (!validate_csrf($_POST['csrf_token'] ?? null)) {
                set_flash('error', 'Security token mismatch.');
                redirect('/dashboard');
            }
            invalidate_task_cache();
            if (function_exists('opcache_reset')) { @opcache_reset(); }
            unset($GLOBALS['ORG_DIRECTORY_CACHE']);
            unset($GLOBALS['TASK_CACHE_VERSION']);
            unset($GLOBALS['CLIENT_CACHE_VERSION']);
            unset($_SESSION['reminders_sent'], $_SESSION['alert_log']);
            set_flash('success', 'Cache cleared.');
            redirect('/dashboard');
        }
        http_response_code(405);
        echo 'Method not allowed';
        break;

    default:
        http_response_code(404);
        render('errors/404', [
            'title' => 'Not found',
            'user' => current_user(),
        ]);
}
