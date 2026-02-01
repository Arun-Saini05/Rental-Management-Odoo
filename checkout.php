<?php
require_once 'config/database.php';
require_once 'config/functions.php';
require('stripe-php-master/init.php');

requireLogin();

$db = new Database();

// Get cart items from session (simplified for demo)
$cart_items = $_SESSION['cart'] ?? [];

if (empty($cart_items)) {
    header('Location: products.php');
    exit();
}

$publishablekey = "pk_test_51SvmfbENuKuGRMUgZAN8CWsIBYnjTjmukidRY8Id3xQaW5kQum78U8CNqXZAUf5pFriLDT75Z2QS0MrMLoRRP56H00ngs1baeE";
$key = "sk_test_51SvmfbENuKuGRMUg3EXcL3bq1CGwg6DLhPWMgD3XSX6XnEhydfQ613mqy5DEG0mNnHDYWBe5C0vlVa9ygl9q1WHR004rKMtjz1";
\Stripe\Stripe::setApiKey($key);

// Calculate totals
$subtotal = 0;
$delivery_charges = 10; // Fixed delivery charge
$tax_rate = 0.18; // 18% GST

foreach ($cart_items as $item) {
    $subtotal += $item['total_price'];
}

$tax_amount = $subtotal * $tax_rate;
$total_amount = $subtotal + $tax_amount + $delivery_charges;

// Convert to cents for Stripe
$total_amount_cents = $total_amount * 100;

