<?php
declare(strict_types=1);

function master_bootstrap(): void
{
    // Database-backed now; nothing to bootstrap into the session.
}

function ensure_client_code_column(): void
{
    try {
        $pdo = db();
        $exists = (bool) $pdo->query("SELECT 1 FROM information_schema.columns WHERE LOWER(table_name)='client' AND LOWER(column_name)='client_code' LIMIT 1")->fetchColumn();
        if (!$exists) {
            $pdo->exec("ALTER TABLE client ADD COLUMN Client_Code VARCHAR(16) NULL");
        }
    } catch (Exception $exception) {
        error_log('Failed to ensure Client_Code: ' . $exception->getMessage());
    }
}

function client_has_login_columns(): bool
{
    static $has;
    if ($has !== null) { return $has; }
    try {
        $pdo = db();
        $login = (bool) $pdo->query("SELECT 1 FROM information_schema.columns WHERE LOWER(table_name)='client' AND LOWER(column_name)='login_id' LIMIT 1")->fetchColumn();
        $cred = (bool) $pdo->query("SELECT 1 FROM information_schema.columns WHERE LOWER(table_name)='client' AND LOWER(column_name)='credentials' LIMIT 1")->fetchColumn();
        $inv = (bool) $pdo->query("SELECT 1 FROM information_schema.columns WHERE LOWER(table_name)='client' AND LOWER(column_name)='invoiced' LIMIT 1")->fetchColumn();
        $has = ($login && $cred && $inv);
    } catch (\Exception $exception) {
        error_log('Client login column detect failed: ' . $exception->getMessage());
        $has = false;
    }
    return $has;
}

function ensure_client_login_columns(): void
{
    try {
        $pdo = db();
        if (!(bool) $pdo->query("SELECT 1 FROM information_schema.columns WHERE LOWER(table_name)='client' AND LOWER(column_name)='login_id' LIMIT 1")->fetchColumn()) {
            $pdo->exec('ALTER TABLE client ADD COLUMN Login_ID VARCHAR(120) NULL');
        }
        if (!(bool) $pdo->query("SELECT 1 FROM information_schema.columns WHERE LOWER(table_name)='client' AND LOWER(column_name)='credentials' LIMIT 1")->fetchColumn()) {
            $pdo->exec('ALTER TABLE client ADD COLUMN Credentials VARCHAR(255) NULL');
        }
        if (!(bool) $pdo->query("SELECT 1 FROM information_schema.columns WHERE LOWER(table_name)='client' AND LOWER(column_name)='invoiced' LIMIT 1")->fetchColumn()) {
            $pdo->exec('ALTER TABLE client ADD COLUMN Invoiced BOOLEAN DEFAULT FALSE');
        }
    } catch (\Exception $exception) {
        error_log('Failed to ensure login/invoiced columns: ' . $exception->getMessage());
    }
}

function client_service_map_insert_sql(): string
{
    $driver = db_driver();
    if ($driver === 'pgsql') {
        return 'INSERT INTO client_service_map (C_ID, Service_Name) VALUES (:client, :service) ON CONFLICT DO NOTHING';
    }
    if ($driver === 'sqlite') {
        return 'INSERT OR IGNORE INTO client_service_map (C_ID, Service_Name) VALUES (:client, :service)';
    }
    return 'INSERT IGNORE INTO client_service_map (C_ID, Service_Name) VALUES (:client, :service)';
}

function client_code_padding(): int
{
    ensure_client_code_column();
    $pdo = db();
    $stmt = $pdo->query("SELECT COALESCE(MAX(LENGTH(SUBSTRING(Client_Code,3))),0) FROM client WHERE Client_Code LIKE 'CA%'");
    $len = (int) ($stmt->fetchColumn() ?: 0);
    return max(3, $len);
}

function generate_next_client_code(): string
{
    ensure_client_code_column();
    $pdo = db();
    $len = client_code_padding();
    $stmt = $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(Client_Code,3) AS UNSIGNED)),0) FROM client WHERE Client_Code LIKE 'CA%'");
    $max = (int) ($stmt->fetchColumn() ?: 0);
    $num = $max + 1;
    return 'CA' . str_pad((string) $num, $len, '0', STR_PAD_LEFT);
}

function generate_next_client_id_for_name(string $name): string
{
    ensure_client_code_column();
    $pdo = db();
    $trim = trim($name);
    $first = '';
    for ($i = 0; $i < strlen($trim); $i++) {
        $ch = $trim[$i];
        if (preg_match('/[A-Za-z0-9]/', $ch)) { $first = strtoupper($ch); break; }
    }
    if ($first === '') { $first = 'X'; }
    $stmt = $pdo->prepare('SELECT Client_Code FROM client WHERE Client_Code LIKE :prefix');
    $stmt->execute(['prefix' => $first . '%']);
    $max = 0;
    foreach ((array) $stmt->fetchAll(PDO::FETCH_COLUMN) as $code) {
        $codeStr = (string) $code;
        if (preg_match('/^' . preg_quote($first, '/') . '(\d{4})$/', $codeStr, $m)) {
            $n = (int) ($m[1] ?? 0);
            if ($n > $max) { $max = $n; }
        }
    }
    $num = $max + 1;
    return $first . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
}

function backfill_missing_client_codes(): void
{
    ensure_client_code_column();
    $pdo = db();
    $len = client_code_padding();
    $seqStmt = $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(Client_Code,3) AS UNSIGNED)),0) FROM client WHERE Client_Code LIKE 'CA%'");
    $seq = (int) ($seqStmt->fetchColumn() ?: 0);
    $ids = $pdo->query("SELECT C_ID FROM client WHERE Client_Code IS NULL ORDER BY C_ID")->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) { return; }
    $update = $pdo->prepare('UPDATE client SET Client_Code = :code WHERE C_ID = :id');
    foreach ($ids as $id) {
        $seq++;
        $code = 'CA' . str_pad((string) $seq, $len, '0', STR_PAD_LEFT);
        try { $update->execute(['code' => $code, 'id' => $id]); } catch (\Exception $e) { /* ignore */ }
    }
}

function client_has_poc_phone_column(): bool
{
    static $has;
    if ($has !== null) { return $has; }
    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT 1 FROM information_schema.columns WHERE LOWER(table_name)='client' AND LOWER(column_name)='poc_phone' LIMIT 1");
        $has = (bool) $stmt->fetchColumn();
    } catch (\Exception $exception) {
        error_log('POC phone column detection failed: ' . $exception->getMessage());
        $has = false;
    }
    return $has;
}

function ensure_client_poc_phone_column(): void
{
    if (client_has_poc_phone_column()) { return; }
    try {
        $pdo = db();
        $pdo->exec('ALTER TABLE client ADD COLUMN POC_Phone VARCHAR(20) NULL');
    } catch (\Exception $exception) {
        error_log('Failed to add POC_Phone: ' . $exception->getMessage());
    }
}

function employee_has_number_column(): bool
{
    static $has;
    if ($has !== null) { return $has; }
    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT 1 FROM information_schema.columns WHERE LOWER(table_name)='employee' AND LOWER(column_name)='employee_number' LIMIT 1");
        $has = (bool) $stmt->fetchColumn();
    } catch (\Exception $exception) {
        error_log('Employee code column detect failed: ' . $exception->getMessage());
        $has = false;
    }
    return $has;
}

function ensure_employee_number_column(): void
{
    if (employee_has_number_column()) { return; }
    try {
        $pdo = db();
        $pdo->exec("ALTER TABLE employee ADD COLUMN Employee_Number VARCHAR(32) NULL");
    } catch (\Exception $exception) {
        error_log('Failed to add Employee_Number: ' . $exception->getMessage());
    }
}

function ensure_employee_primary_key_autoincrement(): void
{
    try {
        $pdo = db();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver !== 'mysql') { return; }
        $row = $pdo->query('SHOW CREATE TABLE employee')->fetch(PDO::FETCH_ASSOC);
        $ddl = (string) ($row['Create Table'] ?? $row['Create Table'] ?? '');
        $needsPk = (stripos($ddl, 'PRIMARY KEY (`EMP_ID`)') === false);
        $needsAuto = (stripos($ddl, '`EMP_ID`') !== false) && (stripos($ddl, 'AUTO_INCREMENT') === false);
        if ($needsAuto) { $pdo->exec('ALTER TABLE employee MODIFY EMP_ID INT UNSIGNED NOT NULL AUTO_INCREMENT'); }
        if ($needsPk) {
            if (stripos($ddl, 'PRIMARY KEY') !== false && stripos($ddl, 'PRIMARY KEY (`EMP_ID`)') === false) { $pdo->exec('ALTER TABLE employee DROP PRIMARY KEY'); }
            $pdo->exec('ALTER TABLE employee ADD PRIMARY KEY (EMP_ID)');
        }
    } catch (\Exception $exception) {
        error_log('Employee PK ensure failed: ' . $exception->getMessage());
    }
}

function employee_number_padding(): int
{
    try {
        ensure_employee_number_column();
        $pdo = db();
        $len = (int) ($pdo->query("SELECT COALESCE(MAX(LENGTH(SUBSTRING(Employee_Number,5))),0) FROM employee WHERE Employee_Number LIKE 'SRMR%'")->fetchColumn() ?: 0);
        return max(4, $len);
    } catch (\Exception $e) {
        return 3;
    }
}

function normalize_employee_number_input(string $code): string
{
    $c = trim($code);
    if ($c === '') { return ''; }
    $upper = strtoupper($c);
    $num = '';
    if (str_starts_with($upper, 'SRMR')) {
        $num = preg_replace('/\D/', '', substr($upper, 4));
    } else {
        $num = preg_replace('/\D/', '', $upper);
    }
    if ($num === '') { return generate_next_employee_number(); }
    return 'SRMR' . str_pad($num, employee_number_padding(), '0', STR_PAD_LEFT);
}

function generate_next_employee_number(): string
{
    ensure_employee_number_column();
    $pdo = db();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $castType = $driver === 'pgsql' ? 'INTEGER' : 'UNSIGNED';
    $stmt = $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(Employee_Number,5) AS $castType)),0) FROM employee WHERE Employee_Number LIKE 'SRMR%'");
    $max = (int) ($stmt->fetchColumn() ?: 0);
    $num = $max + 1;
    return 'SRMR' . str_pad((string) $num, employee_number_padding(), '0', STR_PAD_LEFT);
}

function employee_number_from_id(int $id): string
{
    ensure_employee_number_column();
    return 'SRMR' . str_pad((string) $id, employee_number_padding(), '0', STR_PAD_LEFT);
}

function employee_email_is_nullable(): bool
{
    static $nullable;
    if ($nullable !== null) { return $nullable; }
    try {
        $pdo = db();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info('employee')");
            $nullable = false;
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                if (strtolower((string)($col['name'] ?? '')) === 'email') {
                    $nullable = ((int) ($col['notnull'] ?? 0)) === 0;
                    break;
                }
            }
        } else {
            $stmt = $pdo->query("SELECT CASE WHEN LOWER(is_nullable)='yes' THEN 1 ELSE 0 END AS is_null FROM information_schema.columns WHERE LOWER(table_name)='employee' AND LOWER(column_name)='email' LIMIT 1");
            $nullable = (bool) $stmt->fetchColumn();
        }
    } catch (\Exception $exception) {
        error_log('Employee email nullability detect failed: ' . $exception->getMessage());
        $nullable = false;
    }
    return $nullable;
}

