<?php
require_once __DIR__ . '/../includes/admin_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
include "../db_conn.php";

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php?role=admin");
    exit();
}

$activity_query  = "SELECT c.*, u.name, u.email, u.contact_number 
                    FROM customer_logs c 
                    LEFT JOIN users u ON c.user_id = u.id 
                    ORDER BY c.date DESC";
$activity_result = mysqli_query($conn, $activity_query);

$login_query  = "SELECT l.*, u.name, u.email, u.contact_number 
                 FROM login_logs l 
                 LEFT JOIN users u ON l.user_id = u.id 
                 ORDER BY l.login_time DESC";
$login_result = mysqli_query($conn, $login_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Activity Logs - Caz Admin</title>
    <link rel="stylesheet" href="css/admin-style.css?v=20260910-release">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 20px;
            margin-bottom: 12px;
        }
        .section-header h2 { margin: 0; }
        .section-header .controls { display: flex; align-items: center; gap: 10px; }

        .search-box {
            display: flex; align-items: center; gap: 8px;
            background: #1e1e1e; border: 1px solid #333;
            border-radius: 6px; padding: 6px 12px;
        }
        .search-box input[type="text"] {
            background: transparent; border: none;
            outline: none; color: #fff;
            font-size: 13px; width: 200px;
        }
        .search-box i { color: #888; font-size: 13px; }

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
    <div class="logs-wrapper"><div class="caz-page-heading"><div><span class="caz-eyebrow">Store management</span><h1>Activity logs</h1><p>Review the latest customer and account activity.</p></div></div>

        <!-- Customer Activity -->
        <div class="section-header">
            <h2>Customer Activity</h2>
            <div class="controls">
                <div class="date-box">
                    <i class="fas fa-calendar"></i>
                    <input type="date" id="activityDate" onchange="filterActivity()" title="Filter by date">
                </div>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="activitySearch" onkeyup="filterActivity()" placeholder="Search activity...">
                </div>
            </div>
        </div>

        <div class="wide-table-container" style="margin-bottom:40px; max-height:70vh; overflow-y:scroll;">
    <table class="logs-table">
        <thead style="position:sticky; top:0; background:#111; z-index:1;">
                    <tr>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Activity</th>
                        <th>Date &amp; Time</th>
                    </tr>
                </thead>
                <tbody id="customer-logs-list">
                    <?php if ($activity_result && mysqli_num_rows($activity_result) > 0):
                          while ($row = mysqli_fetch_assoc($activity_result)): ?>
                    <tr class="log-row" data-date="<?php echo date('Y-m-d', strtotime($row['date'])); ?>">
                        <td><strong><?php echo htmlspecialchars($row['name'] ?? 'Unknown'); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['contact_number'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['activity'] ?? ''); ?></td>
                        <td><?php echo date('M d, Y | h:i A', strtotime($row['date'])); ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" style="text-align:center;color:#999;padding:20px;">No activity yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Login/Logout History -->
        <div class="section-header">
            <h2>Login / Logout History</h2>
            <div class="controls">
                <div class="date-box">
                    <i class="fas fa-calendar"></i>
                    <input type="date" id="loginDate" onchange="filterLogin()" title="Filter by date">
                </div>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="loginSearch" onkeyup="filterLogin()" placeholder="Search history...">
                </div>
            </div>
        </div>

        <div class="wide-table-container" style="max-height:70vh; overflow-y:scroll;">
    <table class="logs-table">
        <thead style="position:sticky; top:0; background:#111; z-index:1;">
                    <tr>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Login Time</th>
                        <th>Logout Time</th>
                    </tr>
                </thead>
                <tbody id="login-logs-list">
                    <?php if ($login_result && mysqli_num_rows($login_result) > 0):
                          while ($row = mysqli_fetch_assoc($login_result)): ?>
                    <tr class="log-row" data-date="<?php echo date('Y-m-d', strtotime($row['login_time'])); ?>">
                        <td><strong><?php echo htmlspecialchars($row['name'] ?? 'Unknown'); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['contact_number'] ?? 'N/A'); ?></td>
                        <td><?php echo date('M d, Y | h:i A', strtotime($row['login_time'])); ?></td>
                        <td>
                            <?php echo ($row['logout_time'] && $row['logout_time'] !== '0000-00-00 00:00:00')
                                ? date('M d, Y | h:i A', strtotime($row['logout_time']))
                                : '<span style="color:#4caf50;font-weight:bold;">Still Online</span>'; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" style="text-align:center;color:#999;padding:20px;">No login history yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- PROFILE -->
<?php require __DIR__ . '/../includes/admin_profile.php'; ?>

<script src="../js/caz-alert.js?v=20260921-location"></script>

<script>
function filterActivity() {
    const input = document.getElementById('activitySearch').value.toLowerCase();
    const date  = document.getElementById('activityDate').value;
    document.querySelectorAll('#customer-logs-list .log-row').forEach(row => {
        const matchText = !input || row.textContent.toLowerCase().includes(input);
        const matchDate = !date  || row.dataset.date === date;
        row.style.display = (matchText && matchDate) ? '' : 'none';
    });
}

function filterLogin() {
    const input = document.getElementById('loginSearch').value.toLowerCase();
    const date  = document.getElementById('loginDate').value;
    document.querySelectorAll('#login-logs-list .log-row').forEach(row => {
        const matchText = !input || row.textContent.toLowerCase().includes(input);
        const matchDate = !date  || row.dataset.date === date;
        row.style.display = (matchText && matchDate) ? '' : 'none';
    });
}



function handleLogout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = 'logout.php';
    }
}
</script>

</body>
</html>