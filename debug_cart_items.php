<?php
require_once 'config/database.php';
require_once 'config/functions.php';

echo "=== DEBUGGING CART ITEMS ===\n";

// Check if cart table exists and has data
$db = new Database();

echo "1. Checking cart table structure:\n";
$cart_structure = $db->query("DESCRIBE cart");
while ($row = $cart_structure->fetch_assoc()) {
    echo "- {$row['Field']}: {$row['Type']}\n";
}

echo "\n2. All items in cart table:\n";
$all_cart = $db->query("SELECT * FROM cart");
if ($all_cart->num_rows > 0) {
    while ($row = $all_cart->fetch_assoc()) {
        echo "Cart item: " . json_encode($row) . "\n";
    }
} else {
    echo "No items in cart table\n";
}

echo "\n3. Checking customer records:\n";
$customers = $db->query("SELECT id, user_id FROM customers LIMIT 5");
if ($customers->num_rows > 0) {
    while ($row = $customers->fetch_assoc()) {
        echo "Customer ID: {$row['id']}, User ID: {$row['user_id']}\n";
    }
} else {
    echo "No customers found\n";
}

echo "\n4. Testing with customer ID 1:\n";
$customer_id = 1;
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

echo "Cart items for customer $customer_id: " . $cart_items->num_rows . "\n";

if ($cart_items->num_rows > 0) {
    while ($item = $cart_items->fetch_assoc()) {
        echo "Item: " . json_encode($item) . "\n";
        echo "Daily Price: " . ($item['daily_price'] ?? 'NULL') . "\n";
        echo "Product Name: " . ($item['name'] ?? 'NULL') . "\n";
        echo "---\n";
    }
} else {
    echo "No cart items for customer $customer_id\n";
}

echo "\n5. Checking products with daily pricing:\n";
$products_sql = "SELECT p.id, p.name, rp.price as daily_price 
                 FROM products p 
                 LEFT JOIN rental_pricing rp ON p.id = rp.product_id AND rp.period_type = 'day' AND rp.is_active = 1
                 WHERE p.is_published = 1
                 LIMIT 5";
$products = $db->query($products_sql);
while ($row = $products->fetch_assoc()) {
    echo "Product ID: {$row['id']}, Name: {$row['name']}, Daily Price: " . ($row['daily_price'] ?? 'NULL') . "\n";
}
?>
