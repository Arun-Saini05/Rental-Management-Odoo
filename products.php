<?php
require_once 'config/database.php';
require_once 'config/functions.php';

// Require login to access products page
if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$db = new Database();

// Get search and filter parameters
$search = sanitizeInput($_GET['search'] ?? '');
$category = sanitizeInput($_GET['category'] ?? '');
$sort = sanitizeInput($_GET['sort'] ?? 'newest');
$brand = sanitizeInput($_GET['brand'] ?? '');
$color = sanitizeInput($_GET['color'] ?? '');
$duration = sanitizeInput($_GET['duration'] ?? '');
$min_price = sanitizeInput($_GET['min_price'] ?? 10);
$max_price = sanitizeInput($_GET['max_price'] ?? 10000);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 8;
$offset = ($page - 1) * $limit;

// Build query
$sql = "SELECT p.*, c.name as category_name, u.name as vendor_name,
               (SELECT price FROM rental_pricing rp WHERE rp.product_id = p.id AND rp.period_type = 'day' AND rp.is_active = 1 LIMIT 1) as daily_price,
               (SELECT price FROM rental_pricing rp WHERE rp.product_id = p.id AND rp.period_type = 'hour' AND rp.is_active = 1 LIMIT 1) as hourly_price,
               (SELECT price FROM rental_pricing rp WHERE rp.product_id = p.id AND rp.period_type = 'week' AND rp.is_active = 1 LIMIT 1) as weekly_price,
               (SELECT price FROM rental_pricing rp WHERE rp.product_id = p.id AND rp.period_type = 'month' AND rp.is_active = 1 LIMIT 1) as monthly_price
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN users u ON p.vendor_id = u.id 
        WHERE p.is_published = 1 AND p.is_rentable = 1";

$params = [];
$types = '';

if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

if ($category) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category;
    $types .= 'i';
}

if ($brand) {
    // Filter by brand attribute
    $sql .= " AND p.id IN (SELECT pa.product_id FROM product_attributes pa 
                JOIN attributes a ON pa.attribute_id = a.id 
                JOIN attribute_values av ON pa.attribute_value_id = av.id 
                WHERE a.name = 'Brand' AND av.value LIKE ?)";
    $brandParam = "%$brand%";
    $params[] = $brandParam;
    $types .= 's';
}

if ($color) {
    // Filter by color attribute
    $sql .= " AND p.id IN (SELECT pa.product_id FROM product_attributes pa 
                JOIN attributes a ON pa.attribute_id = a.id 
                JOIN attribute_values av ON pa.attribute_value_id = av.id 
                WHERE a.name = 'Color' AND av.value = ?)";
    $params[] = $color;
    $types .= 's';
}

if ($min_price && $max_price) {
    $sql .= " AND p.sales_price BETWEEN ? AND ?";
    $params[] = $min_price;
    $params[] = $max_price;
    $types .= 'dd';
}

// Add sorting
switch ($sort) {
    case 'price_low':
        $sql .= " ORDER BY p.sales_price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY p.sales_price DESC";
        break;
    case 'name':
        $sql .= " ORDER BY p.name ASC";
        break;
    default:
        $sql .= " ORDER BY p.created_at DESC";
}

// Get total count for pagination
$count_sql = str_replace("SELECT p.*, c.name as category_name, u.name as vendor_name,
               (SELECT price FROM rental_pricing rp WHERE rp.product_id = p.id AND rp.period_type = 'day' AND rp.is_active = 1 LIMIT 1) as daily_price,
               (SELECT price FROM rental_pricing rp WHERE rp.product_id = p.id AND rp.period_type = 'hour' AND rp.is_active = 1 LIMIT 1) as hourly_price,
               (SELECT price FROM rental_pricing rp WHERE rp.product_id = p.id AND rp.period_type = 'week' AND rp.is_active = 1 LIMIT 1) as weekly_price,
               (SELECT price FROM rental_pricing rp WHERE rp.product_id = p.id AND rp.period_type = 'month' AND rp.is_active = 1 LIMIT 1) as monthly_price", "SELECT COUNT(*) as total", $sql);
$count_sql = str_replace(" ORDER BY p.created_at DESC", "", $count_sql);

$count_stmt = $db->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_products = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_products / $limit);

// Add pagination
$sql .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $db->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

// Get categories for filter
$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");

// Get unique brands from attributes
$brands_sql = "SELECT DISTINCT av.value as brand FROM attribute_values av 
              JOIN attributes a ON av.attribute_id = a.id 
              WHERE a.name = 'Brand' ORDER BY av.value";