// Get user addresses
$addresses_sql = "SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC";
$addresses_stmt = $db->prepare($addresses_sql);
$addresses_stmt->bind_param("i", $_SESSION['user_id']);
$addresses_stmt->execute();
$addresses = $addresses_stmt->get_result();

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method_id'])) {
    try {
        $payment_method_id = $_POST['payment_method_id'];
        $address_id = $_POST['address_id'];
        
        // Get address details
        $address_sql = "SELECT * FROM addresses WHERE id = ? AND user_id = ?";
        $address_stmt = $db->prepare($address_sql);
        $address_stmt->bind_param("ii", $address_id, $_SESSION['user_id']);
        $address_stmt->execute();
        $address = $address_stmt->get_result()->fetch_assoc();
        
        if (!$address) {
            throw new Exception('Invalid address selected');
        }
        
        // Create payment intent
        $payment_intent = \Stripe\PaymentIntent::create([
            'amount' => $total_amount_cents,
            'currency' => 'inr',
            'payment_method' => $payment_method_id,
            'confirmation_method' => 'manual',
            'confirm' => true,
            'return_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/order-confirmation.php',
            'metadata' => [
                'user_id' => $_SESSION['user_id'],
                'address_id' => $address_id,
                'subtotal' => $subtotal,
                'tax_amount' => $tax_amount,
                'delivery_charges' => $delivery_charges
            ]
        ]);
        
        if ($payment_intent->status === 'succeeded') {
            // Payment successful - create order
            $order_sql = "INSERT INTO orders (user_id, address_id, subtotal, tax_amount, delivery_charges, total_amount, status, payment_status, stripe_payment_intent_id, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, 'confirmed', 'paid', ?, NOW())";
            $order_stmt = $db->prepare($order_sql);
            $order_stmt->bind_param("iiddddis", $_SESSION['user_id'], $address_id, $subtotal, $tax_amount, $delivery_charges, $total_amount, $payment_intent->id);
            $order_stmt->execute();
            $order_id = $db->insert_id;
            
            // Add order items
            foreach ($cart_items as $item) {
                $order_item_sql = "INSERT INTO order_items (order_id, product_id, quantity, price, total_price) VALUES (?, ?, ?, ?, ?)";
                $order_item_stmt = $db->prepare($order_item_sql);
                $order_item_stmt->bind_param("iiidd", $order_id, $item['product_id'], $item['quantity'], $item['price'], $item['total_price']);
                $order_item_stmt->execute();
            }
            
            // Clear cart
            unset($_SESSION['cart']);
            
            header('Location: order-confirmation.php?order_id=' . $order_id);
            exit();
        } elseif ($payment_intent->status === 'requires_action') {
            // Payment requires additional authentication
            echo json_encode([
                'requires_action' => true,
                'payment_intent_client_secret' => $payment_intent->client_secret
            ]);
            exit();
        } else {
            throw new Exception('Payment failed: ' . $payment_intent->status);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'error' => true,
            'message' => $e->getMessage()
        ]);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Rental Management System</title>
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
                <span class="text-gray-900">Checkout</span>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Left Column - Delivery & Address -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Delivery Method -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">Delivery Method</h3>
                    <div class="space-y-3">
                        <label class="flex items-center justify-between p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                            <div class="flex items-center">
                                <input type="radio" name="delivery_method" value="standard" checked class="mr-3">
                                <div>
                                    <div class="font-medium">Standard Delivery</div>
                                    <div class="text-sm text-gray-600">3-5 business days</div>
                                </div>
                            </div>
                            <span class="text-green-600 font-medium">Free</span>
                        </label>
                        <label class="flex items-center justify-between p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                            <div class="flex items-center">
                                <input type="radio" name="delivery_method" value="pickup" class="mr-3">
                                <div>
                                    <div class="font-medium">Pick up from Store</div>
                                    <div class="text-sm text-gray-600">Available today</div>
                                </div>
                            </div>
                            <span class="text-green-600 font-medium">Free</span>
                        </label>
                    </div>
                </div>

                <!-- Delivery Address -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Delivery Address</h3>
                        <button onclick="showAddressModal()" class="text-blue-600 hover:text-blue-700">
                            <i class="fas fa-plus mr-1"></i>Add New
                        </button>
                    </div>
                    
                    <?php if ($addresses->num_rows > 0): ?>
                        <div class="space-y-3">
                            <?php while ($address = $addresses->fetch_assoc()): ?>
                                <label class="flex items-start p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                    <input type="radio" name="delivery_address" value="<?php echo $address['id']; ?>" 
                                           <?php echo $address['is_default'] ? 'checked' : ''; ?> class="mr-3 mt-1">
                                    <div class="flex-1">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <div class="font-medium"><?php echo $address['name']; ?></div>
                                                <div class="text-sm text-gray-600 mt-1">
                                                    <?php echo $address['address_line1']; ?><br>
                                                    <?php if ($address['address_line2']) echo $address['address_line2'] . '<br>'; ?>
                                                    <?php echo $address['city'] . ', ' . $address['state'] . ' ' . $address['postal_code']; ?><br>
                                                    <?php echo $address['country']; ?><br>
                                                    Phone: <?php echo $address['phone']; ?>
                                                </div>
                                            </div>
                                            <div class="flex space-x-2">
                                                <?php if ($address['is_default']): ?>
                                                    <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">Main Address</span>
                                                <?php endif; ?>
                                                <button class="text-gray-400 hover:text-gray-600">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-map-marker-alt text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-600 mb-4">No delivery addresses found</p>
                            <button onclick="showAddressModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                Add Address
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Billing Address -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">Billing Address</h3>
                    <label class="flex items-center">
                        <input type="checkbox" id="same_as_delivery" checked class="mr-3">
                        <span>Same as delivery address</span>
                    </label>
                    <div id="billing_address_section" class="hidden mt-4">
                        <!-- Billing address form would go here -->
                    </div>
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
                        <div class="border-t pt-2 mt-2">
                            <div class="flex justify-between font-semibold text-lg">
                                <span>Total</span>
                                <span class="text-blue-600"><?php echo formatCurrency($total_amount); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Coupon Code -->
                    <div class="mt-6">
                        <div class="flex space-x-2">
                            <input type="text" placeholder="Coupon code" class="flex-1 px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            <button class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">
                                Apply
                            </button>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-6 space-y-3">
                        <button onclick="proceedToPayment()" class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 font-semibold">
                            Proceed to Payment
                        </button>
                        <a href="cart.php" class="block w-full text-center bg-gray-200 text-gray-700 py-3 rounded-lg hover:bg-gray-300">
                            Back to Cart
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Section -->
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
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span>Subtotal:</span>
                                    <span>₹<?php echo number_format($subtotal, 2); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Tax (18%):</span>
                                    <span>₹<?php echo number_format($tax_amount, 2); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Delivery:</span>
                                    <span>₹<?php echo number_format($delivery_charges, 2); ?></span>
                                </div>
                                <div class="border-t pt-2 flex justify-between font-semibold">
                                    <span>Total:</span>
                                    <span>₹<?php echo number_format($total_amount, 2); ?></span>
                                </div>
                            </div>
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

    <!-- Address Modal -->
    <div id="addressModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4 max-h-screen overflow-y-auto">
            <h3 class="text-lg font-semibold mb-4">Add New Address</h3>
            <form id="addressForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address Line 1</label>
                    <input type="text" name="address_line1" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address Line 2</label>
                    <input type="text" name="address_line2" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                        <input type="text" name="city" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Postal Code</label>
                        <input type="text" name="postal_code" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                        <input type="text" name="state" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                        <input type="text" name="country" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="tel" name="phone" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <div class="flex space-x-3">
                    <button type="button" onclick="closeAddressModal()" class="flex-1 bg-gray-200 text-gray-700 py-2 rounded hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
                        Save Address
                    </button>
                </div>
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

    <script src="https://js.stripe.com/v3/"></script>
    <script>
        // Initialize Stripe
        const stripe = Stripe('<?php echo $publishablekey; ?>');
        const elements = stripe.elements();
        
        // Create card element
        const cardElement = elements.create('card', {
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
        
        // Mount card element
        cardElement.mount('#card-element');
        
        // Handle card element errors
        cardElement.on('change', ({error}) => {
            const displayError = document.getElementById('card-errors');
            if (error) {
                displayError.textContent = error.message;
            } else {
                displayError.textContent = '';
            }
        });
        
        // Handle form submission
        const form = document.getElementById('payment-form');
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            
            const submitButton = document.getElementById('submit-button');
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
            
            const {error, paymentMethod} = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
                billing_details: {
                    name: document.getElementById('cardholder-name').value,
                    email: '<?php echo $_SESSION['user_email'] ?? ''; ?>',
                },
            });
            
            if (error) {
                document.getElementById('card-errors').textContent = error.message;
                submitButton.disabled = false;
                submitButton.innerHTML = 'Pay ₹<?php echo number_format($total_amount, 2); ?>';
                return;
            }
            
            // Send payment method to server
            const formData = new FormData();
            formData.append('payment_method_id', paymentMethod.id);
            formData.append('address_id', document.querySelector('input[name="delivery_address"]:checked').value);
            
            const response = await fetch('checkout.php', {
                method: 'POST',
                body: formData,
            });
            
            const result = await response.json();
            
            if (result.error) {
                document.getElementById('card-errors').textContent = result.message;
                submitButton.disabled = false;
                submitButton.innerHTML = 'Pay ₹<?php echo number_format($total_amount, 2); ?>';
            } else if (result.requires_action) {
                // Handle 3D Secure authentication
                const {error: confirmationError, paymentIntent} = await stripe.handleCardAction(
                    result.payment_intent_client_secret
                );
                
                if (confirmationError) {
                    document.getElementById('card-errors').textContent = confirmationError.message;
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Pay ₹<?php echo number_format($total_amount, 2); ?>';
                } else {
                    // Payment successful, redirect
                    window.location.href = 'order-confirmation.php';
                }
            } else {
                // Payment successful, redirect
                window.location.href = 'order-confirmation.php';
            }
        });
        
        function proceedToPayment() {
            // Validate delivery address selection
            const deliveryAddress = document.querySelector('input[name="delivery_address"]:checked');
            if (!deliveryAddress) {
                alert('Please select a delivery address');
                return;
            }
            
            // Show payment section
            document.getElementById('payment-section').classList.remove('hidden');
            document.getElementById('payment-section').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
    <script>
        function showAddressModal() {
            document.getElementById('addressModal').style.display = 'flex';
        }

        function closeAddressModal() {
            document.getElementById('addressModal').style.display = 'none';
        }

        document.getElementById('same_as_delivery').addEventListener('change', function() {
            const billingSection = document.getElementById('billing_address_section');
            if (this.checked) {
                billingSection.classList.add('hidden');
            } else {
                billingSection.classList.remove('hidden');
            }
        });

        // Handle address form submission
        document.getElementById('addressForm').addEventListener('submit', function(e) {
            e.preventDefault();
            // Add address logic here
            alert('Address added successfully!');
            closeAddressModal();
            location.reload();
        });
    </script>
</body>
</html>
