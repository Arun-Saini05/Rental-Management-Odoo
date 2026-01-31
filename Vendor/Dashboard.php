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

// Pagination for products
$products_per_page = 8;
$current_product_page = isset($_GET['product_page']) ? (int)$_GET['product_page'] : 1;
$product_offset = ($current_product_page - 1) * $products_per_page;

// Get total products count
$total_products_sql = "SELECT COUNT(*) as total FROM products WHERE vendor_id = ?";
$total_products_stmt = $db->prepare($total_products_sql);
$total_products_stmt->bind_param("i", $vendor_id);
$total_products_stmt->execute();
$total_products_result = $total_products_stmt->get_result();
$total_products = $total_products_result->fetch_assoc()['total'];
$total_product_pages = ceil($total_products / $products_per_page);

// Get vendor statistics
$stats_sql = "SELECT 
    COUNT(DISTINCT p.id) as total_products,
    COUNT(DISTINCT ro.id) as total_orders,
    COALESCE(SUM(ro.total_amount), 0) as total_revenue,
    SUM(CASE WHEN ro.status = 'confirmed' THEN 1 ELSE 0 END) as active_orders
FROM users u
LEFT JOIN products p ON u.id = p.vendor_id
LEFT JOIN rental_orders ro ON u.id = ro.vendor_id
WHERE u.id = ?";
$stmt = $db->prepare($stats_sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get recent orders
$orders_sql = "SELECT ro.*, c.user_id, u.name as customer_name, u.email as customer_email
              FROM rental_orders ro
              JOIN customers c ON ro.customer_id = c.id
              JOIN users u ON c.user_id = u.id
              WHERE ro.vendor_id = ?
              ORDER BY ro.created_at DESC
              LIMIT 5";
$stmt = $db->prepare($orders_sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$recent_orders = $stmt->get_result();

// Get vendor products with pagination
$products_sql = "SELECT p.*, c.name as category_name,
                (SELECT COUNT(*) FROM rental_order_lines rol JOIN rental_orders ro ON rol.order_id = ro.id WHERE rol.product_id = p.id AND ro.status != 'cancelled') as order_count
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.vendor_id = ?
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?";
$stmt = $db->prepare($products_sql);
$stmt->bind_param("iii", $vendor_id, $products_per_page, $product_offset);
$stmt->execute();
$vendor_products = $stmt->get_result();

// Get chart data
$monthly_revenue_sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
                        SUM(total_amount) as revenue, COUNT(*) as orders
                        FROM rental_orders 
                        WHERE vendor_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                        ORDER BY month";
$stmt = $db->prepare($monthly_revenue_sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$monthly_revenue = $stmt->get_result();

$status_breakdown_sql = "SELECT status, COUNT(*) as count
                         FROM rental_orders 
                         WHERE vendor_id = ?
                         GROUP BY status";
$stmt = $db->prepare($status_breakdown_sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$status_breakdown = $stmt->get_result();

// Get pending quotations
$quotations_sql = "SELECT rq.*, u.name as customer_name, u.email
                  FROM rental_quotations rq
                  JOIN customers c ON rq.customer_id = c.id
                  JOIN users u ON c.user_id = u.id
                  WHERE rq.vendor_id = ? AND rq.status = 'draft'
                  ORDER BY rq.created_at DESC
                  LIMIT 5";
$stmt = $db->prepare($quotations_sql);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$pending_quotations = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard - Rentify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .order-row:hover {
            background-color: #f9fafb;
        }
        .product-card {
            transition: transform 0.3s ease;
        }
        .product-card:hover {
            transform: scale(1.02);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php require_once '../includes/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Vendor Dashboard</h1>
            <p class="text-gray-600">Manage your rental business and track performance</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Products</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_products']; ?></p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-3">
                        <i class="fas fa-box text-blue-500 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Orders</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_orders']; ?></p>
                    </div>
                    <div class="bg-green-100 rounded-full p-3">
                        <i class="fas fa-shopping-cart text-green-500 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Revenue</p>
                        <p class="text-2xl font-bold text-gray-800">₹<?php echo number_format($stats['total_revenue'], 2); ?></p>
                    </div>
                    <div class="bg-purple-100 rounded-full p-3">
                        <i class="fas fa-rupee-sign text-purple-500 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card bg-white rounded-lg shadow-md p-6 border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Active Orders</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['active_orders']; ?></p>
                    </div>
                    <div class="bg-orange-100 rounded-full p-3">
                        <i class="fas fa-clock text-orange-500 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Revenue Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Revenue Overview</h2>
                <div style="position: relative; height: 300px;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- Order Status Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Order Status Distribution</h2>
                <div style="position: relative; height: 300px;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Quick Actions</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="add-product.php" class="bg-blue-500 hover:bg-blue-600 text-white rounded-lg p-4 text-center transition-colors">
                    <i class="fas fa-plus-circle text-2xl mb-2"></i>
                    <p class="font-medium">Add Product</p>
                </a>
                <a href="quotations.php" class="bg-green-500 hover:bg-green-600 text-white rounded-lg p-4 text-center transition-colors">
                    <i class="fas fa-file-invoice text-2xl mb-2"></i>
                    <p class="font-medium">Create Quotation</p>
                </a>
                <a href="orders.php" class="bg-purple-500 hover:bg-purple-600 text-white rounded-lg p-4 text-center transition-colors">
                    <i class="fas fa-list text-2xl mb-2"></i>
                    <p class="font-medium">View Orders</p>
                </a>
                <a href="earnings.php" class="bg-orange-500 hover:bg-orange-600 text-white rounded-lg p-4 text-center transition-colors">
                    <i class="fas fa-chart-line text-2xl mb-2"></i>
                    <p class="font-medium">View Earnings</p>
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Orders -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Recent Orders</h2>
                    <a href="orders.php" class="text-blue-500 hover:text-blue-600 text-sm">View All</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2 text-gray-600 text-sm">Order #</th>
                                <th class="text-left py-2 text-gray-600 text-sm">Customer</th>
                                <th class="text-left py-2 text-gray-600 text-sm">Amount</th>
                                <th class="text-left py-2 text-gray-600 text-sm">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = $recent_orders->fetch_assoc()): ?>
                            <tr class="order-row border-b">
                                <td class="py-3 text-sm"><?php echo $order['order_number']; ?></td>
                                <td class="py-3 text-sm"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td class="py-3 text-sm">₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td class="py-3">
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        <?php 
                                        $status_colors = [
                                            'draft' => 'bg-gray-100 text-gray-600',
                                            'sent' => 'bg-blue-100 text-blue-600',
                                            'confirmed' => 'bg-green-100 text-green-600',
                                            'in_progress' => 'bg-yellow-100 text-yellow-600',
                                            'completed' => 'bg-purple-100 text-purple-600',
                                            'cancelled' => 'bg-red-100 text-red-600'
                                        ];
                                        echo $status_colors[$order['status']] ?? 'bg-gray-100 text-gray-600';
                                        ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php if ($recent_orders->num_rows === 0): ?>
                        <p class="text-gray-500 text-center py-4">No orders yet</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Quotations -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Pending Quotations</h2>
                    <a href="quotations.php" class="text-blue-500 hover:text-blue-600 text-sm">View All</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2 text-gray-600 text-sm">Quote #</th>
                                <th class="text-left py-2 text-gray-600 text-sm">Customer</th>
                                <th class="text-left py-2 text-gray-600 text-sm">Amount</th>
                                <th class="text-left py-2 text-gray-600 text-sm">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($quote = $pending_quotations->fetch_assoc()): ?>
                            <tr class="order-row border-b">
                                <td class="py-3 text-sm"><?php echo $quote['quotation_number']; ?></td>
                                <td class="py-3 text-sm"><?php echo htmlspecialchars($quote['customer_name']); ?></td>
                                <td class="py-3 text-sm">₹<?php echo number_format($quote['total_amount'], 2); ?></td>
                                <td class="py-3">
                                    <a href="edit-quotation.php?id=<?php echo $quote['id']; ?>" 
                                       class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                                        Send Quote
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php if ($pending_quotations->num_rows === 0): ?>
                        <p class="text-gray-500 text-center py-4">No pending quotations</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Products -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-800">Your Products</h2>
                <a href="products.php" class="text-blue-500 hover:text-blue-600 text-sm">View All</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php while ($product = $vendor_products->fetch_assoc()): ?>
                <div class="product-card border rounded-lg p-4 hover:shadow-lg">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="font-medium text-gray-800"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">
                            <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                        </span>
                    </div>
                    <p class="text-sm text-gray-600 mb-2"><?php echo substr(htmlspecialchars($product['description'] ?? ''), 0, 50) . '...'; ?></p>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-500">Orders: <?php echo $product['order_count']; ?></span>
                        <span class="text-gray-500">Stock: <?php echo $product['quantity_available']; ?></span>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <a href="edit-product.php?id=<?php echo $product['id']; ?>" 
                           class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                            Edit
                        </a>
                        <a href="product-details.php?id=<?php echo $product['id']; ?>" 
                           class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-sm">
                            View
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php if ($vendor_products->num_rows === 0): ?>
                <p class="text-gray-500 text-center py-8">No products added yet. <a href="add-product.php" class="text-blue-500 hover:text-blue-600">Add your first product</a></p>
            <?php endif; ?>
            
            <!-- Products Pagination -->
            <?php if ($total_product_pages > 1): ?>
                <div class="flex justify-center items-center space-x-2 mt-6">
                    <?php if ($current_product_page > 1): ?>
                        <a href="?product_page=<?php echo $current_product_page - 1; ?>" 
                           class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $current_product_page - 2);
                    $end_page = min($total_product_pages, $current_product_page + 2);
                    
                    for ($page = $start_page; $page <= $end_page; $page++): 
                    ?>
                        <a href="?product_page=<?php echo $page; ?>" 
                           class="px-3 py-2 <?php echo $page == $current_product_page ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?> rounded-lg transition-colors">
                            <?php echo $page; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($current_product_page < $total_product_pages): ?>
                        <a href="?product_page=<?php echo $current_product_page + 1; ?>" 
                           class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="text-center text-gray-500 text-sm mt-2">
                    Page <?php echo $current_product_page; ?> of <?php echo $total_product_pages; ?> 
                    (<?php echo $total_products; ?> total products)
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-refresh dashboard data every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);

        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php
                    $labels = [];
                    $revenues = [];
                    while ($month = $monthly_revenue->fetch_assoc()) {
                        $labels[] = date('M Y', strtotime($month['month'] . '-01'));
                        $revenues[] = $month['revenue'] ?? 0;
                    }
                    echo json_encode($labels);
                ?>,
                datasets: [{
                    label: 'Monthly Revenue',
                    data: <?php echo json_encode($revenues); ?>,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: ₹' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusData = <?php
            $status_labels = [];
            $status_counts = [];
            while ($status = $status_breakdown->fetch_assoc()) {
                $status_labels[] = ucfirst(str_replace('_', ' ', $status['status']));
                $status_counts[] = $status['count'];
            }
            echo json_encode(['labels' => $status_labels, 'data' => $status_counts]);
        ?>;
        
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusData.labels,
                datasets: [{
                    data: statusData.data,
                    backgroundColor: [
                        'rgb(156, 163, 175)',
                        'rgb(59, 130, 246)',
                        'rgb(250, 204, 21)',
                        'rgb(251, 146, 60)',
                        'rgb(34, 197, 94)',
                        'rgb(239, 68, 68)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>
