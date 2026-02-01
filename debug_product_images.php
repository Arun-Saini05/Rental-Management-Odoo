<?php
require_once 'config/database.php';
require_once 'config/functions.php';

echo "=== DEBUGGING PRODUCT IMAGES ===\n";

// Get a sample product to test
$db = new Database();
$product_sql = "SELECT id, name, images FROM products WHERE is_published = 1 LIMIT 5";
$stmt = $db->prepare($product_sql);
$stmt->execute();
$products = $stmt->get_result();

echo "Found " . $products->num_rows . " products\n";

if ($products->num_rows > 0) {
    while ($product = $products->fetch_assoc()) {
        echo "\n=== PRODUCT ID: " . $product['id'] . " ===\n";
        echo "Name: " . $product['name'] . "\n";
        echo "Images: " . $product['images'] . "\n";
        
        // Decode images
        $images = json_decode($product['images'] ?? '[]');
        echo "Decoded Images: " . print_r($images, true) . "\n";
        
        if (!empty($images)) {
            foreach ($images as $index => $image) {
                echo "\n--- IMAGE " . ($index + 1) . " ---\n";
                echo "Original: " . $image . "\n";
                
                // Try different path combinations
                $paths = [
                    'assets/products/' . $image,
                    '../assets/products/' . $image,
                    'assets/images/' . $image,
                    '../assets/images/' . $image,
                    $image
                ];
                
                foreach ($paths as $path) {
                    $exists = file_exists($path);
                    echo "Path: $path - " . ($exists ? 'EXISTS' : 'NOT FOUND') . "\n";
                    
                    if ($exists) {
                        echo "✅ FOUND AT: $path\n";
                        echo "File size: " . filesize($path) . " bytes\n";
                        
                        // Try to display the image
                        echo "HTML: <img src='$path' alt='Product Image' style='max-width: 200px;'><br>\n";
                    }
                }
            }
        } else {
            echo "❌ No images found\n";
        }
        
        echo "\n";
    }
} else {
    echo "No products found\n";
}

echo "\n=== CHECKING ASSETS FOLDERS ===\n";
$folders = [
    'assets/products/',
    '../assets/products/',
    'assets/images/',
    '../assets/images/'
];

foreach ($folders as $folder) {
    echo "Folder: $folder - " . (is_dir($folder) ? 'EXISTS' : 'NOT FOUND') . "\n";
    if (is_dir($folder)) {
        $files = scandir($folder);
        $image_files = array_filter($files, function($file) {
            return !in_array($file, ['.', '..']) && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file);
        });
        
        echo "  Image files: " . count($image_files) . "\n";
        if (!empty($image_files)) {
            echo "  Sample files: " . implode(', ', array_slice($image_files, 0, 3)) . "\n";
        }
    }
}

echo "\n=== CHECKING SPECIFIC FILE ===\n";
$specific_file = 'assets/products/1769895529_WhatsApp Image 2026-02-01 at 2.56.28 AM (1).jpeg';
echo "Looking for: $specific_file\n";
echo "Exists: " . (file_exists($specific_file) ? 'YES' : 'NO') . "\n";

if (file_exists($specific_file)) {
    echo "File size: " . filesize($specific_file) . " bytes\n";
    echo "File type: " . mime_content_type($specific_file) . "\n";
    echo "HTML: <img src='$specific_file' alt='Test Image' style='max-width: 300px;'><br>\n";
} else {
    // Try alternative paths
    $alt_paths = [
        '../assets/products/1769895529_WhatsApp Image 2026-02-01 at 2.56.28 AM (1).jpeg',
        'assets/images/1769895529_WhatsApp Image 2026-02-01 at 2.56.28 AM (1).jpeg',
        '../assets/images/1769895529_WhatsApp Image 2026-02-01 at 2.56.28 AM (1).jpeg'
    ];
    
    foreach ($alt_paths as $path) {
        echo "Trying: $path - " . (file_exists($path) ? 'FOUND' : 'NOT FOUND') . "\n";
        if (file_exists($path)) {
            echo "✅ FOUND AT: $path\n";
            echo "HTML: <img src='$path' alt='Test Image' style='max-width: 300px;'><br>\n";
            break;
        }
    }
}

echo "\n=== DEBUG COMPLETE ===\n";
?>
