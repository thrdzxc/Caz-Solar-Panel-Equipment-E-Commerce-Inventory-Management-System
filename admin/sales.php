<?php
require_once __DIR__ . '/../includes/admin_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include "../db_conn.php"; 

// 1. Calculate Summary Stats (Revenue, Order Count, and Daily Average)
$stats_query = "SELECT
                    SUM(unique_sales.total_sales) as total_revenue,
                    COUNT(unique_sales.order_id)  as total_orders,
                    (
                        SELECT AVG(day_total)
                        FROM (
                            SELECT SUM(us2.total_sales) as day_total
                            FROM (
                                SELECT order_id, total_sales, DATE(date) as sale_date
                                FROM sales
                                GROUP BY order_id
                            ) us2
                            GROUP BY us2.sale_date
                        ) as daily_totals
                    ) as daily_avg
                FROM (
                    SELECT order_id, total_sales
                    FROM sales
                    GROUP BY order_id
                ) as unique_sales";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// 2. Fetch Detailed Sales for the Table
$sales_query = "SELECT 
                    s.id as sale_id,
                    s.date,
                    GROUP_CONCAT(DISTINCT p.product_name ORDER BY p.product_name SEPARATOR ', ') as product_name,
                    GROUP_CONCAT(DISTINCT COALESCE(p.category, 'N/A') ORDER BY p.category SEPARATOR ', ') as category,
                    SUM(oi.quantity) as quantity,
                    s.total_sales / NULLIF(SUM(oi.quantity), 0) as price,
                    s.total_sales
                FROM sales s
                JOIN orders o ON s.order_id = o.id
                LEFT JOIN order_items oi ON o.id = oi.order_id
                LEFT JOIN products p ON oi.product_id = p.id
                GROUP BY s.id, s.date, s.total_sales
                ORDER BY s.date DESC";
$result = mysqli_query($conn, $sales_query);

// 3. Fetch CCTV sales by date
$cctv_chart_query = "SELECT DATE(s.date) as sale_date, SUM(s.total_sales) as total
                     FROM sales s
                     JOIN orders o ON s.order_id = o.id
                     JOIN order_items oi ON o.id = oi.order_id
                     JOIN products p ON oi.product_id = p.id
                     WHERE p.category = 'CCTV'
                     GROUP BY DATE(s.date)
                     ORDER BY sale_date ASC";
$cctv_chart_result = mysqli_query($conn, $cctv_chart_query);
$cctv_labels = []; $cctv_data = [];
while($row = mysqli_fetch_assoc($cctv_chart_result)) {
    $cctv_labels[] = date('M d', strtotime($row['sale_date']));
    $cctv_data[]   = $row['total'];
}

// 4. Fetch Solar sales by date
$solar_chart_query = "SELECT DATE(s.date) as sale_date, SUM(s.total_sales) as total
                      FROM sales s
                      JOIN orders o ON s.order_id = o.id
                      JOIN order_items oi ON o.id = oi.order_id
                      JOIN products p ON oi.product_id = p.id
                      WHERE p.category = 'Solar'
                      GROUP BY DATE(s.date)
                      ORDER BY sale_date ASC";
