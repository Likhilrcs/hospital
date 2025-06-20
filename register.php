<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';

// Clear any login-related messages when accessing registration page
unset($_SESSION['error']);

// Get statistics
$stats = [
    'doctors' => 0,
    'patients' => 0,
    'appointments' => 0
];

// Count total doctors
$sql = "SELECT COUNT(*) as count FROM doctors";
if ($stmt = mysqli_prepare($conn, $sql)) {
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['doctors'] = $row['count'];
        }
    }
    mysqli_stmt_close($stmt);
}

// Count total patients
$sql = "SELECT COUNT(*) as count FROM patients";
if ($stmt = mysqli_prepare($conn, $sql)) {
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['patients'] = $row['count'];
        }
    }
    mysqli_stmt_close($stmt);
}

// Count total appointments
$sql = "SELECT COUNT(*) as count FROM appointments";
if ($stmt = mysqli_prepare($conn, $sql)) {
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['appointments'] = $row['count'];
        }
    }
    mysqli_stmt_close($stmt);
}

// Check database connection
if (!$conn) {
    $_SESSION['error'] = "Database connection failed. Please try again later.";
    header("Location: index.php");
    exit();
}

// Function to sanitize input data
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($conn, $data);
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $errors = [];
    
    // Get and sanitize form data
    $firstname = sanitize_input($_POST['firstname']);
    $lastname = sanitize_input($_POST['lastname']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $address = sanitize_input($_POST['address']);
    $role = sanitize_input($_POST['role']);
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email already exists
    $check_email = "SELECT * FROM users WHERE email = '$email'";
    $result = execute_query($conn, $check_email);
    if ($result === false) {
        $errors[] = "Database error occurred while checking email";
    } else if (mysqli_num_rows($result) > 0) {
        $errors[] = "Email already exists";
    }
    
    // Check if username already exists
    $check_username = "SELECT * FROM users WHERE username = '$username'";
    $result = execute_query($conn, $check_username);
    if ($result === false) {
        $errors[] = "Database error occurred while checking username";
    } else if (mysqli_num_rows($result) > 0) {
        $errors[] = "Username already exists";
    }
    
    // Validate password
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert into users table
        $sql = "INSERT INTO users (username, password, email, phone, address, role, first_name, last_name, status) 
                VALUES ('$username', '$hashed_password', '$email', '$phone', '$address', '$role', '$firstname', '$lastname', 'active')";
        
        $result = execute_query($conn, $sql);
        if ($result !== false) {
            $user_id = mysqli_insert_id($conn);
            
            // Create role-specific record
            switch ($role) {
                case 'patient':
                    $sql = "INSERT INTO patients (user_id) VALUES ($user_id)";
                    break;
                default:
                    $errors[] = "Invalid role selected";
                    break;
            }
            
            if (isset($sql) && $role === 'patient') {
                $result = execute_query($conn, $sql);
                if ($result === false) {
                    // If role-specific record creation fails, delete the user record
                    $delete_user = "DELETE FROM users WHERE id = $user_id";
                    execute_query($conn, $delete_user);
                    $errors[] = "Failed to create role-specific record";
                } else {
                    $_SESSION['success'] = "Registration successful! Please login.";
                    header("Location: index.php");
                    exit();
                }
            }
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MediCare Pro Hospital Management System</title>
    <meta name="description" content="Join MediCare Pro - Register for our advanced hospital management system and experience professional healthcare management.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            /* Color Palette */
            --primary-color: #0A2342;
            --secondary-color: #008080;
            --accent-color: #B8860B;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-bg: #F8F9FA;
            --dark-text: #2C3E50;
            --light-text: #6C757D;
            --white: #FFFFFF;
            --border-color: #E9ECEF;
            
            /* Typography */
            --heading-font: 'Montserrat', sans-serif;
            --body-font: 'Inter', sans-serif;
            
            /* Spacing */
            --section-padding: 80px 0;
            --container-padding: 0 20px;
            
            /* Shadows */
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.15);
            
            /* Transitions */
            --transition-fast: 0.2s ease;
            --transition-normal: 0.3s ease;
            --transition-slow: 0.5s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--body-font);
            line-height: 1.6;
            color: var(--dark-text);
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: var(--heading-font);
            font-weight: 700;
            line-height: 1.2;
        }

        /* Header Styles */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            z-index: 1000;
            transition: var(--transition-normal);
        }

        .header.scrolled {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: var(--shadow-md);
        }

        .navbar {
            padding: 1rem 0;
        }

        .navbar-brand {
            font-family: var(--heading-font);
            font-weight: 800;
            font-size: 1.8rem;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .navbar-brand i {
            color: var(--secondary-color);
            font-size: 2rem;
        }

        .navbar-nav .nav-link {
            font-weight: 500;
            color: var(--dark-text);
            margin: 0 1rem;
            position: relative;
            transition: var(--transition-fast);
        }

        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--secondary-color);
            transition: var(--transition-normal);
        }

        .navbar-nav .nav-link:hover::after {
            width: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 50px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: var(--transition-normal);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: var(--white);
            padding: 120px 0 80px;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 900;
            margin-bottom: 1.5rem;
            background: linear-gradient(45deg, var(--white), #E8F4FD);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            font-weight: 400;
        }

        /* Features Section */
        .features-section {
            background: var(--light-bg);
            padding: var(--section-padding);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: var(--white);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            box-shadow: var(--shadow-md);
            transition: var(--transition-normal);
            border: 1px solid var(--border-color);
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.8rem;
            color: var(--white);
        }

        .feature-card h4 {
            font-size: 1.2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .feature-card p {
            color: var(--light-text);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Register Section */
        .register-section {
            background: var(--white);
            padding: var(--section-padding);
        }

        .register-container {
            max-width: 600px;
            margin: 0 auto;
            background: var(--white);
            border-radius: 25px;
            padding: 3rem;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-color);
        }

        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .register-header h3 {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .register-header p {
            color: var(--light-text);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark-text);
            margin-bottom: 0.5rem;
        }

        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 15px;
            padding: 1rem 1.2rem;
            font-size: 1rem;
            transition: var(--transition-normal);
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(0, 128, 128, 0.1);
            outline: none;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .btn-register {
            width: 100%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 15px;
            padding: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--white);
            transition: var(--transition-normal);
            margin-bottom: 1.5rem;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .login-link {
            text-align: center;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .login-link a {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition-fast);
        }

        .login-link a:hover {
            color: var(--primary-color);
        }

        /* Alert Styles */
        .alert {
            border-radius: 15px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fed7d7, #feb2b2);
            color: #742a2a;
        }

        .alert-success {
            background: linear-gradient(135deg, #c6f6d5, #9ae6b4);
            color: #22543d;
        }

        .alert ul {
            margin-bottom: 0;
            padding-left: 1.5rem;
        }

        .alert li {
            margin-bottom: 0.3rem;
        }

        /* Footer */
        .footer {
            background: var(--primary-color);
            color: var(--white);
            padding: 3rem 0 1rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-section h4 {
            margin-bottom: 1rem;
            color: var(--white);
        }

        .footer-section p,
        .footer-section a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition-fast);
        }

        .footer-section a:hover {
            color: var(--white);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            padding-top: 1rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .register-container {
                margin: 0 1rem;
                padding: 2rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .navbar-nav {
                text-align: center;
                margin-top: 1rem;
            }
        }

        @media (max-width: 480px) {
            .hero-title {
                font-size: 2rem;
            }
            
            .register-header h3 {
                font-size: 1.8rem;
            }
            
            .feature-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header" id="header">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand" href="index.php">
                    <i class="fas fa-hospital"></i>
                    MediCare Pro
                </a>
                
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php#home">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php#features">Features</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php#about">About</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php#contact">Contact</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary" href="index.php#login">Login</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content" data-aos="fade-right">
                        <h1 class="hero-title">Join MediCare Pro</h1>
                        <p class="hero-subtitle">Register now to access our comprehensive healthcare management system and experience world-class medical care at your fingertips.</p>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="text-center" data-aos="fade-left">
                        <i class="fas fa-user-plus" style="font-size: 15rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <div class="features-grid">
                <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <h4>Expert Doctors</h4>
                    <p>Access to qualified healthcare professionals with years of experience</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h4>Easy Booking</h4>
                    <p>Simple and intuitive appointment scheduling system</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-icon">
                        <i class="fas fa-file-medical"></i>
                    </div>
                    <h4>Medical Records</h4>
                    <p>Secure digital health records accessible anytime</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h4>Secure Platform</h4>
                    <p>Your data is protected with industry-standard security</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Register Section -->
    <section class="register-section">
        <div class="container">
            <div class="register-container" data-aos="zoom-in">
                <div class="register-header">
                    <h3><i class="fas fa-user-plus"></i> Create Account</h3>
                    <p>Join our healthcare community today</p>
                </div>

                <?php
                if (isset($_SESSION['errors']) && !empty($_SESSION['errors'])) {
                    echo '<div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle"></i> Please fix the following errors:
                            <ul class="mb-0 mt-2">';
                    foreach ($_SESSION['errors'] as $error) {
                        echo '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    echo '</ul>
                          </div>';
                    unset($_SESSION['errors']);
                }

                if (isset($_SESSION['success'])) {
                    echo '<div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle"></i> ' . htmlspecialchars($_SESSION['success']) . '
                          </div>';
                    unset($_SESSION['success']);
                }
                ?>

                <form action="register.php" method="POST" class="needs-validation" novalidate>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstname" class="form-label">
                                <i class="fas fa-user"></i> First Name
                            </label>
                            <input type="text" class="form-control" id="firstname" name="firstname" required placeholder="Enter your first name">
                        </div>

                        <div class="form-group">
                            <label for="lastname" class="form-label">
                                <i class="fas fa-user"></i> Last Name
                            </label>
                            <input type="text" class="form-control" id="lastname" name="lastname" required placeholder="Enter your last name">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" class="form-control" id="email" name="email" required placeholder="Enter your email address">
                    </div>

                    <div class="form-group">
                        <label for="phone" class="form-label">
                            <i class="fas fa-phone"></i> Phone Number
                        </label>
                        <input type="tel" class="form-control" id="phone" name="phone" required placeholder="Enter your phone number">
                    </div>

                    <div class="form-group">
                        <label for="address" class="form-label">
                            <i class="fas fa-map-marker-alt"></i> Address
                        </label>
                        <textarea class="form-control" id="address" name="address" rows="3" required placeholder="Enter your complete address"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="username" class="form-label">
                                <i class="fas fa-user"></i> Username
                            </label>
                            <input type="text" class="form-control" id="username" name="username" required placeholder="Choose a username">
                        </div>

                        <div class="form-group">
                            <label for="role" class="form-label">
                                <i class="fas fa-user-tag"></i> Register as
                            </label>
                            <select class="form-control" id="role" name="role" required>
                                <option value="">Select your role</option>
                                <option value="patient">Patient</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock"></i> Password
                            </label>
                            <input type="password" class="form-control" id="password" name="password" required placeholder="Create a strong password">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label">
                                <i class="fas fa-lock"></i> Confirm Password
                            </label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-register">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>

                    <div class="login-link">
                        <p>Already have an account? <a href="index.php#login">Sign in here</a></p>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4><i class="fas fa-hospital"></i> MediCare Pro</h4>
                    <p>Advanced hospital management system designed to streamline healthcare operations and improve patient care.</p>
                </div>
                
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <p><a href="index.php#home">Home</a></p>
                    <p><a href="index.php#features">Features</a></p>
                    <p><a href="index.php#about">About</a></p>
                    <p><a href="index.php#contact">Contact</a></p>
                </div>
                
                <div class="footer-section">
                    <h4>Contact Info</h4>
                    <p><i class="fas fa-phone"></i> +1 (555) 123-4567</p>
                    <p><i class="fas fa-envelope"></i> info@medicarepro.com</p>
                    <p><i class="fas fa-map-marker-alt"></i> 123 Healthcare Ave, Medical City</p>
                </div>
                
                <div class="footer-section">
                    <h4>Follow Us</h4>
                    <p><a href="#"><i class="fab fa-facebook"></i> Facebook</a></p>
                    <p><a href="#"><i class="fab fa-twitter"></i> Twitter</a></p>
                    <p><a href="#"><i class="fab fa-linkedin"></i> LinkedIn</a></p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2024 MediCare Pro. All rights reserved. | Privacy Policy | Terms of Service</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            easing: 'ease-in-out',
            once: true,
            mirror: false
        });

        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()

        // Enhanced password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        document.getElementById('password').addEventListener('input', function() {
            const confirmPassword = document.getElementById('confirm_password');
            if (confirmPassword.value) {
                confirmPassword.dispatchEvent(new Event('input'));
            }
        });

        // Add hover effects to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.feature-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
        });
    </script>
</body>
</html> 