function ensure_employee_email_nullable(): void
{
    if (employee_email_is_nullable()) { return; }
    try {
        $pdo = db();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            $pdo->exec('ALTER TABLE employee ALTER COLUMN Email DROP NOT NULL');
        } elseif ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE employee MODIFY COLUMN Email VARCHAR(150) NULL');
        } else {
            // SQLite: altering NOT NULL requires table rebuild; skip silently
        }
    } catch (Exception $exception) {
        error_log('Failed to make employee Email nullable: ' . $exception->getMessage());
    }
}

function get_clients(): array
{
    static $cache; static $cacheVersion;
    $version = $GLOBALS['CLIENT_CACHE_VERSION'] ?? null;
    if (is_float($version) || is_int($version)) {
        if ((microtime(true) - (float) $version) > 60) { $GLOBALS['CLIENT_CACHE_VERSION'] = microtime(true); $version = $GLOBALS['CLIENT_CACHE_VERSION']; }
    } else { $GLOBALS['CLIENT_CACHE_VERSION'] = microtime(true); $version = $GLOBALS['CLIENT_CACHE_VERSION']; }
    if ($cache !== null && $cacheVersion === $version) { return $cache; }
    try {
        ensure_client_poc_phone_column();
        ensure_client_code_column();
        ensure_client_login_columns();
        backfill_missing_client_codes();
        $pdo = db();
        $sql = 'SELECT 
                    C_ID AS c_id,
                    Client_Code AS client_code,
                    Name_of_Entity AS name_of_entity,
                    Address1 AS address1,
                    Address2 AS address2,
                        City AS city,
                    State AS state,
                    PIN AS pin,
                    Type_of_Entity AS type_of_entity,
                    Sub_Type_of_Entity AS sub_type_of_entity,
                    PAN_No AS pan_no,
                    TAN_No AS tan_no,
                    GST_Regn_No AS gst_regn_no,
                    Aadhaar_No AS aadhaar_no,
                    Date_of_Incorporation AS date_of_incorporation,
                    Name_of_CEO AS name_of_ceo,
                    Email_of_CEO AS email_of_ceo,
                    Name_of_POC AS name_of_poc,
                    Email_of_POC AS email_of_poc,
                    POC_Phone AS poc_phone,
                    Login_ID AS login_id,
                    Credentials AS credentials,
                    COALESCE(Invoiced, FALSE) AS invoiced,
                    Created_At, Updated_At
                FROM client
                ORDER BY Name_of_Entity';
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $services = [];
        $serviceStmt = $pdo->query('SELECT C_ID AS c_id, Service_Name AS service_name FROM client_service_map ORDER BY Service_Name');
        $serviceRows = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($serviceRows as $row) {
            $cid = (string) ($row['c_id'] ?? '');
            $name = (string) ($row['service_name'] ?? '');
            if ($cid !== '' && $name !== '') { $services[$cid][] = $name; }
        }

        $cache = array_map(static function (array $row) use ($services) {
            $idStr = (string) ($row['c_id'] ?? '');
            return [
                'c_id' => (string) ($row['c_id'] ?? ''),
                'name' => $row['name_of_entity'] ?? '',
                'code' => $row['client_code'] ?? null,
                'industry' => $row['type_of_entity'] ?? 'General',
                'entity_type' => $row['type_of_entity'] ?? null,
                'entity_subtype' => $row['sub_type_of_entity'] ?? null,
                'address' => [
                    'line1' => $row['address1'] ?? null,
                    'line2' => $row['address2'] ?? null,
                    'line3' => $row['address3'] ?? null,
                    'city' => $row['city'] ?? null,
                    'state' => $row['state'] ?? null,
                    'pin' => $row['pin'] ?? null,
                ],
                'pan' => $row['pan_no'] ?? null,
                'tan' => $row['tan_no'] ?? null,
                'gst' => $row['gst_regn_no'] ?? null,
                'aadhaar' => $row['aadhaar_no'] ?? null,
                'incorporated_on' => !empty($row['date_of_incorporation']) ? date('Y-m-d', strtotime((string) $row['date_of_incorporation'])) : null,
                'ceo_name' => $row['name_of_ceo'] ?? null,
                'ceo_email' => $row['email_of_ceo'] ?? null,
                'primary_contact' => $row['name_of_poc'] ?? ($row['name_of_ceo'] ?? ''),
                'email' => $row['email_of_poc'] ?? ($row['email_of_ceo'] ?? ''),
                'poc_name' => $row['name_of_poc'] ?? null,
                'poc_email' => $row['email_of_poc'] ?? null,
                'poc_phone' => $row['poc_phone'] ?? null,
                'login_id' => $row['login_id'] ?? null,
                'credentials' => $row['credentials'] ?? null,
                'invoiced' => (bool) ($row['invoiced'] ?? false),
                'services' => $services[$idStr] ?? [],
                'region' => $row['state'] ?? null,
            ];
        }, $rows);
        $cacheVersion = $version;
        return $cache;
    } catch (\Exception $exception) {
        error_log('Client fetch failed: ' . $exception->getMessage());
        return [];
    }
}

function update_client_services(int|string $clientId, array $services): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $delete = $pdo->prepare('DELETE FROM client_service_map WHERE C_ID = :client');
        $delete->execute(['client' => $clientId]);

        $insert = $pdo->prepare('INSERT INTO client_service_map (C_ID, Service_Name) VALUES (:client, :service)');
        foreach (array_values(array_unique(array_filter($services))) as $service) {
            $insert->execute(['client' => $clientId, 'service' => $service]);
        }

        $pdo->commit();
        return true;
    } catch (\Exception $exception) {
        $pdo->rollBack();
        error_log('Client service update failed: ' . $exception->getMessage());
        return false;
    }
}

function create_client_record(array $clientData, array $services = []): ?string
{
    try {
        ensure_client_poc_phone_column();
        ensure_client_code_column();
        $pdo = db();
        ensure_client_login_columns();
        $stmt = $pdo->prepare('INSERT INTO client (
                C_ID,
                Name_of_Entity,
                Type_of_Entity,
                Sub_Type_of_Entity,
                Address1,
                Address2,
                City,
                State,
                PIN,
                Client_Code,
                PAN_No,
                TAN_No,
                GST_Regn_No,
                Aadhaar_No,
                Date_of_Incorporation,
                Name_of_CEO,
                Email_of_CEO,
                Name_of_POC,
                Email_of_POC,
                POC_Phone,
                Login_ID,
                Credentials,
                Invoiced
            ) VALUES (
                :c_id,
                :name,
                :entity_type,
                :entity_subtype,
                :address1,
                :address2,
                :city,
                :state,
                :pin,
                :client_code,
                :pan,
                :tan,
                :gst,
                :aadhaar,
                :incorporated_on,
                :ceo_name,
                :ceo_email,
                :primary_contact,
                :contact_email,
                :pco_phone,
                :login_id,
                :credentials,
                :invoiced
            )');

        $inputCode = strtoupper(substr((string) ($clientData['client_code'] ?? ''), 0, 120));
        if ($inputCode !== '') {
            $cid = $inputCode;
            $code = substr($cid, 0, 16);
        } else {
            $generated = generate_next_client_id_for_name((string) ($clientData['name'] ?? ''));
            $cid = substr($generated, 0, 120);
            $code = substr($cid, 0, 16);
        }
        $stmt->execute([
            'c_id' => $cid,
            'name' => $clientData['name'],
            'entity_type' => $clientData['entity_type'],
            'entity_subtype' => $clientData['entity_subtype'] ?? null,
            'address1' => $clientData['address1'] ?? null,
            'address2' => $clientData['address2'] ?? null,
            'city' => $clientData['city'] ?? null,
            'state' => $clientData['state'] ?? null,
            'pin' => $clientData['pin'] ?? null,
            'client_code' => $code,
            'pan' => $clientData['pan'] ?? null,
            'tan' => $clientData['tan'] ?? null,
            'gst' => $clientData['gst'] ?? null,
            'aadhaar' => $clientData['aadhaar'] ?? null,
            'incorporated_on' => $clientData['incorporated_on'] !== '' ? $clientData['incorporated_on'] : null,
            'ceo_name' => $clientData['ceo_name'] ?? null,
            'ceo_email' => $clientData['ceo_email'] ?? null,
            'primary_contact' => $clientData['primary_contact'],
            'contact_email' => $clientData['contact_email'],
            'pco_phone' => $clientData['pco_phone'] ?? null,
            'login_id' => ($clientData['login_id'] ?? '') !== '' ? $clientData['login_id'] : null,
            'credentials' => ($clientData['credentials'] ?? '') !== '' ? $clientData['credentials'] : null,
            'invoiced' => (($clientData['invoiced'] ?? false) ? 1 : 0),
        ]);

        if (!empty($services)) {
            update_client_services($cid, $services);
        }

        return $cid;
    } catch (\Exception $exception) {
        error_log('Client creation failed: ' . $exception->getMessage());
        return null;
    }
}

