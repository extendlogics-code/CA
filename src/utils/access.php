<?php
declare(strict_types=1);

const ACCESS_LEVELS = ['none', 'read', 'read/write'];

function get_access_matrix(): array
{
    $pdo = db();
    $stmt = $pdo->query('SELECT Asset AS asset, ceo_Access AS ceo, lead_Access AS lead, Employee_Access AS employee, Customer_Access AS customer FROM access_matrix ORDER BY Asset');
    $matrix = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $matrix[$row['asset']] = [
            'ceo' => $row['ceo'],
            'employee' => $row['employee'],
            'lead' => $row['lead'],
            'customer' => $row['customer'],
        ];
    }
    return $matrix;
}

function update_access_matrix(array $matrix): void
{
    $pdo = db();
    $update = $pdo->prepare('UPDATE access_matrix SET ceo_Access = :ceo, lead_Access = :lead, Employee_Access = :employee, Customer_Access = :customer WHERE Asset = :asset');
    $insert = $pdo->prepare('INSERT INTO access_matrix (Asset, ceo_Access, lead_Access, Employee_Access, Customer_Access) VALUES (:asset, :ceo, :lead, :employee, :customer)');

    foreach ($matrix as $asset => $permissions) {
        $params = [
            'asset' => $asset,
            'ceo' => $permissions['ceo'] ?? 'none',
            'employee' => $permissions['employee'] ?? 'none',
            'lead' => $permissions['lead'] ?? 'none',
            'customer' => $permissions['customer'] ?? 'none',
        ];

        $update->execute($params);
        if ($update->rowCount() === 0) {
            $insert->execute($params);
        }
    }
}
function access_bootstrap(): void
{
    ensure_user_permissions_table();
}

function ensure_user_permissions_table(): void
{
    if (table_exists('user_permissions')) {
        return;
    }

    try {
        $pdo = db();
        $drv = db_driver();
        if ($drv === 'pgsql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS user_permissions (
                Permission_ID SERIAL PRIMARY KEY,
                EMP_ID INTEGER NOT NULL,
                Resource VARCHAR(100) NOT NULL,
                Access_Level VARCHAR(20) NOT NULL,
                UNIQUE (EMP_ID, Resource)
            )');
        } elseif ($drv === 'sqlite') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS user_permissions (
                Permission_ID INTEGER PRIMARY KEY AUTOINCREMENT,
                EMP_ID INTEGER NOT NULL,
                Resource TEXT NOT NULL,
                Access_Level TEXT NOT NULL,
                UNIQUE (EMP_ID, Resource)
            )');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS user_permissions (
                Permission_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                EMP_ID INT UNSIGNED NOT NULL,
                Resource VARCHAR(100) NOT NULL,
                Access_Level VARCHAR(20) NOT NULL,
                UNIQUE KEY uniq_user_resource (EMP_ID, Resource)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
    } catch (Throwable $e) {
        error_log('Create user_permissions failed: ' . $e->getMessage());
    }
}

function get_user_permissions(int $empId): array
{
    ensure_user_permissions_table();
    $pdo = db();
    $stmt = $pdo->prepare('SELECT Resource AS resource, Access_Level AS access_level FROM user_permissions WHERE EMP_ID = :id');
    $stmt->execute(['id' => $empId]);
    $perms = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $perms[$row['resource']] = $row['access_level'];
    }
    return $perms;
}

function update_user_permissions(int $empId, array $matrix): void
{
    ensure_user_permissions_table();
    $pdo = db();
    $update = $pdo->prepare('UPDATE user_permissions SET Access_Level = :level WHERE EMP_ID = :id AND Resource = :res');
    $insert = $pdo->prepare('INSERT INTO user_permissions (EMP_ID, Resource, Access_Level) VALUES (:id, :res, :level)');
    foreach ($matrix as $res => $level) {
        $params = ['id' => $empId, 'res' => $res, 'level' => $level];
        $update->execute($params);
        if ($update->rowCount() === 0) {
            $insert->execute($params);
        }
    }
}

function effective_user_permissions(int $empId): array
{
    $assets = array_keys(get_access_matrix());
    $overrides = get_user_permissions($empId);
    $employee = find_employee($empId);
    $rolePerms = [];
    if ($employee) {
        $rolePerms = get_role_permissions((int) ($employee['role_id'] ?? 0));
    }
    $effective = [];
    foreach ($assets as $asset) {
        $effective[$asset] = $overrides[$asset] ?? ($rolePerms[$asset] ?? 'none');
    }
    return $effective;
}

function get_role_permissions(int $roleId): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT Resource AS resource, Access_Level AS access_level FROM role_permissions WHERE Role_ID = :id');
    $stmt->execute(['id' => $roleId]);
    $perms = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $perms[$row['resource']] = $row['access_level'];
    }
    return $perms;
}

function update_role_permissions(int $roleId, array $matrix): void
{
    $pdo = db();
    $update = $pdo->prepare('UPDATE role_permissions SET Access_Level = :level WHERE Role_ID = :id AND Resource = :res');
    $insert = $pdo->prepare('INSERT INTO role_permissions (Role_ID, Resource, Access_Level) VALUES (:id, :res, :level)');
    foreach ($matrix as $res => $level) {
        $params = ['id' => $roleId, 'res' => $res, 'level' => $level];
        $update->execute($params);
        if ($update->rowCount() === 0) {
            $insert->execute($params);
        }
    }
}

function system_assets(): array
{
    return [
        'dashboard',
        'tasks',
        'clients',
        'services',
        'service-types',
        'statuses',
        'checklist',
        'templates',
        'hierarchy',
        'access',
        'lead-teams',
    ];
}

function create_access_asset(string $asset, array $defaults = []): void
{
    $asset = trim($asset);
    if ($asset === '') return;
    $ceo = $defaults['ceo'] ?? 'none';
    $lead = $defaults['lead'] ?? 'none';
    $employee = $defaults['employee'] ?? 'none';
    $customer = $defaults['customer'] ?? 'none';
    foreach (['ceo' => $ceo, 'lead' => $lead, 'employee' => $employee, 'customer' => $customer] as $k => $v) {
        if (!in_array($v, ACCESS_LEVELS, true)) {
            ${$k} = 'none';
        }
    }
    $pdo = db();
    $update = $pdo->prepare('UPDATE access_matrix SET ceo_Access = :ceo, lead_Access = :lead, Employee_Access = :employee, Customer_Access = :customer WHERE Asset = :asset');
    $insert = $pdo->prepare('INSERT INTO access_matrix (Asset, ceo_Access, lead_Access, Employee_Access, Customer_Access) VALUES (:asset, :ceo, :lead, :employee, :customer)');
    $params = [
        'asset' => $asset,
        'ceo' => $ceo,
        'employee' => $employee,
        'lead' => $lead,
        'customer' => $customer,
    ];
    $update->execute($params);
    if ($update->rowCount() === 0) {
        $insert->execute($params);
    }
}
