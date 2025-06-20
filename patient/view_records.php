<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../index.php");
    exit();
}

// Get the actual patient ID from the patients table
$user_id = $_SESSION['id'];
$patient_sql = "SELECT id FROM patients WHERE user_id = ?";
$actual_patient_id = null;

if ($stmt = mysqli_prepare($conn, $patient_sql)) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $actual_patient_id = $row['id'];
    } else {
        $error = "Patient record not found.";
    }
    mysqli_stmt_close($stmt);
} else {
    $error = "Database error: " . mysqli_error($conn);
}

// Get all medical records for the current patient
if ($actual_patient_id) {
    $stmt = mysqli_prepare($conn, "
        SELECT mr.*, u.first_name as doctor_first_name, u.last_name as doctor_last_name, 
               d.specialization, u.phone as doctor_phone
        FROM medical_records mr
        JOIN doctors d ON mr.doctor_id = d.id
        JOIN users u ON d.user_id = u.id
        WHERE mr.patient_id = ?
        ORDER BY mr.created_at DESC
    ");

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $actual_patient_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = false;
        $error = "Database error: " . mysqli_error($conn);
    }
} else {
    $result = false;
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
    <title>My Medical Records - Hospital Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .medical-record-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 1rem;
            overflow: hidden;
        }
        .record-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
        }
        .record-body {
            padding: 1rem;
        }
        .diagnosis-content, .prescription-content, .notes-content {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        .doctor-info {
            background-color: #e3f2fd;
            padding: 1rem;
            border-radius: 5px;
            border-left: 4px solid #2196f3;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Main Content -->
            <main class="col-12 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">My Medical Records</h1>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php elseif ($result && mysqli_num_rows($result) > 0): ?>
                    <div class="row">
                        <?php while ($record = mysqli_fetch_assoc($result)): ?>
                            <div class="col-12 mb-4">
                                <div class="medical-record-card">
                                    <div class="record-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0">
                                                <i class="fas fa-user-md"></i>
                                                Dr. <?php echo htmlspecialchars($record['doctor_first_name'] . ' ' . $record['doctor_last_name']); ?>
                                                <span class="badge bg-light text-dark ms-2"><?php echo htmlspecialchars($record['specialization']); ?></span>
                                            </h5>
                                            <span class="text-white"><?php echo date('F j, Y', strtotime($record['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="record-body">
                                        <div class="doctor-info mb-4">
                                            <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Doctor Information</h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p class="mb-2">
                                                        <i class="fas fa-user-md me-2"></i>
                                                        Dr. <?php echo htmlspecialchars($record['doctor_first_name'] . ' ' . $record['doctor_last_name']); ?>
                                                    </p>
                                                    <p class="mb-2">
                                                        <i class="fas fa-stethoscope me-2"></i>
                                                        <?php echo htmlspecialchars($record['specialization']); ?>
                                                    </p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p class="mb-2">
                                                        <i class="fas fa-phone me-2"></i>
                                                        <?php echo htmlspecialchars($record['doctor_phone']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="diagnosis-content">
                                                    <h6 class="mb-2"><i class="fas fa-stethoscope me-2"></i>Diagnosis</h6>
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="prescription-content">
                                                    <h6 class="mb-2"><i class="fas fa-prescription me-2"></i>Prescription</h6>
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($record['prescription'])); ?></p>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if (!empty($record['notes'])): ?>
                                            <div class="notes-content mt-4">
                                                <h6 class="mb-2"><i class="fas fa-clipboard me-2"></i>Additional Notes</h6>
                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No medical records found.
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>