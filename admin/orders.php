<?php
require_once __DIR__ . '/../includes/admin_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include "../db_conn.php"; 

$order_query = "SELECT 
                    o.id, 
                    u.name, 
                    o.order_date, 
                    o.total_amount, 
                    o.status,
                    (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', p.product_name) SEPARATOR ', ') 
                     FROM order_items oi 
                     JOIN products p ON oi.product_id = p.id 
                     WHERE oi.order_id = o.id) AS item_list
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                ORDER BY o.order_date DESC";

$result = mysqli_query($conn, $order_query);

if (!$result) {
    die("Query Failed: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Orders - Caz Admin</title>
    <link rel="stylesheet" href="css/admin-style.css?v=20260910-release">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        #profile-panel    { z-index: 9999 !important; }
        #profile-backdrop { z-index: 9998 !important; }

        /* Clear filter button */
        .btn-clear-filter {
            display: none;
            align-items: center; gap: 5px;
            padding: 5px 11px; border-radius: 6px;
            border: 1px solid #555; background: transparent;
            color: #aaa; font-size: 12px; cursor: pointer;
            transition: all 0.15s;
        }
        .btn-clear-filter:hover { border-color: #e53935; color: #e53935; }
        .btn-clear-filter.visible { display: inline-flex; }

        /* Result count badge */
        #result-count {
            font-size: 12px; color: #777;
            margin-left: 4px; align-self: center;
        }
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

<div class="page-content" style="margin-top: 250px;">
    <div class="orders-wrapper"><div class="caz-page-heading"><div><span class="caz-eyebrow">Store management</span><h1>Orders</h1><p>Follow every purchase from checkout to delivery.</p></div></div>
        <div class="orders-header">
            <div style="display:flex; align-items:center; gap:8px; margin-top:10px; flex-wrap:wrap;">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="orderSearch" oninput="applyFilters()" placeholder="Search orders...">
                </div>
                <button class="btn-clear-filter" id="btnClearSearch" onclick="clearSearch()" title="Clear search">
                    <i class="fas fa-times"></i> Clear
                </button>
                <span id="result-count"></span>
            </div>
            <div class="status-tabs">
                <button class="tab-btn active" data-status="all"      onclick="setTab(this)">All</button>
                <button class="tab-btn"         data-status="pending"  onclick="setTab(this)">Pending <span id="badge-pending" class="tab-count"></span></button>
                <button class="tab-btn"         data-status="approved" onclick="setTab(this)">Approved</button>
                <button class="tab-btn"         data-status="shipped"  onclick="setTab(this)">Shipped</button>
                <button class="tab-btn"         data-status="canceled" onclick="setTab(this)">Canceled</button>
            </div>
        </div>

        <div class="table-container">
            <table class="orders-table">
                <thead style="position:sticky; top:0; background:#111; z-index:1;">
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="order-tbody">
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr class="order-row" data-status="<?php echo strtolower($row['status']); ?>">
                        <td>#CZ-<?php echo $row['id']; ?></td>
                        <td><?php echo $row['name']; ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['order_date'])); ?></td>
                        <td><?php echo $row['item_list']; ?></td>
                        <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                        <td><span class="badge <?php echo strtolower($row['status']); ?>"><?php echo $row['status']; ?></span></td>
                        <td><button class="btn-manage" onclick="window.location.href='manage_order.php?id=<?php echo $row['id']; ?>'">Manage</button></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/admin_profile.php'; ?>

<script src="../js/caz-alert.js?v=20260921-location"></script>
<script>
    <?php if (!empty($_SESSION['msg'])): ?>
        cazAlert.<?php echo $_SESSION['msg']['type']; ?>('<?php echo $_SESSION['msg']['text']; ?>');
        <?php unset($_SESSION['msg']); ?>
    <?php endif; ?>
</script>

<script>
// ── State ────────────────────────────────────────────────────
let activeStatus = 'all';

// ── Single source-of-truth filter ───────────────────────────
function applyFilters() {
    const search = document.getElementById('orderSearch').value.toLowerCase().trim();
    const rows   = document.querySelectorAll('.order-row');
    let visible  = 0;

    rows.forEach(row => {
        const statusMatch = activeStatus === 'all' || row.dataset.status === activeStatus;
        const textMatch   = !search || row.innerText.toLowerCase().includes(search);
        const show        = statusMatch && textMatch;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    // Result count
    const total = [...rows].filter(r => activeStatus === 'all' || r.dataset.status === activeStatus).length;
    const countEl = document.getElementById('result-count');
    if (search) {
        countEl.textContent = visible + ' of ' + total + ' result' + (total !== 1 ? 's' : '');
    } else {
        countEl.textContent = '';
    }

    // Show/hide clear button
    document.getElementById('btnClearSearch').classList.toggle('visible', search.length > 0);
}

// ── Tab click ────────────────────────────────────────────────
function setTab(btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    activeStatus = btn.dataset.status;
    applyFilters();
}

// ── Clear search ─────────────────────────────────────────────
function clearSearch() {
    document.getElementById('orderSearch').value = '';
    applyFilters();
}

// ── Profile panel ────────────────────────────────────────────


// ── On load: pending badge ────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const pendingCount = document.querySelectorAll('.order-row[data-status="pending"]').length;
    const badge = document.getElementById('badge-pending');
    if (badge && pendingCount > 0) {
        badge.textContent = pendingCount;
        badge.style.cssText = 'background:#e53935;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:4px;';
    }
});
</script>

</body>
</html>