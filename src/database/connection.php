<?php

declare(strict_types=1);

function database_config(): array
{
    $config = $GLOBALS['DB_CONFIG'] ?? [];
    return is_array($config) ? $config : [];
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = database_config();

    $defaults = [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'u265933834_CAWorkflow',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: 'NewStrongPass123!',
        'charset' => 'utf8',    
    ];

    $db = array_merge($defaults, $config['db'] ?? []);

    $dsn = $config['dsn'] ?? sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['name'],
        $db['charset']
    );

    $username = $config['username'] ?? $db['user'];
    $password = $config['password'] ?? $db['pass'];
    $options = $config['options'] ?? [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, $username, $password, $options);
    } catch (PDOException $exception) {
        error_log('[DB ERROR] ' . $exception->getMessage());
        if (getenv('APP_ENV') === 'dev') {
            throw $exception;
        }
        http_response_code(500);
        exit('Service temporarily unavailable');
    }

    return $pdo;
}