function bulk_import_clients_csv(string $filePath, array $actor = [], string $strategy = 'skip'): array
{
    $result = ['ok' => 0, 'updated' => 0, 'fail' => 0, 'errors' => []];
    if (!is_file($filePath)) { $result['errors'][] = 'CSV not found'; return $result; }
    $fh = fopen($filePath, 'r'); if (!$fh) { $result['errors'][] = 'Unable to open CSV'; return $result; }
    ensure_client_poc_phone_column();
    ensure_client_code_column();
    ensure_client_login_columns();
    $pdo = db();
    $insert = $pdo->prepare('INSERT INTO client (
                C_ID, Name_of_Entity, Type_of_Entity, Sub_Type_of_Entity,
                Address1, Address2, City, State, PIN, Client_Code,
                PAN_No, TAN_No, GST_Regn_No, Aadhaar_No, Date_of_Incorporation,
                Name_of_CEO, Email_of_CEO, Name_of_POC, Email_of_POC, POC_Phone,
                Login_ID, Credentials, Invoiced
            ) VALUES (
                :c_id, :name, :entity_type, :entity_subtype,
                :address1, :address2, :city, :state, :pin, :client_code,
                :pan, :tan, :gst, :aadhaar, :incorporated_on,
                :ceo_name, :ceo_email, :primary_contact, :contact_email, :pco_phone,
                :login_id, :credentials, :invoiced
            )');
    $map = $pdo->prepare(client_service_map_insert_sql());
    $header = null;
    while (($data = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        if ($header === null) { $header = array_map(static fn($h) => strtolower(trim((string) $h)), $data); continue; }
        $row = [];
        for ($i=0; $i<count($header); $i++) { $row[$header[$i] ?? ('col'.$i)] = trim((string)($data[$i] ?? '')); }
        if (empty(array_filter($row))) { continue; }
        $clientData = [
            'name' => $row['name'] ?? '',
            'client_code' => substr((string) ($row['client_code'] ?? ''), 0, 120),
            'entity_type' => $row['entity_type'] ?? '',
            'entity_subtype' => $row['entity_subtype'] ?? '',
            'address1' => $row['address1'] ?? '',
            'address2' => $row['address2'] ?? '',
            'city' => $row['city'] ?? '',
            'state' => $row['state'] ?? '',
            'pin' => $row['pin'] ?? '',
            'pan' => $row['pan'] ?? '',
            'tan' => $row['tan'] ?? '',
            'gst' => $row['gst'] ?? '',
            'aadhaar' => $row['aadhaar'] ?? '',
            'incorporated_on' => $row['incorporated_on'] ?? '',
            'ceo_name' => $row['ceo_name'] ?? '',
            'ceo_email' => $row['ceo_email'] ?? '',
            'primary_contact' => $row['primary_contact'] ?? '',
            'contact_email' => $row['contact_email'] ?? '',
            'pco_phone' => $row['pco_phone'] ?? '',
            'login_id' => $row['log'] ?? ($row['login_id'] ?? ($actor['email'] ?? '')),
            'credentials' => $row['credentials'] ?? ($row['password'] ?? ''),
            'invoiced' => strtolower(trim((string)($row['invoiced'] ?? ''))) === 'yes',
        ];
        try {
            $cid = substr((string) ($clientData['client_code'] ?? ''), 0, 120);
            $code = substr($cid, 0, 16);
            $inc = (string) ($clientData['incorporated_on'] ?? '');
            $incTrim = strtolower(trim($inc));
            $incDate = null;
            if ($incTrim !== '' && !in_array($incTrim, ['na','n/a','null','none','-'], true)) {
                $val = str_replace('/', '-', trim($inc));
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                    $d = DateTime::createFromFormat('Y-m-d', $val);
                    if ($d) { $incDate = $d->format('Y-m-d'); }
                } elseif (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $val, $m)) {
                    $d = DateTime::createFromFormat('d-m-Y', $val);
                    if ($d) { $incDate = $d->format('Y-m-d'); }
                }
            }
            $existing = find_client($cid);
            $servicesRaw = array_map('trim', explode('|', (string) ($row['services'] ?? '')));
            $services = array_values(array_unique(array_filter($servicesRaw, static function($s){
                $x = strtolower($s);
                return $x !== '' && !in_array($x, ['na','n/a','null','none','-'], true);
            })));
            if ($existing) {
                update_client_record_partial($cid, $clientData);
                foreach ($services as $svc) { $map->execute(['client' => $cid, 'service' => $svc]); }
                if (!empty($actor)) {
                    client_dup_store_add((int) $actor['id'], [
                        'c_id' => $cid,
                        'name' => $clientData['name'] ?? ($existing['name'] ?? ''),
                        'pan' => $clientData['pan'] ?? ($existing['pan'] ?? ''),
                        'tan' => $clientData['tan'] ?? ($existing['tan'] ?? ''),
                    ]);
                    write_audit('client', 0, 'bulk_update_from_csv', $actor, ['c_id' => $cid]);
                }
                $result['updated']++;
                continue;
            }
            $insert->execute([
                'c_id' => $cid,
                'name' => $clientData['name'],
                'entity_type' => $clientData['entity_type'],
                'entity_subtype' => $clientData['entity_subtype'] !== '' ? $clientData['entity_subtype'] : null,
                'address1' => $clientData['address1'] !== '' ? $clientData['address1'] : null,
                'address2' => $clientData['address2'] !== '' ? $clientData['address2'] : null,
                'city' => $clientData['city'] !== '' ? $clientData['city'] : null,
                'state' => $clientData['state'] !== '' ? $clientData['state'] : null,
                'pin' => $clientData['pin'] !== '' ? $clientData['pin'] : null,
                'client_code' => $code,
                'pan' => $clientData['pan'] !== '' ? $clientData['pan'] : null,
                'tan' => $clientData['tan'] !== '' ? $clientData['tan'] : null,
                'gst' => $clientData['gst'] !== '' ? $clientData['gst'] : null,
                'aadhaar' => $clientData['aadhaar'] !== '' ? $clientData['aadhaar'] : null,
                'incorporated_on' => $incDate,
                'ceo_name' => $clientData['ceo_name'] !== '' ? $clientData['ceo_name'] : null,
                'ceo_email' => $clientData['ceo_email'] !== '' ? $clientData['ceo_email'] : null,
                'primary_contact' => $clientData['primary_contact'],
                'contact_email' => $clientData['contact_email'],
                'pco_phone' => $clientData['pco_phone'] !== '' ? $clientData['pco_phone'] : null,
                'login_id' => ($clientData['login_id'] ?? '') !== '' ? $clientData['login_id'] : null,
                'credentials' => ($clientData['credentials'] ?? '') !== '' ? $clientData['credentials'] : null,
                'invoiced' => (($clientData['invoiced'] ?? false) ? 1 : 0),
            ]);
            foreach ($services as $svc) { $map->execute(['client' => $cid, 'service' => $svc]); }
            if (!empty($actor)) { write_audit('client', 0, 'create', $actor, ['c_id' => $cid, 'data' => $clientData, 'services' => $services]); }
            $result['ok']++;
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            // Handle duplicates if strategy is overwrite
            if (stripos($msg, 'duplicate') !== false || preg_match('/SQLSTATE\[(?:23000|23505)\]/', $msg)) {
                if ($strategy === 'overwrite') {
                    try {
                        update_client_record_partial($cid, $clientData);
                        foreach ($services as $svc) { $map->execute(['client' => $cid, 'service' => $svc]); }
                        if (!empty($actor)) {
                            client_dup_store_add((int) $actor['id'], [
                                'c_id' => $cid,
                                'name' => $clientData['name'] ?? '',
                                'pan' => $clientData['pan'] ?? '',
                                'tan' => $clientData['tan'] ?? '',
                            ]);
                            write_audit('client', 0, 'bulk_overwrite', $actor, ['c_id' => $cid]);
                        }
                        $result['updated']++;
                        continue;
                    } catch (\Exception $ex) {
                        $msg = $ex->getMessage();
                    }
                }
            }
            if ($strategy === 'skip') {
                // Auto-update existing on duplicates
                try {
                    update_client_record_partial($cid, $clientData);
                    foreach ($services as $svc) { $map->execute(['client' => $cid, 'service' => $svc]); }
                    if (!empty($actor)) {
                        client_dup_store_add((int) $actor['id'], [
                            'c_id' => $cid,
                            'name' => $clientData['name'] ?? '',
                            'pan' => $clientData['pan'] ?? '',
                            'tan' => $clientData['tan'] ?? '',
                        ]);
                        write_audit('client', 0, 'bulk_update_from_csv', $actor, ['c_id' => $cid]);
                    }
                    $result['updated']++;
                    continue;
                } catch (\Exception $ex2) {
                    $msg = $ex2->getMessage();
                }
            }
            $result['fail']++;
            if (empty($result['errors'])) { $result['errors'][] = $msg; }
        }
    }
    fclose($fh);
    return $result;
}

