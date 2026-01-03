<?php
declare(strict_types=1);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}

session_start();

require __DIR__ . '/utils/helpers.php';
require __DIR__ . '/views/auth.php';
require __DIR__ . '/utils/master.php';
require __DIR__ . '/utils/services.php';
require __DIR__ . '/utils/tasks.php';
require __DIR__ . '/utils/templates.php';
require __DIR__ . '/utils/access.php';
require __DIR__ . '/utils/uploads.php';
require __DIR__ . '/utils/reminders.php';
require __DIR__ . '/utils/license.php';
require __DIR__ . '/services/mailer.php';
require __DIR__ . '/database/connection.php';
require __DIR__ . '/utils/install.php';

$GLOBALS['APP_DATA'] = [
    'users' => require __DIR__ . '/data/users.php',
    'employees' => require __DIR__ . '/data/employees.php',
    'clients_seed' => require __DIR__ . '/data/clients.php',
    'service_types' => require __DIR__ . '/data/service_types.php',
    'hierarchy' => require __DIR__ . '/data/hierarchy.php',
    'access_matrix' => require __DIR__ . '/data/access.php',
    'tasks_seed' => require __DIR__ . '/data/tasks.php',
    'templates_seed' => require __DIR__ . '/data/templates.php',
    'org_profiles' => require __DIR__ . '/data/org_profiles.php',
];

$GLOBALS['MAIL_CONFIG'] = file_exists(__DIR__ . '/../config/mail.php')
    ? require __DIR__ . '/../config/mail.php'
    : [];

$GLOBALS['DB_CONFIG'] = file_exists(__DIR__ . '/../config/database.php')
    ? require __DIR__ . '/../config/database.php'
    : [];

install_bootstrap();
master_bootstrap();
tasks_bootstrap();
templates_bootstrap();
access_bootstrap();
license_bootstrap();
