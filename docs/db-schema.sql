CREATE TABLE roles (
    Role_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Role_Name VARCHAR(50) NOT NULL UNIQUE,
    Description TEXT,
    Is_System_Role TINYINT(1) DEFAULT 0,
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    Permission_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Role_ID INT UNSIGNED NOT NULL,
    Resource VARCHAR(100) NOT NULL,
    Access_Level VARCHAR(20) NOT NULL CHECK (Access_Level IN ('none', 'read', 'write', 'read/write', 'admin')),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (Role_ID) REFERENCES roles(Role_ID) ON DELETE CASCADE,
    UNIQUE KEY uniq_role_resource (Role_ID, Resource)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE employee (
    EMP_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Full_Name VARCHAR(150) NOT NULL,
    Email VARCHAR(150) UNIQUE NULL,
    Password_Hash VARCHAR(255) NOT NULL,
    Role_ID INT UNSIGNED NOT NULL,
    Employee_Number VARCHAR(32) NULL,
    Is_Active TINYINT(1) DEFAULT 1,
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Updated_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employee_role FOREIGN KEY (Role_ID) REFERENCES roles(Role_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_permissions (
    Permission_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    EMP_ID INT UNSIGNED NOT NULL,
    Resource VARCHAR(100) NOT NULL,
    Access_Level VARCHAR(20) NOT NULL CHECK (Access_Level IN ('none', 'read', 'write', 'read/write', 'admin')),
    CONSTRAINT fk_user_permissions_employee FOREIGN KEY (EMP_ID) REFERENCES employee(EMP_ID) ON DELETE CASCADE,
    UNIQUE KEY uniq_user_resource (EMP_ID, Resource)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE employeedetails (
    EmployeeDetail_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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
    Updated_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE client (
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
    Updated_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_types (
    Service_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Service_Name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Entity catalogs for Client form
CREATE TABLE entity_groups (
    Group_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Group_Name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE entity_subtypes (
    Subtype_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Subtype_Name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE status_details (
    Status_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Description VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE client_service_map (
    C_ID VARCHAR(120) UNSIGNED NOT NULL,
    Service_Name VARCHAR(120) NOT NULL,
    PRIMARY KEY (C_ID, Service_Name),
    CONSTRAINT fk_client_service_map_client FOREIGN KEY (C_ID) REFERENCES client(C_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Events per client
CREATE TABLE client_events (
    Event_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    C_ID VARCHAR(120) UNSIGNED NOT NULL,
    Event_Name VARCHAR(150) NOT NULL,
    Event_Date DATE,
    Status VARCHAR(80),
    Notes TEXT,
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Updated_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_client_events_client FOREIGN KEY (C_ID) REFERENCES client(C_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task (
    T_ID VARCHAR(120) PRIMARY KEY,
    T_NAME VARCHAR(150) NOT NULL,
    T_TYPE VARCHAR(150),
    T_TYPE_DESCRIPTION TEXT,
    C_ID VARCHAR(120) UNSIGNED NOT NULL,
    Status_ID INT UNSIGNED,
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
    LAST_UPDATED TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_task_client FOREIGN KEY (C_ID) REFERENCES client(C_ID),
    CONSTRAINT fk_task_status FOREIGN KEY (Status_ID) REFERENCES status_details(Status_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_assignment (
    TA_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    T_ID VARCHAR(100) UNSIGNED NOT NULL,
    EMP_ID INT UNSIGNED NOT NULL,
    TASK_ROLE VARCHAR(100) NOT NULL,
    ASSIGN_DATE DATE DEFAULT (CURRENT_DATE),
    CONSTRAINT fk_task_assignment_task FOREIGN KEY (T_ID) REFERENCES task(T_ID),
    CONSTRAINT fk_task_assignment_employee FOREIGN KEY (EMP_ID) REFERENCES employee(EMP_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_comments (
    Comment_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    T_ID VARCHAR(100) UNSIGNED NOT NULL,
    Author_Name VARCHAR(120) NOT NULL,
    Author_Role VARCHAR(80) NOT NULL,
    Body TEXT NOT NULL,
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_task_comments_task FOREIGN KEY (T_ID) REFERENCES task(T_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_attachments (
    Attachment_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    T_ID VARCHAR(100) UNSIGNED NOT NULL,
    File_Path VARCHAR(255) NOT NULL,
    Original_Name VARCHAR(150) NOT NULL,
    Uploaded_By VARCHAR(120),
    Uploaded_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    File_Size INT DEFAULT 0,
    CONSTRAINT fk_task_attachments_task FOREIGN KEY (T_ID) REFERENCES task(T_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE detailed_status (
    DS_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    T_ID VARCHAR(100) UNSIGNED NOT NULL,
    Financials_Reviewed_By_Partner VARCHAR(50) DEFAULT 'No',
    PDF_Sent_To_Client VARCHAR(50) DEFAULT 'No',
    Signed_MRL_Recd VARCHAR(50) DEFAULT 'No',
    Signed_Financials_Recd VARCHAR(50) DEFAULT 'No',
    Signed_Audit_Report_Sent_To_Client VARCHAR(50) DEFAULT 'No',
    Tax_Audit_Completed VARCHAR(50) DEFAULT 'No',
    Three_CEB_Filed VARCHAR(50) DEFAULT 'No',
    All_Docs_Shared_On_Shared_Folder VARCHAR(100) DEFAULT 'No',
    Appeal_Filed VARCHAR(50) DEFAULT 'No',
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Updated_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_detailed_status_task (T_ID),
    CONSTRAINT fk_detailed_status_task FOREIGN KEY (T_ID) REFERENCES task(T_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE remainder (
    R_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    R_NUM VARCHAR(50),
    T_ID VARCHAR(100) UNSIGNED NOT NULL,
    R_NAME VARCHAR(150),
    COMMENTS TEXT,
    CREATED_DATE TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_remainder_task FOREIGN KEY (T_ID) REFERENCES task(T_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notice (
    N_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    T_ID VARCHAR(100) UNSIGNED NOT NULL,
    NOTICE TEXT NOT NULL,
    `TYPE` VARCHAR(100),
    COMMENTS TEXT,
    ENG_LETTER TEXT,
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notice_task FOREIGN KEY (T_ID) REFERENCES task(T_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE templates (
    Template_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(150) NOT NULL,
    Category VARCHAR(100),
    File_Path VARCHAR(255) NOT NULL,
    Uploaded_By VARCHAR(120) NOT NULL,
    Uploaded_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE access_matrix (
    Asset VARCHAR(120) PRIMARY KEY,
    ceo_Access VARCHAR(20) DEFAULT 'read/write',
    lead_Access VARCHAR(20) DEFAULT 'read',
    Employee_Access VARCHAR(20) DEFAULT 'read',
    Customer_Access VARCHAR(20) DEFAULT 'read'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_reminder_ack (
    RA_ID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    T_ID VARCHAR(100)    UNSIGNED NOT NULL,
    EMP_ID INT UNSIGNED NOT NULL,
    Ack_Date DATE NOT NULL,
    UNIQUE KEY uniq_task_reminder_ack (T_ID, EMP_ID, Ack_Date),
    CONSTRAINT fk_task_reminder_task FOREIGN KEY (T_ID) REFERENCES task(T_ID),
    CONSTRAINT fk_task_reminder_employee FOREIGN KEY (EMP_ID) REFERENCES employee(EMP_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_team_map (
    Lead_ID INT UNSIGNED NOT NULL,
    Team_ID INT UNSIGNED NOT NULL,
    Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (Lead_ID, Team_ID),
    CONSTRAINT fk_lead_team_lead FOREIGN KEY (Lead_ID) REFERENCES employee(EMP_ID),
    CONSTRAINT fk_lead_team_member FOREIGN KEY (Team_ID) REFERENCES employee(EMP_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (Role_Name, Description, Is_System_Role) VALUES
('ceo', 'System Administrator with full access to all features and settings', 1),
('employee', 'Internal staff member with access to assigned tasks and clients', 1),
('customer', 'External client with limited access to their own data and reports', 1);

INSERT INTO employee (Full_Name, Email, Password_Hash, Role_ID, Is_Active, Created_At, Updated_At) VALUES
('srini', 'svasan029@gmail.com', '$2y$12$LtBY66kljhMHKPLJp6u.Be2UR4V.q52s3SXwf3bUEy3iV6pTtlcvO', 1, 1, '2025-11-12 21:01:54.549345', '2025-11-12 21:01:54.549345'),
('kannan', 'kannan@gmail.com', '$2y$12$XptUfF6RYERa7fdwfTiTE.k4gS.9seftja4XdgRyCQaeuTy56QikO', 2, 1, '2025-11-13 12:10:00.378327', '2025-11-13 22:09:04.811982'),
('kiruba', 'kiruba@gmail.com', '$2y$12$T7YtGGWFLukkXtjV/osnAOll/PMpCASGFJZXHT1gxNCN/LfmIKNmS', 3, 1, '2025-11-13 12:10:26.594961', '2025-11-13 22:10:09.590878');
INSERT INTO access_matrix (Asset, ceo_Access, lead_Access, Employee_Access, Customer_Access) VALUES
('roles', 'read/write', 'none', 'none', 'none'),
('employees', 'read/write', 'read', 'none', 'none'),
('clients', 'read/write', 'read/write', 'read', 'none'),
('tasks', 'read/write', 'read/write', 'read', 'none'),
('task_comments', 'read/write', 'read/write', 'read/write', 'none'),
('task_attachments', 'read/write', 'read/write', 'read/write', 'none'),
('templates', 'read/write', 'read', 'none', 'none');
