<?php
include 'config.php';

// Access Control - Strict Admin Only
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Handle Role Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_role') {
    $target_user_id = intval($_POST['user_id']);
    $new_role = sanitize($_POST['role']);
    
    // Safety check - cannot demote self
    if ($target_user_id !== $_SESSION['user_id']) {
        $allowed_roles = ['admin', 'vendor', 'customer'];
        if (in_array($new_role, $allowed_roles)) {
            $query = "UPDATE users SET role = '$new_role' WHERE id = $target_user_id";
            if(mysqli_query($conn, $query)) {
                $_SESSION['success'] = "Authority level recalibrated successfully.";
            }
        }
    }
    header("Location: users.php");
    exit;
}

// Fetch Users
$users = [];
$result = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at DESC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) $users[] = $row;
}

$page_title = 'Access Control - Rentify';
include 'header.php';
?>

<div class="h-screen overflow-hidden flex flex-col bg-primary">
    <div class="flex flex-1 overflow-hidden">
        <!-- Sidebar Navigation -->
        <aside class="w-72 border-r border-white/5 bg-black/20 backdrop-blur-xl flex flex-col animate-slideIn">
            <?php include 'settings_sidebar.php'; ?>
        </aside>

        <!-- Main Dynamic Area -->
        <main class="flex-1 overflow-y-auto custom-scrollbar">
            <div class="p-8 max-w-[1200px] mx-auto animate-fadeIn">
                <!-- Page Header -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
                    <div>
                        <h1 class="text-3xl font-extrabold text-white flex items-center gap-3">
                            Access Control
                            <span class="premium-badge premium-badge-primary">System Users</span>
                        </h1>
                        <p class="text-muted mt-2">Manage organizational roles and platform permissions</p>
                    </div>
                    
                    <div class="flex gap-3">
                        <button class="premium-notification hover:bg-primary-500/20" title="Export Audit Log">
                            <i class="fas fa-history text-sm"></i>
                        </button>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-2xl mb-8 flex items-center animate-slideIn">
                        <i class="fas fa-check-shield mr-3"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <!-- Users Matrix -->
                <div class="premium-card p-0 overflow-hidden border-white/5">
                    <div class="premium-table-container border-0 shadow-none">
                        <table class="premium-table">
                            <thead>
                                <tr>
                                    <th class="py-5 pl-8">Authenticated Identity</th>
                                    <th class="py-5">Permission Level</th>
                                    <th class="py-5">System Vitality</th>
                                    <th class="py-5 pr-8 text-right">Operations</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <?php foreach ($users as $user): ?>
                                    <tr class="group hover:bg-white/[0.02] transition-colors">
                                        <td class="py-4 pl-8">
                                            <div class="flex items-center gap-4">
                                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-600 to-indigo-700 flex items-center justify-center font-black text-white text-sm shadow-glow group-hover:scale-105 transition-transform">
                                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="font-bold text-white"><?php echo htmlspecialchars($user['name']); ?></div>
                                                    <div class="text-[10px] text-muted tracking-wide mt-0.5"><?php echo htmlspecialchars($user['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-4">
                                            <form method="POST" class="inline-block">
                                                <input type="hidden" name="action" value="update_role">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                
                                                <select name="role" onchange="this.form.submit()" 
                                                        class="bg-black/40 border border-white/10 rounded-xl px-3 py-1.5 text-[11px] font-bold tracking-wider uppercase focus:outline-none focus:border-primary-500/50 transition-all cursor-pointer <?php 
                                                            echo $user['role'] === 'admin' ? 'text-primary-400 border-primary-500/20' : 
                                                                ($user['role'] === 'vendor' ? 'text-purple-400 border-purple-500/20' : 'text-gray-400'); 
                                                        ?>" <?php echo $user['id'] === $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                                    <option value="admin" class="bg-gray-900" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>SYSTEM ADMIN</option>
                                                    <option value="vendor" class="bg-gray-900" <?php echo $user['role'] === 'vendor' ? 'selected' : ''; ?>>PARTNER VENDOR</option>
                                                    <option value="customer" class="bg-gray-900" <?php echo $user['role'] === 'customer' ? 'selected' : ''; ?>>END CUSTOMER</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td class="py-4">
                                            <div class="flex items-center gap-2">
                                                <span class="w-2 h-2 rounded-full bg-emerald-500 shadow-glow shadow-emerald-500/50 animate-pulse"></span>
                                                <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-widest">Active</span>
                                            </div>
                                        </td>
                                        <td class="py-4 pr-8 text-right">
                                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                                <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <button class="premium-notification w-8 h-8 hover:bg-rose-500/20 text-rose-400" title="Restrict Access">
                                                        <i class="fas fa-user-slash text-[10px]"></i>
                                                    </button>
                                                    <button class="premium-notification w-8 h-8 hover:bg-primary-500/20" title="Edit Profile">
                                                        <i class="fas fa-fingerprint text-[10px]"></i>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-[9px] text-muted italic font-medium px-3 py-1 bg-white/5 rounded-lg border border-white/5 select-none">Current Session</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Role Descriptions -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-10">
                    <div class="p-6 bg-white/5 rounded-2xl border border-white/10 backdrop-blur-md">
                        <div class="w-10 h-10 bg-primary-500/20 rounded-xl flex items-center justify-center mb-4 text-primary-400">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4 class="text-sm font-bold text-white mb-2">System Admin</h4>
                        <p class="text-xs text-muted leading-relaxed">Full recursive access to all system configurations, financial reports, and platform-wide monitoring.</p>
                    </div>
                    <div class="p-6 bg-white/5 rounded-2xl border border-white/10 backdrop-blur-md">
                        <div class="w-10 h-10 bg-purple-500/20 rounded-xl flex items-center justify-center mb-4 text-purple-400">
                            <i class="fas fa-store"></i>
                        </div>
                        <h4 class="text-sm font-bold text-white mb-2">Partner Vendor</h4>
                        <p class="text-xs text-muted leading-relaxed">Authority to manage specific product inventories, order fulfillment, and dedicated vendor performance metrics.</p>
                    </div>
                    <div class="p-6 bg-white/5 rounded-2xl border border-white/10 backdrop-blur-md">
                        <div class="w-10 h-10 bg-gray-500/20 rounded-xl flex items-center justify-center mb-4 text-gray-400">
                            <i class="fas fa-user-tag"></i>
                        </div>
                        <h4 class="text-sm font-bold text-white mb-2">End Customer</h4>
                        <p class="text-xs text-muted leading-relaxed">Baseline access for browsing product listings, managing personal rental history, and profile configuration.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'footer.php'; ?>
</body>
</html>
