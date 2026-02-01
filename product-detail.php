<?php
require_once 'config/database.php';
require_once 'config/functions.php';

if (!isset($_GET['id'])) {
    header('Location: products.php');
    exit();
}

$product_id = $_GET['id'];
$db = new Database();

// Get product details
$sql = "SELECT p.*, c.name as category_name, u.name as vendor_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN users u ON p.vendor_id = u.id 
        WHERE p.id = ? AND p.is_published = 1";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header('Location: products.php');
    exit();
}

// Get rental pricing
$pricing_sql = "SELECT * FROM rental_pricing WHERE product_id = ?";
$pricing_stmt = $db->prepare($pricing_sql);
$pricing_stmt->bind_param("i", $product_id);
$pricing_stmt->execute();
$pricing_result = $pricing_stmt->get_result();

// Get product variants
$variants_sql = "SELECT * FROM product_variants WHERE product_id = ?";
$variants_stmt = $db->prepare($variants_sql);
$variants_stmt->bind_param("i", $product_id);
$variants_stmt->execute();
$variants_result = $variants_stmt->get_result();

// Get related products
$related_sql = "SELECT * FROM products WHERE category_id = ? AND id != ? AND is_published = 1 LIMIT 4";
$related_stmt = $db->prepare($related_sql);
$related_stmt->bind_param("ii", $product['category_id'], $product_id);
$related_stmt->execute();
$related_products = $related_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product['name']; ?> - Rental Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    <!-- Header -->
   
                <!-- Navigation -->
                

                
                  


    <!-- Breadcrumb -->
    <div class="bg-white border-b">
        <div class="container mx-auto px-4 py-3">
            <nav class="flex text-sm">
                <a href="index.php" class="text-gray-600 hover:text-blue-600">Home</a>
                <span class="mx-2 text-gray-400">/</span>
                <a href="products.php" class="text-gray-600 hover:text-blue-600">Products</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-900"><?php echo $product['name']; ?></span>
            </nav>
        </div>
    </div>

    <!-- Product Detail -->
    <div class="container mx-auto px-4 py-8">
        <div class="grid md:grid-cols-2 gap-8">
            <!-- Product Images -->
            <div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="h-96 bg-gray-200 rounded-lg flex items-center justify-center">
                        <?php 
                        // Get product images
                        $images = json_decode($product['images'] ?? '[]');
                        
                        // Debug: Show what we found
                        echo "<!-- Debug: Images found: " . count($images) . " -->";
                        echo "<!-- Debug: Raw images: " . $product['images'] . " -->";
                        echo "<!-- Debug: Decoded: " . print_r($images, true) . " -->";
                        
                        if (!empty($images)) {
                            // Clean up the image path - remove any duplicate assets/ prefixes
                            $first_image = $images[0];
                            echo "<!-- Debug: First image: $first_image -->";
                            
                            // Try different path combinations to find the actual file
                            $possible_paths = [
                                'assets/products/' . $first_image,
                                '../assets/products/' . $first_image,
                                'assets/images/' . $first_image,
                                '../assets/images/' . $first_image,
                                $first_image
                            ];
                            
                            $found_path = null;
                            foreach ($possible_paths as $path) {
                                if (file_exists($path)) {
                                    $found_path = $path;
                                    echo "<!-- Debug: Found at: $path -->";
                                    break;
                                }
                            }
                            
                            if ($found_path) {
                                echo '<img src="' . $found_path . '" alt="' . htmlspecialchars($product['name']) . '" class="w-full h-full object-cover rounded-lg">';
                                echo "<!-- Debug: Using found path: $found_path -->";
                            } else {
                                echo '<img src="https://picsum.photos/seed/' . $product['id'] . '/400/400.jpg" alt="' . htmlspecialchars($product['name']) . '" class="w-full h-full object-cover rounded-lg">';
                                echo "<!-- Debug: Using fallback - no file found -->";
                            }
                        } else {
                            echo '<img src="https://picsum.photos/seed/' . $product['id'] . '/400/400.jpg" alt="' . htmlspecialchars($product['name']) . '" class="w-full h-full object-cover rounded-lg">';
                            echo "<!-- Debug: No images in database -->";
                        }
                        ?>
                    </div>
                    <div class="grid grid-cols-4 gap-2 mt-4">
                        <?php 
                        // Display thumbnail images
                        if (!empty($images)) {
                            foreach ($images as $index => $image) {
                                echo "<!-- Debug: Processing thumbnail $index: $image -->";
                                
                                // Try different path combinations for thumbnails
                                $possible_thumb_paths = [
                                    'assets/products/' . $image,
                                    '../assets/products/' . $image,
                                    'assets/images/' . $image,
                                    '../assets/images/' . $image,
                                    $image
                                ];
                                
                                $found_thumb_path = null;
                                foreach ($possible_thumb_paths as $path) {
                                    if (file_exists($path)) {
                                        $found_thumb_path = $path;
                                        break;
                                    }
                                }
                                
                                echo '<div class="h-20 bg-gray-200 rounded cursor-pointer hover:opacity-75">';
                                if ($found_thumb_path) {
                                    echo '<img src="' . $found_thumb_path . '" alt="Thumbnail ' . ($index + 1) . '" class="w-full h-full object-cover rounded">';
                                    echo "<!-- Debug: Thumb found at: $found_thumb_path -->";
                                } else {
                                    echo '<img src="https://picsum.photos/seed/' . $product['id'] . '_' . $index . '/80/80.jpg" alt="Thumbnail ' . ($index + 1) . '" class="w-full h-full object-cover rounded">';
                                    echo "<!-- Debug: Thumb fallback for: $image -->";
                                }
                                echo '</div>';
                            }
                        } else {
                            // Show placeholder thumbnails
                            for ($i = 0; $i < 4; $i++) {
                                echo '<div class="h-20 bg-gray-200 rounded cursor-pointer hover:opacity-75">';
                                echo '<img src="https://picsum.photos/seed/' . $product['id'] . '_thumb_' . $i . '/80/80.jpg" alt="Thumbnail ' . ($i + 1) . '" class="w-full h-full object-cover rounded">';
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- Product Info -->
            <div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="mb-4">
                        <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">
                            <?php echo $product['category_name']; ?>
                        </span>
                    </div>
                    
                    <h1 class="text-3xl font-bold text-gray-900 mb-4"><?php echo $product['name']; ?></h1>
                    
                    <div class="mb-6">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star text-yellow-400"></i>
                            <i class="fas fa-star-half-alt text-yellow-400"></i>
                            <span class="ml-2 text-gray-600">(4.5) - 24 Reviews</span>
                        </div>
                        <p class="text-gray-600">Vendor: <?php echo $product['vendor_name']; ?></p>
                        <p class="text-gray-600">Stock: <?php echo $product['quantity_on_hand']; ?> units available</p>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-2">Description</h3>
                        <p class="text-gray-700"><?php echo $product['description']; ?></p>
                    </div>

                    <!-- Rental Pricing -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-3">Rental Pricing</h3>
                        <div class="grid grid-cols-3 gap-4">
                            <?php while ($pricing = $pricing_result->fetch_assoc()): ?>
                                <div class="border rounded-lg p-3 text-center hover:border-blue-500 cursor-pointer">
                                    <div class="text-2xl font-bold text-blue-600">
                                        <?php echo formatCurrency($pricing['price']); ?>
                                    </div>
                                    <div class="text-sm text-gray-600">per <?php echo $pricing['period_type']; ?></div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <!-- Product Variants -->
                    <?php if ($variants_result->num_rows > 0): ?>
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-3">Available Variants</h3>
                            <div class="space-y-2">
                                <?php while ($variant = $variants_result->fetch_assoc()): ?>
                                    <div class="border rounded-lg p-3 flex justify-between items-center hover:border-blue-500 cursor-pointer">
                                        <div>
                                            <div class="font-medium"><?php echo $variant['name']; ?></div>
                                            <div class="text-sm text-gray-600">SKU: <?php echo $variant['sku']; ?></div>
                                        </div>
                                        <div class="text-blue-600 font-bold">
                                            <?php echo formatCurrency($variant['sales_price']); ?>/day
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Rental Configuration -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-3">Rental Period</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date & Time</label>
                                <input type="datetime-local" id="start_date" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">End Date & Time</label>
                                <input type="datetime-local" id="end_date" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                            <input type="number" id="quantity" value="1" min="1" max="<?php echo $product['quantity_on_hand']; ?>" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex space-x-4">
                        <button onclick="addToCart(<?php echo $product_id; ?>)" 
                                class="flex-1 bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 font-semibold">
                            <i class="fas fa-cart-plus mr-2"></i>Add to Cart
                        </button>
                        <button onclick="addToWishlist(<?php echo $product_id; ?>)" 
                                class="bg-pink-100 text-pink-600 px-6 py-3 rounded-lg hover:bg-pink-200">
                            <i class="fas fa-heart"></i> Add to Wishlist
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if ($related_products->num_rows > 0): ?>
            <div class="mt-12">
                <h2 class="text-2xl font-bold mb-6">Related Products</h2>
                <div class="grid md:grid-cols-4 gap-6">
                    <?php while ($related = $related_products->fetch_assoc()): ?>
                        <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
                            <div class="h-40 bg-gray-200 rounded-t-lg flex items-center justify-center">
                                <i class="fas fa-box text-4xl text-gray-400"></i>
                            </div>
                            <div class="p-4">
                                <h3 class="font-semibold mb-2"><?php echo $related['name']; ?></h3>
                                <div class="flex justify-between items-center">
                                    <span class="text-blue-600 font-bold"><?php echo formatCurrency($related['sales_price']); ?>/day</span>
                                    <a href="product-detail.php?id=<?php echo $related['id']; ?>" class="text-blue-600 hover:text-blue-700">
                                        View
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; ?>
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
            
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const quantity = document.getElementById('quantity').value;
            
            if (!startDate || !endDate) {
                alert('Please select rental start and end dates');
                return;
            }
            
            if (new Date(startDate) >= new Date(endDate)) {
                alert('End date must be after start date');
                return;
            }
            
            // Check for variants and show dialog if needed
            <?php if ($variants_result->num_rows > 0): ?>
                showVariantDialog(productId, startDate, endDate, quantity);
            <?php else: ?>
                // Add to cart via API
                fetch('/Rental-Odoo/api/cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'add',
                        product_id: productId,
                        quantity: quantity,
                        start_date: startDate,
                        end_date: endDate
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Product added to cart!', 'success');
                        // Update navbar count
                        location.reload();
                    } else {
                        showNotification(data.message || 'Failed to add to cart', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Error adding to cart', 'error');
                });
            <?php endif; ?>
        }
        
        function showVariantDialog(productId, startDate, endDate, quantity) {
            // Create modal for variant selection
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                    <h3 class="text-lg font-semibold mb-4">Select Variant</h3>
                    <div class="space-y-2 mb-4">
                        <?php $variants_result->data_seek(0); while ($variant = $variants_result->fetch_assoc()): ?>
                            <label class="flex items-center justify-between p-3 border rounded cursor-pointer hover:bg-gray-50">
                                <div>
                                    <input type="radio" name="variant" value="<?php echo $variant['id']; ?>" class="mr-2">
                                    <span class="font-medium"><?php echo $variant['name']; ?></span>
                                    <div class="text-sm text-gray-600">SKU: <?php echo $variant['sku']; ?></div>
                                </div>
                                <div class="text-blue-600 font-bold">
                                    <?php echo formatCurrency($variant['sales_price']); ?>/day
                                </div>
                            </label>
                        <?php endwhile; ?>
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="this.closest('.fixed').remove()" class="flex-1 bg-gray-200 text-gray-700 py-2 rounded hover:bg-gray-300">
                            Cancel
                        </button>
                        <button onclick="confirmAddToCart(${productId}, '${startDate}', '${endDate}', ${quantity})" class="flex-1 bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
                            Add to Cart
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        function confirmAddToCart(productId, startDate, endDate, quantity) {
            const selectedVariant = document.querySelector('input[name="variant"]:checked');
            if (!selectedVariant) {
                alert('Please select a variant');
                return;
            }
            
            // Add to cart via API
            fetch('/Rental-Odoo/api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'add',
                    product_id: productId,
                    quantity: quantity,
                    start_date: startDate,
                    end_date: endDate
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Product added to cart!', 'success');
                    document.querySelector('.fixed').remove();
                    // Update navbar count
                    location.reload();
                } else {
                    showNotification(data.message || 'Failed to add to cart', 'error');
                }
            })
            .catch(error => {
                showNotification('Error adding to cart', 'error');
            });
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
        
        // Set minimum date to today
        const today = new Date().toISOString().slice(0, 16);
        document.getElementById('start_date').min = today;
        document.getElementById('end_date').min = today;
    </script>
</body>
</html>
