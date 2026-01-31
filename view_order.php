<?php
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get order ID from URL
$order_id = $_GET['id'] ?? null;

if (!$order_id) {
    header('Location: dashboard.php');
    exit();
}

// Fetch order details
$order_query = "SELECT ro.*, u.name as customer_name, u.email as customer_email, p.name as product_name, p.sales_price as daily_rate
               FROM rental_orders ro 
               LEFT JOIN customers c ON ro.customer_id = c.id 
               LEFT JOIN users u ON c.user_id = u.id
               LEFT JOIN products p ON ro.vendor_id = p.vendor_id
               WHERE ro.id = ?";
$stmt = mysqli_prepare($conn, $order_query);
mysqli_stmt_bind_param($stmt, "i", $order_id);
mysqli_stmt_execute($stmt);
$order_result = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($order_result);

if (!$order) {
    header('Location: dashboard.php');
    exit();
}

// Calculate rental duration
$pickup_date = new DateTime($order['pickup_date']);
$return_date = new DateTime($order['expected_return_date']);
$duration = $pickup_date->diff($return_date)->days;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?php echo htmlspecialchars($order['order_number']); ?> - Rentify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #1a1a1a;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
        }
        
        .order-detail-card {
            background-color: #2d2d2d;
            border: 1px solid #404040;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-draft { background-color: #6b7280; color: white; }
        .status-sent { background-color: #3b82f6; color: white; }
        .status-confirmed { background-color: #10b981; color: white; }
        .status-in_progress { background-color: #f59e0b; color: white; }
        .status-completed { background-color: #8b5cf6; color: white; }
        .status-cancelled { background-color: #ef4444; color: white; }
        
        .btn-primary {
            background-color: #6366f1;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary:hover {
            background-color: #5558e3;
        }
        
        .btn-secondary {
            background-color: #404040;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-secondary:hover {
            background-color: #555555;
        }
        
        .success-message {
            background-color: #10b981;
            color: white;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="min-h-screen p-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center">
                <a href="dashboard.php" class="btn-secondary mr-4">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
                <div>
                    <h1 class="text-3xl font-bold">Order #<?php echo htmlspecialchars($order['order_number']); ?></h1>
                    <p class="text-gray-400">Created on <?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <span class="status-badge status-<?php echo str_replace('_', '-', $order['status']); ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                </span>
                <button class="btn-primary">
                    <i class="fas fa-edit mr-2"></i> Edit Order
                </button>
            </div>
        </div>

        <!-- Success Message -->
        <div class="success-message">
            <i class="fas fa-check-circle mr-3 text-xl"></i>
            <div>
                <strong>Order Created Successfully!</strong>
                <p class="text-sm opacity-90">Your rental order has been created and is now ready for management.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Order Details -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Customer Information -->
                <div class="order-detail-card">
                    <h2 class="text-xl font-semibold mb-4">
                        <i class="fas fa-user mr-2"></i> Customer Information
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-gray-400 text-sm">Name</p>
                            <p class="font-medium"><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm">Email</p>
                            <p class="font-medium"><?php echo htmlspecialchars($order['customer_email'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Product Information -->
                <div class="order-detail-card">
                    <h2 class="text-xl font-semibold mb-4">
                        <i class="fas fa-box mr-2"></i> Product Information
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-gray-400 text-sm">Product Name</p>
                            <p class="font-medium"><?php echo htmlspecialchars($order['product_name'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm">Daily Rate</p>
                            <p class="font-medium text-green-400">$<?php echo number_format($order['daily_rate'] ?? 0, 2); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Rental Period -->
                <div class="order-detail-card">
                    <h2 class="text-xl font-semibold mb-4">
                        <i class="fas fa-calendar mr-2"></i> Rental Period
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-gray-400 text-sm">Pickup Date</p>
                            <p class="font-medium"><?php echo date('M j, Y', strtotime($order['pickup_date'])); ?></p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm">Return Date</p>
                            <p class="font-medium"><?php echo date('M j, Y', strtotime($order['expected_return_date'])); ?></p>
                        </div>
                        <div>
                            <p class="text-gray-400 text-sm">Duration</p>
                            <p class="font-medium"><?php echo $duration; ?> days</p>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <?php if (!empty($order['notes'])): ?>
                <div class="order-detail-card">
                    <h2 class="text-xl font-semibold mb-4">
                        <i class="fas fa-sticky-note mr-2"></i> Notes
                    </h2>
                    <p class="text-gray-300"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar - Pricing Summary -->
            <div class="space-y-6">
                <div class="order-detail-card">
                    <h2 class="text-xl font-semibold mb-4">
                        <i class="fas fa-receipt mr-2"></i> Pricing Summary
                    </h2>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Daily Rate</span>
                            <span>$<?php echo number_format($order['daily_rate'] ?? 0, 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Duration</span>
                            <span><?php echo $duration; ?> days</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Subtotal</span>
                            <span>$<?php echo number_format($order['subtotal'] ?? 0, 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Tax (10%)</span>
                            <span>$<?php echo number_format($order['tax_amount'] ?? 0, 2); ?></span>
                        </div>
                        <div class="border-t border-gray-600 pt-3">
                            <div class="flex justify-between text-lg font-bold">
                                <span>Total</span>
                                <span class="text-green-400">$<?php echo number_format($order['total_amount'] ?? 0, 2); ?></span>
                            </div>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Security Deposit</span>
                            <span>$<?php echo number_format($order['security_deposit_total'] ?? 0, 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Amount Paid</span>
                            <span>$<?php echo number_format($order['amount_paid'] ?? 0, 2); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="order-detail-card">
                    <h2 class="text-xl font-semibold mb-4">
                        <i class="fas fa-bolt mr-2"></i> Quick Actions
                    </h2>
                    <div class="space-y-2">
                        <a href="#" class="btn-primary w-full text-center">
                            <i class="fas fa-file-invoice mr-2"></i> Generate Invoice
                        </a>
                        <a href="#" class="btn-secondary w-full text-center">
                            <i class="fas fa-print mr-2"></i> Print Order
                        </a>
                        <a href="#" class="btn-secondary w-full text-center">
                            <i class="fas fa-envelope mr-2"></i> Send to Customer
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
