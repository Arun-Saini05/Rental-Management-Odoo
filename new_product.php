<?php
include 'config.php';

// Access Control
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_role = $_SESSION['user_role'] ?? 'vendor';
$vendor_id = $_SESSION['vendor_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Authorized User';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = sanitize($_POST['product_name'] ?? '');
    $product_type = sanitize($_POST['product_type'] ?? 'goods');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $sales_price = (float)($_POST['sales_price'] ?? 0);
    $cost_price = (float)($_POST['cost_price'] ?? 0);
    $unit = sanitize($_POST['unit'] ?? 'Units');
    $category = sanitize($_POST['category'] ?? '');
    $is_published = ($user_role === 'admin' && isset($_POST['is_published']) && $_POST['is_published'] == '1') ? 1 : 0;
    
    // Image Logic
    $image_path = '';
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/products/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_name = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES['product_image']['name']));
        $target_path = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_path)) $image_path = $target_path;
    }
    
    // Attributes Logic
    $attributes = [];
    if (isset($_POST['attribute_names']) && isset($_POST['attribute_values'])) {
        foreach ($_POST['attribute_names'] as $index => $attr_name) {
            if (!empty($attr_name) && !empty($_POST['attribute_values'][$index])) {
                $attributes[] = [
                    'name' => sanitize($attr_name),
                    'values' => array_map('trim', explode(',', $_POST['attribute_values'][$index]))
                ];
            }
        }
    }
    $attributes_json = !empty($attributes) ? json_encode($attributes) : null;
    
    $insert_query = "INSERT INTO products (name, type, quantity, sales_price, cost_price, unit, category, vendor_id, image, attributes, is_published, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($stmt, "ssididsssii", $product_name, $product_type, $quantity, $sales_price, $cost_price, $unit, $category, $vendor_id, $image_path, $attributes_json, $is_published);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Inventory item '$product_name' integrated successfully.";
        header('Location: products.php');
        exit();
    } else {
        $error_message = 'Protocol Failure: ' . mysqli_error($conn);
    }
}

$page_title = 'Inventory Integration - Rentify';
include 'header.php';
?>

