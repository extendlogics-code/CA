<?php
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) { require $autoload; }

putenv('APP_ENV=dev');
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
$_SERVER['REQUEST_URI'] = '/tests';

$logs = __DIR__ . '/../storage/logs';
if (!is_dir($logs)) { mkdir($logs, 0777, true); }

require __DIR__ . '/../src/bootstrap.php';