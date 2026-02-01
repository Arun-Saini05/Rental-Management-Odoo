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



// Get customer statistics

$stats_sql = "SELECT 

    COUNT(DISTINCT ro.id) as total_orders,

    SUM(CASE WHEN ro.status = 'completed' THEN 1 ELSE 0 END) as completed_orders,

    SUM(CASE WHEN ro.status = 'in_progress' THEN 1 ELSE 0 END) as active_orders,

    SUM(ro.total_amount) as total_spent,

    SUM(ro.amount_paid) as total_paid

    FROM rental_orders ro 

    WHERE ro.customer_id = ?";

$stmt = $db->prepare($stats_sql);

$stmt->bind_param("i", $customer_id);

$stmt->execute();

$stats = $stmt->get_result()->fetch_assoc();



// Get monthly spending for chart

$monthly_sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 

                SUM(total_amount) as spent, COUNT(*) as orders

                FROM rental_orders 

                WHERE customer_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)

                GROUP BY DATE_FORMAT(created_at, '%Y-%m')

                ORDER BY month";

$stmt = $db->prepare($monthly_sql);

$stmt->bind_param("i", $customer_id);

$stmt->execute();

$monthly_spending = $stmt->get_result();



// Get wishlist count

$wishlist_sql = "SELECT COUNT(*) as count FROM wishlist WHERE customer_id = ?";

$stmt = $db->prepare($wishlist_sql);

$stmt->bind_param("i", $customer_id);

$stmt->execute();

$wishlist_count = $stmt->get_result()->fetch_assoc()['count'];



// Get cart count

$cart_sql = "SELECT COUNT(*) as count, SUM(quantity) as total_items 

             FROM cart WHERE customer_id = ?";

$stmt = $db->prepare($cart_sql);

$stmt->bind_param("i", $customer_id);

$stmt->execute();

$cart_data = $stmt->get_result()->fetch_assoc();



// Get recent quotations

$quotations_sql = "SELECT rq.*, u.name as vendor_name

                   FROM rental_quotations rq

                   JOIN users u ON rq.vendor_id = u.id

                   WHERE rq.customer_id = ?

                   ORDER BY rq.created_at DESC

                   LIMIT 3";

$stmt = $db->prepare($quotations_sql);

$stmt->bind_param("i", $customer_id);

$stmt->execute();

$quotations = $stmt->get_result();



// Get user's rental orders



$orders_sql = "SELECT ro.*, COUNT(rol.id) as item_count 



               FROM rental_orders ro 



               LEFT JOIN rental_order_lines rol ON ro.id = rol.order_id 



               WHERE ro.customer_id = ? 



               GROUP BY ro.id 



               ORDER BY ro.created_at DESC 



               LIMIT 5";



$orders_stmt = $db->prepare($orders_sql);



$orders_stmt->bind_param("i", $user_id);



$orders_stmt->execute();



$recent_orders = $orders_stmt->get_result();







// Get order statistics



$stats_sql = "SELECT 



                COUNT(*) as total_orders,



                SUM(total_amount) as total_spent,



                COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as active_rentals,



                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_rentals



              FROM rental_orders 



              WHERE customer_id = ?";



$stats_stmt = $db->prepare($stats_sql);



$stats_stmt->bind_param("i", $user_id);



$stats_stmt->execute();



$stats = $stats_stmt->get_result()->fetch_assoc();



?>







<!DOCTYPE html>



<html lang="en">



<head>



    <meta charset="UTF-8">



    <meta name="viewport" content="width=device-width, initial-scale=1.0">



    <title>Customer Dashboard - Rental Management System</title>



    <script src="https://cdn.tailwindcss.com"></script>



    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">



    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>



</head>