$brands = $db->query($brands_sql);

// Get unique colors from attributes
$colors_sql = "SELECT DISTINCT av.value as color FROM attribute_values av 
              JOIN attributes a ON av.attribute_id = a.id 
              WHERE a.name = 'Color' ORDER BY av.value";
$colors_result = $db->query($colors_sql);
$colors = [];
while ($color_row = $colors_result->fetch_assoc()) {
    $colors[] = $color_row['color'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Products - Rentify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
<body class="bg-gray-900 text-white">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- Page Header -->
    <div class="bg-gray-800 border-b border-gray-700">
        <div class="container mx-auto px-4 py-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-white">Browse Products</h1>
                    <p class="text-gray-300 mt-2">Find the perfect rental items for your needs</p>
                </div>
                <div class="flex items-center space-x-4">
                    <span id="productCount" class="text-gray-300 text-sm">
                        <?php echo $total_products; ?> products found
                    </span>
                    <button onclick="refreshProducts()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition">
                        <i class="fas fa-sync-alt"></i>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Filters Sidebar -->
            <div class="lg:w-1/4">
                <div class="bg-gray-800 rounded-lg shadow p-6 border border-gray-700">
                    <h3 class="font-semibold text-lg mb-4">Filters</h3>
                    
                    <!-- Search Form -->
                    <form method="GET" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Search</label>
                            <input type="text" name="search" value="<?php echo $search; ?>" 
                                   placeholder="Search products..."
                                   class="w-full px-3 py-2 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:border-blue-500">
                        </div>

                        <!-- Brand Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Brand</label>
                            <select name="brand" class="w-full px-3 py-2 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:border-blue-500">
                                <option value="">All Brands</option>
                                <?php while ($brand_row = $brands->fetch_assoc()): ?>
                                    <option value="<?php echo $brand_row['brand']; ?>" <?php echo $brand == $brand_row['brand'] ? 'selected' : ''; ?>>
                                        <?php echo $brand_row['brand']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Color Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Color</label>
                            <select name="color" class="w-full px-3 py-2 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:border-blue-500">
                                <option value="">All Colors</option>
                                <?php foreach ($colors as $color_option): ?>
                                    <option value="<?php echo $color_option; ?>" <?php echo $color == $color_option ? 'selected' : ''; ?>>
                                        <?php echo $color_option; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Duration Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Duration</label>
                            <select name="duration" class="w-full px-3 py-2 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:border-blue-500">
                                <option value="">All Durations</option>
                                <option value="hour" <?php echo $duration == 'hour' ? 'selected' : ''; ?>>Per Hour</option>
                                <option value="day" <?php echo $duration == 'day' ? 'selected' : ''; ?>>Per Day</option>
                                <option value="week" <?php echo $duration == 'week' ? 'selected' : ''; ?>>Per Week</option>
                                <option value="month" <?php echo $duration == 'month' ? 'selected' : ''; ?>>Per Month</option>
                            </select>
                        </div>

                        <!-- Price Range Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Price Range</label>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm text-gray-400">
                                    <span>$<?php echo $min_price; ?></span>
                                    <span>$<?php echo $max_price; ?></span>
                                </div>
                                <input type="range" name="max_price" min="10" max="10000" value="<?php echo $max_price; ?>" 
                                       class="w-full" onchange="this.form.submit()">
                            </div>
                        </div>

                        <!-- Category Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Category</label>
                            <select name="category" class="w-full px-3 py-2 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:border-blue-500">
                                <option value="">All Categories</option>
                                <?php while ($cat = $categories->fetch_assoc()): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo $cat['name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Sort -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Sort By</label>
                            <select name="sort" class="w-full px-3 py-2 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:border-blue-500">
                                <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>Name: A to Z</option>
                            </select>
                        </div>

                        <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">
                            Apply Filters
                        </button>
                        <a href="products.php" class="block w-full text-center bg-gray-700 text-gray-300 py-2 rounded-lg hover:bg-gray-600">
                            Clear Filters
                        </a>
                    </form>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="lg:w-3/4">
                <?php if ($products->num_rows > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <?php while ($product = $products->fetch_assoc()): ?>
                            <?php 
                            $is_out_of_stock = ($product['quantity_on_hand'] ?? 0) <= 0;
                            $display_price = $product['daily_price'] ?: $product['sales_price'];
                            $price_period = 'per day';
                            
                            if ($duration == 'hour' && $product['hourly_price']) {
                                $display_price = $product['hourly_price'];
                                $price_period = 'per hour';
                            } elseif ($duration == 'week' && $product['weekly_price']) {
                                $display_price = $product['weekly_price'];
                                $price_period = 'per week';
                            } elseif ($duration == 'month' && $product['monthly_price']) {
                                $display_price = $product['monthly_price'];
                                $price_period = 'per month';
                            }
                            ?>
                            <div class="bg-gray-800 rounded-lg border border-gray-700 hover:border-gray-600 transition-all hover:transform hover:scale-105 <?php echo $is_out_of_stock ? 'opacity-75' : ''; ?>">
                                <div class="h-56 bg-gray-700 rounded-t-lg flex items-center justify-center relative overflow-hidden">
                                    <?php if ($is_out_of_stock): ?>
                                        <div class="absolute inset-0 bg-black bg-opacity-75 flex items-center justify-center z-10">
                                            <span class="text-red-500 font-bold text-xl">Out of stock</span>
                                        </div>
                                    <?php endif; ?>
                                    <i class="fas fa-box text-8xl text-gray-500"></i>
                                    <?php if (($product['quantity_on_hand'] ?? 0) > 0 && ($product['quantity_on_hand'] ?? 0) <= 5): ?>
                                        <span class="absolute top-3 right-3 bg-orange-500 text-white px-3 py-2 text-sm rounded-full font-medium">
                                            Low Stock
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="p-5">
                                    <h3 class="font-semibold text-lg mb-3 text-white line-clamp-2"><?php echo $product['name']; ?></h3>
                                    <div class="flex justify-between items-center mb-3">
                                        <span class="text-blue-400 font-bold text-lg">₹<?php echo number_format($display_price, 0); ?> <?php echo $price_period; ?></span>
                                    </div>
                                    <div class="flex space-x-2">
                                        <a href="product-detail.php?id=<?php echo $product['id']; ?>" 
                                           class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg text-center hover:bg-blue-700 transition-colors font-medium">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="flex justify-center items-center space-x-2 mt-8">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['category']) ? '&category=' . $_GET['category'] : ''; ?><?php echo isset($_GET['brand']) ? '&brand=' . urlencode($_GET['brand']) : ''; ?><?php echo isset($_GET['color']) ? '&color=' . $_GET['color'] : ''; ?><?php echo isset($_GET['duration']) ? '&duration=' . $_GET['duration'] : ''; ?><?php echo isset($_GET['min_price']) ? '&min_price=' . $_GET['min_price'] : ''; ?><?php echo isset($_GET['max_price']) ? '&max_price=' . $_GET['max_price'] : ''; ?><?php echo isset($_GET['sort']) ? '&sort=' . $_GET['sort'] : ''; ?>" 
                                   class="px-3 py-2 bg-gray-700 text-white rounded hover:bg-gray-600">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="px-3 py-2 bg-blue-600 text-white rounded"><?php echo $i; ?></span>
                                <?php elseif ($i <= 3 || $i >= $total_pages - 2 || ($i >= $page - 1 && $i <= $page + 1)): ?>
                                    <a href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['category']) ? '&category=' . $_GET['category'] : ''; ?><?php echo isset($_GET['brand']) ? '&brand=' . urlencode($_GET['brand']) : ''; ?><?php echo isset($_GET['color']) ? '&color=' . $_GET['color'] : ''; ?><?php echo isset($_GET['duration']) ? '&duration=' . $_GET['duration'] : ''; ?><?php echo isset($_GET['min_price']) ? '&min_price=' . $_GET['min_price'] : ''; ?><?php echo isset($_GET['max_price']) ? '&max_price=' . $_GET['max_price'] : ''; ?><?php echo isset($_GET['sort']) ? '&sort=' . $_GET['sort'] : ''; ?>" 
                                       class="px-3 py-2 bg-gray-700 text-white rounded hover:bg-gray-600"><?php echo $i; ?></a>
                                <?php elseif ($i == 4 || $i == $total_pages - 3): ?>
                                    <span class="px-3 py-2 text-gray-400">...</span>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['category']) ? '&category=' . $_GET['category'] : ''; ?><?php echo isset($_GET['brand']) ? '&brand=' . urlencode($_GET['brand']) : ''; ?><?php echo isset($_GET['color']) ? '&color=' . $_GET['color'] : ''; ?><?php echo isset($_GET['duration']) ? '&duration=' . $_GET['duration'] : ''; ?><?php echo isset($_GET['min_price']) ? '&min_price=' . $_GET['min_price'] : ''; ?><?php echo isset($_GET['max_price']) ? '&max_price=' . $_GET['max_price'] : ''; ?><?php echo isset($_GET['sort']) ? '&sort=' . $_GET['sort'] : ''; ?>" 
                                   class="px-3 py-2 bg-gray-700 text-white rounded hover:bg-gray-600">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-search text-6xl text-gray-600 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-400 mb-2">No products found</h3>
                        <p class="text-gray-500">Try adjusting your filters or search terms</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-16">
        <div class="container mx-auto px-4">
            <div class="text-center">
                <p>&copy; 2024 Rentify. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        function addToCart(productId) {
            // Check if user is logged in
            <?php if (!isLoggedIn()): ?>
                if (confirm('Please login to add items to cart. Redirect to login page?')) {
                    window.location.href = 'auth/login.php';
                }
                return;
            <?php endif; ?>
            
            // Show date selection modal
            showCartModal(productId);
        }
        
        function addToWishlist(productId) {
            // Check if user is logged in
            <?php if (!isLoggedIn()): ?>
                if (confirm('Please login to add items to wishlist. Redirect to login page?')) {
                    window.location.href = 'auth/login.php';
                }
                return;
            <?php endif; ?>
            
            // Add to wishlist via API
            fetch('/Rental-Odoo/api/wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'add',
                    product_id: productId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Product added to wishlist!', 'success');
                    // Update navbar count
                    location.reload();
                } else {
                    showNotification(data.message || 'Failed to add to wishlist', 'error');
                }
            })
            .catch(error => {
                showNotification('Error adding to wishlist', 'error');
            });
        }
        
        function showCartModal(productId) {
            // Create modal for date selection
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center';
            modal.innerHTML = `
                <div class="bg-white rounded-lg p-6 w-full max-w-md">
                    <h3 class="text-lg font-semibold mb-4">Select Rental Dates</h3>
                    <form id="cartForm">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                            <input type="date" name="start_date" required min="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                            <input type="date" name="end_date" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                            <input type="number" name="quantity" value="1" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <input type="hidden" name="product_id" value="${productId}">
                        <div class="flex gap-2">
                            <button type="submit" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">Add to Cart</button>
                            <button type="button" onclick="closeCartModal()" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">Cancel</button>
                        </div>
                    </form>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Handle form submission
            document.getElementById('cartForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('/Rental-Odoo/api/cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'add',
                        product_id: formData.get('product_id'),
                        quantity: formData.get('quantity'),
                        start_date: formData.get('start_date'),
                        end_date: formData.get('end_date')
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Product added to cart!', 'success');
                        closeCartModal();
                        // Update navbar count
                        location.reload();
                    } else {
                        showNotification(data.message || 'Failed to add to cart', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Error adding to cart', 'error');
                });
            });
        }
        
        function closeCartModal() {
            const modal = document.querySelector('.fixed.inset-0');
            if (modal) {
                modal.remove();
            }
        }
        
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg text-white ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            }`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
        
        function refreshProducts() {
            // Show loading state
            const button = event.target.closest('button');
            const originalContent = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Refreshing...</span>';
            button.disabled = true;
            
            // Add timestamp to prevent caching
            const timestamp = new Date().getTime();
            const currentUrl = window.location.pathname + window.location.search;
            const urlWithTimestamp = currentUrl + (currentUrl.includes('?') ? '&' : '?') + '_t=' + timestamp;
            
            // Reload the page
            window.location.href = urlWithTimestamp;
        }
        
        // Auto-refresh every 30 seconds (optional)
        setInterval(() => {
            const timestamp = new Date().getTime();
            const currentUrl = window.location.pathname + window.location.search;
            const urlWithTimestamp = currentUrl + (currentUrl.includes('?') ? '&' : '?') + '_t=' + timestamp;
            
            // Check if page is visible (user is actively viewing)
            if (!document.hidden) {
                fetch(urlWithTimestamp, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(response => response.text())
                    .then(html => {
                        // Extract product count from the refreshed page
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newCount = doc.getElementById('productCount');
                        if (newCount && newCount.textContent !== document.getElementById('productCount').textContent) {
                            // Product count changed, show notification
                            showNotification('New products available! Click Refresh to update.', 'success');
                        }
                    })
                    .catch(error => console.log('Auto-refresh check failed:', error));
            }
        }, 30000); // Check every 30 seconds
        
        // Price range slider update
        document.querySelector('input[type="range"]').addEventListener('input', function(e) {
            document.querySelector('.flex.justify-between.text-sm.text-gray-400 span:last-child').textContent = '$' + e.target.value;
        });
    </script>
</body>
</html>
