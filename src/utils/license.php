<?php
declare(strict_types=1);

function license_bootstrap(): void
{
    // Skip license check for CLI (optional, but good for maintenance)
    if (php_sapi_name() === 'cli') {
        return;
    }

    // Optimization: Check session first to avoid running wmic on every request
    if (!empty($_SESSION['license_verified'])) {
        return;
    }

    $verification = verify_system_license();
    if ($verification['status'] === true) {
        $_SESSION['license_verified'] = true;
    } else {
        error_log('License verification failed: ' . $verification['message']);
        http_response_code(403);
        exit;
    }
}

function get_system_fingerprint_safe(): string 
{
    // Return a truncated hash for display purposes
    $hash = get_system_fingerprint();
    return substr($hash, 0, 8) . '-' . substr($hash, 8, 8) . '-...';
}

function get_system_fingerprint(): string
{
    $os = PHP_OS_FAMILY;
    $serial = '';

    if ($os === 'Windows') {
        // 1. Try Disk Serial
        $output = [];
        // wmic diskdrive get SerialNumber
        @exec('wmic diskdrive get SerialNumber 2>&1', $output);
        foreach ($output as $line) {
            $line = trim($line);
            if ($line !== '' && stripos($line, 'SerialNumber') === false && stripos($line, 'wmic') === false) {
                $serial .= $line;
                break; // Take first one
            }
        }

        // 2. Try BIOS UUID
        $output = [];
        @exec('wmic csproduct get UUID 2>&1', $output);
        foreach ($output as $line) {
            $line = trim($line);
            if ($line !== '' && stripos($line, 'UUID') === false && stripos($line, 'wmic') === false) {
                $serial .= $line;
                break;
            }
        }
    } else {
        // Mac/Linux Fallback
        if ($os === 'Darwin') {
            $output = [];
            @exec('system_profiler SPHardwareDataType | grep "Serial Number" 2>&1', $output);
            if (!empty($output)) {
                $parts = explode(':', $output[0]);
                $serial = trim(end($parts));
            }
        } else {
            $serial = @file_get_contents('/etc/machine-id');
        }
    }

    if (empty($serial)) {
        // Fallback to hostname if hardware query fails (e.g. permission denied)
        // This is less secure but prevents app from crashing on compatible systems
        $serial = php_uname('n');
    }
    
    // Salt and Hash
    // Using a fixed salt ensures consistent hashing across restarts
    return hash('sha256', 'SRMR_APP_LICENSE_SALT_v1_' . trim((string)$serial));
}

function ensure_license_table(): void
{
    if (table_exists('system_license')) { return; }
    try {
        $pdo = db();
        $driver = db_driver();
        if ($driver === 'sqlite') {
             $pdo->exec('CREATE TABLE IF NOT EXISTS system_license (id INTEGER PRIMARY KEY AUTOINCREMENT, license_hash TEXT NOT NULL, registered_at TEXT)');
        } elseif ($driver === 'pgsql') {
             $pdo->exec('CREATE TABLE IF NOT EXISTS system_license (id SERIAL PRIMARY KEY, license_hash VARCHAR(128) NOT NULL, registered_at TIMESTAMP)');
        } else {
             $pdo->exec('CREATE TABLE IF NOT EXISTS system_license (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, license_hash VARCHAR(128) NOT NULL, registered_at DATETIME)');
        }
    } catch (Throwable $e) {
        error_log('Failed to create license table: ' . $e->getMessage());
    }
}

function verify_system_license(): array
{
    try {
        ensure_license_table();
        $pdo = db();
        
        // Get the single license record
        $stmt = $pdo->query('SELECT * FROM system_license ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $currentHash = get_system_fingerprint();

        if (!$row) {
            $auto = strtolower((string) (getenv('LICENSE_AUTO_REGISTER') ?: '1'));
            if (in_array($auto, ['1','true','yes'], true)) {
                $stmt = $pdo->prepare('INSERT INTO system_license (license_hash, registered_at) VALUES (:hash, :now)');
                $stmt->execute([
                    'hash' => $currentHash,
                    'now' => date('Y-m-d H:i:s')
                ]);
                return ['status' => true, 'message' => 'System registered successfully.'];
            }
            return ['status' => false, 'message' => 'Not activated. Contact EL support team.'];
        }

        $storedHash = $row['license_hash'];
        if ($storedHash === $currentHash) {
            return ['status' => true, 'message' => 'License valid.'];
        }

        return [
            'status' => false, 
            'message' => 'This application is bound to a different hardware configuration. Access denied.'
        ];

    } catch (Throwable $e) {
        return ['status' => false, 'message' => 'License System Error: ' . $e->getMessage()];
    }
}

function get_license_info(): ?array
{
    try {
        ensure_license_table();
        $pdo = db();
        $stmt = $pdo->query('SELECT * FROM system_license ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }

        $row['current_fingerprint'] = get_system_fingerprint();
        $row['is_valid'] = ($row['license_hash'] === $row['current_fingerprint']);
        
        return $row;
    } catch (Throwable $e) {
        return null;
    }
}
