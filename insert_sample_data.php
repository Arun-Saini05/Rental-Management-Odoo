<?php
include 'config.php';

echo "<h2>Inserting Sample Data</h2>";

// Insert sample users
$users = [
    ['John Smith', 'john.smith@email.com', 'password123', 'customer', '555-0101', '123 Main St', 'City', 'State', '12345'],
    ['Mark Wood', 'mark.wood@email.com', 'password123', 'customer', '555-0102', '456 Oak Ave', 'City', 'State', '12345'],
    ['Alex Johnson', 'alex.johnson@email.com', 'password123', 'customer', '555-0103', '789 Pine Rd', 'City', 'State', '12345'],
    ['Admin User', 'admin@rentify.com', 'admin123', 'admin', '555-0000', 'Admin Office', 'City', 'State', '12345']
];

foreach ($users as $user) {
    $sql = "INSERT IGNORE INTO users (name, email, password, role, phone, address, city, state, postal_code, is_active, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssssssss", $user[0], $user[1], $user[2], $user[3], $user[4], $user[5], $user[6], $user[7], $user[8]);
    if (mysqli_stmt_execute($stmt)) {
        echo "<p style='color: green;'>✓ User '{$user[0]}' added</p>";
    }
}

// Insert sample customers (link to users)
$get_users = "SELECT id, name FROM users WHERE role = 'customer'";
$users_result = mysqli_query($conn, $get_users);

while ($user = mysqli_fetch_assoc($users_result)) {
    $sql = "INSERT IGNORE INTO customers (user_id, credit_limit, total_orders) VALUES (?, 5000.00, 0)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user['id']);
    if (mysqli_stmt_execute($stmt)) {
        echo "<p style='color: green;'>✓ Customer record for '{$user['name']}' added</p>";
    }
}

// Insert sample products
$products = [
    ['TV', '55-inch Smart TV', 1, 'TV001', 800.00, 1450.00, 10, 0, 10, 1, 1, 1, '["tv1.jpg"]'],
    ['Printer', 'Laser Printer', 1, 'PRN001', 30.00, 50.00, 15, 0, 15, 1, 1, 1, '["printer1.jpg"]'],
    ['Car', 'Sedan Rental', 2, 'CAR001', 500.00, 775.00, 5, 0, 5, 1, 1, 1, '["car1.jpg"]'],
    ['Projector', 'HD Projector', 1, 'PRJ001', 10.00, 14.50, 8, 0, 8, 1, 1, 1, '["proj1.jpg"]'],
    ['Games', 'Video Game Console', 3, 'GME001', 30.00, 50.00, 12, 0, 12, 1, 1, 1, '["game1.jpg"]']
];

foreach ($products as $product) {
    $sql = "INSERT IGNORE INTO products (name, description, category_id, sku, cost_price, sales_price, quantity_on_hand, quantity_reserved, quantity_available, is_rentable, is_published, vendor_id, images) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sisdddiiiiis", $product[0], $product[1], $product[2], $product[3], $product[4], $product[5], $product[6], $product[7], $product[8], $product[9], $product[10], $product[11], $product[12]);
    if (mysqli_stmt_execute($stmt)) {
        echo "<p style='color: green;'>✓ Product '{$product[0]}' added</p>";
    }
}

// Insert sample rental orders
$orders = [
    ['ORD-001', 1, 1, 'draft', 1450.00, 145.00, 1595.00, 200.00, 0.00, '2024-01-15', '2024-01-22', NULL, 1, 'Sample order 1'],
    ['ORD-002', 2, 2, 'sent', 50.00, 5.00, 55.00, 50.00, 55.00, '2024-01-16', '2024-01-19', NULL, 2, 'Sample order 2'],
    ['ORD-003', 3, 3, 'confirmed', 775.00, 77.50, 852.50, 100.00, 852.50, '2024-01-17', '2024-01-19', NULL, 3, 'Sample order 3'],
    ['ORD-004', 1, 4, 'in_progress', 14.50, 1.45, 15.95, 15.95, 15.95, '2024-01-18', '2024-01-19', NULL, 4, 'Sample order 4'],
    ['ORD-005', 2, 5, 'cancelled', 50.00, 5.00, 55.00, 0.00, 0.00, '2024-01-19', '2024-01-24', NULL, 5, 'Sample order 5'],
    ['ORD-006', 3, 3, 'completed', 775.00, 77.50, 852.50, 100.00, 852.50, '2024-01-20', '2024-01-23', '2024-01-23', 3, 'Sample order 6'],
    ['ORD-007', 1, 1, 'draft', 1450.00, 145.00, 1595.00, 200.00, 0.00, '2024-01-21', '2024-01-22', NULL, 1, 'Sample order 7']
];

foreach ($orders as $order) {
    $sql = "INSERT IGNORE INTO rental_orders (order_number, customer_id, vendor_id, status, subtotal, tax_amount, total_amount, security_deposit_total, amount_paid, pickup_date, expected_return_date, actual_return_date, delivery_address_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sisddddddsssis", $order[0], $order[1], $order[2], $order[3], $order[4], $order[5], $order[6], $order[7], $order[8], $order[9], $order[10], $order[11], $order[12], $order[13]);
    if (mysqli_stmt_execute($stmt)) {
        echo "<p style='color: green;'>✓ Order '{$order[0]}' added</p>";
    }
}

echo "<h3 style='color: green;'>✓ Sample data insertion completed!</h3>";
echo "<p><a href='login.php' style='color: #0066cc; text-decoration: none;'>👉 Go to Login</a></p>";
echo "<p><a href='check_db.php' style='color: #0066cc; text-decoration: none;'>👉 Check Database</a></p>";

mysqli_close($conn);
?>
