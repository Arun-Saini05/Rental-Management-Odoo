<?php
include 'config.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize Inputs
    $company_name = sanitize($_POST['company_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $gstin = sanitize($_POST['gstin']);
    $address = sanitize($_POST['address']);
    
    // Logo Handling
    $logo_name = '';
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === 0) {
        $upload_dir = 'uploads/logo/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $new_name = 'logo_' . time() . '.' . $file_ext;
            if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $upload_dir . $new_name)) {
                $logo_name = $new_name;
            }
        }
    }

    // Update Query
    // First, check if row exists (id=1)
    $check = mysqli_query($conn, "SELECT id FROM settings WHERE id = 1");
    if (mysqli_num_rows($check) > 0) {
        // Update
        $query = "UPDATE settings SET 
            company_name = '$company_name',
            email = '$email',
            phone = '$phone',
            gstin = '$gstin',
            address = '$address'";
        
        if ($logo_name) {
            $query .= ", logo = '$logo_name'";
        }
        
        $query .= " WHERE id = 1";
    } else {
        // Insert
        if ($logo_name) {
             $query = "INSERT INTO settings (id, company_name, email, phone, gstin, address, logo) 
                       VALUES (1, '$company_name', '$email', '$phone', '$gstin', '$address', '$logo_name')";
        } else {
             $query = "INSERT INTO settings (id, company_name, email, phone, gstin, address) 
                       VALUES (1, '$company_name', '$email', '$phone', '$gstin', '$address')";
        }
    }

    if (mysqli_query($conn, $query)) {
        header("Location: settings.php?success=1");
    } else {
        header("Location: settings.php?error=" . urlencode(mysqli_error($conn)));
    }
} else {
    header("Location: settings.php");
}
?>
