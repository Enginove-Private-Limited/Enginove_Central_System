-- ==========================================================
-- ENGINOVE CENTRAL MANAGEMENT SYSTEM (ECMS)
-- Database: enginove_2026
-- Version: 1.0
-- ==========================================================

-- ----------------------------------------------------------
-- CREATE DATABASE
-- ----------------------------------------------------------


USE enginove_2026;

-- ==========================================================
-- DEPARTMENTS
-- ==========================================================

CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    status ENUM('ACTIVE','INACTIVE') DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO departments (department_name) VALUES
('Quantity Survey'),
('Engineering'),
('ADMIN'),
('IT'),
('STORES');

-- ==========================================================
-- USERS
-- ==========================================================

CREATE TABLE users (

    id INT AUTO_INCREMENT PRIMARY KEY,

    department_id INT,

    first_name VARCHAR(100) NOT NULL,

    last_name VARCHAR(100) NOT NULL,

    username VARCHAR(100) UNIQUE NOT NULL,

    email VARCHAR(150) UNIQUE,

    phone VARCHAR(30),

    password VARCHAR(255) NOT NULL,

    status ENUM('ACTIVE','DISABLED') DEFAULT 'ACTIVE',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (department_id)
    REFERENCES departments(id)

);
-- ==========================================================
-- ROLES
-- ==========================================================

CREATE TABLE roles (

    id INT AUTO_INCREMENT PRIMARY KEY,

    role_name VARCHAR(100) UNIQUE NOT NULL,

    description TEXT

);

INSERT INTO roles(role_name) VALUES

('Administrator'),
('Tender Manager'),
('Quantity Surveyor'),
('Engineer'),
('Stores'),
('IT'),
('Viewer');

-- ==========================================================
-- USER ROLES
-- ==========================================================

CREATE TABLE user_roles (

    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    role_id INT NOT NULL,

    FOREIGN KEY(user_id)
    REFERENCES users(id)
    ON DELETE CASCADE,

    FOREIGN KEY(role_id)
    REFERENCES roles(id)
    ON DELETE CASCADE

);

-- ==========================================================
-- MODULES
-- ==========================================================

CREATE TABLE modules (

    id INT AUTO_INCREMENT PRIMARY KEY,

    module_name VARCHAR(100) UNIQUE,

    description TEXT

);

INSERT INTO modules(module_name) VALUES

('Dashboard'),
('Tender Management'),
('Purchase Orders'),
('Procurement'),
('Suppliers'),
('Artisans'),
('Engineering'),
('Quantity Survey'),
('Stores'),
('Todo'),
('Reports'),
('Users'),
('Settings');

-- ==========================================================
-- MODULE PERMISSIONS
-- ==========================================================

CREATE TABLE role_module_permissions (

    id INT AUTO_INCREMENT PRIMARY KEY,

    role_id INT,

    module_id INT,

    can_view BOOLEAN DEFAULT FALSE,

    can_create BOOLEAN DEFAULT FALSE,

    can_edit BOOLEAN DEFAULT FALSE,

    can_delete BOOLEAN DEFAULT FALSE,

    FOREIGN KEY(role_id)
    REFERENCES roles(id)
    ON DELETE CASCADE,

    FOREIGN KEY(module_id)
    REFERENCES modules(id)
    ON DELETE CASCADE

);

-- ==========================================================
-- TENDERS
-- ==========================================================

CREATE TABLE tenders (

    id INT AUTO_INCREMENT PRIMARY KEY,

    tender_number VARCHAR(100) UNIQUE,

    tender_name VARCHAR(255),

    description TEXT,

    department_id INT,

    assigned_to INT,

    issue_date DATE,

    due_date DATE,

    validity_period INT,

    quotation_pdf VARCHAR(255),

    status ENUM(
        'Draft',
        'Open',
        'Submitted',
        'Awarded',
        'Lost',
        'Cancelled'
    ) DEFAULT 'Draft',

    created_by INT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY(department_id)
    REFERENCES departments(id),

    FOREIGN KEY(assigned_to)
    REFERENCES users(id),

    FOREIGN KEY(created_by)
    REFERENCES users(id)

);

-- ==========================================================
-- TENDER SUBMISSION PROOF
-- ==========================================================

CREATE TABLE tender_submission_proofs (

    id INT AUTO_INCREMENT PRIMARY KEY,

    tender_id INT,

    uploaded_by INT,

    proof_file VARCHAR(255),

    notes TEXT,

    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY(tender_id)
    REFERENCES tenders(id)
    ON DELETE CASCADE,

    FOREIGN KEY(uploaded_by)
    REFERENCES users(id)

);

-- ==========================================================
-- TENDER TRACKING
-- ==========================================================

CREATE TABLE tender_tracking (

    id INT AUTO_INCREMENT PRIMARY KEY,

    tender_id INT,

    status VARCHAR(100),

    remarks TEXT,

    updated_by INT,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY(tender_id)
    REFERENCES tenders(id)
    ON DELETE CASCADE,

    FOREIGN KEY(updated_by)
    REFERENCES users(id)

);

