<?php
declare(strict_types=1);

function app_data(string $key, $default = null)
{
    return $GLOBALS['APP_DATA'][$key] ?? $default;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function app_base_path(): string
{
    static $base;
    if ($base !== null) {
        return $base;
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($scriptName));
    $dir = rtrim($dir, '/');

    if ($dir === '' || $dir === '.') {
        $base = '';
    } else {
        $base = $dir;
    }

    return $base;
}

function url_for(string $path = '/'): string
{
    $path = '/' . ltrim($path, '/');
    $base = app_base_path();

    if ($path === '//') {
        $path = '/';
    }

    if ($base === '') {
        return $path === '/' ? '/' : $path;
    }

    if ($path === '/') {
        return $base . '/';
    }

    return rtrim($base, '/') . $path;
}

function asset(string $path): string
{
    $relative = ltrim($path, '/');
    $base = app_base_path();
    $url = ($base === '' ? '' : rtrim($base, '/')) . '/' . $relative;

    $fullPath = __DIR__ . '/../../public/' . $relative;
    if (is_file($fullPath)) {
        $url .= ((strpos($url, '?') !== false) ? '&' : '?') . 'v=' . filemtime($fullPath);
    }

    return $url;
}

function redirect(string $path): void
{
    if ($path !== '' && $path[0] === '/') {
        $path = url_for($path);
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $code = ($method === 'POST') ? 303 : 302;
    header('Location: ' . $path, true, $code);
    exit;
}

function render(string $view, array $data = []): void
{
    $viewPath = __DIR__ . '/../views/' . $view . '.php';
    if (!file_exists($viewPath)) {
        http_response_code(404);
        echo 'View not found';
        return;
    }

    extract($data, EXTR_SKIP);
    include __DIR__ . '/../views/partials/header.php';
    include $viewPath;
    include __DIR__ . '/../views/partials/footer.php';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validate_csrf(?string $token): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return is_string($token) && $sessionToken !== '' && hash_equals($sessionToken, $token);
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function get_flash(string $type): ?string
{
    if (!isset($_SESSION['flash'][$type])) {
        return null;
    }

    $message = $_SESSION['flash'][$type];
    unset($_SESSION['flash'][$type]);

    return $message;
}

function request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = app_base_path();

    if ($base !== '' && strpos($uri, $base) === 0) {
        $uri = substr($uri, strlen($base));
    }

    $uri = rtrim($uri, '/');

    return $uri === '' ? '/' : $uri;
}

function mail_config(): array
{
    return $GLOBALS['MAIL_CONFIG'] ?? [];
}

function login_log_path(): string
{
    $dir = __DIR__ . '/../../storage/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    return $dir . '/login.log';
}

function audit_log_path(): string
{
    $dir = __DIR__ . '/../../storage/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    return $dir . '/audit.log';
}

function alerts_log_path(): string
{
    $dir = __DIR__ . '/../../storage/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    return $dir . '/alerts.log';
}

function app_error_log_path(string $scope): string
{
    $dir = __DIR__ . '/../../storage/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $safe = strtolower(trim((string) $scope));
    $safe = preg_replace('/[^a-z0-9_-]+/', '-', $safe) ?? 'app';
    $safe = trim($safe, '-');
    if ($safe === '') {
        $safe = 'app';
    }
    return $dir . '/' . $safe . '-error.log';
}

function write_app_error(string $scope, string $message, ?Throwable $exception = null, array $context = []): void
{
    $entry = [
        'time' => date('c'),
        'scope' => $scope,
        'message' => $message,
    ];

    if ($context) {
        $entry['context'] = $context;
    }

    $request = [];
    if (!empty($_SERVER['REQUEST_METHOD'])) {
        $request['method'] = $_SERVER['REQUEST_METHOD'];
    }
    if (!empty($_SERVER['REQUEST_URI'])) {
        $request['path'] = $_SERVER['REQUEST_URI'];
    }
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $request['ip'] = $_SERVER['REMOTE_ADDR'];
    }
    if ($request) {
        $entry['request'] = $request;
    }

    if (function_exists('current_user')) {
        $user = current_user();
        if ($user) {
            $entry['user'] = [
                'id' => $user['id'] ?? null,
                'email' => $user['email'] ?? null,
                'role' => $user['role'] ?? null,
            ];
        }
    }

    if ($exception) {
        $entry['exception'] = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];
    }

    $line = '[' . date('c') . '] ' . json_encode($entry, JSON_UNESCAPED_SLASHES);
    file_put_contents(app_error_log_path($scope), $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function ensure_audit_log_rotation(): void
{
    $dir = __DIR__ . '/../../storage/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $marker = $dir . '/audit.current-month';
    $current = date('Y-m');
    $previous = null;
    if (is_file($marker)) {
        $previous = trim((string) @file_get_contents($marker));
    }
    if ($previous === '' || $previous === null) {
        @file_put_contents($marker, $current);
        if (!is_file($dir . '/audit.log')) { @touch($dir . '/audit.log'); }
        return;
    }
    if ($previous !== $current) {
        $active = $dir . '/audit.log';
        if (is_file($active) && @filesize($active) > 0) {
            $target = $dir . '/audit-' . $previous . '.log';
            if (is_file($target)) {
                $i = 1;
                do { $target = $dir . '/audit-' . $previous . '-' . $i . '.log'; $i++; } while (is_file($target) && $i < 100);
            }
            @rename($active, $target);
        }
        @file_put_contents($marker, $current);
        if (!is_file($dir . '/audit.log')) { @touch($dir . '/audit.log'); }
    }
}

function write_login_log(string $line): void
{
    $entry = '[' . date('c') . '] ' . $line . PHP_EOL;
    ensure_login_log_rotation();
    file_put_contents(login_log_path(), $entry, FILE_APPEND | LOCK_EX);
}
function ensure_login_log_rotation(): void
{
    $dir = __DIR__ . '/../../storage/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $marker = $dir . '/login.current-month';
    $current = date('Y-m');
    $previous = null;
    if (is_file($marker)) { $previous = trim((string) @file_get_contents($marker)); }
    if ($previous === '' || $previous === null) {
        @file_put_contents($marker, $current);
        if (!is_file($dir . '/login.log')) { @touch($dir . '/login.log'); }
        return;
    }
    if ($previous !== $current) {
        $active = $dir . '/login.log';
        if (is_file($active) && @filesize($active) > 0) {
            $target = $dir . '/login-' . $previous . '.log';
            if (is_file($target)) {
                $i = 1;
                do { $target = $dir . '/login-' . $previous . '-' . $i . '.log'; $i++; } while (is_file($target) && $i < 100);
            }
            @rename($active, $target);
        }
        @file_put_contents($marker, $current);
        if (!is_file($dir . '/login.log')) { @touch($dir . '/login.log'); }
    }
}
function ensure_alerts_log_rotation(): void
{
    $dir = __DIR__ . '/../../storage/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $marker = $dir . '/alerts.current-month';
    $current = date('Y-m');
    $previous = null;
    if (is_file($marker)) { $previous = trim((string) @file_get_contents($marker)); }
    if ($previous === '' || $previous === null) {
        @file_put_contents($marker, $current);
        if (!is_file($dir . '/alerts.log')) { @touch($dir . '/alerts.log'); }
        return;
    }
    if ($previous !== $current) {
        $active = $dir . '/alerts.log';
        if (is_file($active) && @filesize($active) > 0) {
            $target = $dir . '/alerts-' . $previous . '.log';
            if (is_file($target)) {
                $i = 1;
                do { $target = $dir . '/alerts-' . $previous . '-' . $i . '.log'; $i++; } while (is_file($target) && $i < 100);
            }
            @rename($active, $target);
        }
        @file_put_contents($marker, $current);
        if (!is_file($dir . '/alerts.log')) { @touch($dir . '/alerts.log'); }
    }
}
function staff_bulk_log_path(): string
{
    $dir = __DIR__ . '/../../storage/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    return $dir . '/staff-bulk-' . date('Y-m-d') . '.log';
}

function write_staff_bulk_log(string $line): void
{
    $entry = '[' . date('c') . '] ' . $line . PHP_EOL;
    file_put_contents(staff_bulk_log_path(), $entry, FILE_APPEND | LOCK_EX);
}
