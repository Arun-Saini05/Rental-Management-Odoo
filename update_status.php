<?php
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$order_id = $input['order_id'] ?? null;
$action = $input['action'] ?? null;

if (!$order_id || !$action) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing order_id or action']);
    exit();
}

try {
    // Get current order status
    $current_query = "SELECT status, is_locked FROM rental_orders WHERE id = ?";
    $stmt = mysqli_prepare($conn, $current_query);
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);

    if (!$order) {
        throw new Exception('Order not found');
    }

    $current_status = $order['status'];
    $new_status = $current_status;
    $is_locked = $order['is_locked'];

    // State transition logic
    switch ($action) {
        case 'send':
            if ($current_status !== 'draft') {
                throw new Exception('Only draft orders can be sent');
            }
            $new_status = 'sent';
            break;
            
        case 'confirm':
            if ($current_status !== 'sent') {
                throw new Exception('Only sent orders can be confirmed');
            }
            $new_status = 'confirmed';
            $is_locked = 1; // Lock the order when confirmed
            break;
            
        case 'create_invoice':
            if ($current_status !== 'confirmed') {
                throw new Exception('Only confirmed orders can create invoices');
            }
            // Status remains confirmed, just redirect to invoice creation
            break;
            
        default:
            throw new Exception('Invalid action');
    }

    // Update order status and lock state
    if ($action !== 'create_invoice') {
        $update_query = "UPDATE rental_orders SET status = ?, is_locked = ?, updated_at = NOW() WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "sii", $new_status, $is_locked, $order_id);
        mysqli_stmt_execute($stmt);
    }

    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => getSuccessMessage($action),
        'new_status' => $new_status,
        'is_locked' => $is_locked,
        'redirect_url' => getRedirectUrl($action, $order_id)
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getSuccessMessage($action) {
    switch ($action) {
        case 'send':
            return 'Quotation sent successfully!';
        case 'confirm':
            return 'Order confirmed! Prices and dates are now locked.';
        case 'create_invoice':
            return 'Redirecting to invoice creation...';
        default:
            return 'Action completed successfully!';
    }
}

function getRedirectUrl($action, $order_id) {
    switch ($action) {
        case 'create_invoice':
            return "invoice.php?order_id=$order_id";
        default:
            return null; // No redirect needed for other actions
    }
}
?>
