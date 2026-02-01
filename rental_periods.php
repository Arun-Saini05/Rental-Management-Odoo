<?php
include 'config.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Handle Form Submission - Add Period
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_period') {
    $name = sanitize($_POST['name']);
    $duration = intval($_POST['duration']);
    $unit = sanitize($_POST['unit']);
    
    $query = "INSERT INTO rental_periods (name, duration, unit) VALUES ('$name', $duration, '$unit')";
    mysqli_query($conn, $query);
    header("Location: rental_periods.php");
    exit;
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_period') {
    $id = intval($_POST['period_id']);
    $query = "DELETE FROM rental_periods WHERE id = $id";
    mysqli_query($conn, $query);
    header("Location: rental_periods.php");
    exit;
}

// Handle Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_period') {
    $id = intval($_POST['period_id']);
    $name = sanitize($_POST['name']);
    $duration = intval($_POST['duration']);
    $unit = sanitize($_POST['unit']);
    
    $query = "UPDATE rental_periods SET name = '$name', duration = $duration, unit = '$unit' WHERE id = $id";
    mysqli_query($conn, $query);
    header("Location: rental_periods.php");
    exit;
}

// Fetch Rental Periods
$periods = [];
$result = mysqli_query($conn, "SELECT * FROM rental_periods ORDER BY duration ASC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $periods[] = $row;
    }
}

$page_title = 'Rental Durations - Rentify';
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
                        Rental Durations
                        <span class="premium-badge premium-badge-info"><?php echo count($periods); ?> Active</span>
                    </h1>
                    <p class="text-muted mt-2">Define and manage standard rental timeframes</p>
                </div>
                <button onclick="document.getElementById('newPeriodPanel').classList.add('open')" class="premium-btn premium-btn-primary">
                    <i class="fas fa-plus mr-2"></i> Create Duration
                </button>
            </div>

            <!-- Table Container -->
            <div class="premium-table-container animate-slideIn">
                <table class="premium-table">
                    <thead>
                        <tr>
                            <th>Label / Name</th>
                            <th>Value</th>
                            <th>Time Unit</th>
                            <th class="text-right">Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($periods)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-20 text-muted italic">
                                    No rental periods configuration found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($periods as $period): ?>
                                <tr>
                                    <td>
                                        <div class="font-bold text-white"><?php echo htmlspecialchars($period['name']); ?></div>
                                    </td>
                                    <td>
                                        <div class="premium-badge premium-badge-primary bg-primary-500/10"><?php echo htmlspecialchars($period['duration']); ?></div>
                                    </td>
                                    <td>
                                        <span class="text-secondary capitalize"><?php echo htmlspecialchars($period['unit']); ?></span>
                                    </td>
                                    <td class="text-right">
                                        <div class="premium-dropdown inline-block">
                                            <button class="w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all">
                                                <i class="fas fa-ellipsis-v text-xs text-muted"></i>
                                            </button>
                                            <div class="premium-dropdown-content">
                                                <button onclick="openEditPanel(<?php echo $period['id']; ?>, '<?php echo htmlspecialchars($period['name'], ENT_QUOTES); ?>', <?php echo $period['duration']; ?>, '<?php echo htmlspecialchars($period['unit'], ENT_QUOTES); ?>')" class="premium-dropdown-item">
                                                    <i class="fas fa-edit text-xs"></i>Edit Record
                                                </button>
                                                <div class="premium-dropdown-divider"></div>
                                                <form method="POST" onsubmit="return confirm('Archive this rental duration?');">
                                                    <input type="hidden" name="action" value="delete_period">
                                                    <input type="hidden" name="period_id" value="<?php echo $period['id']; ?>">
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
<div class="premium-overlay" id="overlay" onclick="closeAllPanels()"></div>

<!-- New Period Panel -->
<div id="newPeriodPanel" class="premium-panel">
    <div class="premium-panel-header">
        <h3 class="premium-panel-title">Add Duration</h3>
        <button onclick="closeAllPanels()" class="premium-panel-close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="premium-panel-body">
        <form method="POST" class="space-y-6">
            <input type="hidden" name="action" value="add_period">
            <div class="space-y-2">
                <label class="premium-label">Display Name</label>
                <input type="text" name="name" required class="premium-input" placeholder="e.g. Standard Week">
            </div>
            <div class="space-y-2">
                <label class="premium-label">Numerical Duration</label>
                <input type="number" name="duration" required min="1" class="premium-input" placeholder="e.g. 7">
            </div>
            <div class="space-y-2">
                <label class="premium-label">Time Metric</label>
                <select name="unit" class="premium-input premium-select">
                    <option value="Hours">Hours</option>
                    <option value="Days" selected>Days</option>
                    <option value="Weeks">Weeks</option>
                    <option value="Months">Months</option>
                    <option value="Years">Years</option>
                </select>
            </div>
            <div class="pt-6">
                <button type="submit" class="w-full premium-btn premium-btn-primary">
                    Confirm Creation
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Period Panel -->
<div id="editPeriodPanel" class="premium-panel">
    <div class="premium-panel-header">
        <h3 class="premium-panel-title">Modify Duration</h3>
        <button onclick="closeAllPanels()" class="premium-panel-close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="premium-panel-body">
        <form method="POST" class="space-y-6">
            <input type="hidden" name="action" value="edit_period">
            <input type="hidden" name="period_id" id="edit_period_id">
            <div class="space-y-2">
                <label class="premium-label">Update Name</label>
                <input type="text" name="name" id="edit_name" required class="premium-input">
            </div>
            <div class="space-y-2">
                <label class="premium-label">Update Duration</label>
                <input type="number" name="duration" id="edit_duration" required min="1" class="premium-input">
            </div>
            <div class="space-y-2">
                <label class="premium-label">Update Unit</label>
                <select name="unit" id="edit_unit" class="premium-input premium-select">
                    <option value="Hours">Hours</option>
                    <option value="Days">Days</option>
                    <option value="Weeks">Weeks</option>
                    <option value="Months">Months</option>
                    <option value="Years">Years</option>
                </select>
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
    function openEditPanel(id, name, duration, unit) {
        document.getElementById('edit_period_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_duration').value = duration;
        document.getElementById('edit_unit').value = unit;
        
        document.getElementById('overlay').classList.add('show');
        document.getElementById('editPeriodPanel').classList.add('open');
    }
    
    function closeAllPanels() {
        document.getElementById('overlay').classList.remove('show');
        document.querySelectorAll('.premium-panel').forEach(p => p.classList.remove('open'));
    }

    // New Period Logic
    const panelTrigger = document.querySelector('[onclick*="newPeriodPanel"]');
    if (panelTrigger) {
        panelTrigger.onclick = function() {
            document.getElementById('overlay').classList.add('show');
            document.getElementById('newPeriodPanel').classList.add('open');
        }
    }
</script>

<?php include 'footer.php'; ?>
</body>
</html>
