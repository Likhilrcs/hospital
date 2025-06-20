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
$error = null;
$success = null;

// Get list of patients
$patients = [];
$sql = "SELECT p.id, u.first_name, u.last_name, u.email, u.phone 
        FROM patients p 
        JOIN users u ON p.user_id = u.id 
        WHERE u.status = 'active'
        ORDER BY u.first_name, u.last_name";

if ($result = mysqli_query($conn, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $patients[] = $row;
    }
    mysqli_free_result($result);
}

// Get the correct doctor ID from doctors table
$get_doctor_id = "SELECT d.id FROM doctors d WHERE d.user_id = ?";
if ($stmt = mysqli_prepare($conn, $get_doctor_id)) {
    mysqli_stmt_bind_param($stmt, "i", $doctor_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $doctor_id = $row['id']; // Update doctor_id with the correct ID from doctors table
        } else {
            $error = "Doctor record not found. Please contact support.";
        }
    }
    mysqli_stmt_close($stmt);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $patient_id = sanitize_input($_POST['patient_id']);
    $diagnosis = sanitize_input($_POST['diagnosis']);
    $prescription = sanitize_input($_POST['prescription']);
    $notes = sanitize_input($_POST['notes']);
    
    // Verify that we have a valid doctor_id
    if (!isset($doctor_id) || empty($doctor_id)) {
        $error = "Invalid doctor ID. Please contact support.";
    } else {
        $sql = "INSERT INTO medical_records (patient_id, doctor_id, diagnosis, prescription, notes) 
                VALUES (?, ?, ?, ?, ?)";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "iisss", $patient_id, $doctor_id, $diagnosis, $prescription, $notes);
            
            if (mysqli_stmt_execute($stmt)) {
                $success = "Medical record added successfully.";
            } else {
                $error = "Error adding medical record: " . mysqli_error($conn);
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
    <title>Add Medical Record - Hospital Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .record-form {
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
        <div class="record-form">
            <div class="form-header">
                <h2><i class="fas fa-file-medical"></i> Add Medical Record</h2>
                <p class="text-muted">Create a new medical record for a patient</p>
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

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="patient_id" class="form-label">Select Patient</label>
                    <select class="form-select" id="patient_id" name="patient_id" required>
                        <option value="">Choose a patient...</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>">
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?> 
                                (<?php echo htmlspecialchars($patient['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">
                        Please select a patient.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="diagnosis" class="form-label">Diagnosis</label>
                    <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3" required></textarea>
                    <div class="invalid-feedback">
                        Please provide a diagnosis.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="prescription" class="form-label">Prescription</label>
                    <textarea class="form-control" id="prescription" name="prescription" rows="3" required></textarea>
                    <div class="invalid-feedback">
                        Please provide a prescription.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Additional Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Medical Record
                    </button>
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
    </script>
</body>
</html> 