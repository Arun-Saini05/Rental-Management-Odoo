<?php
require_once 'config/database.php';
require_once 'config/functions.php';

echo "=== DEBUGGING CART IMAGES ===\n";

// Start session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'customer';

$db = new Database();
$user_id = $_SESSION['user_id'];

// Get customer ID
$customer_sql = "SELECT id FROM customers WHERE user_id = ?";
$stmt = $db->prepare($customer_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

if (!$customer) {
    echo "ERROR: Customer not found\n";
    exit;
}

$customer_id = $customer['id'];
echo "Customer ID: $customer_id\n";

// Get cart items with images
$cart_sql = "SELECT c.*, p.*, cat.name as category_name,
             (SELECT price FROM rental_pricing rp WHERE rp.product_id = p.id AND rp.period_type = 'day' AND rp.is_active = 1 LIMIT 1) as daily_price,
             (SELECT security_deposit FROM rental_pricing rp WHERE rp.product_id = p.id AND rp.period_type = 'day' AND rp.is_active = 1 LIMIT 1) as security_deposit,
             u.name as vendor_name
             FROM cart c
             JOIN products p ON c.product_id = p.id
             LEFT JOIN categories cat ON p.category_id = cat.id
             LEFT JOIN users u ON p.vendor_id = u.id
             WHERE c.customer_id = ?";
$stmt = $db->prepare($cart_sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$cart_items = $stmt->get_result();

echo "Cart items found: " . $cart_items->num_rows . "\n";

if ($cart_items->num_rows > 0) {
    while ($item = $cart_items->fetch_assoc()) {
        echo "\n=== CART ITEM ===\n";
        echo "Product ID: " . $item['id'] . "\n";
        echo "Product Name: " . $item['name'] . "\n";
        echo "Images: " . $item['images'] . "\n";
        
        // Decode images
        $images = json_decode($item['images'] ?? '[]');
        echo "Decoded Images: " . print_r($images, true) . "\n";
        
        if (!empty($images)) {
            $first_image = $images[0];
            $image_path = '../assets/products/' . $first_image;
            echo "Image Path: $image_path\n";
            echo "File Exists: " . (file_exists($image_path) ? 'YES' : 'NO') . "\n";
            
            if (file_exists($image_path)) {
                echo "✅ Image file found\n";
            } else {
                echo "❌ Image file not found\n";
                // Try alternative paths
                $alt_paths = [
                    '../assets/images/' . $first_image,
                    'assets/products/' . $first_image,
                    'assets/images/' . $first_image,
                    $first_image
                ];
                
                foreach ($alt_paths as $path) {
                    echo "Checking: $path - " . (file_exists($path) ? 'FOUND' : 'NOT FOUND') . "\n";
                }
            }
        } else {
            echo "❌ No images found\n";
        }
        
        echo "---\n";
    }
} else {
    echo "No cart items found\n";
}

echo "\n=== CHECKING ASSETS FOLDERS ===\n";
$folders = [
    '../assets/products/',
    '../assets/images/',
    'assets/products/',
    'assets/images/'
];

foreach ($folders as $folder) {
    echo "Folder: $folder - " . (is_dir($folder) ? 'EXISTS' : 'NOT FOUND') . "\n";
    if (is_dir($folder)) {
        $files = scandir($folder);
        echo "  Files: " . count(array_diff($files, ['.', '..'])) . "\n";
    }
}

echo "\n=== DEBUG COMPLETE ===\n";
?>
