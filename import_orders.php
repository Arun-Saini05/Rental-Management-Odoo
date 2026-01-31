<?php
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['importFile'])) {
    $file = $_FILES['importFile'];
    
    // Check file type
    $allowedTypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    if (!in_array($file['type'], $allowedTypes)) {
        $response['message'] = 'Invalid file type. Please upload CSV or Excel file.';
        echo json_encode($response);
        exit();
    }
    
    // Handle CSV file
    if ($file['type'] == 'text/csv') {
        $handle = fopen($file['tmp_name'], 'r');
        
        if ($handle) {
            // Skip header row
            fgetcsv($handle);
            
            $imported = 0;
            $errors = 0;
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                try {
                    // Extract data from CSV (assuming specific column order)
                    $order_number = $data[0] ?? '';
                    $customer_name = $data[1] ?? '';
                    $product_name = $data[3] ?? '';
                    $daily_rate = floatval(str_replace('$', '', $data[4] ?? 0));
                    $pickup_date = $data[5] ?? '';
                    $return_date = $data[6] ?? '';
                    $status = $data[7] ?? 'draft';
                    $total_amount = floatval(str_replace('$', '', $data[8] ?? 0));
                    
                    // Find customer ID
                    $customer_query = "SELECT c.id FROM customers c JOIN users u ON c.user_id = u.id WHERE u.name = ?";
                    $customer_stmt = mysqli_prepare($conn, $customer_query);
                    mysqli_stmt_bind_param($customer_stmt, "s", $customer_name);
                    mysqli_stmt_execute($customer_stmt);
                    $customer_result = mysqli_stmt_get_result($customer_stmt);
                    $customer = mysqli_fetch_assoc($customer_result);
                    
                    // Find product ID
                    $product_query = "SELECT id, vendor_id FROM products WHERE name = ?";
                    $product_stmt = mysqli_prepare($conn, $product_query);
                    mysqli_stmt_bind_param($product_stmt, "s", $product_name);
                    mysqli_stmt_execute($product_stmt);
                    $product_result = mysqli_stmt_get_result($product_stmt);
                    $product = mysqli_fetch_assoc($product_result);
                    
                    if ($customer && $product) {
                        // Calculate duration and totals
                        $pickup = new DateTime($pickup_date);
                        $return = new DateTime($return_date);
                        $duration = $pickup->diff($return)->days;
                        
                        $subtotal = $daily_rate * $duration;
                        $tax_amount = $subtotal * 0.1;
                        $security_deposit = $daily_rate * 2;
                        
                        // Insert order
                        $insert_query = "INSERT INTO rental_orders (order_number, customer_id, vendor_id, status, subtotal, tax_amount, total_amount, security_deposit_total, amount_paid, pickup_date, expected_return_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)";
                        
                        $stmt = mysqli_prepare($conn, $insert_query);
                        mysqli_stmt_bind_param($stmt, "sisddddddss", $order_number, $customer['id'], $product['vendor_id'], $status, $subtotal, $tax_amount, $total_amount, $security_deposit, $pickup_date, $return_date);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $imported++;
                        } else {
                            $errors++;
                        }
                    } else {
                        $errors++;
                    }
                } catch (Exception $e) {
                    $errors++;
                }
            }
            
            fclose($handle);
            
            if ($imported > 0) {
                $response['success'] = true;
                $response['message'] = "Successfully imported {$imported} orders. " . ($errors > 0 ? "{$errors} errors occurred." : "");
            } else {
                $response['message'] = "No orders were imported. Please check your file format.";
            }
        } else {
            $response['message'] = "Error reading file.";
        }
    } else {
        // For Excel files, you would need a library like PhpSpreadsheet
        $response['message'] = "Excel import not yet implemented. Please use CSV format.";
    }
} else {
    $response['message'] = "No file uploaded.";
}

echo json_encode($response);
?>
