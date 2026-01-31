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

// Handle quotation response
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['respond_quotation'])) {
    $quotation_id = sanitizeInput($_POST['quotation_id']);
    $response = sanitizeInput($_POST['response']);
    $notes = sanitizeInput($_POST['notes']);
    
    // Update quotation status
    $update_sql = "UPDATE rental_quotations SET status = ?, notes = ? WHERE id = ? AND customer_id = ?";
    $stmt = $db->prepare($update_sql);
    $stmt->bind_param("ssii", $response, $notes, $quotation_id, $customer_id);
    $stmt->execute();
    
    // Create notification for vendor
    $quotation_sql = "SELECT vendor_id FROM rental_quotations WHERE id = ?";
    $stmt = $db->prepare($quotation_sql);
    $stmt->bind_param("i", $quotation_id);
    $stmt->execute();
    $quotation = $stmt->get_result()->fetch_assoc();
    
    $notification_sql = "INSERT INTO notifications (user_id, title, message, type, reference_type, reference_id) 
                         VALUES (?, ?, ?, 'info', 'quotation', ?)";
    $stmt = $db->prepare($notification_sql);
    $title = "Quotation Response";
    $message = "Customer has " . $response . " your quotation";
    $stmt->bind_param("issi", $quotation['vendor_id'], $title, $message, $quotation_id);
    $stmt->execute();
    
    header('Location: quotations.php?responded=1');
    exit();
}

// Get quotations
$quotations_sql = "SELECT rq.*, u.name as vendor_name, u.email as vendor_email, u.phone as vendor_phone
                   FROM rental_quotations rq
                   JOIN users u ON rq.vendor_id = u.id
                   WHERE rq.customer_id = ?
                   ORDER BY rq.created_at DESC";
