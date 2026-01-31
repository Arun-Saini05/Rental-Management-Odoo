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
$quotation_id = sanitizeInput($_GET['id']);

// Get quotation details
$sql = "SELECT rq.*, u.name as customer_name, u.email as customer_email
        FROM rental_quotations rq
        JOIN customers c ON rq.customer_id = c.id
        JOIN users u ON c.user_id = u.id
        WHERE rq.id = ? AND rq.vendor_id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("ii", $quotation_id, $vendor_id);
$stmt->execute();
$quotation = $stmt->get_result()->fetch_assoc();

if (!$quotation) {
    header('Location: quotations.php');
    exit();
}

// Get quotation lines
$lines_sql = "SELECT rql.*, p.name as product_name, c.name as category_name
              FROM rental_quotation_lines rql
              JOIN products p ON rql.product_id = p.id
              LEFT JOIN categories c ON p.category_id = c.id
              WHERE rql.quotation_id = ?";
$stmt = $db->prepare($lines_sql);
$stmt->bind_param("i", $quotation_id);
$stmt->execute();
$quotation_lines = $stmt->get_result();

// Handle quotation update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notes = sanitizeInput($_POST['notes']);
    $valid_until = sanitizeInput($_POST['valid_until']);
    
    // Update quotation
    $sql = "UPDATE rental_quotations SET notes = ?, valid_until = ? WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ssi", $notes, $valid_until, $quotation_id);
    $stmt->execute();
    
    // Update lines if provided
    if (isset($_POST['update_lines'])) {
        foreach ($_POST['lines'] as $line_id => $line_data) {
            $quantity = intval($line_data['quantity']);
            $start_date = $line_data['start_date'];
            $end_date = $line_data['end_date'];
            
            // Get product pricing
            $product_sql = "SELECT rp.price, rp.security_deposit 
                           FROM rental_pricing rp 
                           WHERE rp.product_id = ? AND rp.period_type = 'day' AND rp.is_active = 1 
                           LIMIT 1";
            $stmt = $db->prepare($product_sql);
            $stmt->bind_param("i", $line_data['product_id']);
            $stmt->execute();
            $pricing = $stmt->get_result()->fetch_assoc();
            
            if ($pricing) {
                $days = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
                $line_total = $pricing['price'] * $quantity * $days;
                $security_deposit = $pricing['security_deposit'] * $quantity;
                
                // Update line
                $line_sql = "UPDATE rental_quotation_lines 
                            SET quantity = ?, unit_price = ?, rental_start_date = ?, 
                            rental_end_date = ?, line_total = ?, security_deposit = ?
                            WHERE id = ?";
                $stmt = $db->prepare($line_sql);
                $stmt->bind_param("idssddi", $quantity, $pricing['price'], $start_date, 
                                 $end_date, $line_total, $security_deposit, $line_id);
                $stmt->execute();
            }
        }
        
        // Recalculate totals
        $recalc_sql = "SELECT SUM(line_total) as subtotal, SUM(security_deposit) as deposit_total
                       FROM rental_quotation_lines WHERE quotation_id = ?";
        $stmt = $db->prepare($recalc_sql);
        $stmt->bind_param("i", $quotation_id);
        $stmt->execute();
        $totals = $stmt->get_result()->fetch_assoc();
        
        $subtotal = $totals['subtotal'];
        $tax_amount = $subtotal * 0.18;
        $total_amount = $subtotal + $tax_amount;
        $security_deposit_total = $totals['deposit_total'];
        
        // Update quotation totals
        $update_sql = "UPDATE rental_quotations 
                       SET subtotal = ?, tax_amount = ?, total_amount = ?, security_deposit_total = ?
                       WHERE id = ?";
        $stmt = $db->prepare($update_sql);
        $stmt->bind_param("dddi", $subtotal, $tax_amount, $total_amount, $security_deposit_total, $quotation_id);
        $stmt->execute();
    }
    
    header('Location: edit-quotation.php?id=' . $quotation_id . '&updated=1');
    exit();
}

