<?php
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

if (!isCustomer()) {
    header('Location: ../index.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: invoices.php');
    exit();
}

$db = new Database();
$user_id = $_SESSION['user_id'];
$invoice_id = $_GET['id'];

// Get customer ID
$customer_sql = "SELECT id FROM customers WHERE user_id = ?";
$stmt = $db->prepare($customer_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$customer_id = $customer['id'];

// Get invoice details
$invoice_sql = "SELECT i.*, ro.order_number, ro.status as order_status, ro.pickup_date, ro.expected_return_date,
                c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
                c.address as customer_address, c.city as customer_city, c.state as customer_state, c.postal_code as customer_postal_code,
                u.name as vendor_name, u.email as vendor_email, u.phone as vendor_phone,
                u.company_name, u.address as vendor_address, u.gstin as vendor_gstin
                FROM invoices i
                JOIN rental_orders ro ON i.order_id = ro.id
                JOIN customers c ON ro.customer_id = c.id
                JOIN users u ON ro.vendor_id = u.id
                WHERE i.id = ? AND ro.customer_id = ?";
$stmt = $db->prepare($invoice_sql);
$stmt->bind_param("ii", $invoice_id, $customer_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if (!$invoice) {
    header('Location: invoices.php');
    exit();
}

// Get order items
$items_sql = "SELECT oi.*, p.name as product_name, p.description as product_description
              FROM order_items oi
              JOIN products p ON oi.product_id = p.id
              WHERE oi.order_id = ?";
$stmt = $db->prepare($items_sql);
$stmt->bind_param("i", $invoice['order_id']);
$stmt->execute();
$items = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo $invoice['invoice_no']; ?> - Rentify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '../includes/navbar.php'; ?>

    <!-- Page Header -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white">
        <div class="container mx-auto px-4 py-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold">Invoice #<?php echo $invoice['invoice_no']; ?></h1>
                    <p class="text-blue-100">Order #<?php echo $invoice['order_number']; ?></p>
                </div>
                <div class="flex space-x-4">
                    <button onclick="window.print()" class="bg-white text-blue-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-print mr-2"></i>Print
                    </button>
                    <a href="download-invoice.php?id=<?php echo $invoice['id']; ?>" 
                       class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-download mr-2"></i>Download
                    </a>
                    <a href="invoices.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Invoices
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoice Content -->
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg p-8" id="invoice-content">
            <!-- Invoice Header -->
            <div class="border-b pb-6 mb-6">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">Rentify</h2>
                        <p class="text-gray-600">Rental Management System</p>
                        <?php if ($invoice['vendor_gstin']): ?>
                            <p class="text-gray-600">GSTIN: <?php echo $invoice['vendor_gstin']; ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <h3 class="text-lg font-semibold text-gray-900">INVOICE</h3>
                        <p class="text-gray-600">#<?php echo $invoice['invoice_no']; ?></p>
                        <p class="text-gray-600">Date: <?php echo date('M j, Y', strtotime($invoice['created_at'])); ?></p>
                        <p class="text-gray-600">Due Date: <?php echo date('M j, Y', strtotime($invoice['due_date'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Bill To and From -->
            <div class="grid md:grid-cols-2 gap-8 mb-6">
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">Bill To:</h4>
                    <p class="text-gray-700"><?php echo $invoice['customer_name']; ?></p>
                    <p class="text-gray-600"><?php echo $invoice['customer_email']; ?></p>
                    <p class="text-gray-600"><?php echo $invoice['customer_phone']; ?></p>
                    <?php if ($invoice['customer_address']): ?>
                        <p class="text-gray-600">
                            <?php echo $invoice['customer_address']; ?><br>
                            <?php echo $invoice['customer_city']; ?>, <?php echo $invoice['customer_state']; ?> <?php echo $invoice['customer_postal_code']; ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">From:</h4>
                    <p class="text-gray-700"><?php echo $invoice['vendor_name']; ?></p>
                    <?php if ($invoice['company_name']): ?>
                        <p class="text-gray-600"><?php echo $invoice['company_name']; ?></p>
                    <?php endif; ?>
                    <p class="text-gray-600"><?php echo $invoice['vendor_email']; ?></p>
                    <p class="text-gray-600"><?php echo $invoice['vendor_phone']; ?></p>
                    <?php if ($invoice['vendor_address']): ?>
                        <p class="text-gray-600"><?php echo $invoice['vendor_address']; ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Rental Period -->
            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                <h4 class="font-semibold text-gray-900 mb-2">Rental Period:</h4>
                <p class="text-gray-700">
                    <?php 
                    echo date('M j, Y', strtotime($invoice['rental_start_date'])); 
                    echo ' to '; 
                    echo date('M j, Y', strtotime($invoice['rental_end_date'])); 
                    ?>
                </p>
            </div>

            <!-- Items Table -->
            <div class="mb-6">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Product</th>
                            <th class="px-4 py-2 text-center text-sm font-medium text-gray-700">Quantity</th>
                            <th class="px-4 py-2 text-right text-sm font-medium text-gray-700">Unit Price</th>
                            <th class="px-4 py-2 text-right text-sm font-medium text-gray-700">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php 
                        $subtotal = 0;
                        while ($item = $items->fetch_assoc()): 
                            $item_total = $item['quantity'] * $item['unit_price'];
                            $subtotal += $item_total;
                        ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo $item['product_name']; ?></p>
                                        <?php if ($item['product_description']): ?>
                                            <p class="text-sm text-gray-600"><?php echo $item['product_description']; ?></p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center"><?php echo $item['quantity']; ?></td>
                                <td class="px-4 py-3 text-right">₹<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td class="px-4 py-3 text-right font-medium">₹<?php echo number_format($item_total, 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="flex justify-end mb-6">
                <div class="w-full md:w-1/2">
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal:</span>
                            <span class="font-medium">₹<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tax (18%):</span>
                            <span class="font-medium">₹<?php echo number_format($invoice['tax_amount'], 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Security Deposit:</span>
                            <span class="font-medium">₹<?php echo number_format($invoice['security_deposit'], 2); ?></span>
                        </div>
                        <div class="border-t pt-2">
                            <div class="flex justify-between text-lg font-bold">
                                <span>Total:</span>
                                <span>₹<?php echo number_format($invoice['total_amount'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div class="text-center">
                <?php
                $status_class = '';
                switch ($invoice['order_status']) {
                    case 'completed':
                        $status_class = 'bg-green-100 text-green-800';
                        break;
                    case 'pending':
                        $status_class = 'bg-yellow-100 text-yellow-800';
                        break;
                    case 'cancelled':
                        $status_class = 'bg-red-100 text-red-800';
                        break;
                    default:
                        $status_class = 'bg-gray-100 text-gray-800';
                }
                ?>
                <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $status_class; ?>">
                    Status: <?php echo ucfirst($invoice['order_status']); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-16">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2024 Rentify. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