$stmt = $db->prepare($quotations_sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$quotations = $stmt->get_result();

// Get quotation statistics
$stats_sql = "SELECT 
    COUNT(*) as total_quotations,
    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as pending_quotations,
    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_quotations,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_quotations
    FROM rental_quotations WHERE customer_id = ?";
$stmt = $db->prepare($stats_sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Quotations - Rentify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '../includes/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">My Quotations</h1>
            <p class="text-gray-600">View and respond to rental quotations from vendors</p>
        </div>

        <?php if (isset($_GET['responded'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                Your response has been sent to the vendor!
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Quotations</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_quotations']; ?></p>
                    </div>
                    <i class="fas fa-file-invoice text-blue-500 text-xl"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Pending</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['pending_quotations']; ?></p>
                    </div>
                    <i class="fas fa-clock text-yellow-500 text-xl"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Accepted</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['accepted_quotations']; ?></p>
                    </div>
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Rejected</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['rejected_quotations']; ?></p>
                    </div>
                    <i class="fas fa-times-circle text-red-500 text-xl"></i>
                </div>
            </div>
        </div>

        <?php if ($quotations->num_rows > 0): ?>
            <div class="space-y-6">
                <?php while ($quotation = $quotations->fetch_assoc()): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">
                                    Quotation #<?php echo htmlspecialchars($quotation['quotation_number']); ?>
                                </h3>
                                <p class="text-sm text-gray-600">
                                    From: <?php echo htmlspecialchars($quotation['vendor_name']); ?>
                                    <span class="mx-2">•</span>
                                    <?php echo htmlspecialchars($quotation['vendor_email']); ?>
                                    <span class="mx-2">•</span>
                                    <?php echo htmlspecialchars($quotation['vendor_phone']); ?>
                                </p>
                                <p class="text-sm text-gray-600">
                                    Created: <?php echo date('M d, Y H:i', strtotime($quotation['created_at'])); ?>
                                    <span class="mx-2">•</span>
                                    Valid until: <?php echo date('M d, Y', strtotime($quotation['valid_until'])); ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="inline-block px-3 py-1 text-xs rounded-full 
                                    <?php 
                                    $status_colors = [
                                        'draft' => 'bg-yellow-100 text-yellow-800',
                                        'sent' => 'bg-blue-100 text-blue-800',
                                        'accepted' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800'
                                    ];
                                    echo $status_colors[$quotation['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $quotation['status'])); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Quotation Details -->
                        <div class="mb-4">
                            <?php
                            $lines_sql = "SELECT rql.*, p.name as product_name, c.name as category_name
                                         FROM rental_quotation_lines rql
                                         JOIN products p ON rql.product_id = p.id
                                         LEFT JOIN categories c ON p.category_id = c.id
                                         WHERE rql.quotation_id = ?";
                            $stmt = $db->prepare($lines_sql);
                            $stmt->bind_param("i", $quotation['id']);
                            $stmt->execute();
                            $lines = $stmt->get_result();
                            ?>

                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b">
                                            <th class="text-left py-2">Product</th>
                                            <th class="text-center py-2">Quantity</th>
                                            <th class="text-center py-2">Daily Rate</th>
                                            <th class="text-center py-2">Days</th>
                                            <th class="text-right py-2">Total</th>
                                            <th class="text-right py-2">Deposit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($line = $lines->fetch_assoc()): ?>
                                        <tr class="border-b">
                                            <td class="py-2">
                                                <div>
                                                    <p class="font-medium"><?php echo htmlspecialchars($line['product_name']); ?></p>
                                                    <p class="text-gray-500"><?php echo htmlspecialchars($line['category_name']); ?></p>
                                                </div>
                                            </td>
                                            <td class="text-center py-2"><?php echo $line['quantity']; ?></td>
                                            <td class="text-center py-2">₹<?php echo number_format($line['unit_price'], 2); ?></td>
                                            <td class="text-center py-2">
                                                <?php 
                                                $days = (strtotime($line['rental_end_date']) - strtotime($line['rental_start_date'])) / (60 * 60 * 24);
                                                echo $days;
                                                ?>
                                            </td>
                                            <td class="text-right py-2">₹<?php echo number_format($line['line_total'], 2); ?></td>
                                            <td class="text-right py-2">₹<?php echo number_format($line['security_deposit'], 2); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pricing Summary -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <h4 class="font-semibold text-gray-800 mb-2">Pricing Summary:</h4>
                                <div class="space-y-1 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Subtotal:</span>
                                        <span>₹<?php echo number_format($quotation['subtotal'], 2); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Tax (18% GST):</span>
                                        <span>₹<?php echo number_format($quotation['tax_amount'], 2); ?></span>
                                    </div>
                                    <div class="flex justify-between font-semibold">
                                        <span>Total Amount:</span>
                                        <span>₹<?php echo number_format($quotation['total_amount'], 2); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Security Deposit:</span>
                                        <span>₹<?php echo number_format($quotation['security_deposit_total'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($quotation['notes']): ?>
                            <div>
                                <h4 class="font-semibold text-gray-800 mb-2">Vendor Notes:</h4>
                                <p class="text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($quotation['notes'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Action Buttons -->
                        <?php if ($quotation['status'] === 'draft' || $quotation['status'] === 'sent'): ?>
                            <div class="flex gap-3">
                                <button onclick="showResponseModal(<?php echo $quotation['id']; ?>, 'accepted')" 
                                        class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg">
                                    <i class="fas fa-check mr-2"></i>Accept Quotation
                                </button>
                                <button onclick="showResponseModal(<?php echo $quotation['id']; ?>, 'rejected')" 
                                        class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg">
                                    <i class="fas fa-times mr-2"></i>Reject Quotation
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <i class="fas fa-file-invoice text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">No quotations yet</h3>
                <p class="text-gray-600 mb-6">Vendors will send you quotations for your rental requests</p>
                <a href="../products.php" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg">
                    <i class="fas fa-search mr-2"></i>Browse Products
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Response Modal -->
    <div id="responseModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Respond to Quotation</h3>
            <form method="POST">
                <input type="hidden" name="respond_quotation" value="1">
                <input type="hidden" name="quotation_id" id="modalQuotationId">
                <input type="hidden" name="response" id="modalResponse">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Your Response</label>
                    <div id="responseText" class="text-lg font-medium text-gray-800"></div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes (Optional)</label>
                    <textarea name="notes" rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="Add any comments or questions..."></textarea>
                </div>
                
                <div class="flex justify-end gap-4">
                    <button type="button" onclick="closeResponseModal()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                        Send Response
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showResponseModal(quotationId, response) {
            document.getElementById('modalQuotationId').value = quotationId;
            document.getElementById('modalResponse').value = response;
            document.getElementById('responseText').textContent = response === 'accepted' ? 'Accept this quotation' : 'Reject this quotation';
            document.getElementById('responseModal').classList.remove('hidden');
        }
        
        function closeResponseModal() {
            document.getElementById('responseModal').classList.add('hidden');
        }
    </script>
</body>
</html>
