<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "doctor") {
    $_SESSION['redirect_url'] = $_SERVER['PHP_SELF'];
    header("location: ../index.php");
    exit;
}

// Clear the redirect URL if it exists
if (isset($_SESSION['redirect_url'])) {
    unset($_SESSION['redirect_url']);
}

$doctor_id = $_SESSION["id"];
$success_msg = $error_msg = "";

// Get doctor's current information
$sql = "SELECT d.*, u.first_name, u.last_name, u.email, u.phone 
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        WHERE d.id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $doctor_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $doctor = mysqli_fetch_assoc($result);
    } else {
        $error_msg = "Error fetching profile: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize input
    $first_name = sanitize_input($_POST["first_name"]);
    $last_name = sanitize_input($_POST["last_name"]);
    $email = sanitize_input($_POST["email"]);
    $phone = sanitize_input($_POST["phone"]);
    $specialization = sanitize_input($_POST["specialization"]);
    $qualification = sanitize_input($_POST["qualification"]);
    $experience = sanitize_input($_POST["experience"]);
    $consultation_fee = sanitize_input($_POST["consultation_fee"]);
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Invalid email format";
    }
    // Check if email is already taken by another user
    else {
        $check_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        if ($check_stmt = mysqli_prepare($conn, $check_sql)) {
            mysqli_stmt_bind_param($check_stmt, "si", $email, $doctor['user_id']);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                $error_msg = "Email is already taken";
            }
            mysqli_stmt_close($check_stmt);
        }
    }
    
    // Validate phone number (basic format: 10 digits)
    if (!preg_match("/^[0-9]{10}$/", $phone)) {
        $error_msg = "Phone number must be 10 digits";
    }
    
    // Validate experience (0-50 years)
    if ($experience < 0 || $experience > 50) {
        $error_msg = "Experience must be between 0 and 50 years";
    }
    
    // Validate consultation fee (0-1000)
    if ($consultation_fee < 0 || $consultation_fee > 1000) {
        $error_msg = "Consultation fee must be between $0 and $1000";
    }
    
    // If no validation errors, proceed with update
    if (empty($error_msg)) {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update users table
            $user_sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?";
            if ($user_stmt = mysqli_prepare($conn, $user_sql)) {
                mysqli_stmt_bind_param($user_stmt, "ssssi", $first_name, $last_name, $email, $phone, $doctor['user_id']);
                mysqli_stmt_execute($user_stmt);
                mysqli_stmt_close($user_stmt);
            }
            
            // Update doctors table
            $doctor_sql = "UPDATE doctors SET specialization = ?, qualification = ?, experience = ?, consultation_fee = ? WHERE id = ?";
            if ($doctor_stmt = mysqli_prepare($conn, $doctor_sql)) {
                mysqli_stmt_bind_param($doctor_stmt, "ssidi", $specialization, $qualification, $experience, $consultation_fee, $doctor_id);
                mysqli_stmt_execute($doctor_stmt);
                mysqli_stmt_close($doctor_stmt);
            }
            
            // Commit transaction
            mysqli_commit($conn);
            $success_msg = "Profile updated successfully!";
            
            // Refresh doctor data
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $doctor_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $doctor = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_msg = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Function to sanitize input
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
    <title>Update Profile - Hospital Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-card {
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-label {
            font-weight: 500;
        }
        .required-field::after {
            content: " *";
            color: red;
        }
        .validation-message {
            font-size: 0.875em;
            color: #6c757d;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-user-edit"></i> Update Profile</h2>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card profile-card">
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label required-field">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($doctor['first_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label required-field">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($doctor['last_name']); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label required-field">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($doctor['email']); ?>" required>
                            <div class="validation-message">Enter a valid email address</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label required-field">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($doctor['phone']); ?>" 
                                   pattern="[0-9]{10}" required>
                            <div class="validation-message">Enter a 10-digit phone number</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="specialization" class="form-label required-field">Specialization</label>
                            <input type="text" class="form-control" id="specialization" name="specialization" 
                                   value="<?php echo htmlspecialchars($doctor['specialization']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="qualification" class="form-label required-field">Qualification</label>
                            <input type="text" class="form-control" id="qualification" name="qualification" 
                                   value="<?php echo htmlspecialchars($doctor['qualification']); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="experience" class="form-label required-field">Experience (years)</label>
                            <input type="number" class="form-control" id="experience" name="experience" 
                                   value="<?php echo htmlspecialchars($doctor['experience']); ?>" 
                                   required min="0" max="50">
                            <div class="validation-message">Enter years of experience (0-50)</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="consultation_fee" class="form-label required-field">Consultation Fee</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="consultation_fee" name="consultation_fee" 
                                       value="<?php echo htmlspecialchars($doctor['consultation_fee']); ?>" 
                                       required min="0" max="1000" step="0.01">
                            </div>
                            <div class="validation-message">Enter fee between $0 and $1000</div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>
</html> 