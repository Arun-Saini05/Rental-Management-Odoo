<?php
include 'config.php';

// Access Control
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch Data for Dropdowns
$customers_result = mysqli_query($conn, "SELECT c.id, u.name, u.email FROM customers c JOIN users u ON c.user_id = u.id");
$products_result = mysqli_query($conn, "SELECT id, name, sales_price FROM products WHERE is_rentable = 1 AND is_published = 1");

// Order Logic
$order_number = 'ORD-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
$order_id = $_GET['id'] ?? null;
$current_order = null;
$current_status = 'draft';
$is_locked = 0;

if ($order_id) {
    $order_query = "SELECT * FROM rental_orders WHERE id = ?";
    $stmt = mysqli_prepare($conn, $order_query);
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $current_order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($current_order) {
        $current_status = $current_order['status'] ?? 'draft';
        $is_locked = $current_order['is_locked'] ?? 0;
        $order_number = $current_order['order_number'];
    }
}

// Handle Save/Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $customer_id = sanitize($_POST['customer_id']);
    $notes = sanitize($_POST['notes'] ?? '');
    $start_date = sanitize($_POST['start_date']);
    $end_date = sanitize($_POST['end_date']);
    
    // Total calculation
    $subtotal = 0;
    if(isset($_POST['unit_price'])) {
        foreach($_POST['unit_price'] as $idx => $up) {
            $subtotal += floatval($up) * intval($_POST['quantity'][$idx] ?? 1);
        }
    }
    $tax = $subtotal * 0.1;
    $total = $subtotal + $tax;

    $status = $current_status;
    $lock = $is_locked;
    if ($action === 'confirm_order') {
        $status = 'confirmed';
        $lock = 1;
    } elseif ($action === 'send_order') {
        $status = 'sent';
    } elseif ($action === 'save_order') {
        // Keep current status unless it's new
        if (!$order_id) $status = 'draft';
    }

    if ($order_id) {
        $q = "UPDATE rental_orders SET customer_id=?, subtotal=?, tax_amount=?, total_amount=?, status=?, pickup_date=?, expected_return_date=?, notes=?, is_locked=?, updated_at=NOW() WHERE id=?";
        $stmt = mysqli_prepare($conn, $q);
        mysqli_stmt_bind_param($stmt, "idddssssii", $customer_id, $subtotal, $tax, $total, $status, $start_date, $end_date, $notes, $lock, $order_id);
        mysqli_stmt_execute($stmt);
    } else {
        $q = "INSERT INTO rental_orders (order_number, customer_id, status, subtotal, tax_amount, total_amount, pickup_date, expected_return_date, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $q);
        mysqli_stmt_bind_param($stmt, "sisdddsss", $order_number, $customer_id, $status, $subtotal, $tax, $total, $start_date, $end_date, $notes);
        mysqli_stmt_execute($stmt);
        $order_id = mysqli_insert_id($conn);
    }
    header('Location: new_order.php?id=' . $order_id . '&saved=1');
    exit();
}

$page_title = $order_id ? "Order $order_number" : "Initialize Rental Order";
include 'header.php';
?>

