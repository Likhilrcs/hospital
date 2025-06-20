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

// Handle doctor status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_status') {
        $user_id = sanitize_input($_POST['user_id']);
        $new_status = sanitize_input($_POST['status']);
        
        $sql = "UPDATE users SET status = ? WHERE id = ? AND role = 'doctor'";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "si", $new_status, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Doctor status updated successfully.";
            } else {
                $error = "Error updating doctor status: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Get all doctors with their details
$doctors = [];
$sql = "SELECT d.*, u.id as user_id, u.first_name, u.last_name, u.email, u.phone, u.status 
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        ORDER BY u.first_name, u.last_name";

if ($result = mysqli_query($conn, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $doctors[] = $row;
    }
    mysqli_free_result($result);
} else {
    $error = "Error fetching doctors: " . mysqli_error($conn);
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
    <title>Manage Doctors - Hospital Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .doctor-card {
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .doctor-header {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .doctor-body {
            padding: 15px;
        }
        .status-badge {
            font-size: 0.9em;
            padding: 5px 10px;
            border-radius: 15px;
        }
        .status-active { background-color: #d4edda; color: #155724; }
        .status-inactive { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-user-md"></i> Manage Doctors</h2>
            <div>
                <a href="add_doctor.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Doctor
                </a>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
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

        <?php if (empty($doctors)): ?>
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle"></i> No doctors found.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($doctors as $doctor): ?>
                    <div class="col-md-6">
                        <div class="card doctor-card">
                            <div class="doctor-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-user-md"></i> 
                                        Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                    </h5>
                                    <span class="status-badge status-<?php echo $doctor['status']; ?>">
                                        <?php echo ucfirst($doctor['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="doctor-body">
                                <p><strong>Specialization:</strong> <?php echo htmlspecialchars($doctor['specialization']); ?></p>
                                <p><strong>Qualification:</strong> <?php echo htmlspecialchars($doctor['qualification']); ?></p>
                                <p><strong>Experience:</strong> <?php echo htmlspecialchars($doctor['experience_years']); ?> years</p>
                                <p><strong>License:</strong> <?php echo htmlspecialchars($doctor['license_number']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($doctor['email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($doctor['phone']); ?></p>
                                
                                <div class="btn-group w-100 mt-3">
                                    <a href="edit_doctor.php?id=<?php echo $doctor['user_id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="user_id" value="<?php echo $doctor['user_id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo $doctor['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                        <button type="submit" class="btn <?php echo $doctor['status'] === 'active' ? 'btn-danger' : 'btn-success'; ?>">
                                            <i class="fas <?php echo $doctor['status'] === 'active' ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                            <?php echo $doctor['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
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