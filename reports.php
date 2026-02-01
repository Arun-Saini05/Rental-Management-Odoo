<?php
include 'config.php';

// Access Control - Admin and Vendor only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'vendor'])) {
    header("Location: dashboard.php");
    exit;
}

$is_admin = $_SESSION['user_role'] === 'admin';
$page_title = 'Analytics & Reports - Rentify';
include 'header.php';
?>

<div class="h-screen overflow-hidden flex flex-col bg-primary">
    <main class="flex-1 overflow-y-auto custom-scrollbar">
        <div class="p-8 max-w-[1600px] mx-auto animate-fadeIn">
            <!-- Header Section -->
            <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-6 mb-10">
                <div>
                    <h1 class="text-3xl font-extrabold text-white flex items-center gap-3">
                        Business Intelligence
                        <span class="premium-badge premium-badge-primary">Live Data</span>
                    </h1>
                    <p class="text-muted mt-2">Analytical overview of revenue, orders, and performance metrics</p>
                </div>
                
                <div class="flex flex-wrap items-center gap-3 bg-white/5 p-2 rounded-2xl border border-white/5 backdrop-blur-md">
                    <div class="flex items-center gap-2 px-3">
                        <i class="fas fa-filter text-primary-400 text-sm"></i>
                        <select id="criteriaSelect" class="bg-transparent text-white font-bold text-sm focus:outline-none cursor-pointer">
                            <option value="revenue" class="bg-gray-900">Revenue Analysis</option>
                            <option value="orders_count" class="bg-gray-900">Orders Frequency</option>
                            <option value="product_sales" class="bg-gray-900">Top Performing Products</option>
                            <?php if ($is_admin): ?>
                            <option value="vendor_sales" class="bg-gray-900">Vendor Performance</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="h-8 w-[1px] bg-white/10 mx-2"></div>
                    
                    <div class="flex items-center gap-2">
                        <input type="date" id="startDate" class="bg-transparent text-white text-xs font-medium border-0 focus:ring-0 cursor-pointer">
                        <span class="text-muted text-[10px] uppercase">to</span>
                        <input type="date" id="endDate" class="bg-transparent text-white text-xs font-medium border-0 focus:ring-0 cursor-pointer">
                    </div>
                    
                    <div class="h-8 w-[1px] bg-white/10 mx-2"></div>
                    
                    <div class="flex gap-2">
                        <button onclick="window.print()" class="premium-notification hover:bg-primary-500/20" title="Print Report">
                            <i class="fas fa-print"></i>
                        </button>
                        <a href="export_csv.php" id="exportCsvBtn" class="premium-notification hover:bg-emerald-500/20" title="Export CSV">
                            <i class="fas fa-file-csv"></i>
                        </a>
                        <a href="export_excel.php" id="exportExcelBtn" class="premium-notification hover:bg-blue-500/20" title="Export Excel">
                            <i class="fas fa-file-excel"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Visualization Area -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Main Chart -->
                <div class="lg:col-span-8">
                    <div class="premium-chart-container h-[500px] flex flex-col animate-slideIn">
                        <div class="premium-chart-header">
                            <h4 class="premium-chart-title m-0 flex items-center gap-2">
                                <i class="fas fa-chart-line text-primary-400"></i>
                                Trend Analysis
                            </h4>
                            <div class="flex gap-2">
                                <div class="px-3 py-1 bg-primary-500/10 rounded-lg text-[10px] font-bold text-primary-400 border border-primary-500/20">PREMIUM ANALYTICS</div>
                            </div>
                        </div>
                        <div class="flex-1 relative mt-4">
                            <canvas id="reportChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Data Summary Sidebar -->
                <div class="lg:col-span-4">
                    <div class="premium-card h-full animate-slideIn" style="animation-delay: 0.1s">
                        <h4 class="premium-chart-title mb-6 flex items-center gap-2">
                            <i class="fas fa-list-ul text-secondary-400"></i>
                            Detailed Breakout
                        </h4>
                        
                        <div class="premium-table-container max-h-[400px] custom-scrollbar border-0 shadow-none">
                            <table class="premium-table" id="dataTable">
                                <thead>
                                    <tr>
                                        <th class="bg-transparent py-4 text-[10px]" id="labelHeader">Entity</th>
                                        <th class="bg-transparent py-4 text-right text-[10px]" id="valueHeader">Impact</th>
                                    </tr>
                                </thead>
                                <tbody id="dataTableBody">
                                    <!-- Dynamic Rows -->
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-auto pt-8">
                            <div class="p-4 bg-gradient-to-br from-indigo-500/10 to-purple-500/10 border border-white/5 rounded-2xl">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs text-muted">Total Cumulative</span>
                                    <span class="premium-badge premium-badge-success" id="summaryBadge">+14.2%</span>
                                </div>
                                <div class="text-3xl font-black text-white" id="summaryValue">₹0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
