<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin") {
    $_SESSION['redirect_url'] = $_SERVER['PHP_SELF'];
    header("location: ../index.php");
    exit;
}

// Clear the redirect URL if it exists
if (isset($_SESSION['redirect_url'])) {
    unset($_SESSION['redirect_url']);
}

$error = null;
$success = null;

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
    $specialization = sanitize_input($_POST['specialization']);
    $qualification = sanitize_input($_POST['qualification']);
    $experience = sanitize_input($_POST['experience']);
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
                VALUES ('$username', '$hashed_password', '$email', '$phone', '$address', 'doctor', '$firstname', '$lastname', 'active')";
        
        $result = execute_query($conn, $sql);
        if ($result !== false) {
            $user_id = mysqli_insert_id($conn);
            
            // Insert into doctors table
            $sql = "INSERT INTO doctors (user_id, specialization, qualification, experience) 
                    VALUES ($user_id, '$specialization', '$qualification', '$experience')";
            
            $result = execute_query($conn, $sql);
            if ($result === false) {
                // If doctor record creation fails, delete the user record
                $delete_user = "DELETE FROM users WHERE id = $user_id";
                execute_query($conn, $delete_user);
                $errors[] = "Failed to create doctor record";
            } else {
                $success = "Doctor added successfully!";
            }
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
    }
    
    if (!empty($errors)) {
        $error = implode(", ", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Doctor - MediCare Pro</title>
    <meta name="description" content="Add new doctor to MediCare Pro Hospital Management System">
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
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: var(--heading-font);
            font-weight: 700;
            line-height: 1.2;
        }

        /* Header Styles */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 0;
            z-index: 1000;
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 600;
        }

        .btn-logout {
            background: linear-gradient(135deg, var(--danger-color), #c53030);
            border: none;
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
            color: var(--white);
            font-weight: 600;
            transition: var(--transition-normal);
            text-decoration: none;
        }

        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: var(--white);
        }

        /* Main Content */
        .main-content {
            padding: 2rem 0;
            min-height: calc(100vh - 80px);
        }

        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(30px);
            border-radius: 25px;
            padding: 3rem;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.3);
            max-width: 800px;
            margin: 0 auto;
        }

        .form-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .form-title {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-subtitle {
            color: var(--light-text);
            font-size: 1.1rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 15px;
            padding: 1rem 1.2rem;
            font-size: 1rem;
            transition: var(--transition-normal);
            background: var(--white);
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

        .btn-submit {
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

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-back {
            background: linear-gradient(135deg, var(--light-text), #495057);
            border: none;
            border-radius: 15px;
            padding: 0.8rem 1.5rem;
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition-normal);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: var(--white);
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem 0;
            }
            
            .form-container {
                margin: 0 1rem;
                padding: 2rem;
            }
            
            .form-title {
                font-size: 2rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        @media (max-width: 480px) {
            .form-title {
                font-size: 1.8rem;
            }
            
            .form-container {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand" href="dashboard.php">
                    <i class="fas fa-hospital"></i>
                    MediCare Pro
                </a>
                
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <span class="d-none d-md-inline">Welcome, Admin</span>
                    <a href="../includes/logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <div class="form-container" data-aos="fade-up">
                <div class="form-header">
                    <h1 class="form-title">
                        <i class="fas fa-user-md"></i> Add New Doctor
                    </h1>
                    <p class="form-subtitle">Register a new doctor to the hospital system</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form action="add_doctor.php" method="POST" class="needs-validation" novalidate>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstname" class="form-label">
                                <i class="fas fa-user"></i> First Name
                            </label>
                            <input type="text" class="form-control" id="firstname" name="firstname" required placeholder="Enter doctor's first name">
                        </div>

                        <div class="form-group">
                            <label for="lastname" class="form-label">
                                <i class="fas fa-user"></i> Last Name
                            </label>
                            <input type="text" class="form-control" id="lastname" name="lastname" required placeholder="Enter doctor's last name">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope"></i> Email Address
                            </label>
                            <input type="email" class="form-control" id="email" name="email" required placeholder="Enter doctor's email address">
                        </div>

                        <div class="form-group">
                            <label for="phone" class="form-label">
                                <i class="fas fa-phone"></i> Phone Number
                            </label>
                            <input type="tel" class="form-control" id="phone" name="phone" required placeholder="Enter doctor's phone number">
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="address" class="form-label">
                            <i class="fas fa-map-marker-alt"></i> Address
                        </label>
                        <textarea class="form-control" id="address" name="address" rows="3" required placeholder="Enter doctor's complete address"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="specialization" class="form-label">
                                <i class="fas fa-stethoscope"></i> Specialization
                            </label>
                            <input type="text" class="form-control" id="specialization" name="specialization" required placeholder="e.g., Cardiology, Neurology">
                        </div>

                        <div class="form-group">
                            <label for="qualification" class="form-label">
                                <i class="fas fa-graduation-cap"></i> Qualification
                            </label>
                            <input type="text" class="form-control" id="qualification" name="qualification" required placeholder="e.g., MBBS, MD">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="experience" class="form-label">
                                <i class="fas fa-clock"></i> Years of Experience
                            </label>
                            <input type="number" class="form-control" id="experience" name="experience" required placeholder="Enter years of experience" min="0" max="50">
                        </div>

                        <div class="form-group">
                            <label for="username" class="form-label">
                                <i class="fas fa-user"></i> Username
                            </label>
                            <input type="text" class="form-control" id="username" name="username" required placeholder="Choose a username">
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

                    <button type="submit" class="btn btn-submit">
                        <i class="fas fa-user-plus"></i> Add Doctor
                    </button>

                    <div class="text-center">
                        <a href="dashboard.php" class="btn-back">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>

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

        // Add hover effects to form controls
        document.addEventListener('DOMContentLoaded', function() {
            const formControls = document.querySelectorAll('.form-control');
            formControls.forEach(control => {
                control.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                
                control.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html> 