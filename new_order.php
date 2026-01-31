<?php
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch customers for dropdown
$customers_query = "SELECT c.id, u.name, u.email FROM customers c JOIN users u ON c.user_id = u.id";
$customers_result = mysqli_query($conn, $customers_query);

// Fetch products for dropdown
$products_query = "SELECT id, name, sales_price FROM products WHERE is_rentable = 1 AND is_published = 1";
$products_result = mysqli_query($conn, $products_query);

// Generate order number
$order_number = 'S' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_id = sanitize($_POST['customer_id']);
    $invoice_address = sanitize($_POST['invoice_address']);
    $delivery_address = sanitize($_POST['delivery_address']);
    $order_date = sanitize($_POST['order_date']);
    $start_date = sanitize($_POST['start_date']);
    $end_date = sanitize($_POST['end_date']);
    $notes = sanitize($_POST['notes']);
    $terms_conditions = sanitize($_POST['terms_conditions']);
    $coupon_code = sanitize($_POST['coupon_code']);
    $discount = floatval($_POST['discount'] ?? 0);
    $shipping = floatval($_POST['shipping'] ?? 0);
    $downpayment = floatval($_POST['downpayment'] ?? 0);
    
    // Process product line items
    $products = $_POST['products'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $unit_prices = $_POST['unit_prices'] ?? [];
    
    $subtotal = 0;
    $order_lines = [];
    
    foreach ($products as $index => $product_id) {
        if (!empty($product_id) && !empty($quantities[$index])) {
            $quantity = intval($quantities[$index]);
            $unit_price = floatval($unit_prices[$index]);
            $amount = $quantity * $unit_price;
            $subtotal += $amount;
            
            $order_lines[] = [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'amount' => $amount
            ];
        }
    }
    
    // Calculate totals
    $discount_amount = $subtotal * ($discount / 100);
    $untaxed_amount = $subtotal - $discount_amount + $shipping;
    $tax_amount = $untaxed_amount * 0.1; // 10% tax
    $total_amount = $untaxed_amount + $tax_amount;
    
    // Insert order
    $insert_query = "INSERT INTO rental_orders (order_number, customer_id, status, subtotal, tax_amount, total_amount, security_deposit_total, amount_paid, pickup_date, expected_return_date, notes, created_at) VALUES (?, ?, 'quotation', ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($stmt, "sidddddddss", $order_number, $customer_id, $subtotal, $tax_amount, $total_amount, $downpayment, $downpayment, $start_date, $end_date, $notes);
    
    if (mysqli_stmt_execute($stmt)) {
        $order_id = mysqli_insert_id($conn);
        
        // Insert order lines (you would need to create order_lines table)
        foreach ($order_lines as $line) {
            // Insert order line logic here
        }
        
        header('Location: view_order.php?id=' . $order_id);
        exit();
    } else {
        $error = "Error creating order: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Order Page - Rentify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #1a1a1a;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
        }
        
        .form-input {
            background-color: #2d2d2d;
            border: 1px solid #404040;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            width: 100%;
        }
        
        .form-input::placeholder {
            color: #999;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #6366f1;
        }
        
        .btn-action {
            background-color: #404040;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 12px;
            margin-right: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-action:hover {
            background-color: #555555;
        }
        
        .btn-primary {
            background-color: #6366f1;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: #5558e3;
        }
        
        .status-btn {
            background-color: #404040;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 12px;
            margin-right: 8px;
            transition: all 0.3s ease;
        }
        
        .status-btn.active {
            background-color: #6366f1;
        }
        
        .status-btn:hover {
            background-color: #555555;
        }
        
        .order-line {
            background-color: #2d2d2d;
            border: 1px solid #404040;
            border-radius: 4px;
            padding: 12px;
            margin-bottom: 8px;
        }
        
        .pricing-summary {
            background-color: #2d2d2d;
            border: 1px solid #404040;
            border-radius: 4px;
            padding: 16px;
        }
        
        .header-section {
            background-color: #2d2d2d;
            border-bottom: 1px solid #404040;
            padding: 16px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="min-h-screen">
        <!-- Header -->
        <div class="header-section">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-6">
                    <!-- Profile/Logout -->
                    <div class="flex items-center space-x-4">
                        <i class="fas fa-user-circle text-xl"></i>
                        <span><?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Admin'; ?></span>
                        <a href="logout.php" class="text-red-400 hover:text-red-300">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                    
                    <!-- Page Title -->
                    <div>
                        <h1 class="text-2xl font-bold">New Order Page</h1>
                    </div>
                    
                    <!-- Order Type/Status -->
                    <div class="flex items-center space-x-2">
                        <input type="checkbox" id="new_rental" checked>
                        <label for="new_rental" class="text-sm">New Rental order</label>
                        <button class="text-red-400 hover:text-red-300">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex items-center space-x-2">
                    <button class="btn-action">
                        <i class="fas fa-paper-plane mr-1"></i> Send
                    </button>
                    <button class="btn-action">
                        <i class="fas fa-check mr-1"></i> Confirm
                    </button>
                    <button class="btn-action">
                        <i class="fas fa-print mr-1"></i> Print
                    </button>
                    <button class="btn-action">
                        <i class="fas fa-file-invoice mr-1"></i> Create Invoice
                    </button>
                </div>
            </div>
            
            <!-- Workflow Status Buttons -->
            <div class="flex items-center space-x-2 mt-4">
                <button class="status-btn active" id="quotation-btn">
                    <i class="fas fa-file-alt mr-1"></i> Quotation
                </button>
                <button class="status-btn" id="quotation-sent-btn">
                    <i class="fas fa-envelope mr-1"></i> Quotation Sent
                </button>
                <button class="status-btn" id="sale-order-btn">
                    <i class="fas fa-shopping-cart mr-1"></i> Sale Order
                </button>
                <span class="text-xs text-gray-400 ml-2">Sale Order/Rental Order and the status should change to Sale Order</span>
            </div>
        </div>

        <div class="container mx-auto px-4">
            <?php if (isset($error)): ?>
                <div class="bg-red-500/20 border border-red-500 text-red-100 px-4 py-3 rounded-lg mb-6">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column - Order Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Order Number -->
                    <div class="order-line">
                        <label class="block text-sm font-medium mb-2">Order Number</label>
                        <input type="text" value="<?php echo $order_number; ?>" readonly class="form-input bg-gray-700">
                    </div>

                    <!-- Customer Information -->
                    <div class="order-line">
                        <label class="block text-sm font-medium mb-2">Customer *</label>
                        <select name="customer_id" class="form-input" required>
                            <option value="">Select a customer</option>
                            <?php while ($customer = mysqli_fetch_assoc($customers_result)): ?>
                                <option value="<?php echo $customer['id']; ?>">
                                    <?php echo htmlspecialchars($customer['name'] . ' (' . $customer['email'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Invoice Address -->
                    <div class="order-line">
                        <label class="block text-sm font-medium mb-2">Invoice Address</label>
                        <textarea name="invoice_address" class="form-input" rows="3" placeholder="Enter invoice address..."></textarea>
                    </div>

                    <!-- Delivery Address -->
                    <div class="order-line">
                        <label class="block text-sm font-medium mb-2">Delivery Address</label>
                        <textarea name="delivery_address" class="form-input" rows="3" placeholder="Enter delivery address..."></textarea>
                    </div>

                    <!-- Order Lines -->
                    <div class="order-line">
                        <div class="flex items-center justify-between mb-4">
                            <label class="text-sm font-medium">Order Line</label>
                            <button type="button" class="btn-action" onclick="addProductLine()">
                                <i class="fas fa-plus mr-1"></i> Add a Product
                            </button>
                        </div>
                        
                        <div id="product-lines">
                            <!-- Product Line 1 -->
                            <div class="product-line border border-gray-600 rounded p-4 mb-4">
                                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="block text-xs text-gray-400 mb-1">Product</label>
                                        <select name="products[]" class="form-input product-select" onchange="updateProductDetails(this)">
                                            <option value="">Select product</option>
                                            <?php 
                                            mysqli_data_seek($products_result, 0);
                                            while ($product = mysqli_fetch_assoc($products_result)): 
                                            ?>
                                                <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['sales_price']; ?>">
                                                    <?php echo htmlspecialchars($product['name']); ?> [Start Date -> End Date]
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                        <small class="text-xs text-gray-400">Must include product rental duration</small>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Quantity</label>
                                        <input type="number" name="quantities[]" class="form-input quantity-input" placeholder="Units" min="1" value="1" onchange="calculateLineTotal(this)">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Unit Price</label>
                                        <input type="number" name="unit_prices[]" class="form-input unit-price-input" placeholder="Rs" step="0.01" min="0" onchange="calculateLineTotal(this)">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">Amount</label>
                                        <input type="text" class="form-input bg-gray-700 amount-display" readonly value="Rs 0">
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <label class="block text-xs text-gray-400 mb-1">Taxes</label>
                                    <input type="text" class="form-input" placeholder="Taxes">
                                </div>
                                <div class="mt-2">
                                    <label class="block text-xs text-gray-400 mb-1">Downpayment</label>
                                    <input type="text" name="downpayment_line[]" class="form-input" placeholder="Units">
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" class="btn-action mt-2">
                            <i class="fas fa-sticky-note mr-1"></i> Add a note
                        </button>
                    </div>

                    <!-- Terms & Conditions -->
                    <div class="order-line">
                        <label class="block text-sm font-medium mb-2">Terms & Conditions</label>
                        <input type="url" name="terms_conditions" class="form-input" placeholder="https://xxxxx.xxx.xxx/terms">
                    </div>
                </div>

                <!-- Right Column - Pricing and Additional Options -->
                <div class="space-y-6">
                    <!-- Rental Period -->
                    <div class="order-line">
                        <label class="block text-sm font-medium mb-2">Rental Period</label>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Start date</label>
                                <input type="date" name="start_date" class="form-input" required>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">End date</label>
                                <input type="date" name="end_date" class="form-input" required>
                            </div>
                        </div>
                    </div>

                    <!-- Order Date -->
                    <div class="order-line">
                        <label class="block text-sm font-medium mb-2">Order date</label>
                        <input type="date" name="order_date" class="form-input" value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <!-- Pricing Summary -->
                    <div class="pricing-summary">
                        <h3 class="font-semibold mb-4">Pricing Summary</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span>Untaxed Amount:</span>
                                <span id="untaxed-amount">Rs 0</span>
                            </div>
                            <div class="flex justify-between font-bold">
                                <span>Total:</span>
                                <span id="total-amount" class="text-green-400">Rs 0</span>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Options -->
                    <div class="order-line">
                        <h3 class="font-semibold mb-4">Additional Options</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Coupon Code</label>
                                <input type="text" name="coupon_code" class="form-input" placeholder="Enter coupon code">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Discount (%)</label>
                                <input type="number" name="discount" class="form-input" placeholder="0" min="0" max="100" step="0.1" onchange="calculateTotals()">
                            </div>
                            <button type="button" class="btn-action w-full" onclick="addShipping()">
                                <i class="fas fa-truck mr-1"></i> Add Shipping
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="order-line">
                        <button type="submit" class="btn-primary w-full">
                            <i class="fas fa-save mr-2"></i> Create Order
                        </button>
                        <a href="dashboard.php" class="btn-action w-full inline-block text-center mt-2">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        let productLineCount = 1;
        let shippingCost = 0;

        function addProductLine() {
            productLineCount++;
            const productLines = document.getElementById('product-lines');
            //clones the first product row 
            const newLine = document.querySelector('.product-line').cloneNode(true);
            
            // Clear values in cloned line
            newLine.querySelectorAll('select, input').forEach(input => {
                //Selects all inputs & dropdowns inside the cloned row
                if (input.type === 'text' || input.type === 'number') {
                    input.value = input.type === 'number' && input.classList.contains('quantity-input') ? '1' : '';
                    //Prevents copying old values.
                } else if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                    //Resets dropdown to “Select Product”
                }
            });
            
            productLines.appendChild(newLine);
            //Adds the cleaned product row to the form
        }

        function updateProductDetails(select) {
            //Automatically fills the unit price when a product is selected.
            const selectedOption = select.options[select.selectedIndex];
            const price = selectedOption.getAttribute('data-price') || 0;
            const line = select.closest('.product-line');
            const unitPriceInput = line.querySelector('.unit-price-input');
            
            unitPriceInput.value = price;
            //Auto-fills unit price
            calculateLineTotal(unitPriceInput);
        }

        function calculateLineTotal(input) {
            const line = input.closest('.product-line');
            const quantity = parseFloat(line.querySelector('.quantity-input').value) || 0;
            const unitPrice = parseFloat(line.querySelector('.unit-price-input').value) || 0;
            const amount = quantity * unitPrice;
            
            line.querySelector('.amount-display').value = 'Rs ' + amount.toFixed(2);
            calculateTotals();
        }

        function calculateTotals() {
            let subtotal = 0;
            document.querySelectorAll('.product-line').forEach(line => {
                const amountText = line.querySelector('.amount-display').value;
                const amount = parseFloat(amountText.replace('Rs ', '')) || 0;
                subtotal += amount;
            });

            const discount = parseFloat(document.querySelector('input[name="discount"]').value) || 0;
            const discountAmount = subtotal * (discount / 100);
            const untaxedAmount = subtotal - discountAmount + shippingCost;
            const tax = untaxedAmount * 0.1;
            const total = untaxedAmount + tax;

            document.getElementById('untaxed-amount').textContent = 'Rs ' + untaxedAmount.toFixed(2);
            document.getElementById('total-amount').textContent = 'Rs ' + total.toFixed(2);
        }

        function addShipping() {
            const shippingCost = prompt('Enter shipping cost:');
            if (shippingCost && !isNaN(shippingCost)) {
                window.shippingCost = parseFloat(shippingCost);
                calculateTotals();
            }
        }

        // Workflow status buttons
        document.querySelectorAll('.status-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.status-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Set minimum dates
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('input[name="start_date"]').min = today;
        document.querySelector('input[name="end_date"]').min = today;
    </script>
</body>
</html>
