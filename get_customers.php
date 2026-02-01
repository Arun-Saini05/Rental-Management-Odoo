<?php
include 'config.php';

// Fetch customers
$query = "SELECT c.id, u.name, u.address FROM customers c LEFT JOIN users u ON c.user_id = u.id ORDER BY u.name";
$result = mysqli_query($conn, $query);

$customers = [];
while ($row = mysqli_fetch_assoc($result)) {
    $customers[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'address' => $row['address']
    ];
}

header('Content-Type: application/json');
echo json_encode($customers);
?>