<div class="h-screen overflow-hidden flex flex-col bg-primary">
    <!-- Action Header -->
    <div class="h-20 border-b border-white/5 bg-black/20 backdrop-blur-xl flex items-center justify-between px-8 z-20 animate-slideIn">
        <div class="flex items-center gap-6">
            <h1 class="text-xl font-black text-white uppercase tracking-tighter">New Resource Integration</h1>
            <div class="h-10 w-[1px] bg-white/10"></div>
            <div class="flex gap-4">
                <button type="button" onclick="switchTab('general')" class="premium-tab-btn active" data-tab="general">Operational Core</button>
                <button type="button" onclick="switchTab('attributes')" class="premium-tab-btn" data-tab="attributes">Visual Variants</button>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" form="product-form" class="premium-btn premium-btn-primary h-10 px-8">
                <i class="fas fa-microchip mr-2 text-xs"></i>Execute Integration
            </button>
            <a href="products.php" class="premium-notification h-10 w-10 hover:bg-rose-500/10 text-rose-400">
                <i class="fas fa-times text-sm"></i>
            </a>
        </div>
    </div>

    <main class="flex-1 overflow-y-auto custom-scrollbar">
        <div class="p-8 max-w-[1000px] mx-auto animate-fadeIn">
            <?php if (isset($error_message)): ?>
                <div class="bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-2xl mb-8 flex items-center">
                    <i class="fas fa-exclamation-triangle mr-3"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="product-form" enctype="multipart/form-data" class="space-y-8">
                <!-- General Section -->
                <div id="tab-general" class="tab-pane space-y-8 active">
                    <div class="premium-card">
                        <h4 class="premium-chart-title mb-8 flex items-center gap-2">
                            <i class="fas fa-fingerprint text-primary-400"></i> Identity & Visualization
                        </h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-6">
                                <div class="space-y-2">
                                    <label class="premium-label">Descriptive Designation *</label>
                                    <input type="text" name="product_name" class="premium-input h-12" placeholder="e.g. Neural Processor X1" required>
                                </div>
                                <div class="space-y-2">
                                    <label class="premium-label">Category Classification</label>
                                    <select name="category" class="premium-input h-12">
                                        <option value="Electronics">Electronics & Hardware</option>
                                        <option value="Furniture">Commercial Furniture</option>
                                        <option value="Clothing">Industrial Wear</option>
                                        <option value="Sports">Fitness Equipment</option>
                                        <option value="Other">Miscellaneous</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="space-y-2">
                                <label class="premium-label">Digital Representation (Image)</label>
                                <div class="relative group">
                                    <div class="w-full h-[124px] border-2 border-dashed border-white/10 rounded-2xl flex flex-col items-center justify-center bg-white/5 hover:bg-white/[0.08] transition-all cursor-pointer overflow-hidden p-4 text-center">
                                        <i class="fas fa-cloud-upload-alt text-2xl text-muted mb-2 group-hover:text-primary-400"></i>
                                        <span class="text-[10px] font-bold text-muted uppercase tracking-widest group-hover:text-white">Relay Binary Image</span>
                                        <input type="file" name="product_image" class="absolute inset-0 opacity-0 cursor-pointer" accept="image/*" onchange="previewImage(this)">
                                    </div>
                                    <div id="image-preview" class="hidden absolute inset-0 bg-primary rounded-2xl overflow-hidden border border-white/10">
                                        <img src="" class="w-full h-full object-cover">
                                        <button type="button" onclick="resetImage()" class="absolute top-2 right-2 w-6 h-6 bg-rose-500 rounded-lg flex items-center justify-center text-white text-[10px] shadow-lg"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="premium-card">
                        <h4 class="premium-chart-title mb-8 flex items-center gap-2">
                            <i class="fas fa-coins text-secondary-400"></i> Fiscal Parameters
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="space-y-2">
                                <label class="premium-label">Consumer Yield Rate *</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-muted text-xs">₹</span>
                                    <input type="number" name="sales_price" step="0.01" class="premium-input h-12 pl-8" placeholder="0.00" required>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="premium-label">Operational Overhead</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-muted text-xs">₹</span>
                                    <input type="number" name="cost_price" step="0.01" class="premium-input h-12 pl-8" placeholder="0.00">
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="premium-label">Unit Metric</label>
                                <select name="unit" class="premium-input h-12">
                                    <option value="Units">Per Instance</option>
                                    <option value="Days">Timed: Daily</option>
                                    <option value="Weeks">Timed: Weekly</option>
                                    <option value="Months">Timed: Monthly</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="premium-card">
                        <div class="flex items-center justify-between mb-8">
                            <h4 class="premium-chart-title m-0 flex items-center gap-2">
                                <i class="fas fa-cog text-muted"></i> System Visibility
                            </h4>
                            <div class="flex items-center gap-4">
                                <span class="text-[10px] font-bold text-muted uppercase tracking-widest">Public Protocol</span>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_published" value="1" class="sr-only peer" checked>
                                    <div class="w-11 h-6 bg-white/10 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-500"></div>
                                </label>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-4">
                                <label class="premium-label">Operational Logic</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="premium-option-card">
                                        <input type="radio" name="product_type" value="goods" checked class="hidden peer">
                                        <div class="p-4 rounded-xl border border-white/5 bg-white/5 peer-checked:border-primary-500/50 peer-checked:bg-primary-500/10 transition-all text-center cursor-pointer">
                                            <i class="fas fa-cube mb-2 block text-muted peer-checked:text-primary-400"></i>
                                            <span class="text-[10px] font-bold uppercase tracking-widest text-muted">Physical</span>
                                        </div>
                                    </label>
                                    <label class="premium-option-card">
                                        <input type="radio" name="product_type" value="service" class="hidden peer">
                                        <div class="p-4 rounded-xl border border-white/5 bg-white/5 peer-checked:border-secondary-500/50 peer-checked:bg-secondary-500/10 transition-all text-center cursor-pointer">
                                            <i class="fas fa-bolt mb-2 block text-muted peer-checked:text-secondary-400"></i>
                                            <span class="text-[10px] font-bold uppercase tracking-widest text-muted">Virtual</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="premium-label">Current Stock Equilibrium</label>
                                <input type="number" name="quantity" class="premium-input h-12 text-center" value="10">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attributes Section -->
                <div id="tab-attributes" class="tab-pane space-y-8 hidden">
                    <div class="premium-card p-0 overflow-hidden border-white/5">
                        <div class="p-6 border-b border-white/5 flex items-center justify-between">
                            <h4 class="premium-chart-title m-0 flex items-center gap-2">
                                <i class="fas fa-layer-group text-primary-400"></i> Dimensional Variants
                            </h4>
                            <button type="button" onclick="addAttribute()" class="text-[10px] font-black text-primary-400 uppercase tracking-widest hover:text-white transition-colors">
                                <i class="fas fa-plus-circle mr-1"></i> New Attribute
                            </button>
                        </div>
                        
                        <div id="attributes-container" class="p-6 space-y-4">
                            <div class="attribute-row-premium p-6 rounded-2xl bg-white/5 border border-white/5 group relative animate-fadeIn">
                                <div class="grid grid-cols-12 gap-6">
                                    <div class="col-span-12 md:col-span-4 space-y-2">
                                        <label class="premium-label text-[10px]">Variant Key</label>
                                        <input type="text" name="attribute_names[]" class="premium-input h-10 text-xs" placeholder="e.g. Chrome Intensity">
                                    </div>
                                    <div class="col-span-12 md:col-span-7 space-y-2">
                                        <label class="premium-label text-[10px]">Permitted Values (Comma Delimited)</label>
                                        <input type="text" name="attribute_values[]" class="premium-input h-10 text-xs" placeholder="e.g. Ultra, Matte, Neon">
                                    </div>
                                    <div class="col-span-12 md:col-span-1 flex items-end justify-end">
                                        <button type="button" onclick="this.closest('.attribute-row-premium').remove()" class="h-10 w-10 text-rose-500/50 hover:text-rose-500 hover:bg-rose-500/10 rounded-xl transition-all">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>
