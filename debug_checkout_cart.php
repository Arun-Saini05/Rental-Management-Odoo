<?php
require_once 'config/database.php';
require_once 'config/functions.php';

echo "=== DEBUGGING CHECKOUT CART DATA ===\n";

// Start session
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
$customer = $stmt->get_result()->fetch_assoc();

if (!$customer) {
    echo "ERROR: Customer not found\n";
    exit;
}

$customer_id = $customer['id'];
echo "Customer ID: $customer_id\n";

// Get cart items
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

echo "Cart items found: " . $cart_items->num_rows . "\n";

// Calculate totals
$subtotal = 0;
$security_deposit_total = 0;
$cart_data = [];

// Process each cart item
while ($item = $cart_items->fetch_assoc()) {
    echo "Raw cart item: " . print_r($item, true) . "\n";
    echo "Item type: " . gettype($item) . "\n";
    
    // Ensure we have an array
    if (!is_array($item)) {
        echo "ERROR: Item is not an array, skipping\n";
        continue;
    }
    
    if ($item['daily_price']) {
        $days = max(1, (strtotime($item['end_date']) - strtotime($item['start_date'])) / (60 * 60 * 24));
        $item_total = $item['daily_price'] * $item['quantity'] * $days;
        $item_deposit = ($item['security_deposit'] ?? 0) * $item['quantity'];
        
        $subtotal += $item_total;
        $security_deposit_total += $item_deposit;
        
        $processed_item = [
            'id' => $item['id'] ?? 0,
            'name' => $item['name'] ?? 'Unknown Product',
            'category_name' => $item['category_name'] ?? 'Uncategorized',
            'vendor_name' => $item['vendor_name'] ?? 'Unknown Vendor',
            'images' => $item['images'] ?? '[]',
            'quantity' => $item['quantity'] ?? 1,
            'daily_price' => $item['daily_price'] ?? 0,
            'security_deposit' => $item['security_deposit'] ?? 0,
            'start_date' => $item['start_date'] ?? '',
            'end_date' => $item['end_date'] ?? '',
            'days' => $days,
            'item_total' => $item_total,
            'item_deposit' => $item_deposit
        ];
        
        $cart_data[] = $processed_item;
        echo "Processed item: " . print_r($processed_item, true) . "\n";
    }
}

echo "\nFinal cart_data count: " . count($cart_data) . "\n";
echo "Final cart_data: " . print_r($cart_data, true) . "\n";
echo "Subtotal: $subtotal\n";
echo "Security Deposit: $security_deposit_total\n";

$tax_amount = $subtotal * 0.18;
$total_amount = $subtotal + $tax_amount;

echo "Tax Amount: $tax_amount\n";
echo "Total Amount: $total_amount\n";

echo "\n=== SIMULATED CHECKOUT DISPLAY ===\n";
if (!empty($cart_data)) {
    foreach ($cart_data as $index => $item) {
        echo "=== ITEM $index ===\n";
        echo "Name: " . ($item['name'] ?? 'Unknown') . "\n";
        echo "Category: " . ($item['category_name'] ?? 'Uncategorized') . "\n";
        echo "Vendor: " . ($item['vendor_name'] ?? 'Unknown') . "\n";
        echo "Quantity: " . ($item['quantity'] ?? 1) . "\n";
        echo "Days: " . ($item['days'] ?? 1) . "\n";
        echo "Daily Price: ₹" . number_format($item['daily_price'] ?? 0, 2) . "\n";
        echo "Item Total: ₹" . number_format($item['item_total'] ?? 0, 2) . "\n";
        echo "Deposit: ₹" . number_format($item['item_deposit'] ?? 0, 2) . "\n";
        echo "Dates: " . ($item['start_date'] ?? 'N/A') . " - " . ($item['end_date'] ?? 'N/A') . "\n";
        echo "Images: " . ($item['images'] ?? '[]') . "\n";
        echo "---\n";
    }
} else {
    echo "No cart items to display\n";
}

echo "\n=== DEBUG COMPLETE ===\n";
?>
