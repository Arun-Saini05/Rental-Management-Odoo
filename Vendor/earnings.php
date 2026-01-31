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

// Get date range filters
$start_date = sanitizeInput($_GET['start_date'] ?? date('Y-m-01'));
$end_date = sanitizeInput($_GET['end_date'] ?? date('Y-m-d'));

// Overall earnings
$earnings_sql = "SELECT 
    COUNT(*) as total_orders,
    SUM(total_amount) as total_revenue,
    SUM(amount_paid) as total_paid,
    SUM(security_deposit_total) as total_deposits,
    AVG(total_amount) as avg_order_value
    FROM rental_orders 
    WHERE vendor_id = ? AND status != 'cancelled'
    AND created_at BETWEEN ? AND ?";
$stmt = $db->prepare($earnings_sql);
$stmt->bind_param("iss", $vendor_id, $start_date, $end_date);
$stmt->execute();
$earnings = $stmt->get_result()->fetch_assoc();

// Monthly earnings for chart
$monthly_sql = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as orders,
    SUM(total_amount) as revenue,
    SUM(amount_paid) as paid
    FROM rental_orders 
    WHERE vendor_id = ? AND status != 'cancelled'
    AND created_at BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month";
$stmt = $db->prepare($monthly_sql);
$stmt->bind_param("iss", $vendor_id, $start_date, $end_date);
$stmt->execute();
$monthly_data = $stmt->get_result();

// Top products
$products_sql = "SELECT p.name, COUNT(rol.id) as rental_count, 
                SUM(rol.line_total) as total_revenue
                FROM rental_order_lines rol
                JOIN products p ON rol.product_id = p.id
                JOIN rental_orders ro ON rol.order_id = ro.id
                WHERE p.vendor_id = ? AND ro.status != 'cancelled'
                AND ro.created_at BETWEEN ? AND ?
                GROUP BY p.id, p.name
                ORDER BY total_revenue DESC
                LIMIT 10";
$stmt = $db->prepare($products_sql);
$stmt->bind_param("iss", $vendor_id, $start_date, $end_date);
$stmt->execute();
$top_products = $stmt->get_result();

// Recent transactions
$transactions_sql = "SELECT ro.*, u.name as customer_name
                      FROM rental_orders ro
                      JOIN customers c ON ro.customer_id = c.id
                      JOIN users u ON c.user_id = u.id
                      WHERE ro.vendor_id = ? AND ro.amount_paid > 0
                      AND ro.created_at BETWEEN ? AND ?
                      ORDER BY ro.created_at DESC
                      LIMIT 10";
$stmt = $db->prepare($transactions_sql);
$stmt->bind_param("iss", $vendor_id, $start_date, $end_date);
$stmt->execute();
$transactions = $stmt->get_result();

// Status breakdown
$status_sql = "SELECT status, COUNT(*) as count, SUM(total_amount) as amount
               FROM rental_orders 
               WHERE vendor_id = ? AND created_at BETWEEN ? AND ?
               GROUP BY status";
