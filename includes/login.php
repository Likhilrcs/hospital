<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];
    $role = $_POST["role"];
    
    // Validate input
    if (empty($username) || empty($password) || empty($role)) {
        $_SESSION['error'] = "Please fill in all fields.";
        header("location: ../index.php");
        exit;
    }
    
    $sql = "SELECT id, username, password, first_name, last_name, role, status FROM users WHERE username = ? AND role = ?";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ss", $username, $role);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) == 1) {
                mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password, $first_name, $last_name, $role, $status);
                if (mysqli_stmt_fetch($stmt)) {
                    if (password_verify($password, $hashed_password)) {
                        if ($status === 'active') {
                            // Password is correct, start a new session
                            session_regenerate_id(true);
                            
                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["first_name"] = $first_name;
                            $_SESSION["last_name"] = $last_name;
                            $_SESSION["role"] = $role;
                            
                            // Redirect based on role and stored redirect URL
                            $redirect_url = isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : '';
                            
                            // Update last login
                            $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                            if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                                mysqli_stmt_bind_param($update_stmt, "i", $id);
                                mysqli_stmt_execute($update_stmt);
                                mysqli_stmt_close($update_stmt);
                            }
                            
                            // Redirect user to appropriate dashboard
                            switch($role) {
                                case 'admin':
                                    header("location: " . ($redirect_url ?: "../admin/dashboard.php"));
                                    break;
                                case 'doctor':
                                    header("location: " . ($redirect_url ?: "../doctor/dashboard.php"));
                                    break;
                                case 'patient':
                                    header("location: " . ($redirect_url ?: "../patient/dashboard.php"));
                                    break;
                                default:
                                    $_SESSION['error'] = "Invalid role selected.";
                                    header("location: ../index.php");
                                    break;
                            }
                            exit;
                        } else {
                            $_SESSION['error'] = "Your account is not active. Please contact the administrator.";
                        }
                    } else {
                        $_SESSION['error'] = "Invalid username or password.";
                    }
                }
            } else {
                $_SESSION['error'] = "Invalid username or password.";
            }
        } else {
            $_SESSION['error'] = "Oops! Something went wrong. Please try again later.";
        }
        
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Database error: " . mysqli_error($conn);
    }
    
    mysqli_close($conn);
    
    // If we get here, there was an error
    header("location: ../index.php");
    exit;
}
?> 