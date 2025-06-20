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
$doctor = null;

// Get doctor ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("location: manage_doctors.php");
    exit;
}

$user_id = (int)$_GET['id'];

// Get doctor information
$sql = "SELECT d.*, u.id as user_id, u.first_name, u.last_name, u.email, u.phone, u.address, u.username, u.status 
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        WHERE u.id = ? AND u.role = 'doctor'";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $doctor = mysqli_fetch_assoc($result);
    } else {
        $error = "Doctor not found.";
    }
    mysqli_stmt_close($stmt);
} else {
    $error = "Database error: " . mysqli_error($conn);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && $doctor) {
    $errors = [];
    
    // Get and sanitize form data
    $firstname = sanitize_input($_POST['firstname']);
    $lastname = sanitize_input($_POST['lastname']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $address = sanitize_input($_POST['address']);
    $status = sanitize_input($_POST['status']);
    
    // Doctor-specific fields
    $specialization = sanitize_input($_POST['specialization']);
    $qualification = sanitize_input($_POST['qualification']);
    $experience_years = sanitize_input($_POST['experience_years']);
    $license_number = sanitize_input($_POST['license_number']);
    
    // Optional password change
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email already exists (excluding current doctor)
    $check_email = "SELECT * FROM users WHERE email = '$email' AND id != $user_id";
    $result = execute_query($conn, $check_email);
    if ($result === false) {
        $errors[] = "Database error occurred while checking email";
    } else if (mysqli_num_rows($result) > 0) {
        $errors[] = "Email already exists";
    }
    
    // Validate password if provided
    if (!empty($new_password)) {
        if (strlen($new_password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
    }
    
    // Validate doctor-specific fields
    if (empty($specialization)) {
        $errors[] = "Specialization is required";
    }
    
    if (empty($qualification)) {
        $errors[] = "Qualification is required";
    }
    
    if (!is_numeric($experience_years) || $experience_years < 0) {
        $errors[] = "Experience years must be a valid number";
    }
    
    if (empty($license_number)) {
        $errors[] = "License number is required";
    }
    
    // If no errors, proceed with update
    if (empty($errors)) {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update users table
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, status = ?, password = ? WHERE id = ? AND role = 'doctor'";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sssssssi", $firstname, $lastname, $email, $phone, $address, $status, $hashed_password, $user_id);
            } else {
                $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, status = ? WHERE id = ? AND role = 'doctor'";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ssssssi", $firstname, $lastname, $email, $phone, $address, $status, $user_id);
            }
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to update user information");
            }
            mysqli_stmt_close($stmt);
            
            // Update doctors table
            $sql = "UPDATE doctors SET specialization = ?, qualification = ?, experience_years = ?, license_number = ? WHERE user_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssisi", $specialization, $qualification, $experience_years, $license_number, $user_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to update doctor information");
            }
            mysqli_stmt_close($stmt);
            
            // Commit transaction
            mysqli_commit($conn);
            
            $success = "Doctor information updated successfully!";
            
            // Refresh doctor data
            $sql = "SELECT d.*, u.id as user_id, u.first_name, u.last_name, u.email, u.phone, u.address, u.username, u.status 
                    FROM doctors d 
                    JOIN users u ON d.user_id = u.id 
                    WHERE u.id = ? AND u.role = 'doctor'";
            
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                if ($result && mysqli_num_rows($result) > 0) {
                    $doctor = mysqli_fetch_assoc($result);
                }
                mysqli_stmt_close($stmt);
            }
            
        } catch (Exception $e) {
            // Rollback transaction
            mysqli_rollback($conn);
            $errors[] = $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}

// Function to sanitize input data
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($conn, $data);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Doctor - Hospital Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .form-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .section-divider {
            border-top: 2px solid #dee2e6;
            margin: 30px 0;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="form-container">
            <div class="form-header">
                <h2><i class="fas fa-user-edit"></i> Edit Doctor Information</h2>
                <p class="text-muted">
                    <?php if ($doctor): ?>
                        Editing: Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if (!$doctor): ?>
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> Doctor not found or you don't have permission to edit this doctor.
                </div>
                <a href="manage_doctors.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Manage Doctors
                </a>
            <?php else: ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $user_id); ?>" method="POST" class="needs-validation" novalidate>
                    <!-- Personal Information Section -->
                    <h4><i class="fas fa-user"></i> Personal Information</h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="firstname" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstname" name="firstname" 
                                   value="<?php echo htmlspecialchars($doctor['first_name']); ?>" required>
                            <div class="invalid-feedback">
                                Please provide a first name.
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="lastname" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastname" name="lastname" 
                                   value="<?php echo htmlspecialchars($doctor['last_name']); ?>" required>
                            <div class="invalid-feedback">
                                Please provide a last name.
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($doctor['email']); ?>" required>
                            <div class="invalid-feedback">
                                Please provide a valid email address.
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($doctor['phone']); ?>" required>
                            <div class="invalid-feedback">
                                Please provide a phone number.
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($doctor['address']); ?></textarea>
                        <div class="invalid-feedback">
                            Please provide an address.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="active" <?php echo $doctor['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $doctor['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo $doctor['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                        <div class="invalid-feedback">
                            Please select a status.
                        </div>
                    </div>

                    <div class="section-divider"></div>

                    <!-- Professional Information Section -->
                    <h4><i class="fas fa-stethoscope"></i> Professional Information</h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="specialization" class="form-label">Specialization</label>
                            <input type="text" class="form-control" id="specialization" name="specialization" 
                                   value="<?php echo htmlspecialchars($doctor['specialization']); ?>" required>
                            <div class="invalid-feedback">
                                Please provide a specialization.
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="qualification" class="form-label">Qualification</label>
                            <input type="text" class="form-control" id="qualification" name="qualification" 
                                   value="<?php echo htmlspecialchars($doctor['qualification']); ?>" required>
                            <div class="invalid-feedback">
                                Please provide a qualification.
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="experience_years" class="form-label">Years of Experience</label>
                            <input type="number" class="form-control" id="experience_years" name="experience_years" 
                                   value="<?php echo htmlspecialchars($doctor['experience_years']); ?>" 
                                   min="0" max="50" required>
                            <div class="invalid-feedback">
                                Please provide years of experience.
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="license_number" class="form-label">License Number</label>
                            <input type="text" class="form-control" id="license_number" name="license_number" 
                                   value="<?php echo htmlspecialchars($doctor['license_number']); ?>" required>
                            <div class="invalid-feedback">
                                Please provide a license number.
                            </div>
                        </div>
                    </div>

                    <div class="section-divider"></div>

                    <!-- Account Information Section -->
                    <h4><i class="fas fa-lock"></i> Account Information</h4>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($doctor['username']); ?>" readonly>
                        <small class="form-text text-muted">Username cannot be changed for security reasons.</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new_password" class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                            <div class="invalid-feedback">
                                Password must be at least 8 characters long.
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            <div class="invalid-feedback">
                                Please confirm your password.
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <a href="manage_doctors.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Manage Doctors
                            </a>
                        </div>
                        <div class="col-md-6 text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Doctor
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (password && confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        document.getElementById('new_password').addEventListener('input', function() {
            const confirmPassword = document.getElementById('confirm_password');
            if (confirmPassword.value) {
                if (this.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
        });
    </script>
</body>
</html> 