<?php
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

if (!isVendor()) {
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$vendor_id = $_SESSION['user_id'];

$order_id = sanitizeInput($_GET['id'] ?? 0);

// Get order details
$order_sql = "SELECT ro.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone,
              u.address as customer_address, c.user_id as customer_user_id
              FROM rental_orders ro
              JOIN customers c ON ro.customer_id = c.id
              JOIN users u ON c.user_id = u.id
              WHERE ro.id = ? AND ro.vendor_id = ?";
$stmt = $db->prepare($order_sql);
$stmt->bind_param("ii", $order_id, $vendor_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header('Location: orders.php');
    exit();
}

// Get order lines
$lines_sql = "SELECT rol.*, p.name as product_name, p.description as product_description,
              c.name as category_name
              FROM rental_order_lines rol
              JOIN products p ON rol.product_id = p.id
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE rol.order_id = ?";
$stmt = $db->prepare($lines_sql);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_lines = $stmt->get_result();

// Get vendor details
$vendor_sql = "SELECT u.name as vendor_name, u.email as vendor_email, u.phone as vendor_phone,
               u.address as vendor_address
               FROM users u WHERE u.id = ?";
$stmt = $db->prepare($vendor_sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$vendor = $stmt->get_result()->fetch_assoc();

// Handle invoice generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_invoice'])) {
    // Generate invoice number
    $invoice_number = 'INV-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Create invoice record
    $invoice_sql = "INSERT INTO invoices (invoice_number, order_id, vendor_id, customer_id, 
                    subtotal, tax_amount, total_amount, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'paid', NOW())";
    $stmt = $db->prepare($invoice_sql);
    $stmt->bind_param("siidddd", $invoice_number, $order_id, $vendor_id, $order['customer_id'],
                      $order['subtotal'], $order['tax_amount'], $order['total_amount']);
    
    if ($stmt->execute()) {
        $invoice_id = $db->getLastId();
        header('Location: invoice.php?id=' . $order_id . '&invoice_id=' . $invoice_id . '&generated=1');
        exit();
    }
}

// Check if invoice already exists
$invoice_sql = "SELECT * FROM invoices WHERE order_id = ?";
$stmt = $db->prepare($invoice_sql);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $invoice['invoice_number'] ?? $order['order_number']; ?> - Rentify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            body { font-size: 12pt; }
            .invoice-container { max-width: 100%; margin: 0; padding: 0; }
        }
        .print-only { display: none; }
        .invoice-container { max-width: 800px; margin: 0 auto; }
    </style>
