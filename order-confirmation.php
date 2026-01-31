<?php
require_once 'config/database.php';
require_once 'config/functions.php';

requireLogin();

if (!isset($_GET['order_id'])) {
    header('Location: products.php');
    exit();
}

$order_id = $_GET['order_id'];
$db = new Database();

// Get order details
$sql = "SELECT ro.*, u.name as customer_name, u.email as customer_email, u.company_name, u.gstin
        FROM rental_orders ro 
        JOIN users u ON ro.customer_id = u.id 
        WHERE ro.id = ? AND ro.customer_id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header('Location: products.php');
    exit();
}

// Get order lines
$lines_sql = "SELECT rol.*, p.name as product_name, pv.name as variant_name
              FROM rental_order_lines rol 
              JOIN products p ON rol.product_id = p.id 
              LEFT JOIN product_variants pv ON rol.variant_id = pv.id 
              WHERE rol.order_id = ?";
$lines_stmt = $db->prepare($lines_sql);
$lines_stmt->bind_param("i", $order_id);
$lines_stmt->execute();
$order_lines = $lines_stmt->get_result();

// Get invoice details
$invoice_sql = "SELECT * FROM invoices WHERE order_id = ?";
$invoice_stmt = $db->prepare($invoice_sql);
$invoice_stmt->bind_param("i", $order_id);
$invoice_stmt->execute();
$invoice = $invoice_stmt->get_result()->fetch_assoc();

