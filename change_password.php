<?php
include 'config.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate passwords match
    if ($new_password !== $confirm_password) {
        header("Location: settings.php?pwd_error=New passwords do not match");
        exit;
    }
    
    // Validate minimum length
    if (strlen($new_password) < 6) {
        header("Location: settings.php?pwd_error=Password must be at least 6 characters");
        exit;
    }
    
    // Get current password from database
    $query = "SELECT password FROM users WHERE id = $user_id";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // For demo: Check if using plain text or hashed password
        // In production, always use password_verify()
        $password_valid = false;
        
        // Check if it's a hashed password
        if (password_verify($current_password, $user['password'])) {
            $password_valid = true;
        } 
        // Fallback for plain text passwords (demo mode)
        elseif ($current_password === $user['password']) {
            $password_valid = true;
        }
        
        if ($password_valid) {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $update_query = "UPDATE users SET password = '$hashed_password' WHERE id = $user_id";
            if (mysqli_query($conn, $update_query)) {
                header("Location: settings.php?pwd_success=1");
            } else {
                header("Location: settings.php?pwd_error=Failed to update password");
            }
        } else {
            header("Location: settings.php?pwd_error=Current password is incorrect");
        }
    } else {
        header("Location: settings.php?pwd_error=User not found");
    }
} else {
    header("Location: settings.php");
}
?>
