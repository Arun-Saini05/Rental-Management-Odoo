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

// Get invoices for this customer
$invoices_sql = "SELECT i.*, ro.order_number, ro.status as order_status, p.name as product_name
                FROM invoices i
                JOIN rental_orders ro ON i.order_id = ro.id
                LEFT JOIN products p ON ro.id = p.id  -- This might need adjustment based on your schema
                WHERE ro.customer_id = ?
                ORDER BY i.created_at DESC";
$stmt = $db->prepare($invoices_sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$invoices = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Invoices - Rentify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '../includes/navbar.php'; ?>

    <!-- Page Header -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white">
        <div class="container mx-auto px-4 py-8">
            <h1 class="text-3xl font-bold">My Invoices</h1>
            <p class="text-blue-100">View and download your rental invoices</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <?php if ($invoices->num_rows > 0): ?>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($invoice = $invoices->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-medium text-gray-900"><?php echo $invoice['invoice_no']; ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-900"><?php echo $invoice['order_no']; ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-sm text-gray-900"><?php echo $invoice['product_name'] ?: 'Multiple Products'; ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-medium text-gray-900">₹<?php echo number_format($invoice['total_amount'], 2); ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $status_class = '';
                                        switch ($invoice['order_status']) {
                                            case 'completed':
                                                $status_class = 'bg-green-100 text-green-800';
                                                break;
                                            case 'pending':
                                                $status_class = 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'cancelled':
                                                $status_class = 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                $status_class = 'bg-gray-100 text-gray-800';
                                        }
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                            <?php echo ucfirst($invoice['order_status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y', strtotime($invoice['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="view-invoice.php?id=<?php echo $invoice['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="download-invoice.php?id=<?php echo $invoice['id']; ?>" 
                                           class="text-green-600 hover:text-green-900">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow p-8 text-center">
                <i class="fas fa-file-invoice text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Invoices Found</h3>
                <p class="text-gray-500 mb-6">You haven't made any rental orders yet.</p>
                <a href="../products.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    Browse Products
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-16">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; 2024 Rentify. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
