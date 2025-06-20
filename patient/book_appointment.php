<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "patient") {
    // Store the current page URL in session before redirecting
    $_SESSION['redirect_url'] = $_SERVER['PHP_SELF'];
    header("location: ../index.php");
    exit;
}

// Clear the redirect URL if it exists
if (isset($_SESSION['redirect_url'])) {
    unset($_SESSION['redirect_url']);
}

$patient_id = $_SESSION["id"];
$error = null;
$success = null;

// Get list of doctors
$doctors = [];
$sql = "SELECT d.id, u.first_name, u.last_name, d.specialization 
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        WHERE u.status = 'active'";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $doctors[] = $row;
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $doctor_id = sanitize_input($_POST['doctor_id']);
    $appointment_date = sanitize_input($_POST['appointment_date']);
    $appointment_time = sanitize_input($_POST['appointment_time']);
    $description = sanitize_input($_POST['description']);

    // Validate date (must be future date)
    if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $error = "Appointment date must be in the future";
    } else {
        // Check if the doctor is available at the selected time
        $check_sql = "SELECT * FROM appointments 
                     WHERE doctor_id = ? 
                     AND appointment_date = ? 
                     AND appointment_time = ? 
                     AND status = 'scheduled'";
        
        if ($stmt = mysqli_prepare($conn, $check_sql)) {
            mysqli_stmt_bind_param($stmt, "iss", $doctor_id, $appointment_date, $appointment_time);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $error = "This time slot is already booked. Please choose another time.";
            } else {
                // Get patient ID from users table
                $patient_sql = "SELECT id FROM patients WHERE user_id = ?";
                if ($stmt2 = mysqli_prepare($conn, $patient_sql)) {
                    mysqli_stmt_bind_param($stmt2, "i", $patient_id);
                    mysqli_stmt_execute($stmt2);
                    $result = mysqli_stmt_get_result($stmt2);
                    if ($row = mysqli_fetch_assoc($result)) {
                        $patient_id = $row['id'];
                        
                        // Insert the appointment with the correct patient_id
                        $insert_sql = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, description, status) 
                                     VALUES (?, ?, ?, ?, ?, 'scheduled')";
                        
                        if ($stmt3 = mysqli_prepare($conn, $insert_sql)) {
                            mysqli_stmt_bind_param($stmt3, "iisss", $patient_id, $doctor_id, $appointment_date, $appointment_time, $description);
                            
                            if (mysqli_stmt_execute($stmt3)) {
                                $success = "Appointment booked successfully!";
                            } else {
                                $error = "Error booking appointment: " . mysqli_error($conn);
                            }
                            mysqli_stmt_close($stmt3);
                        }
                    } else {
                        // Patient record not found for this user. This is a data integrity issue.
                        $error = "Patient record not found. Please contact support or try re-registering your account.";
                    }
                    mysqli_stmt_close($stmt2);
                }
            }
            mysqli_stmt_close($stmt);
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
    <title>Book Appointment - Hospital Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .appointment-form {
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
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="appointment-form">
            <div class="form-header">
                <h2><i class="fas fa-calendar-plus"></i> Book an Appointment</h2>
                <p class="text-muted">Schedule your appointment with our doctors</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="doctor_id" class="form-label">Select Doctor</label>
                        <select class="form-select" id="doctor_id" name="doctor_id" required>
                            <option value="">Choose a doctor...</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>">
                                    Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?> 
                                    (<?php echo htmlspecialchars($doctor['specialization']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">
                            Please select a doctor.
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="appointment_date" class="form-label">Appointment Date</label>
                        <input type="date" class="form-control" id="appointment_date" name="appointment_date" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                        <div class="invalid-feedback">
                            Please select a valid date.
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="appointment_time" class="form-label">Appointment Time</label>
                        <input type="time" class="form-control" id="appointment_time" name="appointment_time" 
                               min="09:00" max="17:00" required>
                        <div class="invalid-feedback">
                            Please select a valid time between 9 AM and 5 PM.
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="description" class="form-label">Reason for Visit</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Please describe your symptoms or reason for visit" required></textarea>
                        <div class="invalid-feedback">
                            Please provide a reason for your visit.
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                    <div class="col-md-6 text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calendar-check"></i> Book Appointment
                        </button>
                    </div>
                </div>
            </form>
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

        // Set minimum time to current time if today is selected
        document.getElementById('appointment_date').addEventListener('change', function() {
            const today = new Date().toISOString().split('T')[0];
            const timeInput = document.getElementById('appointment_time');
            
            if (this.value === today) {
                const currentTime = new Date().toLocaleTimeString('en-US', { 
                    hour12: false, 
                    hour: '2-digit', 
                    minute: '2-digit' 
                });
                timeInput.min = currentTime;
            } else {
                timeInput.min = '09:00';
            }
        });
    </script>
</body>
</html> 