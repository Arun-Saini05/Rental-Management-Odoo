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

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = sanitizeInput($_POST['product_id']);
    $quantity = intval($_POST['quantity'] ?? 1);
    $start_date = sanitizeInput($_POST['start_date']);
    $end_date = sanitizeInput($_POST['end_date']);
    
    // Check if already in cart
    $check_sql = "SELECT id FROM cart WHERE customer_id = ? AND product_id = ?";
    $stmt = $db->prepare($check_sql);
    $stmt->bind_param("ii", $customer_id, $product_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        // Update quantity
        $update_sql = "UPDATE cart SET quantity = quantity + ?, start_date = ?, end_date = ? WHERE customer_id = ? AND product_id = ?";
        $stmt = $db->prepare($update_sql);
        $stmt->bind_param("issii", $quantity, $start_date, $end_date, $customer_id, $product_id);
        $stmt->execute();
    } else {
        // Add to cart
        $insert_sql = "INSERT INTO cart (customer_id, product_id, quantity, start_date, end_date, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $db->prepare($insert_sql);
        $stmt->bind_param("iiiss", $customer_id, $product_id, $quantity, $start_date, $end_date);
        $stmt->execute();
    }
    
    header('Location: cart.php?added=1');
    exit();
}

// Handle update cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $product_id = sanitizeInput($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $start_date = sanitizeInput($_POST['start_date']);
    $end_date = sanitizeInput($_POST['end_date']);
    
    if ($quantity > 0) {
        $update_sql = "UPDATE cart SET quantity = ?, start_date = ?, end_date = ? WHERE customer_id = ? AND product_id = ?";
        $stmt = $db->prepare($update_sql);
        $stmt->bind_param("issii", $quantity, $start_date, $end_date, $customer_id, $product_id);
        $stmt->execute();
    }
    
    header('Location: cart.php?updated=1');
    exit();
}

// Handle remove from cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_cart'])) {
    $product_id = sanitizeInput($_POST['product_id']);
    
    $delete_sql = "DELETE FROM cart WHERE customer_id = ? AND product_id = ?";
    $stmt = $db->prepare($delete_sql);
    $stmt->bind_param("ii", $customer_id, $product_id);
    $stmt->execute();
    
    header('Location: cart.php?removed=1');
    exit();
}

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Rentify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '../includes/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Shopping Cart</h1>
            <p class="text-gray-600">Review your rental selections</p>
        </div>

        <?php if (isset($_GET['added'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                Product added to cart!
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['updated'])): ?>
            <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-edit mr-2"></i>
                Cart updated!
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['removed'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-trash mr-2"></i>
                Product removed from cart!
            </div>
        <?php endif; ?>

        <?php if (!empty($cart_data)): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Cart Items -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Cart Items (<?php echo count($cart_data); ?>)</h2>
                        
                        <div class="space-y-4">
                            <?php foreach ($cart_data as $item): ?>
                                <div class="border rounded-lg p-4 hover:bg-gray-50">
                                    <div class="flex gap-4">
                                        <!-- Product Image -->
                                        <div class="flex-shrink-0">
                                            <?php 
                                            $images = json_decode($item['images'] ?? '[]');
                                            $image_url = !empty($images) ? '../assets/images/' . $images[0] : 'https://picsum.photos/seed/' . $item['id'] . '/100/100.jpg';
                                            ?>
                                            <img src="<?php echo $image_url; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                 class="w-24 h-24 object-cover rounded-lg">
                                        </div>

                                        <!-- Product Details -->
                                        <div class="flex-grow">
                                            <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($item['name']); ?></h3>
                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($item['category_name']); ?></p>
                                            <p class="text-sm text-gray-600">Vendor: <?php echo htmlspecialchars($item['vendor_name']); ?></p>
                                            <p class="text-sm font-medium text-blue-600">₹<?php echo number_format($item['daily_price'], 2); ?>/day</p>
                                            
                                            <!-- Update Form -->
                                            <form method="POST" class="mt-3 grid grid-cols-1 md:grid-cols-4 gap-2">
                                                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                                <input type="hidden" name="update_cart" value="1">
                                                
                                                <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                                       min="1" class="px-2 py-1 border rounded text-sm" placeholder="Qty">
                                                <input type="date" name="start_date" value="<?php echo $item['start_date']; ?>" 
                                                       class="px-2 py-1 border rounded text-sm" required>
                                                <input type="date" name="end_date" value="<?php echo $item['end_date']; ?>" 
                                                       class="px-2 py-1 border rounded text-sm" required>
                                                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                                                    Update
                                                </button>
                                            </form>
                                        </div>

                                        <!-- Price and Actions -->
                                        <div class="flex flex-col items-end justify-between">
                                            <div class="text-right">
                                                <p class="text-sm text-gray-600"><?php echo $item['days']; ?> days × <?php echo $item['quantity']; ?> qty</p>
                                                <p class="font-semibold text-gray-800">₹<?php echo number_format($item['item_total'], 2); ?></p>
                                                <p class="text-sm text-gray-600">Deposit: ₹<?php echo number_format($item['item_deposit'], 2); ?></p>
                                            </div>
                                            
                                            <form method="POST" class="mt-2">
                                                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                                <input type="hidden" name="remove_from_cart" value="1">
                                                <button type="submit" class="text-red-600 hover:text-red-700 text-sm">
                                                    <i class="fas fa-trash mr-1"></i>Remove
                                                </button>
                                            </form>
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
                                    <span class="font-semibold text-gray-800">Total:</span>
                                    <span class="font-bold text-lg text-blue-600">₹<?php echo number_format($total_amount, 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 space-y-3">
                            <a href="checkout.php" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-3 rounded-lg text-center block">
                                <i class="fas fa-credit-card mr-2"></i>Proceed to Checkout
                            </a>
                            <a href="../products.php" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-3 rounded-lg text-center block">
                                <i class="fas fa-arrow-left mr-2"></i>Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Your cart is empty</h3>
                <p class="text-gray-600 mb-6">Add some products to get started!</p>
                <a href="../products.php" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg">
                    <i class="fas fa-search mr-2"></i>Browse Products
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
