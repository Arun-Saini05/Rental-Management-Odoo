<?php
require_once 'config/database.php';
require_once 'config/functions.php';

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

// Create mock cart data for testing
$cart_data = [
    [
        'id' => 1,
        'name' => 'Test Product 1',
        'category_name' => 'Test Category',
        'vendor_name' => 'Test Vendor',
        'images' => '["test1.jpg"]',
        'quantity' => 2,
        'daily_price' => 100.00,
        'security_deposit' => 500.00,
        'days' => 7,
        'item_total' => 1400.00,
        'item_deposit' => 1000.00
    ],
    [
        'id' => 2,
        'name' => 'Test Product 2',
        'category_name' => 'Another Category',
        'vendor_name' => 'Another Vendor',
        'images' => '["test2.jpg"]',
        'quantity' => 1,
        'daily_price' => 150.00,
        'security_deposit' => 300.00,
        'days' => 5,
        'item_total' => 750.00,
        'item_deposit' => 300.00
    ]
];

// Calculate totals
$subtotal = 0;
$security_deposit_total = 0;
foreach ($cart_data as $item) {
    $subtotal += $item['item_total'];
    $security_deposit_total += $item['item_deposit'];
}

$tax_amount = $subtotal * 0.18; // 18% GST
$total_amount = $subtotal + $tax_amount;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Checkout - Rentify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '../includes/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Test Checkout Page</h1>
        
        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Order Items -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Order Items (Mock Data)</h2>
                    
                    <div class="space-y-4">
                        <?php foreach ($cart_data as $item): ?>
                            <div class="border rounded-lg p-4">
                                <div class="flex gap-4">
                                    <!-- Product Image -->
                                    <div class="flex-shrink-0">
                                        <?php 
                                        $images = json_decode($item['images'], true);
                                        $image_url = !empty($images) ? '../assets/products/' . $images[0] : 'https://picsum.photos/seed/' . $item['id'] . '/80/80.jpg';
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
                        <button class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg">
                            <i class="fas fa-check mr-2"></i>Test Order
                        </button>
                        <a href="cart.php" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-3 rounded-lg text-center block">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Cart
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
