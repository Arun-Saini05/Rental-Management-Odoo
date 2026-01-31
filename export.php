<?php
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get export type and format
$exportType = $_GET['type'] ?? 'orders';
$format = $_GET['format'] ?? 'csv';

// Set headers based on format
if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="rental_orders_' . date('Y-m-d') . '.csv"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV header
    fputcsv($output, ['Order ID', 'Order Number', 'Customer Name', 'Product Name', 'Rental Price', 'Rental Duration', 'Status', 'Created At']);
    
    // Fetch and write data
    $orders_query = "SELECT ro.id, ro.order_number, u.name as customer_name, p.name as product_name, p.sales_price as rental_price, 
                    DATEDIFF(ro.expected_return_date, ro.pickup_date) as rental_duration, ro.status, ro.created_at
                    FROM rental_orders ro 
                    LEFT JOIN customers c ON ro.customer_id = c.id 
                    LEFT JOIN users u ON c.user_id = u.id
                    LEFT JOIN products p ON ro.vendor_id = p.vendor_id
                    ORDER BY ro.created_at DESC";
    $orders_result = mysqli_query($conn, $orders_query);
    
    while ($row = mysqli_fetch_assoc($orders_result)) {
        fputcsv($output, [
            $row['id'],
            $row['order_number'] ?? '',
            $row['customer_name'] ?? 'Unknown Customer',
            $row['product_name'] ?? 'Unknown Product',
            $row['rental_price'] ?? 0,
            $row['rental_duration'] ?? 0,
            ucfirst(str_replace('_', ' ', $row['status'])),
            $row['created_at']
        ]);
    }
    
    fclose($output);
    
} elseif ($format === 'excel') {
    // For Excel export, we'll use a simple HTML table format that Excel can open
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="rental_orders_' . date('Y-m-d') . '.xls"');
    
    echo '<table border="1">';
    echo '<tr><th>Order ID</th><th>Order Number</th><th>Customer Name</th><th>Product Name</th><th>Rental Price</th><th>Rental Duration</th><th>Status</th><th>Created At</th></tr>';
    
    // Fetch data
    $orders_query = "SELECT ro.id, ro.order_number, u.name as customer_name, p.name as product_name, p.sales_price as rental_price, 
                    DATEDIFF(ro.expected_return_date, ro.pickup_date) as rental_duration, ro.status, ro.created_at
                    FROM rental_orders ro 
                    LEFT JOIN customers c ON ro.customer_id = c.id 
                    LEFT JOIN users u ON c.user_id = u.id
                    LEFT JOIN products p ON ro.vendor_id = p.vendor_id
                    ORDER BY ro.created_at DESC";
    $orders_result = mysqli_query($conn, $orders_query);
    
    while ($row = mysqli_fetch_assoc($orders_result)) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['id']) . '</td>';
        echo '<td>' . htmlspecialchars($row['order_number'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['customer_name'] ?? 'Unknown Customer') . '</td>';
        echo '<td>' . htmlspecialchars($row['product_name'] ?? 'Unknown Product') . '</td>';
        echo '<td>$' . number_format($row['rental_price'] ?? 0, 2) . '</td>';
        echo '<td>' . htmlspecialchars($row['rental_duration'] ?? 0) . ' days</td>';
        echo '<td>' . htmlspecialchars(ucfirst(str_replace('_', ' ', $row['status']))) . '</td>';
        echo '<td>' . htmlspecialchars($row['created_at']) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
}

exit();
?>
