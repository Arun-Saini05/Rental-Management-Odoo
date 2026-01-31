<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Show current session data before logout
// echo "<pre>Before logout: "; print_r($_SESSION); echo "</pre>";

// Unset all session variables
$_SESSION = array();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Debug: Verify session is destroyed
// echo "<pre>After logout: "; print_r($_SESSION); echo "</pre>";

// Redirect to landing page
header('Location: ../index.php');
exit();
?>
