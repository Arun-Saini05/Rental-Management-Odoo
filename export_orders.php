<?php
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch orders for export
$orders_query = "SELECT ro.order_number, u.name as customer_name, u.email, p.name as product_name, 
                p.sales_price, ro.pickup_date, ro.expected_return_date, ro.status, ro.total_amount, ro.created_at
                FROM rental_orders ro 
                LEFT JOIN customers c ON ro.customer_id = c.id 
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN products p ON ro.vendor_id = p.vendor_id
                ORDER BY ro.created_at DESC";

$orders_result = mysqli_query($conn, $orders_query);

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="rental_orders_' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Create file pointer
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'Order Number',
    'Customer Name',
    'Customer Email',
    'Product Name',
    'Daily Rate',
    'Pickup Date',
    'Return Date',
    'Status',
    'Total Amount',
    'Created Date'
]);

// Add data rows
while ($order = mysqli_fetch_assoc($orders_result)) {
    fputcsv($output, [
        $order['order_number'],
        $order['customer_name'],
        $order['email'],
        $order['product_name'],
        '$' . number_format($order['sales_price'], 2),
        $order['pickup_date'],
        $order['expected_return_date'],
        ucfirst(str_replace('_', ' ', $order['status'])),
        '$' . number_format($order['total_amount'], 2),
        $order['created_at']
    ]);
}

// Close file pointer
fclose($output);

mysqli_close($conn);
exit();
?>
