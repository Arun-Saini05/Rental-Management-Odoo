<?php
include 'config.php';

// Fetch products
$query = "SELECT id, name, sales_price as price FROM products ORDER BY name";
$result = mysqli_query($conn, $query);

$products = [];
while ($row = mysqli_fetch_assoc($result)) {
    $products[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'price' => $row['price']
    ];
}

header('Content-Type: application/json');
echo json_encode($products);
?>
