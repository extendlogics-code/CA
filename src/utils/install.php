<?php
declare(strict_types=1);

function install_bootstrap(): void
{
    try {
        ensure_database_initialized();
    } catch (Throwable $e) {
        error_log('Install bootstrap failed: ' . $e->getMessage());
    }
}

function ensure_database_initialized(): void
{
    $dir = __DIR__ . '/../../storage/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $marker = $dir . '/db.initialized';
    $driver = db_driver();
    if ($driver !== 'pgsql') { return; }

    $needInit = !table_exists('roles') || !table_exists('employee') || !table_exists('client') || !table_exists('task');
    if (!$needInit && is_file($marker)) { return; }

    ensure_base_tables_pgsql();
    seed_initial_data_pgsql();
    @file_put_contents($marker, date('c'));
}

function ensure_base_tables_pgsql(): void
{
    $pdo = db();
    if (!table_exists('roles')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS roles (
            Role_ID SERIAL PRIMARY KEY,
            Role_Name VARCHAR(50) NOT NULL UNIQUE,
            Description TEXT,
            Is_System_Role BOOLEAN DEFAULT FALSE,
            Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }
    if (!table_exists('role_permissions')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS role_permissions (
            Permission_ID SERIAL PRIMARY KEY,
            Role_ID INTEGER NOT NULL,
            Resource VARCHAR(100) NOT NULL,
            Access_Level VARCHAR(20) NOT NULL,
            UNIQUE (Role_ID, Resource)
        )');
    }
    if (!table_exists('employee')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS employee (
            EMP_ID SERIAL PRIMARY KEY,
            Full_Name VARCHAR(150) NOT NULL,
            Email VARCHAR(150) UNIQUE NULL,
            Password_Hash VARCHAR(255) NOT NULL,
            Role_ID INTEGER NOT NULL,
            Employee_Number VARCHAR(32) NULL,
            Is_Active BOOLEAN DEFAULT TRUE,
            Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            Updated_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }
    if (!table_exists('employeedetails')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS employeedetails (
            EmployeeDetail_ID SERIAL PRIMARY KEY,
            Level_Name VARCHAR(120) NOT NULL,
            Level_Order SMALLINT DEFAULT 0,
            Display_Order SMALLINT DEFAULT 0,
            Employee_Name VARCHAR(150) NOT NULL,
            Title VARCHAR(150),
            Email VARCHAR(150),
            Location VARCHAR(120),
            State VARCHAR(120),
            Country VARCHAR(120),
            Tenure VARCHAR(80),
            Focus TEXT,
            Bio TEXT,
            Skills TEXT,
            Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            Updated_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }
    if (!table_exists('client')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS client (
            C_ID VARCHAR(120) PRIMARY KEY,
            Name_of_Entity VARCHAR(150) NOT NULL,
            Client_Code VARCHAR(16),
            Address1 VARCHAR(200),
            Address2 VARCHAR(200),
            Address3 VARCHAR(200),
            City VARCHAR(100),
            State VARCHAR(100),
            PIN VARCHAR(10),
            Type_of_Entity VARCHAR(100),
            Sub_Type_of_Entity VARCHAR(100),
            PAN_No VARCHAR(15),
            TAN_No VARCHAR(15),
            GST_Regn_No VARCHAR(20),
            Aadhaar_No VARCHAR(20),
            Date_of_Incorporation DATE,
            Name_of_CEO VARCHAR(100),
            Email_of_CEO VARCHAR(100),
            Name_of_POC VARCHAR(100),
            Email_of_POC VARCHAR(100),
            POC_Phone VARCHAR(20),
            Login_ID VARCHAR(120),
            Credentials VARCHAR(255),
            Invoiced BOOLEAN DEFAULT FALSE,
            Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            Updated_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }
    if (!table_exists('service_types')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS service_types (
            Service_ID SERIAL PRIMARY KEY,
            Service_Name VARCHAR(120) NOT NULL UNIQUE
        )');
    }
    if (!table_exists('entity_groups')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS entity_groups (
            Group_ID SERIAL PRIMARY KEY,
            Group_Name VARCHAR(120) NOT NULL UNIQUE
        )');
    }
    if (!table_exists('entity_subtypes')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS entity_subtypes (
            Subtype_ID SERIAL PRIMARY KEY,
            Subtype_Name VARCHAR(120) NOT NULL UNIQUE
        )');
    }
    if (!table_exists('status_details')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS status_details (
            Status_ID SERIAL PRIMARY KEY,
            Description VARCHAR(80) NOT NULL UNIQUE
        )');
    }
    if (!table_exists('client_service_map')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS client_service_map (
            C_ID VARCHAR(120) NOT NULL,
            Service_Name VARCHAR(120) NOT NULL,
            PRIMARY KEY (C_ID, Service_Name)
        )');
    }
    if (!table_exists('client_events')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS client_events (
            Event_ID SERIAL PRIMARY KEY,
            C_ID VARCHAR(120) NOT NULL,
            Event_Name VARCHAR(150) NOT NULL,
            Event_Date DATE,
            Status VARCHAR(80),
            Notes TEXT,
            Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            Updated_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }
    if (!table_exists('task')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS task (
            T_ID SERIAL PRIMARY KEY,
            T_NAME VARCHAR(150) NOT NULL,
            T_TYPE VARCHAR(150),
            T_TYPE_DESCRIPTION TEXT,
            C_ID VARCHAR(120) NOT NULL,
            Status_ID INTEGER,
            TASK_YEAR SMALLINT,
            ASSIGNMENT_TYPE VARCHAR(100),
            APPEALS_TYPE VARCHAR(100),
            ECD_TENTATIVE DATE,
            ECD_DROP_DEAD DATE,
            DUE_PER_NOTICE DATE,
            NOTICE_DATE DATE,
            GST_Return_Month CHAR(7),
            GST_Send_Month CHAR(7),
            Task_Code VARCHAR(16),
            PROGRESS SMALLINT DEFAULT 0,
            CREATED_DATE TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            LAST_UPDATED TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }
    if (!table_exists('task_assignment')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS task_assignment (
            TA_ID SERIAL PRIMARY KEY,
            T_ID INTEGER NOT NULL,
            EMP_ID INTEGER NOT NULL,
            TASK_ROLE VARCHAR(100) NOT NULL,
            ASSIGN_DATE DATE DEFAULT CURRENT_DATE
        )');
    }
    if (!table_exists('task_comments')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS task_comments (
            Comment_ID SERIAL PRIMARY KEY,
            T_ID INTEGER NOT NULL,
            Author_Name VARCHAR(120) NOT NULL,
            Author_Role VARCHAR(80) NOT NULL,
            Body TEXT NOT NULL,
            Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }
    if (!table_exists('task_attachments')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS task_attachments (
            Attachment_ID SERIAL PRIMARY KEY,
            T_ID INTEGER NOT NULL,
            File_Path VARCHAR(255) NOT NULL,
            Original_Name VARCHAR(150) NOT NULL,
            Uploaded_By VARCHAR(120),
            Uploaded_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            File_Size INTEGER DEFAULT 0
        )');
    }
    if (!table_exists('detailed_status')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS detailed_status (
            DS_ID SERIAL PRIMARY KEY,
            T_ID INTEGER NOT NULL UNIQUE,
            Financials_Reviewed_By_Partner VARCHAR(50) DEFAULT \'No\',
            PDF_Sent_To_Client VARCHAR(50) DEFAULT \'No\',
            Signed_MRL_Recd VARCHAR(50) DEFAULT \'No\',
            Signed_Financials_Recd VARCHAR(50) DEFAULT \'No\',
            Signed_Audit_Report_Sent_To_Client VARCHAR(50) DEFAULT \'No\',
            Tax_Audit_Completed VARCHAR(50) DEFAULT \'No\',
            Three_CEB_Filed VARCHAR(50) DEFAULT \'No\',
            All_Docs_Shared_On_Shared_Folder VARCHAR(100) DEFAULT \'No\',
            Appeal_Filed VARCHAR(50) DEFAULT \'No\',
            Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            Updated_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }
    if (!table_exists('remainder')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS remainder (
            R_ID SERIAL PRIMARY KEY,
            R_NUM VARCHAR(50),
            T_ID INTEGER NOT NULL,
            R_NAME VARCHAR(150),
            COMMENTS TEXT,
            CREATED_DATE TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }
    if (!table_exists('notice')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS notice (
            N_ID SERIAL PRIMARY KEY,
            T_ID INTEGER NOT NULL,
            NOTICE TEXT NOT NULL,
            TYPE VARCHAR(100),
            COMMENTS TEXT,
            ENG_LETTER TEXT,
            Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }
    if (!table_exists('templates')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS templates (
            Template_ID SERIAL PRIMARY KEY,
            Name VARCHAR(150) NOT NULL,
            Category VARCHAR(100),
            File_Path VARCHAR(255) NOT NULL,
            Uploaded_By VARCHAR(120) NOT NULL,
            Uploaded_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }
    if (!table_exists('access_matrix')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS access_matrix (
            Asset VARCHAR(120) PRIMARY KEY,
            ceo_Access VARCHAR(20) DEFAULT \'read/write\',
            lead_Access VARCHAR(20) DEFAULT \'read\',
            Employee_Access VARCHAR(20) DEFAULT \'read\',
            Customer_Access VARCHAR(20) DEFAULT \'read\'
        )');
    }
    if (!table_exists('task_reminder_ack')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS task_reminder_ack (
            RA_ID SERIAL PRIMARY KEY,
            T_ID INTEGER NOT NULL,
            EMP_ID INTEGER NOT NULL,
            Ack_Date DATE NOT NULL,
            UNIQUE (T_ID, EMP_ID, Ack_Date)
        )');
    }
    if (!table_exists('lead_team_map')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS lead_team_map (
            Lead_ID INTEGER NOT NULL,
            Team_ID INTEGER NOT NULL,
            Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (Lead_ID, Team_ID)
        )');
    }
}

function seed_initial_data_pgsql(): void
{
    $pdo = db();
    // Roles
    $roles = ['ceo', 'lead', 'employee', 'customer'];
    foreach ($roles as $role) {
        $stmt = $pdo->prepare('SELECT 1 FROM roles WHERE Role_Name = :name');
        $stmt->execute(['name' => $role]);
        if (!$stmt->fetchColumn()) {
            $ins = $pdo->prepare('INSERT INTO roles (Role_Name, Description, Is_System_Role) VALUES (:name, :desc, TRUE)');
            $ins->execute(['name' => $role, 'desc' => ucfirst($role) . ' role']);
        }
    }
    // Access matrix minimal defaults
    $assets = [
        ['roles','read/write','none','none','none'],
        ['employees','read/write','read','none','none'],
        ['clients','read/write','read/write','read','none'],
        ['tasks','read/write','read/write','read','none'],
        ['task_comments','read/write','read/write','read/write','none'],
        ['task_attachments','read/write','read/write','read/write','none'],
        ['templates','read/write','read','none','none'],
    ];
    foreach ($assets as $row) {
        $stmt = $pdo->prepare('SELECT 1 FROM access_matrix WHERE Asset = :asset');
        $stmt->execute(['asset' => $row[0]]);
        if (!$stmt->fetchColumn()) {
            $ins = $pdo->prepare('INSERT INTO access_matrix (Asset, ceo_Access, lead_Access, Employee_Access, Customer_Access) VALUES (:a,:c,:l,:e,:u)');
            $ins->execute(['a'=>$row[0],'c'=>$row[1],'l'=>$row[2],'e'=>$row[3],'u'=>$row[4]]);
        }
    }
    // Seed sample employees (optional)
    $samples = [
        ['srini','svasan029@gmail.com','ceo','$2y$12$LtBY66kljhMHKPLJp6u.Be2UR4V.q52s3SXwf3bUEy3iV6pTtlcvO'],
        ['kannan','kannan@gmail.com','lead','$2y$12$XptUfF6RYERa7fdwfTiTE.k4gS.9seftja4XdgRyCQaeuTy56QikO'],
        ['kiruba','kiruba@gmail.com','employee','$2y$12$T7YtGGWFLukkXtjV/osnAOll/PMpCASGFJZXHT1gxNCN/LfmIKNmS'],
    ];
    foreach ($samples as $emp) {
        $stmt = $pdo->prepare('SELECT 1 FROM employee WHERE Email = :email');
        $stmt->execute(['email' => $emp[1]]);
        if (!$stmt->fetchColumn()) {
            $rid = (int) ($pdo->query("SELECT Role_ID FROM roles WHERE Role_Name = '{$emp[2]}'")->fetchColumn() ?: 0);
            if ($rid > 0) {
                $ins = $pdo->prepare('INSERT INTO employee (Full_Name, Email, Password_Hash, Role_ID, Is_Active) VALUES (:n,:e,:p,:r, TRUE)');
                $ins->execute(['n'=>$emp[0],'e'=>$emp[1],'p'=>$emp[3],'r'=>$rid]);
            }
        }
    }
}

