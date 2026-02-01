<?php
require_once 'config/database.php';
require_once 'config/functions.php';

echo "=== TESTING CART FIX ===\n";

// Start session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'customer';

$db = new Database();
$user_id = $_SESSION['user_id'];

echo "User ID: $user_id\n";

// Get customer ID
$customer_sql = "SELECT id FROM customers WHERE user_id = ?";
$stmt = $db->prepare($customer_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

if (!$customer) {
    echo "ERROR: Customer not found\n";
    exit;
}

$customer_id = $customer['id'];
echo "Customer ID: $customer_id\n";

// Test simple cart query first
echo "\n=== TESTING SIMPLE CART QUERY ===\n";
$simple_cart_sql = "SELECT * FROM cart WHERE customer_id = ?";
$simple_stmt = $db->prepare($simple_cart_sql);
$simple_stmt->bind_param("i", $customer_id);
$simple_stmt->execute();
$simple_cart = $simple_stmt->get_result();

echo "Simple cart rows: " . $simple_cart->num_rows . "\n";

if ($simple_cart->num_rows > 0) {
    while ($row = $simple_cart->fetch_assoc()) {
        echo "Simple cart row: " . print_r($row, true) . "\n";
    }
} else {
    echo "No cart items found\n";
}

// Test the main cart query
echo "\n=== TESTING MAIN CART QUERY ===\n";
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

echo "Main cart rows: " . $cart_items->num_rows . "\n";

if ($cart_items->num_rows > 0) {
    while ($item = $cart_items->fetch_assoc()) {
        echo "Cart item type: " . gettype($item) . "\n";
        echo "Cart item: " . print_r($item, true) . "\n";
        echo "---\n";
    }
} else {
    echo "No cart items found in main query\n";
}

// Test cart data processing
echo "\n=== TESTING CART DATA PROCESSING ===\n";
$cart_data = [];
$subtotal = 0;

if ($cart_items->num_rows > 0) {
    while ($item = $cart_items->fetch_assoc()) {
        echo "Processing item: " . print_r($item, true) . "\n";
        
        if (is_array($item)) {
            $processed_item = [
                'id' => $item['id'] ?? 0,
                'name' => $item['name'] ?? 'Unknown Product',
                'category_name' => $item['category_name'] ?? 'Uncategorized',
                'vendor_name' => $item['vendor_name'] ?? 'Unknown Vendor',
                'quantity' => $item['quantity'] ?? 1,
                'daily_price' => $item['daily_price'] ?? 0,
                'item_total' => ($item['daily_price'] ?? 0) * ($item['quantity'] ?? 1)
            ];
            
            $cart_data[] = $processed_item;
            $subtotal += $processed_item['item_total'];
            
            echo "Processed item: " . print_r($processed_item, true) . "\n";
        } else {
            echo "ERROR: Item is not an array, it's: " . gettype($item) . "\n";
        }
    }
}

echo "Final cart_data count: " . count($cart_data) . "\n";
echo "Subtotal: $subtotal\n";

echo "\n=== TEST COMPLETE ===\n";
?>
