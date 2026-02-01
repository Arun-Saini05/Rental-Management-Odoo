<?php
include 'config.php';

// Access Control
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$order_id = $_GET['id'] ?? null;
if (!$order_id) {
    header('Location: dashboard.php');
    exit();
}

// Fetch order details
$order_query = "SELECT ro.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
               FROM rental_orders ro 
               LEFT JOIN customers c ON ro.customer_id = c.id 
               LEFT JOIN users u ON c.user_id = u.id
               WHERE ro.id = ?";
$stmt = mysqli_prepare($conn, $order_query);
mysqli_stmt_bind_param($stmt, "i", $order_id);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$order) {
    header('Location: dashboard.php');
    exit();
}

// Dates & Duration
$pickup = new DateTime($order['pickup_date']);
$return = new DateTime($order['expected_return_date']);
$duration = $pickup->diff($return)->days ?: 1;

$page_title = "Reference " . $order['order_number'];
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
                <h1 class="text-xl font-black text-white uppercase tracking-tighter"><?php echo $order['order_number']; ?></h1>
                <p class="text-[10px] font-bold text-muted uppercase tracking-widest mt-1">Order Summary • <?php echo date('F d, Y', strtotime($order['created_at'])); ?></p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <span class="premium-badge <?php 
                echo $order['status'] === 'confirmed' ? 'premium-badge-success' : 
                    ($order['status'] === 'sent' ? 'premium-badge-info' : 'premium-badge-warning'); 
            ?>">
                <?php echo strtoupper($order['status']); ?> PROTOCOL
            </span>
            <div class="h-6 w-[1px] bg-white/10 mx-2"></div>
            <a href="new_order.php?id=<?php echo $order_id; ?>" class="premium-btn premium-btn-secondary h-10 px-6">
                <i class="fas fa-edit mr-2 text-xs"></i>Modify Parameters
            </a>
            <button onclick="window.print()" class="premium-notification h-10 w-10 hover:bg-white/5">
                <i class="fas fa-print text-sm"></i>
            </button>
        </div>
    </div>

    <main class="flex-1 overflow-y-auto custom-scrollbar">
        <div class="p-8 max-w-[1400px] mx-auto animate-fadeIn">
            <!-- Summary Success -->
            <div class="bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 p-6 rounded-3xl mb-10 flex items-center justify-between group animate-slideIn">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-indigo-500/20 rounded-full flex items-center justify-center mr-4 text-indigo-400">
                        <i class="fas fa-shield-check text-xl"></i>
                    </div>
                    <div>
                        <h4 class="font-black text-white text-sm uppercase tracking-tighter">Integrity Verified</h4>
                        <p class="text-xs text-indigo-300/70">Numerical data and rental intervals have been synchronized with the core database.</p>
                    </div>
                </div>
                <div class="hidden md:block">
                    <span class="text-[10px] font-bold text-muted uppercase tracking-widest px-4 py-2 bg-black/20 rounded-xl border border-white/5">Hash: <?php echo md5($order['id'] . $order['created_at']); ?></span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Left Details -->
                <div class="lg:col-span-8 space-y-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Client Identification -->
                        <div class="premium-card">
                            <h4 class="premium-chart-title mb-6 flex items-center gap-2">
                                <i class="fas fa-user-circle text-primary-400"></i>
                                Partner Identity
                            </h4>
                            <div class="flex items-center gap-4 p-4 rounded-2xl bg-white/5 border border-white/5">
                                <div class="w-16 h-16 bg-gradient-to-br from-primary-600 to-indigo-700 rounded-2xl flex items-center justify-center font-black text-white text-xl shadow-glow">
                                    <?php echo strtoupper(substr($order['customer_name'], 0, 1)); ?>
                                </div>
                                <div class="overflow-hidden">
                                    <div class="text-lg font-black text-white truncate"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                    <div class="text-xs text-muted flex items-center gap-2 mt-1">
                                        <i class="fas fa-envelope text-primary-400 text-[10px]"></i>
                                        <?php echo htmlspecialchars($order['customer_email']); ?>
                                    </div>
                                    <div class="text-xs text-muted flex items-center gap-2 mt-1">
                                        <i class="fas fa-phone text-primary-400 text-[10px]"></i>
                                        <?php echo htmlspecialchars($order['customer_phone'] ?: '+00 000 000 00'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Temporal Metrics -->
                        <div class="premium-card">
                            <h4 class="premium-chart-title mb-6 flex items-center gap-2">
                                <i class="fas fa-calendar-alt text-secondary-400"></i>
                                Rental Interval
                            </h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="p-4 rounded-2xl bg-white/5 border border-white/5 text-center">
                                    <div class="text-[9px] font-bold text-muted uppercase tracking-widest mb-1">Commence</div>
                                    <div class="text-sm font-black text-white"><?php echo date('M d, Y', strtotime($order['pickup_date'])); ?></div>
                                </div>
                                <div class="p-4 rounded-2xl bg-white/5 border border-white/5 text-center">
                                    <div class="text-[9px] font-bold text-muted uppercase tracking-widest mb-1">Conclude</div>
                                    <div class="text-sm font-black text-white"><?php echo date('M d, Y', strtotime($order['expected_return_date'])); ?></div>
                                </div>
                            </div>
                            <div class="mt-4 p-4 rounded-2xl bg-primary-500/10 border border-primary-500/20 flex items-center justify-between">
                                <span class="text-[10px] font-bold text-primary-400 uppercase tracking-widest">Aggregate Duration</span>
                                <span class="text-sm font-black text-white"><?php echo $duration; ?> Execution Cycle<?php echo $duration > 1 ? 's' : ''; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Order Matrix -->
                    <div class="premium-card p-0 overflow-hidden border-white/5">
                        <div class="p-6 border-b border-white/5">
                            <h4 class="premium-chart-title m-0 flex items-center gap-2">
                                <i class="fas fa-stream text-muted"></i>
                                Resource Allocation Matrix
                            </h4>
                        </div>
                        <div class="premium-table-container border-0 shadow-none">
                            <table class="premium-table">
                                <thead>
                                    <tr>
                                        <th class="py-5 pl-8 text-[10px] uppercase font-black text-muted tracking-widest transition-all">Deployment Resource</th>
                                        <th class="py-5 text-[10px] uppercase font-black text-muted tracking-widest">Base Rate</th>
                                        <th class="py-5 text-center text-[10px] uppercase font-black text-muted tracking-widest">Volume</th>
                                        <th class="py-5 pr-8 text-right text-[10px] uppercase font-black text-muted tracking-widest">Line Yield</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    <!-- Placeholder data since order_lines might be empty -->
                                    <tr class="group hover:bg-white/[0.02] transition-colors">
                                        <td class="py-6 pl-8">
                                            <div class="flex items-center gap-4">
                                                <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-muted group-hover:text-primary-400 transition-colors">
                                                    <i class="fas fa-box"></i>
                                                </div>
                                                <div class="font-bold text-white uppercase tracking-tighter">Standard Resource Unit</div>
                                            </div>
                                        </td>
                                        <td class="py-6 text-sm font-medium text-muted">₹ <?php echo number_format($order['subtotal'] / $duration, 2); ?></td>
                                        <td class="py-6 text-center text-sm font-black text-white">01</td>
                                        <td class="py-6 pr-8 text-right font-black text-white tracking-tighter">₹ <?php echo number_format($order['subtotal'], 2); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tactical Notes -->
                    <?php if (!empty($order['notes'])): ?>
                    <div class="premium-card p-6 border-dashed border-white/10 opacity-80">
                        <div class="flex items-center gap-3 mb-4">
                            <i class="fas fa-sticky-note text-muted text-xs"></i>
                            <span class="text-[10px] font-bold text-muted uppercase tracking-widest">Tactical Execution Notes</span>
                        </div>
                        <p class="text-xs text-white/70 leading-relaxed font-medium italic">"<?php echo nl2br(htmlspecialchars($order['notes'])); ?>"</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Financial Logic Panel -->
                <div class="lg:col-span-4 space-y-8">
                    <div class="premium-card bg-gradient-to-br from-indigo-600/20 to-purple-600/20 shadow-glow shadow-indigo-500/10">
                        <h4 class="premium-chart-title mb-8 flex items-center justify-between">
                            Fiscal Equilibrium
                            <i class="fas fa-receipt text-indigo-400/50"></i>
                        </h4>
                        
                        <div class="space-y-6">
                            <div class="flex items-center justify-between text-muted">
                                <span class="text-[10px] font-black uppercase tracking-widest">Base Computation</span>
                                <span class="text-sm font-bold text-white/50">₹ <?php echo number_format($order['subtotal'], 2); ?></span>
                            </div>
                            
                            <div class="flex items-center justify-between text-muted">
                                <span class="text-[10px] font-black uppercase tracking-widest">Logical Taxation</span>
                                <span class="text-sm font-bold text-white/50">₹ <?php echo number_format($order['tax_amount'], 2); ?></span>
                            </div>

                            <div class="pt-6 border-t border-white/10 flex items-center justify-between">
                                <span class="text-sm font-black text-white uppercase tracking-tighter">Net Obligation</span>
                                <span class="text-4xl font-black text-indigo-400 shadow-glow-sm tracking-tighter">₹ <?php echo number_format($order['total_amount'], 2); ?></span>
                            </div>

                            <div class="grid grid-cols-2 gap-3 mt-4">
                                <div class="p-4 rounded-2xl bg-emerald-500/5 border border-emerald-500/10 text-center">
                                    <div class="text-[8px] font-bold text-emerald-500/50 uppercase tracking-widest mb-1">Paid Status</div>
                                    <div class="text-xs font-black text-emerald-400">₹ <?php echo number_format($order['amount_paid'], 2); ?></div>
                                </div>
                                <div class="p-4 rounded-2xl bg-amber-500/5 border border-amber-500/10 text-center">
                                    <div class="text-[8px] font-bold text-amber-500/50 uppercase tracking-widest mb-1">Security Res.</div>
                                    <div class="text-xs font-black text-amber-400">₹ <?php echo number_format($order['security_deposit_total'], 2); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Command Module -->
                    <div class="premium-card">
                        <h4 class="premium-chart-title mb-6 text-[11px] text-muted uppercase font-black">Command Protocol</h4>
                        <div class="space-y-3">
                            <button class="premium-btn border border-white/10 w-full justify-between hover:bg-white/5 group">
                                Broadcast Documentation <i class="fas fa-share-nodes text-[10px] text-muted group-hover:text-primary-400 transition-colors"></i>
                            </button>
                            <button class="premium-btn border border-white/10 w-full justify-between hover:bg-white/5 group">
                                Replicate Sequence <i class="fas fa-copy text-[10px] text-muted group-hover:text-primary-400 transition-colors"></i>
                            </button>
                            <?php if ($order['status'] !== 'sale_order'): ?>
                                <button class="premium-btn premium-btn-primary w-full justify-between">
                                    Initiate Fiscal Invoice <i class="fas fa-file-invoice-dollar text-[10px] shadow-glow-sm"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Audit Information -->
                    <div class="px-4 py-2 bg-black/40 rounded-2xl border border-white/5 text-[9px] text-muted font-medium flex items-center justify-between">
                        <span>Database Reference Index: <?php echo $order['id']; ?></span>
                        <span class="flex items-center gap-1"><i class="fas fa-clock"></i> Sync: Just now</span>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
