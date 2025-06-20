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

// Handle patient status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_status') {
        $user_id = sanitize_input($_POST['user_id']);
        $new_status = sanitize_input($_POST['status']);
        
        $sql = "UPDATE users SET status = ? WHERE id = ? AND role = 'patient'";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "si", $new_status, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Patient status updated successfully.";
            } else {
                $error = "Error updating patient status: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Get all patients with their details
$patients = [];
$sql = "SELECT p.*, u.id as user_id, u.first_name, u.last_name, u.email, u.phone, u.status 
        FROM patients p 
        JOIN users u ON p.user_id = u.id 
        ORDER BY u.first_name, u.last_name";

if ($result = mysqli_query($conn, $sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $patients[] = $row;
    }
    mysqli_free_result($result);
} else {
    $error = "Error fetching patients: " . mysqli_error($conn);
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
    <title>Manage Patients - Hospital Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Floating background elements */
        body::before,
        body::after {
            content: '';
            position: fixed;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 6s ease-in-out infinite;
            z-index: 0;
        }

        body::before {
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        body::after {
            bottom: 10%;
            right: 10%;
            animation-delay: 3s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .page-container {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            padding: 20px;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(30px);
            border-radius: 25px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: fadeInDown 1s ease-out;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title h2 {
            color: #2d3748;
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-title p {
            color: #718096;
            font-size: 1.1rem;
            font-weight: 500;
            margin: 0;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            border-radius: 15px;
            padding: 0.8rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #718096, #4a5568);
            color: white;
            box-shadow: 0 4px 15px rgba(113, 128, 150, 0.3);
        }

        .btn:hover {
            transform: translateY(-3px);
            color: white;
            text-decoration: none;
        }

        .btn-primary:hover {
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary:hover {
            box-shadow: 0 8px 25px rgba(113, 128, 150, 0.4);
        }

        .alert {
            border-radius: 15px;
            border: none;
            margin-bottom: 1.5rem;
            font-weight: 500;
            padding: 1rem 1.5rem;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fed7d7, #feb2b2);
            color: #742a2a;
        }

        .alert-success {
            background: linear-gradient(135deg, #c6f6d5, #9ae6b4);
            color: #22543d;
        }

        .patients-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(30px);
            border-radius: 25px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: fadeInUp 1s ease-out 0.3s both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .patients-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
        }

        .patient-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            overflow: hidden;
            transition: all 0.3s ease;
            animation: fadeInUp 1s ease-out 0.5s both;
        }

        .patient-card:nth-child(2) { animation-delay: 0.7s; }
        .patient-card:nth-child(3) { animation-delay: 0.9s; }
        .patient-card:nth-child(4) { animation-delay: 1.1s; }

        .patient-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .patient-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .patient-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .patient-card:hover .patient-header::before {
            left: 100%;
        }

        .patient-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .patient-name h5 {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: rgba(72, 187, 120, 0.2);
            color: #38a169;
            border: 2px solid rgba(72, 187, 120, 0.3);
        }

        .status-inactive {
            background: rgba(245, 101, 101, 0.2);
            color: #e53e3e;
            border: 2px solid rgba(245, 101, 101, 0.3);
        }

        .patient-body {
            padding: 1.5rem;
        }

        .patient-info {
            display: grid;
            gap: 0.8rem;
            margin-bottom: 1.5rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f7fafc;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #2d3748;
            min-width: 120px;
        }

        .info-value {
            color: #4a5568;
            font-weight: 500;
        }

        .patient-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-edit {
            background: linear-gradient(135deg, #4299e1, #3182ce);
            color: white;
            flex: 1;
            justify-content: center;
        }

        .btn-status {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
            flex: 1;
            justify-content: center;
        }

        .btn-deactivate {
            background: linear-gradient(135deg, #e53e3e, #c53030);
        }

        .btn-activate {
            background: linear-gradient(135deg, #48bb78, #38a169);
        }

        .no-patients {
            text-align: center;
            padding: 3rem;
            color: #718096;
        }

        .no-patients i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .no-patients h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: #2d3748;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-container {
                padding: 15px;
            }
            
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .page-title h2 {
                font-size: 2rem;
            }
            
            .patients-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .patient-actions {
                flex-direction: column;
            }
            
            .page-header,
            .patients-container {
                padding: 1.5rem;
            }

            body::before,
            body::after {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .page-title h2 {
                font-size: 1.8rem;
            }
            
            .patient-header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .patient-info {
                gap: 0.5rem;
            }
            
            .info-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.3rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <div class="header-content">
                <div class="page-title">
                    <h2><i class="fas fa-users"></i> Manage Patients</h2>
                    <p>View and manage all registered patients</p>
                </div>
                <div class="action-buttons">
                    <a href="../register.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Patient
                    </a>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
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

        <div class="patients-container">
            <?php if (empty($patients)): ?>
                <div class="no-patients">
                    <i class="fas fa-users"></i>
                    <h3>No Patients Found</h3>
                    <p>There are currently no patients registered in the system.</p>
                </div>
            <?php else: ?>
                <div class="patients-grid">
                    <?php foreach ($patients as $patient): ?>
                        <div class="patient-card">
                            <div class="patient-header">
                                <div class="patient-header-content">
                                    <div class="patient-name">
                                        <h5>
                                            <i class="fas fa-user"></i> 
                                            <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                        </h5>
                                    </div>
                                    <span class="status-badge status-<?php echo $patient['status']; ?>">
                                        <?php echo ucfirst($patient['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="patient-body">
                                <div class="patient-info">
                                    <div class="info-item">
                                        <span class="info-label">
                                            <i class="fas fa-calendar"></i> Date of Birth:
                                        </span>
                                        <span class="info-value">
                                            <?php echo date('F j, Y', strtotime($patient['date_of_birth'])); ?>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">
                                            <i class="fas fa-venus-mars"></i> Gender:
                                        </span>
                                        <span class="info-value">
                                            <?php echo htmlspecialchars($patient['gender']); ?>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">
                                            <i class="fas fa-tint"></i> Blood Group:
                                        </span>
                                        <span class="info-value">
                                            <?php echo htmlspecialchars($patient['blood_group']); ?>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">
                                            <i class="fas fa-envelope"></i> Email:
                                        </span>
                                        <span class="info-value">
                                            <?php echo htmlspecialchars($patient['email']); ?>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">
                                            <i class="fas fa-phone"></i> Phone:
                                        </span>
                                        <span class="info-value">
                                            <?php echo htmlspecialchars($patient['phone']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="patient-actions">
                                    <a href="edit_patient.php?id=<?php echo $patient['user_id']; ?>" class="btn btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="user_id" value="<?php echo $patient['user_id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo $patient['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                        <button type="submit" class="btn btn-status <?php echo $patient['status'] === 'active' ? 'btn-deactivate' : 'btn-activate'; ?>">
                                            <i class="fas <?php echo $patient['status'] === 'active' ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                            <?php echo $patient['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to patient cards
            const patientCards = document.querySelectorAll('.patient-card');
            patientCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-10px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Add click effects to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
        });
    </script>
</body>
</html> 