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

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = sanitizeInput($_POST['order_id']);
    $new_status = sanitizeInput($_POST['status']);
    $notes = sanitizeInput($_POST['notes']);
    
    // Update order status
    $sql = "UPDATE rental_orders SET status = ?, notes = ? WHERE id = ? AND vendor_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ssii", $new_status, $notes, $order_id, $vendor_id);
    $stmt->execute();
    
    // Create notification for customer
    $order_sql = "SELECT ro.customer_id FROM rental_orders ro WHERE ro.id = ?";
    $stmt = $db->prepare($order_sql);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    
    $notification_sql = "INSERT INTO notifications (user_id, title, message, type, reference_type, reference_id) 
                         VALUES (?, ?, ?, 'info', 'order', ?)";
    $stmt = $db->prepare($notification_sql);
    $title = "Order Status Updated";
    $message = "Your order status has been updated to: " . ucfirst($new_status);
    $stmt->bind_param("issi", $order['customer_id'], $title, $message, $order_id);
    $stmt->execute();
    
    header('Location: orders.php?updated=1');
    exit();
}

// Handle pickup/delivery scheduling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_delivery'])) {
    $order_id = sanitizeInput($_POST['order_id']);
    $delivery_type = sanitizeInput($_POST['delivery_type']);
    $scheduled_date = sanitizeInput($_POST['scheduled_date']);
    $scheduled_time = sanitizeInput($_POST['scheduled_time']);
    $driver_name = sanitizeInput($_POST['driver_name']);
    $driver_phone = sanitizeInput($_POST['driver_phone']);
    
    // Insert/update pickup/delivery record
    $sql = "INSERT INTO pickup_deliveries (order_id, type, status, scheduled_date, scheduled_time, 
            driver_name, driver_phone) VALUES (?, ?, 'scheduled', ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE scheduled_date = VALUES(scheduled_date), 
            scheduled_time = VALUES(scheduled_time), driver_name = VALUES(driver_name), 
            driver_phone = VALUES(driver_phone)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("isssss", $order_id, $delivery_type, $scheduled_date, $scheduled_time, 
                     $driver_name, $driver_phone);
    $stmt->execute();
    
    header('Location: orders.php?scheduled=1');
    exit();
}

// Get orders with filters
$status_filter = sanitizeInput($_GET['status'] ?? '');
$where_clause = "WHERE ro.vendor_id = ?";
$params = [$vendor_id];
$types = "i";

