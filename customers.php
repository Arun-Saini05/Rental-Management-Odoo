<?php
include 'config.php';

// Access Control
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_customer') {
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        
        // Check email
        $check_query = "SELECT id FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_get_result($stmt)->num_rows > 0) {
            $_SESSION['error'] = "Email already existence in our records.";
        } else {
            mysqli_begin_transaction($conn);
            try {
                $user_query = "INSERT INTO users (name, email, phone, address, role, created_at) VALUES (?, ?, ?, ?, 'customer', NOW())";
                $stmt = mysqli_prepare($conn, $user_query);
                mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $phone, $address);
                mysqli_stmt_execute($stmt);
                $user_id = mysqli_insert_id($conn);
                
                $insert_query = "INSERT INTO customers (user_id, created_at) VALUES (?, NOW())";
                $stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);
                
                mysqli_commit($conn);
                $_SESSION['success'] = "Customer profile established successfully.";
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $_SESSION['error'] = "Critical error during profile creation: " . $e->getMessage();
            }
        }
        header('Location: customers.php');
        exit();
    }
    
    if ($action === 'update_customer') {
        $id = sanitize($_POST['customer_id']);
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        
        $get_user_query = "SELECT user_id FROM customers WHERE id = ?";
        $stmt = mysqli_prepare($conn, $get_user_query);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $customer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        
        if ($customer) {
            $check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
            $stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($stmt, "si", $email, $customer['user_id']);
            mysqli_stmt_execute($stmt);
            if (mysqli_stmt_get_result($stmt)->num_rows > 0) {
                $_SESSION['error'] = "Conflicting email detected.";
            } else {
                $update_query = "UPDATE users SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "ssssi", $name, $email, $phone, $address, $customer['user_id']);
                mysqli_stmt_execute($stmt);
                $_SESSION['success'] = "Customer configuration updated.";
            }
        }
        header('Location: customers.php');
        exit();
    }
    
    if ($action === 'delete_customer') {
        $id = sanitize($_POST['customer_id']);
        mysqli_begin_transaction($conn);
        try {
            $get_user_query = "SELECT user_id FROM customers WHERE id = ?";
            $stmt = mysqli_prepare($conn, $get_user_query);
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $customer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            
            if ($customer) {
                mysqli_query($conn, "DELETE FROM customers WHERE id = $id");
                mysqli_query($conn, "DELETE FROM users WHERE id = {$customer['user_id']}");
                mysqli_commit($conn);
                $_SESSION['success'] = "Customer record archived.";
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "Action failed: " . $e->getMessage();
        }
        header('Location: customers.php');
        exit();
    }
}

// Search & Pagination
$search = sanitize($_GET['search'] ?? '');
$search_condition = $search ? "WHERE u.name LIKE '%$search%' OR u.email LIKE '%$search%'" : "";
$page = sanitize($_GET['page'] ?? 1);
$per_page = 12;
$offset = ($page - 1) * $per_page;

$total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM customers c LEFT JOIN users u ON c.user_id = u.id $search_condition"))['total'];
$total_pages = ceil($total_customers / $per_page);

$customers = [];
$query = "SELECT c.*, u.name, u.email, u.address, u.phone as u_phone, u.created_at 
          FROM customers c 
          LEFT JOIN users u ON c.user_id = u.id 
          $search_condition 
          ORDER BY u.created_at DESC 
          LIMIT $per_page OFFSET $offset";
$res = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($res)) $customers[] = $row;

$edit_customer = null;
if (isset($_GET['edit'])) {
    $id = sanitize($_GET['edit']);
    $edit_customer = mysqli_fetch_assoc(mysqli_query($conn, "SELECT c.*, u.name, u.email, u.address, u.phone FROM customers c LEFT JOIN users u ON c.user_id = u.id WHERE c.id = $id"));
}

$page_title = 'Client Directory - Rentify';
include 'header.php';
?>

