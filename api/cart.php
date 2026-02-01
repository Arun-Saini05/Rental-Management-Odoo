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
    // Get cart items
    $cart_sql = "SELECT c.*, p.*, cat.name as category_name,
                 (SELECT price FROM rental_pricing rp WHERE rp.product_id = p.id AND rp.period_type = 'day' AND rp.is_active = 1 LIMIT 1) as daily_price,
                 (SELECT security_deposit FROM rental_pricing rp WHERE rp.product_id = p.id AND rp.period_type = 'day' AND rp.is_active = 1 LIMIT 1) as security_deposit
                 FROM cart c
                 JOIN products p ON c.product_id = p.id
                 LEFT JOIN categories cat ON p.category_id = cat.id
                 WHERE c.customer_id = ?";
    $stmt = $db->prepare($cart_sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $cart_items = $stmt->get_result();

    $items = [];
    while ($item = $cart_items->fetch_assoc()) {
        if ($item['daily_price']) {
            $days = max(1, (strtotime($item['end_date']) - strtotime($item['start_date'])) / (60 * 60 * 24));
            $item_total = $item['daily_price'] * $item['quantity'] * $days;
            
            $images = json_decode($item['images'] ?? '[]');
            
            // Clean up the image path - remove any duplicate assets/ prefixes
            if (!empty($images)) {
                $first_image = $images[0];
                // Remove any leading ../assets/ or assets/ from the image path
                $clean_image = preg_replace('/^(\.\.\/)?assets\//', '', $first_image);
                $image_url = '../assets/products/' . $clean_image;
                
                // Check if file exists and use fallback if not
                if (!file_exists($image_url)) {
                    $image_url = 'https://picsum.photos/seed/' . $item['id'] . '/200/150.jpg';
                }
            } else {
                $image_url = 'https://picsum.photos/seed/' . $item['id'] . '/200/150.jpg';
            }
            
            $items[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'category' => $item['category_name'],
                'quantity' => $item['quantity'],
                'days' => $days,
                'price' => $item['daily_price'],
                'total' => $item_total,
                'start_date' => $item['start_date'],
                'end_date' => $item['end_date'],
                'image' => $image_url
            ];
        }
    }

    echo json_encode(['success' => true, 'items' => $items]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'add') {
        $product_id = $input['product_id'] ?? 0;
        $quantity = $input['quantity'] ?? 1;
        $start_date = $input['start_date'] ?? '';
        $end_date = $input['end_date'] ?? '';
        
        if ($product_id <= 0 || empty($start_date) || empty($end_date)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
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
        
        // Check if already in cart
        $check_sql = "SELECT id FROM cart WHERE customer_id = ? AND product_id = ?";
        $stmt = $db->prepare($check_sql);
        $stmt->bind_param("ii", $customer_id, $product_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            // Update existing cart item
            $update_sql = "UPDATE cart SET quantity = ?, start_date = ?, end_date = ? WHERE customer_id = ? AND product_id = ?";
            $stmt = $db->prepare($update_sql);
            $stmt->bind_param("issii", $quantity, $start_date, $end_date, $customer_id, $product_id);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Cart item updated']);
        } else {
            // Add new cart item
            $insert_sql = "INSERT INTO cart (customer_id, product_id, quantity, start_date, end_date) VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($insert_sql);
            $stmt->bind_param("iiiss", $customer_id, $product_id, $quantity, $start_date, $end_date);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Product added to cart']);
        }
        
    } elseif ($action === 'remove') {
        $product_id = $input['product_id'] ?? 0;
        
        if ($product_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            exit();
        }
        
        $delete_sql = "DELETE FROM cart WHERE customer_id = ? AND product_id = ?";
        $stmt = $db->prepare($delete_sql);
        $stmt->bind_param("ii", $customer_id, $product_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Item removed from cart']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
