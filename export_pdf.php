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
        $header2 = $criteria === 'revenue' ? 'Total Amount (₹)' : 'Orders Count';
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
        $header2 = 'Sales Amount (₹)';
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
        $header2 = 'Sales Amount (₹)';
        $value_key = 'amount';
        break;
        
    default:
        $query = "SELECT DATE(created_at) as label, SUM(total_amount) as amount
                  FROM rental_orders 
                  WHERE status = 'sent' 
                  GROUP BY DATE(created_at)";
        $header1 = 'Date';
        $header2 = 'Amount (₹)';
        $value_key = 'amount';
}

// Fetch data
$data = [];
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Report - <?php echo ucfirst(str_replace('_', ' ', $criteria)); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; padding: 20px; background: #fff; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { color: #4F46E5; margin-bottom: 10px; }
        .header p { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #4F46E5; color: white; padding: 12px; text-align: left; }
        td { border: 1px solid #ddd; padding: 10px; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .print-btn { 
            background: #4F46E5; 
            color: white; 
            border: none; 
            padding: 12px 24px; 
            cursor: pointer; 
            font-size: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .print-btn:hover { background: #4338CA; }
        @media print {
            .print-btn { display: none; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
    
    <div class="header">
        <h1>Rentify Report</h1>
        <p><strong>Report Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $criteria)); ?></p>
        <p><strong>Date Range:</strong> <?php echo $start_date; ?> to <?php echo $end_date; ?></p>
        <p><strong>Generated:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th><?php echo $header1; ?></th>
                <th><?php echo $header2; ?></th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total = 0;
            $i = 1;
            foreach ($data as $row): 
                $total += floatval($row[$value_key]);
            ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo htmlspecialchars($row['label']); ?></td>
                <td><?php echo $criteria === 'orders_count' ? $row[$value_key] : '₹' . number_format($row[$value_key], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background: #f0f0f0;">
                <td colspan="2" style="text-align: right;">Total:</td>
                <td><?php echo $criteria === 'orders_count' ? $total : '₹' . number_format($total, 2); ?></td>
            </tr>
        </tfoot>
    </table>
    
    <script>
        // Auto-trigger print dialog
        // window.print();
    </script>
</body>
</html>