<div class="flex flex-col bg-primary min-h-screen">
    <!-- Top Action Bar -->
    <div class="h-auto py-6 border-b border-white/5 bg-black/20 backdrop-blur-xl flex flex-col gap-6 px-12 z-20 animate-slideIn">
        <div class="flex items-center gap-10">
            <div class="flex items-center gap-4">
                <a href="new_order.php" class="btn-new inline-flex items-center no-underline !bg-[#c084fc] !text-black">New</a>
                <div class="flex items-center gap-3">
                    <span class="text-white font-bold text-lg"><?php echo ($current_status === 'confirmed' || $current_status === 'completed') ? 'Sale order' : 'Rental order'; ?></span>
                    <div class="flex gap-1">
                        <i class="fas fa-check-square text-emerald-500 text-lg"></i>
                        <i class="fas fa-window-close text-rose-500 text-lg opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <?php if ($current_status !== 'confirmed' && $current_status !== 'completed'): ?>
                    <button type="submit" form="order-form" name="action" value="send_order" class="btn-send !bg-[#c084fc] !text-black">Send</button>
                    <button type="button" onclick="triggerWorkflow('confirm')" class="btn-outline-premium">Confirm</button>
                <?php endif; ?>
                
                <button type="button" onclick="window.print()" class="btn-outline-premium">Print</button>
                
                <?php if ($current_status === 'confirmed' || $current_status === 'completed'): ?>
                    <button type="button" onclick="triggerWorkflow('create_invoice')" class="btn-send !bg-[#c084fc] !text-black">Create Invoice</button>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-0">
                <div class="invoice-status-pill <?php echo ($current_status === 'draft' || !$order_id) ? 'active' : ''; ?> !text-[10px] !py-1 !px-4">Quotation</div>
                <div class="invoice-status-pill <?php echo $current_status === 'sent' ? 'active' : ''; ?> !text-[10px] !py-1 !px-4">Quotation Sent</div>
                <div class="invoice-status-pill <?php echo ($current_status === 'confirmed' || $current_status === 'completed') ? 'active' : ''; ?> !text-[10px] !py-1 !px-4">Sale Order</div>
            </div>
        </div>
    </div>

    <main class="flex-1 overflow-y-auto custom-scrollbar">
        <div class="p-12 max-w-[1400px] mx-auto animate-fadeIn border border-white/10 my-8 bg-black/30 rounded-lg">
            <?php if (isset($_GET['created'])): ?>
                <div class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg animate-slideIn text-sm">
                    <i class="fas fa-check-circle mr-2"></i> Rental order initialized successfully.
                </div>
            <?php endif; ?>
            
            <h1 class="invoice-title-handwritten text-white mb-10">New Order Page</h1>

            <form method="POST" id="order-form" class="space-y-12 text-sm">
                <div class="text-4xl font-bold text-white mb-12 tracking-tight">
                    <?php echo $order_id ? $order_number : "S00075"; ?>
                </div>

                <!-- Info Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-20 gap-y-6">
                    <!-- Left Column -->
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <label class="invoice-grid-label w-40 text-white/70">Customer</label>
                            <div class="invoice-grid-value flex-1 px-2 border-b border-white/20">
                                <select name="customer_id" class="bg-transparent border-none text-white focus:outline-none w-full text-sm" required <?php echo $is_locked ? 'disabled' : ''; ?>>
                                    <option value="" class="bg-gray-900">Select...</option>
                                    <?php mysqli_data_seek($customers_result, 0); while ($c = mysqli_fetch_assoc($customers_result)): ?>
                                        <option value="<?php echo $c['id']; ?>" class="bg-gray-900" <?php echo ($current_order && $current_order['customer_id'] == $c['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <label class="invoice-grid-label w-40 text-white/70">Invoice Address</label>
                            <div class="invoice-grid-value flex-1 px-2 border-b border-white/20">
                                <input type="text" name="invoice_address" class="bg-transparent border-none text-white focus:outline-none w-full text-sm" placeholder="Address line..." <?php echo $is_locked ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <label class="invoice-grid-label w-40 text-white/70">Delivery Address</label>
                            <div class="invoice-grid-value flex-1 px-2 border-b border-white/20">
                                <input type="text" name="delivery_address" class="bg-transparent border-none text-white focus:outline-none w-full text-sm" placeholder="Address line..." <?php echo $is_locked ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <label class="invoice-grid-label w-40 text-white/70">Rental Period</label>
                            <div class="flex items-center gap-2 flex-1">
                                <div class="invoice-grid-value !min-w-[140px] !bg-white !rounded px-2 border-none">
                                    <input type="date" name="start_date" class="bg-transparent border-none text-black focus:outline-none w-full text-sm p-1 font-bold" required <?php echo $is_locked ? 'readonly' : ''; ?> value="<?php echo $current_order['pickup_date'] ?? ''; ?>">
                                </div>
                                <span class="text-white/30 px-2">→</span>
                                <div class="invoice-grid-value !min-w-[140px] !bg-white !rounded px-2 border-none">
                                    <input type="date" name="end_date" class="bg-transparent border-none text-black focus:outline-none w-full text-sm p-1 font-bold" required <?php echo $is_locked ? 'readonly' : ''; ?> value="<?php echo $current_order['expected_return_date'] ?? ''; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <label class="invoice-grid-label w-40 text-white/70">Order date</label>
                            <div class="invoice-grid-value flex-1 px-2 border-b border-white/20">
                                <input type="text" class="bg-transparent border-none text-white h-auto p-0 focus:outline-none w-full text-sm" value="<?php echo date('Y-m-d'); ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="border-b border-white/20 flex">
                    <button type="button" class="border-b-2 border-white text-white pb-3 font-bold text-sm">Order Line</button>
                </div>

                <!-- Product Table -->
                <div class="premium-table-container overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[800px]" id="line-table">
                        <thead>
                            <tr class="border-b border-white/10 uppercase text-[10px] tracking-widest text-white/60">
                                <th class="py-4 font-bold pl-2">Product</th>
                                <th class="py-4 font-bold text-center">Quantity</th>
                                <th class="py-4 font-bold">Unit</th>
                                <th class="py-4 font-bold">Unit Price</th>
                                <th class="py-4 font-bold">Taxes</th>
                                <th class="py-4 font-bold text-right pr-2">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="line-items">
                            <tr class="border-b border-white/5 animate-fadeIn group">
                                <td class="py-4 pr-4 pl-2">
                                    <select name="product_id[]" class="bg-transparent border-none text-white focus:outline-none w-full text-sm appearance-none" onchange="updateProductDetails(this)" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                        <option value="">Select Product...</option>
                                        <?php mysqli_data_seek($products_result, 0); while ($p = mysqli_fetch_assoc($products_result)): ?>
                                            <option value="<?php echo $p['id']; ?>" data-price="<?php echo $p['sales_price']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </td>
                                <td class="py-4 px-2">
                                    <input type="number" name="quantity[]" value="1" min="1" class="bg-white/5 border border-white/10 text-white text-center rounded w-16 h-8 text-sm focus:border-primary-500 outline-none" onchange="calculateLineTotal(this)" <?php echo $is_locked ? 'readonly' : ''; ?>>
                                </td>
                                <td class="py-4 px-2 text-white/60 text-sm italic">Units</td>
                                <td class="py-4 px-2">
                                    <input type="number" name="unit_price[]" step="0.01" class="bg-transparent border-none text-white focus:outline-none w-24 text-sm" placeholder="0.00" onchange="calculateLineTotal(this)" <?php echo $is_locked ? 'readonly' : ''; ?>>
                                </td>
                                <td class="py-4 px-2 text-white/40 text-[10px]">—</td>
                                <td class="py-4 pr-2 text-right">
                                    <input type="text" name="line_total[]" class="bg-transparent border-none text-white text-right focus:outline-none w-24 text-sm font-bold" value="0.00" readonly>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Table Actions -->
                <div class="flex gap-4 pt-4">
                    <a href="javascript:void(0)" onclick="addLineItem()" class="action-link-premium">Add a Product</a>
                    <a href="javascript:void(0)" onclick="toggleNotes()" class="action-link-premium">Add a note</a>
                </div>

                <!-- Notes Area -->
                <div id="notes-container" class="hidden animate-fadeIn">
                    <textarea name="notes" rows="3" class="bg-white/5 border border-white/10 text-white p-4 rounded-lg w-full text-sm focus:border-primary-500 outline-none" placeholder="Enter supplemental notes..."><?php echo htmlspecialchars($current_order['notes'] ?? ''); ?></textarea>
                </div>

                <!-- Total Actions -->
                <div class="flex justify-between items-start pt-12">
                    <div class="space-y-4">
                        <div class="flex gap-2">
                            <button type="button" class="btn-outline-premium !text-[10px] !px-4 !py-1">Coupon Code</button>
                            <button type="button" class="btn-outline-premium !text-[10px] !px-4 !py-1">Discount</button>
                            <button type="button" class="btn-outline-premium !text-[10px] !px-4 !py-1">Add Shipping</button>
                        </div>
                        <p class="text-white/40 text-xs mt-4">Terms & Conditions: <a href="#" class="text-primary-400 no-underline">https://xxxxx.xxx.xxx/terms</a></p>
                    </div>

                    <div class="w-80 space-y-3">
                        <div class="flex justify-between items-center text-white/60">
                            <span class="text-xs">Untaxed Amount:</span>
                            <span class="font-bold text-sm" id="untaxed-total">Rs 0.00</span>
                        </div>
                        <div class="flex justify-between items-center text-white pt-2 border-t border-white/10">
                            <span class="text-sm font-bold">Total:</span>
                            <span class="text-xl font-black text-primary-400" id="grand-total">Rs 0.00</span>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
    function updateProductDetails(select) {
        const opt = select.options[select.selectedIndex];
        const price = opt.getAttribute('data-price') || 0;
        const row = select.closest('tr');
        const priceInput = row.querySelector('input[name="unit_price[]"]');
        priceInput.value = price;
        calculateLineTotal(priceInput);
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
        let untaxed = 0;
        document.querySelectorAll('input[name="line_total[]"]').forEach(input => {
            untaxed += parseFloat(input.value) || 0;
        });

        document.getElementById('untaxed-total').textContent = 'Rs ' + untaxed.toLocaleString('en-IN', {minimumFractionDigits: 2});
        // Simplistic total same as untaxed for mockup consistency
        document.getElementById('grand-total').textContent = 'Rs ' + untaxed.toLocaleString('en-IN', {minimumFractionDigits: 2});
    }

    function addLineItem() {
        if (<?php echo $is_locked ? 'true' : 'false'; ?>) return;
        const tbody = document.getElementById('line-items');
        const firstRow = tbody.querySelector('tr');
        const clone = firstRow.cloneNode(true);
        
        clone.querySelectorAll('input').forEach(i => {
            if(i.name !== 'quantity[]') i.value = i.name === 'line_total[]' ? '0.00' : '';
            else i.value = '1';
        });
        clone.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
        tbody.appendChild(clone);
    }

    function toggleNotes() {
        const container = document.getElementById('notes-container');
        container.classList.toggle('hidden');
    }

    function triggerWorkflow(action) {
        const orderId = <?php echo $order_id ?: 'null'; ?>;
        
        if(action === 'create_invoice') {
             if (!orderId) { alert('Protocol Error: Sequence identification missing.'); return; }
             window.location.href = `invoices.php?order_id=${orderId}`;
             return;
        }

        // For confirm, we allow it even if not saved, it will save and confirm
        const f = document.createElement('form');
        f.method = 'POST';
        f.innerHTML = `<input type="hidden" name="action" value="${action === 'confirm' ? 'confirm_order' : 'save_order'}">`;
        if(orderId) f.innerHTML += `<input type="hidden" name="order_id" value="${orderId}">`;
        
        // Append current form data
        const mainForm = document.getElementById('order-form');
        const formData = new FormData(mainForm);
        for (let [key, value] of formData.entries()) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            f.appendChild(input);
        }

        document.body.appendChild(f);
        f.submit();
    }

    document.addEventListener('DOMContentLoaded', calculateTotals);
</script>

<?php include 'footer.php'; ?>
</body>
</html>
