<?php
include 'config.php';

// Access Control
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$invoice_id = $_GET['id'] ?? null;
$invoice = null;
$current_status = 'draft';
$is_locked = false;

if ($invoice_id) {
    $query = "SELECT * FROM invoices WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $invoice_id);
    mysqli_stmt_execute($stmt);
    $invoice = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($invoice) {
        $current_status = $invoice['status'];
        $is_locked = $current_status === 'posted';
    }
}

$invoice_number = $invoice['invoice_number'] ?? 'INV/' . date('Y') . '/' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

// Handle status transitions & actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_invoice') {
        $customer_id = sanitize($_POST['customer_id']);
        $order_id = sanitize($_POST['order_id']) ?? 1;
        $subtotal = 0;
        if (isset($_POST['line_total'])) foreach ($_POST['line_total'] as $t) $subtotal += floatval($t);
        $tax = $subtotal * 0.10;
        $total = $subtotal + $tax;
        
        if ($invoice_id) {
            $q = "UPDATE invoices SET customer_id = ?, subtotal = ?, tax_amount = ?, total_amount = ? WHERE id = ? AND status = 'draft'";
            $stmt = mysqli_prepare($conn, $q);
            mysqli_stmt_bind_param($stmt, "idddi", $customer_id, $subtotal, $tax, $total, $invoice_id);
            mysqli_stmt_execute($stmt);
            mysqli_query($conn, "DELETE FROM invoice_lines WHERE invoice_id = $invoice_id");
        } else {
            $q = "INSERT INTO invoices (invoice_number, order_id, customer_id, subtotal, tax_amount, total_amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'draft', NOW())";
            $stmt = mysqli_prepare($conn, $q);
            mysqli_stmt_bind_param($stmt, "siiddd", $invoice_number, $order_id, $customer_id, $subtotal, $tax, $total);
            mysqli_stmt_execute($stmt);
            $invoice_id = mysqli_insert_id($conn);
        }
        
        if (isset($_POST['product_id'])) {
            foreach ($_POST['product_id'] as $k => $pid) {
                $qty = floatval($_POST['quantity'][$k]);
                $price = floatval($_POST['unit_price'][$k]);
                $line_t = floatval($_POST['line_total'][$k]);
                $iq = "INSERT INTO invoice_lines (invoice_id, description, quantity, unit_price, line_total) VALUES (?, 'Product Resource', ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $iq);
                mysqli_stmt_bind_param($stmt, "iddd", $invoice_id, $qty, $price, $line_t);
                mysqli_stmt_execute($stmt);
            }
        }
        header('Location: invoice.php?id=' . $invoice_id . '&saved=1');
        exit();
    }
}

$page_title = $invoice_id ? "Bill Reference " . $invoice_number : "New Fiscal Assignment";
include 'header.php';
?>

