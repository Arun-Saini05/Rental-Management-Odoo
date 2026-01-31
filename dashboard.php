<?php
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch rental orders from database
$orders_query = "SELECT ro.id, ro.order_number, u.name as customer_name, p.name as product_name, p.sales_price as rental_price, 
                DATEDIFF(ro.expected_return_date, ro.pickup_date) as rental_duration, ro.status
                FROM rental_orders ro 
                LEFT JOIN customers c ON ro.customer_id = c.id 
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN products p ON ro.vendor_id = p.vendor_id
                ORDER BY ro.created_at DESC";
$orders_result = mysqli_query($conn, $orders_query);

// Count orders by status
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rentify Dashboard - Rental Orders</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: #1a1a1a;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
        }
        
        .sidebar {
            background-color: #2d2d2d;
            border-right: 1px solid #404040;
        }
        
        .top-nav {
            background-color: #2d2d2d;
            border-bottom: 1px solid #404040;
        }
        
        .order-card {
            background-color: #2d2d2d;
            border: 1px solid #404040;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }
        
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-draft {
            background-color: #6b7280;
            color: white;
        }
        
        .status-sent {
            background-color: #3b82f6;
            color: white;
        }
        
        .status-confirmed {
            background-color: #10b981;
            color: white;
        }
        
        .status-in-progress {
            background-color: #f59e0b;
            color: white;
        }
        
        .status-completed {
            background-color: #8b5cf6;
            color: white;
        }
        
        .status-cancelled {
            background-color: #ef4444;
            color: white;
        }
        
        .filter-btn {
            background-color: #404040;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-btn:hover {
            background-color: #555555;
        }
        
        .filter-btn.active {
            background-color: #6366f1;
        }
        
        .view-switcher {
            background-color: #404040;
            padding: 4px;
            border-radius: 6px;
        }
        
        .view-switcher button {
            padding: 6px 10px;
            border: none;
            background: transparent;
            color: #999;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .view-switcher button.active {
            background-color: #6366f1;
            color: white;
        }
        
        .dropdown {
            position: relative;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #2d2d2d;
            border: 1px solid #404040;
            border-radius: 6px;
            z-index: 1000;
            min-width: 160px;
            right: 0;
        }
        
        .dropdown-content.show {
            display: block;
        }
        
        .search-bar {
            background-color: #404040;
            border: 1px solid #555555;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            width: 300px;
        }
        
        .search-bar::placeholder {
            color: #999;
        }
        
        .new-btn {
            background-color: #ec4899;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .new-btn:hover {
            background-color: #db2777;
        }
        
        .export-import-btn {
            background-color: #404040;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 12px;
            margin-right: 8px;
            transition: all 0.3s ease;
        }
        
        .export-import-btn:hover {
            background-color: #555555;
        }
    </style>
</head>
<body>
    <div class="flex h-screen overflow-hidden">
        <!-- Left Sidebar -->
        <div class="sidebar w-64 h-full flex flex-col">
            <div class="p-4">
                <h2 class="text-lg font-semibold mb-6">Orders Menu</h2>
                <nav class="space-y-2">
                    <a href="#" class="flex items-center p-3 rounded-lg bg-indigo-600 text-white">
                        <i class="fas fa-shopping-cart mr-3"></i>
                        Orders
                    </a>
                    <a href="#" class="flex items-center p-3 rounded-lg hover:bg-gray-700 text-gray-300">
                        <i class="fas fa-file-invoice mr-3"></i>
                        Invoices
                    </a>
                    <a href="#" class="flex items-center p-3 rounded-lg hover:bg-gray-700 text-gray-300">
                        <i class="fas fa-users mr-3"></i>
                        Customers
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col">
            <!-- Top Navigation -->
            <header class="top-nav px-6 py-4">
                <div class="flex items-center justify-between">
                    <!-- Logo and Nav -->
                    <div class="flex items-center space-x-8">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center mr-2">
                                <i class="fas fa-cube text-white text-sm"></i>
                            </div>
                            <span class="font-bold text-lg">Your Logo</span>
                        </div>
                        <nav class="flex space-x-6">
                            <a href="#" class="text-white font-medium">Orders</a>
                            <a href="#" class="text-gray-400 hover:text-white">Products</a>
                            <a href="#" class="text-gray-400 hover:text-white">Reports</a>
                            <a href="#" class="text-gray-400 hover:text-white">Settings</a>
                        </nav>
                    </div>

                    <!-- Search and User Profile -->
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <input type="text" placeholder="Search..." class="search-bar pl-10">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                        
                        <!-- View Switcher -->
                        <div class="view-switcher">
                            <button class="active" title="Grid View">
                                <i class="fas fa-th"></i>
                            </button>
                            <button title="List View">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>

                        <!-- User Profile Dropdown -->
                        <div class="dropdown" id="userDropdown">
                            <button class="flex items-center space-x-2 text-white" onclick="toggleDropdown()">
                                <i class="fas fa-user-circle text-xl"></i>
                                <span><?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Admin'; ?></span>
                                <i class="fas fa-chevron-down text-xs"></i>
                            </button>
                            <div class="dropdown-content" id="dropdownContent">
                                <a href="#" class="block px-4 py-2 hover:bg-gray-700">Profile</a>
                                <a href="logout.php" class="block px-4 py-2 hover:bg-gray-700">Logout</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Dashboard Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- Export/Import Records -->
                <div class="mb-6">
                    <a href="export.php?type=orders&format=csv" class="export-import-btn">
                        <i class="fas fa-download mr-1"></i> Export CSV
                    </a>
                    <a href="export.php?type=orders&format=excel" class="export-import-btn">
                        <i class="fas fa-file-excel mr-1"></i> Export Excel
                    </a>
                    <button class="export-import-btn" onclick="showImportModal()">
                        <i class="fas fa-upload mr-1"></i> Import Records
                    </button>
                    <span class="text-gray-400 text-sm ml-2">For CSV, excel imports & exports.</span>
                </div>

                <!-- Rental Order Section -->
                <div class="flex gap-6">
                    <!-- Left Sidebar - Status Summary -->
                    <div class="w-64">
                        <div class="bg-gray-800 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-semibold">Rental Status</h3>
                                <i class="fas fa-cog text-gray-400"></i>
                            </div>
                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span>Total:</span>
                                    <span class="font-bold"><?php echo $status_counts['total']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Draft:</span>
                                    <span class="font-bold text-gray-400"><?php echo $status_counts['draft']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Sent:</span>
                                    <span class="font-bold text-blue-400"><?php echo $status_counts['sent']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Confirmed:</span>
                                    <span class="font-bold text-green-400"><?php echo $status_counts['confirmed']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>In Progress:</span>
                                    <span class="font-bold text-yellow-400"><?php echo $status_counts['in_progress']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Completed:</span>
                                    <span class="font-bold text-purple-400"><?php echo $status_counts['completed']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Cancelled:</span>
                                    <span class="font-bold text-red-400"><?php echo $status_counts['cancelled']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content - Kanban Board -->
                    <div class="flex-1">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-2xl font-bold">Rental Order</h2>
                            <a href="new_order.php" class="new-btn">
                                <i class="fas fa-plus mr-2"></i> New
                            </a>
                        </div>

                        <!-- Filter Buttons -->
                        <div class="flex gap-4 mb-6">
                            <button class="filter-btn" id="pickup-filter">
                                <i class="fas fa-truck mr-2"></i> Pickup
                                <small class="block text-xs text-gray-400">Invoiced & paid orders</small>
                            </button>
                            <button class="filter-btn" id="return-filter">
                                <i class="fas fa-undo mr-2"></i> Return
                                <small class="block text-xs text-gray-400">Return dates approaching/passed</small>
                            </button>
                        </div>

                        <!-- Kanban Cards Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="orders-container">
                            <?php if (empty($orders)): ?>
                                <div class="col-span-full text-center py-12 text-gray-400">
                                    <i class="fas fa-inbox text-4xl mb-4"></i>
                                    <p>No rental orders found</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <div class="order-card" data-status="<?php echo $order['status']; ?>">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="font-semibold text-lg">
                                                <?php echo htmlspecialchars($order['customer_name'] ?? 'Unknown Customer'); ?>
                                            </h4>
                                            <span class="status-badge status-<?php echo str_replace('_', '-', $order['status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="text-sm text-gray-400 mb-3">
                                            Order ID: <?php echo htmlspecialchars($order['order_number'] ?? $order['id']); ?>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="font-medium"><?php echo htmlspecialchars($order['product_name'] ?? 'Unknown Product'); ?></div>
                                            <div class="text-lg font-bold text-green-400">
                                                $<?php echo number_format($order['rental_price'] ?? 0, 2); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="text-sm text-gray-400">
                                            <i class="fas fa-calendar mr-2"></i>
                                            Rental Duration: <?php echo htmlspecialchars($order['rental_duration'] ?? 'Not specified'); ?> days
                                        </div>
                                        
                                        <div class="mt-3 flex gap-2">
                                            <button class="text-xs bg-gray-700 hover:bg-gray-600 px-2 py-1 rounded">
                                                <i class="fas fa-edit mr-1"></i> Edit
                                            </button>
                                            <button class="text-xs bg-gray-700 hover:bg-gray-600 px-2 py-1 rounded">
                                                <i class="fas fa-eye mr-1"></i> View
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Toggle dropdown on click
        function toggleDropdown() {
            const dropdown = document.getElementById('dropdownContent');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            const dropdownContent = document.getElementById('dropdownContent');
            
            if (!dropdown.contains(event.target)) {
                dropdownContent.classList.remove('show');
            }
        });

        // Export orders to CSV
        function exportOrders() {
            window.location.href = 'export_orders.php';
        }

        // Import orders from file
        function importOrders(event) {
            const file = event.target.files[0];
            if (file) {
                const formData = new FormData();
                formData.append('importFile', file);
                
                fetch('import_orders.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Orders imported successfully!');
                        location.reload();
                    } else {
                        alert('Error importing orders: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error importing orders: ' + error);
                });
            }
        }

        // Filter functionality
        document.getElementById('pickup-filter').addEventListener('click', function() {
            this.classList.toggle('active');
            filterOrders('pickup');
        });

        document.getElementById('return-filter').addEventListener('click', function() {
            this.classList.toggle('active');
            filterOrders('return');
        });

        function filterOrders(type) {
            const cards = document.querySelectorAll('.order-card');
            cards.forEach(card => {
                const status = card.dataset.status;
                if (type === 'pickup') {
                    card.style.display = status === 'completed' ? 'block' : 'none';
                } else if (type === 'return') {
                    // Show orders with approaching/passed return dates
                    card.style.display = 'block'; // Simplified for demo
                }
            });
        }

        // View switcher
        document.querySelectorAll('.view-switcher button').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.view-switcher button').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const container = document.getElementById('orders-container');
                if (this.querySelector('i').classList.contains('fa-list')) {
                    container.className = 'space-y-4';
                } else {
                    container.className = 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4';
                }
            });
        });

        // Search functionality
        document.querySelector('.search-bar').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.order-card');
            
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });
        });

        // Dropdown click functionality
        const dropdown = document.querySelector('.dropdown');
        const dropdownButton = dropdown.querySelector('button');
        
        dropdownButton.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            dropdown.classList.remove('show');
        });
        
        // Prevent dropdown from closing when clicking inside
        dropdown.querySelector('.dropdown-content').addEventListener('click', function(e) {
            e.stopPropagation();
        });

        // Import modal functionality
        function showImportModal() {
            window.location.href = 'import.php';
        }
    </script>
</body>
</html>
