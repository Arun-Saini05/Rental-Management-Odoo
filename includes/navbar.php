<?php
// Check if functions are already loaded
if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/../config/functions.php';
}

// Get cart count for customers (only if logged in)
$cart_count = 0;
$cart_items_count = 0;
if (function_exists('isLoggedIn') && isLoggedIn() && !isVendor()) {
    try {
        $db = new Database();
        $user_id = $_SESSION['user_id'] ?? 0;
        
        if ($user_id > 0) {
            // Get customer ID
            $customer_sql = "SELECT id FROM customers WHERE user_id = ?";
            $stmt = $db->prepare($customer_sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $customer = $stmt->get_result()->fetch_assoc();
            
            if ($customer) {
                $customer_id = $customer['id'];
                // Get cart count
                $cart_sql = "SELECT COUNT(*) as count, SUM(quantity) as total_items FROM cart WHERE customer_id = ?";
                $stmt = $db->prepare($cart_sql);
                $stmt->bind_param("i", $customer_id);
                $stmt->execute();
                $cart_data = $stmt->get_result()->fetch_assoc();
                $cart_count = $cart_data['count'] ?? 0;
                $cart_items_count = $cart_data['total_items'] ?? 0;
            }
        }
    } catch (Exception $e) {
        // Silently handle database errors
        $cart_count = 0;
        $cart_items_count = 0;
    }
}
?>

<header class="bg-gray-800 shadow-sm sticky top-0 z-50 border-b border-gray-700">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <!-- Logo -->
            <div class="flex items-center">
                <h1 class="text-2xl font-bold text-white">Rentify</h1>
            </div>

            <!-- Navigation -->
            <nav class="hidden md:flex space-x-8">
                <a href="/Rental-Odoo/products.php" class="text-white font-semibold">Products</a>
                <a href="#" class="text-gray-300 hover:text-white">Terms & Condition</a>
                <a href="#" class="text-gray-300 hover:text-white">About us</a>
                <a href="#" class="text-gray-300 hover:text-white">Contact Us</a>
            </nav>

            <!-- Search and User Actions -->
            <div class="flex items-center space-x-4">
                <!-- Search Bar -->
                <form action="/Rental-Odoo/products.php" method="GET" class="relative hidden md:block">
                    <input type="text" name="search" placeholder="Search products..." 
                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                           class="pl-10 pr-10 py-2 bg-gray-700 text-white border border-gray-600 rounded-lg focus:outline-none focus:border-blue-500">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    <button type="submit" class="absolute right-2 top-2 text-gray-400 hover:text-white">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </form>
                
                <!-- Mobile Search Button -->
                <button onclick="toggleMobileSearch()" class="md:hidden text-gray-300 hover:text-white">
                    <i class="fas fa-search text-xl"></i>
                </button>

                <!-- Icons (Hide for vendors) -->
                <?php if (!isVendor()): ?>
                <button onclick="openWishlistModal()" class="text-gray-300 hover:text-white relative">
                    <i class="fas fa-heart text-xl"></i>
                    <?php 
                    // Get wishlist count
                    $wishlist_sql = "SELECT COUNT(*) as count FROM wishlist WHERE customer_id = ?";
                    $stmt = $db->prepare($wishlist_sql);
                    $stmt->bind_param("i", $customer_id);
                    $stmt->execute();
                    $wishlist_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
                    
                    if ($wishlist_count > 0): ?>
                    <span class="absolute -top-2 -right-2 bg-pink-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs"><?php echo $wishlist_count; ?></span>
                    <?php endif; ?>
                </button>
                <button onclick="openCartModal()" class="text-gray-300 hover:text-white relative">
                    <i class="fas fa-shopping-cart text-xl"></i>
                    <?php if ($cart_items_count > 0): ?>
                    <span class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs"><?php echo $cart_items_count; ?></span>
                    <?php endif; ?>
                </button>
                <?php endif; ?>

                <!-- Auth Buttons (Show if not logged in) -->
                <?php if (!function_exists('isLoggedIn') || !isLoggedIn()): ?>
                <a href="/Rental-Odoo/auth/login.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition">
                    <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                </a>
                <?php endif; ?>

                <!-- User Menu (Show if logged in) -->
                <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                <div class="relative">
                    <button onclick="toggleProfileMenu()" class="text-gray-300 hover:text-white flex items-center space-x-2 px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                        <i class="fas fa-user-circle text-xl"></i>
                        <span class="hidden md:inline"><?php echo $_SESSION['user_name']; ?></span>
                        <i class="fas fa-chevron-down text-sm"></i>
                    </button>
                    
                    <!-- Profile Dropdown Menu -->
                    <div id="profileMenu" class="absolute right-0 mt-2 w-48 bg-gray-800 rounded-lg shadow-lg border border-gray-700 hidden z-50">
                        <div class="py-2">
                            <?php if (isVendor()): ?>
                                <!-- Vendor Menu -->
                                <a href="/Rental-Odoo/Vendor/Dashboard.php" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white transition">
                                    <i class="fas fa-tachometer-alt mr-3"></i>
                                    Vendor Dashboard
                                </a>
                                <a href="/Rental-Odoo/Vendor/add-product.php" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white transition">
                                    <i class="fas fa-plus mr-3"></i>
                                    Add Product
                                </a>
                                <a href="/Rental-Odoo/Vendor/orders.php" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white transition">
                                    <i class="fas fa-shopping-bag mr-3"></i>
                                    Orders
                                </a>
                                <a href="/Rental-Odoo/Vendor/quotations.php" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white transition">
                                    <i class="fas fa-file-invoice mr-3"></i>
                                    Quotations
                                </a>
                                <a href="/Rental-Odoo/Vendor/earnings.php" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white transition">
                                    <i class="fas fa-chart-line mr-3"></i>
                                    Earnings
                                </a>
                            <?php else: ?>
                                <!-- Customer Menu -->
                                <a href="/Rental-Odoo/Customer/Dashboard.php" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white transition">
                                    <i class="fas fa-tachometer-alt mr-3"></i>
                                    Dashboard
                                </a>
                                <a href="/Rental-Odoo/Customer/profile.php" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white transition">
                                    <i class="fas fa-user mr-3"></i>
                                    My Profile
                                </a>
                                <a href="/Rental-Odoo/Customer/orders.php" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white transition">
                                    <i class="fas fa-shopping-bag mr-3"></i>
                                    My Orders
                                </a>
                            <?php endif; ?>
                            <hr class="border-gray-700 my-2">
                            <a href="/Rental-Odoo/auth/logout.php" class="flex items-center px-4 py-2 text-red-400 hover:bg-gray-700 hover:text-red-300 transition">
                                <i class="fas fa-sign-out-alt mr-3"></i>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<!-- Wishlist Modal -->
<div id="wishlistModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[80vh] overflow-hidden">
        <div class="bg-gradient-to-r from-pink-500 to-purple-500 text-white p-4">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold">My Wishlist</h2>
                <button onclick="closeWishlistModal()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        <div id="wishlistContent" class="p-4 overflow-y-auto max-h-[60vh]">
            <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-4xl text-gray-400"></i>
                <p class="text-gray-600 mt-2">Loading wishlist...</p>
            </div>
        </div>
    </div>
</div>

<!-- Cart Modal -->
<div id="cartModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[80vh] overflow-hidden">
        <div class="bg-gradient-to-r from-blue-500 to-green-500 text-white p-4">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold">Shopping Cart</h2>
                <button onclick="closeCartModal()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        <div id="cartContent" class="p-4 overflow-y-auto max-h-[60vh]">
            <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-4xl text-gray-400"></i>
                <p class="text-gray-600 mt-2">Loading cart...</p>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle profile dropdown menu
function toggleProfileMenu() {
    const menu = document.getElementById('profileMenu');
    menu.classList.toggle('hidden');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const menu = document.getElementById('profileMenu');
    const button = event.target.closest('button[onclick="toggleProfileMenu()"]');
    
    if (!button && !menu.contains(event.target)) {
        menu.classList.add('hidden');
    }
});