<div class="h-screen overflow-hidden flex flex-col bg-primary">
    <!-- Action Header -->
    <div class="h-20 border-b border-white/5 bg-black/20 backdrop-blur-xl flex items-center justify-between px-8 z-20 animate-slideIn">
        <div class="flex items-center gap-6">
            <a href="dashboard.php" class="premium-notification h-10 w-10 hover:bg-white/5 flex items-center justify-center">
                <i class="fas fa-arrow-left text-xs"></i>
            </a>
            <div class="h-10 w-[1px] bg-white/10"></div>
            <div>
                <h1 class="text-xl font-black text-white uppercase tracking-tighter"><?php echo $invoice_number; ?></h1>
                <p class="text-[10px] font-bold text-muted uppercase tracking-widest mt-1"><?php echo ucfirst($current_status); ?> PROTOCOL • FISCAL MODULE</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <?php if (!$is_locked): ?>
                <button type="submit" form="invoice-form" name="action" value="save_invoice" class="premium-btn premium-btn-primary h-10 px-6">
                    <i class="fas fa-microchip mr-2 text-xs"></i>Synchronize Protocol
                </button>
            <?php else: ?>
                <span class="premium-badge premium-badge-success">AUDIT LOCKED</span>
                <button onclick="window.print()" class="premium-notification h-10 w-10 hover:bg-white/5">
                    <i class="fas fa-print text-sm"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <main class="flex-1 overflow-y-auto custom-scrollbar">
        <div class="p-8 max-w-[1400px] mx-auto animate-fadeIn">
            <form method="POST" id="invoice-form" class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Main Configuration -->
                <div class="lg:col-span-8 space-y-8">
                    <div class="premium-card">
                        <h4 class="premium-chart-title mb-8 flex items-center gap-2">
                            <i class="fas fa-fingerprint text-primary-400"></i>
                            Transactional Identity
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-6">
                                <div class="space-y-2">
                                    <label class="premium-label">Operational Identity (Client)</label>
                                    <select name="customer_id" class="premium-input h-12" required <?php echo $is_locked ? 'disabled' : ''; ?>>
                                        <option value="">Select Partner...</option>
                                        <!-- Note: Options should be fetched via AJAX or pre-rendered -->
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="premium-label">Temporal Reference Index</label>
                                    <select name="order_id" class="premium-input h-12" required <?php echo $is_locked ? 'disabled' : ''; ?>>
                                        <option value="">Order Link...</option>
                                    </select>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="premium-label">Transactional Metadata</label>
                                <textarea name="notes" rows="5" class="premium-input p-4" placeholder="Execution specific terms..." <?php echo $is_locked ? 'readonly' : ''; ?>><?php echo $invoice['notes'] ?? ''; ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Line Items Matrix -->
                    <div class="premium-card p-0 overflow-hidden border-white/5">
                        <div class="p-6 border-b border-white/5 flex items-center justify-between">
                            <h4 class="premium-chart-title m-0 flex items-center gap-2">
                                <i class="fas fa-layer-group text-secondary-400"></i>
                                Resource Distribution Matrix
                            </h4>
                            <?php if (!$is_locked): ?>
                                <button type="button" class="text-[10px] font-black text-primary-400 uppercase tracking-widest hover:text-white transition-all">
                                    <i class="fas fa-plus-circle mr-1"></i> Integrate Resource
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="p-6 rounded-2xl bg-white/5 border border-white/5 group hover:border-primary-500/30 transition-all">
                                <div class="grid grid-cols-12 gap-6">
                                    <div class="col-span-12 md:col-span-5 space-y-2">
                                        <label class="premium-label text-[10px]">Identified Resource</label>
                                        <select name="product_id[]" class="premium-input h-10 text-xs" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                            <option value="">Resource Discovery...</option>
                                        </select>
                                    </div>
                                    <div class="col-span-4 md:col-span-2 space-y-2">
                                        <label class="premium-label text-[10px] text-center block">Volume</label>
                                        <input type="number" name="quantity[]" value="1" class="premium-input h-10 text-center text-xs" <?php echo $is_locked ? 'readonly' : ''; ?>>
                                    </div>
                                    <div class="col-span-4 md:col-span-2 space-y-2">
                                        <label class="premium-label text-[10px]">Unit Rate</label>
                                        <input type="number" name="unit_price[]" value="0.00" class="premium-input h-10 text-xs" <?php echo $is_locked ? 'readonly' : ''; ?>>
                                    </div>
                                    <div class="col-span-4 md:col-span-3 space-y-2 text-right">
                                        <label class="premium-label text-[10px] pr-1">Line Yield</label>
                                        <input type="text" value="₹ 0.00" readonly class="premium-input h-10 text-right text-xs bg-black/40 border-white/5 text-white font-black">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fiscal Calculation Module -->
                <div class="lg:col-span-4 space-y-8">
                    <div class="premium-card bg-gradient-to-br from-primary-600/20 to-indigo-600/20 shadow-glow">
                        <h4 class="premium-chart-title mb-8 flex items-center justify-between text-white/50">
                            Fiscal Equilibrium
                            <i class="fas fa-microchip text-md opacity-20"></i>
                        </h4>
                        
                        <div class="space-y-6">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-black text-muted uppercase tracking-widest">Aggregate Yield</span>
                                <span class="text-sm font-bold text-white/50">₹ 0.00</span>
                            </div>
                            <div class="flex items-center justify-between pb-6 border-b border-white/5">
                                <span class="text-[10px] font-black text-muted uppercase tracking-widest">Protocol Levy (10%)</span>
                                <span class="text-sm font-bold text-white/50">₹ 0.00</span>
                            </div>
                            <div class="pt-2 flex items-center justify-between">
                                <span class="text-sm font-black text-white uppercase tracking-tighter">Net Obligation</span>
                                <span class="text-3xl font-black text-secondary-400 shadow-glow-sm tracking-tighter">₹ 0.00</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 rounded-3xl bg-black/40 border border-white/5 text-[9px] text-muted leading-relaxed font-medium italic opacity-60">
                        <i class="fas fa-info-circle mr-2 text-primary-500"></i>
                        Verification parameters: This module operates under real-time synchronization. Numerical adjustments are mirrored in the core transactional ledger upon execution.
                    </div>
                </div>
            </form>
        </div>
    </main>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
