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

// Handle status filter
$status_filter = sanitizeInput($_GET['status'] ?? '');
$where_clause = "WHERE ro.customer_id = ?";
$params = [$customer_id];
$types = "i";

if (!empty($status_filter)) {
    $where_clause .= " AND ro.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Get all user orders with details
$orders_sql = "SELECT ro.*, COUNT(rol.id) as item_count,
               u.name as vendor_name, u.email as vendor_email
               FROM rental_orders ro 
               LEFT JOIN rental_order_lines rol ON ro.id = rol.order_id
               LEFT JOIN users u ON ro.vendor_id = u.id
               $where_clause
               GROUP BY ro.id 
               ORDER BY ro.created_at DESC";
$orders_stmt = $db->prepare($orders_sql);
$orders_stmt->bind_param($types, ...$params);
$orders_stmt->execute();
$orders = $orders_stmt->get_result();

// Get order statistics
$stats_sql = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as active_orders,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
    SUM(total_amount) as total_spent
    FROM rental_orders WHERE customer_id = ?";
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
    <title>My Orders - Rental Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '../includes/navbar.php'; ?>

    <!-- Page Header -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white">
        <div class="container mx-auto px-4 py-8">
            <h1 class="text-3xl font-bold">My Orders</h1>
            <p class="text-blue-100">View and track all your rental orders</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Orders</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_orders']; ?></p>
                    </div>
                    <i class="fas fa-shopping-bag text-blue-500 text-xl"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Confirmed</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['confirmed_orders']; ?></p>
                    </div>
                    <i class="fas fa-clock text-yellow-500 text-xl"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Active</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['active_orders']; ?></p>
                    </div>
                    <i class="fas fa-truck text-orange-500 text-xl"></i>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Completed</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['completed_orders']; ?></p>
                    </div>
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-semibold">Order History</h2>
                    <div class="flex space-x-2">
                        <form method="GET" class="flex">
                            <select name="status" onchange="this.form.submit()" 
                                    class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                                <option value="">All Orders</option>
                                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="sent" <?php echo $status_filter === 'sent' ? 'selected' : ''; ?>>Sent</option>
                                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </form>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <?php if ($orders->num_rows > 0): ?>
                    <div class="space-y-4">
                        <?php while ($order = $orders->fetch_assoc()): ?>
                            <div class="border rounded-lg p-6 hover:bg-gray-50">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="font-semibold text-lg text-gray-900">
                                            Order #<?php echo $order['order_number']; ?>
                                        </h3>
                                        <p class="text-sm text-gray-600 mt-1">
                                            Placed on <?php echo formatDate($order['created_at']); ?>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            <?php echo $order['item_count']; ?> items • 
                                            <?php echo formatCurrency($order['total_amount']); ?>
                                        </p>
                                        <div class="mt-3">
                                            <span class="inline-block px-3 py-1 text-xs rounded-full 
                                                <?php 
                                                switch($order['status']) {
                                                    case 'confirmed': echo 'bg-blue-100 text-blue-800'; break;
                                                    case 'in_progress': echo 'bg-orange-100 text-orange-800'; break;
                                                    case 'completed': echo 'bg-green-100 text-green-800'; break;
                                                    case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                                    default: echo 'bg-gray-100 text-gray-800';
                                                }
                                                ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-lg"><?php echo formatCurrency($order['total_amount']); ?></p>
                                        <div class="mt-3 space-x-2">
                                            <a href="order-detail.php?id=<?php echo $order['id']; ?>" 
                                               class="text-blue-600 hover:text-blue-700 text-sm">
                                                <i class="fas fa-eye mr-1"></i>View Details
                                            </a>
                                            <?php if ($order['status'] === 'confirmed'): ?>
                                                <a href="#" class="text-green-600 hover:text-green-700 text-sm">
                                                    <i class="fas fa-download mr-1"></i>Pickup Slip
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No orders yet</h3>
                        <p class="text-gray-600 mb-4">Start renting products to see your orders here</p>
                        <a href="../products.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            Browse Products
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