// Handle quotation sending
if (isset($_GET['send']) && $quotation['status'] === 'draft') {
    // Update status to sent
    $sql = "UPDATE rental_quotations SET status = 'sent' WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $quotation_id);
    $stmt->execute();
    
    // Create notification for customer
    $notification_sql = "INSERT INTO notifications (user_id, title, message, type, reference_type, reference_id) 
                        VALUES (?, ?, ?, 'info', 'quotation', ?)";
    $stmt = $db->prepare($notification_sql);
    $title = "New Quotation Received";
    $message = "You have received a new quotation: " . $quotation['quotation_number'];
    $stmt->bind_param("issi", $quotation['customer_id'], $title, $message, $quotation_id);
    $stmt->execute();
    
    header('Location: quotations.php?sent=1');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Quotation - Rentify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php require_once '../includes/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Edit Quotation</h1>
                <p class="text-gray-600">Modify quotation details and send to customer</p>
            </div>

            <?php if (isset($_GET['updated'])): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-check-circle mr-2"></i>
                    Quotation updated successfully!
                </div>
            <?php endif; ?>

            <!-- Quotation Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800"><?php echo $quotation['quotation_number']; ?></h2>
                        <p class="text-gray-600">Customer: <?php echo htmlspecialchars($quotation['customer_name']); ?></p>
                        <p class="text-gray-600">Email: <?php echo htmlspecialchars($quotation['customer_email']); ?></p>
                    </div>
                    <div class="text-right">
                        <span class="px-3 py-1 text-sm rounded-full 
                            <?php 
                            $status_colors = [
                                'draft' => 'bg-gray-100 text-gray-600',
                                'sent' => 'bg-blue-100 text-blue-600',
                                'confirmed' => 'bg-green-100 text-green-600',
                                'cancelled' => 'bg-red-100 text-red-600'
                            ];
                            echo $status_colors[$quotation['status']] ?? 'bg-gray-100 text-gray-600';
                            ?>">
                            <?php echo ucfirst($quotation['status']); ?>
                        </span>
                        <p class="text-sm text-gray-600 mt-2">Valid until: <?php echo date('M d, Y', strtotime($quotation['valid_until'])); ?></p>
                    </div>
                </div>

                <form method="POST">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Valid Until
                            </label>
                            <input type="date" name="valid_until" required
                                   value="<?php echo $quotation['valid_until']; ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Notes
                        </label>
                        <textarea name="notes" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?php echo htmlspecialchars($quotation['notes']); ?></textarea>
                    </div>

                    <input type="hidden" name="update_lines" value="1">
                </form>
            </div>

            <!-- Quotation Items -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Quotation Items</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2">Product</th>
                                <th class="text-left py-2">Daily Rate</th>
                                <th class="text-left py-2">Quantity</th>
                                <th class="text-left py-2">Start Date</th>
                                <th class="text-left py-2">End Date</th>
                                <th class="text-left py-2">Days</th>
                                <th class="text-left py-2">Line Total</th>
                                <th class="text-left py-2">Security Deposit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($line = $quotation_lines->fetch_assoc()): ?>
                            <tr class="border-b">
                                <td class="py-3">
                                    <div>
                                        <p class="font-medium"><?php echo htmlspecialchars($line['product_name']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($line['category_name']); ?></p>
                                    </div>
                                    <input type="hidden" name="lines[<?php echo $line['id']; ?>][product_id]" 
                                           value="<?php echo $line['product_id']; ?>">
                                </td>
                                <td class="py-3">₹<?php echo number_format($line['unit_price'], 2); ?></td>
                                <td class="py-3">
                                    <input type="number" name="lines[<?php echo $line['id']; ?>][quantity]" 
                                           value="<?php echo $line['quantity']; ?>" min="1"
                                           class="w-20 px-2 py-1 border border-gray-300 rounded">
                                </td>
                                <td class="py-3">
                                    <input type="date" name="lines[<?php echo $line['id']; ?>][start_date]" 
                                           value="<?php echo $line['rental_start_date']; ?>"
                                           class="px-2 py-1 border border-gray-300 rounded">
                                </td>
                                <td class="py-3">
                                    <input type="date" name="lines[<?php echo $line['id']; ?>][end_date]" 
                                           value="<?php echo $line['rental_end_date']; ?>"
                                           class="px-2 py-1 border border-gray-300 rounded">
                                </td>
                                <td class="py-3"><?php echo $line['rental_days']; ?></td>
                                <td class="py-3">₹<?php echo number_format($line['line_total'], 2); ?></td>
                                <td class="py-3">₹<?php echo number_format($line['security_deposit'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Summary -->
            <div class="bg-gray-50 rounded-lg p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Subtotal</p>
                        <p class="text-lg font-semibold">₹<?php echo number_format($quotation['subtotal'], 2); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Tax (18%)</p>
                        <p class="text-lg font-semibold">₹<?php echo number_format($quotation['tax_amount'], 2); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Security Deposit</p>
                        <p class="text-lg font-semibold">₹<?php echo number_format($quotation['security_deposit_total'], 2); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Total Amount</p>
                        <p class="text-lg font-bold text-blue-600">₹<?php echo number_format($quotation['total_amount'], 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Actions</h3>
                        <p class="text-sm text-gray-600">Choose what to do with this quotation</p>
                    </div>
                    <div class="flex gap-4">
                        <a href="quotations.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg">
                            Back to Quotations
                        </a>
                        <button type="submit" form="quotationForm" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg">
                            <i class="fas fa-save mr-2"></i>Update Quotation
                        </a>
                        <?php if ($quotation['status'] === 'draft'): ?>
                            <a href="edit-quotation.php?id=<?php echo $quotation_id; ?>&send=1" 
                               class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg"
                               onclick="return confirm('Are you sure you want to send this quotation to the customer?')">
                                <i class="fas fa-paper-plane mr-2"></i>Send to Customer
                            </a>
                        <?php endif; ?>
                        <?php if ($quotation['status'] === 'sent'): ?>
                            <button onclick="window.print()" class="bg-purple-500 hover:bg-purple-600 text-white px-6 py-2 rounded-lg">
                                <i class="fas fa-print mr-2"></i>Print Quotation
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .no-print {
                display: none;
            }
            .bg-white {
                box-shadow: none;
                border: 1px solid #ccc;
            }
        }
    </style>
</body>
</html>
