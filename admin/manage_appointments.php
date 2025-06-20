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

// Handle appointment status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_status') {
        $appointment_id = sanitize_input($_POST['appointment_id']);
        $new_status = sanitize_input($_POST['status']);
        
        $sql = "UPDATE appointments SET status = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "si", $new_status, $appointment_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Appointment status updated successfully.";
            } else {
                $error = "Error updating appointment status: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Get all appointments with patient and doctor details
$appointments = [];
$sql = "SELECT a.*, 
        p.id as patient_id, pu.first_name as patient_first_name, pu.last_name as patient_last_name, pu.phone as patient_phone,
        d.id as doctor_id, du.first_name as doctor_first_name, du.last_name as doctor_last_name
        FROM appointments a 
        JOIN patients p ON a.patient_id = p.id 
        JOIN users pu ON p.user_id = pu.id
        JOIN doctors d ON a.doctor_id = d.id 
        JOIN users du ON d.user_id = du.id
        ORDER BY a.appointment_date DESC, a.appointment_time DESC";

if ($result = mysqli_query($conn, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $appointments[] = $row;
    }
    mysqli_free_result($result);
} else {
    $error = "Error fetching appointments: " . mysqli_error($conn);
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
    <title>Manage Appointments - Hospital Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .appointment-card {
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .appointment-header {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .appointment-body {
            padding: 15px;
        }
        .status-badge {
            font-size: 0.9em;
            padding: 5px 10px;
            border-radius: 15px;
        }
        .status-scheduled { background-color: #cce5ff; color: #004085; }
        .status-completed { background-color: #d4edda; color: #155724; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-calendar-check"></i> Manage Appointments</h2>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
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

        <?php if (empty($appointments)): ?>
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle"></i> No appointments found.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($appointments as $appointment): ?>
                    <div class="col-md-6">
                        <div class="card appointment-card">
                            <div class="appointment-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-calendar"></i> 
                                        Appointment #<?php echo $appointment['id']; ?>
                                    </h5>
                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="appointment-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-user"></i> Patient</h6>
                                        <p><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></p>
                                        <p><small>Phone: <?php echo htmlspecialchars($appointment['patient_phone']); ?></small></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-user-md"></i> Doctor</h6>
                                        <p>Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></p>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?></p>
                                <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></p>
                                <p><strong>Reason:</strong> <?php echo htmlspecialchars($appointment['description']); ?></p>
                                
                                <?php if ($appointment['status'] === 'scheduled'): ?>
                                    <form method="POST" class="mt-3">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                        <div class="btn-group w-100">
                                            <button type="submit" name="status" value="completed" class="btn btn-success">
                                                <i class="fas fa-check"></i> Mark as Completed
                                            </button>
                                            <button type="submit" name="status" value="cancelled" class="btn btn-danger">
                                                <i class="fas fa-times"></i> Cancel Appointment
                                            </button>
                                        </div>
                                    </form>
                                <?php endif; ?>
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