<?php
declare(strict_types=1);

if (!function_exists('attempt_login')) {
    function attempt_login(string $email, string $password): ?array
    {
        $user = find_employee_by_email($email);

        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }

        return null;
    }
}

if (!function_exists('login_user')) {
    function login_user(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
    }
}

if (!function_exists('logout_user')) {
    function logout_user(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }
}

if (!function_exists('is_authenticated')) {
    function is_authenticated(): bool
    {
        return current_user() !== null;
    }
}

if (!function_exists('user_has_role')) {
    function user_has_role($roles): bool
    {
        $user = current_user();
        if (!$user) {
            return false;
        }

        $roles = (array) $roles;
        $normalize = function ($name) {
            if (function_exists('normalize_role_slug')) {
                return normalize_role_slug((string) $name);
            }
            return strtolower((string) $name);
        };
        $wanted = array_map($normalize, $roles);
        $actual = $normalize($user['role'] ?? '');
        return in_array($actual, $wanted, true);
    }
}

if (!function_exists('require_auth')) {
    function require_auth(): void
    {
        if (!is_authenticated()) {
            set_flash('error', 'Please sign in to continue.');
            redirect('/login');
        }
    }
}

if (!function_exists('require_roles')) {
    function require_roles(array $roles): void
    {
        require_auth();

        if (!user_has_role($roles)) {
            http_response_code(403);
            render('errors/403', [
                'title' => 'Access denied',
                'user' => current_user(),
            ]);
            exit;
        }
    }
}

if (!function_exists('role_label')) {
    function role_label(string $role): string
    {
        return match ($role) {
            'superadmin' => 'Super Admin',
            'employee' => 'Employee',
            'customer' => 'Customer',
            default => ucfirst($role),
        };
    }
}

if (!function_exists('user_has_permission')) {
    function user_has_permission(string $resource, string $action): bool
    {
        $user = current_user();
        if (!$user) return false;

        $pdo = db();
        $override = $pdo->prepare('SELECT Access_Level FROM user_permissions WHERE EMP_ID = :id AND Resource = :resource');
        $override->execute(['id' => $user['id'], 'resource' => $resource]);
        $permission = $override->fetchColumn();

        if ($permission === false) {
            $stmt = $pdo->prepare('SELECT Access_Level FROM role_permissions rp JOIN employee e ON rp.Role_ID = e.Role_ID WHERE e.EMP_ID = :id AND rp.Resource = :resource');
            $stmt->execute(['id' => $user['id'], 'resource' => $resource]);
            $permission = $stmt->fetchColumn();
        }

        return match ($action) {
            'read' => in_array($permission, ['read', 'read/write', 'write', 'admin'], true),
            'write' => in_array($permission, ['read/write', 'write', 'admin'], true),
            'admin' => $permission === 'admin',
            default => false
        };
    }
}

if (!function_exists('handle_registration')) {
    function handle_registration(): array
    {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirmation = $_POST['password_confirmation'] ?? '';
        $role_input = trim((string) ($_POST['role'] ?? $_POST['role_id'] ?? ''));

        $response = [
            'success' => false,
            'message' => '',
            'field_errors' => [],
            'data' => [
                'full_name' => $full_name,
                'email' => $email,
                'role' => $role_input,
            ],
        ];

        try {
            error_log('[REGISTRATION] Starting registration process');

            if ($full_name === '') {
                $response['field_errors']['full_name'] = 'Full name is required.';
            }

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response['field_errors']['email'] = 'Enter a valid email address.';
            }

            if ($password === '') {
                $response['field_errors']['password'] = 'Password is required.';
            } elseif (strlen($password) < 12 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
                $response['field_errors']['password'] = 'Password must be at least 12 characters and include upper, lower, number, and symbol.';
            }

            if ($password_confirmation === '') {
                $response['field_errors']['password_confirmation'] = 'Confirm your password.';
            } elseif ($password !== $password_confirmation) {
                $response['field_errors']['password_confirmation'] = 'Passwords do not match.';
            }

            if ($role_input === '') {
                $response['field_errors']['role'] = 'Role is required.';
            }

            if (!empty($response['field_errors'])) {
                $response['message'] = 'Please fix the highlighted fields.';
                return $response;
            }

            if (find_employee_by_email($email)) {
                $response['field_errors']['email'] = 'Email is already registered.';
                $response['message'] = 'Please use a different email address.';
                return $response;
            }

            $pdo = db();
            $role_id = null;

            if ($role_input !== '' && ctype_digit((string) $role_input)) {
                $stmt = $pdo->prepare('SELECT Role_ID FROM roles WHERE Role_ID = ?');
                $stmt->execute([(int) $role_input]);
                $role_id = $stmt->fetchColumn();
            } else {
                $stmt = $pdo->prepare('SELECT Role_ID FROM roles WHERE LOWER(Role_Name) = LOWER(?)');
                $stmt->execute([$role_input]);
                $role_id = $stmt->fetchColumn();
            }

            if (!$role_id) {
                $response['field_errors']['role'] = 'Invalid role selected.';
                $response['message'] = 'Please pick a valid role.';
                return $response;
            }

            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO employee (EMP_ID, Full_Name, Email, Password_Hash, Role_ID) VALUES (?, ?, ?, ?, ?)');

            if ($stmt->execute([null, $full_name, $email, $password_hash, $role_id])) {
                $newId = (int) $pdo->lastInsertId();
                if ($newId > 0) {
                    try {
                        $finalCode = employee_number_from_id($newId);
                        update_employee_number($newId, $finalCode);
                    } catch (Throwable $e) {
                        error_log('[REGISTRATION WARN] failed to set employee code for id=' . $newId . ' err=' . $e->getMessage());
                    }
                }
                return [
                    'success' => true,
                    'message' => 'Registration successful! You can now sign in.',
                ];
            }

            $response['message'] = 'Database error occurred.';
            return $response;
        } catch (PDOException $e) {
            error_log('[REGISTRATION ERROR] ' . $e->getMessage());
            $response['message'] = 'Database error occurred.';
            return $response;
        } catch (Exception $e) {
            error_log('[REGISTRATION ERROR] ' . $e->getMessage());
            $response['message'] = 'An unexpected error occurred.';
            return $response;
        }
    }
}

