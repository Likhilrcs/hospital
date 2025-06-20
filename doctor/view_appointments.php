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

// Get the correct doctor ID from doctors table
$get_doctor_id = "SELECT d.id FROM doctors d WHERE d.user_id = ?";
if ($stmt = mysqli_prepare($conn, $get_doctor_id)) {
    mysqli_stmt_bind_param($stmt, "i", $doctor_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $doctor_id = $row['id']; // Update doctor_id with the correct ID from doctors table
            echo "<!-- Debug: Updated doctor_id: " . $doctor_id . " -->";
        } else {
            $error = "Doctor record not found. Please contact support.";
        }
    }
    mysqli_stmt_close($stmt);
}

// Add debug information
echo "<!-- Debug: Session data: " . print_r($_SESSION, true) . " -->";
echo "<!-- Debug: Doctor ID: " . $doctor_id . " -->";

// Check if doctor exists
$check_doctor = "SELECT d.id, u.first_name, u.last_name 
                 FROM doctors d 
                 JOIN users u ON d.user_id = u.id 
                 WHERE d.id = ?";
if ($stmt = mysqli_prepare($conn, $check_doctor)) {
    mysqli_stmt_bind_param($stmt, "i", $doctor_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            echo "<!-- Debug: Doctor found: " . $row['first_name'] . ' ' . $row['last_name'] . " -->";
        } else {
            $error = "Doctor not found in database. Please contact support.";
        }
    }
    mysqli_stmt_close($stmt);
}

// Check if there are any appointments
$check_appointments = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ?";
if ($stmt = mysqli_prepare($conn, $check_appointments)) {
    mysqli_stmt_bind_param($stmt, "i", $doctor_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            echo "<!-- Debug: Total appointments found: " . $row['count'] . " -->";
        }
    }
    mysqli_stmt_close($stmt);
}

// Handle status updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['appointment_id']) && isset($_POST['status'])) {
    $appointment_id = sanitize_input($_POST['appointment_id']);
    $status = sanitize_input($_POST['status']);
    
    $sql = "UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "sii", $status, $appointment_id, $doctor_id);
        if (mysqli_stmt_execute($stmt)) {
            $success = "Appointment status updated successfully.";
        } else {
            $error = "Error updating appointment status: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

// Get all appointments for the doctor
$appointments = [];
$sql = "SELECT a.*, 
               u.first_name as patient_first_name, u.last_name as patient_last_name, 
               p.date_of_birth, p.gender, p.blood_group, 
               u.phone as phone_number, 
               u.email as patient_email, u.address as patient_address
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE a.doctor_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC";

// Debug: Print the doctor_id
echo "<!-- Debug: Current doctor_id: " . $doctor_id . " -->";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $doctor_id);
    
    // Debug: Print the SQL query
    echo "<!-- Debug: SQL Query: " . $sql . " -->";
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        // Debug: Check number of rows
        $num_rows = mysqli_num_rows($result);
        echo "<!-- Debug: Number of appointments found: " . $num_rows . " -->";
        
        if ($num_rows > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $appointments[] = $row;
            }
            
            // Debug: Print first appointment data
            if (!empty($appointments)) {
                echo "<!-- Debug: First appointment data: " . print_r($appointments[0], true) . " -->";
            }
        } else {
            $error = "No appointments found for doctor ID: " . $doctor_id;
        }
    } else {
        $error = "Error executing query: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
} else {
    $error = "Error preparing statement: " . mysqli_error($conn);
}

// Debug: Print final appointments array
echo "<!-- Debug: Total appointments in array: " . count($appointments) . " -->";

// Function to sanitize input
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($conn, $data);
}

// Function to get status badge class
function get_status_badge_class($status) {
    switch ($status) {
        case 'scheduled':
            return 'bg-primary';
        case 'completed':
            return 'bg-success';
        case 'cancelled':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointments - Hospital Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4 animate-fade-in">
            <h2><i class="fas fa-calendar-check"></i> My Appointments</h2>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-auto-dismiss" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-auto-dismiss" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($appointments)): ?>
            <div class="alert alert-info animate-fade-in" role="alert">
                <i class="fas fa-info-circle"></i> No appointments found.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($appointments as $index => $appointment): ?>
                    <div class="col-md-6 animate-on-scroll" style="animation-delay: <?php echo $index * 0.1; ?>s">
                        <div class="card appointment-card">
                            <div class="appointment-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-user"></i> 
                                        <?php echo htmlspecialchars($appointment['patient_first_name']) . ' ' . htmlspecialchars($appointment['patient_last_name']); ?>
                                    </h5>
                                    <span class="badge <?php echo get_status_badge_class($appointment['status']); ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="appointment-body">
                                <div class="patient-info">
                                    <p class="mb-1">
                                        <i class="fas fa-user"></i> 
                                        <strong>Name:</strong> 
                                        <?php echo htmlspecialchars($appointment['patient_first_name']) . ' ' . htmlspecialchars($appointment['patient_last_name']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-envelope"></i> 
                                        <strong>Email:</strong> 
                                        <?php echo htmlspecialchars($appointment['patient_email']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-home"></i> 
                                        <strong>Address:</strong> 
                                        <?php echo htmlspecialchars($appointment['patient_address']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-calendar"></i> 
                                        <strong>Date:</strong> 
                                        <?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-clock"></i> 
                                        <strong>Time:</strong> 
                                        <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-birthday-cake"></i> 
                                        <strong>Age:</strong> 
                                        <?php 
                                            $dob = new DateTime($appointment['date_of_birth']);
                                            $now = new DateTime();
                                            $age = $dob->diff($now)->y;
                                            echo $age . ' years';
                                        ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-venus-mars"></i> 
                                        <strong>Gender:</strong> 
                                        <?php echo ucfirst($appointment['gender']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-tint"></i> 
                                        <strong>Blood Group:</strong> 
                                        <?php echo $appointment['blood_group']; ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-phone"></i> 
                                        <strong>Phone:</strong> 
                                        <?php echo htmlspecialchars($appointment['phone_number']); ?>
                                    </p>
                                </div>
                                
                                <div class="mb-3">
                                    <h6><i class="fas fa-comment-medical"></i> Reason for Visit</h6>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($appointment['description'])); ?></p>
                                </div>
                                
                                <?php if ($appointment['status'] === 'scheduled'): ?>
                                    <div class="d-flex justify-content-end gap-2">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="btn btn-success btn-sm" data-bs-toggle="tooltip" title="Mark as Completed">
                                                <i class="fas fa-check"></i> Complete
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="btn btn-danger btn-sm" data-bs-toggle="tooltip" title="Cancel Appointment">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html> 