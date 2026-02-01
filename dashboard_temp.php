<?php
$page_title = 'Dashboard - Rentify';
include 'header.php';

// Get user role
$user_role = $_SESSION['user_role'] ?? 'vendor';
$vendor_id = $_SESSION['vendor_id'] ?? null;

// Fetch rental orders from database
$orders_query = "SELECT ro.id, ro.order_number, u.name as customer_name, p.name as product_name, p.sales_price as rental_price, 
                DATEDIFF(ro.expected_return_date, ro.pickup_date) as rental_duration, ro.status
                FROM rental_orders ro 
                LEFT JOIN customers c ON ro.customer_id = c.id 
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN products p ON ro.vendor_id = p.vendor_id
                ORDER BY ro.created_at DESC";
$orders_result = mysqli_query($conn, $orders_query);

// Fetch invoices
$invoices_query = "SELECT i.id, i.invoice_number, u.name as customer_name, i.total_amount, i.status, i.created_at
                  FROM invoices i 
                  LEFT JOIN customers c ON i.customer_id = c.id 
                  LEFT JOIN users u ON c.user_id = u.id
                  ORDER BY i.created_at DESC LIMIT 10";
$invoices_result = mysqli_query($conn, $invoices_query);

// Initialize status counts
$status_counts = [
    'total' => 0,
    'draft' => 0,
    'sent' => 0,
    'confirmed' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'cancelled' => 0
];

$orders = [];
while ($row = mysqli_fetch_assoc($orders_result)) {
    $orders[] = $row;
    $status_counts['total']++;
    
    switch ($row['status']) {
        case 'draft':
            $status_counts['draft']++;
            break;
        case 'sent':
            $status_counts['sent']++;
            break;
        case 'confirmed':
            $status_counts['confirmed']++;
            break;
        case 'in_progress':
            $status_counts['in_progress']++;
            break;
        case 'completed':
            $status_counts['completed']++;
            break;
        case 'cancelled':
            $status_counts['cancelled']++;
            break;
    }
}
?>

        <!-- Page Content -->
        <div class="container mx-auto px-4 py-8">
            <!-- Dashboard Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-gray-800 p-6 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Total Orders</p>
                            <p class="text-2xl font-bold"><?php echo $status_counts['total']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center">
                            <i class="fas fa-shopping-cart text-white"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-800 p-6 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Draft Orders</p>
                            <p class="text-2xl font-bold"><?php echo $status_counts['draft']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-gray-500 rounded-lg flex items-center justify-center">
                            <i class="fas fa-edit text-white"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-800 p-6 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Completed</p>
                            <p class="text-2xl font-bold"><?php echo $status_counts['completed']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check-circle text-white"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-800 p-6 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Revenue</p>
                            <p class="text-2xl font-bold">Rs <?php echo number_format(array_sum(array_column($orders, 'rental_price')), 2); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-500 rounded-lg flex items-center justify-center">
                            <i class="fas fa-dollar-sign text-white"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="bg-gray-800 rounded-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold">Recent Orders</h2>
                    <a href="new_order.php" class="btn-primary">
                        <i class="fas fa-plus mr-2"></i>New Order
                    </a>
                </div>
                
                <div class="space-y-4">
                    <?php if (empty($orders)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-shopping-cart text-4xl text-gray-600 mb-4"></i>
                            <p class="text-gray-400">No orders found</p>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($orders, 0, 5) as $order): ?>
                            <div class="bg-gray-700 p-4 rounded-lg hover:bg-gray-600 transition-colors">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-4">
                                            <div>
                                                <p class="font-semibold"><?php echo htmlspecialchars($order['order_number']); ?></p>
                                                <p class="text-sm text-gray-400"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                                            </div>
                                            <div>
                                                <p class="text-sm"><?php echo htmlspecialchars($order['product_name']); ?></p>
                                                <p class="text-sm text-gray-400">Rs <?php echo number_format($order['rental_price'], 2); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <span class="status-badge status-<?php echo $order['status']; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                        <a href="view_order.php?id=<?php echo $order['id']; ?>" class="text-blue-400 hover:text-blue-300">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

<?php include 'footer.php'; ?>
