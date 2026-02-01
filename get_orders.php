<?php
include 'config.php';

// Fetch orders
$query = "SELECT id, order_number FROM rental_orders ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);

$orders = [];
while ($row = mysqli_fetch_assoc($result)) {
    $orders[] = [
        'id' => $row['id'],
        'order_number' => $row['order_number']
    ];
}

header('Content-Type: application/json');
echo json_encode($orders);
?>