function bulk_import_staff_csv(string $filePath, array $actor = []): array
{
    $result = ['ok' => 0, 'fail' => 0, 'errors' => []];
    if (!is_file($filePath)) { $result['errors'][] = 'CSV not found'; return $result; }
    write_staff_bulk_log('CSV start ' . $filePath);
    $fh = fopen($filePath, 'r'); if (!$fh) { $result['errors'][] = 'Unable to open CSV'; return $result; }
    $roles = get_roles();
    $roleMap = [];
    foreach ($roles as $r) { $roleMap[strtolower((string) ($r['name'] ?? ''))] = (int) ($r['id'] ?? 0); }
    $header = null;
    while (($data = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        if ($header === null) { $header = array_map(static fn($h) => strtolower(trim((string) $h)), $data); continue; }
        $row = [];
        for ($i=0; $i<count($header); $i++) { $row[$header[$i] ?? ('col'.$i)] = trim((string)($data[$i] ?? '')); }
        if (empty(array_filter($row))) { continue; }
        $fullName = $row['full_name'] ?? '';
        $email = normalize_email_input((string) ($row['email'] ?? ''));
        $code = $row['emp_id'] ?? ($row['employee_number'] ?? ($row['employee_code'] ?? ''));
        $roleName = (string) ($row['role'] ?? '');
        $password = $row['password'] ?? '';
        $roleId = role_id_by_label($roleName);
        $errs = [];
        if ($fullName === '') { $errs['full_name'] = 1; }
        if ($email === '') { $errs['email'] = 1; }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errs['email'] = 1; }
        if ($roleId <= 0) { $errs['role'] = 1; }
        if (strlen($password) < 8) { $errs['password'] = 1; }
        if (!empty($errs)) { write_staff_bulk_log('Invalid row ' . ($email ?: $fullName)); $result['fail']++; if (empty($result['errors'])) { $result['errors'][] = 'Invalid row for ' . ($email ?: $fullName); } continue; }
        try {
            $existing = $email !== '' ? find_employee_by_email_any($email) : null;
        } catch (\Exception $e) {
            $existing = null;
        }
        if ($existing) {
            $updatePayload = [
                'full_name' => $fullName,
                'email' => $email,
                'role_id' => $roleId,
            ];
            $upd = update_employee_profile((int) ($existing['id'] ?? 0), $updatePayload);
            if (($upd['success'] ?? false)) {
                write_staff_bulk_log('Updated existing ' . $email . ' role=' . $roleName);
                try {
                    $info = find_employee((int) ($existing['id'] ?? 0));
                    if (empty($info['employee_number'])) { update_employee_number((int) ($existing['id'] ?? 0), employee_number_from_id((int) ($existing['id'] ?? 0))); }
                } catch (\Exception $e) {}
                if (!empty($actor)) { write_audit('staff', (int) ($existing['id'] ?? 0), 'update', $actor, ['email' => $email, 'role' => $roleName]); }
                $result['ok']++;
            } else {
                $result['fail']++;
                write_staff_bulk_log('Update failed ' . $email);
                if (empty($result['errors'])) { $result['errors'][] = 'Failed to update ' . $email; }
            }
            continue;
        }

        $creation = create_employee_profile($fullName, $email, $password, $roleId, ($code !== '' ? $code : null));
        if ($creation['success'] ?? false) {
            $newId = (int) ($creation['id'] ?? 0);
            $finalCode = employee_number_from_id($newId);
            write_staff_bulk_log('Created ' . $email . ' role=' . $roleName . ' code=' . $finalCode);
            try { update_employee_number($newId, $finalCode); } catch (\Exception $e) {}
            if (!empty($actor)) { write_audit('staff', (int) ($creation['id'] ?? 0), 'create', $actor, ['email' => $email, 'role' => $roleName]); }
            $result['ok']++;
        } else {
            $result['fail']++;
            $errKey = (string) ($creation['error'] ?? '');
            $detail = '';
            $sqlState = (string) ($creation['sqlstate'] ?? '');
            $driverCode = (string) ($creation['driver_code'] ?? '');
            if ($sqlState !== '' || $driverCode !== '') { $detail = ' [' . $sqlState . '/' . $driverCode . ']'; }
            if ($errKey === 'duplicate_emp_id') {
                write_staff_bulk_log('Duplicate emp_id ' . ($code ?: '') . $detail);
                if (empty($result['errors'])) { $result['errors'][] = 'Employee number already exists' . ($code !== '' ? (': ' . $code) : '') . $detail; }
            } elseif ($errKey === 'duplicate') {
                write_staff_bulk_log('Duplicate email ' . $email . $detail);
                if (empty($result['errors'])) { $result['errors'][] = 'Email already exists: ' . $email . $detail; }
            } else {
                write_staff_bulk_log('Create failed ' . ($email ?: $fullName) . $detail);
                if (empty($result['errors'])) { $result['errors'][] = 'DB error' . ($detail !== '' ? (' ' . $detail) : '') . ' for ' . ($email ?: $fullName); }
            }
        }
    }
    fclose($fh);
    write_staff_bulk_log('CSV summary ok=' . $result['ok'] . ' fail=' . $result['fail']);
    return $result;
}
function update_client_record(int|string $clientId, array $clientData): bool
{
    try {
        ensure_client_poc_phone_column();
        $pdo = db();
        ensure_client_login_columns();
        $stmt = $pdo->prepare('UPDATE client SET
                Name_of_Entity = :name,
                Type_of_Entity = :entity_type,
                Sub_Type_of_Entity = :entity_subtype,
                Address1 = :address1,
                Address2 = :address2,
                City = :city,
                State = :state,
                PIN = :pin,
                PAN_No = :pan,
                TAN_No = :tan,
                GST_Regn_No = :gst,
                Aadhaar_No = :aadhaar,
                Date_of_Incorporation = :incorporated_on,
                Name_of_CEO = :ceo_name,
                Email_of_CEO = :ceo_email,
                Name_of_POC = :primary_contact,
                Email_of_POC = :contact_email,
                POC_Phone = :pco_phone,
                Login_ID = COALESCE(:login_id, Login_ID),
                Credentials = COALESCE(:credentials, Credentials),
                Invoiced = COALESCE(:invoiced, Invoiced)
            WHERE C_ID = :id');
        return $stmt->execute([
            'id' => $clientId,
            'name' => $clientData['name'],
            'entity_type' => $clientData['entity_type'],
            'entity_subtype' => $clientData['entity_subtype'] ?? null,
            'address1' => $clientData['address1'] ?? null,
            'address2' => $clientData['address2'] ?? null,
            'city' => $clientData['city'] ?? null,
            'state' => $clientData['state'] ?? null,
            'pin' => $clientData['pin'] ?? null,
            'pan' => $clientData['pan'] ?? null,
            'tan' => $clientData['tan'] ?? null,
            'gst' => $clientData['gst'] ?? null,
            'aadhaar' => $clientData['aadhaar'] ?? null,
            'incorporated_on' => $clientData['incorporated_on'] !== '' ? $clientData['incorporated_on'] : null,
            'ceo_name' => $clientData['ceo_name'] ?? null,
            'ceo_email' => $clientData['ceo_email'] ?? null,
            'primary_contact' => $clientData['primary_contact'],
            'contact_email' => $clientData['contact_email'],
            'pco_phone' => $clientData['pco_phone'] ?? null,
            'login_id' => ($clientData['login_id'] ?? null) ?: null,
            'credentials' => ($clientData['credentials'] ?? null) ?: null,
            'invoiced' => isset($clientData['invoiced']) ? (int) ((bool) $clientData['invoiced']) : null,
        ]);
    } catch (\Exception $exception) {
        error_log('Client update failed: ' . $exception->getMessage());
        return false;
    }
}

function compute_client_diff(array $current, array $incoming): array
{
    $map = [
        'name' => 'name',
        'entity_type' => 'entity_type',
        'entity_subtype' => 'entity_subtype',
        'address1' => ['address1','address.line1'],
        'address2' => ['address2','address.line2'],
        'city' => ['city','address.city'],
        'state' => ['state','address.state'],
        'pin' => ['pin','address.pin'],
        'pan' => 'pan',
        'tan' => 'tan',
        'gst' => 'gst',
        'aadhaar' => 'aadhaar',
        'incorporated_on' => 'incorporated_on',
        'ceo_name' => 'ceo_name',
        'ceo_email' => 'ceo_email',
        'primary_contact' => ['name_of_poc','primary_contact'],
        'contact_email' => ['email_of_poc','contact_email'],
        'pco_phone' => 'pco_phone',
        'login_id' => 'login_id',
        'credentials' => 'credentials',
        'invoiced' => 'invoiced',
    ];
    $diffs = [];
    foreach ($map as $label => $keys) {
        $keysList = is_array($keys) ? $keys : [$keys];
        $new = null;
        foreach ($keysList as $k) { if (array_key_exists($k, $incoming)) { $new = $incoming[$k]; break; } }
        $old = $current[$label] ?? null;
        if ($label === 'invoiced') { $new = (int) ((bool) ($new ?? false)); $old = (int) ((bool) ($old ?? false)); }
        if ($new === null || $new === '' || $new === $old) { continue; }
        $diffs[] = ['field' => $label, 'old' => $old, 'new' => $new];
    }
    return $diffs;
}

function delete_client(int|string $clientId): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $servicesStmt = $pdo->prepare('SELECT DISTINCT Service_Name FROM client_service_map WHERE C_ID = :id');
        $servicesStmt->execute(['id' => $clientId]);
        $names = array_values(array_filter(array_map(static function($r){ return (string)($r['Service_Name'] ?? ''); }, $servicesStmt->fetchAll(PDO::FETCH_ASSOC))));

        $pdo->prepare('DELETE FROM client_service_map WHERE C_ID = :id')->execute(['id' => $clientId]);
        $pdo->prepare('DELETE FROM client WHERE C_ID = :id')->execute(['id' => $clientId]);

        if (!empty($names)) {
            $check = $pdo->prepare('SELECT 1 FROM client_service_map WHERE Service_Name = :name LIMIT 1');
            $del = $pdo->prepare('DELETE FROM service_types WHERE Service_Name = :name');
            foreach ($names as $n) {
                $check->execute(['name' => $n]);
                if ($check->fetchColumn() === false) { $del->execute(['name' => $n]); }
            }
        }
        $pdo->commit();
        return true;
    } catch (\Exception $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('Client delete failed: ' . $exception->getMessage());
        return false;
    }
}

function delete_all_clients(): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $count = (int) ($pdo->query('SELECT COUNT(*) FROM client')->fetchColumn() ?: 0);
        $pdo->exec('DELETE FROM client_service_map');
        $pdo->exec('DELETE FROM client');
        $pdo->commit();
        if ($count > 0) {
            write_audit('client', 0, 'bulk_delete_all', current_user(), ['deleted' => $count]);
        }
        return true;
    } catch (\Exception $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('Bulk delete clients failed: ' . $exception->getMessage());
        return false;
    }
}

function get_service_type_records(): array
{
    try {
        $pdo = db();
        $stmt = $pdo->query('SELECT Service_ID AS id, Service_Name AS name FROM service_types ORDER BY Service_Name');
        return array_map(static function (array $row) {
            return [
                'id' => (int) $row['id'],
                'name' => $row['name'],
            ];
        }, $stmt->fetchAll());
    } catch (\Exception $exception) {
        error_log('Service catalog fallback: ' . $exception->getMessage());
        $fallback = array_values(app_data('service_types', []));
        return array_map(static function ($name, $index) {
            return [
                'id' => $index + 1,
                'name' => $name,
            ];
        }, $fallback, array_keys($fallback));
    }
}

function get_service_catalog(): array
{
    return array_column(get_service_type_records(), 'name');
}

function db_driver(): string
{
    static $driver;
    if ($driver !== null) { return $driver; }
    try {
        $val = db()->getAttribute(PDO::ATTR_DRIVER_NAME);
        $driver = is_string($val) ? strtolower($val) : 'mysql';
    } catch (\Exception $e) {
        $driver = 'mysql';
    }
    return $driver;
}

function table_exists(string $table): bool
{
    $t = strtolower($table);
    try {
        $pdo = db();
        $driver = db_driver();
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND lower(name) = :name LIMIT 1");
            $stmt->execute(['name' => $t]);
            return (bool) $stmt->fetchColumn();
        }
        $schemaFunc = $driver === 'pgsql' ? 'current_schema()' : 'DATABASE()';
        $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = $schemaFunc AND LOWER(table_name) = :name LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['name' => $t]);
        return (bool) $stmt->fetchColumn();
    } catch (\Exception $e) { return false; }
}

function ensure_entity_groups_table(): void
{
    if (table_exists('entity_groups')) { return; }
    try {
        $pdo = db();
        $drv = db_driver();
        if ($drv === 'pgsql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS entity_groups (Group_ID SERIAL PRIMARY KEY, Group_Name VARCHAR(120) NOT NULL UNIQUE)');
        } elseif ($drv === 'sqlite') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS entity_groups (Group_ID INTEGER PRIMARY KEY AUTOINCREMENT, Group_Name TEXT NOT NULL UNIQUE)');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS entity_groups (Group_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, Group_Name VARCHAR(120) NOT NULL UNIQUE)');
        }
    } catch (\Exception $e) { error_log('Create entity_groups failed: ' . $e->getMessage()); }
}

function ensure_entity_subtypes_table(): void
{
    if (table_exists('entity_subtypes')) { return; }
    try {
        $pdo = db();
        $drv = db_driver();
        if ($drv === 'pgsql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS entity_subtypes (Subtype_ID SERIAL PRIMARY KEY, Subtype_Name VARCHAR(120) NOT NULL UNIQUE)');
        } elseif ($drv === 'sqlite') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS entity_subtypes (Subtype_ID INTEGER PRIMARY KEY AUTOINCREMENT, Subtype_Name TEXT NOT NULL UNIQUE)');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS entity_subtypes (Subtype_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, Subtype_Name VARCHAR(120) NOT NULL UNIQUE)');
        }
    } catch (\Exception $e) { error_log('Create entity_subtypes failed: ' . $e->getMessage()); }
}

function ensure_client_events_table(): void
{
    if (table_exists('client_events')) { return; }
    try {
        $pdo = db();
        $drv = db_driver();
        if ($drv === 'pgsql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS client_events (Event_ID SERIAL PRIMARY KEY, C_ID VARCHAR(120) NOT NULL, Event_Name VARCHAR(150) NOT NULL, Event_Date DATE, Status VARCHAR(80), Notes TEXT)');
        } elseif ($drv === 'sqlite') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS client_events (Event_ID INTEGER PRIMARY KEY AUTOINCREMENT, C_ID TEXT NOT NULL, Event_Name TEXT NOT NULL, Event_Date TEXT, Status TEXT, Notes TEXT)');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS client_events (Event_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, C_ID VARCHAR(120) NOT NULL, Event_Name VARCHAR(150) NOT NULL, Event_Date DATE, Status VARCHAR(80), Notes TEXT)');
        }
        ensure_client_events_fk();
    } catch (\Exception $e) { error_log('Create client_events failed: ' . $e->getMessage()); }
}

function ensure_client_events_fk(): void
{
    $drv = db_driver();
    if ($drv === 'sqlite') { return; }
    try {
        $pdo = db();
        $pdo->exec('ALTER TABLE client_events ADD CONSTRAINT fk_client_events_client FOREIGN KEY (C_ID) REFERENCES client(C_ID)');
    } catch (\Exception $e) {
        // Ignore if constraint already exists or cannot be added
    }
}

