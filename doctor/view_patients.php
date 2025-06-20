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

// Get all patients with their medical records
$patients = [];
$sql = "SELECT DISTINCT p.*, u.first_name, u.last_name, u.email, u.phone, u.status,
        (SELECT COUNT(*) FROM medical_records WHERE patient_id = p.id AND doctor_id = ?) as record_count,
        (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id AND doctor_id = ?) as appointment_count
        FROM patients p 
        JOIN users u ON p.user_id = u.id 
        JOIN appointments a ON p.id = a.patient_id 
        WHERE a.doctor_id = ? AND u.status = 'active'
        ORDER BY u.first_name, u.last_name";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "iii", $doctor_id, $doctor_id, $doctor_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            // Get latest medical record for each patient
            $record_sql = "SELECT * FROM medical_records 
                          WHERE patient_id = ? AND doctor_id = ? 
                          ORDER BY created_at DESC LIMIT 1";
            
            if ($record_stmt = mysqli_prepare($conn, $record_sql)) {
                mysqli_stmt_bind_param($record_stmt, "ii", $row['id'], $doctor_id);
                mysqli_stmt_execute($record_stmt);
                $record_result = mysqli_stmt_get_result($record_stmt);
                if ($record = mysqli_fetch_assoc($record_result)) {
                    $row['latest_record'] = $record;
                }
                mysqli_stmt_close($record_stmt);
            }
            
            $patients[] = $row;
        }
    } else {
        $error = "Error fetching patients: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
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
    <title>View Patients - Hospital Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .patient-card {
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .patient-header {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .patient-body {
            padding: 15px;
        }
        .stats-badge {
            font-size: 0.9em;
            padding: 5px 10px;
            border-radius: 15px;
            margin-right: 10px;
        }
        .latest-record {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-users"></i> My Patients</h2>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($patients)): ?>
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle"></i> No patients found.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($patients as $patient): ?>
                    <div class="col-md-6">
                        <div class="card patient-card">
                            <div class="patient-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-user"></i> 
                                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                    </h5>
                                    <div>
                                        <span class="stats-badge bg-primary text-white">
                                            <i class="fas fa-file-medical"></i> <?php echo $patient['record_count']; ?> Records
                                        </span>
                                        <span class="stats-badge bg-info text-white">
                                            <i class="fas fa-calendar-check"></i> <?php echo $patient['appointment_count']; ?> Appointments
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="patient-body">
                                <p><strong>Date of Birth:</strong> <?php echo date('F j, Y', strtotime($patient['date_of_birth'])); ?></p>
                                <p><strong>Gender:</strong> <?php echo htmlspecialchars($patient['gender']); ?></p>
                                <p><strong>Blood Group:</strong> <?php echo htmlspecialchars($patient['blood_group']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phone']); ?></p>
                                
                                <?php if (isset($patient['latest_record'])): ?>
                                    <div class="latest-record">
                                        <h6><i class="fas fa-history"></i> Latest Medical Record</h6>
                                        <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($patient['latest_record']['created_at'])); ?></p>
                                        <p><strong>Diagnosis:</strong> <?php echo htmlspecialchars($patient['latest_record']['diagnosis']); ?></p>
                                        <p><strong>Prescription:</strong> <?php echo htmlspecialchars($patient['latest_record']['prescription']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="btn-group w-100 mt-3">
                                    <a href="add_medical_record.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-file-medical"></i> Add Medical Record
                                    </a>
                                    <a href="view_patient_records.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-info">
                                        <i class="fas fa-history"></i> View History
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 