<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

if (!isCustomer()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Customer access required']);
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

if (!$customer) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Customer not found']);
    exit();
}

$customer_id = $customer['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get wishlist items
    $wishlist_sql = "SELECT w.*, p.*, c.name as category_name,
                    (SELECT price FROM rental_pricing rp WHERE rp.product_id = p.id AND rp.period_type = 'day' AND rp.is_active = 1 LIMIT 1) as daily_price
                    FROM wishlist w
                    JOIN products p ON w.product_id = p.id
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE w.customer_id = ?
                    ORDER BY w.created_at DESC";
    $stmt = $db->prepare($wishlist_sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $wishlist_items = $stmt->get_result();

    $items = [];
    while ($item = $wishlist_items->fetch_assoc()) {
        $images = json_decode($item['images'] ?? '[]');
        $image_url = !empty($images) ? '../assets/images/' . $images[0] : 'https://picsum.photos/seed/' . $item['id'] . '/200/150.jpg';
        
        $items[] = [
            'id' => $item['id'],
            'name' => $item['name'],
            'category' => $item['category_name'],
            'price' => $item['daily_price'],
            'image' => $image_url,
            'created_at' => $item['created_at']
        ];
    }

    echo json_encode(['success' => true, 'items' => $items]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'add') {
        $product_id = $input['product_id'] ?? 0;
        
        if ($product_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            exit();
        }
        
        // Check if product exists
        $product_sql = "SELECT id FROM products WHERE id = ?";
        $stmt = $db->prepare($product_sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit();
        }
        
        // Check if already in wishlist
        $check_sql = "SELECT id FROM wishlist WHERE customer_id = ? AND product_id = ?";
        $stmt = $db->prepare($check_sql);
        $stmt->bind_param("ii", $customer_id, $product_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Product already in wishlist']);
            exit();
        }
        
        // Add to wishlist
        $insert_sql = "INSERT INTO wishlist (customer_id, product_id) VALUES (?, ?)";
        $stmt = $db->prepare($insert_sql);
        $stmt->bind_param("ii", $customer_id, $product_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Product added to wishlist']);
        
    } elseif ($action === 'remove') {
        $product_id = $input['product_id'] ?? 0;
        
        if ($product_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            exit();
        }
        
        $delete_sql = "DELETE FROM wishlist WHERE customer_id = ? AND product_id = ?";
        $stmt = $db->prepare($delete_sql);
        $stmt->bind_param("ii", $customer_id, $product_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Item removed from wishlist']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