<body class="bg-gray-50">



    <?php include '../includes/navbar.php'; ?>







    <!-- Dashboard Header -->



    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white">



        <div class="container mx-auto px-4 py-8">



            <h1 class="text-3xl font-bold mb-2">Welcome back, <?php echo $_SESSION['user_name']; ?>!</h1>



            <p class="text-blue-100">Manage your rentals and track your orders</p>



        </div>



    </div>







    <!-- Main Content -->



    <div class="container mx-auto px-4 py-8">



        <!-- Statistics Cards -->



        <div class="grid md:grid-cols-4 gap-6 mb-8">



            <div class="bg-white rounded-lg shadow p-6">



                <div class="flex items-center justify-between">



                    <div>



                        <p class="text-gray-600 text-sm">Total Orders</p>



                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_orders']; ?></p>



                    </div>



                    <div class="bg-blue-100 p-3 rounded-full">



                        <i class="fas fa-shopping-bag text-blue-600"></i>



                    </div>



                </div>



            </div>







            <div class="bg-white rounded-lg shadow p-6">



                <div class="flex items-center justify-between">



                    <div>



                        <p class="text-gray-600 text-sm">Total Spent</p>



                        <p class="text-2xl font-bold text-gray-900"><?php echo formatCurrency($stats['total_spent']); ?></p>



                    </div>



                    <div class="bg-green-100 p-3 rounded-full">



                        <i class="fas fa-dollar-sign text-green-600"></i>



                    </div>



                </div>



            </div>







            <div class="bg-white rounded-lg shadow p-6">



                <div class="flex items-center justify-between">



                    <div>



                        <p class="text-gray-600 text-sm">Active Rentals</p>



                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['active_rentals']; ?></p>



                    </div>



                    <div class="bg-orange-100 p-3 rounded-full">



                        <i class="fas fa-clock text-orange-600"></i>



                    </div>



                </div>



            </div>







            <div class="bg-white rounded-lg shadow p-6">



                <div class="flex items-center justify-between">



                    <div>



                        <p class="text-gray-600 text-sm">Completed</p>



                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['completed_rentals']; ?></p>



                    </div>



                    <div class="bg-purple-100 p-3 rounded-full">



                        <i class="fas fa-check-circle text-purple-600"></i>



                    </div>



                </div>



            </div>



        </div>







        <div class="grid lg:grid-cols-3 gap-8">



            <!-- Recent Orders -->



            <div class="lg:col-span-2">



                <div class="bg-white rounded-lg shadow">



                    <div class="p-6 border-b">



                        <div class="flex justify-between items-center">



                            <h2 class="text-xl font-semibold">Recent Orders</h2>



                            <a href="orders.php" class="text-blue-600 hover:text-blue-700">View All</a>



                        </div>



                    </div>



                    <div class="p-6">



                        <?php if ($recent_orders->num_rows > 0): ?>



                            <div class="space-y-4">



                                <?php while ($order = $recent_orders->fetch_assoc()): ?>



                                    <div class="border rounded-lg p-4 hover:bg-gray-50">



                                        <div class="flex justify-between items-start">



                                            <div>



                                                <h3 class="font-medium text-gray-900"><?php echo $order['order_no']; ?></h3>



                                                <p class="text-sm text-gray-600 mt-1">



                                                    <?php echo formatDate($order['created_at']); ?> • 



                                                    <?php echo $order['item_count']; ?> items



                                                </p>



                                                <p class="text-sm text-gray-600">



                                                    Rental Period: <?php echo formatDate($order['pickup_date']); ?> - <?php echo formatDate($order['expected_return_date']); ?>



                                                </p>



                                            </div>



                                            <div class="text-right">



                                                <span class="inline-block px-3 py-1 text-xs rounded-full 



                                                    <?php 



                                                    switch($order['status']) {



                                                        case 'confirmed': echo 'bg-blue-100 text-blue-800'; break;



                                                        case 'in_progress': echo 'bg-orange-100 text-orange-800'; break;



                                                        case 'completed': echo 'bg-green-100 text-green-800'; break;



                                                        default: echo 'bg-gray-100 text-gray-800';



                                                    }



                                                    ?>">



                                                    <?php echo ucfirst($order['status']); ?>



                                                </span>



                                                <p class="font-semibold mt-2"><?php echo formatCurrency($order['total_amount']); ?></p>



                                            </div>



                                        </div>



                                        <div class="mt-4 flex space-x-2">



                                            <a href="order-detail.php?id=<?php echo $order['id']; ?>" 



                                               class="text-blue-600 hover:text-blue-700 text-sm">



                                                <i class="fas fa-eye mr-1"></i>View Details



                                            </a>



                                            <?php if ($order['status'] === 'confirmed'): ?>



                                                <a href="#" class="text-green-600 hover:text-green-700 text-sm">



                                                    <i class="fas fa-download mr-1"></i>Download Pickup Slip



                                                </a>



                                            <?php endif; ?>



                                        </div>



                                    </div>



                                <?php endwhile; ?>



                            </div>



                        <?php else: ?>



                            <div class="text-center py-8">



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







            <!-- Quick Actions & Profile -->



            <div class="space-y-6">



                <!-- Quick Actions -->



                <div class="bg-white rounded-lg shadow p-6">



                    <h2 class="text-xl font-semibold mb-4">Quick Actions</h2>



                    <div class="space-y-3">



                        <a href="../products.php" class="flex items-center p-3 border rounded-lg hover:bg-gray-50">



                            <i class="fas fa-plus-circle text-blue-600 mr-3"></i>



                            <span>Browse Products</span>



                        </a>



                        <a href="orders.php" class="flex items-center p-3 border rounded-lg hover:bg-gray-50">



                            <i class="fas fa-list text-green-600 mr-3"></i>



                            <span>View All Orders</span>



                        </a>



                        <a href="invoices.php" class="flex items-center p-3 border rounded-lg hover:bg-gray-50">



                            <i class="fas fa-file-invoice text-purple-600 mr-3"></i>



                            <span>My Invoices</span>



                        </a>



                        <a href="profile.php" class="flex items-center p-3 border rounded-lg hover:bg-gray-50">



                            <i class="fas fa-user-cog text-orange-600 mr-3"></i>



                            <span>Profile Settings</span>



                        </a>



                    </div>



                </div>







                <!-- Profile Summary -->



                <div class="bg-white rounded-lg shadow p-6">



                    <h2 class="text-xl font-semibold mb-4">Profile Summary</h2>



                    <div class="text-center mb-4">



                        <div class="w-20 h-20 bg-gray-200 rounded-full mx-auto mb-3 flex items-center justify-center">



                            <i class="fas fa-user text-3xl text-gray-400"></i>



                        </div>



                        <h3 class="font-medium text-gray-900"><?php echo $_SESSION['user_name']; ?></h3>



                        <p class="text-sm text-gray-600"><?php echo $_SESSION['user_email']; ?></p>



                    </div>



                    <div class="space-y-2 text-sm">



                        <div class="flex justify-between">



                            <span class="text-gray-600">Member Since</span>



                            <span class="font-medium">Jan 2024</span>



                        </div>



                        <div class="flex justify-between">



                            <span class="text-gray-600">Account Type</span>



                            <span class="font-medium">Customer</span>



                        </div>



                        <div class="flex justify-between">



                            <span class="text-gray-600">Status</span>



                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">Active</span>



                        </div>



                    </div>



                </div>







                <!-- Help & Support -->



                <div class="bg-white rounded-lg shadow p-6">



                    <h2 class="text-xl font-semibold mb-4">Help & Support</h2>



                    <div class="space-y-3">



                        <a href="#" class="flex items-center p-3 border rounded-lg hover:bg-gray-50">



                            <i class="fas fa-question-circle text-blue-600 mr-3"></i>



                            <span>FAQ</span>



                        </a>



                        <a href="#" class="flex items-center p-3 border rounded-lg hover:bg-gray-50">



                            <i class="fas fa-headset text-green-600 mr-3"></i>



                            <span>Contact Support</span>



                        </a>



                        <a href="#" class="flex items-center p-3 border rounded-lg hover:bg-gray-50">



                            <i class="fas fa-book text-purple-600 mr-3"></i>



                            <span>User Guide</span>



                        </a>



                    </div>



                </div>



            </div>



        </div>



    </div>







    <!-- Footer -->



    <footer class="bg-gray-800 text-white py-8 mt-16">



        <div class="container mx-auto px-4">



            <div class="text-center">



                <p>&copy; 2024 RentalHub. All rights reserved.</p>



            </div>



        </div>



    </footer>



    <script>

        // Spending Chart

        const spendingCtx = document.getElementById('spendingChart').getContext('2d');

        const spendingChart = new Chart(spendingCtx, {

            type: 'line',

            data: {

                labels: <?php

                    $labels = [];

                    $amounts = [];

                    while ($month = $monthly_spending->fetch_assoc()) {

                        $labels[] = date('M Y', strtotime($month['month'] . '-01'));

                        $amounts[] = $month['spent'] ?? 0;

                    }

                    echo json_encode($labels);

                ?>,

                datasets: [{

                    label: 'Monthly Spending',

                    data: <?php echo json_encode($amounts); ?>,

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

                                return 'Spent: ₹' + context.parsed.y.toLocaleString();

                            }

                        }

                    }

                }

            }

        });

    </script>

</body>

</html>