// Entity Groups Catalog
function get_entity_group_records(): array
{
    ensure_entity_groups_table();
    try {
        $pdo = db();
        $stmt = $pdo->query('SELECT Group_ID AS id, Group_Name AS name FROM entity_groups ORDER BY Group_Name');
        return array_map(static function (array $row) {
            return ['id' => (int)($row['id'] ?? 0), 'name' => (string)($row['name'] ?? '')];
        }, $stmt->fetchAll());
    } catch (\Exception $e) {
        error_log('Entity groups fallback: ' . $e->getMessage());
        return [];
    }
}

function get_entity_group_catalog(): array
{
    return array_column(get_entity_group_records(), 'name');
}

function create_entity_group(string $name): bool
{
    try {
        $name = trim($name);
        if ($name === '') { return false; }
        ensure_entity_groups_table();
        $pdo = db();
        $stmt = $pdo->prepare('INSERT INTO entity_groups (Group_Name) VALUES (:name)');
        return $stmt->execute(['name' => $name]);
    } catch (PDOException $e) {
        if ((int)$e->getCode() !== 23505) { error_log('Entity group create failed: ' . $e->getMessage()); }
        return false;
    } catch (\Exception $e) { error_log('Entity group create failed: ' . $e->getMessage()); return false; }
}

function update_entity_group(int $id, string $name): bool
{
    $name = trim($name);
    if ($id <= 0 || $name === '') { return false; }
    try {
        ensure_entity_groups_table();
        $pdo = db();
        $stmt = $pdo->prepare('UPDATE entity_groups SET Group_Name = :name WHERE Group_ID = :id');
        return $stmt->execute(['name' => $name, 'id' => $id]);
    } catch (\Exception $e) { error_log('Entity group update failed: ' . $e->getMessage()); return false; }
}

function delete_entity_group(int $id): bool
{
    if ($id <= 0) { return false; }
    try {
        ensure_entity_groups_table();
        $pdo = db();
        $stmt = $pdo->prepare('DELETE FROM entity_groups WHERE Group_ID = :id');
        return $stmt->execute(['id' => $id]);
    } catch (\Exception $e) { error_log('Entity group delete failed: ' . $e->getMessage()); return false; }
}

// Entity Subtypes Catalog
function get_entity_subtype_records(): array
{
    ensure_entity_subtypes_table();
    try {
        $pdo = db();
        $stmt = $pdo->query('SELECT Subtype_ID AS id, Subtype_Name AS name FROM entity_subtypes ORDER BY Subtype_Name');
        return array_map(static function (array $row) {
            return ['id' => (int)($row['id'] ?? 0), 'name' => (string)($row['name'] ?? '')];
        }, $stmt->fetchAll());
    } catch (\Exception $e) {
        error_log('Entity subtypes fallback: ' . $e->getMessage());
        return [];
    }
}

function get_entity_subtype_catalog(): array
{
    return array_column(get_entity_subtype_records(), 'name');
}

function create_entity_subtype(string $name): bool
{
    try {
        $name = trim($name);
        if ($name === '') { return false; }
        ensure_entity_subtypes_table();
        $pdo = db();
        $stmt = $pdo->prepare('INSERT INTO entity_subtypes (Subtype_Name) VALUES (:name)');
        return $stmt->execute(['name' => $name]);
    } catch (PDOException $e) {
        if ((int)$e->getCode() !== 23505) { error_log('Entity subtype create failed: ' . $e->getMessage()); }
        return false;
    } catch (\Exception $e) { error_log('Entity subtype create failed: ' . $e->getMessage()); return false; }
}

function update_entity_subtype(int $id, string $name): bool
{
    $name = trim($name);
    if ($id <= 0 || $name === '') { return false; }
    try {
        ensure_entity_subtypes_table();
        $pdo = db();
        $stmt = $pdo->prepare('UPDATE entity_subtypes SET Subtype_Name = :name WHERE Subtype_ID = :id');
        return $stmt->execute(['name' => $name, 'id' => $id]);
    } catch (\Exception $e) { error_log('Entity subtype update failed: ' . $e->getMessage()); return false; }
}

function delete_entity_subtype(int $id): bool
{
    if ($id <= 0) { return false; }
    try {
        ensure_entity_subtypes_table();
        $pdo = db();
        $stmt = $pdo->prepare('DELETE FROM entity_subtypes WHERE Subtype_ID = :id');
        return $stmt->execute(['id' => $id]);
    } catch (\Exception $e) { error_log('Entity subtype delete failed: ' . $e->getMessage()); return false; }
}

// Client Events
function get_client_events(string $clientId): array
{
    ensure_client_events_table();
    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT Event_ID AS id, Event_Name AS name, Event_Date AS date, Status AS status, Notes AS notes FROM client_events WHERE C_ID = :cid ORDER BY COALESCE(Event_Date, Created_At) DESC');
        $stmt->execute(['cid' => (string)$clientId]);
        return array_map(static function(array $row){
            return [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'date' => (string)($row['date'] ?? ''),
                'status' => (string)($row['status'] ?? ''),
                'notes' => (string)($row['notes'] ?? ''),
            ];
        }, $stmt->fetchAll());
    } catch (\Exception $e) { error_log('Get client events failed: ' . $e->getMessage()); return []; }
}

function create_client_event(string $clientId, array $data): bool
{
    ensure_client_events_table();
    try {
        $pdo = db();
        $stmt = $pdo->prepare('INSERT INTO client_events (C_ID, Event_Name, Event_Date, Status, Notes) VALUES (:cid, :name, :date, :status, :notes)');
        return $stmt->execute([
            'cid' => (string)$clientId,
            'name' => trim((string)($data['name'] ?? '')),
            'date' => (string)($data['date'] ?? null),
            'status' => trim((string)($data['status'] ?? '')),
            'notes' => trim((string)($data['notes'] ?? '')),
        ]);
    } catch (\Exception $e) { error_log('Create client event failed: ' . $e->getMessage()); return false; }
}

function update_client_event(int $eventId, array $data): bool
{
    if ($eventId <= 0) { return false; }
    try {
        ensure_client_events_table();
        $pdo = db();
        $stmt = $pdo->prepare('UPDATE client_events SET Event_Name = :name, Event_Date = :date, Status = :status, Notes = :notes WHERE Event_ID = :id');
        return $stmt->execute([
            'id' => $eventId,
            'name' => trim((string)($data['name'] ?? '')),
            'date' => (string)($data['date'] ?? null),
            'status' => trim((string)($data['status'] ?? '')),
            'notes' => trim((string)($data['notes'] ?? '')),
        ]);
    } catch (\Exception $e) { error_log('Update client event failed: ' . $e->getMessage()); return false; }
}

function delete_client_event(int $eventId): bool
{
    if ($eventId <= 0) { return false; }
    try {
        ensure_client_events_table();
        $pdo = db();
        $stmt = $pdo->prepare('DELETE FROM client_events WHERE Event_ID = :id');
        return $stmt->execute(['id' => $eventId]);
    } catch (\Exception $e) { error_log('Delete client event failed: ' . $e->getMessage()); return false; }
}

