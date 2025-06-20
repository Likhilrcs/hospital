<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "patient") {
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

// Get the actual patient ID from the patients table
$patient_sql = "SELECT id FROM patients WHERE user_id = ?";
$actual_patient_id = null;

if ($stmt = mysqli_prepare($conn, $patient_sql)) {
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
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

// Get all prescriptions for the patient
$prescriptions = [];
if ($actual_patient_id) {
    $sql = "SELECT mr.*, u.first_name as doctor_first_name, u.last_name as doctor_last_name, 
            d.specialization, d.qualification
            FROM medical_records mr
            JOIN doctors d ON mr.doctor_id = d.id
            JOIN users u ON d.user_id = u.id
            WHERE mr.patient_id = ? AND mr.prescription IS NOT NULL AND mr.prescription != ''
            ORDER BY mr.created_at DESC";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $actual_patient_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $prescriptions[] = $row;
            }
        } else {
            $error = "Error fetching prescriptions: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = "Database error: " . mysqli_error($conn);
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
    <title>Prescriptions - Hospital Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4 animate-fade-in">
            <h2><i class="fas fa-prescription"></i> My Prescriptions</h2>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-auto-dismiss" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($prescriptions)): ?>
            <div class="alert alert-info animate-fade-in" role="alert">
                <i class="fas fa-info-circle"></i> No prescriptions found.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($prescriptions as $index => $prescription): ?>
                    <div class="col-md-6 animate-on-scroll" style="animation-delay: <?php echo $index * 0.1; ?>s">
                        <div class="card prescription-card">
                            <div class="prescription-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-calendar-check"></i> 
                                        <?php echo date('F j, Y', strtotime($prescription['created_at'])); ?>
                                    </h5>
                                    <span class="badge bg-primary">
                                        <i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="prescription-body">
                                <div class="doctor-info">
                                    <h6><i class="fas fa-user-md"></i> Prescribed By</h6>
                                    <p class="mb-1">
                                        <strong>Name:</strong> 
                                        Dr. <?php echo htmlspecialchars($prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Specialization:</strong> 
                                        <?php echo htmlspecialchars($prescription['specialization']); ?>
                                    </p>
                                    <p class="mb-0">
                                        <strong>Qualification:</strong> 
                                        <?php echo htmlspecialchars($prescription['qualification']); ?>
                                    </p>
                                </div>
                                
                                <div class="mb-3">
                                    <h6><i class="fas fa-stethoscope"></i> Diagnosis</h6>
                                    <div class="diagnosis-content">
                                        <?php echo nl2br(htmlspecialchars($prescription['diagnosis'])); ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <h6><i class="fas fa-prescription"></i> Prescription</h6>
                                    <div class="prescription-content">
                                        <?php echo nl2br(htmlspecialchars($prescription['prescription'])); ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($prescription['notes'])): ?>
                                    <div class="mb-3">
                                        <h6><i class="fas fa-clipboard-list"></i> Additional Notes</h6>
                                        <div class="notes-content">
                                            <?php echo nl2br(htmlspecialchars($prescription['notes'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="text-end mt-3">
                                    <button class="btn btn-primary print-prescription" data-bs-toggle="tooltip" title="Print Prescription">
                                        <i class="fas fa-print"></i> Print Prescription
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Print prescription functionality
        document.querySelectorAll('.print-prescription').forEach(button => {
            button.addEventListener('click', function() {
                const prescriptionCard = this.closest('.prescription-card');
                const printWindow = window.open('', '_blank');
                
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>Prescription</title>
                            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                            <style>
                                body { padding: 20px; }
                                .prescription-header { margin-bottom: 20px; }
                                .doctor-info { margin-bottom: 20px; }
                                .prescription-content { white-space: pre-line; }
                                @media print {
                                    .no-print { display: none; }
                                }
                            </style>
                        </head>
                        <body>
                            <div class="container">
                                ${prescriptionCard.innerHTML}
                            </div>
                            <div class="text-center mt-4 no-print">
                                <button onclick="window.print()" class="btn btn-primary">Print</button>
                            </div>
                        </body>
                    </html>
                `);
                
                printWindow.document.close();
            });
        });
    </script>
</body>
</html> 