// Wishlist Modal Functions
function openWishlistModal() {
    document.getElementById('wishlistModal').classList.remove('hidden');
    loadWishlistContent();
}

function closeWishlistModal() {
    document.getElementById('wishlistModal').classList.add('hidden');
}

function loadWishlistContent() {
    fetch('/Rental-Odoo/api/wishlist.php')
        .then(response => response.json())
        .then(data => {
            const content = document.getElementById('wishlistContent');
            if (data.success && data.items.length > 0) {
                let html = '<div class="space-y-4">';
                data.items.forEach(item => {
                    html += `
                        <div class="border rounded-lg p-4 hover:shadow-lg transition-shadow">
                            <div class="flex gap-4">
                                <img src="${item.image}" alt="${item.name}" class="w-24 h-24 object-cover rounded">
                                <div class="flex-grow">
                                    <h3 class="font-semibold text-gray-800">${item.name}</h3>
                                    <p class="text-sm text-gray-600">${item.category}</p>
                                    <p class="text-blue-600 font-semibold">₹${item.price}/day</p>
                                    <div class="mt-3 flex gap-2">
                                        <button onclick="addToCartFromWishlist(${item.id})" class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-1 rounded text-sm">
                                            <i class="fas fa-shopping-cart mr-1"></i>Add to Cart
                                        </button>
                                        <a href="/Rental-Odoo/product-detail.php?id=${item.id}" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                                            <i class="fas fa-eye mr-1"></i>View
                                        </a>
                                        <button onclick="removeFromWishlist(${item.id})" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">
                                            <i class="fas fa-trash mr-1"></i>Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                content.innerHTML = html;
            } else {
                content.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-heart text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2">Your wishlist is empty</h3>
                        <p class="text-gray-600 mb-4">Start adding products you're interested in!</p>
                        <a href="/Rental-Odoo/products.php" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg">
                            Browse Products
                        </a>
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('wishlistContent').innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-6xl text-red-400 mb-4"></i>
                    <p class="text-gray-600">Error loading wishlist</p>
                </div>
            `;
        });
}

function removeFromWishlist(productId) {
    fetch('/Rental-Odoo/api/wishlist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'remove',
            product_id: productId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadWishlistContent();
            // Update navbar count
            location.reload();
        }
    });
}

// Cart Modal Functions
function openCartModal() {
    document.getElementById('cartModal').classList.remove('hidden');
    loadCartContent();
}

function closeCartModal() {
    document.getElementById('cartModal').classList.add('hidden');
}

function loadCartContent() {
    fetch('/Rental-Odoo/api/cart.php')
        .then(response => response.json())
        .then(data => {
            const content = document.getElementById('cartContent');
            if (data.success && data.items.length > 0) {
                let html = '<div class="space-y-4">';
                let subtotal = 0;
                data.items.forEach(item => {
                    subtotal += item.total;
                    html += `
                        <div class="border rounded-lg p-4 hover:bg-gray-50">
                            <div class="flex gap-4">
                                <img src="${item.image}" alt="${item.name}" class="w-20 h-20 object-cover rounded">
                                <div class="flex-grow">
                                    <h3 class="font-semibold">${item.name}</h3>
                                    <p class="text-sm text-gray-600">${item.quantity} × ${item.days} days @ ₹${item.price}/day</p>
                                    <p class="text-blue-600 font-semibold">₹${item.total}</p>
                                    <div class="mt-3 flex gap-2">
                                        <button onclick="updateCartItem(${item.id})" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                                            <i class="fas fa-edit mr-1"></i>Update
                                        </button>
                                        <button onclick="removeFromCart(${item.id})" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">
                                            <i class="fas fa-trash mr-1"></i>Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                html += `
                    <div class="border-t pt-4 mt-4">
                        <div class="flex justify-between items-center mb-4">
                            <span class="font-semibold">Subtotal:</span>
                            <span class="font-bold text-lg">₹${subtotal}</span>
                        </div>
                        <div class="flex gap-2">
                            <a href="/Rental-Odoo/Customer/cart.php" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-center">View Cart</a>
                            <a href="/Rental-Odoo/Customer/checkout.php" class="flex-1 bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-center">Checkout</a>
                        </div>
                    </div>
                `;
                content.innerHTML = html;
            } else {
                content.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2">Your cart is empty</h3>
                        <p class="text-gray-600 mb-4">Add some products to get started!</p>
                        <a href="/Rental-Odoo/products.php" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg">
                            Browse Products
                        </a>
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('cartContent').innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-6xl text-red-400 mb-4"></i>
                    <p class="text-gray-600">Error loading cart</p>
                </div>
            `;
        });
}

function removeFromCart(productId) {
    fetch('/Rental-Odoo/api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'remove',
            product_id: productId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadCartContent();
            // Update navbar count
            location.reload();
        }
    });
}

// Add to Cart from Wishlist
function addToCartFromWishlist(productId) {
    // For now, redirect to product detail page with add to cart parameter
    window.location.href = `/Rental-Odoo/product-detail.php?id=${productId}&add_to_cart=1`;
}

// Update Cart Item
function updateCartItem(productId) {
    // For now, redirect to cart page for editing
    window.location.href = `/Rental-Odoo/Customer/cart.php?edit=${productId}`;
}

// Mobile Search Functions
function toggleMobileSearch() {
    const mobileSearch = document.getElementById('mobileSearch');
    if (mobileSearch.classList.contains('hidden')) {
        mobileSearch.classList.remove('hidden');
        document.getElementById('mobileSearchInput').focus();
    } else {
        mobileSearch.classList.add('hidden');
    }
}

function performMobileSearch() {
    const searchValue = document.getElementById('mobileSearchInput').value;
    if (searchValue.trim()) {
        window.location.href = `/Rental-Odoo/products.php?search=${encodeURIComponent(searchValue)}`;
    }
}
</script>

<!-- Mobile Search Bar -->
<div id="mobileSearch" class="hidden fixed top-16 left-0 right-0 bg-gray-800 border-b border-gray-700 z-40 md:hidden">
    <div class="container mx-auto px-4 py-3">
        <form onsubmit="performMobileSearch(); return false;" class="flex items-center">
            <input type="text" id="mobileSearchInput" placeholder="Search products..." 
                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                   class="flex-1 px-4 py-2 bg-gray-700 text-white border border-gray-600 rounded-l-lg focus:outline-none focus:border-blue-500">
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-r-lg">
                <i class="fas fa-search"></i>
            </button>
            <button type="button" onclick="toggleMobileSearch()" class="ml-2 text-gray-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </form>
    </div>
</div>