$solar_chart_result = mysqli_query($conn, $solar_chart_query);
$solar_labels = []; $solar_data = [];
while($row = mysqli_fetch_assoc($solar_chart_result)) {
    $solar_labels[] = date('M d', strtotime($row['sale_date']));
    $solar_data[]   = $row['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Dashboard - Caz Admin</title>
    <link rel="stylesheet" href="css/admin-style.css?v=20260910-release">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .date-box {
            display: flex; align-items: center; gap: 8px;
            background: #1e1e1e; border: 1px solid #333;
            border-radius: 6px; padding: 1px 12px;
        }
        .date-box i { color: #888; font-size: 13px; }
        .date-box input[type="date"] {
            background: transparent; border: none;
            outline: none; color: #fff;
            font-size: 13px; cursor: pointer; color-scheme: dark;
        }

        /* Export buttons */
        .export-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 6px; border: none;
            font-size: 13px; font-weight: 600; cursor: pointer;
            transition: opacity 0.2s, transform 0.1s;
        }
        .export-btn:hover  { opacity: 0.85; }
        .export-btn:active { transform: scale(0.97); }
        .btn-csv  { background: #2e7d32; color: #fff; }
        .btn-pdf  { background: #c62828; color: #fff; }
    </style>
<link rel="stylesheet" href="../css/refined.css?v=20260920-shopping">
<script defer src="../js/refined.js?v=20260910-release"></script>
<link rel="stylesheet" href="../css/experience.css?v=20260921-location"><script defer src="../js/experience.js?v=20260926-teal"></script>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../css/background-theme.css?v=20260926-teal">
<link rel="stylesheet" href="../css/glass-panels.css?v=20260926-teal">
</head>
<body class="caz-admin caz-themed">
<?php require __DIR__ . '/../includes/background.php'; ?>

<?php include __DIR__ . '/../includes/navigation.php'; ?>

<div class="page-content">
    <div class="sales-wrapper"><div class="caz-page-heading"><div><span class="caz-eyebrow">Store management</span><h1>Sales overview</h1><p>See your store performance and recent transactions.</p></div></div>
        
        <div class="sales-stats-grid">
            <div class="stat-card green">
                <i class="fas fa-coins"></i>
                <div class="stat-info">
                    <span class="stat-label">Total Revenue</span>
                    <span class="stat-value">₱<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></span>
                </div>
            </div>
            <div class="stat-card yellow">
                <i class="fas fa-shopping-cart"></i>
                <div class="stat-info">
                    <span class="stat-label">Orders Made</span>
                    <span class="stat-value"><?php echo $stats['total_orders'] ?? 0; ?></span>
                </div>
            </div>
            <div class="stat-card red">
                <i class="fas fa-chart-line"></i>
                <div class="stat-info">
                    <span class="stat-label">Daily Avg</span>
                    <span class="stat-value">₱<?php echo number_format($stats['daily_avg'] ?? 0, 2); ?></span>
                </div>
            </div>
        </div>

        <div class="sales-charts-row">
            <div class="chart-box">
                <h3>CCTV Sales Performance</h3>
                <canvas id="cctvChart" height="120"></canvas>
            </div>
            <div class="chart-box">
                <h3>Solar Sales Performance</h3>
                <canvas id="solarChart" height="120"></canvas>
            </div>
        </div>

        <div class="sales-table-header">
            <h2>Recent Transactions</h2>
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <div class="date-box">
                    <i class="fas fa-calendar"></i>
                    <input type="date" id="salesDate" onchange="filterSales()" title="Filter by date">
                </div>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="salesSearch" onkeyup="filterSales()" placeholder="Search transactions...">
                </div>
                <!-- Export Buttons -->
                <button class="export-btn btn-csv" onclick="exportCSV()">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button class="export-btn btn-pdf" onclick="exportPDF()">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
        </div>

        <div class="wide-table-container" style="max-height:400px; overflow-y:auto;">
            <table class="sales-table" id="salesTable">
                <thead style="position:sticky; top:0; background:#1a1a1a; z-index:1;">
                    <tr>
                        <th>Sale ID</th>
                        <th>Date & Time</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Qty Sold</th>
                        <th>Unit Price</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>
                <tbody id="sales-list">
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <tr class="sale-row" data-date="<?php echo date('Y-m-d', strtotime($row['date'])); ?>">
                            <td>#SL-<?php echo $row['sale_id']; ?></td>
                            <td><?php echo date('M d, Y | h:i A', strtotime($row['date'])); ?></td>
                            <td><?php echo $row['product_name']; ?></td>
                            <td><?php echo $row['category']; ?></td>
                            <td><?php echo $row['quantity']; ?></td>
                            <td>₱<?php echo number_format($row['price'], 2); ?></td>
                            <td><strong>₱<?php echo number_format($row['total_sales'], 2); ?></strong></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center; padding: 20px;">No sales recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/admin_profile.php'; ?>

<script src="../js/caz-alert.js?v=20260921-location"></script>

<script>

function handleLogout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = 'logout.php';
    }
}
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<!-- jsPDF + AutoTable for PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

<script>
const cctvLabels  = <?php echo json_encode($cctv_labels); ?>;
const cctvData    = <?php echo json_encode($cctv_data); ?>;
const solarLabels = <?php echo json_encode($solar_labels); ?>;
const solarData   = <?php echo json_encode($solar_data); ?>;

function makeChart(id, labels, data, color, label) {
    Chart.defaults.font.family = 'Inter, Segoe UI, sans-serif';
    new Chart(document.getElementById(id), {
        type: 'line',
        data: {
            labels: labels.length ? labels : ['No Data'],
            datasets: [{
                label: label,
                data: data.length ? data : [0],
                borderColor: color,
                backgroundColor: color + '22',
                borderWidth: 2,
                pointRadius: 4,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { labels: { color: '#52677e', font: { size: 14 } } }
            },
            scales: {
                x: { ticks: { color: '#52677e' }, grid: { color: '#e1e7ee' } },
                y: { ticks: { color: '#52677e' }, grid: { color: '#e1e7ee' }, beginAtZero: true }
            }
        }
    });
}

makeChart('cctvChart',  cctvLabels,  cctvData,  '#e53935', 'CCTV Sales (₱)');
makeChart('solarChart', solarLabels, solarData, '#ff9800', 'Solar Sales (₱)');

function filterSales() {
    const input = document.getElementById('salesSearch').value.toLowerCase();
    const date  = document.getElementById('salesDate').value;
    document.querySelectorAll('.sale-row').forEach(row => {
        const matchText = !input || row.innerText.toLowerCase().includes(input);
        const matchDate = !date  || row.dataset.date === date;
        row.style.display = (matchText && matchDate) ? '' : 'none';
    });
}

// ─── Helpers ────────────────────────────────────────────────
function getVisibleRows() {
    return [...document.querySelectorAll('.sale-row')]
        .filter(r => r.style.display !== 'none');
}

function getExportFilename(ext) {
    const date   = document.getElementById('salesDate').value;
    const search = document.getElementById('salesSearch').value.trim();
    let name = 'sales-transactions';
    if (date)   name += '_' + date;
    if (search) name += '_' + search.replace(/\s+/g, '-');
    return name + '.' + ext;
}

function buildFilterLabel() {
    const date   = document.getElementById('salesDate').value;
    const search = document.getElementById('salesSearch').value.trim();
    const parts  = [];
    if (date)   parts.push('Date: ' + date);
    if (search) parts.push('Search: "' + search + '"');
    return parts.length ? parts.join('  |  ') : 'All transactions';
}

// ─── CSV Export ─────────────────────────────────────────────
function exportCSV() {
    const rows = getVisibleRows();
    if (!rows.length) { alert('No visible rows to export.'); return; }

    const headers = ['Sale ID','Date & Time','Product Name','Category','Qty Sold','Unit Price','Total Amount'];
    const csvRows = [headers.join(',')];

    rows.forEach(row => {
        const cells = [...row.querySelectorAll('td')].map(td => {
            // Wrap in quotes and escape inner quotes
            return '"' + td.innerText.replace(/"/g, '""') + '"';
        });
        csvRows.push(cells.join(','));
    });

    const blob = new Blob([csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = getExportFilename('csv');
    a.click();
    URL.revokeObjectURL(url);
}

// ─── PDF Export ─────────────────────────────────────────────
function exportPDF() {
    const rows = getVisibleRows();
    if (!rows.length) { alert('No visible rows to export.'); return; }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });

    // Header
    doc.setFontSize(18);
    doc.setTextColor(40, 40, 40);
    doc.text('Caz Admin — Sales Transactions', 40, 40);

    doc.setFontSize(10);
    doc.setTextColor(100, 100, 100);
    doc.text('Exported: ' + new Date().toLocaleString(), 40, 58);
    doc.text('Filter: ' + buildFilterLabel(), 40, 72);

    // Table
    const headers = [['Sale ID','Date & Time','Product Name','Category','Qty Sold','Unit Price','Total Amount']];
    const body = rows.map(row =>
        [...row.querySelectorAll('td')].map(td => td.innerText)
    );

    doc.autoTable({
        head: headers,
        body: body,
        startY: 85,
        styles: {
            fontSize: 9,
            cellPadding: 5,
            overflow: 'linebreak'
        },
        headStyles: {
            fillColor: [30, 30, 30],
            textColor: [255, 255, 255],
            fontStyle: 'bold'
        },
        alternateRowStyles: {
            fillColor: [245, 245, 245]
        },
        columnStyles: {
            0: { cellWidth: 60 },
            1: { cellWidth: 110 },
            2: { cellWidth: 'auto' },
            3: { cellWidth: 70 },
            4: { cellWidth: 55 },
            5: { cellWidth: 70 },
            6: { cellWidth: 80 }
        }
    });

    doc.save(getExportFilename('pdf'));
}
</script>

</body>
</html>
