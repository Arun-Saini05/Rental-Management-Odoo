<?php
include 'config.php';

// Access Control
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'vendor'])) {
    header("Location: dashboard.php");
    exit;
}

$is_admin = $_SESSION['user_role'] === 'admin';
$user_id = $_SESSION['user_id'];

// Get parameters
$criteria = isset($_GET['criteria']) ? $_GET['criteria'] : 'revenue';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Build query based on criteria and role
switch ($criteria) {
    case 'revenue':
    case 'orders_count':
        if ($is_admin) {
            $query = "SELECT DATE(created_at) as label, SUM(total_amount) as amount, COUNT(*) as count
                      FROM rental_orders 
                      WHERE status = 'sent' 
                      AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'
                      GROUP BY DATE(created_at) 
                      ORDER BY DATE(created_at)";
        } else {
            $query = "SELECT DATE(created_at) as label, SUM(total_amount) as amount, COUNT(*) as count
                      FROM rental_orders 
                      WHERE vendor_id = $user_id 
                      AND status = 'sent' 
                      AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'
                      GROUP BY DATE(created_at) 
                      ORDER BY DATE(created_at)";
        }
        $header1 = 'Date';
        $header2 = $criteria === 'revenue' ? 'Total Amount' : 'Orders Count';
        $value_key = $criteria === 'revenue' ? 'amount' : 'count';
        break;
        
    case 'product_sales':
        if ($is_admin) {
            $query = "SELECT p.name as label, SUM(oi.subtotal) as amount 
                      FROM order_items oi
                      JOIN products p ON oi.product_id = p.id
                      JOIN orders o ON oi.order_id = o.id
                      WHERE o.status = 'sent' 
                      AND DATE(o.created_at) BETWEEN '$start_date' AND '$end_date'
                      GROUP BY p.id 
                      ORDER BY amount DESC";
        } else {
            $query = "SELECT p.name as label, SUM(oi.subtotal) as amount 
                      FROM order_items oi
                      JOIN products p ON oi.product_id = p.id
                      JOIN orders o ON oi.order_id = o.id
                      WHERE o.vendor_id = $user_id 
                      AND o.status = 'sent' 
                      AND DATE(o.created_at) BETWEEN '$start_date' AND '$end_date'
                      GROUP BY p.id 
                      ORDER BY amount DESC";
        }
        $header1 = 'Product';
        $header2 = 'Sales Amount';
        $value_key = 'amount';
        break;
        
    case 'vendor_sales':
        if ($is_admin) {
            $query = "SELECT u.name as label, SUM(o.total_amount) as amount 
                      FROM rental_orders o
                      JOIN users u ON o.vendor_id = u.id
                      WHERE o.status = 'sent' 
                      AND DATE(o.created_at) BETWEEN '$start_date' AND '$end_date'
                      GROUP BY o.vendor_id 
                      ORDER BY amount DESC";
        } else {
            $query = "SELECT 'N/A' as label, 0 as amount";
        }
        $header1 = 'Vendor';
        $header2 = 'Sales Amount';
        $value_key = 'amount';
        break;
        
    default:
        $query = "SELECT DATE(created_at) as label, SUM(total_amount) as amount
                  FROM rental_orders 
                  WHERE status = 'sent' 
                  GROUP BY DATE(created_at)";
        $header1 = 'Date';
        $header2 = 'Amount';
        $value_key = 'amount';
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="report_' . $criteria . '_' . date('Y-m-d') . '.xls"');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #4F46E5; color: white; }
    </style>
</head>
<body>
    <h2>Report: <?php echo ucfirst(str_replace('_', ' ', $criteria)); ?></h2>
    <p>Date Range: <?php echo $start_date; ?> to <?php echo $end_date; ?></p>
    <table>
        <thead>
            <tr>
                <th><?php echo $header1; ?></th>
                <th><?php echo $header2; ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $result = mysqli_query($conn, $query);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['label']) . "</td>";
                    echo "<td>" . htmlspecialchars($row[$value_key]) . "</td>";
                    echo "</tr>";
                }
            }
            ?>
        </tbody>
    </table>
</body>
</html>
