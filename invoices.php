<?php
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$invoice_id = $_GET['id'] ?? null;
$invoice = null;
$current_status = 'draft';
$is_locked = false;

if ($invoice_id) {
    $query = "SELECT i.*, u.name as customer_name, u.email as customer_email, u.address as customer_address
              FROM invoices i 
              LEFT JOIN customers c ON i.customer_id = c.id 
              LEFT JOIN users u ON c.user_id = u.id
              WHERE i.id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $invoice_id);
    mysqli_stmt_execute($stmt);
    $invoice = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($invoice) {
        $current_status = $invoice['status'];
        $is_locked = ($current_status !== 'draft');
    }
}

$pre_order_id = $_GET['order_id'] ?? null;
$pre_customer_id = null;
if ($pre_order_id && !$invoice_id) {
    $q = "SELECT customer_id FROM rental_orders WHERE id = ?";
    $stmt = mysqli_prepare($conn, $q);
    mysqli_stmt_bind_param($stmt, "i", $pre_order_id);
    mysqli_stmt_execute($stmt);
    $pre_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($pre_row) $pre_customer_id = $pre_row['customer_id'];
}

// Data for dropdowns
$customers_result = mysqli_query($conn, "SELECT c.id, u.name, u.email FROM customers c LEFT JOIN users u ON c.user_id = u.id ORDER BY u.name");
$orders_result = mysqli_query($conn, "SELECT id, order_number FROM rental_orders ORDER BY created_at DESC");
$products_result = mysqli_query($conn, "SELECT id, name, sales_price FROM products ORDER BY name");

$invoice_number = $invoice['invoice_number'] ?? 'INV-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_invoice') {
        // (Logic from original invoices.php preserved)
        $customer_id = sanitize($_POST['customer_id']);
        $order_id = sanitize($_POST['order_id']);
        $subtotal = 0;
        if (isset($_POST['line_total'])) foreach ($_POST['line_total'] as $t) $subtotal += floatval($t);
        $tax = $subtotal * 0.10;
        $total = $subtotal + $tax;
        
        if ($invoice_id) {
            $q = "UPDATE invoices SET customer_id = ?, order_id = ?, total_amount = ? WHERE id = ? AND status = 'draft'";
            $stmt = mysqli_prepare($conn, $q);
            mysqli_stmt_bind_param($stmt, "siid", $customer_id, $order_id, $total, $invoice_id);
            mysqli_stmt_execute($stmt);
            mysqli_query($conn, "DELETE FROM invoice_lines WHERE invoice_id = $invoice_id");
        } else {
            $q = "INSERT INTO invoices (invoice_number, customer_id, order_id, total_amount, status, created_at) VALUES (?, ?, ?, ?, 'draft', NOW())";
            $stmt = mysqli_prepare($conn, $q);
            mysqli_stmt_bind_param($stmt, "siid", $invoice_number, $customer_id, $order_id, $total);
            mysqli_stmt_execute($stmt);
            $invoice_id = mysqli_insert_id($conn);
        }
        
        if (isset($_POST['product_id'])) {
            foreach ($_POST['product_id'] as $k => $pid) {
                $qty = floatval($_POST['quantity'][$k]);
                $price = floatval($_POST['unit_price'][$k]);
                $line_t = floatval($_POST['line_total'][$k]);
                $desc = sanitize($_POST['product_name'][$k]);
                $iq = "INSERT INTO invoice_lines (invoice_id, description, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $iq);
                mysqli_stmt_bind_param($stmt, "isddd", $invoice_id, $desc, $qty, $price, $line_t);
                mysqli_stmt_execute($stmt);
            }
        }
        header('Location: invoices.php?id=' . $invoice_id . '&saved=1');
        exit();
    } elseif ($action === 'confirm') {
        // First save/update the invoice
        $customer_id = sanitize($_POST['customer_id']);
        $order_id = sanitize($_POST['order_id']);
        $subtotal = 0;
        if (isset($_POST['line_total'])) foreach ($_POST['line_total'] as $t) $subtotal += floatval($t);
        $tax = $subtotal * 0.10;
        $total = $subtotal + $tax;
        
        if ($invoice_id) {
            $q = "UPDATE invoices SET customer_id = ?, order_id = ?, total_amount = ?, status = 'sent' WHERE id = ? AND status = 'draft'";
            $stmt = mysqli_prepare($conn, $q);
            mysqli_stmt_bind_param($stmt, "siid", $customer_id, $order_id, $total, $invoice_id);
            mysqli_stmt_execute($stmt);
            mysqli_query($conn, "DELETE FROM invoice_lines WHERE invoice_id = $invoice_id");
        } else {
            $q = "INSERT INTO invoices (invoice_number, customer_id, order_id, total_amount, status, created_at) VALUES (?, ?, ?, ?, 'sent', NOW())";
            $stmt = mysqli_prepare($conn, $q);
            mysqli_stmt_bind_param($stmt, "siid", $invoice_number, $customer_id, $order_id, $total);
            mysqli_stmt_execute($stmt);
            $invoice_id = mysqli_insert_id($conn);
        }

        // Save lines
        if (isset($_POST['product_id'])) {
            foreach ($_POST['product_id'] as $k => $pid) {
                $qty = floatval($_POST['quantity'][$k]);
                $price = floatval($_POST['unit_price'][$k]);
                $line_t = floatval($_POST['line_total'][$k]);
                $desc = sanitize($_POST['product_name'][$k]);
                $iq = "INSERT INTO invoice_lines (invoice_id, description, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $iq);
                mysqli_stmt_bind_param($stmt, "isddd", $invoice_id, $desc, $qty, $price, $line_t);
                mysqli_stmt_execute($stmt);
            }
        }
        header('Location: invoices.php?id=' . $invoice_id . '&confirmed=1');
        exit();
    } elseif ($action === 'send' && $invoice_id) {
        header('Location: invoices.php?id=' . $invoice_id . '&sent=1');
        exit();
    }
}