</head>
<body class="bg-gray-50">
    <?php require_once '../includes/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <?php if (isset($_GET['generated'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                Invoice generated successfully!
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="no-print flex justify-between items-center mb-6">
            <div>
                <a href="orders.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Orders
                </a>
            </div>
            <div class="flex gap-2">
                <?php if (!$invoice): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="generate_invoice" value="1">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-file-invoice mr-2"></i>Generate Invoice
                        </button>
                    </form>
                <?php endif; ?>
                <button onclick="window.print()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-print mr-2"></i>Print
                </button>
                <button onclick="downloadPDF()" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-download mr-2"></i>Download PDF
                </button>
            </div>
        </div>

        <!-- Invoice Container -->
        <div class="invoice-container bg-white rounded-lg shadow-lg p-8">
            <!-- Invoice Header -->
            <div class="border-b-2 border-gray-200 pb-6 mb-6">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">INVOICE</h1>
                        <p class="text-gray-600 mt-2">
                            <?php if ($invoice): ?>
                                Invoice #: <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                            <?php else: ?>
                                Proforma Invoice
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="bg-blue-100 text-blue-800 px-4 py-2 rounded-lg">
                            <p class="font-semibold"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></p>
                        </div>
                        <p class="text-sm text-gray-600 mt-2">Date: <?php echo date('M d, Y'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Vendor & Customer Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
                <div>
                    <h3 class="font-semibold text-gray-800 mb-2">From:</h3>
                    <div class="text-gray-600">
                        <p class="font-medium"><?php echo htmlspecialchars($vendor['vendor_name']); ?></p>
                        <p><?php echo htmlspecialchars($vendor['vendor_email']); ?></p>
                        <p><?php echo htmlspecialchars($vendor['vendor_phone']); ?></p>
                        <p><?php echo nl2br(htmlspecialchars($vendor['vendor_address'])); ?></p>
                    </div>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800 mb-2">Bill To:</h3>
                    <div class="text-gray-600">
                        <p class="font-medium"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                        <p><?php echo htmlspecialchars($order['customer_email']); ?></p>
                        <p><?php echo htmlspecialchars($order['customer_phone']); ?></p>
                        <p><?php echo nl2br(htmlspecialchars($order['customer_address'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Order Details -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
                <div>
                    <p class="text-sm text-gray-600">Order Number</p>
                    <p class="font-semibold"><?php echo htmlspecialchars($order['order_number']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Order Date</p>
                    <p class="font-semibold"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Expected Return</p>
                    <p class="font-semibold"><?php echo date('M d, Y', strtotime($order['expected_return_date'])); ?></p>
                </div>
            </div>

            <!-- Invoice Items -->
            <div class="mb-6">
                <h3 class="font-semibold text-gray-800 mb-4">Rental Details</h3>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="text-left py-3 px-4 border border-gray-300">Product</th>
                                <th class="text-center py-3 px-4 border border-gray-300">Qty</th>
                                <th class="text-center py-3 px-4 border border-gray-300">Daily Rate</th>
                                <th class="text-center py-3 px-4 border border-gray-300">Days</th>
                                <th class="text-right py-3 px-4 border border-gray-300">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $subtotal = 0;
                            while ($line = $order_lines->fetch_assoc()): 
                                $subtotal += $line['line_total'];
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-4 border border-gray-300">
                                    <div>
                                        <p class="font-medium"><?php echo htmlspecialchars($line['product_name']); ?></p>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($line['category_name']); ?></p>
                                    </div>
                                </td>
                                <td class="text-center py-3 px-4 border border-gray-300"><?php echo $line['quantity']; ?></td>
                                <td class="text-center py-3 px-4 border border-gray-300">₹<?php echo number_format($line['unit_price'], 2); ?></td>
                                <td class="text-center py-3 px-4 border border-gray-300"><?php echo $line['rental_days']; ?></td>
                                <td class="text-right py-3 px-4 border border-gray-300">₹<?php echo number_format($line['line_total'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Totals -->
            <div class="flex justify-end mb-6">
                <div class="w-full md:w-1/2">
                    <table class="w-full">
                        <tr>
                            <td class="py-2 text-gray-600">Subtotal:</td>
                            <td class="py-2 text-right font-medium">₹<?php echo number_format($subtotal, 2); ?></td>
                        </tr>
                        <tr>
                            <td class="py-2 text-gray-600">Tax (18% GST):</td>
                            <td class="py-2 text-right font-medium">₹<?php echo number_format($order['tax_amount'], 2); ?></td>
                        </tr>
                        <tr class="border-t-2 border-gray-300">
                            <td class="py-3 font-semibold text-gray-800">Total Amount:</td>
                            <td class="py-3 text-right font-bold text-lg">₹<?php echo number_format($order['total_amount'], 2); ?></td>
                        </tr>
                        <tr>
                            <td class="py-2 text-gray-600">Security Deposit:</td>
                            <td class="py-2 text-right font-medium">₹<?php echo number_format($order['security_deposit_total'], 2); ?></td>
                        </tr>
                        <tr>
                            <td class="py-2 text-gray-600">Amount Paid:</td>
                            <td class="py-2 text-right font-medium text-green-600">₹<?php echo number_format($order['amount_paid'], 2); ?></td>
                        </tr>
                        <tr class="border-t-2 border-gray-300">
                            <td class="py-3 font-semibold text-gray-800">Balance Due:</td>
                            <td class="py-3 text-right font-bold text-lg text-orange-600">
                                ₹<?php echo number_format($order['total_amount'] - $order['amount_paid'], 2); ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Notes -->
            <?php if ($order['notes']): ?>
            <div class="mb-6">
                <h3 class="font-semibold text-gray-800 mb-2">Notes:</h3>
                <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="border-t-2 border-gray-200 pt-6 mt-6">
                <div class="flex justify-between items-center">
                    <div class="text-gray-600">
                        <p class="text-sm">Thank you for your business!</p>
                        <p class="text-sm">For any questions, please contact us at <?php echo htmlspecialchars($vendor['vendor_email']); ?></p>
                    </div>
                    <?php if ($invoice): ?>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Invoice ID: <?php echo $invoice['id']; ?></p>
                        <p class="text-sm text-gray-600">Generated: <?php echo date('M d, Y H:i', strtotime($invoice['created_at'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function downloadPDF() {
            // Simple PDF download simulation
            // In production, you would use a library like jsPDF or server-side PDF generation
            window.print();
            
            // Show download message
            const message = document.createElement('div');
            message.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
            message.innerHTML = '<i class="fas fa-check-circle mr-2"></i>PDF download initiated!';
            document.body.appendChild(message);
            
            setTimeout(() => {
                message.remove();
            }, 3000);
        }
    </script>
</body>
</html>
