<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "doctor") {
    // Store the current page URL in session before redirecting
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

// Get doctor information
$sql = "SELECT d.*, u.first_name, u.last_name, u.email, u.phone, u.address 
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        WHERE d.user_id = ?";

$doctor = null;

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $doctor_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $doctor = $row;
        } else {
            $error = "Doctor information not found.";
        }
    } else {
        $error = "Error fetching doctor information: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
} else {
    $error = "Database error: " . mysqli_error($conn);
}

// Get statistics
$stats = [
    'appointments' => 0,
    'patients' => 0,
    'completed' => 0,
    'pending' => 0
];

// Count today's appointments
$sql = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE()";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $doctor['id']);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['appointments'] = $row['count'];
        }
    }
    mysqli_stmt_close($stmt);
}

// Count total patients
$sql = "SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE doctor_id = ?";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $doctor['id']);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['patients'] = $row['count'];
        }
    }
    mysqli_stmt_close($stmt);
}

// Count completed appointments
$sql = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'completed'";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $doctor['id']);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['completed'] = $row['count'];
        }
    }
    mysqli_stmt_close($stmt);
}

// Count pending appointments
$sql = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'pending'";
if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $doctor['id']);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $stats['pending'] = $row['count'];
        }
    }
    mysqli_stmt_close($stmt);
}