if (!empty($status_filter)) {
    $where_clause .= " AND ro.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql = "SELECT ro.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone,
        c.user_id as customer_user_id
        FROM rental_orders ro
        JOIN customers c ON ro.customer_id = c.id
        JOIN users u ON c.user_id = u.id
        $where_clause
        ORDER BY ro.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$orders = $stmt->get_result();

// Get order statistics
$stats_sql = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as active_orders,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
    SUM(total_amount) as total_revenue
    FROM rental_orders WHERE vendor_id = ?";
$stmt = $db->prepare($stats_sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Rentify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .order-card {
            transition: transform 0.3s ease;
        }
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .status-badge {
            transition: all 0.3s ease;
        }
        .status-badge:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php require_once '../includes/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Orders Management</h1>
            <p class="text-gray-600">View and manage rental orders from customers</p>
        </div>

        <?php if (isset($_GET['updated'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                Order updated successfully!
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['scheduled'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                Pickup/delivery scheduled successfully!
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Orders</p>
                        <p class="text-xl font-bold text-gray-800"><?php echo $stats['total_orders']; ?></p>
                    </div>
                    <i class="fas fa-shopping-cart text-blue-500 text-xl"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Confirmed</p>
                        <p class="text-xl font-bold text-gray-800"><?php echo $stats['confirmed_orders']; ?></p>
                    </div>
                    <i class="fas fa-check text-yellow-500 text-xl"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Active</p>
                        <p class="text-xl font-bold text-gray-800"><?php echo $stats['active_orders']; ?></p>
                    </div>
                    <i class="fas fa-clock text-orange-500 text-xl"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Completed</p>
                        <p class="text-xl font-bold text-gray-800"><?php echo $stats['completed_orders']; ?></p>
                    </div>
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Revenue</p>
                        <p class="text-xl font-bold text-gray-800">₹<?php echo number_format($stats['total_revenue'], 0); ?></p>
                    </div>
                    <i class="fas fa-rupee-sign text-purple-500 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-4 mb-6">
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium text-gray-700">Filter by Status:</label>
                    <select onchange="window.location.href='?status=' + this.value" 
                            class="px-3 py-1 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">All Orders</option>
                        <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="sent" <?php echo $status_filter === 'sent' ? 'selected' : ''; ?>>Sent</option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="ml-auto">
                    <a href="earnings.php" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg text-sm">
                        <i class="fas fa-chart-line mr-2"></i>View Earnings
                    </a>
                </div>
            </div>
        </div>

        <!-- Orders List -->
        <div class="space-y-4">
            <?php while ($order = $orders->fetch_assoc()): ?>
                <div class="order-card bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800"><?php echo $order['order_number']; ?></h3>
                            <div class="text-sm text-gray-600">
                                <p>Customer: <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                <p>Email: <?php echo htmlspecialchars($order['customer_email']); ?></p>
                                <p>Phone: <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="status-badge px-3 py-1 text-sm rounded-full 
                                <?php 
                                $status_colors = [
                                    'draft' => 'bg-gray-100 text-gray-600',
                                    'sent' => 'bg-blue-100 text-blue-600',
                                    'confirmed' => 'bg-yellow-100 text-yellow-600',
                                    'in_progress' => 'bg-orange-100 text-orange-600',
                                    'completed' => 'bg-green-100 text-green-600',
                                    'cancelled' => 'bg-red-100 text-red-600'
                                ];
                                echo $status_colors[$order['status']] ?? 'bg-gray-100 text-gray-600';
                                ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                            </span>
                            <p class="text-sm text-gray-600 mt-1">Order Date: <?php echo date('M d, Y', strtotime($order['created_at'])); ?></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <p class="text-sm text-gray-600">Total Amount</p>
                            <p class="font-semibold">₹<?php echo number_format($order['total_amount'], 2); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Security Deposit</p>
                            <p class="font-semibold">₹<?php echo number_format($order['security_deposit_total'], 2); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Amount Paid</p>
                            <p class="font-semibold">₹<?php echo number_format($order['amount_paid'], 2); ?></p>
                        </div>
                    </div>

                    <?php if ($order['pickup_date']): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <p class="text-sm text-gray-600">Pickup Date</p>
                            <p class="font-semibold"><?php echo date('M d, Y', strtotime($order['pickup_date'])); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Expected Return</p>
                            <p class="font-semibold"><?php echo date('M d, Y', strtotime($order['expected_return_date'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($order['notes']): ?>
                    <div class="mb-4">
                        <p class="text-sm text-gray-600">Notes:</p>
                        <p class="text-sm"><?php echo htmlspecialchars($order['notes']); ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="flex justify-between items-center">
                        <div class="flex gap-2">
                            <button onclick="toggleOrderDetails(<?php echo $order['id']; ?>)" 
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                                <i class="fas fa-eye mr-1"></i>View Details
                            </button>
                            
                            <?php if ($order['status'] === 'confirmed'): ?>
                                <button onclick="showDeliveryModal(<?php echo $order['id']; ?>)" 
                                        class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                                    <i class="fas fa-truck mr-1"></i>Schedule Delivery
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($order['status'] === 'in_progress'): ?>
                                <button onclick="showReturnModal(<?php echo $order['id']; ?>)" 
                                        class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-1 rounded text-sm">
                                    <i class="fas fa-undo mr-1"></i>Schedule Return
                                </button>
                            <?php endif; ?>
                            
                            <button onclick="showStatusModal(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>')" 
                                    class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-1 rounded text-sm">
                                <i class="fas fa-edit mr-1"></i>Update Status
                            </button>
                        </div>
                        
                        <div>
                            <a href="invoice.php?id=<?php echo $order['id']; ?>" 
                               class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-sm">
                                <i class="fas fa-file-invoice mr-1"></i>Invoice
                            </a>
                        </div>
                    </div>

                    <!-- Order Details (Hidden by default) -->
                    <div id="orderDetails<?php echo $order['id']; ?>" class="hidden mt-4 pt-4 border-t">
                        <?php
                        $lines_sql = "SELECT rol.*, p.name as product_name, c.name as category_name
                                     FROM rental_order_lines rol
                                     JOIN products p ON rol.product_id = p.id
                                     LEFT JOIN categories c ON p.category_id = c.id
                                     WHERE rol.order_id = ?";
                        $stmt = $db->prepare($lines_sql);
                        $stmt->bind_param("i", $order['id']);
                        $stmt->execute();
                        $order_lines = $stmt->get_result();
                        ?>
                        
                        <h4 class="font-semibold text-gray-800 mb-2">Order Items:</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b">
                                        <th class="text-left py-2">Product</th>
                                        <th class="text-left py-2">Quantity</th>
                                        <th class="text-left py-2">Daily Rate</th>
                                        <th class="text-left py-2">Days</th>
                                        <th class="text-left py-2">Total</th>
                                        <th class="text-left py-2">Delivered</th>
                                        <th class="text-left py-2">Returned</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($line = $order_lines->fetch_assoc()): ?>
                                    <tr class="border-b">
                                        <td class="py-2">
                                            <div>
                                                <p class="font-medium"><?php echo htmlspecialchars($line['product_name']); ?></p>
                                                <p class="text-gray-500"><?php echo htmlspecialchars($line['category_name']); ?></p>
                                            </div>
                                        </td>
                                        <td class="py-2"><?php echo $line['quantity']; ?></td>
                                        <td class="py-2">₹<?php echo number_format($line['unit_price'], 2); ?></td>
                                        <td class="py-2"><?php echo $line['rental_days']; ?></td>
                                        <td class="py-2">₹<?php echo number_format($line['line_total'], 2); ?></td>
                                        <td class="py-2"><?php echo $line['quantity_delivered']; ?></td>
                                        <td class="py-2"><?php echo $line['quantity_returned']; ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
            
            <?php if ($orders->num_rows === 0): ?>
                <div class="bg-white rounded-lg shadow-md p-8 text-center">
                    <i class="fas fa-shopping-cart text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">No orders found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Update Order Status</h3>
            <form method="POST">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="order_id" id="modalOrderId">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">New Status</label>
                    <select name="status" id="modalStatus" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Select status</option>
                        <option value="draft">Draft</option>
                        <option value="sent">Sent</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                              placeholder="Add notes about this status change"></textarea>
                </div>
                
                <div class="flex justify-end gap-4">
                    <button type="button" onclick="closeStatusModal()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                        Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delivery Modal -->
    <div id="deliveryModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Schedule Delivery/Pickup</h3>
            <form method="POST">
                <input type="hidden" name="schedule_delivery" value="1">
                <input type="hidden" name="order_id" id="deliveryOrderId">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                    <select name="delivery_type" id="deliveryType" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="pickup">Pickup</option>
                        <option value="delivery">Delivery</option>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                        <input type="date" name="scheduled_date" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Time</label>
                        <input type="time" name="scheduled_time" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Driver Name</label>
                        <input type="text" name="driver_name" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Driver Phone</label>
                        <input type="text" name="driver_phone" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="flex justify-end gap-4">
                    <button type="button" onclick="closeDeliveryModal()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg">
                        Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleOrderDetails(orderId) {
            const details = document.getElementById('orderDetails' + orderId);
            details.classList.toggle('hidden');
        }
        
        function showStatusModal(orderId, currentStatus) {
            document.getElementById('modalOrderId').value = orderId;
            document.getElementById('modalStatus').value = currentStatus;
            document.getElementById('statusModal').classList.remove('hidden');
        }
        
        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }
        
        function showDeliveryModal(orderId) {
            document.getElementById('deliveryOrderId').value = orderId;
            document.getElementById('deliveryModal').classList.remove('hidden');
        }
        
        function closeDeliveryModal() {
            document.getElementById('deliveryModal').classList.add('hidden');
        }
        
        function showReturnModal(orderId) {
            document.getElementById('deliveryOrderId').value = orderId;
            document.getElementById('deliveryType').value = 'return';
            document.getElementById('deliveryModal').classList.remove('hidden');
        }
    </script>
</body>
</html>
