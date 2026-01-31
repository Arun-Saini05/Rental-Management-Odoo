<?php
require_once 'config/database.php';
require_once 'config/functions.php';

requireLogin();

$db = new Database();

// Get cart items from session
$cart_items = $_SESSION['cart'] ?? [];

if (empty($cart_items)) {
    header('Location: products.php');
    exit();
}

// Calculate totals
$subtotal = 0;
$delivery_charges = 10;
$tax_rate = 0.18;

foreach ($cart_items as $item) {
    $subtotal += $item['total_price'];
}

$tax_amount = $subtotal * $tax_rate;
$total_amount = $subtotal + $tax_amount + $delivery_charges;
$security_deposit = $total_amount * 0.20; // 20% security deposit

// Get user addresses
$addresses_sql = "SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC";
$addresses_stmt = $db->prepare($addresses_sql);
$addresses_stmt->bind_param("i", $_SESSION['user_id']);
$addresses_stmt->execute();
$addresses = $addresses_stmt->get_result();
$default_address = $addresses->fetch_assoc();

// Create quotation if not exists
if (!isset($_SESSION['quotation_id'])) {
    $quotation_no = generateQuotationNo();
    $sql = "INSERT INTO quotations (quotation_no, customer_id, status, subtotal, tax_amount, total_amount) 
            VALUES (?, ?, 'Draft', ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("siddd", $quotation_no, $_SESSION['user_id'], $subtotal, $tax_amount, $total_amount);
    $stmt->execute();
    $_SESSION['quotation_id'] = $db->getLastId();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Rental Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-blue-600">Rentify</h1>
                </div>

                <!-- Navigation -->
                <nav class="hidden md:flex space-x-8">
                    <a href="index.php" class="text-gray-700 hover:text-blue-600">Home</a>
                    <a href="products.php" class="text-gray-700 hover:text-blue-600">Products</a>
                    <a href="#" class="text-gray-700 hover:text-blue-600">Terms & Condition</a>
                    <a href="#" class="text-gray-700 hover:text-blue-600">About us</a>
                    <a href="#" class="text-gray-700 hover:text-blue-600">Contact Us</a>
                </nav>

                <!-- Search and User Actions -->
                <div class="flex items-center space-x-4">
                    <!-- Search Bar -->
                    <div class="relative hidden md:block">
                        <input type="text" placeholder="Search products..." class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>

                    <!-- Icons -->
                    <button class="text-gray-700 hover:text-blue-600">
                        <i class="fas fa-heart text-xl"></i>
                    </button>
                    <button class="text-gray-700 hover:text-blue-600 relative">
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs"><?php echo count($cart_items); ?></span>
                    </button>

                    <!-- User Menu -->
                    <div class="relative">
                        <button class="flex items-center space-x-2 text-gray-700 hover:text-blue-600">
                            <i class="fas fa-user-circle text-xl"></i>
                            <span class="hidden md:block"><?php echo $_SESSION['user_name']; ?></span>
                        </button>
                    </div>
                    <a href="auth/logout.php" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Breadcrumb -->
    <div class="bg-white border-b">
        <div class="container mx-auto px-4 py-3">
            <nav class="flex text-sm">
                <a href="index.php" class="text-gray-600 hover:text-blue-600">Home</a>
                <span class="mx-2 text-gray-400">/</span>
                <a href="products.php" class="text-gray-600 hover:text-blue-600">Products</a>
                <span class="mx-2 text-gray-400">/</span>
                <a href="cart.php" class="text-gray-600 hover:text-blue-600">Cart</a>
                <span class="mx-2 text-gray-400">/</span>
                <a href="checkout.php" class="text-gray-600 hover:text-blue-600">Address</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-900">Payment</span>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Left Column - Payment Method -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Payment Method -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">Payment Method</h3>
                    
                    <!-- Card Payment -->
                    <div class="space-y-4">
                        <div class="border rounded-lg p-4">
                            <div class="flex items-center mb-4">
                                <input type="radio" name="payment_method" value="card" checked class="mr-3">
                                <i class="fas fa-credit-card text-blue-600 mr-2"></i>
                                <span class="font-medium">Credit/Debit Card</span>
                            </div>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Card Number</label>
                                    <div class="relative">
                                        <input type="text" placeholder="XXXX XXXX XXXX XXXX" maxlength="19" 
                                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                                        <i class="fas fa-credit-card absolute right-3 top-3 text-gray-400"></i>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                                        <input type="text" placeholder="MM/YY" maxlength="5" 
                                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">CVV</label>
                                        <input type="text" placeholder="123" maxlength="3" 
                                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" class="mr-2">
                                        <span class="text-sm">Save my payment details for future purchases</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Other Payment Methods -->
                        <div class="border rounded-lg p-4">
                            <div class="flex items-center">
                                <input type="radio" name="payment_method" value="upi" class="mr-3">
                                <i class="fas fa-mobile-alt text-green-600 mr-2"></i>
                                <span class="font-medium">UPI</span>
                            </div>
                        </div>

                        <div class="border rounded-lg p-4">
                            <div class="flex items-center">
                                <input type="radio" name="payment_method" value="netbanking" class="mr-3">
                                <i class="fas fa-university text-purple-600 mr-2"></i>
                                <span class="font-medium">Net Banking</span>
                            </div>
                        </div>

                        <div class="border rounded-lg p-4">
                            <div class="flex items-center">
                                <input type="radio" name="payment_method" value="cod" class="mr-3">
                                <i class="fas fa-money-bill-wave text-orange-600 mr-2"></i>
                                <span class="font-medium">Cash on Delivery</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delivery & Billing Information -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Delivery & Billing</h3>
                        <button class="text-blue-600 hover:text-blue-700">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                    
                    <?php if ($default_address): ?>
                        <div class="space-y-4">
                            <div>
                                <h4 class="font-medium text-gray-900 mb-2">Delivery Address</h4>
                                <div class="text-sm text-gray-600">
                                    <p class="font-medium"><?php echo $default_address['name']; ?></p>
                                    <p><?php echo $default_address['address_line1']; ?></p>
                                    <?php if ($default_address['address_line2']): ?>
                                        <p><?php echo $default_address['address_line2']; ?></p>
                                    <?php endif; ?>
                                    <p><?php echo $default_address['city'] . ', ' . $default_address['state'] . ' ' . $default_address['postal_code']; ?></p>
                                    <p><?php echo $default_address['country']; ?></p>
                                    <p>Phone: <?php echo $default_address['phone']; ?></p>
                                </div>
                            </div>
                            
                            <div>
                                <h4 class="font-medium text-gray-900 mb-2">Billing Address</h4>
                                <div class="text-sm text-gray-600">
                                    <p>Same as delivery address</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column - Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow p-6 sticky top-24">
                    <h3 class="text-lg font-semibold mb-4">Order Summary</h3>
                    
                    <!-- Cart Items -->
                    <div class="space-y-4 mb-6">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="flex space-x-4">
                                <div class="w-16 h-16 bg-gray-200 rounded flex items-center justify-center">
                                    <i class="fas fa-box text-gray-400"></i>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-medium"><?php echo $item['name']; ?></h4>
                                    <p class="text-sm text-gray-600">
                                        <?php echo formatDate($item['start_date']); ?> - <?php echo formatDate($item['end_date']); ?>
                                    </p>
                                    <p class="text-sm text-gray-600">Qty: <?php echo $item['quantity']; ?></p>
                                </div>
                                <div class="text-right">
                                    <div class="font-medium"><?php echo formatCurrency($item['total_price']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Price Breakdown -->
                    <div class="border-t pt-4 space-y-2">
                        <div class="flex justify-between">
                            <span>Sub Total</span>
                            <span><?php echo formatCurrency($subtotal); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Delivery Charges</span>
                            <span><?php echo formatCurrency($delivery_charges); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Tax (18%)</span>
                            <span><?php echo formatCurrency($tax_amount); ?></span>
                        </div>
                        <div class="flex justify-between text-sm text-gray-600">
                            <span>Security Deposit (Refundable)</span>
                            <span><?php echo formatCurrency($security_deposit); ?></span>
                        </div>
                        <div class="border-t pt-2 mt-2">
                            <div class="flex justify-between font-semibold text-lg">
                                <span>Total Amount</span>
                                <span class="text-blue-600"><?php echo formatCurrency($total_amount + $security_deposit); ?></span>
                            </div>
                            <div class="text-sm text-gray-600 mt-1">
                                (Including <?php echo formatCurrency($security_deposit); ?> security deposit)
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-6 space-y-3">
                        <button onclick="processPayment()" class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 font-semibold">
                            Pay Now - <?php echo formatCurrency($total_amount + $security_deposit); ?>
                        </button>
                        <a href="checkout.php" class="block w-full text-center bg-gray-200 text-gray-700 py-3 rounded-lg hover:bg-gray-300">
                            Back to Address
                        </a>
                    </div>

                    <!-- Security Note -->
                    <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                        <div class="flex items-start">
                            <i class="fas fa-shield-alt text-blue-600 mt-1 mr-3"></i>
                            <div class="text-sm text-blue-800">
                                <p class="font-medium mb-1">Secure Payment</p>
                                <p>Your payment information is encrypted and secure. We accept all major credit cards and payment methods.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Express Checkout Modal -->
    <div id="expressCheckoutModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4 max-h-screen overflow-y-auto">
            <h3 class="text-lg font-semibold mb-4">Express Checkout</h3>
            <form class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Card Details</label>
                    <input type="text" placeholder="Card Number" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" value="<?php echo $_SESSION['user_name']; ?>" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" value="<?php echo $_SESSION['user_email']; ?>" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" rows="3"><?php echo $default_address['address_line1'] . ', ' . $default_address['city']; ?></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Zip Code</label>
                        <input type="text" value="<?php echo $default_address['postal_code']; ?>" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                        <input type="text" value="<?php echo $default_address['city']; ?>" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                    <input type="text" value="<?php echo $default_address['country']; ?>" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <button type="button" onclick="completeExpressCheckout()" class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 font-semibold">
                    Pay Now
                </button>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-16">
        <div class="container mx-auto px-4">
            <div class="text-center">
                <p>&copy; 2024 Rentify. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        function processPayment() {
            // Validate payment method
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethod) {
                alert('Please select a payment method');
                return;
            }

            // Show express checkout modal for card payments
            if (paymentMethod.value === 'card') {
                document.getElementById('expressCheckoutModal').style.display = 'flex';
            } else {
                // Process other payment methods
                confirmPayment();
            }
        }

        function completeExpressCheckout() {
            document.getElementById('expressCheckoutModal').style.display = 'none';
            confirmPayment();
        }

        function confirmPayment() {
            // Create rental order and invoice
            fetch('api/process-payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    quotation_id: <?php echo $_SESSION['quotation_id']; ?>,
                    payment_method: document.querySelector('input[name="payment_method"]:checked').value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear cart and redirect to order confirmation
                    window.location.href = 'order-confirmation.php?order_id=' + data.order_id;
                } else {
                    alert('Payment failed: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Payment processing failed. Please try again.');
            });
        }

        // Format card number input
        document.querySelector('input[placeholder="XXXX XXXX XXXX XXXX"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });

        // Format expiry date input
        document.querySelector('input[placeholder="MM/YY"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.slice(0, 2) + '/' + value.slice(2, 4);
            }
            e.target.value = value;
        });

        // Only numbers for CVV
        document.querySelector('input[placeholder="123"]').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    </script>
</body>
</html>
