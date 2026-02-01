<?php
include 'config.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Handle Form Submission - Add Attribute
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_attribute') {
    $name = sanitize($_POST['name']);
    $display_type = sanitize($_POST['display_type']);
    $values_str = sanitize($_POST['values']); // Comma separated
    
    // Process values into JSON
    $values_array = array_map('trim', explode(',', $values_str));
    $values_json = json_encode($values_array);
    
    $query = "INSERT INTO attributes (name, display_type, `values`) VALUES ('$name', '$display_type', '$values_json')";
    mysqli_query($conn, $query);
    header("Location: attributes.php");
    exit;
}

// Handle Delete Attribute
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_attribute') {
    $id = intval($_POST['attr_id']);
    $query = "DELETE FROM attributes WHERE id = $id";
    mysqli_query($conn, $query);
    header("Location: attributes.php");
    exit;
}

// Handle Edit Attribute
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_attribute') {
    $id = intval($_POST['attr_id']);
    $name = sanitize($_POST['name']);
    $display_type = sanitize($_POST['display_type']);
    $values_str = sanitize($_POST['values']);
    
    $values_array = array_map('trim', explode(',', $values_str));
    $values_json = json_encode($values_array);
    
    $query = "UPDATE attributes SET name = '$name', display_type = '$display_type', `values` = '$values_json' WHERE id = $id";
    mysqli_query($conn, $query);
    header("Location: attributes.php");
    exit;
}

// Fetch Attributes
$attributes = [];
$result = mysqli_query($conn, "SELECT * FROM attributes ORDER BY id DESC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $attributes[] = $row;
    }
}

