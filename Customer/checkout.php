<?php
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

if (!isCustomer()) {
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$user_id = $_SESSION['user_id'];

// Get customer ID
$customer_sql = "SELECT id FROM customers WHERE user_id = ?";
$stmt = $db->prepare($customer_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$customer_id = $customer['id'];

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

if ($cart_items->num_rows === 0) {
    header('Location: cart.php');
    exit();
}

// Calculate totals
$subtotal = 0;
$security_deposit_total = 0;
$cart_data = [];

while ($item = $cart_items->fetch_assoc()) {
    if ($item['daily_price']) {
        $days = max(1, (strtotime($item['end_date']) - strtotime($item['start_date'])) / (60 * 60 * 24));
        $item_total = $item['daily_price'] * $item['quantity'] * $days;
        $item_deposit = ($item['security_deposit'] ?? 0) * $item['quantity'];
        
        $subtotal += $item_total;
        $security_deposit_total += $item_deposit;
        
        $cart_data[] = array_merge($item, [
            'days' => $days,
            'item_total' => $item_total,
            'item_deposit' => $item_deposit
        ]);
    }
}

$tax_amount = $subtotal * 0.18; // 18% GST
$total_amount = $subtotal + $tax_amount;

// Handle order placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $pickup_date = sanitizeInput($_POST['pickup_date']);
    $delivery_address = sanitizeInput($_POST['delivery_address']);
    $notes = sanitizeInput($_POST['notes']);
    
    // Generate order number
    $order_number = 'ORD-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Create order
    $order_sql = "INSERT INTO rental_orders (order_number, customer_id, vendor_id, status, 
                  subtotal, tax_amount, total_amount, security_deposit_total, pickup_date, 
                  expected_return_date, delivery_address, notes, created_at) 
                  VALUES (?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    // Group cart items by vendor
    $vendor_orders = [];
    foreach ($cart_data as $item) {
        $vendor_id = $item['vendor_id'];
        if (!isset($vendor_orders[$vendor_id])) {
            $vendor_orders[$vendor_id] = [
                'items' => [],
                'subtotal' => 0,
                'tax_amount' => 0,
                'total_amount' => 0,
                'security_deposit_total' => 0
            ];
        }
        $vendor_orders[$vendor_id]['items'][] = $item;
        $vendor_orders[$vendor_id]['subtotal'] += $item['item_total'];
        $vendor_orders[$vendor_id]['security_deposit_total'] += $item['item_deposit'];
    }
    
    // Calculate tax and total for each vendor
    foreach ($vendor_orders as $vendor_id => &$order) {
        $order['tax_amount'] = $order['subtotal'] * 0.18;
        $order['total_amount'] = $order['subtotal'] + $order['tax_amount'];
    }
    
    // Create orders for each vendor
    foreach ($vendor_orders as $vendor_id => $order_data) {
        $stmt = $db->prepare($order_sql);
        $stmt->bind_param("siidddssssss", $order_number, $customer_id, $vendor_id,
                          $order_data['subtotal'], $order_data['tax_amount'], $order_data['total_amount'],
                          $order_data['security_deposit_total'], $pickup_date, 
                          $_POST['return_date'], $delivery_address, $notes);
        $stmt->execute();
        
        $order_id = $db->getLastId();
        
        // Add order lines
        foreach ($order_data['items'] as $item) {
            $line_sql = "INSERT INTO rental_order_lines (order_id, product_id, quantity, 
                        unit_price, rental_days, line_total, security_deposit) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
            $line_stmt = $db->prepare($line_sql);
            $line_stmt->bind_param("iididdd", $order_id, $item['id'], $item['quantity'],
                                  $item['daily_price'], $item['days'], $item['item_total'], $item['item_deposit']);
            $line_stmt->execute();
        }
    }
    
    // Clear cart
    $clear_sql = "DELETE FROM cart WHERE customer_id = ?";
    $stmt = $db->prepare($clear_sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    
    header('Location: orders.php?order_placed=1');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Rentify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '../includes/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Checkout</h1>
            <p class="text-gray-600">Review your order and complete your rental</p>
        </div>

        <form method="POST" id="checkoutForm">
            <input type="hidden" name="place_order" value="1">
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Order Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Delivery Information -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Delivery Information</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Pickup Date *</label>
                                <input type="date" name="pickup_date" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                       min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Expected Return Date *</label>
                                <input type="date" name="return_date" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Delivery Address *</label>
                            <textarea name="delivery_address" rows="3" required
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                      placeholder="Enter your complete delivery address"></textarea>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Order Notes (Optional)</label>
                            <textarea name="notes" rows="2"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                      placeholder="Any special instructions or requirements"></textarea>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Order Items</h2>
                        
                        <div class="space-y-4">
                            <?php foreach ($cart_data as $item): ?>
                                <div class="border rounded-lg p-4">
                                    <div class="flex gap-4">
                                        <!-- Product Image -->
                                        <div class="flex-shrink-0">
                                            <?php 
                                            $images = json_decode($item['images'] ?? '[]');
                                            $image_url = !empty($images) ? '../assets/images/' . $images[0] : 'https://picsum.photos/seed/' . $item['id'] . '/80/80.jpg';
                                            ?>
                                            <img src="<?php echo $image_url; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                 class="w-20 h-20 object-cover rounded-lg">
                                        </div>

                                        <!-- Product Details -->
                                        <div class="flex-grow">
                                            <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($item['name']); ?></h3>
                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($item['category_name']); ?></p>
                                            <p class="text-sm text-gray-600">Vendor: <?php echo htmlspecialchars($item['vendor_name']); ?></p>
                                            <p class="text-sm text-gray-600">
                                                <?php echo $item['quantity']; ?> × <?php echo $item['days']; ?> days @ ₹<?php echo number_format($item['daily_price'], 2); ?>/day
                                            </p>
                                        </div>

                                        <!-- Price -->
                                        <div class="text-right">
                                            <p class="font-semibold text-gray-800">₹<?php echo number_format($item['item_total'], 2); ?></p>
                                            <p class="text-sm text-gray-600">Deposit: ₹<?php echo number_format($item['item_deposit'], 2); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-md p-6 sticky top-4">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Order Summary</h2>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Subtotal:</span>
                                <span class="font-medium">₹<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Tax (18% GST):</span>
                                <span class="font-medium">₹<?php echo number_format($tax_amount, 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Security Deposit:</span>
                                <span class="font-medium">₹<?php echo number_format($security_deposit_total, 2); ?></span>
                            </div>
                            <div class="border-t pt-3">
                                <div class="flex justify-between">
                                    <span class="font-semibold text-gray-800">Total Amount:</span>
                                    <span class="font-bold text-lg text-blue-600">₹<?php echo number_format($total_amount, 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-3 rounded-lg">
                                <i class="fas fa-check mr-2"></i>Place Order
                            </button>
                            <a href="cart.php" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-3 rounded-lg text-center block mt-3">
                                <i class="fas fa-arrow-left mr-2"></i>Back to Cart
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Set minimum dates
        document.addEventListener('DOMContentLoaded', function() {
            const pickupDate = document.querySelector('input[name="pickup_date"]');
            const returnDate = document.querySelector('input[name="return_date"]');
            
            pickupDate.addEventListener('change', function() {
                returnDate.min = new Date(this.value).toISOString().split('T')[0];
            });
        });
    </script>
</body>
</html>
