<?php
require_once 'config/database.php';
require_once 'config/functions.php';

echo "=== STRIPE PAYMENT DEBUG ===\n\n";

// Test the cart data processing
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'customer';
$_SESSION['user_email'] = 'test@example.com';

$db = new Database();
$user_id = $_SESSION['user_id'];

// Get customer ID
$customer_sql = "SELECT id FROM customers WHERE user_id = ?";
$stmt = $db->prepare($customer_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
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

if ($cart_items->num_rows > 0) {
    while ($item = $cart_items->fetch_assoc()) {
        if (isset($item['daily_price']) && $item['daily_price'] > 0) {
            $days = max(1, (strtotime($item['end_date']) - strtotime($item['start_date'])) / (60 * 60 * 24));
            $item_total = $item['daily_price'] * ($item['quantity'] ?? 1) * $days;
            $item_deposit = ($item['security_deposit'] ?? 0) * ($item['quantity'] ?? 1);
            
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
                'days' => $days,
                'item_total' => $item_total,
                'item_deposit' => $item_deposit
            ];
            
            $cart_data[] = $processed_item;
            
            echo "Item: " . ($item['name'] ?? 'Unknown') . " - Total: ₹" . number_format($item_total, 2) . "\n";
        }
    }
}

$tax_amount = $subtotal * 0.18; // 18% GST
$total_amount = $subtotal + $tax_amount;

echo "\n=== CALCULATED TOTALS ===\n";
echo "Subtotal: ₹" . number_format($subtotal, 2) . "\n";
echo "Tax (18%): ₹" . number_format($tax_amount, 2) . "\n";
echo "Total Amount: ₹" . number_format($total_amount, 2) . "\n";
echo "Security Deposit: ₹" . number_format($security_deposit_total, 2) . "\n";

echo "\n=== STRIPE INTEGRATION CHECK ===\n";

// Check if Stripe library exists
if (file_exists('stripe-php-master/init.php')) {
    echo "✅ Stripe library found\n";
} else {
    echo "❌ Stripe library NOT found\n";
}

// Check API keys
$publishablekey = "pk_test_51SvmfbENuKuGRMUgZAN8CWsIBYnjTjmukidRY8Id3xQaW5kQum78U8CNqXZAUf5pFriLDT75Z2QS0MrMLoRRP56H00ngs1baeE";
$key = "sk_test_51SvmfbENuKuGRMUg3EXcL3bq1CGwg6DLhPWMgD3XSX6XnEhydfQ613mqy5DEG0mNnHDYWBe5C0vlVa9ygl9q1WHR004rKMtjz1";

if (strpos($publishablekey, 'pk_test_') === 0) {
    echo "✅ Stripe publishable key format correct\n";
} else {
    echo "❌ Stripe publishable key format issue\n";
}

if (strpos($key, 'sk_test_') === 0) {
    echo "✅ Stripe secret key format correct\n";
} else {
    echo "❌ Stripe secret key format issue\n";
}

echo "\n=== PAYMENT FLOW TEST ===\n";
echo "1. Cart items: " . count($cart_data) . "\n";
echo "2. Total amount: ₹" . number_format($total_amount, 2) . "\n";
echo "3. Stripe button should show: Pay ₹" . number_format($total_amount, 2) . "\n";
echo "4. Payment form should appear with dynamic price\n";

echo "\n=== TROUBLESHOOTING ===\n";
echo "If Stripe is not working:\n";
echo "1. Check browser console for JavaScript errors\n";
echo "2. Verify Stripe library loads correctly\n";
echo "3. Check if cart has items with daily pricing\n";
echo "4. Test with test card: 4242 4242 4242 4242\n";
echo "5. Check network tab for failed requests\n";

echo "\n✅ STRIPE DEBUG COMPLETE\n";
?>
