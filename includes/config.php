<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'hospital_management');

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Attempt to connect to MySQL database
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if (mysqli_query($conn, $sql)) {
    // Select the database
    if (!mysqli_select_db($conn, DB_NAME)) {
        die("Error selecting database: " . mysqli_error($conn));
    }
} else {
    die("Error creating database: " . mysqli_error($conn));
}

// Create users table if not exists
$create_users = "CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    role ENUM('admin', 'doctor', 'patient') NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    profile_picture VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
)";

if (!mysqli_query($conn, $create_users)) {
    die("Error creating users table: " . mysqli_error($conn));
}

// Create other tables if they don't exist
$tables = [
    "CREATE TABLE IF NOT EXISTS patients (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        date_of_birth DATE,
        gender ENUM('Male', 'Female', 'Other'),
        blood_group VARCHAR(5),
        medical_history TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS doctors (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        specialization VARCHAR(100),
        qualification VARCHAR(100),
        experience_years INT,
        license_number VARCHAR(50),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS appointments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        patient_id INT,
        doctor_id INT,
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    )",
    
    "CREATE TABLE IF NOT EXISTS medical_records (
        id INT PRIMARY KEY AUTO_INCREMENT,
        patient_id INT,
        doctor_id INT,
        diagnosis TEXT,
        prescription TEXT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    )"
];

foreach ($tables as $sql) {
    if (!mysqli_query($conn, $sql)) {
        die("Error creating table: " . mysqli_error($conn));
    }
}

// Check if admin user exists, if not create one
$check_admin = "SELECT * FROM users WHERE username = 'admin'";
$result = mysqli_query($conn, $check_admin);
if (mysqli_num_rows($result) == 0) {
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, password, email, phone, address, role, first_name, last_name, status) 
            VALUES ('admin', '$admin_password', 'admin@hospital.com', '1234567890', 'System Address', 'admin', 'System', 'Administrator', 'active')";
    if (!mysqli_query($conn, $sql)) {
        die("Error creating admin user: " . mysqli_error($conn));
    }
}

// Function to handle database errors
function handle_db_error($conn, $error_message) {
    $_SESSION['error'] = $error_message . ": " . mysqli_error($conn);
    return false;
}

// Function to execute queries safely
function execute_query($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    if ($result === false) {
        return handle_db_error($conn, "Query execution failed");
    }
    return $result;
}

// Verify table structure
$verify_table = "DESCRIBE users";
$result = mysqli_query($conn, $verify_table);
if ($result) {
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }
    if (!in_array('email', $columns)) {
        die("Error: email column is missing from users table");
    }
}
?> 