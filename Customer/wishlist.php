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

// Handle add to wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_wishlist'])) {
    $product_id = sanitizeInput($_POST['product_id']);
    
    // Check if already in wishlist
    $check_sql = "SELECT id FROM wishlist WHERE customer_id = ? AND product_id = ?";
    $stmt = $db->prepare($check_sql);
    $stmt->bind_param("ii", $customer_id, $product_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        $insert_sql = "INSERT INTO wishlist (customer_id, product_id, created_at) VALUES (?, ?, NOW())";
        $stmt = $db->prepare($insert_sql);
        $stmt->bind_param("ii", $customer_id, $product_id);
        $stmt->execute();
    }
    
    header('Location: wishlist.php?added=1');
    exit();
}

// Handle remove from wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_wishlist'])) {
    $product_id = sanitizeInput($_POST['product_id']);
    
    $delete_sql = "DELETE FROM wishlist WHERE customer_id = ? AND product_id = ?";
    $stmt = $db->prepare($delete_sql);
    $stmt->bind_param("ii", $customer_id, $product_id);
    $stmt->execute();
    
    header('Location: wishlist.php?removed=1');
    exit();
}

// Get wishlist items
$wishlist_sql = "SELECT w.*, p.*, c.name as category_name,
                (SELECT price FROM rental_pricing rp WHERE rp.product_id = p.id AND rp.period_type = 'day' AND rp.is_active = 1 LIMIT 1) as daily_price,
                u.name as vendor_name
                FROM wishlist w
                JOIN products p ON w.product_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN users u ON p.vendor_id = u.id
                WHERE w.customer_id = ?
                ORDER BY w.created_at DESC";
$stmt = $db->prepare($wishlist_sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$wishlist_items = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - Rentify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '../includes/navbar.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">My Wishlist</h1>
            <p class="text-gray-600">Products you've saved for later</p>
        </div>

        <?php if (isset($_GET['added'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                Product added to wishlist!
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['removed'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-trash mr-2"></i>
                Product removed from wishlist!
            </div>
        <?php endif; ?>

        <?php if ($wishlist_items->num_rows > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php while ($item = $wishlist_items->fetch_assoc()): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                        <!-- Product Image -->
                        <div class="relative">
                            <?php 
                            $images = json_decode($item['images'] ?? '[]');
                            $image_url = !empty($images) ? '../assets/images/' . $images[0] : 'https://picsum.photos/seed/' . $item['id'] . '/400/300.jpg';
                            ?>
                            <img src="<?php echo $image_url; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                 class="w-full h-48 object-cover">
                            
                            <!-- Remove Button -->
                            <form method="POST" class="absolute top-2 right-2">
                                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                <button type="submit" name="remove_from_wishlist" 
                                        class="bg-red-500 hover:bg-red-600 text-white p-2 rounded-full shadow-lg">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </div>

                        <!-- Product Details -->
                        <div class="p-4">
                            <h3 class="font-semibold text-gray-800 mb-1"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($item['category_name']); ?></p>
                            <p class="text-sm text-gray-600 mb-2">Vendor: <?php echo htmlspecialchars($item['vendor_name']); ?></p>
                            
                            <?php if ($item['daily_price']): ?>
                                <p class="text-lg font-bold text-blue-600 mb-3">₹<?php echo number_format($item['daily_price'], 2); ?>/day</p>
                            <?php endif; ?>

                            <!-- Action Buttons -->
                            <div class="flex gap-2">
                                <a href="../product-detail.php?id=<?php echo $item['id']; ?>" 
                                   class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded text-sm text-center">
                                    <i class="fas fa-eye mr-1"></i>View
                                </a>
                                <form method="POST" class="flex-1">
                                    <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                    <input type="hidden" name="add_to_cart" value="1">
                                    <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded text-sm">
                                        <i class="fas fa-cart-plus mr-1"></i>Add to Cart
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <i class="fas fa-heart text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Your wishlist is empty</h3>
                <p class="text-gray-600 mb-6">Start adding products you're interested in!</p>
                <a href="../products.php" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg">
                    <i class="fas fa-search mr-2"></i>Browse Products
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Handle add to cart (simplified - would need cart implementation)
        document.querySelectorAll('form').forEach(form => {
            if (form.querySelector('input[name="add_to_cart"]')) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    alert('Cart functionality would be implemented here');
                });
            }
        });
    </script>
</body>
</html>