// Get today's appointments
$today_appointments = [];
$today = date('Y-m-d');
$sql = "SELECT a.*, p.first_name as patient_first_name, p.last_name as patient_last_name
        FROM appointments a 
        JOIN patients p ON a.patient_id = p.id 
        WHERE a.doctor_id = ? AND a.appointment_date = ?
        ORDER BY a.appointment_time ASC";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "is", $doctor['id'], $today);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $today_appointments[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - MediCare Pro</title>
    <meta name="description" content="Doctor dashboard for MediCare Pro Hospital Management System">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            /* Color Palette */
            --primary-color: #0A2342;
            --secondary-color: #008080;
            --accent-color: #B8860B;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-bg: #F8F9FA;
            --dark-text: #2C3E50;
            --light-text: #6C757D;
            --white: #FFFFFF;
            --border-color: #E9ECEF;
            
            /* Typography */
            --heading-font: 'Montserrat', sans-serif;
            --body-font: 'Inter', sans-serif;
            
            /* Spacing */
            --section-padding: 80px 0;
            --container-padding: 0 20px;
            
            /* Shadows */
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.15);
            
            /* Transitions */
            --transition-fast: 0.2s ease;
            --transition-normal: 0.3s ease;
            --transition-slow: 0.5s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--body-font);
            line-height: 1.6;
            color: var(--dark-text);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: var(--heading-font);
            font-weight: 700;
            line-height: 1.2;
        }

        /* Header Styles */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar {
            padding: 1rem 0;
        }

        .navbar-brand {
            font-family: var(--heading-font);
            font-weight: 800;
            font-size: 1.8rem;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .navbar-brand i {
            color: var(--secondary-color);
            font-size: 2rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 600;
        }

        .btn-logout {
            background: linear-gradient(135deg, var(--danger-color), #c53030);
            border: none;
            border-radius: 25px;
            padding: 0.5rem 1.5rem;
            color: var(--white);
            font-weight: 600;
            transition: var(--transition-normal);
            text-decoration: none;
        }

        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: var(--white);
        }

        /* Main Content */
        .main-content {
            padding: 2rem 0;
            min-height: calc(100vh - 80px);
        }

        .dashboard-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(30px);
            border-radius: 25px;
            padding: 2rem;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 2rem;
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .dashboard-title {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .dashboard-subtitle {
            color: var(--light-text);
            font-size: 1.1rem;
        }

        /* Doctor Info Card */
        .doctor-info-card {
            background: var(--white);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            border-left: 4px solid var(--secondary-color);
        }

        .doctor-info-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .doctor-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 2rem;
            font-weight: 600;
        }

        .doctor-details h3 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .doctor-details p {
            color: var(--light-text);
            margin-bottom: 0.3rem;
        }

        .doctor-specialty {
            background: var(--secondary-color);
            color: var(--white);
            padding: 0.3rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            box-shadow: var(--shadow-md);
            transition: var(--transition-normal);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(0, 128, 128, 0.1), transparent);
            transition: left 0.5s;
        }

        .stat-card:hover::before {
            left: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.8rem;
            color: var(--white);
            position: relative;
            z-index: 1;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .stat-label {
            color: var(--light-text);
            font-size: 1rem;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        /* Quick Actions */
        .quick-actions {
            background: var(--white);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .action-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 15px;
            padding: 1.5rem;
            color: var(--white);
            text-decoration: none;
            text-align: center;
            transition: var(--transition-normal);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            color: var(--white);
        }

        .action-btn i {
            font-size: 2rem;
        }

        /* Today's Appointments */
        .today-appointments {
            background: var(--white);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-md);
        }

        .appointment-card {
            background: var(--light-bg);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--secondary-color);
            transition: var(--transition-normal);
        }

        .appointment-card:hover {
            transform: translateX(5px);
            box-shadow: var(--shadow-md);
        }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .appointment-title {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .appointment-time {
            background: var(--secondary-color);
            color: var(--white);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .appointment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-label {
            font-weight: 600;
            color: var(--dark-text);
        }

        .detail-value {
            color: var(--light-text);
        }

        .appointment-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-small {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition-normal);
        }

        .btn-primary-small {
            background: var(--primary-color);
            color: var(--white);
        }

        .btn-success-small {
            background: var(--success-color);
            color: var(--white);
        }

        .btn-warning-small {
            background: var(--warning-color);
            color: var(--dark-text);
        }

        .btn-small:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem 0;
            }
            
            .dashboard-container {
                margin: 0 1rem;
                padding: 1.5rem;
            }
            
            .dashboard-title {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
            
            .appointment-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .appointment-details {
                grid-template-columns: 1fr;
            }
            
            .appointment-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .dashboard-title {
                font-size: 1.8rem;
            }
            
            .stat-card {
                padding: 1.5rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .action-btn {
                padding: 1rem;
            }
            
            .doctor-info-header {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand" href="dashboard.php">
                    <i class="fas fa-hospital"></i>
                    MediCare Pro
                </a>
                
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <span class="d-none d-md-inline">Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></span>
                    <a href="../includes/logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <div class="dashboard-container" data-aos="fade-up">
                <div class="dashboard-header">
                    <h1 class="dashboard-title">
                        <i class="fas fa-stethoscope"></i> Doctor Dashboard
                    </h1>
                    <p class="dashboard-subtitle">Manage your patients and appointments efficiently</p>
                </div>

                <!-- Doctor Info -->
                <div class="doctor-info-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="doctor-info-header">
                        <div class="doctor-avatar">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <div class="doctor-details">
                            <h3>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h3>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($doctor['email']); ?></p>
                            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($doctor['phone']); ?></p>
                            <span class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialization']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card" data-aos="fade-up" data-aos-delay="200">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-number" data-count="<?php echo $stats['appointments']; ?>">0</div>
                        <div class="stat-label">Total Appointments</div>
                    </div>

                    <div class="stat-card" data-aos="fade-up" data-aos-delay="300">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number" data-count="<?php echo $stats['patients']; ?>">0</div>
                        <div class="stat-label">Total Patients</div>
                    </div>

                    <div class="stat-card" data-aos="fade-up" data-aos-delay="400">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-number" data-count="<?php echo $stats['completed']; ?>">0</div>
                        <div class="stat-label">Completed</div>
                    </div>

                    <div class="stat-card" data-aos="fade-up" data-aos-delay="500">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-number" data-count="<?php echo $stats['pending']; ?>">0</div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions" data-aos="fade-up" data-aos-delay="600">
                    <h3 class="section-title">
                        <i class="fas fa-bolt"></i> Quick Actions
                    </h3>
                    <div class="actions-grid">
                        <a href="view_appointments.php" class="action-btn">
                            <i class="fas fa-calendar-alt"></i>
                            View Appointments
                        </a>
                        <a href="view_patients.php" class="action-btn">
                            <i class="fas fa-users"></i>
                            View Patients
                        </a>
                        <a href="add_medical_record.php" class="action-btn">
                            <i class="fas fa-file-medical"></i>
                            Add Medical Record
                        </a>
                        <a href="update_profile.php" class="action-btn">
                            <i class="fas fa-user-edit"></i>
                            Update Profile
                        </a>
                    </div>
                </div>

                <!-- Today's Appointments -->
                <div class="today-appointments" data-aos="fade-up" data-aos-delay="700">
                    <h3 class="section-title">
                        <i class="fas fa-calendar-day"></i> Today's Appointments
                    </h3>
                    
                    <?php if (empty($today_appointments)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times" style="font-size: 3rem; color: var(--light-text); opacity: 0.5;"></i>
                            <p class="mt-3 text-muted">No appointments scheduled for today</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($today_appointments as $appointment): ?>
                            <div class="appointment-card">
                                <div class="appointment-header">
                                    <div class="appointment-title">
                                        <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>
                                    </div>
                                    <div class="appointment-time">
                                        <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                    </div>
                                </div>
                                <div class="appointment-details">
                                    <div class="detail-item">
                                        <span class="detail-label">
                                            <i class="fas fa-sticky-note"></i> Reason:
                                        </span>
                                        <span class="detail-value">
                                            <?php echo htmlspecialchars($appointment['reason']); ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">
                                            <i class="fas fa-info-circle"></i> Status:
                                        </span>
                                        <span class="detail-value">
                                            <span class="badge bg-<?php echo $appointment['status'] === 'confirmed' ? 'success' : ($appointment['status'] === 'pending' ? 'warning' : 'secondary'); ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </span>
                                    </div>
                                </div>
                                <div class="appointment-actions">
                                    <a href="view_appointments.php?id=<?php echo $appointment['id']; ?>" class="btn-small btn-primary-small">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <?php if ($appointment['status'] === 'pending'): ?>
                                        <a href="view_appointments.php?confirm=<?php echo $appointment['id']; ?>" class="btn-small btn-success-small">
                                            <i class="fas fa-check"></i> Confirm
                                        </a>
                                    <?php endif; ?>
                                    <a href="add_medical_record.php?patient_id=<?php echo $appointment['patient_id']; ?>" class="btn-small btn-warning-small">
                                        <i class="fas fa-file-medical"></i> Add Record
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            easing: 'ease-in-out',
            once: true,
            mirror: false
        });

        // Animated counters
        function animateCounter(element, target, prefix = '', suffix = '') {
            let current = 0;
            const increment = target / 100;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.textContent = prefix + Math.floor(current) + suffix;
            }, 20);
        }

        // Trigger counter animation when elements come into view
        const observerOptions = {
            threshold: 0.5,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const element = entry.target;
                    const target = parseInt(element.getAttribute('data-count'));
                    const prefix = element.textContent.includes('$') ? '$' : '';
                    const suffix = element.textContent.includes('$') ? '' : '';
                    
                    animateCounter(element, target, prefix, suffix);
                    observer.unobserve(element);
                }
            });
        }, observerOptions);

        // Observe all stat numbers
        document.querySelectorAll('.stat-number').forEach(element => {
            observer.observe(element);
        });

        // Add hover effects to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .appointment-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = this.classList.contains('stat-card') ? 
                        'translateY(-5px) scale(1.02)' : 'translateX(5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = this.classList.contains('stat-card') ? 
                        'translateY(0) scale(1)' : 'translateX(0)';
                });
            });

            // Add click effects to action buttons
            const actionButtons = document.querySelectorAll('.action-btn, .btn-small');
            actionButtons.forEach(button => {
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