if (!function_exists('handle_login')) {
    function handle_login(): void
    {
        try {
            error_log('[LOGIN] Starting login process');
            
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            if (!isset($_SESSION['login_throttle'])) {
                $_SESSION['login_throttle'] = [];
            }
            $key = strtolower($email) . '|' . $ip;
            $info = $_SESSION['login_throttle'][$key] ?? ['failures' => 0, 'locked_until' => 0];

            if ($info['locked_until'] > time()) {
                $wait = $info['locked_until'] - time();
                $_SESSION['auth_form'] = [
                    'tab' => 'login',
                    'data' => ['email' => $email],
                    'errors' => ['password' => 'Too many attempts. Try again in ' . $wait . ' seconds.'],
                ];
                set_flash('error', 'Too many attempts. Please wait ' . $wait . ' seconds.');
                redirect('/login');
            }
            
            error_log('[LOGIN] Attempting login for email: ' . $email . ' from IP: ' . $ip);
            
            $user = attempt_login($email, $password);
            
            if ($user) {
                $_SESSION['login_throttle'][$key] = ['failures' => 0, 'locked_until' => 0];
                error_log('[LOGIN SUCCESS] Login successful for user: ' . $email . ' with role: ' . ($user['role'] ?? 'unknown'));
                write_login_log('[LOGIN SUCCESS] ' . $email . ' role=' . ($user['role'] ?? 'unknown'));
                $requiresMfa = (getenv('MFA_CEO') === '1') && (($user['role'] ?? '') === 'ceo');
                if ($requiresMfa) {
                    $code = (string) random_int(100000, 999999);
                    $_SESSION['mfa'] = [
                        'user' => $user,
                        'email' => $user['email'] ?? $email,
                        'code' => $code,
                        'expires' => time() + 300,
                        'attempts' => 0,
                    ];
                    error_log('[MFA] Code ' . $code . ' generated for ' . ($user['email'] ?? $email));
                    write_login_log('[MFA ISSUED] ' . ($user['email'] ?? $email));
                    $_SESSION['auth_form'] = [
                        'tab' => 'mfa',
                        'data' => ['email' => $email],
                        'errors' => [],
                    ];
                    set_flash('success', 'Enter the verification code to complete sign in.');
                    redirect('/login');
                }
                login_user($user);
                set_flash('success', 'Login successful');
                redirect('/dashboard');
            }
            
            $failures = (int) ($info['failures'] ?? 0) + 1;
            $backoff = min(300, (int) pow(2, min(8, $failures)));
            $_SESSION['login_throttle'][$key] = [
                'failures' => $failures,
                'locked_until' => time() + $backoff,
            ];

            error_log('[LOGIN FAILED] Invalid credentials for email: ' . $email . ' (failures=' . $failures . ', backoff=' . $backoff . 's)');
            $_SESSION['auth_form'] = [
                'tab' => 'login',
                'data' => ['email' => $email],
                'errors' => ['password' => 'Invalid email or password.'],
            ];
            set_flash('error', 'Invalid email or password');
            redirect('/login');
        } catch (PDOException $e) {
            error_log('[LOGIN DB ERROR] ' . $e->getMessage());
            set_flash('error', 'Unable to reach the database. Please try again later.');
            redirect('/login');
        } catch (Exception $e) {
            error_log('[LOGIN ERROR] Exception during login: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            set_flash('error', 'An error occurred during login. Please try again.');
            redirect('/login');
        }
    }
}
