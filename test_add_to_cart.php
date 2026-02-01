<?php
require_once 'config/database.php';
require_once 'config/functions.php';

// Simulate customer session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'customer';

$db = new Database();
$user_id = $_SESSION['user_id'];

// Get customer ID
$customer_sql = "SELECT id FROM customers WHERE user_id = ?";
$stmt = $db->prepare($customer_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$customer = $stmt->get_result()->find_assoc();
$customer_id = $customer['id'];

echo "Customer ID: $customer_id\n";

// Clear existing cart
$db->query("DELETE FROM cart WHERE customer_id = $customer_id");

// Add a test product to cart
$product_id = 1; // Assuming product ID 1 exists
$start_date = date('Y-m-d');
$end_date = date('Y-m-d', strtotime('+7 days'));

// Check if product exists and has daily pricing
$product_check = $db->query("SELECT p.*, rp.price as daily_price FROM products p LEFT JOIN rental_pricing rp ON p.id = rp.product_id AND rp.period_type = 'day' WHERE p.id = $product_id");
$product = $product_check->fetch_assoc();

if ($product && $product['daily_price']) {
    // Add to cart
    $insert_sql = "INSERT INTO cart (customer_id, product_id, quantity, start_date, end_date, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $db->prepare($insert_sql);
    $stmt->bind_param("iiiss", $customer_id, $product_id, 1, $start_date, $end_date);
    $stmt->execute();
    
    echo "Added product to cart: {$product['name']} with daily price {$product['daily_price']}\n";
    echo "Start Date: $start_date, End Date: $end_date\n";
} else {
    echo "Product $product_id not found or has no daily pricing\n";
}

// Now test the checkout query
$cart_sql = "SELECT c.*, p.*, cat.name as category_name,
             (SELECT price FROM rental_pricing rp WHERE rp.product_id = p.id AND rp.period_type = 'day' AND rp.is_active = 1 LIMIT 1) as daily_price,
             (SELECT security_deposit FROM rental_pricing rp WHERE rp.product_id = p.id AND rp.period_type = 'day' AND rp.is_active = 1 LIMIT 1) as security_deposit,
             u.name as vendor_name
             FROM cart c
             JOIN products p ON c.product_id = p.id
             LEFT JOIN categories cat ON p.category_id = cat.id
             LEFT JOIN users u ON p.vendor_id = u.id
             WHERE c.customer_id = ?";
$stmt = $db->prepare($cart_sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$cart_items = $stmt->get_result();

echo "\nCart items for checkout: " . $cart_items->num_rows . "\n";

if ($cart_items->num_rows > 0) {
    while ($item = $cart_items->fetch_assoc()) {
        echo "Item found:\n";
        echo "- Name: " . ($item['name'] ?? 'NULL') . "\n";
        echo "- Category: " . ($item['category_name'] ?? 'NULL') . "\n";
        echo "- Vendor: " . ($item['vendor_name'] ?? 'NULL') . "\n";
        echo "- Daily Price: " . ($item['daily_price'] ?? 'NULL') . "\n";
        echo "- Security Deposit: " . ($item['security_deposit'] ?? 'NULL') . "\n";
        echo "- Start Date: " . $item['start_date'] . "\n";
        echo "- End Date: " . $item['end_date'] . "\n";
        echo "- Quantity: " . $item['quantity'] . "\n";
    }
} else {
    echo "No cart items found\n";
}

echo "\nNow visit: /Customer/checkout.php to test\n";
?>