function update_service_type(int $serviceId, string $newName): bool
{
    $newName = trim($newName);
    if ($serviceId <= 0 || $newName === '') {
        return false;
    }

    $pdo = null;
    try {
        $pdo = db();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT Service_Name FROM service_types WHERE Service_ID = :id FOR UPDATE');
        $stmt->execute(['id' => $serviceId]);
        $currentName = $stmt->fetchColumn();
        if ($currentName === false) {
            $pdo->rollBack();
            return false;
        }

        $update = $pdo->prepare('UPDATE service_types SET Service_Name = :name WHERE Service_ID = :id');
        $update->execute(['name' => $newName, 'id' => $serviceId]);

        if ($currentName !== $newName) {
            $mapUpdate = $pdo->prepare('UPDATE client_service_map SET Service_Name = :new WHERE Service_Name = :old');
            $mapUpdate->execute(['new' => $newName, 'old' => $currentName]);
        }

        $pdo->commit();
        return true;
    } catch (PDOException $exception) {
        if ($pdo !== null && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ((int) $exception->getCode() !== 23505) {
            error_log('Service type update failed: ' . $exception->getMessage());
        }
        return false;
    } catch (\Exception $exception) {
        if ($pdo !== null && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Service type update failed: ' . $exception->getMessage());
        return false;
    }
}

function delete_service_type(int $serviceId): bool
{
    if ($serviceId <= 0) {
        return false;
    }

    $pdo = null;
    try {
        $pdo = db();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT Service_Name FROM service_types WHERE Service_ID = :id FOR UPDATE');
        $stmt->execute(['id' => $serviceId]);
        $currentName = $stmt->fetchColumn();
        if ($currentName === false) {
            $pdo->rollBack();
            return false;
        }

        $delete = $pdo->prepare('DELETE FROM service_types WHERE Service_ID = :id');
        $delete->execute(['id' => $serviceId]);

        $mapDelete = $pdo->prepare('DELETE FROM client_service_map WHERE Service_Name = :name');
        $mapDelete->execute(['name' => $currentName]);

        $pdo->commit();
        return true;
    } catch (\Exception $exception) {
        if ($pdo !== null && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Service type deletion failed: ' . $exception->getMessage());
        return false;
    }
}

function create_service_type(string $name): bool
{
    try {
        $name = trim($name);
        if ($name === '') {
            return false;
        }

        $pdo = db();
        $stmt = $pdo->prepare('INSERT INTO service_types (Service_Name) VALUES (:name)');
        return $stmt->execute(['name' => $name]);
    } catch (PDOException $exception) {
        if ((int) $exception->getCode() !== 23505) {
            error_log('Service type creation failed: ' . $exception->getMessage());
        }
        return false;
    } catch (\Exception $exception) {
        error_log('Service type creation failed: ' . $exception->getMessage());
        return false;
    }
}

function get_org_directory(): array
{
    $cache = $GLOBALS['ORG_DIRECTORY_CACHE'] ?? null;
    if ($cache !== null) {
        return $cache;
    }

    $fallbackHierarchy = app_data('hierarchy', []);
    $fallbackProfiles = app_data('org_profiles', []);
    $fallbackOrders = [];
    $order = 0;
    foreach ($fallbackHierarchy as $level => $_) {
        $fallbackOrders[$level] = $order++;
    }
    $fallback = [
        'hierarchy' => $fallbackHierarchy,
        'profiles' => $fallbackProfiles,
        'levels' => $fallbackOrders,
    ];

    try {
        $pdo = db();
        $sql = 'SELECT 
                    EmployeeDetail_ID AS id,
                    Level_Name AS level,
                    COALESCE(Level_Order, 0) AS level_order,
                    Employee_Name AS name,
                    COALESCE(Display_Order, 0) AS person_order,
                    Title,
                    Email,
                    Location,
                    State,
                    Country,
                    Tenure,
                    Focus,
                    Bio,
                    Skills AS skills
                FROM employeedetails
                ORDER BY level_order, level, person_order, name';
        $rows = $pdo->query($sql)->fetchAll();

        if (empty($rows)) {
            $GLOBALS['ORG_DIRECTORY_CACHE'] = $fallback;
            return $fallback;
        }

        $hierarchy = [];
        $profiles = [];
        $levelOrders = [];
        $canonMap = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $rawLevel = trim((string) ($row['level'] ?? 'Team')) ?: 'Team';
            $orderVal = (int) ($row['level_order'] ?? 0);
            if ($orderVal > 0) {
                $displayLevel = 'Level ' . $orderVal;
            } else {
                $normKey = strtolower(preg_replace('/\s+/', ' ', $rawLevel));
                if (!isset($canonMap[$normKey])) {
                    $canonMap[$normKey] = preg_replace('/\s+/', ' ', $rawLevel);
                }
                $displayLevel = $canonMap[$normKey];
            }
            if (!isset($hierarchy[$displayLevel])) {
                $hierarchy[$displayLevel] = [];
            }
            $hierarchy[$displayLevel][] = $name;
            if (!isset($levelOrders[$displayLevel])) {
                $levelOrders[$displayLevel] = $orderVal;
            }

            $skills = array_values(array_filter(array_map('trim', explode(',', (string) ($row['skills'] ?? '')))));

            $profiles[$name] = [
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'title' => $row['title'] ?? $displayLevel,
                'email' => $row['email'] ?? null,
                'location' => $row['location'] ?? null,
                'state' => $row['state'] ?? null,
                'country' => $row['country'] ?? null,
                'tenure' => $row['tenure'] ?? null,
                'focus' => $row['focus'] ?? null,
                'bio' => $row['bio'] ?? null,
                'skills' => $skills,
            ];
        }

        if (empty($hierarchy)) {
            $GLOBALS['ORG_DIRECTORY_CACHE'] = $fallback;
            return $fallback;
        }

        $GLOBALS['ORG_DIRECTORY_CACHE'] = ['hierarchy' => $hierarchy, 'profiles' => $profiles, 'levels' => $levelOrders];
        return $GLOBALS['ORG_DIRECTORY_CACHE'];
    } catch (\Exception $exception) {
        error_log('Org directory fallback: ' . $exception->getMessage());
        $GLOBALS['ORG_DIRECTORY_CACHE'] = $fallback;
        return $fallback;
    }
}

function tamil_nadu_location_lookup(): array
{
    static $lookup;
    if ($lookup !== null) {
        return $lookup;
    }

    $cities = [
        'Chennai', 'Coimbatore', 'Madurai', 'Tiruchirappalli', 'Salem', 'Tirunelveli', 'Erode', 'Vellore',
        'Thoothukudi', 'Dindigul', 'Thanjavur', 'Karur', 'Kanchipuram', 'Nagercoil', 'Hosur', 'Cuddalore',
        'Tiruppur', 'Nagapattinam', 'Pudukkottai', 'Virudhunagar', 'Ranipet', 'Sivakasi', 'Ariyalur',
        'Perambalur', 'Krishnagiri', 'Nilgiris', 'Namakkal', 'Theni', 'Ramanathapuram'
    ];

    $lookup = [];
    foreach ($cities as $city) {
        $lookup[$city] = ['state' => 'Tamil Nadu', 'country' => 'India'];
    }

    return $lookup;
}

function indian_states(): array
{
    return [
        'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
        'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand',
        'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur',
        'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab',
        'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura',
        'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
        'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu',
        'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry'
    ];
}

function tenure_years_options(): array
{
    $options = [];
    for ($i = 0; $i <= 60; $i++) {
        $label = $i === 1 ? 'year' : 'years';
        $options[] = $i . ' ' . $label;
    }
    return $options;
}

function create_org_member(array $payload): array
{
    $level = trim((string) ($payload['level'] ?? ''));
    $name = trim((string) ($payload['name'] ?? ''));

    if ($level === '' || $name === '') {
        return ['success' => false, 'error' => 'invalid'];
    }

    $levelOrder = (int) ($payload['level_order'] ?? 0);
    $displayOrder = (int) ($payload['display_order'] ?? 0);
    $skills = $payload['skills'] ?? '';
    if (is_array($skills)) {
        $skills = implode(', ', array_values(array_filter(array_map('trim', $skills))));
    }
    $skills = trim((string) $skills);

    try {
        $pdo = db();

        // Check for existing member with same level and name
        $check = $pdo->prepare('SELECT EmployeeDetail_ID AS id FROM employeedetails WHERE Level_Name = :level AND Employee_Name = :name LIMIT 1');
        $check->execute(['level' => $level, 'name' => $name]);
        $existingId = (int) ($check->fetchColumn() ?: 0);

        if ($existingId > 0) {
            // Update existing record (idempotent create on duplicate attempts)
            $upd = $pdo->prepare('UPDATE employeedetails SET 
                Level_Order = :level_order,
                Display_Order = :display_order,
                Title = :title,
                Email = :email,
                Location = :location,
                State = :state,
                Country = :country,
                Tenure = :tenure,
                Focus = :focus,
                Bio = :bio,
                Skills = :skills
                WHERE EmployeeDetail_ID = :id');
            $upd->execute([
                'id' => $existingId,
                'level_order' => $levelOrder,
                'display_order' => $displayOrder,
                'title' => trim((string) ($payload['title'] ?? '')) ?: null,
                'email' => trim((string) ($payload['email'] ?? '')) ?: null,
                'location' => trim((string) ($payload['location'] ?? '')) ?: null,
                'state' => trim((string) ($payload['state'] ?? '')) ?: null,
                'country' => trim((string) ($payload['country'] ?? '')) ?: null,
                'tenure' => trim((string) ($payload['tenure'] ?? '')) ?: null,
                'focus' => trim((string) ($payload['focus'] ?? '')) ?: null,
                'bio' => trim((string) ($payload['bio'] ?? '')) ?: null,
                'skills' => $skills !== '' ? $skills : null,
            ]);
            $GLOBALS['ORG_DIRECTORY_CACHE'] = null;
            return ['success' => true, 'id' => $existingId, 'upsert' => true];
        }

        // Insert new record
        $stmt = $pdo->prepare('INSERT INTO employeedetails 
            (Level_Name, Level_Order, Display_Order, Employee_Name, Title, Email, Location, State, Country, Tenure, Focus, Bio, Skills)
            VALUES (:level, :level_order, :display_order, :name, :title, :email, :location, :state, :country, :tenure, :focus, :bio, :skills)');
        $stmt->execute([
            'level' => $level,
            'level_order' => $levelOrder,
            'display_order' => $displayOrder,
            'name' => $name,
            'title' => trim((string) ($payload['title'] ?? '')) ?: null,
            'email' => trim((string) ($payload['email'] ?? '')) ?: null,
            'location' => trim((string) ($payload['location'] ?? '')) ?: null,
            'state' => trim((string) ($payload['state'] ?? '')) ?: null,
            'country' => trim((string) ($payload['country'] ?? '')) ?: null,
            'tenure' => trim((string) ($payload['tenure'] ?? '')) ?: null,
            'focus' => trim((string) ($payload['focus'] ?? '')) ?: null,
            'bio' => trim((string) ($payload['bio'] ?? '')) ?: null,
            'skills' => $skills !== '' ? $skills : null,
        ]);

        $GLOBALS['ORG_DIRECTORY_CACHE'] = null;
        return ['success' => true, 'id' => (int) $pdo->lastInsertId()];
    } catch (PDOException $exception) {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $msgText = (string) ($exception->errorInfo[2] ?? $exception->getMessage());
        if ($driverCode === 1062 || $sqlState === '23505' || (strcasecmp($sqlState, '23000') === 0 && stripos($msgText, 'unique') !== false)) {
            return ['success' => false, 'error' => 'duplicate'];
        }
        error_log('Org member creation failed: ' . $exception->getMessage());
        return ['success' => false, 'error' => 'db'];
    } catch (\Exception $exception) {
        error_log('Org member creation failed: ' . $exception->getMessage());
        return ['success' => false, 'error' => 'db'];
    }
}

function find_org_member(int $id): ?array
{
    if ($id <= 0) return null;
    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT EmployeeDetail_ID AS id, Level_Name AS level, COALESCE(Level_Order,0) AS level_order, COALESCE(Display_Order,0) AS display_order, Employee_Name AS name, Title AS title, Email AS email, Location AS location, State AS state, Country AS country, Tenure AS tenure, Focus AS focus, Bio AS bio, Skills AS skills FROM employeedetails WHERE EmployeeDetail_ID = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return $row;
    } catch (\Exception $exception) {
        error_log('Org member lookup failed: ' . $exception->getMessage());
        return null;
    }
}

function update_org_member(int $id, array $payload): array
{
    if ($id <= 0) return ['success' => false, 'error' => 'invalid'];
    $level = trim((string) ($payload['level'] ?? ''));
    $name = trim((string) ($payload['name'] ?? ''));
    if ($level === '' || $name === '') {
        return ['success' => false, 'error' => 'invalid'];
    }
    $levelOrder = (int) ($payload['level_order'] ?? 0);
    $displayOrder = (int) ($payload['display_order'] ?? 0);
    $skills = $payload['skills'] ?? '';
    if (is_array($skills)) {
        $skills = implode(', ', array_values(array_filter(array_map('trim', $skills))));
    }
    $skills = trim((string) $skills);

    try {
        $pdo = db();
        $stmt = $pdo->prepare('UPDATE employeedetails SET Level_Name = :level, Level_Order = :level_order, Display_Order = :display_order, Employee_Name = :name, Title = :title, Email = :email, Location = :location, State = :state, Country = :country, Tenure = :tenure, Focus = :focus, Bio = :bio, Skills = :skills WHERE EmployeeDetail_ID = :id');
        $stmt->execute([
            'id' => $id,
            'level' => $level,
            'level_order' => $levelOrder,
            'display_order' => $displayOrder,
            'name' => $name,
            'title' => trim((string) ($payload['title'] ?? '')) ?: null,
            'email' => trim((string) ($payload['email'] ?? '')) ?: null,
            'location' => trim((string) ($payload['location'] ?? '')) ?: null,
            'state' => trim((string) ($payload['state'] ?? '')) ?: null,
            'country' => trim((string) ($payload['country'] ?? '')) ?: null,
            'tenure' => trim((string) ($payload['tenure'] ?? '')) ?: null,
            'focus' => trim((string) ($payload['focus'] ?? '')) ?: null,
            'bio' => trim((string) ($payload['bio'] ?? '')) ?: null,
            'skills' => $skills !== '' ? $skills : null,
        ]);
        $GLOBALS['ORG_DIRECTORY_CACHE'] = null;
        return ['success' => true];
    } catch (\Exception $exception) {
        error_log('Org member update failed: ' . $exception->getMessage());
        return ['success' => false, 'error' => 'db'];
    }
}

function delete_org_member(int $id): bool
{
    if ($id <= 0) return false;
    try {
        $pdo = db();
        $stmt = $pdo->prepare('DELETE FROM employeedetails WHERE EmployeeDetail_ID = :id');
        $stmt->execute(['id' => $id]);
        $GLOBALS['ORG_DIRECTORY_CACHE'] = null;
        return $stmt->rowCount() > 0;
    } catch (\Exception $exception) {
        error_log('Org member delete failed: ' . $exception->getMessage());
        return false;
    }
}

function get_employees(?string $filterRole = null): array
{
    try {
        ensure_employee_number_column();
        $pdo = db();
        $sql = 'SELECT 
                    e.EMP_ID AS emp_id,
                    e.Employee_Number AS employee_number,
                    e.Full_Name AS full_name,
                    e.Email AS email,
                    e.Role_ID AS role_id,
                    e.Is_Active AS is_active,
                    r.Role_Name AS role_name
                FROM employee e
                LEFT JOIN roles r ON r.Role_ID = e.Role_ID
                WHERE e.Is_Active = TRUE
                ORDER BY e.Full_Name';
        $rows = $pdo->query($sql)->fetchAll();

        $employees = array_map(static function (array $row) {
            $roleName = $row['role_name'] ?? 'employee';
            $slug = normalize_role_slug($roleName);
            return [
                'id' => (int) $row['emp_id'],
                'name' => $row['full_name'],
                'email' => $row['email'],
                'employee_number' => $row['employee_number'] ?? null,
                'title' => $roleName,
                'role' => $roleName,
                'role_type' => $slug,
                'role_id' => (int) $row['role_id'],
                'active' => (bool) $row['is_active'],
            ];
        }, $rows);
    } catch (\Exception $exception) {
        error_log('Employee fetch failed: ' . $exception->getMessage());
        $employees = seed_employees_data();
    }

    if ($filterRole === null) {
        return $employees;
    }

    return array_values(array_filter($employees, static fn ($emp) => $emp['role_type'] === $filterRole));
}

function get_lead_team(int $leadId): array
{
    if ($leadId <= 0) return [];
    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT e.EMP_ID AS emp_id, e.Full_Name AS full_name, e.Email AS email, r.Role_Name AS role_name, ltm.Created_At AS assigned_at
                                FROM lead_team_map ltm
                                JOIN employee e ON e.EMP_ID = ltm.Team_ID
                                JOIN roles r ON r.Role_ID = e.Role_ID
                                WHERE ltm.Lead_ID = :lead
                                ORDER BY e.Full_Name');
        $stmt->execute(['lead' => $leadId]);
        return array_map(static function (array $row) {
            return [
                'id' => (int) $row['emp_id'],
                'name' => $row['full_name'],
                'email' => $row['email'],
                'title' => $row['role_name'],
                'role_type' => normalize_role_slug($row['role_name']),
                'assigned_at' => $row['assigned_at'],
            ];
        }, $stmt->fetchAll());
    } catch (\Exception $exception) {
        error_log('Lead team lookup failed: ' . $exception->getMessage());
        return [];
    }
}

function add_lead_team_member(int $leadId, int $teamId): bool
{
    if ($leadId <= 0 || $teamId <= 0 || $leadId === $teamId) return false;
    try {
        $pdo = db();
        $stmt = $pdo->prepare('INSERT INTO lead_team_map (Lead_ID, Team_ID) VALUES (:lead, :team)');
        $stmt->execute(['lead' => $leadId, 'team' => $teamId]);
        return true;
    } catch (\Exception $exception) {
        error_log('Lead team add failed: ' . $exception->getMessage());
        return false;
    }
}

function remove_lead_team_member(int $leadId, int $teamId): bool
{
    if ($leadId <= 0 || $teamId <= 0) return false;
    try {
        $pdo = db();
        $stmt = $pdo->prepare('DELETE FROM lead_team_map WHERE Lead_ID = :lead AND Team_ID = :team');
        $stmt->execute(['lead' => $leadId, 'team' => $teamId]);
        return $stmt->rowCount() > 0;
    } catch (\Exception $exception) {
        error_log('Lead team remove failed: ' . $exception->getMessage());
        return false;
    }
}

function get_roles(): array
{
    try {
        $pdo = db();
        $stmt = $pdo->query('SELECT Role_ID AS id, Role_Name AS name, COALESCE(Description, \'\') AS description FROM roles ORDER BY Role_Name');
        return array_map(static function (array $row) {
            return [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'slug' => normalize_role_slug($row['name']),
            ];
        }, $stmt->fetchAll());
    } catch (\Exception $exception) {
        error_log('Role fetch failed: ' . $exception->getMessage());
        return [
            ['id' => 0, 'name' => 'superadmin', 'description' => 'Super Admin', 'slug' => 'superadmin'],
            ['id' => 0, 'name' => 'CEO', 'description' => 'Executive leadership', 'slug' => 'ceo'],
            ['id' => 0, 'name' => 'Lead', 'description' => 'Engagement lead', 'slug' => 'lead'],
            ['id' => 0, 'name' => 'Team', 'description' => 'Project team', 'slug' => 'team'],
            ['id' => 0, 'name' => 'Employee', 'description' => 'General employee', 'slug' => 'employee'],
        ];
    }
}

function role_id_by_label(string $label): int
{
    $label = strtolower(trim($label));
    $roles = get_roles();
    $byName = [];
    $bySlug = [];
    foreach ($roles as $r) {
        $name = strtolower((string) ($r['name'] ?? ''));
        $id = (int) ($r['id'] ?? 0);
        if ($id > 0) {
            $byName[$name] = $id;
            $bySlug[normalize_role_slug($name)] = $id;
        }
    }

    if (isset($byName[$label])) {
        return $byName[$label];
    }

    $inSlug = normalize_role_slug($label);
    return $bySlug[$inSlug] ?? 0;
}

function create_employee_profile(string $fullName, string $email, string $password, int $roleId, ?string $overrideCode = null): array
{
    if ($fullName === '' || $password === '' || $roleId <= 0) {
        return ['success' => false, 'error' => 'invalid'];
    }

    try {
        $pdo = db();
        ensure_employee_number_column();
        ensure_employee_primary_key_autoincrement();
        ensure_employee_email_nullable();
        $normalizedEmail = normalize_email_input($email);
    $empCode = ($overrideCode !== null && trim($overrideCode) !== '')
            ? normalize_employee_number_input($overrideCode)
            : generate_next_employee_number();
    if (trim($empCode) !== '') {
        $dup = find_employee_by_number($empCode);
        if ($dup) {
            return ['success' => false, 'error' => 'duplicate_emp_id'];
        }
    }
        write_staff_bulk_log(
            'Create attempt driver=' . (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)
            . ' name=' . $fullName
            . ' email=' . $normalizedEmail
            . ' role_id=' . (string) $roleId
            . ' code=' . $empCode
            . ' emp_id=' . $empCode
            . ' email_nullable=' . (employee_email_is_nullable() ? '1' : '0')
        );
        $stmt = $pdo->prepare('INSERT INTO employee (EMP_ID, Full_Name, Email, Password_Hash, Role_ID, Employee_Number) VALUES (:id, :name, :email, :hash, :role, :code)');
        $stmt->execute([
            'id' => $empCode,
            'name' => $fullName,
            'email' => ($normalizedEmail !== '' ? $normalizedEmail : (employee_email_is_nullable() ? null : '')),
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $roleId,
            'code' => $empCode,
        ]);

        write_staff_bulk_log('Create success id=' . (int) $pdo->lastInsertId() . ' email=' . $normalizedEmail);

        return ['success' => true, 'id' => (int) $pdo->lastInsertId()];
    } catch (PDOException $exception) {
        $code = (int) $exception->getCode();
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $msgText = (string) ($exception->errorInfo[2] ?? $exception->getMessage());
        write_staff_bulk_log('DB error email=' . normalize_email_input($email) . ' sqlstate=' . $sqlState . ' code=' . $driverCode);
        if ($driverCode === 1062 || $sqlState === '23505' || (strcasecmp($sqlState, '23000') === 0 && stripos($msgText, 'unique') !== false)) {
            return ['success' => false, 'error' => 'duplicate', 'sqlstate' => $sqlState, 'driver_code' => $driverCode];
        }
        error_log('Employee creation failed: ' . $exception->getMessage());
        return ['success' => false, 'error' => 'db', 'sqlstate' => $sqlState, 'driver_code' => $driverCode, 'message' => $exception->getMessage()];
    } catch (\Exception $exception) {
        error_log('Employee creation failed: ' . $exception->getMessage());
        return ['success' => false, 'error' => 'unknown'];
    }
}

function update_employee_profile(int $employeeId, array $payload): array
{
    $fullName = trim((string) ($payload['full_name'] ?? ''));
    $email = normalize_email_input((string) ($payload['email'] ?? ''));
    $roleId = (int) ($payload['role_id'] ?? 0);

    if ($employeeId <= 0 || $fullName === '' || $roleId <= 0) {
        return ['success' => false, 'error' => 'invalid'];
    }

    try {
        $pdo = db();
        ensure_employee_number_column();
        ensure_employee_email_nullable();
        $newCode = ($payload['employee_number'] ?? ($payload['employee_code'] ?? null));
        if ($newCode !== null && trim((string)$newCode) !== '') {
            $normalized = normalize_employee_number_input((string)$newCode);
            $stmtChk = $pdo->prepare('SELECT 1 FROM employee WHERE Employee_Number = :code AND EMP_ID <> :id LIMIT 1');
            $stmtChk->execute(['code' => $normalized, 'id' => $employeeId]);
            if ($stmtChk->fetchColumn()) {
                return ['success' => false, 'error' => 'duplicate_emp_id'];
            }
        }
        $stmt = $pdo->prepare('UPDATE employee 
                SET Full_Name = :name,
                    Email = :email,
                    Role_ID = :role,
                    Employee_Number = COALESCE(:code, Employee_Number),
                    Updated_At = CURRENT_TIMESTAMP
                WHERE EMP_ID = :id');
        $stmt->execute([
            'name' => $fullName,
            'email' => ($email !== '' ? $email : (employee_email_is_nullable() ? null : '')),
            'role' => $roleId,
            'code' => (isset($normalized) ? $normalized : (($payload['employee_number'] ?? ($payload['employee_code'] ?? null)) ?: null)),
            'id' => $employeeId,
        ]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'error' => 'not_found'];
        }

        return ['success' => true];
    } catch (PDOException $exception) {
        $code = (int) $exception->getCode();
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $msgText = (string) ($exception->errorInfo[2] ?? $exception->getMessage());
        if ($driverCode === 1062 || $sqlState === '23505' || (strcasecmp($sqlState, '23000') === 0 && stripos($msgText, 'unique') !== false)) {
            return ['success' => false, 'error' => 'duplicate'];
        }
        error_log('Employee update failed: ' . $exception->getMessage());
        return ['success' => false, 'error' => 'db'];
    } catch (\Exception $exception) {
        error_log('Employee update failed: ' . $exception->getMessage());
        return ['success' => false, 'error' => 'unknown'];
    }
}

function update_employee_number(int $employeeId, string $code): bool
{
    if ($employeeId <= 0 || trim($code) === '') { return false; }
    try {
        ensure_employee_number_column();
        $pdo = db();
        $stmt = $pdo->prepare('UPDATE employee SET Employee_Number = :code, Updated_At = CURRENT_TIMESTAMP WHERE EMP_ID = :id');
        return $stmt->execute(['code' => substr(trim($code), 0, 32), 'id' => $employeeId]);
    } catch (\Exception $exception) {
        error_log('Employee code update failed: ' . $exception->getMessage());
        return false;
    }
}

function delete_employee(int $employeeId): bool
{
    if ($employeeId <= 0) {
        return false;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare('UPDATE employee SET Is_Active = FALSE, Updated_At = CURRENT_TIMESTAMP WHERE EMP_ID = :id');
        $stmt->execute(['id' => $employeeId]);
        return $stmt->rowCount() > 0;
    } catch (\Exception $exception) {
        error_log('Employee delete failed: ' . $exception->getMessage());
        return false;
    }
}

function normalize_role_slug(string $roleName): string
{
    $name = strtolower($roleName);
    return match (true) {
        text_contains($name, 'super') => 'superadmin',
        text_contains($name, 'ceo') => 'ceo',
        text_contains($name, 'lead') || text_contains($name, 'director') => 'lead',
        text_contains($name, 'analyst') || text_contains($name, 'team') => 'team',
        text_contains($name, 'client') || text_contains($name, 'customer') => 'customer',
        default => 'employee',
    };
}

function normalize_email_input(string $email): string
{
    $e = strtolower($email);
    $e = str_replace(["\u{00A0}", "\u{200B}", "\u{200C}", "\u{200D}", "\u{FEFF}", "\u{202F}", "\u{2060}"], '', $e);
    $e = preg_replace('/[\x00-\x1F\x7F]/u', '', $e);
    $e = trim($e);
    if (function_exists('normalizer_normalize')) {
        $e = normalizer_normalize($e, Normalizer::FORM_KC);
    }
    return $e;
}

function find_employee(int $id): ?array
{
    try {
        ensure_employee_number_column();
        $pdo = db();
        $stmt = $pdo->prepare('SELECT 
                                   e.EMP_ID AS emp_id,
                                   e.Employee_Number AS employee_number,
                                   e.Full_Name AS full_name,
                                   e.Email AS email,
                                   e.Role_ID AS role_id,
                                   r.Role_Name AS role_name
                               FROM employee e
                               LEFT JOIN roles r ON r.Role_ID = e.Role_ID
                               WHERE e.EMP_ID = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $roleName = $row['role_name'] ?? 'employee';
        return [
            'id' => (int) $row['emp_id'],
            'name' => $row['full_name'],
            'email' => $row['email'],
            'role_id' => (int) $row['role_id'],
            'role' => $roleName,
            'role_type' => normalize_role_slug($roleName),
        ];
    } catch (\Exception $exception) {
        error_log('Employee lookup failed: ' . $exception->getMessage());
        return seed_employee_by_id($id);
    }
}

function find_employee_by_email(string $email): ?array
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT 
                                   e.EMP_ID AS emp_id,
                                   e.Full_Name AS full_name,
                                   e.Email AS email,
                                   e.Password_Hash AS password_hash,
                                   r.Role_Name AS role_name
                               FROM employee e
                               JOIN roles r ON r.Role_ID = e.Role_ID
                               WHERE LOWER(TRIM(e.Email)) = LOWER(:email) AND e.Is_Active = TRUE');
        $stmt->execute(['email' => normalize_email_input($email)]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row['emp_id'],
            'name' => $row['full_name'],
            'email' => $row['email'],
            'password_hash' => $row['password_hash'],
            'role' => normalize_role_slug($row['role_name']),
        ];
    } catch (\Exception $exception) {
        error_log('Employee email lookup failed: ' . $exception->getMessage());
        throw $exception;
    }
}

function find_employee_by_email_any(string $email): ?array
{
    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT 
                                   EMP_ID AS emp_id,
                                   Full_Name AS full_name,
                                   Email AS email,
                                   Is_Active AS is_active
                               FROM employee
                               WHERE LOWER(TRIM(Email)) = LOWER(:email)
                               LIMIT 1');
        $stmt->execute(['email' => normalize_email_input($email)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) ($row['emp_id'] ?? 0),
            'name' => (string) ($row['full_name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'is_active' => (bool) ($row['is_active'] ?? false),
        ];
    } catch (\Exception $exception) {
        error_log('Employee email lookup (any) failed: ' . $exception->getMessage());
        throw $exception;
    }
}

function find_client(int|string $id): ?array
{
    $cid = (string) $id;
    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT 1 FROM client WHERE C_ID = :id');
        $stmt->execute(['id' => $cid]);
        if (!$stmt->fetchColumn()) {
            return null;
        }

        foreach (get_clients() as $client) {
            if (($client['c_id'] ?? '') === $cid) {
                return $client;
            }
        }
    } catch (\Exception $exception) {
        error_log('Client lookup failed: ' . $exception->getMessage());
    }

    return null;
}

function ceo_contact(): ?array
{
    try {
        $pdo = db();
        $sql = 'SELECT e.EMP_ID AS emp_id, e.Full_Name AS full_name, e.Email AS email, r.Role_Name AS role_name
                FROM employee e
                JOIN roles r ON r.Role_ID = e.Role_ID
                WHERE e.Is_Active = TRUE
                ORDER BY (CASE WHEN LOWER(r.Role_Name) LIKE \'%ceo%\' THEN 0 ELSE 1 END), e.EMP_ID
                LIMIT 1';
        $row = $pdo->query($sql)->fetch();
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row['emp_id'],
            'name' => $row['full_name'],
            'email' => $row['email'],
            'role' => normalize_role_slug($row['role_name']),
        ];
    } catch (\Exception $exception) {
        error_log('CEO contact fallback: ' . $exception->getMessage());
        foreach (seed_employees_data() as $employee) {
            if (in_array($employee['role_type'], ['ceo', 'superadmin'], true)) {
                return $employee;
            }
        }
        return null;
    }
}

function text_contains(string $haystack, string $needle): bool
{
    return stripos($haystack, $needle) !== false;
}

function seed_clients_data(): array
{
    return array_values(app_data('clients_seed', []));
}

function seed_employees_data(): array
{
    return array_map(static function (array $employee) {
        $roleSource = (string) ($employee['role_type'] ?? $employee['title'] ?? 'employee');
        $roleType = normalize_role_slug($roleSource);

        return [
            'id' => (int) ($employee['id'] ?? 0),
            'name' => $employee['name'] ?? '',
            'email' => $employee['email'] ?? '',
            'title' => $employee['title'] ?? ucfirst($roleType),
            'role' => $employee['role'] ?? $roleType,
            'role_type' => $roleType,
            'role_id' => (int) ($employee['role_id'] ?? 0),
            'active' => true,
        ];
    }, app_data('employees', []));
}

function seed_employee_by_id(int $id): ?array
{
    foreach (seed_employees_data() as $employee) {
        if ($employee['id'] === $id) {
            return $employee;
        }
    }

    return null;
}

function seed_user_by_email(string $email): ?array
{
    foreach (app_data('users', []) as $user) {
        if (strcasecmp($user['email'] ?? '', $email) !== 0) {
            continue;
        }

        $role = normalize_role_slug((string) ($user['role'] ?? 'employee'));

        return [
            'id' => (int) ($user['id'] ?? 0),
            'name' => $user['name'] ?? '',
            'email' => $user['email'] ?? '',
            'password_hash' => $user['password_hash'] ?? '',
            'role' => $role,
        ];
    }

    return null;
}
function client_dup_store_path(int $userId): string
{
    $dir = __DIR__ . '/../../storage/uploads/tmp';
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
    return $dir . '/client_dups_' . $userId . '.json';
}

function client_dup_store_add(int $userId, array $entry): void
{
    $fp = client_dup_store_path($userId);
    $list = [];
    if (is_file($fp)) { $list = json_decode((string) file_get_contents($fp), true) ?: []; }
    $list[] = $entry;
    file_put_contents($fp, json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function client_dup_store_list(int $userId): array
{
    $fp = client_dup_store_path($userId);
    if (!is_file($fp)) { return []; }
    return json_decode((string) file_get_contents($fp), true) ?: [];
}

function client_dup_store_clear(int $userId): void
{
    $fp = client_dup_store_path($userId);
    if (is_file($fp)) { unlink($fp); }
}
function update_client_record_partial(int|string $clientId, array $clientData): bool
{
    $current = find_client($clientId) ?? [];
    $map = [
        'Name_of_Entity' => 'name',
        'Type_of_Entity' => 'entity_type',
        'Sub_Type_of_Entity' => 'entity_subtype',
        'Address1' => ['address1','address.line1'],
        'Address2' => ['address2','address.line2'],
        'City' => ['city','address.city'],
        'State' => ['state','address.state'],
        'PIN' => ['pin','address.pin'],
        'PAN_No' => 'pan',
        'TAN_No' => 'tan',
        'GST_Regn_No' => 'gst',
        'Aadhaar_No' => 'aadhaar',
        'Date_of_Incorporation' => 'incorporated_on',
        'Name_of_CEO' => 'ceo_name',
        'Email_of_CEO' => 'ceo_email',
        'Name_of_POC' => ['name_of_poc','primary_contact'],
        'Email_of_POC' => ['email_of_poc','contact_email'],
        'POC_Phone' => 'pco_phone',
        'Login_ID' => 'login_id',
        'Credentials' => 'credentials',
        'Invoiced' => 'invoiced',
    ];
    $sets = [];
    $params = ['id' => (string) $clientId];
    foreach ($map as $col => $keys) {
        $keysList = is_array($keys) ? $keys : [$keys];
        $new = null;
        foreach ($keysList as $k) {
            if (array_key_exists($k, $clientData)) { $new = $clientData[$k]; break; }
        }
        if ($col === 'Date_of_Incorporation') { $new = normalize_date_value($new); }
        if ($new === null || $new === '') { continue; }
        $old = null;
        $simple = is_string($keys) ? $keys : $keysList[0];
        // best-effort read from current flattened structure
        $old = $current[$simple] ?? null;
        if ($col === 'Invoiced') { $new = (int) ((bool) $new); $old = (int) ((bool) $old); }
        if ($new === $old) { continue; }
        $sets[] = $col . ' = :' . $col;
        $params[$col] = $new;
    }
    if (empty($sets)) { return true; }
    $sql = 'UPDATE client SET ' . implode(', ', $sets) . ' WHERE C_ID = :id';
    $pdo = db();
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}
function normalize_date_value(mixed $val): ?string
{
    if ($val === null) { return null; }
    $s = strtolower(trim((string) $val));
    if ($s === '' || in_array($s, ['na','n/a','null','none','-'], true)) { return null; }
    $s = str_replace('/', '-', (string) $val);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        $d = DateTime::createFromFormat('Y-m-d', $s);
        return $d ? $d->format('Y-m-d') : null;
    }
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $s)) {
        $d = DateTime::createFromFormat('d-m-Y', $s);
        return $d ? $d->format('Y-m-d') : null;
    }
    return null;
}
function find_employee_by_number(string $code): ?array
{
    try {
        ensure_employee_number_column();
        $pdo = db();
        $stmt = $pdo->prepare('SELECT EMP_ID AS emp_id, Employee_Number AS employee_number, Full_Name AS full_name, Email AS email FROM employee WHERE Employee_Number = :code LIMIT 1');
        $stmt->execute(['code' => substr(trim($code), 0, 32)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { return null; }
        return [
            'id' => (int) ($row['emp_id'] ?? 0),
            'employee_number' => (string) ($row['employee_number'] ?? ''),
            'name' => (string) ($row['full_name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
        ];
    } catch (\Exception $exception) {
        error_log('Employee number lookup failed: ' . $exception->getMessage());
        return null;
    }
}