let reportChart = null;

document.addEventListener('DOMContentLoaded', function() {
    const today = new Date();
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    
    document.getElementById('endDate').value = today.toISOString().split('T')[0];
    document.getElementById('startDate').value = thirtyDaysAgo.toISOString().split('T')[0];
    
    loadReportData();
});

document.getElementById('criteriaSelect').addEventListener('change', loadReportData);
document.getElementById('startDate').addEventListener('change', loadReportData);
document.getElementById('endDate').addEventListener('change', loadReportData);

function loadReportData() {
    const criteria = document.getElementById('criteriaSelect').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    document.getElementById('exportCsvBtn').href = `export_csv.php?criteria=${criteria}&start_date=${startDate}&end_date=${endDate}`;
    document.getElementById('exportExcelBtn').href = `export_excel.php?criteria=${criteria}&start_date=${startDate}&end_date=${endDate}`;
    
    fetch(`fetch_report_data.php?criteria=${criteria}&start_date=${startDate}&end_date=${endDate}`)
        .then(response => response.json())
        .then(data => {
            updateChart(data, criteria);
            updateTable(data, criteria);
        });
}

function updateChart(data, criteria) {
    const ctx = document.getElementById('reportChart').getContext('2d');
    if (reportChart) reportChart.destroy();
    
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(99, 102, 241, 0.4)');
    gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');

    reportChart = new Chart(ctx, {
        type: (criteria === 'revenue' || criteria === 'orders_count') ? 'line' : 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: getDatasetLabel(criteria),
                data: data.values,
                backgroundColor: (criteria === 'revenue' || criteria === 'orders_count') ? gradient : 'rgba(99, 102, 241, 0.6)',
                borderColor: '#6366f1',
                borderWidth: (criteria === 'revenue' || criteria === 'orders_count') ? 3 : 0,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#6366f1',
                pointBorderColor: 'rgba(255,255,255,0.2)',
                pointBorderWidth: 4,
                pointRadius: 4,
                pointHoverRadius: 6,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 15, 35, 0.95)',
                    titleColor: '#fff',
                    bodyColor: '#a5b4fc',
                    borderColor: 'rgba(255,255,255,0.1)',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 12,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return formatValue(context.raw, criteria);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: 'rgba(255,255,255,0.3)', font: { size: 10 } }
                },
                y: {
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: { 
                        color: 'rgba(255,255,255,0.3)', 
                        font: { size: 10 },
                        callback: function(value) { return formatValue(value, criteria, true); }
                    }
                }
            }
        }
    });
}

function updateTable(data, criteria) {
    const tbody = document.getElementById('dataTableBody');
    const labelHeader = document.getElementById('labelHeader');
    const valueHeader = document.getElementById('valueHeader');
    let total = 0;
    
    switch(criteria) {
        case 'revenue': labelHeader.textContent = 'Date Period'; valueHeader.textContent = 'Earnings'; break;
        case 'orders_count': labelHeader.textContent = 'Date Period'; valueHeader.textContent = 'Volume'; break;
        case 'product_sales': labelHeader.textContent = 'Premium Item'; valueHeader.textContent = 'Contribution'; break;
        case 'vendor_sales': labelHeader.textContent = 'Partner'; valueHeader.textContent = 'Performance'; break;
    }
    
    tbody.innerHTML = '';
    data.labels.forEach((label, i) => {
        const val = data.values[i];
        total += parseFloat(val);
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="py-3 text-xs font-medium text-white/80">${label}</td>
            <td class="py-3 text-right text-xs font-bold text-white">${formatValue(val, criteria)}</td>
        `;
        tbody.appendChild(row);
    });
    
    document.getElementById('summaryValue').textContent = formatValue(total, criteria);
}

function getDatasetLabel(criteria) {
    switch(criteria) {
        case 'revenue': return 'Revenue Trend';
        case 'orders_count': return 'Order Volume';
        case 'product_sales': return 'Product Contribution';
        case 'vendor_sales': return 'Vendor Performance';
        default: return 'Business Data';
    }
}

function formatValue(value, criteria, short = false) {
    if (criteria === 'orders_count') return value + (short ? '' : ' Orders');
    if (short && value >= 1000) return '₹' + (value/1000).toFixed(1) + 'k';
    return '₹' + parseFloat(value).toLocaleString('en-IN');
}
</script>

<style>
@media print {
    .premium-header, .premium-sidebar, .no-print { display: none !important; }
    body { background: white !important; color: black !important; }
    .premium-card, .premium-chart-container { 
        background: white !important; 
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        color: black !important;
    }
    .text-white, .text-muted, .text-primary-400 { color: black !important; }
    canvas { max-width: 100% !important; height: auto !important; }
}
</style>

<?php include 'footer.php'; ?>
</body>
</html>