<div class="h-screen overflow-hidden flex flex-col bg-primary">
    <main class="flex-1 overflow-y-auto custom-scrollbar">
        <div class="p-8 max-w-[1600px] mx-auto animate-fadeIn">
            <!-- Header -->
            <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-6 mb-10">
                <div>
                    <h1 class="text-3xl font-extrabold text-white flex items-center gap-3">
                        Client Directory
                        <span class="premium-badge premium-badge-primary"><?php echo $total_customers; ?> Profiles</span>
                    </h1>
                    <p class="text-muted mt-2">Centralized management of your verified rental customers</p>
                </div>
                
                <div class="flex flex-wrap items-center gap-4 bg-white/5 p-2 rounded-2xl border border-white/5 backdrop-blur-md">
                    <div class="relative group">
                        <input type="text" id="custSearch" placeholder="Find clients..." value="<?php echo htmlspecialchars($search); ?>"
                               class="premium-input pl-10 w-64 focus:w-80 h-10 text-sm">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-muted group-focus-within:text-primary-400 transition-colors"></i>
                    </div>
                    
                    <button onclick="openModal()" class="premium-btn premium-btn-primary h-10 px-6">
                        <i class="fas fa-user-plus mr-2 text-xs"></i>Add Client
                    </button>
                    
                    <div class="h-8 w-[1px] bg-white/10 mx-1"></div>
                    
                    <div class="flex bg-black/20 rounded-xl p-1">
                        <button onclick="setView('grid')" id="grid-view-btn" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/5 text-muted transition-all">
                            <i class="fas fa-th-large text-xs"></i>
                        </button>
                        <button onclick="setView('list')" id="list-view-btn" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/5 text-muted transition-all ml-1">
                            <i class="fas fa-list text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-2xl mb-8 flex items-center animate-slideIn">
                    <i class="fas fa-check-circle mr-3"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <!-- Grid View -->
            <div id="grid-view" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 animate-stagger">
                <?php foreach ($customers as $customer): ?>
                    <div class="premium-card user-card-premium group">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-14 h-14 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center font-black text-white text-xl shadow-glow group-hover:scale-110 transition-transform">
                                <?php echo strtoupper(substr($customer['name'], 0, 1)); ?>
                            </div>
                            <div class="overflow-hidden">
                                <h3 class="font-bold text-white truncate group-hover:text-primary-400 transition-colors"><?php echo htmlspecialchars($customer['name']); ?></h3>
                                <p class="text-[10px] text-muted tracking-widest uppercase mt-0.5">ID: #<?php echo $customer['id']; ?></p>
                            </div>
                        </div>
                        
                        <div class="space-y-3 mb-8">
                            <div class="flex items-center gap-3 group/item">
                                <div class="w-7 h-7 bg-white/5 rounded-lg flex items-center justify-center border border-white/5 group-hover/item:border-primary-500/30 transition-all">
                                    <i class="fas fa-envelope text-[10px] text-muted group-hover/item:text-primary-400"></i>
                                </div>
                                <span class="text-xs text-secondary truncate"><?php echo htmlspecialchars($customer['email']); ?></span>
                            </div>
                            <div class="flex items-center gap-3 group/item">
                                <div class="w-7 h-7 bg-white/5 rounded-lg flex items-center justify-center border border-white/5 group-hover/item:border-primary-500/30 transition-all">
                                    <i class="fas fa-phone text-[10px] text-muted group-hover/item:text-primary-400"></i>
                                </div>
                                <span class="text-xs text-secondary"><?php echo htmlspecialchars($customer['u_phone'] ?? 'Unlinked'); ?></span>
                            </div>
                        </div>
                        
                        <div class="pt-6 border-t border-white/5 flex items-center justify-between">
                            <span class="text-[10px] text-muted">Joined <?php echo date('M Y', strtotime($customer['created_at'])); ?></span>
                            <div class="flex gap-2">
                                <button onclick="editCustomer(<?php echo $customer['id']; ?>)" class="premium-notification w-7 h-7 hover:bg-primary-500/20">
                                    <i class="fas fa-pencil-alt text-[9px]"></i>
                                </button>
                                <button onclick="deleteCustomer(<?php echo $customer['id']; ?>)" class="premium-notification w-7 h-7 hover:bg-rose-500/20 text-rose-400">
                                    <i class="fas fa-trash text-[9px]"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- List View -->
            <div id="list-view" class="hidden animate-slideIn">
                <div class="premium-table-container">
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>Client Identity</th>
                                <th>Contact Information</th>
                                <th>Primary Location</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 bg-primary-500/10 rounded-xl flex items-center justify-center font-bold text-primary-400 text-sm">
                                                <?php echo strtoupper(substr($customer['name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="font-bold text-white"><?php echo htmlspecialchars($customer['name']); ?></div>
                                                <div class="text-[10px] text-muted mt-0.5">#<?php echo $customer['id']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-xs text-white"><?php echo htmlspecialchars($customer['email']); ?></div>
                                        <div class="text-[10px] text-muted mt-1"><?php echo htmlspecialchars($customer['u_phone'] ?? '-'); ?></div>
                                    </td>
                                    <td>
                                        <div class="text-[11px] text-secondary truncate max-w-xs"><?php echo htmlspecialchars($customer['address']); ?></div>
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <button onclick="editCustomer(<?php echo $customer['id']; ?>)" class="premium-notification w-8 h-8 hover:bg-primary-500/10">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            <button onclick="deleteCustomer(<?php echo $customer['id']; ?>)" class="premium-notification w-8 h-8 hover:bg-rose-500/10 text-rose-400">
                                                <i class="fas fa-trash-alt text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex justify-center mt-12 mb-20 animate-slideIn">
                    <nav class="flex gap-2 bg-white/5 p-1.5 rounded-2xl border border-white/5">
                        <?php if ($page > 1): ?>
                            <a href="?search=<?php echo $search; ?>&page=<?php echo $page-1; ?>" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white/5 hover:bg-primary-600 transition-all text-white">
                                <i class="fas fa-chevron-left text-xs"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i=1; $i<=$total_pages; $i++): ?>
                            <a href="?search=<?php echo $search; ?>&page=<?php echo $i; ?>" 
                               class="w-10 h-10 flex items-center justify-center rounded-xl transition-all <?php echo $i == $page ? 'bg-primary-600 text-white shadow-glow' : 'hover:bg-white/5 text-muted'; ?> text-xs font-bold">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?search=<?php echo $search; ?>&page=<?php echo $page+1; ?>" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white/5 hover:bg-primary-600 transition-all text-white">
                                <i class="fas fa-chevron-right text-xs"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Modal Overlay -->
<div class="premium-overlay" id="modalOverlay" onclick="closeModal()"></div>

<!-- User Modal -->
<div id="customerModal" class="premium-panel">
    <div class="premium-panel-header">
        <h3 class="premium-panel-title" id="modalTitle">Add Client</h3>
        <button onclick="closeModal()" class="premium-panel-close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="premium-panel-body">
        <form method="POST" id="customerForm" class="space-y-6">
            <input type="hidden" name="customer_id" id="customer_id">
            <input type="hidden" name="action" id="form_action" value="create_customer">
            
            <div class="space-y-2">
                <label class="premium-label">Legal Name</label>
                <input type="text" name="name" required class="premium-input" placeholder="e.g. Johnathan Doe">
            </div>
            
            <div class="space-y-2">
                <label class="premium-label">Digital Address (Email)</label>
                <input type="email" name="email" required class="premium-input" placeholder="client@example.com">
            </div>
            
            <div class="space-y-2">
                <label class="premium-label">Contact Number</label>
                <input type="tel" name="phone" class="premium-input" placeholder="+1 (555) 000-0000">
            </div>
            
            <div class="space-y-2">
                <label class="premium-label">Physical Residence</label>
                <textarea name="address" rows="3" class="premium-input" placeholder="Enter complete billing address..."></textarea>
            </div>
            
            <div class="pt-6">
                <button type="submit" class="w-full premium-btn premium-btn-primary">
                    Commit Profile Update
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function setView(view) {
        const grid = document.getElementById('grid-view');
        const list = document.getElementById('list-view');
        const gBtn = document.getElementById('grid-view-btn');
        const lBtn = document.getElementById('list-view-btn');
        
        if (view === 'grid') {
            grid.classList.remove('hidden');
            list.classList.add('hidden');
            gBtn.classList.add('active', 'bg-primary-600/20', 'text-primary-400');
            lBtn.classList.remove('active', 'bg-primary-600/20', 'text-primary-400');
        } else {
            grid.classList.add('hidden');
            list.classList.remove('hidden');
            lBtn.classList.add('active', 'bg-primary-600/20', 'text-primary-400');
            gBtn.classList.remove('active', 'bg-primary-600/20', 'text-primary-400');
        }
        localStorage.setItem('customerView', view);
    }

    function openModal() {
        document.getElementById('modalTitle').textContent = 'Profile Initialization';
        document.getElementById('form_action').value = 'create_customer';
        document.getElementById('customerForm').reset();
        document.getElementById('modalOverlay').classList.add('show');
        document.getElementById('customerModal').classList.add('open');
    }

    function closeModal() {
        document.getElementById('modalOverlay').classList.remove('show');
        document.getElementById('customerModal').classList.remove('open');
    }

    function editCustomer(id) {
        window.location.href = 'customers.php?edit=' + id;
    }

    function deleteCustomer(id) {
        if (confirm('Archive this customer profile permanently?')) {
            const f = document.createElement('form');
            f.method = 'POST';
            f.innerHTML = `<input type="hidden" name="action" value="delete_customer"><input type="hidden" name="customer_id" value="${id}">`;
            document.body.appendChild(f);
            f.submit();
        }
    }

    document.getElementById('custSearch').addEventListener('input', function(e) {
        if(e.target.value === "") window.location.href = 'customers.php';
    });
    
    document.getElementById('custSearch').addEventListener('keypress', function(e) {
        if(e.key === 'Enter') window.location.href='customers.php?search=' + this.value;
    });

    // Auto-view init
    const savedView = localStorage.getItem('customerView') || 'grid';
    setView(savedView);

    // Edit logic
    <?php if ($edit_customer): ?>
    document.addEventListener('DOMContentLoaded', () => {
        openModal();
        document.getElementById('modalTitle').textContent = 'Modify Client Profile';
        document.getElementById('form_action').value = 'update_customer';
        document.getElementById('customer_id').value = '<?php echo $edit_customer['id']; ?>';
        document.getElementsByName('name')[0].value = '<?php echo addslashes($edit_customer['name']); ?>';
        document.getElementsByName('email')[0].value = '<?php echo addslashes($edit_customer['email']); ?>';
        document.getElementsByName('phone')[0].value = '<?php echo addslashes($edit_customer['phone']); ?>';
        document.getElementsByName('address')[0].value = '<?php echo addslashes($edit_customer['address']); ?>';
    });
    <?php endif; ?>
</script>

<?php include 'footer.php'; ?>
</body>
</html>