// Clear cart and session
unset($_SESSION['cart']);
unset($_SESSION['quotation_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Rental Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
        }
        .print-only { display: none; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm sticky top-0 z-50 no-print">
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

                <!-- User Actions -->
                <div class="flex items-center space-x-4">
                    <a href="Customer/Dashboard.php" class="text-gray-700 hover:text-blue-600">
                        <i class="fas fa-user-circle text-xl"></i>
                        <span class="hidden md:inline ml-2"><?php echo $_SESSION['user_name']; ?></span>
                    </a>
                    <a href="auth/logout.php" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Success Message -->
    <div class="bg-green-50 border-b border-green-200">
        <div class="container mx-auto px-4 py-6">
            <div class="flex items-center justify-center">
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                        <i class="fas fa-check text-green-600 text-2xl"></i>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Thank you for your order!</h1>
                    <p class="text-lg text-green-600 font-medium">Your Payment has been processed.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Left Column - Order Details -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Order Information -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h2 class="text-xl font-semibold mb-2">Order Information</h2>
                            <p class="text-gray-600">Order ID: <span class="font-medium"><?php echo $order['order_no']; ?></span></p>
                            <p class="text-gray-600">Order Date: <?php echo formatDate($order['created_at']); ?></p>
                            <p class="text-gray-600">Status: <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm"><?php echo $order['status']; ?></span></p>
                        </div>
                        <div class="no-print">
                            <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-print mr-2"></i>Print Invoice
                            </button>
                        </div>
                    </div>

                    <!-- Customer Information -->
                    <div class="border-t pt-6">
                        <h3 class="font-semibold mb-3">Customer Information</h3>
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="font-medium text-gray-900 mb-2">Customer Name</h4>
                                <p class="text-gray-600"><?php echo $order['customer_name']; ?></p>
                                <?php if ($order['company_name']): ?>
                                    <p class="text-gray-600"><?php echo $order['company_name']; ?></p>
                                <?php endif; ?>
                                <?php if ($order['gstin']): ?>
                                    <p class="text-gray-600">GSTIN: <?php echo $order['gstin']; ?></p>
                                <?php endif; ?>
                                <p class="text-gray-600"><?php echo $order['customer_email']; ?></p>
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-900 mb-2">Delivery Address</h4>
                                <p class="text-gray-600">123 Business Street</p>
                                <p class="text-gray-600">City, State 12345</p>
                                <p class="text-gray-600">Country</p>
                                <p class="text-gray-600">Phone: +1-555-0123</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold mb-4">Order Items</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left py-2">Product</th>
                                    <th class="text-left py-2">Variant</th>
                                    <th class="text-center py-2">Qty</th>
                                    <th class="text-left py-2">Rental Period</th>
                                    <th class="text-right py-2">Price</th>
                                </tr>
                            </thead>
                            <div class="space-y-4">
                                <?php while ($line = $order_lines->fetch_assoc()): ?>
                                    <tr class="border-b">
                                        <td class="py-3">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-12 h-12 bg-gray-200 rounded flex items-center justify-center">
                                                    <i class="fas fa-box text-gray-400"></i>
                                                </div>
                                                <span class="font-medium"><?php echo $line['product_name']; ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3"><?php echo $line['variant_name'] ?: 'N/A'; ?></td>
                                        <td class="py-3 text-center"><?php echo $line['quantity']; ?></td>
                                        <td class="py-3">
                                            <div class="text-sm">
                                                <div><?php echo formatDate($line['rental_start_date']); ?></div>
                                                <div><?php echo formatDate($line['rental_end_date']); ?></div>
                                            </div>
                                        </td>
                                        <td class="py-3 text-right font-medium"><?php echo formatCurrency($line['line_total']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </div>
                        </table>
                    </div>
                </div>

                <!-- Rental Information -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="font-semibold mb-4">Rental Information</h3>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Pickup Information</h4>
                            <p class="text-gray-600">Pickup Date: <?php echo formatDate($order['pickup_date']); ?></p>
                            <p class="text-gray-600">Expected Return: <?php echo formatDate($order['expected_return_date']); ?></p>
                            <div class="mt-3 p-3 bg-blue-50 rounded">
                                <p class="text-sm text-blue-800">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Please bring a valid ID proof during pickup
                                </p>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900 mb-2">Security Deposit</h4>
                            <p class="text-gray-600">Deposit Amount: <?php echo formatCurrency($order['security_deposit_total']); ?></p>
                            <p class="text-sm text-gray-500 mt-2">This amount will be refunded upon safe return of the items</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow p-6 sticky top-24">
                    <h3 class="text-lg font-semibold mb-4">Order Summary</h3>
                    
                    <!-- Product Name Summary -->
                    <div class="mb-4">
                        <h4 class="font-medium text-gray-900 mb-2">Product Name</h4>
                        <?php 
                        $order_lines->data_seek(0); 
                        $first_line = $order_lines->fetch_assoc();
                        ?>
                        <p class="text-gray-600"><?php echo $first_line['product_name']; ?></p>
                        <?php if ($order_lines->num_rows > 1): ?>
                            <p class="text-sm text-gray-500">+<?php echo $order_lines->num_rows - 1; ?> more items</p>
                        <?php endif; ?>
                    </div>

                    <!-- Rental Period -->
                    <div class="mb-4">
                        <h4 class="font-medium text-gray-900 mb-2">Rental Period</h4>
                        <p class="text-gray-600">
                            <?php echo formatDate($order['pickup_date']); ?> to <?php echo formatDate($order['expected_return_date']); ?>
                        </p>
                    </div>

                    <!-- Price Breakdown -->
                    <div class="border-t pt-4 space-y-2">
                        <div class="flex justify-between">
                            <span>Sub Total</span>
                            <span><?php echo formatCurrency($order['subtotal']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Delivery Charges</span>
                            <span><?php echo formatCurrency($order['total_amount'] - $order['subtotal'] - $order['tax_amount']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Tax Amount</span>
                            <span><?php echo formatCurrency($order['tax_amount']); ?></span>
                        </div>
                        <div class="flex justify-between text-sm text-gray-600">
                            <span>Security Deposit</span>
                            <span><?php echo formatCurrency($order['security_deposit_total']); ?></span>
                        </div>
                        <div class="border-t pt-2 mt-2">
                            <div class="flex justify-between font-semibold text-lg">
                                <span>Total</span>
                                <span class="text-blue-600"><?php echo formatCurrency($order['total_amount'] + $order['security_deposit_total']); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Invoice Information -->
                    <?php if ($invoice): ?>
                        <div class="mt-6 p-4 bg-gray-50 rounded">
                            <h4 class="font-medium text-gray-900 mb-2">Invoice Information</h4>
                            <p class="text-sm text-gray-600">Invoice No: <?php echo $invoice['invoice_no']; ?></p>
                            <p class="text-sm text-gray-600">Status: <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs"><?php echo $invoice['status']; ?></span></p>
                            <p class="text-sm text-gray-600">Due Date: <?php echo formatDate($invoice['due_date']); ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="mt-6 space-y-3 no-print">
                        <a href="Customer/Dashboard.php" class="block w-full text-center bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 font-semibold">
                            View My Orders
                        </a>
                        <a href="index.php" class="block w-full text-center bg-gray-200 text-gray-700 py-3 rounded-lg hover:bg-gray-300">
                            Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Only Header -->
    <div class="print-only text-center mb-8">
        <h1 class="text-2xl font-bold">Rentify Invoice</h1>
        <p>123 Business Street, City, State 12345</p>
        <p>Phone: +1-555-0123 | Email: info@rentify.com</p>
        <p>GSTIN: 27AAAPL1234C1ZV</p>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-16 no-print">
        <div class="container mx-auto px-4">
            <div class="text-center">
                <p>&copy; 2024 Rentify. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