$invoice_items = [];
if ($invoice_id) {
    $ir = mysqli_query($conn, "SELECT * FROM invoice_lines WHERE invoice_id = $invoice_id ORDER BY id");
    while ($it = mysqli_fetch_assoc($ir)) $invoice_items[] = $it;
}

$page_title = $invoice_id ? "Bill " . $invoice_number : "Draft Fiscal Statement";
include 'header.php';
?>

<div class="h-screen overflow-hidden flex flex-col bg-primary">
    <!-- Top Action Bar -->
    <div class="h-24 border-b border-white/5 bg-black/20 backdrop-blur-xl flex items-center justify-between px-8 z-20 animate-slideIn">
        <div class="flex items-center gap-10">
            <div class="flex items-center gap-4">
                <a href="invoices.php" class="btn-new inline-flex items-center no-underline">New</a>
            </div>
            
            <div class="flex items-center gap-3">
                <?php if (!$is_locked): ?>
                    <button type="submit" form="invoice-form" name="action" value="save_invoice" class="btn-send">Save</button>
                    <button type="button" onclick="triggerWorkflow('confirm')" class="btn-outline-premium">Confirm</button>
                <?php else: ?>
                    <button type="button" onclick="triggerWorkflow('send')" class="btn-send">Send</button>
                <?php endif; ?>
                <button type="button" onclick="window.print()" class="btn-outline-premium">Print</button>
            </div>
        </div>

        <div class="flex items-center gap-1">
            <div class="invoice-status-pill <?php echo $current_status === 'draft' ? 'active' : ''; ?>">Draft</div>
            <div class="invoice-status-pill <?php echo $current_status !== 'draft' ? 'active' : ''; ?>">Posted</div>
        </div>
    </div>

    <main class="flex-1 overflow-y-auto custom-scrollbar">
        <div class="p-12 max-w-[1400px] mx-auto animate-fadeIn border border-white/10 my-8 bg-black/30 rounded-lg">
            <?php if (isset($_GET['saved'])): ?>
                <div class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg animate-slideIn">
                    <i class="fas fa-check-circle mr-2"></i> Invoice changes saved successfully.
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['confirmed'])): ?>
                <div class="mb-6 p-4 bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 rounded-lg animate-slideIn">
                    <i class="fas fa-lock mr-2"></i> Invoice confirmed and posted. It is now locked for editing.
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['sent'])): ?>
                <div class="mb-6 p-4 bg-blue-500/10 border border-blue-500/20 text-blue-400 rounded-lg animate-slideIn">
                    <i class="fas fa-paper-plane mr-2"></i> Invoice has been marked as sent to the customer.
                </div>
            <?php endif; ?>
            <h1 class="invoice-title-handwritten text-white mb-10">Invoice Page</h1>

            <form method="POST" id="invoice-form" class="space-y-12">
                <div class="text-4xl font-bold text-white mb-12 tracking-tight">
                    <?php echo $invoice_id ? $invoice_number : "<span class='text-muted italic opacity-50'>Draft Invoice</span>"; ?>
                </div>

                <!-- Info Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-20 gap-y-6">
                    <!-- Left Column -->
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <label class="invoice-grid-label w-40">Customer</label>
                            <div class="invoice-grid-value flex-1">
                                <select name="customer_id" class="bg-transparent border-none text-white focus:outline-none w-full text-sm" required <?php echo $is_locked ? 'disabled' : ''; ?>>
                                    <option value="">Select...</option>
                                    <?php mysqli_data_seek($customers_result, 0); while ($c = mysqli_fetch_assoc($customers_result)): ?>
                                        <option value="<?php echo $c['id']; ?>" <?php echo (($invoice && $invoice['customer_id'] == $c['id']) || ($pre_customer_id == $c['id'])) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <label class="invoice-grid-label w-40">Invoice Address</label>
                            <div class="invoice-grid-value flex-1">
                                <input type="text" name="invoice_address" class="bg-transparent border-none text-white focus:outline-none w-full text-sm" placeholder="---" value="<?php echo htmlspecialchars($invoice['customer_address'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="flex items-center">
                            <label class="invoice-grid-label w-40">Delivery Address</label>
                            <div class="invoice-grid-value flex-1">
                                <input type="text" name="delivery_address" class="bg-transparent border-none text-white focus:outline-none w-full text-sm" placeholder="---">
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <label class="invoice-grid-label w-40">Source Document</label>
                            <div class="invoice-grid-value flex-1">
                                <select name="order_id" class="bg-transparent border-none text-white focus:outline-none w-full text-sm" required <?php echo $is_locked ? 'disabled' : ''; ?>>
                                    <option value="">Select Order...</option>
                                    <?php mysqli_data_seek($orders_result, 0); while ($o = mysqli_fetch_assoc($orders_result)): ?>
                                        <option value="<?php echo $o['id']; ?>" <?php echo (($invoice && $invoice['order_id'] == $o['id']) || ($pre_order_id == $o['id'])) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($o['order_number']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <label class="invoice-grid-label w-40">Rental Period</label>
                            <div class="flex items-center gap-2 flex-1">
                                <div class="invoice-grid-value !min-w-[100px]">
                                    <input type="date" name="start_date" class="bg-transparent border-none text-white focus:outline-none w-full text-xs">
                                </div>
                                <span class="text-white">→</span>
                                <div class="invoice-grid-value !min-w-[100px]">
                                    <input type="date" name="end_date" class="bg-transparent border-none text-white focus:outline-none w-full text-xs">
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <label class="invoice-grid-label w-40">Invoice date</label>
                            <div class="invoice-grid-value flex-1">
                                <input type="date" name="invoice_date" value="<?php echo date('Y-m-d'); ?>" class="bg-transparent border-none text-white focus:outline-none w-full text-sm">
                            </div>
                        </div>
                        <div class="flex items-center">
                            <label class="invoice-grid-label w-40">Due Date</label>
                            <div class="invoice-grid-value flex-1">
                                <input type="date" name="due_date" class="bg-transparent border-none text-white focus:outline-none w-full text-sm">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Invoice Lines Section -->
                <div class="mt-16">
                    <div class="border-b border-white/40 mb-8 pb-2">
                        <span class="text-xl font-bold text-white">Invoice Lines</span>
                    </div>

                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-white/20">
                                <th class="py-4 font-bold text-white text-sm">Product</th>
                                <th class="py-4 font-bold text-white text-sm text-center">Quantity</th>
                                <th class="py-4 font-bold text-white text-sm text-center">Unit</th>
                                <th class="py-4 font-bold text-white text-sm text-right">Unit Price</th>
                                <th class="py-4 font-bold text-white text-sm text-center">Taxes</th>
                                <th class="py-4 font-bold text-white text-sm text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="invoice-lines-tbody">
                            <?php $lines = !empty($invoice_items) ? $invoice_items : [[]]; foreach ($lines as $item): ?>
                            <tr class="border-b border-white/10 group">
                                <td class="py-4 pr-4">
                                    <select name="product_id[]" class="bg-transparent border-none text-white focus:outline-none w-full text-sm" onchange="updateProductDetails(this)" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                        <option value="">Select Product...</option>
                                        <?php mysqli_data_seek($products_result, 0); while ($p = mysqli_fetch_assoc($products_result)): ?>
                                            <option value="<?php echo $p['id']; ?>" data-price="<?php echo $p['sales_price']; ?>" data-name="<?php echo htmlspecialchars($p['name']); ?>" 
                                                <?php echo (isset($item['product_id']) && $item['product_id'] == $p['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($p['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <input type="hidden" name="product_name[]" class="product-name-input" value="<?php echo $item['description'] ?? ''; ?>">
                                </td>
                                <td class="py-4 px-2 text-center">
                                    <input type="number" name="quantity[]" value="<?php echo $item['quantity'] ?? 1; ?>" step="0.01" class="bg-transparent border-none text-white focus:outline-none w-16 text-center text-sm" onchange="calculateLineTotal(this)" <?php echo $is_locked ? 'readonly' : ''; ?>>
                                </td>
                                <td class="py-4 px-2 text-center text-sm text-white/70">Units</td>
                                <td class="py-4 px-2 text-right">
                                    <span class="text-sm text-white/70 mr-1">Rs</span>
                                    <input type="number" name="unit_price[]" step="0.01" value="<?php echo $item['unit_price'] ?? 0; ?>" class="bg-transparent border-none text-white focus:outline-none w-20 text-right text-sm" onchange="calculateLineTotal(this)" <?php echo $is_locked ? 'readonly' : ''; ?>>
                                </td>
                                <td class="py-4 px-2 text-center text-sm text-white/50">---</td>
                                <td class="py-4 pl-4 text-right">
                                    <span class="text-sm text-white mr-1">Rs</span>
                                    <input type="number" name="line_total[]" value="<?php echo $item['line_total'] ?? 0; ?>" readonly class="bg-transparent border-none text-white focus:outline-none w-24 text-right text-sm font-bold">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="mt-6 flex flex-col gap-4">
                        <div class="flex gap-6">
                            <a href="javascript:void(0)" onclick="addInvoiceLine()" class="action-link-premium">Add a Product</a>
                            <a href="javascript:void(0)" onclick="toggleNotes()" class="action-link-premium">Add a note</a>
                        </div>
                        <div id="notes-container" class="<?php echo empty($invoice['notes']) ? 'hidden' : ''; ?> animate-fadeIn">
                             <textarea name="notes" rows="3" class="bg-white/5 border border-white/10 text-white p-4 rounded-lg w-full text-sm focus:border-primary-500 outline-none" placeholder="Enter supplemental notes..."><?php echo htmlspecialchars($invoice['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Footer / Totals -->
                <div class="mt-20 flex flex-col md:flex-row justify-between items-end gap-10 pb-10">
                    <div class="text-sm">
                        <span class="text-white font-bold">Terms & Conditions: </span>
                        <a href="#" class="action-link-premium">https://xxxxx.xxx.xxx/terms</a>
                    </div>

                    <div class="space-y-6 w-full max-w-md">
                        <div class="flex justify-end gap-3 mb-8">
                            <button type="button" class="btn-outline-premium !text-[10px] !px-4 !py-1">Coupon Code</button>
                            <button type="button" class="btn-outline-premium !text-[10px] !px-4 !py-1">Discount</button>
                            <button type="button" class="btn-outline-premium !text-[10px] !px-4 !py-1">Add Shipping</button>
                        </div>

                        <div class="space-y-2 border-t border-white/20 pt-6">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-white font-bold opacity-80">Untaxed Amount:</span>
                                <span class="text-sm text-white font-bold" id="subtotal">Rs 0.00</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-2xl text-white font-bold">Total:</span>
                                <span class="text-2xl text-white font-bold" id="total-amount">Rs 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
    function toggleNotes() {
        const container = document.getElementById('notes-container');
        container.classList.toggle('hidden');
    }

    function updateProductDetails(select) {
        const opt = select.options[select.selectedIndex];
        const p = opt.getAttribute('data-price') || 0;
        const n = opt.getAttribute('data-name') || '';
        const row = select.closest('tr');
        row.querySelector('input[name="unit_price[]"]').value = p;
        row.querySelector('.product-name-input').value = n;
        calculateLineTotal(row.querySelector('input[name="quantity[]"]'));
    }

    function calculateLineTotal(el) {
        const row = el.closest('tr');
        const q = parseFloat(row.querySelector('input[name="quantity[]"]').value) || 0;
        const p = parseFloat(row.querySelector('input[name="unit_price[]"]').value) || 0;
        const total = q * p;
        row.querySelector('input[name="line_total[]"]').value = total.toFixed(2);
        calculateTotals();
    }

    function calculateTotals() {
        let gross = 0;
        document.querySelectorAll('input[name="line_total[]"]').forEach(i => gross += parseFloat(i.value) || 0);
        const total = gross;

        document.getElementById('subtotal').textContent = 'Rs ' + gross.toLocaleString('en-IN', {minimumFractionDigits: 2});
        document.getElementById('total-amount').textContent = 'Rs ' + total.toLocaleString('en-IN', {minimumFractionDigits: 2});
    }

    function addInvoiceLine() {
        const tbody = document.getElementById('invoice-lines-tbody');
        const rows = tbody.querySelectorAll('tr');
        const clone = rows[0].cloneNode(true);
        clone.querySelectorAll('select, input').forEach(i => {
            if(i.tagName === 'SELECT') i.selectedIndex = 0;
            else if(i.type === 'number') i.value = i.name === 'quantity[]' ? 1 : 0;
            else if(i.type === 'hidden') i.value = '';
        });
        tbody.appendChild(clone);
    }

    function triggerWorkflow(action) {
        if(confirm('Execute workflow action: ' + action + '?')) {
            const f = document.getElementById('invoice-form');
            const i = document.createElement('input');
            i.type = 'hidden'; i.name = 'action'; i.value = action;
            f.appendChild(i); f.submit();
        }
    }

    document.addEventListener('DOMContentLoaded', calculateTotals);
</script>

<?php include 'footer.php'; ?>
</body>
</html>
