<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/functions.php';

session_start();

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$quotation_id = $data['quotation_id'] ?? 0;
$payment_method = $data['payment_method'] ?? '';

if (empty($quotation_id) || empty($payment_method)) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

$db = new Database();

try {
    // Start transaction
    $db->conn->begin_transaction();

    // Get quotation details
    $sql = "SELECT * FROM rental_quotations WHERE id = ? AND customer_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ii", $quotation_id, $_SESSION['user_id']);
    $stmt->execute();
    $quotation = $stmt->get_result()->fetch_assoc();

    if (!$quotation) {
        throw new Exception('Quotation not found');
    }

    // Create rental order
    $order_no = generateOrderNo();
    $order_sql = "INSERT INTO rental_orders (order_number, quotation_id, customer_id, status, subtotal, tax_amount, total_amount, security_deposit_total, pickup_date, expected_return_date) 
                  VALUES (?, ?, ?, 'confirmed', ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))";
    $security_deposit = $quotation['security_deposit_total'] ?: ($quotation['total_amount'] * 0.20);
    $order_stmt = $db->prepare($order_sql);
    $order_stmt->bind_param("siidddd", $order_no, $quotation_id, $_SESSION['user_id'], $quotation['subtotal'], $quotation['tax_amount'], $quotation['total_amount'], $security_deposit);
    $order_stmt->execute();
    $order_id = $db->getLastId();

    // Copy quotation lines to order lines
    $lines_sql = "SELECT * FROM rental_quotation_lines WHERE quotation_id = ?";
    $lines_stmt = $db->prepare($lines_sql);
    $lines_stmt->bind_param("i", $quotation_id);
    $lines_stmt->execute();
    $quotation_lines = $lines_stmt->get_result();

    while ($line = $quotation_lines->fetch_assoc()) {
        $order_line_sql = "INSERT INTO rental_order_lines (order_id, product_id, variant_id, quantity, rental_start_date, rental_end_date, unit_price, line_total, security_deposit) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $order_line_stmt = $db->prepare($order_line_sql);
        $order_line_stmt->bind_param("iiisssddd", $order_id, $line['product_id'], $line['variant_id'], $line['quantity'], $line['rental_start_date'], $line['rental_end_date'], $line['unit_price'], $line['line_total'], $line['security_deposit']);
        $order_line_stmt->execute();

        // Update product quantity (reserve stock)
        $update_sql = "UPDATE products SET quantity_reserved = quantity_reserved + ? WHERE id = ?";
        $update_stmt = $db->prepare($update_sql);
        $update_stmt->bind_param("ii", $line['quantity'], $line['product_id']);
        $update_stmt->execute();
    }

    // Create invoice
    $invoice_no = generateInvoiceNo();
    $invoice_sql = "INSERT INTO invoices (invoice_number, order_id, customer_id, status, subtotal, tax_amount, total_amount, security_deposit_amount, due_date) 
                    VALUES (?, ?, ?, 'sent', ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))";
    $invoice_stmt = $db->prepare($invoice_sql);
    $invoice_stmt->bind_param("siidddd", $invoice_no, $order_id, $_SESSION['user_id'], $quotation['subtotal'], $quotation['tax_amount'], $quotation['total_amount'], $security_deposit);
    $invoice_stmt->execute();
    $invoice_id = $db->getLastId();

    // Create payment record
    $payment_no = 'PAY' . time() . rand(1000, 9999);
    $payment_sql = "INSERT INTO payments (payment_number, invoice_id, customer_id, amount, payment_method, payment_status, transaction_id, payment_date) 
                    VALUES (?, ?, ?, ?, ?, 'completed', ?, NOW())";
    $transaction_id = 'TXN' . time() . rand(1000, 9999);
    $payment_stmt = $db->prepare($payment_sql);
    $payment_stmt->bind_param("sidsss", $payment_no, $invoice_id, $_SESSION['user_id'], $quotation['total_amount'], $payment_method, $transaction_id);
    $payment_stmt->execute();

    // Update quotation status
    $update_quotation_sql = "UPDATE rental_quotations SET status = 'confirmed' WHERE id = ?";
    $update_quotation_stmt = $db->prepare($update_quotation_sql);
    $update_quotation_stmt->bind_param("i", $quotation_id);
    $update_quotation_stmt->execute();

    // Commit transaction
    $db->conn->commit();

    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'order_no' => $order_no,
        'invoice_no' => $invoice_no,
        'message' => 'Payment processed successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction
    $db->conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