$stmt = $db->prepare($status_sql);
$stmt->bind_param("iss", $vendor_id, $start_date, $end_date);
$stmt->execute();
$status_breakdown = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earnings Dashboard - Rentify</title>
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
        .chart-container {
            position: relative;
            height: 300px;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php require_once '../includes/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Earnings Dashboard</h1>
            <p class="text-gray-600">Track your rental business performance and revenue</p>
        </div>

        <!-- Date Filter -->
        <div class="bg-white rounded-lg shadow-md p-4 mb-8">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" required
                           class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" required
                           class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg">
                    <i class="fas fa-filter mr-2"></i>Filter
                </button>
                <div class="ml-auto">
                    <a href="?start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-d'); ?>" 
                       class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm">
                        This Month
                    </a>
                </div>
            </form>
        </div>

        <!-- Key Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Revenue</p>
                        <p class="text-2xl font-bold text-gray-800">₹<?php echo number_format($earnings['total_revenue'] ?? 0, 2); ?></p>
                    </div>
                    <div class="bg-green-100 rounded-full p-3">
                        <i class="fas fa-rupee-sign text-green-500 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Amount Paid</p>
                        <p class="text-2xl font-bold text-gray-800">₹<?php echo number_format($earnings['total_paid'] ?? 0, 2); ?></p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-3">
                        <i class="fas fa-money-check text-blue-500 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Orders</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $earnings['total_orders'] ?? 0; ?></p>
                    </div>
                    <div class="bg-purple-100 rounded-full p-3">
                        <i class="fas fa-shopping-cart text-purple-500 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card bg-white rounded-lg shadow-md p-6 border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Avg Order Value</p>
                        <p class="text-2xl font-bold text-gray-800">₹<?php echo number_format($earnings['avg_order_value'] ?? 0, 2); ?></p>
                    </div>
                    <div class="bg-orange-100 rounded-full p-3">
                        <i class="fas fa-chart-line text-orange-500 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Revenue Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Revenue Trend</h2>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- Order Status Breakdown -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Order Status Breakdown</h2>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Top Products -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Top Performing Products</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2 text-gray-600 text-sm">Product</th>
                                <th class="text-left py-2 text-gray-600 text-sm">Rentals</th>
                                <th class="text-left py-2 text-gray-600 text-sm">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($product = $top_products->fetch_assoc()): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 text-sm"><?php echo htmlspecialchars($product['name']); ?></td>
                                <td class="py-3 text-sm"><?php echo $product['rental_count']; ?></td>
                                <td class="py-3 text-sm font-medium">₹<?php echo number_format($product['total_revenue'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php if ($top_products->num_rows === 0): ?>
                        <p class="text-gray-500 text-center py-4">No data available</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Recent Transactions</h2>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2 text-gray-600 text-sm">Order #</th>
                                <th class="text-left py-2 text-gray-600 text-sm">Customer</th>
                                <th class="text-left py-2 text-gray-600 text-sm">Amount</th>
                                <th class="text-left py-2 text-gray-600 text-sm">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($transaction = $transactions->fetch_assoc()): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 text-sm"><?php echo $transaction['order_number']; ?></td>
                                <td class="py-3 text-sm"><?php echo htmlspecialchars($transaction['customer_name']); ?></td>
                                <td class="py-3 text-sm font-medium">₹<?php echo number_format($transaction['amount_paid'], 2); ?></td>
                                <td class="py-3 text-sm"><?php echo date('M d, Y', strtotime($transaction['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php if ($transactions->num_rows === 0): ?>
                        <p class="text-gray-500 text-center py-4">No transactions found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Security Deposits -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Security Deposits Summary</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                    <p class="text-gray-500 text-sm">Total Deposits Held</p>
                    <p class="text-2xl font-bold text-orange-600">₹<?php echo number_format($earnings['total_deposits'] ?? 0, 2); ?></p>
                </div>
                <div class="text-center">
                    <p class="text-gray-500 text-sm">Pending Returns</p>
                    <p class="text-2xl font-bold text-yellow-600">
                        <?php 
                        $pending_sql = "SELECT COUNT(*) as count FROM rental_orders 
                                      WHERE vendor_id = ? AND status = 'in_progress'";
                        $stmt = $db->prepare($pending_sql);
                        $stmt->bind_param("i", $vendor_id);
                        $stmt->execute();
                        $pending = $stmt->get_result()->fetch_assoc();
                        echo $pending['count'] ?? 0;
                        ?>
                    </p>
                </div>
                <div class="text-center">
                    <p class="text-gray-500 text-sm">Available for Withdrawal</p>
                    <p class="text-2xl font-bold text-green-600">
                        <?php 
                        $available_sql = "SELECT SUM(security_deposit_total) as amount 
                                        FROM rental_orders 
                                        WHERE vendor_id = ? AND status = 'completed' 
                                        AND actual_return_date IS NOT NULL
                                        AND security_deposit_total > 0";
                        $stmt = $db->prepare($available_sql);
                        $stmt->bind_param("i", $vendor_id);
                        $stmt->execute();
                        $available = $stmt->get_result()->fetch_assoc();
                        echo number_format($available['amount'] ?? 0, 2);
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php
                    $labels = [];
                    $revenues = [];
                    $paid = [];
                    while ($month = $monthly_data->fetch_assoc()) {
                        $labels[] = date('M Y', strtotime($month['month'] . '-01'));
                        $revenues[] = $month['revenue'] ?? 0;
                        $paid[] = $month['paid'] ?? 0;
                    }
                    echo json_encode($labels);
                ?>,
                datasets: [{
                    label: 'Total Revenue',
                    data: <?php echo json_encode($revenues); ?>,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1
                }, {
                    label: 'Amount Paid',
                    data: <?php echo json_encode($paid); ?>,
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.1
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
                                return context.dataset.label + ': ₹' + context.parsed.y.toLocaleString();
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
