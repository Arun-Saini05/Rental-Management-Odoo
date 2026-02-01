<?php
include 'config.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Fetch Settings
$settings = [];
$result = mysqli_query($conn, "SELECT * FROM settings WHERE id = 1");
if ($result && mysqli_num_rows($result) > 0) {
    $settings = mysqli_fetch_assoc($result);
} else {
    $settings = ['company_name' => '', 'email' => '', 'phone' => '', 'gstin' => '', 'address' => '', 'logo' => ''];
}

// Fetch current user
$user_id = $_SESSION['user_id'];
$current_user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));

$page_title = 'System Settings - Rentify';
include 'header.php';
?>

<div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <?php include 'settings_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto bg-primary custom-scrollbar">
        <div class="p-8 max-w-[1400px] mx-auto animate-fadeIn">
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-10">
                <div>
                    <h1 class="text-3xl font-extrabold text-white flex items-center gap-3">
                        General Settings
                        <span class="premium-badge premium-badge-primary">Admin Only</span>
                    </h1>
                    <p class="text-muted mt-2">Configure core business information and security preferences</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Left Column: Form -->
                <div class="lg:col-span-8 space-y-8">
                    <form action="save_settings.php" method="POST" enctype="multipart/form-data" class="premium-card">
                        <div class="flex items-center justify-between mb-8">
                            <h3 class="premium-chart-title m-0">Business Identity</h3>
                            <button type="submit" class="premium-btn premium-btn-primary">
                                <i class="fas fa-save mr-2"></i>Update Workspace
                            </button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Logo Upload -->
                            <div class="md:col-span-2 flex items-center gap-8 p-6 bg-white/5 border border-white/5 rounded-2xl group transition-all hover:border-primary-500/30">
                                <div class="relative w-32 h-32 flex-shrink-0">
                                    <div class="w-full h-full bg-primary-900/30 rounded-2xl flex items-center justify-center overflow-hidden border-2 border-dashed border-white/20 group-hover:border-primary-500/50 transition-all">
                                        <?php if (!empty($settings['logo'])): ?>
                                            <img src="uploads/logo/<?php echo htmlspecialchars($settings['logo']); ?>" alt="Logo" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <i class="fas fa-image text-4xl text-white/20"></i>
                                        <?php endif; ?>
                                    </div>
                                    <label class="absolute inset-0 flex items-center justify-center bg-primary-500/60 opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer rounded-2xl text-white">
                                        <i class="fas fa-camera text-xl"></i>
                                        <input type="file" name="company_logo" class="hidden" accept="image/*">
                                    </label>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-bold text-white mb-2">Workspace Identifier</h4>
                                    <p class="text-xs text-muted leading-relaxed">This logo will be displayed on all invoices, reports, and tenant portals. Recommended size: 512x512px.</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="premium-label">Legal Company Name</label>
                                <input type="text" name="company_name" value="<?php echo htmlspecialchars($settings['company_name']); ?>" class="premium-input" placeholder="e.g. Rentify Solutions Ltd">
                            </div>
                            
                            <div class="space-y-2">
                                <label class="premium-label">Identification (GSTIN/TAX)</label>
                                <input type="text" name="gstin" value="<?php echo htmlspecialchars($settings['gstin']); ?>" class="premium-input" placeholder="Enter Registration No">
                            </div>
                            
                            <div class="space-y-2">
                                <label class="premium-label">Official Email</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($settings['email']); ?>" class="premium-input" placeholder="support@company.com">
                            </div>
                            
                            <div class="space-y-2">
                                <label class="premium-label">Support Helpline</label>
                                <input type="text" name="phone" value="<?php echo htmlspecialchars($settings['phone']); ?>" class="premium-input" placeholder="+1 (555) 000-0000">
                            </div>
                            
                            <div class="md:col-span-2 space-y-2">
                                <label class="premium-label">Registered Headquarters</label>
                                <textarea name="address" rows="3" class="premium-input" placeholder="Enter complete physical address..."><?php echo htmlspecialchars($settings['address']); ?></textarea>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Right Column -->
                <div class="lg:col-span-4 space-y-8">
                    <!-- Identity Badge -->
                    <div class="premium-card bg-gradient-to-br from-primary-600/20 to-secondary-600/20 border-primary-500/30 overflow-hidden group">
                        <div class="relative z-10">
                            <div class="flex items-center gap-5 mb-6">
                                <div class="w-16 h-16 bg-gradient-to-tr from-primary-500 to-primary-700 rounded-2xl flex items-center justify-center text-2xl font-black text-white shadow-glow group-hover:scale-105 transition-transform">
                                    <?php echo strtoupper(substr($current_user['name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-white"><?php echo htmlspecialchars($current_user['name']); ?></h3>
                                    <p class="text-xs text-primary-300"><?php echo htmlspecialchars($current_user['email']); ?></p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <span class="premium-badge premium-badge-primary"><?php echo strtoupper($current_user['role']); ?></span>
                                <span class="premium-badge premium-badge-success">Verified Admin</span>
                            </div>
                        </div>
                        <div class="absolute -bottom-6 -right-6 text-9xl text-white/5 font-black italic">ROOT</div>
                    </div>

                    <!-- Enhanced Tabs -->
                    <div class="premium-card p-0 overflow-hidden">
                        <div class="premium-tabs border-0 rounded-none bg-white/5 p-1">
                            <button onclick="showTab('work')" id="tab-work" class="premium-tab flex-1 active">
                                <i class="fas fa-briefcase mr-2 text-xs"></i>Work Info
                            </button>
                            <button onclick="showTab('security')" id="tab-security" class="premium-tab flex-1">
                                <i class="fas fa-shield-alt mr-2 text-xs"></i>Security
                            </button>
                        </div>
                        
                        <div class="p-6">
                            <!-- Work Tab -->
                            <div id="content-work" class="tab-content">
                                <label class="premium-label mb-4 opacity-70">Current Simulation Role</label>
                                <div class="grid grid-cols-3 gap-2 mb-8">
                                    <label class="cursor-pointer">
                                        <input type="radio" name="sim_role" value="admin" class="peer sr-only" checked>
                                        <div class="text-[10px] py-1 px-2 border border-white/10 rounded-lg text-center peer-checked:bg-primary-600 peer-checked:border-primary-500 transition-all font-bold">ADMIN</div>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="sim_role" value="vendor" class="peer sr-only">
                                        <div class="text-[10px] py-1 px-2 border border-white/10 rounded-lg text-center peer-checked:bg-emerald-600 peer-checked:border-emerald-500 transition-all font-bold">VENDOR</div>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="sim_role" value="customer" class="peer sr-only">
                                        <div class="text-[10px] py-1 px-2 border border-white/10 rounded-lg text-center peer-checked:bg-cyan-600 peer-checked:border-cyan-500 transition-all font-bold">USER</div>
                                    </label>
                                </div>
                                
                                <div class="bg-primary-900/40 border border-primary-500/20 rounded-2xl p-4">
                                    <p class="text-[11px] text-primary-300 leading-relaxed italic">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Full system settings access is restricted to root administrators. Standard users will see a modified profile view.
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Security Tab -->
                            <div id="content-security" class="tab-content hidden">
                                <form action="change_password.php" method="POST" class="space-y-4">
                                    <div class="space-y-1">
                                        <label class="text-[11px] text-muted ml-1">Current Credential</label>
                                        <input type="password" name="current_password" required class="premium-input py-2 text-sm bg-black/20">
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[11px] text-muted ml-1">Target Credential</label>
                                        <input type="password" name="new_password" required class="premium-input py-2 text-sm bg-black/20">
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[11px] text-muted ml-1">Confirm Target</label>
                                        <input type="password" name="confirm_password" required class="premium-input py-2 text-sm bg-black/20">
                                    </div>
                                    <button type="submit" class="w-full premium-btn-secondary py-2 text-[11px] mt-4">
                                        Commit Secure Update
                                    </button>
                                    
                                    <?php if (isset($_GET['pwd_error'])): ?>
                                        <div class="text-[10px] text-rose-400 bg-rose-400/10 p-2 rounded-lg text-center mt-4">
                                            <?php echo htmlspecialchars($_GET['pwd_error']); ?>
                                        </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    function showTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.premium-tab').forEach(el => el.classList.remove('active'));
        
        document.getElementById('content-' + tabName).classList.remove('hidden');
        document.getElementById('tab-' + tabName).classList.add('active');
    }
</script>

<?php include 'footer.php'; ?>
</body>
</html>