</div>

<style>
    .premium-tab-btn {
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: rgba(255,255,255,0.4);
        padding: 8px 16px;
        border-radius: 12px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid transparent;
    }
    .premium-tab-btn.active {
        color: white;
        background: rgba(99, 102, 241, 0.1);
        border-color: rgba(99, 102, 241, 0.2);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        text-shadow: 0 0 8px rgba(99, 102, 241, 0.5);
    }
    .premium-tab-btn:hover:not(.active) {
        color: white;
        background: rgba(255,255,255,0.05);
    }
</style>

<script>
    function switchTab(t) {
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));
        document.getElementById('tab-' + t).classList.remove('hidden');
        document.querySelectorAll('.premium-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelector(`[data-tab="${t}"]`).classList.add('active');
    }

    function addAttribute() {
        const container = document.getElementById('attributes-container');
        const row = container.querySelector('.attribute-row-premium').cloneNode(true);
        row.querySelectorAll('input').forEach(i => i.value = '');
        container.appendChild(row);
    }

    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('image-preview');
                preview.querySelector('img').src = e.target.result;
                preview.classList.remove('hidden');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function resetImage() {
        document.getElementById('image-preview').classList.add('hidden');
        document.querySelector('input[name="product_image"]').value = '';
    }
</script>

<?php include 'footer.php'; ?>
</body>
</html>
