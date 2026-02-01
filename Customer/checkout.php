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

$publishablekey = "pk_test_51SvmfbENuKuGRMUgZAN8CWsIBYnjTjmukidRY8Id3xQaW5kQum78U8CNqXZAUf5pFriLDT75Z2QS0MrMLoRRP56H00ngs1baeE";

// Get customer ID
$customer_sql = "SELECT id FROM customers WHERE user_id = ?";
$stmt = $db->prepare($customer_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$customer_id = $customer['id'];

// Get cart items - simplified query to test
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

// Debug: Check if we got any results
error_log("Cart query executed, rows found: " . $cart_items->num_rows);

if ($cart_items->num_rows === 0) {
    error_log("No cart items found for customer ID: $customer_id");
    // Try a simpler query to see what's in cart
    $simple_cart_sql = "SELECT * FROM cart WHERE customer_id = ?";
    $simple_stmt = $db->prepare($simple_cart_sql);
    $simple_stmt->bind_param("i", $customer_id);
    $simple_stmt->execute();
    $simple_cart = $simple_stmt->get_result();
    
    error_log("Simple cart query rows: " . $simple_cart->num_rows);
    if ($simple_cart->num_rows > 0) {
        while ($row = $simple_cart->fetch_assoc()) {
            error_log("Simple cart row: " . print_r($row, true));
        }
    }
    
    header('Location: cart.php');
    exit();
}

// Calculate totals
$subtotal = 0;
$security_deposit_total = 0;
$cart_data = [];

// Debug: Check if we have cart items
if ($cart_items->num_rows === 0) {
    error_log("No cart items found for customer ID: $customer_id");
} else {
    error_log("Found " . $cart_items->num_rows . " cart items");
}

// Process each cart item
while ($item = $cart_items->fetch_assoc()) {
    // Debug: Check what we actually have
    error_log("Raw cart item: " . print_r($item, true));
    error_log("Item type: " . gettype($item));
    
    // Ensure we have an array
    if (!is_array($item)) {
        error_log("ERROR: Item is not an array, skipping");
        continue;
    }
    
    // Check if daily_price exists and is valid
    if (isset($item['daily_price']) && $item['daily_price'] > 0) {
        $days = max(1, (strtotime($item['end_date']) - strtotime($item['start_date'])) / (60 * 60 * 24));
        $item_total = $item['daily_price'] * $item['quantity'] * $days;
        $item_deposit = ($item['security_deposit'] ?? 0) * $item['quantity'];
        
        $subtotal += $item_total;
        $security_deposit_total += $item_deposit;
        
        // Ensure we have all required fields with defaults
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
        error_log("Processed cart item: " . print_r($processed_item, true));
    } else {
        error_log("Skipping item - no daily_price: " . print_r($item, true));
        
        // Even if no daily_price, still add the item with zero price for debugging
        $days = max(1, (strtotime($item['end_date']) - strtotime($item['start_date'])) / (60 * 60 * 24));
        $processed_item = [
            'id' => $item['id'] ?? 0,
            'name' => $item['name'] ?? 'Unknown Product',
            'category_name' => $item['category_name'] ?? 'Uncategorized',
            'vendor_name' => $item['vendor_name'] ?? 'Unknown Vendor',
            'images' => $item['images'] ?? '[]',
            'quantity' => $item['quantity'] ?? 1,
            'daily_price' => 0,
            'security_deposit' => 0,
            'start_date' => $item['start_date'] ?? '',
            'end_date' => $item['end_date'] ?? '',
            'days' => $days,
            'item_total' => 0,
            'item_deposit' => 0
        ];
        
        $cart_data[] = $processed_item;
        error_log("Added zero-price item: " . print_r($processed_item, true));
    }
}

// Force cart_data to be an array of arrays if it's empty or wrong
if (empty($cart_data) || !is_array($cart_data)) {
    error_log("Cart data is empty or invalid, keeping cart empty");
    $cart_data = [];
    $subtotal = 0;
    $security_deposit_total = 0;
}

error_log("Final cart_data count: " . count($cart_data));
error_log("Final cart_data: " . print_r($cart_data, true));
error_log("Subtotal: $subtotal, Security Deposit: $security_deposit_total");

$tax_amount = $subtotal * 0.18; // 18% GST
$total_amount = $subtotal + $tax_amount;

// Handle order placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $payment_method = $_POST['place_order'];
    
    if ($payment_method === 'stripe') {
        // Handle Stripe payment
        try {
            $payment_method_id = $_POST['payment_method_id'] ?? '';
            $pickup_date = sanitizeInput($_POST['pickup_date']);
            $return_date = sanitizeInput($_POST['return_date']);
            $notes = sanitizeInput($_POST['notes']);
            
            if (empty($payment_method_id)) {
                throw new Exception('Payment method ID is required');
            }
            
            // Create order with Stripe payment
            $order_number = 'ORD-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $order_sql = "INSERT INTO rental_orders (order_number, customer_id, vendor_id, status, 
                          subtotal, tax_amount, total_amount, security_deposit_total, pickup_date, 
                          expected_return_date, notes, stripe_payment_method_id, created_at) 
                          VALUES (?, ?, ?, 'confirmed', ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            // Group cart items by vendor
            $vendor_orders = [];
            foreach ($cart_data as $item) {
                $vendor_id = $item['vendor_id'] ?? 1;
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
                                  $return_date, $notes, $payment_method_id);
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
            unset($_SESSION['cart']);
            
            header('Location: order-confirmation.php?order_id=' . $order_id);
            exit();
            
        } catch (Exception $e) {
            echo json_encode([
                'error' => true,
                'message' => $e->getMessage()
            ]);
            exit();
        }
    } elseif ($payment_method === 'cod') {
        // Handle Cash on Delivery
        $pickup_date = sanitizeInput($_POST['pickup_date']);
        $return_date = sanitizeInput($_POST['return_date']);
        $notes = sanitizeInput($_POST['notes']);
        
        // Create order with COD status
        $order_number = 'ORD-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $order_sql = "INSERT INTO rental_orders (order_number, customer_id, vendor_id, status, 
                      subtotal, tax_amount, total_amount, security_deposit_total, pickup_date, 
                      expected_return_date, notes, payment_method, created_at) 
                      VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, 'cod', NOW())";
        
        // Group cart items by vendor (same logic as above)
        $vendor_orders = [];
        foreach ($cart_data as $item) {
            $vendor_id = $item['vendor_id'] ?? 1;
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
                              $return_date, $notes);
            $stmt->execute();
            
            $order_id = $db->getLastId();
            
            // Add order lines (same logic as above)
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
        unset($_SESSION['cart']);
        
        header('Location: order-confirmation.php?order_id=' . $order_id);
        exit();
    }
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
                            <?php 
                            // Debug information
                            error_log("Displaying cart items - count: " . count($cart_data));
                            error_log("Cart data type: " . gettype($cart_data));
                            
                            if (!empty($cart_data)): ?>
                                <?php foreach ($cart_data as $index => $item): ?>
                                    <?php 
                                    error_log("Processing item $index: " . print_r($item, true));
                                    error_log("Item type: " . gettype($item));
                                    
                                    if (is_array($item)): ?>
                                        <div class="border rounded-lg p-4 hover:bg-gray-50">
                                            <div class="flex gap-4">
                                                <!-- Product Image -->
                                                <div class="flex-shrink-0">
                                                    <?php 
                                                    $images = json_decode($item['images'] ?? '[]', true);
                                                    
                                                    // Try different path combinations to find the actual file
                                                    $image_url = 'https://picsum.photos/seed/' . ($item['id'] ?? 1) . '/80/80.jpg';
                                                    if (!empty($images)) {
                                                        $first_image = $images[0];
                                                        $possible_paths = [
                                                            '../assets/products/' . $first_image,
                                                            'assets/products/' . $first_image,
                                                            '../assets/images/' . $first_image,
                                                            'assets/images/' . $first_image,
                                                            $first_image
                                                        ];
                                                        
                                                        foreach ($possible_paths as $path) {
                                                            if (file_exists($path)) {
                                                                $image_url = $path;
                                                                break;
                                                            }
                                                        }
                                                    }
                                                    
                                                    echo '<img src="' . $image_url . '" alt="' . htmlspecialchars($item['name'] ?? 'Product') . '" class="w-20 h-20 object-cover rounded-lg">';
                                                    ?>
                                                </div>

                                                <!-- Product Details -->
                                                <div class="flex-grow">
                                                    <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($item['name'] ?? 'Unknown Product'); ?></h3>
                                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></p>
                                                    <p class="text-sm text-gray-600">Vendor: <?php echo htmlspecialchars($item['vendor_name'] ?? 'Unknown Vendor'); ?></p>
                                                    
                                                    <!-- Rental Details -->
                                                    <div class="mt-2 space-y-1">
                                                        <p class="text-sm text-gray-600">
                                                            <i class="fas fa-tag mr-1"></i>
                                                            <?php echo $item['quantity'] ?? 1; ?> × <?php echo $item['days'] ?? 1; ?> days @ ₹<?php echo number_format($item['daily_price'] ?? 0, 2); ?>/day
                                                        </p>
                                                        <p class="text-sm text-gray-600">
                                                            <i class="fas fa-calendar-alt mr-1"></i>
                                                            <?php echo date('M j, Y', strtotime($item['start_date'] ?? 'now')); ?> - <?php echo date('M j, Y', strtotime($item['end_date'] ?? 'now')); ?>
                                                        </p>
                                                    </div>
                                                </div>

                                                <!-- Price -->
                                                <div class="text-right">
                                                    <p class="font-semibold text-gray-800">₹<?php echo number_format($item['item_total'] ?? 0, 2); ?></p>
                                                    <p class="text-sm text-gray-600">Deposit: ₹<?php echo number_format($item['item_deposit'] ?? 0, 2); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="border rounded-lg p-4 bg-red-50">
                                            <p class="text-red-600">Error: Invalid cart item data (type: <?php echo gettype($item); ?>)</p>
                                            <p class="text-red-500 text-sm">Value: <?php echo htmlspecialchars(print_r($item, true)); ?></p>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="border rounded-lg p-8 text-center">
                                    <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
                                    <h3 class="text-xl font-semibold text-gray-800 mb-2">No items in cart</h3>
                                    <p class="text-gray-600 mb-4">Your cart is empty. Add some products to continue with checkout.</p>
                                    <a href="../products.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                                        <i class="fas fa-shopping-bag mr-2"></i>Browse Products
                                    </a>
                                </div>
                            <?php endif; ?>
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
                            <!-- Payment Method Selection -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                                <div class="space-y-2">
                                    <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="radio" name="payment_method" value="stripe" checked class="mr-3">
                                        <i class="fab fa-stripe text-blue-600 mr-2"></i>
                                        <span class="ml-2 text-gray-700">Stripe Payment</span>
                                    </label>
                                    <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="radio" name="payment_method" value="cod" class="mr-3">
                                        <i class="fas fa-money-bill-wave text-gray-600"></i>
                                        <span class="ml-2 text-gray-700">Cash on Delivery</span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Stripe Button Container -->
                            <div id="stripe-button-container" class="mb-3">
                                <button type="button" onclick="showStripePayment()" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg">
                                    <i class="fab fa-stripe mr-2"></i>Pay with Stripe
                                </button>
                            </div>
                            
                            <!-- Debug Info -->
                            <div id="stripe-debug" class="bg-yellow-50 p-2 rounded text-sm text-yellow-800 mb-2">
                                <strong>Debug Info:</strong>
                                <div id="stripe-debug-content"></div>
                            </div>
                            
                            <!-- COD Button -->
                            <div id="cod-button-container" class="mb-3 hidden">
                                <button type="submit" name="place_order" value="cod" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-lg">
                                    <i class="fas fa-money-bill-wave mr-2"></i>Cash on Delivery
                                </button>
                            </div>
                            
                            <a href="cart.php" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-3 rounded-lg text-center block">
                                <i class="fas fa-arrow-left mr-2"></i>Back to Cart
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Stripe Payment Section -->
    <div id="payment-section" class="hidden">
        <div class="container mx-auto px-4 py-8">
            <div class="max-w-2xl mx-auto">
                <div class="bg-white rounded-lg shadow-lg p-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Payment Information</h2>
                    
                    <!-- Stripe Payment Form -->
                    <form id="payment-form" class="space-y-6">
                        <!-- Cardholder Name -->
                        <div>
                            <label for="cardholder-name" class="block text-sm font-medium text-gray-700 mb-2">
                                Cardholder Name
                            </label>
                            <input type="text" id="cardholder-name" name="cardholder-name" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="John Doe">
                        </div>
                        
                        <!-- Stripe Card Element -->
                        <div>
                            <label for="card-element" class="block text-sm font-medium text-gray-700 mb-2">
                                Card Information
                            </label>
                            <div id="card-element" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <!-- Stripe Elements will create the card input here -->
                            </div>
                            <div id="card-errors" role="alert" class="mt-2 text-sm text-red-600"></div>
                        </div>
                        
                        <!-- Order Summary -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="font-semibold text-gray-800 mb-3">Order Summary</h3>
                            <!-- Cart Items -->
                            <div class="space-y-4 mb-6">
                                <?php if (!empty($cart_data)): ?>
                                    <?php foreach ($cart_data as $item): ?>
                                        <div class="flex space-x-4">
                                            <div class="w-16 h-16 bg-gray-200 rounded flex items-center justify-center">
                                                <?php 
                                                $images = json_decode($item['images'] ?? '[]', true);
                                                $image_url = !empty($images) ? '../assets/products/' . $images[0] : 'https://picsum.photos/seed/' . $item['id'] . '/64/64.jpg';
                                                ?>
                                                <img src="<?php echo $image_url; ?>" alt="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>" 
                                                     class="w-full h-full object-cover rounded">
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="font-medium"><?php echo htmlspecialchars($item['name'] ?? 'Unknown Product'); ?></h4>
                                                <p class="text-sm text-gray-600">
                                                    <?php echo date('M j, Y', strtotime($item['start_date'] ?? 'now')); ?> - <?php echo date('M j, Y', strtotime($item['end_date'] ?? 'now')); ?>
                                                </p>
                                                <p class="text-sm text-gray-600">Qty: <?php echo $item['quantity'] ?? 1; ?> × <?php echo $item['days'] ?? 1; ?> days</p>
                                                <p class="text-sm text-gray-600">Vendor: <?php echo htmlspecialchars($item['vendor_name'] ?? 'Unknown Vendor'); ?></p>
                                            </div>
                                            <div class="text-right">
                                                <div class="font-medium">₹<?php echo number_format($item['item_total'] ?? 0, 2); ?></div>
                                                <div class="text-xs text-gray-500">Deposit: ₹<?php echo number_format($item['item_deposit'] ?? 0, 2); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <p class="text-gray-500">No items in cart</p>
                                        <a href="products.php" class="text-blue-600 hover:text-blue-700 text-sm">Browse Products</a>
                                    </div>
                                <?php endif; ?>
                            </div>    
                            <!-- Security Notice -->
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-lock text-blue-600 mr-3"></i>
                                    <div class="text-sm text-blue-800">
                                        <p class="font-semibold">Secure Payment</p>
                                        <p>Your payment information is encrypted and secure. We never store your card details.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <button type="submit" id="submit-button" 
                                class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 font-semibold transition-colors">
                            Pay ₹<?php echo number_format($total_amount, 2); ?>
                        </button>
                        
                        <!-- Back Button -->
                        <button type="button" onclick="document.getElementById('payment-section').classList.add('hidden')"
                                class="w-full bg-gray-200 text-gray-700 py-3 rounded-lg hover:bg-gray-300 font-semibold transition-colors">
                            Back to Order Details
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://js.stripe.com/v3/"></script>
    <script>
        // Payment method switching
        document.addEventListener('change', function(e) {
            const stripeContainer = document.getElementById('stripe-button-container');
            const codContainer = document.getElementById('cod-button-container');
            
            if (e.target.name === 'payment_method') {
                if (e.target.value === 'stripe') {
                    stripeContainer.classList.remove('hidden');
                    codContainer.classList.add('hidden');
                } else if (e.target.value === 'cod') {
                    stripeContainer.classList.add('hidden');
                    codContainer.classList.remove('hidden');
                }
            }
        });

        const stripe = Stripe('<?php echo $publishablekey; ?>');
        let elements = null;
        let cardElement = null;

        // Show Stripe payment form
        function showStripePayment() {
            const paymentSection = document.getElementById('payment-section');
            if (paymentSection) {
                paymentSection.classList.remove('hidden');
                paymentSection.scrollIntoView({ behavior: 'smooth' });
            }

            if (!cardElement) {
                elements = stripe.elements();
                cardElement = elements.create('card', {
                    style: {
                        base: {
                            fontSize: '16px',
                            color: '#424770',
                            '::placeholder': {
                                color: '#aab7c4',
                            },
                        },
                    },
                });

                cardElement.mount('#card-element');

                cardElement.on('change', ({error}) => {
                    const displayError = document.getElementById('card-errors');
                    if (displayError) {
                        displayError.textContent = error ? error.message : '';
                    }
                });
            }
        }

        // Handle form submission
        const paymentForm = document.getElementById('payment-form');
        if (paymentForm) {
            paymentForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                const submitButton = document.getElementById('submit-button');
                const originalText = submitButton ? submitButton.textContent : '';
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Processing...';
                }

                const pickupDateEl = document.querySelector('input[name="pickup_date"]');
                const returnDateEl = document.querySelector('input[name="return_date"]');
                const notesEl = document.querySelector('textarea[name="notes"]');

                const payload = {
                    action: 'create_payment_intent',
                    pickup_date: pickupDateEl ? pickupDateEl.value : '',
                    return_date: returnDateEl ? returnDateEl.value : '',
                    notes: notesEl ? notesEl.value : ''
                };

                let createResult;
                try {
                    const res = await fetch('/Rental-Odoo/stripe_payment.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    createResult = await res.json();
                } catch (e) {
                    createResult = { success: false, message: 'Network error' };
                }

                if (!createResult || !createResult.success || !createResult.client_secret) {
                    const errEl = document.getElementById('card-errors');
                    if (errEl) errEl.textContent = (createResult && createResult.message) ? createResult.message : 'Unable to start payment.';
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalText;
                    }
                    return;
                }

                const billingName = (document.getElementById('cardholder-name') || {}).value || '';

                const { error, paymentIntent } = await stripe.confirmCardPayment(createResult.client_secret, {
                    payment_method: {
                        card: cardElement,
                        billing_details: {
                            name: billingName
                        }
                    }
                });

                if (error) {
                    const errEl = document.getElementById('card-errors');
                    if (errEl) errEl.textContent = error.message;
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalText;
                    }
                    return;
                }

                if (!paymentIntent || paymentIntent.status !== 'succeeded') {
                    const errEl = document.getElementById('card-errors');
                    if (errEl) errEl.textContent = 'Payment not completed.';
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalText;
                    }
                    return;
                }

                let confirmResult;
                try {
                    const res = await fetch('/Rental-Odoo/stripe_payment.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'confirm_payment',
                            payment_intent_id: paymentIntent.id,
                            pickup_date: payload.pickup_date,
                            return_date: payload.return_date,
                            notes: payload.notes
                        })
                    });
                    confirmResult = await res.json();
                } catch (e) {
                    confirmResult = { success: false, message: 'Network error' };
                }

                if (confirmResult && confirmResult.success && confirmResult.order_id) {
                    window.location.href = 'order-confirmation.php?order_id=' + confirmResult.order_id;
                    return;
                }

                const errEl = document.getElementById('card-errors');
                if (errEl) errEl.textContent = (confirmResult && confirmResult.message) ? confirmResult.message : 'Order creation failed.';
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }
            });
        }

        // Set minimum dates
        document.addEventListener('DOMContentLoaded', function() {
            const pickupDate = document.querySelector('input[name="pickup_date"]');
            const returnDate = document.querySelector('input[name="return_date"]');
            
            if (pickupDate && returnDate) {
                pickupDate.addEventListener('change', function() {
                    returnDate.min = new Date(this.value).toISOString().split('T')[0];
                });
            }
        });
    </script>
</body>
</html>
