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

// Get vendor's products for quotation (only products with pricing)
$products_sql = "SELECT p.*, c.name as category_name,
                (SELECT price FROM rental_pricing rp WHERE rp.product_id = p.id AND rp.period_type = 'day' AND rp.is_active = 1 LIMIT 1) as daily_price
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.vendor_id = ? AND p.is_published = 1 AND p.is_rentable = 1
                AND EXISTS (SELECT 1 FROM rental_pricing rp WHERE rp.product_id = p.id AND rp.is_active = 1)
                ORDER BY p.name";
$stmt = $db->prepare($products_sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$products = $stmt->get_result();

// Get customers for dropdown
$customers_sql = "SELECT u.id, u.name, u.email, u.phone 
                  FROM users u 
                  JOIN customers c ON u.id = c.user_id 
                  WHERE u.role = 'customer' 
                  ORDER BY u.name";
$customers = $db->query($customers_sql);

// Get existing quotations
$quotations_sql = "SELECT rq.*, u.name as customer_name, u.email as customer_email
                  FROM rental_quotations rq
                  JOIN customers c ON rq.customer_id = c.id
                  JOIN users u ON c.user_id = u.id
                  WHERE rq.vendor_id = ?
                  ORDER BY rq.created_at DESC";
$stmt = $db->prepare($quotations_sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$quotations = $stmt->get_result();

// Handle quotation creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quotation'])) {
    $customer_id = sanitizeInput($_POST['customer_id']);
    $notes = sanitizeInput($_POST['notes']);
    $valid_until = sanitizeInput($_POST['valid_until']);
    
    // Generate quotation number
    $quotation_number = 'RQ-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Calculate totals
    $subtotal = 0;
    $security_deposit_total = 0;
    $selected_products = [];
    
    if (isset($_POST['products'])) {
        foreach ($_POST['products'] as $product_id => $details) {
            if (!empty($details['quantity']) && !empty($details['start_date']) && !empty($details['end_date'])) {
                $quantity = intval($details['quantity']);
                $start_date = $details['start_date'];
                $end_date = $details['end_date'];
                
                // Get product and pricing
                $product_sql = "SELECT p.*, rp.price, rp.security_deposit 
                               FROM products p 
                               LEFT JOIN rental_pricing rp ON p.id = rp.product_id AND rp.period_type = 'day'
                               WHERE p.id = ? AND p.vendor_id = ?";
                $stmt = $db->prepare($product_sql);
                $stmt->bind_param("ii", $product_id, $vendor_id);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();
                
                if ($product && !is_null($product['price']) && $product['price'] > 0) {
                    $days = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
                    $line_total = $product['price'] * $quantity * $days;
                    $line_deposit = ($product['security_deposit'] ?? 0) * $quantity;
                    
                    $subtotal += $line_total;
                    $security_deposit_total += $line_deposit;
                    
                    $selected_products[] = [
                        'product_id' => $product_id,
                        'quantity' => $quantity,
                        'unit_price' => $product['price'],
                        'rental_start_date' => $start_date,
                        'rental_end_date' => $end_date,
                        'line_total' => $line_total,
                        'security_deposit' => $line_deposit
                    ];
                } else {
                    // Skip products without pricing
                    continue;
                }
            }
        }
    }
    
    $tax_amount = $subtotal * 0.18; // 18% GST
    $total_amount = $subtotal + $tax_amount;
    
    // Validate that at least one product was selected
    if (empty($selected_products)) {
        header('Location: quotations.php?error=no_products');
        exit();
    }
    
    // Create quotation
    $sql = "INSERT INTO rental_quotations (quotation_number, customer_id, vendor_id, status, 
            subtotal, tax_amount, total_amount, security_deposit_total, notes, valid_until) 
            VALUES (?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("siiddddss", $quotation_number, $customer_id, $vendor_id, 
                      $subtotal, $tax_amount, $total_amount, $security_deposit_total, 
                      $notes, $valid_until);
    
    if ($stmt->execute()) {
        $quotation_id = $db->getLastId();
        
        // Add quotation lines
        foreach ($selected_products as $product) {
            $line_sql = "INSERT INTO rental_quotation_lines (quotation_id, product_id, quantity, 
                        unit_price, rental_start_date, rental_end_date, line_total, security_deposit) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $line_stmt = $db->prepare($line_sql);
            $line_stmt->bind_param("iidsssdd", $quotation_id, $product['product_id'], 
                                  $product['quantity'], $product['unit_price'], 
                                  $product['rental_start_date'], $product['rental_end_date'], 
                                  $product['line_total'], $product['security_deposit']);
            $line_stmt->execute();
        }
        
        header('Location: edit-quotation.php?id=' . $quotation_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotations - Rentify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .product-row:hover {
            background-color: #f9fafb;
        }
        .quotation-card {
            transition: transform 0.3s ease;
        }
        .quotation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php require_once '../includes/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Quotations</h1>
            <p class="text-gray-600">Create and manage rental quotations for customers</p>
        </div>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'no_products'): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                Please select at least one product with valid pricing to create a quotation.
            </div>
        <?php endif; ?>

        <!-- Create New Quotation -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Create New Quotation</h2>
            <form method="POST" id="quotationForm">
                <input type="hidden" name="create_quotation" value="1">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Customer *
                        </label>
                        <select name="customer_id" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select customer</option>
                            <?php while ($customer = $customers->fetch_assoc()): ?>
                                <option value="<?php echo $customer['id']; ?>">
                                    <?php echo htmlspecialchars($customer['name']); ?> 
                                    (<?php echo htmlspecialchars($customer['email']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Valid Until *
                        </label>
                        <input type="date" name="valid_until" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Notes
                    </label>
                    <textarea name="notes" rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Add any notes or special instructions"></textarea>
                </div>

                <!-- Product Selection -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-800 mb-4">Select Products</h3>
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
                                    <th class="text-left py-2">Total</th>
                                </tr>
                            </thead>
                            <tbody id="productTableBody">
                                <?php while ($product = $products->fetch_assoc()): ?>
                                <tr class="product-row border-b">
                                    <td class="py-3">
                                        <div class="flex items-center">
                                            <input type="checkbox" name="selected_products[]" 
                                                   value="<?php echo $product['id']; ?>"
                                                   onchange="toggleProductRow(this)"
                                                   class="mr-2">
                                            <div>
                                                <p class="font-medium"><?php echo htmlspecialchars($product['name']); ?></p>
                                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($product['category_name']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3">₹<?php echo number_format($product['daily_price'], 2); ?></td>
                                    <td class="py-3">
                                        <input type="number" name="products[<?php echo $product['id']; ?>][quantity]" 
                                               min="1" value="1" disabled
                                               class="w-20 px-2 py-1 border border-gray-300 rounded product-input">
                                    </td>
                                    <td class="py-3">
                                        <input type="date" name="products[<?php echo $product['id']; ?>][start_date]" 
                                               disabled
                                               class="px-2 py-1 border border-gray-300 rounded product-input">
                                    </td>
                                    <td class="py-3">
                                        <input type="date" name="products[<?php echo $product['id']; ?>][end_date]" 
                                               disabled
                                               class="px-2 py-1 border border-gray-300 rounded product-input">
                                    </td>
                                    <td class="py-3">
                                        <span class="days-display">-</span>
                                    </td>
                                    <td class="py-3">
                                        <span class="total-display">₹0.00</span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Summary -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Subtotal</p>
                            <p class="text-lg font-semibold" id="subtotal">₹0.00</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Tax (18%)</p>
                            <p class="text-lg font-semibold" id="tax">₹0.00</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Security Deposit</p>
                            <p class="text-lg font-semibold" id="deposit">₹0.00</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Amount</p>
                            <p class="text-lg font-bold text-blue-600" id="total">₹0.00</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg">
                        <i class="fas fa-plus mr-2"></i>Create Quotation
                    </button>
                </div>
            </form>
        </div>

        <!-- Existing Quotations -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Existing Quotations</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php while ($quotation = $quotations->fetch_assoc()): ?>
                <div class="quotation-card border rounded-lg p-4">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h3 class="font-medium text-gray-800"><?php echo $quotation['quotation_number']; ?></h3>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($quotation['customer_name']); ?></p>
                        </div>
                        <span class="px-2 py-1 text-xs rounded-full 
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
                    </div>
                    <div class="text-sm text-gray-600 mb-3">
                        <p>Amount: ₹<?php echo number_format($quotation['total_amount'], 2); ?></p>
                        <p>Valid: <?php echo date('M d, Y', strtotime($quotation['valid_until'])); ?></p>
                    </div>
                    <div class="flex gap-2">
                        <a href="edit-quotation.php?id=<?php echo $quotation['id']; ?>" 
                           class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                            <?php echo $quotation['status'] === 'draft' ? 'Edit' : 'View'; ?>
                        </a>
                        <?php if ($quotation['status'] === 'draft'): ?>
                            <a href="send-quotation.php?id=<?php echo $quotation['id']; ?>" 
                               class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                                Send
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php if ($quotations->num_rows === 0): ?>
                <p class="text-gray-500 text-center py-8">No quotations created yet</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleProductRow(checkbox) {
            const row = checkbox.closest('tr');
            const inputs = row.querySelectorAll('.product-input');
            const isEnabled = checkbox.checked;
            
            inputs.forEach(input => {
                input.disabled = !isEnabled;
            });
            
            if (isEnabled) {
                // Set default dates
                const today = new Date();
                const tomorrow = new Date(today);
                tomorrow.setDate(tomorrow.getDate() + 1);
                
                const startDateInput = row.querySelector('input[name*="[start_date]"]');
                const endDateInput = row.querySelector('input[name*="[end_date]"]');
                
                startDateInput.value = today.toISOString().split('T')[0];
                endDateInput.value = tomorrow.toISOString().split('T')[0];
                
                // Add event listeners for date changes
                startDateInput.addEventListener('change', () => calculateRowTotal(row));
                endDateInput.addEventListener('change', () => calculateRowTotal(row));
                row.querySelector('input[name*="[quantity]"]').addEventListener('input', () => calculateRowTotal(row));
                
                calculateRowTotal(row);
            } else {
                // Clear calculations
                row.querySelector('.days-display').textContent = '-';
                row.querySelector('.total-display').textContent = '₹0.00';
            }
            
            calculateTotals();
        }
        
        function calculateRowTotal(row) {
            const quantity = parseInt(row.querySelector('input[name*="[quantity]"]').value) || 0;
            const startDate = new Date(row.querySelector('input[name*="[start_date]"]').value);
            const endDate = new Date(row.querySelector('input[name*="[end_date]"]').value);
            const dailyRate = parseFloat(row.cells[1].textContent.replace('₹', ''));
            
            if (quantity > 0 && startDate && endDate && endDate > startDate) {
                const days = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24));
                const total = dailyRate * quantity * days;
                
                row.querySelector('.days-display').textContent = days;
                row.querySelector('.total-display').textContent = '₹' + total.toFixed(2);
            } else {
                row.querySelector('.days-display').textContent = '-';
                row.querySelector('.total-display').textContent = '₹0.00';
            }
        }
        
        function calculateTotals() {
            let subtotal = 0;
            let deposit = 0;
            
            document.querySelectorAll('.product-row input[type="checkbox"]:checked').forEach(checkbox => {
                const row = checkbox.closest('tr');
                const totalText = row.querySelector('.total-display').textContent;
                const total = parseFloat(totalText.replace('₹', '')) || 0;
                subtotal += total;
                
                // Add security deposit (assuming 20% of rental amount)
                deposit += total * 0.2;
            });
            
            const tax = subtotal * 0.18;
            const total = subtotal + tax + deposit;
            
            document.getElementById('subtotal').textContent = '₹' + subtotal.toFixed(2);
            document.getElementById('tax').textContent = '₹' + tax.toFixed(2);
            document.getElementById('deposit').textContent = '₹' + deposit.toFixed(2);
            document.getElementById('total').textContent = '₹' + total.toFixed(2);
        }
        
        // Set minimum date for date inputs to today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.querySelectorAll('input[type="date"]').forEach(input => {
                input.min = today;
            });
        });
    </script>
</body>
</html>
