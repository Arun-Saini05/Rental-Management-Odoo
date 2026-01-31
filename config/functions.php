<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isVendor() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'vendor';
}

function isCustomer() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'customer';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../auth/login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ../index.php');
        exit();
    }
}

function requireVendor() {
    requireLogin();
    if (!isVendor() && !isAdmin()) {
        header('Location: ../index.php');
        exit();
    }
}

function generateQuotationNo() {
    return 'Q' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
}

function generateOrderNo() {
    return 'SO' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
}

function generateInvoiceNo() {
    return 'INV' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
}

function calculateRentalPrice($product_id, $variant_id, $start_date, $end_date, $period_type) {
    $db = new Database();
    
    $sql = "SELECT price FROM rental_pricing WHERE 
            product_id = ? AND 
            variant_id = ? AND 
            period_type = ? AND 
            is_active = 1";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("iis", $product_id, $variant_id, $period_type);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $unit_price = $row['price'];
        
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $interval = $start->diff($end);
        
        switch($period_type) {
            case 'hour':
                $duration = $interval->h + ($interval->days * 24);
                break;
            case 'day':
                $duration = $interval->days;
                break;
            case 'week':
                $duration = floor($interval->days / 7);
                break;
            case 'month':
                $duration = floor($interval->days / 30);
                break;
            default:
                $duration = 1;
        }
        
        return $unit_price * $duration;
    }
    
    return 0;
}

function checkProductAvailability($product_id, $variant_id, $start_date, $end_date, $quantity = 1) {
    $db = new Database();
    
    // Get total available quantity
    $sql = "SELECT quantity_available FROM products WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $available_quantity = $product['quantity_available'];
    
    // Get already booked quantity for the period
    $sql = "SELECT SUM(rol.quantity) as booked_quantity 
            FROM rental_order_lines rol 
            JOIN rental_orders ro ON rol.order_id = ro.id 
            WHERE rol.product_id = ? 
            AND rol.variant_id = ? 
            AND ro.status NOT IN ('cancelled', 'completed')
            AND (
                (rol.rental_start_date <= ? AND rol.rental_end_date >= ?) OR
                (rol.rental_start_date <= ? AND rol.rental_end_date >= ?) OR
                (rol.rental_start_date >= ? AND rol.rental_end_date <= ?)
            )";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param("iisssss", $product_id, $variant_id, $start_date, $start_date, $end_date, $end_date, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $booked = $result->fetch_assoc();
    $booked_quantity = $booked['booked_quantity'] ?: 0;
    
    return ($available_quantity - $booked_quantity) >= $quantity;
}

function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

function formatDate($date) {
    return date('M d, Y h:i A', strtotime($date));
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function uploadFile($file, $target_dir, $max_size = 5242880) {
    $target_file = $target_dir . basename($file["name"]);
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check file size
    if ($file["size"] > $max_size) {
        return ["success" => false, "message" => "File is too large."];
    }
    
    // Allow certain file formats
    $allowed_types = ["jpg", "jpeg", "png", "gif"];
    if (!in_array($file_type, $allowed_types)) {
        return ["success" => false, "message" => "Only JPG, JPEG, PNG & GIF files are allowed."];
    }
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ["success" => true, "file_path" => $target_file];
    } else {
        return ["success" => false, "message" => "Error uploading file."];
    }
}

function SKU($id)
{
    return "PRD-" . $id;
}

?>