$page_title = 'Product Attributes - Rentify';
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
                        Product Attributes
                        <span class="premium-badge premium-badge-primary"><?php echo count($attributes); ?> Defined</span>
                    </h1>
                    <p class="text-muted mt-2">Manage characteristics like color, size, and material labels</p>
                </div>
                <button onclick="document.getElementById('newAttributePanel').classList.add('open')" class="premium-btn premium-btn-primary">
                    <i class="fas fa-plus mr-2"></i> New Attribute
                </button>
            </div>

            <!-- Table Container -->
            <div class="premium-table-container !overflow-visible animate-slideIn">
                <table class="premium-table">
                    <thead>
                        <tr>
                            <th>Attribute Identity</th>
                            <th>Display Style</th>
                            <th>Available Options</th>
                            <th class="text-right">Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attributes)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-20 text-muted italic">
                                    No attributes defined. Organize your inventory with custom properties.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($attributes as $attr): ?>
                                <tr>
                                    <td>
                                        <div class="font-bold text-white"><?php echo htmlspecialchars($attr['name']); ?></div>
                                    </td>
                                    <td>
                                        <span class="premium-badge premium-badge-secondary bg-white/5 uppercase text-[10px] tracking-widest px-3 border border-white/10">
                                            <?php echo htmlspecialchars($attr['display_type']); ?>
                                        </span>
                                    </td>
                                    <td class="max-w-xs">
                                        <div class="flex flex-wrap gap-1">
                                            <?php 
                                            $vals = json_decode($attr['values'], true);
                                            if (is_array($vals)) {
                                                foreach (array_slice($vals, 0, 4) as $v) {
                                                    echo '<span class="text-[10px] bg-primary-500/10 text-primary-300 px-2 py-0.5 rounded-full border border-primary-500/20">'.htmlspecialchars($v).'</span>';
                                                }
                                                if (count($vals) > 4) {
                                                    echo '<span class="text-[10px] text-muted ml-1">+ '.(count($vals)-4).' more</span>';
                                                }
                                            } else {
                                                echo '<span class="text-muted">-</span>';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        <div class="premium-dropdown inline-block">
                                            <button class="w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all">
                                                <i class="fas fa-ellipsis-v text-xs text-muted"></i>
                                            </button>
                                            <div class="premium-dropdown-content">
                                                <button onclick="openEditAttrPanel(<?php echo $attr['id']; ?>, '<?php echo htmlspecialchars($attr['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($attr['display_type'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars(implode(', ', json_decode($attr['values'], true)), ENT_QUOTES); ?>')" class="premium-dropdown-item">
                                                    <i class="fas fa-edit text-xs"></i>Edit Profile
                                                </button>
                                                <div class="premium-dropdown-divider"></div>
                                                <form method="POST" onsubmit="return confirm('Permanently remove this attribute?');">
                                                    <input type="hidden" name="action" value="delete_attribute">
                                                    <input type="hidden" name="attr_id" value="<?php echo $attr['id']; ?>">
                                                    <button type="submit" class="premium-dropdown-item text-red-400">
                                                        <i class="fas fa-trash-alt text-xs"></i>Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Sliding Panels -->
<div class="premium-overlay" id="overlay_attr" onclick="closeAllAttrPanels()"></div>

<!-- New Attribute Panel -->
<div id="newAttributePanel" class="premium-panel">
    <div class="premium-panel-header">
        <h3 class="premium-panel-title">Add Attribute</h3>
        <button onclick="closeAllAttrPanels()" class="premium-panel-close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="premium-panel-body">
        <form method="POST" class="space-y-6">
            <input type="hidden" name="action" value="add_attribute">
            <div class="space-y-2">
                <label class="premium-label">Attribute Name</label>
                <input type="text" name="name" required class="premium-input" placeholder="e.g. Material Type">
            </div>
            
            <div class="space-y-2">
                <label class="premium-label">UX Display Mode</label>
                <div class="grid grid-cols-3 gap-2">
                    <label class="cursor-pointer">
                        <input type="radio" name="display_type" value="Pills" checked class="peer sr-only">
                        <div class="text-[11px] py-1.5 border border-white/10 rounded-xl text-center peer-checked:bg-primary-600 peer-checked:border-primary-500 transition-all font-bold">PILLS</div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="display_type" value="Radio" class="peer sr-only">
                        <div class="text-[11px] py-1.5 border border-white/10 rounded-xl text-center peer-checked:bg-primary-600 peer-checked:border-primary-500 transition-all font-bold">RADIO</div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="display_type" value="Dropdown" class="peer sr-only">
                        <div class="text-[11px] py-1.5 border border-white/10 rounded-xl text-center peer-checked:bg-primary-600 peer-checked:border-primary-500 transition-all font-bold">SELECT</div>
                    </label>
                </div>
            </div>

            <div class="space-y-2">
                <label class="premium-label">Value Dataset</label>
                <textarea name="values" rows="4" required class="premium-input" placeholder="Enter options, separated by commas (e.g. Wood, Metal, Plastic)"></textarea>
                <p class="text-[10px] text-muted italic">Options will be rendered as interactive selection elements.</p>
            </div>
            
            <div class="pt-6">
                <button type="submit" class="w-full premium-btn premium-btn-primary">
                    Create Dataset
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Attribute Panel -->
<div id="editAttributePanel" class="premium-panel">
    <div class="premium-panel-header">
        <h3 class="premium-panel-title">Modify Attribute</h3>
        <button onclick="closeAllAttrPanels()" class="premium-panel-close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="premium-panel-body">
        <form method="POST" class="space-y-6">
            <input type="hidden" name="action" value="edit_attribute">
            <input type="hidden" name="attr_id" id="edit_attr_id">
            
            <div class="space-y-2">
                <label class="premium-label">Update Name</label>
                <input type="text" name="name" id="edit_attr_name" required class="premium-input">
            </div>
            
            <div class="space-y-2">
                <label class="premium-label">Update UX Style</label>
                <div class="grid grid-cols-3 gap-2">
                    <label class="cursor-pointer">
                        <input type="radio" name="display_type" id="edit_display_pills" value="Pills" class="peer sr-only">
                        <div class="text-[11px] py-1.5 border border-white/10 rounded-xl text-center peer-checked:bg-primary-600 peer-checked:border-primary-500 transition-all font-bold">PILLS</div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="display_type" id="edit_display_radio" value="Radio" class="peer sr-only">
                        <div class="text-[11px] py-1.5 border border-white/10 rounded-xl text-center peer-checked:bg-primary-600 peer-checked:border-primary-500 transition-all font-bold">RADIO</div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="display_type" id="edit_display_dropdown" value="Dropdown" class="peer sr-only">
                        <div class="text-[11px] py-1.5 border border-white/10 rounded-xl text-center peer-checked:bg-primary-600 peer-checked:border-primary-500 transition-all font-bold">SELECT</div>
                    </label>
                </div>
            </div>

            <div class="space-y-2">
                <label class="premium-label">Update Options</label>
                <textarea name="values" id="edit_attr_values" rows="4" required class="premium-input"></textarea>
            </div>
            
            <div class="pt-6">
                <button type="submit" class="w-full premium-btn premium-btn-primary">
                    Apply Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditAttrPanel(id, name, display_type, values) {
        document.getElementById('edit_attr_id').value = id;
        document.getElementById('edit_attr_name').value = name;
        document.getElementById('edit_attr_values').value = values;
        
        // Handle radio buttons
        if (display_type === 'Pills') document.getElementById('edit_display_pills').checked = true;
        else if (display_type === 'Radio') document.getElementById('edit_display_radio').checked = true;
        else if (display_type === 'Dropdown') document.getElementById('edit_display_dropdown').checked = true;
        
        document.getElementById('overlay_attr').classList.add('show');
        document.getElementById('editAttributePanel').classList.add('open');
    }

    function closeAllAttrPanels() {
        document.getElementById('overlay_attr').classList.remove('show');
        document.querySelectorAll('.premium-panel').forEach(p => p.classList.remove('open'));
    }

    // New Attribute Logic
    const attrPanelTrigger = document.querySelector('[onclick*="newAttributePanel"]');
    if (attrPanelTrigger) {
        attrPanelTrigger.onclick = function() {
            document.getElementById('overlay_attr').classList.add('show');
            document.getElementById('newAttributePanel').classList.add('open');
        }
    }
</script>

<?php include 'footer.php'; ?>
</body>
</html>
