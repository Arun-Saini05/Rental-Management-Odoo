<?php
include 'config.php';

// Access Control
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'vendor'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$is_admin = $_SESSION['user_role'] === 'admin';
$user_id = $_SESSION['user_id'];

// Get parameters
$criteria = isset($_GET['criteria']) ? $_GET['criteria'] : 'revenue';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

$labels = [];
$values = [];

// Build query based on criteria and role
switch ($criteria) {
    case 'revenue':
        if ($is_admin) {
            $query = "SELECT DATE(created_at) as label, SUM(total_amount) as value 
                      FROM rental_orders 
                      WHERE status = 'sent' 
                      AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'
                      GROUP BY DATE(created_at) 
                      ORDER BY DATE(created_at)";
        } else {
            $query = "SELECT DATE(created_at) as label, SUM(total_amount) as value 
                      FROM rental_orders 
                      WHERE vendor_id = $user_id 
                      AND status = 'sent' 
                      AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'
                      GROUP BY DATE(created_at) 
                      ORDER BY DATE(created_at)";
        }
        break;
        
    case 'orders_count':
        if ($is_admin) {
            $query = "SELECT DATE(created_at) as label, COUNT(*) as value 
                      FROM rental_orders 
                      WHERE status = 'sent' 
                      AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'
                      GROUP BY DATE(created_at) 
                      ORDER BY DATE(created_at)";
        } else {
            $query = "SELECT DATE(created_at) as label, COUNT(*) as value 
                      FROM rental_orders 
                      WHERE vendor_id = $user_id 
                      AND status = 'sent' 
                      AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'
                      GROUP BY DATE(created_at) 
                      ORDER BY DATE(created_at)";
        }
        break;
        
    case 'product_sales':
        if ($is_admin) {
            $query = "SELECT p.name as label, SUM(oi.subtotal) as value 
                      FROM order_items oi
                      JOIN products p ON oi.product_id = p.id
                      JOIN orders o ON oi.order_id = o.id
                      WHERE o.status = 'sent' 
                      AND DATE(o.created_at) BETWEEN '$start_date' AND '$end_date'
                      GROUP BY p.id 
                      ORDER BY value DESC
                      LIMIT 10";
        } else {
            $query = "SELECT p.name as label, SUM(oi.subtotal) as value 
                      FROM order_items oi
                      JOIN products p ON oi.product_id = p.id
                      JOIN orders o ON oi.order_id = o.id
                      WHERE o.vendor_id = $user_id 
                      AND o.status = 'sent' 
                      AND DATE(o.created_at) BETWEEN '$start_date' AND '$end_date'
                      GROUP BY p.id 
                      ORDER BY value DESC
                      LIMIT 10";
        }
        break;
        
    case 'vendor_sales':
        // Admin only
        if ($is_admin) {
            $query = "SELECT u.name as label, SUM(o.total_amount) as value 
                      FROM rental_orders o
                      JOIN users u ON o.vendor_id = u.id
                      WHERE o.status = 'sent' 
                      AND DATE(o.created_at) BETWEEN '$start_date' AND '$end_date'
                      GROUP BY o.vendor_id 
                      ORDER BY value DESC
                      LIMIT 10";
        } else {
            // Vendor cannot access this
            $query = "SELECT 'N/A' as label, 0 as value";
        }
        break;
        
    default:
        $query = "SELECT DATE(created_at) as label, SUM(total_amount) as value 
                  FROM rental_orders 
                  WHERE status = 'sent' 
                  GROUP BY DATE(created_at) 
                  ORDER BY DATE(created_at)";
}

$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $labels[] = $row['label'];
        $values[] = floatval($row['value']);
    }
}

// Return JSON
header('Content-Type: application/json');
echo json_encode([
    'labels' => $labels,
    'values' => $values
]);
?>
