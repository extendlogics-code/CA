<?php exit; ?>

// Register routes
$router->match('GET|POST', '/register', function() {
    try {
        error_log('[REGISTER ROUTE] Starting registration route handler');
        
        // Check if any superadmin users exist
        error_log('[REGISTER ROUTE] Checking for existing superadmin users');
        $pdo = db();
        $stmt = $pdo->query("SELECT * FROM employee WHERE role_id = 1 LIMIT 1");
        
        if ($stmt->fetchColumn()) {
            error_log('[REGISTER ROUTE] Superadmin already exists, redirecting to login');
            set_flash('error', 'Registration is only for first-time superadmin setup');
            redirect('/login');
        }
        error_log('[REGISTER ROUTE] No superadmin found, proceeding with registration');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log('[REGISTER ROUTE] POST request detected, calling handle_registration');
            handle_registration();
        }
        
        error_log('[REGISTER ROUTE] Rendering registration form');
        render('auth/register', [
            'title' => 'Superadmin Registration'
        ]);
        
    } catch (Exception $e) {
        error_log('[REGISTER ROUTE ERROR] Exception caught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        set_flash('error', 'An unexpected error occurred during registration.');
        redirect('/login');
    }
});

$router->match('GET|POST', '/login', function() {
    try {
        error_log('[LOGIN ROUTE] Starting login route handler');
        
        // Check if any superadmin exists
        error_log('[LOGIN ROUTE] Checking for existing superadmin users');
        $pdo = db();
        $stmt = $pdo->query("SELECT 1 FROM employee e JOIN roles r ON r.Role_ID = e.Role_ID WHERE LOWER(r.Role_Name) = 'superadmin' LIMIT 1");
        $superadminExists = $stmt->fetchColumn();
        error_log('[LOGIN ROUTE] Superadmin exists: ' . ($superadminExists ? 'yes' : 'no'));
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log('[LOGIN ROUTE] POST request detected');

            if (!validate_csrf($_POST['csrf_token'] ?? null)) {
                error_log('[LOGIN ROUTE] CSRF validation failed');
                set_flash('error', 'Security token mismatch. Please try again.');
                redirect('/login');
            }
            
            // Determine action type
            $action = $_POST['action'] ?? '';
            error_log('[LOGIN ROUTE] Action type: ' . $action);
            
            if ($action === 'register') {
                error_log('[LOGIN ROUTE] Calling handle_registration for registration action');
                $result = handle_registration();

                if ($result['success']) {
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
            } elseif ($action === 'login') {
                error_log('[LOGIN ROUTE] Calling handle_login for login action');
                handle_login();
            } else {
                error_log('[LOGIN ROUTE ERROR] Unknown action type: ' . $action);
                set_flash('error', 'Invalid action.');
                redirect('/login');
            }
        }

        $authForm = $_SESSION['auth_form'] ?? [];
        unset($_SESSION['auth_form']);
        $activeTab = $authForm['tab'] ?? 'login';
        $loginForm = $activeTab === 'login' ? ($authForm['data'] ?? []) : [];
        $registerForm = $activeTab === 'register' ? ($authForm['data'] ?? []) : [];
        $registerErrors = $activeTab === 'register' ? ($authForm['errors'] ?? []) : [];

        error_log('[LOGIN ROUTE] Rendering login form');
        render('auth/login', [
            'title' => 'Login',
            'show_registration_link' => !$superadminExists,
            'flashError' => get_flash('error'),
            'flashSuccess' => get_flash('success'),
            'active_tab' => $activeTab,
            'login_form' => $loginForm,
            'register_form' => $registerForm,
            'register_errors' => $registerErrors,
        ]);
        
    } catch (Exception $e) {
        error_log('[LOGIN ROUTE ERROR] Exception caught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        set_flash('error', 'An unexpected error occurred during login.');
        redirect('/login');
    }
});

$router->match('GET|POST', '/services', function() {
    try {
        require_roles(['superadmin', 'employee']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                $priority = $_POST['priority'] ?? 'High';
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
    } catch (Exception $e) {
        error_log('[SERVICES ROUTE ERROR] ' . $e->getMessage());
        set_flash('error', 'Unable to load services right now.');
        redirect('/dashboard');
    }
});

$router->post('/register', function() {
    // Process registration and get detailed results
    $result = process_registration();
    
    // Prepare response data
    $response = [
        'title' => 'Login',
        'registration_result' => $result,
        'form_data' => $_POST
    ];
    
    // Check if superadmin exists to determine if registration link should be shown
    $pdo = db();
    $stmt = $pdo->query("SELECT 1 FROM employee e JOIN roles r ON r.Role_ID = e.Role_ID WHERE LOWER(r.Role_Name) = 'superadmin' LIMIT 1");
    $response['show_registration_link'] = !$stmt->fetchColumn();
    
    // Render the login view with all registration results
    render('auth/login', $response);
});

// Run router
try {
    error_log('[ROUTER] Starting router execution');
    $router->run();
    error_log('[ROUTER] Router execution completed');
} catch (Exception $e) {
    error_log('[ROUTER ERROR] Fatal router error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo 'Service temporarily unavailable';
}

/**
 * Processes registration and returns detailed results
 */
function process_registration(): array
{
    try {
        error_log('[REGISTRATION] Starting registration process');
        
        // Get form data
        $full_name = trim($_POST['full_name'] ?? '');
        $email = normalize_email_input((string) ($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $password_confirmation = $_POST['password_confirmation'] ?? '';
        $role_name = $_POST['role'] ?? '';
        
        // Basic validation
        $errors = [];
        
        if (empty($full_name)) {
            $errors['full_name'] = 'Full name is required';
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required';
        }
        
        if (empty($password)) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }
        
        if ($password !== $password_confirmation) {
            $errors['password_confirmation'] = 'Passwords do not match';
        }
        
        if (empty($role_name)) {
            $errors['role'] = 'Role is required';
        }
        
        // If there are validation errors, return them
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Please correct the errors below',
                'errors' => $errors
            ];
        }
        
        // Check if email is already in use
        if (find_employee_by_email($email)) {
            return [
                'success' => false,
                'message' => 'Email is already in use',
                'errors' => ['email' => 'Email is already registered']
            ];
        }
        
        // Process registration
        $pdo = db();
        
        // Get role ID
        $stmt = $pdo->prepare("SELECT Role_ID FROM roles WHERE Role_Name = ?");
        $stmt->execute([$role_name]);
        $role_id = $stmt->fetchColumn();
        
        if (!$role_id) {
            return [
                'success' => false,
                'message' => 'Invalid role selected',
                'errors' => ['role' => 'Invalid role']
            ];
        }
        
        // Hash password and create user
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        ensure_employee_number_column();
        ensure_employee_primary_key_autoincrement();
        $empCode = generate_next_employee_number();
        $stmt = $pdo->prepare("INSERT INTO employee (EMP_ID, Full_Name, Email, Password_Hash, Role_ID, Employee_Number) VALUES (?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([null, $full_name, $email, $password_hash, $role_id, $empCode])) {
            return [
                'success' => true,
                'message' => 'Registration successful! Please login.'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Database error occurred during registration'
        ];
        
    } catch (PDOException $e) {
        error_log('[REGISTRATION ERROR] Database error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'A database error occurred',
            'error_details' => $e->getMessage()
        ];
    } catch (Exception $e) {
        error_log('[REGISTRATION ERROR] ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An unexpected error occurred'
        ];
    }
}