-- ==========================================================
-- SUPPLIERS
-- ==========================================================

CREATE TABLE suppliers (

    id INT AUTO_INCREMENT PRIMARY KEY,

    supplier_name VARCHAR(255),

    contact_person VARCHAR(255),

    phone VARCHAR(50),

    email VARCHAR(150),

    address TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);

-- ==========================================================
-- SUPPLIER PRODUCTS
-- ==========================================================

CREATE TABLE supplier_products (

    id INT AUTO_INCREMENT PRIMARY KEY,

    supplier_id INT,

    product_reference VARCHAR(100),

    product_name VARCHAR(255),

    description TEXT,

    FOREIGN KEY(supplier_id)
    REFERENCES suppliers(id)
    ON DELETE CASCADE

);

-- ==========================================================
-- ARTISANS
-- ==========================================================

CREATE TABLE artisans (

    id INT AUTO_INCREMENT PRIMARY KEY,

    artisan_name VARCHAR(255),

    trade VARCHAR(150),

    phone VARCHAR(50),

    email VARCHAR(150),

    address TEXT

);

-- ==========================================================
-- PURCHASE ORDERS
-- ==========================================================

CREATE TABLE purchase_orders (

    id INT AUTO_INCREMENT PRIMARY KEY,

    po_number VARCHAR(100),

    tender_id INT,

    supplier_id INT,

    order_date DATE,

    expected_delivery DATE,

    total_amount DECIMAL(15,2),

    status ENUM(
        'Pending',
        'Approved',
        'Ordered',
        'Received',
        'Cancelled'
    ) DEFAULT 'Pending',

    FOREIGN KEY(tender_id)
    REFERENCES tenders(id),

    FOREIGN KEY(supplier_id)
    REFERENCES suppliers(id)

);

-- ==========================================================
-- QUOTATIONS
-- ==========================================================

CREATE TABLE quotations (

    id INT AUTO_INCREMENT PRIMARY KEY,

    tender_id INT,

    supplier_id INT,

    quotation_amount DECIMAL(15,2),

    quotation_pdf VARCHAR(255),

    contact_person VARCHAR(255),

    phone VARCHAR(50),

    remarks TEXT,

    FOREIGN KEY(tender_id)
    REFERENCES tenders(id)
    ON DELETE CASCADE,

    FOREIGN KEY(supplier_id)
    REFERENCES suppliers(id)

);

-- ==========================================================
-- TODOS
-- ==========================================================

CREATE TABLE todos (

    id INT AUTO_INCREMENT PRIMARY KEY,

    assigned_to INT,

    title VARCHAR(255),

    description TEXT,

    due_date DATE,

    priority ENUM(
        'Low',
        'Medium',
        'High'
    ) DEFAULT 'Medium',

    status ENUM(
        'Pending',
        'Completed'
    ) DEFAULT 'Pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY(assigned_to)
    REFERENCES users(id)

);

-- ==========================================================
-- ACTIVITY LOGS
-- ==========================================================

CREATE TABLE activity_logs (

    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT,

    activity TEXT,

    ip_address VARCHAR(100),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY(user_id)
    REFERENCES users(id)

);

-- ==========================================================
-- DEFAULT ADMIN ACCOUNT
-- Password: admin123 (Replace with a hashed password before production)
-- ==========================================================



-- ==========================================================
-- ASSIGN ADMIN ROLE
-- ==========================================================

INSERT INTO user_roles(user_id, role_id)
VALUES (1,1);

-- ==========================================================
-- END OF DATABASE
-- ==========================================================


/*
===========================================================
PASSWORD HASHING & VERIFICATION
===========================================================

REGISTRATION
------------
When a user registers, NEVER save the plain-text password
(e.g. "admin123") directly into the database.

Instead, hash it using PHP's password_hash() function.

Example:

$password = $_POST['password'];

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Save $hashedPassword into the database

-----------------------------------------------------------
Example of what is stored in the database:

$2y$10$MqvM4R1XQfTQmKjKj9QvkePq3Smw7d4jJkDqHjQxYv6F6ZkXJj8bC

NOT:

admin123

The hash is one-way and cannot be reversed back into the
original password.

===========================================================

LOGIN
-----

When the user logs in:

1. Read the hashed password from the database.
2. Compare it with the entered password using
   password_verify().

Example:

$password = $_POST['password'];

// Get hashed password from database
$dbPassword = $row['password'];

if (password_verify($password, $dbPassword)) {

    // Login successful

} else {

    // Invalid username or password

}

===========================================================

NEVER:

- Store plain-text passwords.
- Compare passwords using == or ===.
- Encrypt passwords yourself.

ALWAYS:

- Hash passwords using password_hash().
- Verify passwords using password_verify().
- Store the hash in a VARCHAR(255) column.

===========================================================
*/