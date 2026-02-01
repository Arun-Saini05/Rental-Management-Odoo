<?php
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

if (!isCustomer()) {
    header('Location: ../index.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: invoices.php');
    exit();
}

$db = new Database();
$user_id = $_SESSION['user_id'];
$invoice_id = $_GET['id'];

// Get customer ID
$customer_sql = "SELECT id FROM customers WHERE user_id = ?";
$stmt = $db->prepare($customer_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$customer_id = $customer['id'];

// Get invoice details
$invoice_sql = "SELECT i.*, ro.order_number, ro.status as order_status, ro.pickup_date, ro.expected_return_date,
                c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
                c.address as customer_address, c.city as customer_city, c.state as customer_state, c.postal_code as customer_postal_code,
                u.name as vendor_name, u.email as vendor_email, u.phone as vendor_phone,
                u.company_name, u.address as vendor_address, u.gstin as vendor_gstin
                FROM invoices i
                JOIN rental_orders ro ON i.order_id = ro.id
                JOIN customers c ON ro.customer_id = c.id
                JOIN users u ON ro.vendor_id = u.id
                WHERE i.id = ? AND ro.customer_id = ?";
$stmt = $db->prepare($invoice_sql);
$stmt->bind_param("ii", $invoice_id, $customer_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if (!$invoice) {
    header('Location: invoices.php');
    exit();
}

// Get order items
$items_sql = "SELECT oi.*, p.name as product_name, p.description as product_description
              FROM order_items oi
              JOIN products p ON oi.product_id = p.id
              WHERE oi.order_id = ?";
$stmt = $db->prepare($items_sql);
$stmt->bind_param("i", $invoice['order_id']);
$stmt->execute();
$items = $stmt->get_result();

// Generate HTML content for PDF
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo $invoice['invoice_no']; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .header { border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 20px; }
        .company-info { float: left; }
        .invoice-info { float: right; text-align: right; }
        .clearfix { clear: both; }
        .addresses { margin: 20px 0; }
        .address-col { width: 48%; float: left; }
        .address-col:first-child { margin-right: 4%; }
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .items-table th { background-color: #f2f2f2; }
        .items-table td:last-child, .items-table th:last-child { text-align: right; }
        .totals { width: 300px; float: right; margin-top: 20px; }
        .total-row { display: flex; justify-content: space-between; margin: 5px 0; }
        .total-row.bold { font-weight: bold; border-top: 2px solid #333; padding-top: 5px; }
        .status { text-align: center; margin-top: 30px; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <h2><?php echo $invoice['company_name'] ?: $invoice['vendor_name']; ?></h2>
            <p><?php echo $invoice['vendor_email']; ?></p>
            <p><?php echo $invoice['vendor_phone']; ?></p>
            <?php if ($invoice['vendor_gstin']): ?>
                <p>GSTIN: <?php echo $invoice['vendor_gstin']; ?></p>
            <?php endif; ?>
        </div>
        <div class="invoice-info">
            <h3>INVOICE</h3>
            <p><strong>#<?php echo $invoice['invoice_no']; ?></strong></p>
            <p>Date: <?php echo date('M j, Y', strtotime($invoice['created_at'])); ?></p>
            <p>Due Date: <?php echo date('M j, Y', strtotime($invoice['due_date'])); ?></p>
        </div>
        <div class="clearfix"></div>
    </div>

    <div class="addresses">
        <div class="address-col">
            <h4>Bill To:</h4>
            <p><strong><?php echo $invoice['customer_name']; ?></strong></p>
            <p><?php echo $invoice['customer_email']; ?></p>
            <p><?php echo $invoice['customer_phone']; ?></p>
            <?php if ($invoice['customer_address']): ?>
                <p><?php echo $invoice['customer_address']; ?></p>
                <p><?php echo $invoice['customer_city']; ?>, <?php echo $invoice['customer_state']; ?> <?php echo $invoice['customer_postal_code']; ?></p>
            <?php endif; ?>
        </div>
        <div class="address-col">
            <h4>From:</h4>
            <p><strong><?php echo $invoice['vendor_name']; ?></strong></p>
            <?php if ($invoice['company_name']): ?>
                <p><?php echo $invoice['company_name']; ?></p>
            <?php endif; ?>
            <p><?php echo $invoice['vendor_email']; ?></p>
            <p><?php echo $invoice['vendor_phone']; ?></p>
            <?php if ($invoice['vendor_address']): ?>
                <p><?php echo $invoice['vendor_address']; ?></p>
            <?php endif; ?>
        </div>
        <div class="clearfix"></div>
    </div>

    <div style="background-color: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 5px;">
        <h4>Rental Period:</h4>
        <p><?php echo date('M j, Y', strtotime($invoice['rental_start_date'])); ?> to <?php echo date('M j, Y', strtotime($invoice['rental_end_date'])); ?></p>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $subtotal = 0;
            while ($item = $items->fetch_assoc()): 
                $item_total = $item['quantity'] * $item['unit_price'];
                $subtotal += $item_total;
            ?>
                <tr>
                    <td>
                        <strong><?php echo $item['product_name']; ?></strong>
                        <?php if ($item['product_description']): ?>
                            <br><small><?php echo $item['product_description']; ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td>₹<?php echo number_format($item['unit_price'], 2); ?></td>
                    <td>₹<?php echo number_format($item_total, 2); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="totals">
        <div class="total-row">
            <span>Subtotal:</span>
            <span>₹<?php echo number_format($subtotal, 2); ?></span>
        </div>
        <div class="total-row">
            <span>Tax (18%):</span>
            <span>₹<?php echo number_format($invoice['tax_amount'], 2); ?></span>
        </div>
        <div class="total-row">
            <span>Security Deposit:</span>
            <span>₹<?php echo number_format($invoice['security_deposit'], 2); ?></span>
        </div>
        <div class="total-row bold">
            <span>Total:</span>
            <span>₹<?php echo number_format($invoice['total_amount'], 2); ?></span>
        </div>
    </div>

    <div class="clearfix"></div>

    <div class="status" style="background-color: <?php
        switch ($invoice['order_status']) {
            case 'completed': echo '#d4edda'; break;
            case 'pending': echo '#fff3cd'; break;
            case 'cancelled': echo '#f8d7da'; break;
            default: echo '#e2e3e5';
        }
    ?>;">
        <strong>Status: <?php echo ucfirst($invoice['order_status']); ?></strong>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="invoice_' . $invoice['invoice_no'] . '.pdf"');

// Convert HTML to PDF using DomPDF (if available) or fallback to HTML
if (file_exists('../vendor/dompdf/dompdf_config.inc.php')) {
    require_once '../vendor/dompdf/dompdf_config.inc.php';
    require_once '../vendor/dompdf/autoload.inc.php';
    
    use Dompdf\Dompdf;
    
    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream('invoice_' . $invoice['invoice_no'] . '.pdf', array('Attachment' => 1));
} else {
    // Fallback: Download as HTML file
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="invoice_' . $invoice['invoice_no'] . '.html"');
    echo $html;
}
?>
