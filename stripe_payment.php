<?php
require_once 'config/database.php';
require_once 'config/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

requireLogin();

if (!isCustomer()) {
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
    echo json_encode(['success' => false, 'message' => 'Customer not found']);
    exit();
}

$customer_id = $customer['id'];

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $action = $input['action'] ?? '';
    
    if ($action === 'create_payment_intent') {
        try {
            $amount = floatval($input['amount']) * 100; // Convert to cents
            $currency = $input['currency'] ?? 'inr';
            
            // Create payment intent
            $payment_intent = \Stripe\PaymentIntent::create([
                'amount' => $amount,
                'currency' => $currency,
                'automatic_payment_methods' => ['card'],
                'metadata' => [
                    'customer_id' => $customer_id,
                    'integration_check' => 'true'
                ],
                'return_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/order-confirmation.php',
            ]);
            
            echo json_encode([
                'success' => true,
                'client_secret' => $payment_intent->client_secret,
                'payment_intent_id' => $payment_intent->id,
                'amount' => $payment_intent->amount,
                'currency' => $payment_intent->currency
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    } elseif ($action === 'confirm_payment') {
        try {
            $payment_intent_id = $input['payment_intent_id'];
            
            // Retrieve payment intent
            $payment_intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
            
            if ($payment_intent->status === 'succeeded') {
                // Create order with confirmed status
                $order_number = 'ORD-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Get cart items for order
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
                
                // Calculate totals
                $subtotal = 0;
                $security_deposit_total = 0;
                $cart_data = [];
                
                while ($item = $cart_items->fetch_assoc()) {
                    if (isset($item['daily_price']) && $item['daily_price'] > 0) {
                        $days = max(1, (strtotime($item['end_date']) - strtotime($item['start_date'])) / (60 * 60 * 24));
                        $item_total = $item['daily_price'] * ($item['quantity'] ?? 1) * $days;
                        $item_deposit = ($item['security_deposit'] ?? 0) * ($item['quantity'] ?? 1);
                        
                        $subtotal += $item_total;
                        $security_deposit_total += $item_deposit;
                        
                        $processed_item = [
                            'id' => $item['id'] ?? 0,
                            'name' => $item['name'] ?? 'Unknown Product',
                            'category_name' => $item['category_name'] ?? 'Uncategorized',
                            'vendor_name' => $item['vendor_name'] ?? 'Unknown Vendor',
                            'images' => $item['images'] ?? '[]',
                            'quantity' => $item['quantity'] ?? 1,
                            'daily_price' => $item['daily_price'] ?? 0,
                            'security_deposit' => $item['security_deposit'] ?? 0,
                            'days' => $days,
                            'item_total' => $item_total,
                            'item_deposit' => $item_deposit
                        ];
                        
                        $cart_data[] = $processed_item;
                    }
                }
                
                $tax_amount = $subtotal * 0.18; // 18% GST
                $total_amount = $subtotal + $tax_amount;
                
                // Create order with confirmed status
                $order_sql = "INSERT INTO rental_orders (order_number, customer_id, vendor_id, status, 
                              subtotal, tax_amount, total_amount, security_deposit_total, pickup_date, 
                              expected_return_date, notes, stripe_payment_intent_id, created_at) 
                              VALUES (?, ?, ?, 'confirmed', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
                // Group cart items by vendor
                $vendor_orders = [];
                foreach ($cart_data as $item) {
                    $vendor_id = $item['vendor_id'] ?? 1;
                    if (!isset($vendor_orders[$vendor_id])) {
                        $vendor_orders[$vendor_id] = [
                            'items' => [],
                            'subtotal' => 0,
                            'tax_amount' => 0,
                            'total_amount' => 0,
                            'security_deposit_total' => 0
                        ];
                    }
                    $vendor_orders[$vendor_id]['items'][] = $item;
                    $vendor_orders[$vendor_id]['subtotal'] += $item['item_total'];
                    $vendor_orders[$vendor_id]['security_deposit_total'] += $item['item_deposit'];
                }
                
                // Calculate tax and total for each vendor
                foreach ($vendor_orders as $vendor_id => &$order) {
                    $order['tax_amount'] = $order['subtotal'] * 0.18;
                    $order['total_amount'] = $order['subtotal'] + $order['tax_amount'];
                }
                
                // Create orders for each vendor
                foreach ($vendor_orders as $vendor_id => $order_data) {
                    $stmt = $db->prepare($order_sql);
                    $stmt->bind_param("siidddssssssss", $order_number, $customer_id, $vendor_id,
                                      $order_data['subtotal'], $order_data['tax_amount'], $order_data['total_amount'],
                                      $order_data['security_deposit_total'], $pickup_date, 
                                      $return_date, $notes, $payment_intent_id);
                    $stmt->execute();
                    
                    $order_id = $db->getLastId();
                    
                    // Add order lines
                    foreach ($order_data['items'] as $item) {
                        $line_sql = "INSERT INTO rental_order_lines (order_id, product_id, quantity, 
                                    unit_price, rental_days, line_total, security_deposit) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $line_stmt = $db->prepare($line_sql);
                        $line_stmt->bind_param("iididdd", $order_id, $item['id'], $item['quantity'],
                                          $item['daily_price'], $item['days'], $item['item_total'], $item['item_deposit']);
                        $line_stmt->execute();
                    }
                }
                
                // Clear cart
                unset($_SESSION['cart']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Order created successfully',
                    'order_id' => $order_id,
                    'order_number' => $order_number,
                    'total_amount' => $total_amount
                ]);
                
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid action'
                ]);
            }
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
?>
